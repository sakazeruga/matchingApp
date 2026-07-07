<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/ScoringEngine.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// applicant_id を GET パラメータで受け取る（将来はログインセッションから取得）
$applicant_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$applicant_id) {
    http_response_code(400);
    exit('申請IDが必要です。');
}

// 既に完了済みのセッションがあるか確認
$pdo = get_pdo();
$stmt = $pdo->prepare(
    "SELECT id, assessment_type, status FROM assessment_sessions
     WHERE applicant_id = :id AND status = 'completed'
     ORDER BY id DESC LIMIT 1"
);
$stmt->execute([':id' => $applicant_id]);
$done = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>アセスメント | 雨雲はれる</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Hiragino Kaku Gothic ProN','Meiryo',sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh; display: flex; align-items: center;
            justify-content: center; padding: 2rem 1rem;
        }
        .card {
            background: #fff; border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,.2);
            padding: 2.5rem 2rem; max-width: 560px; width: 100%;
        }
        .logo { font-size: .85rem; color: #999; margin-bottom: .5rem; }
        h1 { font-size: 1.5rem; color: #2c3e50; margin-bottom: .5rem; }
        .sub { color: #888; font-size: .9rem; margin-bottom: 2rem; line-height: 1.6; }
        .step-list { list-style: none; margin-bottom: 2rem; }
        .step-list li {
            display: flex; align-items: flex-start; gap: .8rem;
            padding: .7rem 0; border-bottom: 1px solid #f0f0f0; font-size: .9rem;
        }
        .step-list li:last-child { border-bottom: none; }
        .step-num {
            min-width: 24px; height: 24px; border-radius: 50%;
            background: #667eea; color: #fff; font-size: .75rem;
            display: flex; align-items: center; justify-content: center;
            font-weight: bold; flex-shrink: 0; margin-top: 1px;
        }
        .type-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem; }
        .type-card {
            border: 2px solid #e8edf2; border-radius: 10px;
            padding: 1.2rem; cursor: pointer; transition: all .15s;
            text-decoration: none; color: inherit;
        }
        .type-card:hover { border-color: #667eea; background: #f8f6ff; }
        .type-card .icon { font-size: 1.8rem; margin-bottom: .4rem; }
        .type-card .name { font-weight: bold; font-size: .95rem; color: #2c3e50; }
        .type-card .desc { font-size: .78rem; color: #888; margin-top: .3rem; line-height: 1.4; }
        .type-card .count { font-size: .78rem; color: #667eea; margin-top: .4rem; }
        .done-badge {
            display: inline-block; background: #27ae60;
            color: #fff; font-size: .7rem; padding: 2px 6px;
            border-radius: 10px; margin-left: .3rem; vertical-align: middle;
        }
        .note { font-size: .78rem; color: #aaa; text-align: center; margin-top: 1rem; }
    </style>
</head>
<body>
<div class="card">
    <div class="logo">雨雲はれる</div>
    <h1>入会アセスメント</h1>
    <p class="sub">
        あなたの「関係性スタイル」を測定します。<br>
        正直に答えることで、より良いマッチングに繋がります。
    </p>

    <ul class="step-list">
        <li><span class="step-num">1</span>各設問に直感で回答（所要時間：約15分/回）</li>
        <li><span class="step-num">2</span>2種類のアセスメントを両方完了してください</li>
        <li><span class="step-num">3</span>スコアは本登録審査に使用されます</li>
    </ul>

    <div class="type-grid">
        <a class="type-card" href="step.php?id=<?= (int)$applicant_id ?>&type=love_score">
            <div class="icon">💡</div>
            <div class="name">
                恋愛偏差値
                <?php if ($done && $done['assessment_type'] === 'love_score'): ?>
                    <span class="done-badge">完了</span>
                <?php endif; ?>
            </div>
            <div class="desc">感情の成熟度・自立性・誠実さなど9項目</div>
            <div class="count">44設問</div>
        </a>
        <a class="type-card" href="step.php?id=<?= (int)$applicant_id ?>&type=cute_score">
            <div class="icon">💖</div>
            <div class="name">
                可愛さ指数
                <?php if ($done && $done['assessment_type'] === 'cute_score'): ?>
                    <span class="done-badge">完了</span>
                <?php endif; ?>
            </div>
            <div class="desc">感謝表現・心理的安全性・自己開示など7項目</div>
            <div class="count">30設問</div>
        </a>
    </div>

    <p class="note">回答内容は厳重に管理され、第三者に開示されることはありません。</p>
</div>
</body>
</html>
