<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/ScoringEngine.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$applicant_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$type         = $_GET['type'] ?? '';

if (!$applicant_id || !in_array($type, ['love_score', 'cute_score'], true)) {
    header('Location: index.php');
    exit;
}

$sess_key = "assessment_{$type}_{$applicant_id}";
$answers  = $_SESSION[$sess_key]['answers'] ?? [];

if (empty($answers)) {
    header("Location: step.php?id={$applicant_id}&type={$type}");
    exit;
}

$engine = new ScoringEngine($type);
$result = $engine->calculate($answers);

$pdo = get_pdo();

try {
    $pdo->beginTransaction();

    // セッション作成
    $stmt = $pdo->prepare(
        "INSERT INTO assessment_sessions (applicant_id, assessment_type, status, completed_at)
         VALUES (:aid, :type, :status, NOW())"
    );
    $status = $result['has_warning'] ? 'flagged' : 'completed';
    $stmt->execute([':aid'=>$applicant_id, ':type'=>$type, ':status'=>$status]);
    $session_id = (int)$pdo->lastInsertId();

    // 回答を保存
    $stmt = $pdo->prepare(
        "INSERT INTO assessment_responses (session_id, question_id, score) VALUES (:sid, :qid, :score)"
    );
    foreach ($answers as $qid => $score) {
        $stmt->execute([':sid'=>$session_id, ':qid'=>$qid, ':score'=>$score]);
    }

    // カテゴリ別スコアを保存
    $stmt = $pdo->prepare(
        "INSERT INTO assessment_scores
         (session_id, applicant_id, assessment_type, category_code, category_score,
          total_score, has_warning, warning_details)
         VALUES (:sid, :aid, :type, :cat, :catscore, :total, :warn, :details)"
    );
    foreach ($result['categories'] as $cat => $cat_score) {
        $stmt->execute([
            ':sid'      => $session_id,
            ':aid'      => $applicant_id,
            ':type'     => $type,
            ':cat'      => $cat,
            ':catscore' => $cat_score,
            ':total'    => $result['total'],
            ':warn'     => $result['has_warning'] ? 1 : 0,
            ':details'  => json_encode($result['warnings'], JSON_UNESCAPED_UNICODE),
        ]);
    }

    $pdo->commit();

    // セッションをクリア
    unset($_SESSION[$sess_key]);

    header("Location: result.php?session_id={$session_id}");
    exit;

} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('Assessment save error: ' . $e->getMessage());
    http_response_code(500);
    exit('保存中にエラーが発生しました。');
}
