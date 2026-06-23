<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

// POST のみ受け付ける
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// セッション開始（レート制限・CSRF 両方に必要）
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// レート制限（CSRF トークン消費前にチェック）
enforce_rate_limit();

// CSRF 検証
verify_csrf();

// ────────────────────────────────────────────
// 1. 入力値の取得・バリデーション
// ────────────────────────────────────────────
$nickname = trim($_POST['nickname'] ?? '');
if ($nickname === '' || mb_strlen($nickname) > 50) {
    http_response_code(400);
    exit('ニックネームが不正です。');
}

$email = trim($_POST['email'] ?? '');
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
    http_response_code(400);
    exit('メールアドレスが不正です。');
}

$answers = [];   // [question_id => answer_string]
foreach (QUESTIONS as $qid => $q) {
    $key = 'q' . $qid;
    $val = trim($_POST[$key] ?? '');
    if ($val === '') {
        http_response_code(400);
        exit('未回答の設問があります。');
    }
    if ($q['type'] === 'choice' && !in_array($val, $q['options'], true)) {
        http_response_code(400);
        exit('不正な選択肢が送信されました。');
    }
    if ($q['type'] === 'text' && mb_strlen($val) > 1000) {
        http_response_code(400);
        exit('回答が長すぎます。');
    }
    $answers[$qid] = $val;
}

// ────────────────────────────────────────────
// 2. 禁忌肢チェック（選択肢問題）
// ────────────────────────────────────────────
$reject_reason = null;
foreach (QUESTIONS as $qid => $q) {
    if ($q['type'] !== 'choice') {
        continue;
    }
    if (in_array($answers[$qid], $q['banned'], true)) {
        $reject_reason = 'Q' . $qid . '：' . $answers[$qid];
        break;
    }
}

// ────────────────────────────────────────────
// 3. Gemini APIスクリーニング（禁忌なしの場合のみ）
// ────────────────────────────────────────────
if ($reject_reason === null) {
    $text_answers = [];
    foreach (QUESTIONS as $qid => $q) {
        if ($q['type'] === 'text') {
            $text_answers[] = 'Q' . $qid . '「' . $q['text'] . '」' . "\n" . $answers[$qid];
        }
    }
    $combined_text = implode("\n\n", $text_answers);

    $gemini_result = call_gemini($combined_text);
    if ($gemini_result === null) {
        // API エラー時は安全側に倒して pending とし、人間審査に委ねる
        error_log('Gemini API error: response was null');
    } elseif ($gemini_result['flagged'] === true) {
        $reject_reason = 'AI審査：' . mb_strimwidth($gemini_result['reason'] ?? '', 0, 80, '…');
    }
}

// ────────────────────────────────────────────
// 4. DB 保存（トランザクション）
// ────────────────────────────────────────────
$pdo = get_pdo();

try {
    $pdo->beginTransaction();

    // applicants
    $stmt = $pdo->prepare(
        'INSERT INTO applicants (nickname, email, submitted_at) VALUES (:nickname, :email, NOW())'
    );
    $stmt->execute([':nickname' => $nickname, ':email' => $email]);
    $applicant_id = (int)$pdo->lastInsertId();

    // responses
    $stmt = $pdo->prepare(
        'INSERT INTO responses (applicant_id, question_id, answer) VALUES (:aid, :qid, :ans)'
    );
    foreach ($answers as $qid => $ans) {
        $stmt->execute([
            ':aid' => $applicant_id,
            ':qid' => $qid,
            ':ans' => $ans,
        ]);
    }

    // results
    $status = ($reject_reason === null) ? 'pending' : 'rejected';
    $stmt = $pdo->prepare(
        'INSERT INTO results (applicant_id, status, reject_reason, created_at)
         VALUES (:aid, :status, :reason, NOW())'
    );
    $stmt->execute([
        ':aid'    => $applicant_id,
        ':status' => $status,
        ':reason' => $reject_reason,
    ]);

    $pdo->commit();

    // 管理者への新規申請通知
    if (MAIL_ADMIN !== '') {
        require_once __DIR__ . '/mailer.php';
        mail_notify_admin($nickname, $email, $status);
    }
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('DB error: ' . $e->getMessage());
    http_response_code(500);
    exit('サーバーエラーが発生しました。しばらくしてから再度お試しください。');
}

// ────────────────────────────────────────────
// 5. リダイレクト
// ────────────────────────────────────────────
if ($status === 'rejected') {
    header('Location: rejected.php');
} else {
    header('Location: complete.php');
}
exit;

// ────────────────────────────────────────────
// Gemini API 呼び出し
// 戻り値: ['flagged' => bool, 'reason' => string] | null（エラー時）
// ────────────────────────────────────────────
function call_gemini(string $text): ?array
{
    if (GEMINI_API_KEY === '') {
        error_log('GEMINI_API_KEY が設定されていません');
        return null;
    }

    $system_instruction =
        "以下の文章に、女性蔑視・暴力的・性的に不適切な内容が含まれるか判定してください。\n" .
        "JSONのみ返してください。\n" .
        '{"flagged": true/false, "reason": "理由（flaggedがtrueの場合のみ）"}';

    $payload = json_encode([
        'system_instruction' => [
            'parts' => [['text' => $system_instruction]],
        ],
        'contents' => [
            ['role' => 'user', 'parts' => [['text' => $text]]],
        ],
        'generationConfig' => [
            'responseMimeType' => 'application/json',
            'temperature'      => 0.0,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    $ctx = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\n",
            'content'       => $payload,
            'timeout'       => 10,
            'ignore_errors' => true,
        ],
    ]);

    $raw = @file_get_contents(GEMINI_API_URL, false, $ctx);
    if ($raw === false) {
        error_log('Gemini API: HTTP request failed');
        return null;
    }

    try {
        $body = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        error_log('Gemini API: JSON parse error: ' . $e->getMessage());
        return null;
    }

    // エラーレスポンス
    if (isset($body['error'])) {
        error_log('Gemini API error: ' . ($body['error']['message'] ?? $raw));
        return null;
    }

    // 生成テキスト取り出し
    $generated = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;
    if ($generated === null) {
        error_log('Gemini API: unexpected response structure: ' . $raw);
        return null;
    }

    try {
        $result = json_decode($generated, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        error_log('Gemini API: inner JSON parse error: ' . $e->getMessage());
        return null;
    }

    if (!isset($result['flagged']) || !is_bool($result['flagged'])) {
        error_log('Gemini API: invalid result schema: ' . $generated);
        return null;
    }

    return $result;
}
