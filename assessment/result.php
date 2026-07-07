<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/questions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$session_id = filter_input(INPUT_GET, 'session_id', FILTER_VALIDATE_INT);
if (!$session_id) { header('Location: index.php'); exit; }

$pdo = get_pdo();

$stmt = $pdo->prepare(
    "SELECT s.applicant_id, s.assessment_type, s.status,
            a.nickname
     FROM assessment_sessions s
     JOIN applicants a ON a.id = s.applicant_id
     WHERE s.id = :sid"
);
$stmt->execute([':sid' => $session_id]);
$session = $stmt->fetch();
if (!$session) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare(
    "SELECT category_code, category_score, total_score, has_warning, warning_details
     FROM assessment_scores WHERE session_id = :sid"
);
$stmt->execute([':sid' => $session_id]);
$rows = $stmt->fetchAll();

$categories = [];
$total_score = 0;
$has_warning = false;
$warnings    = [];

foreach ($rows as $row) {
    $categories[$row['category_code']] = (float)$row['category_score'];
    $total_score = (float)$row['total_score'];
    if ($row['has_warning']) {
        $has_warning = true;
        $details = json_decode($row['warning_details'] ?? '[]', true);
        foreach ($details as $w) {
            $warnings[$w['sub']] = $w;
        }
    }
}

$type       = $session['assessment_type'];
$weights    = CATEGORY_WEIGHTS[$type];
$cat_labels = CATEGORY_LABELS;

// レーダーチャート用データ（SVG）
$chart_cats = array_keys($weights);
$n          = count($chart_cats);
$cx = 160; $cy = 160; $r_max = 120;

function polar(float $cx, float $cy, float $r, int $i, int $n): array {
    $angle = deg2rad(-90 + 360 / $n * $i);
    return ['x' => round($cx + $r * cos($angle), 2), 'y' => round($cy + $r * sin($angle), 2)];
}

$polygon_points = '';
foreach ($chart_cats as $i => $cat) {
    $score = $categories[$cat] ?? 0;
    $p = polar($cx, $cy, $r_max * $score / 100, $i, $n);
    $polygon_points .= "{$p['x']},{$p['y']} ";
}

$score_grade = match(true) {
    $total_score >= 80 => ['label'=>'S', 'color'=>'#8e44ad', 'msg'=>'卓越した関係性スタイルをお持ちです'],
    $total_score >= 65 => ['label'=>'A', 'color'=>'#2980b9', 'msg'=>'高い関係性リテラシーをお持ちです'],
    $total_score >= 50 => ['label'=>'B', 'color'=>'#27ae60', 'msg'=>'バランスの取れた関係性スタイルです'],
    $total_score >= 35 => ['label'=>'C', 'color'=>'#e67e22', 'msg'=>'成長の余地があります'],
    default            => ['label'=>'D', 'color'=>'#e74c3c', 'msg'=>'育成プログラムへの参加をお勧めします'],
};
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>アセスメント結果 | 雨雲はれる</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Hiragino Kaku Gothic ProN','Meiryo',sans-serif;
            background: #f4f6fb; color: #333; padding: 2rem 1rem 4rem;
        }
        .container { max-width: 680px; margin: 0 auto; }
        .header-card {
            background: linear-gradient(135deg,#667eea,#764ba2);
            border-radius: 16px; padding: 2rem; color: #fff;
            text-align: center; margin-bottom: 1.2rem;
        }
        .header-card .type-label { font-size: .85rem; opacity: .8; margin-bottom: .4rem; }
        .header-card h1 { font-size: 1.4rem; margin-bottom: 1rem; }
        .score-circle {
            width: 110px; height: 110px; border-radius: 50%;
            background: rgba(255,255,255,.15); border: 3px solid rgba(255,255,255,.5);
            margin: 0 auto 1rem; display: flex; flex-direction: column;
            align-items: center; justify-content: center;
        }
        .score-circle .num { font-size: 2rem; font-weight: bold; }
        .score-circle .label { font-size: .78rem; opacity: .8; }
        .grade { font-size: 1.2rem; font-weight: bold; margin-bottom: .3rem; }
        .grade-msg { font-size: .85rem; opacity: .85; }
        .card {
            background: #fff; border-radius: 10px;
            box-shadow: 0 1px 4px rgba(0,0,0,.06);
            padding: 1.5rem; margin-bottom: 1rem;
        }
        .card h2 { font-size: 1rem; color: #2c3e50; margin-bottom: 1rem;
                   padding-bottom: .5rem; border-bottom: 2px solid #eee; }
        .chart-wrap { display: flex; justify-content: center; margin-bottom: 1rem; }
        /* Category bars */
        .cat-row { margin-bottom: .8rem; }
        .cat-head { display: flex; justify-content: space-between; font-size: .85rem;
                    color: #555; margin-bottom: .3rem; }
        .cat-bar { height: 8px; background: #eee; border-radius: 4px; overflow: hidden; }
        .cat-fill { height: 100%; border-radius: 4px;
                    background: linear-gradient(90deg,#667eea,#764ba2); transition: width .6s; }
        /* Warnings */
        .warning-box {
            background: #fff8e1; border: 1px solid #ffe082; border-left: 4px solid #f39c12;
            border-radius: 6px; padding: 1rem; margin-bottom: .7rem;
        }
        .warning-box .w-title { font-weight: bold; color: #e67e22; font-size: .88rem; margin-bottom: .3rem; }
        .warning-box .w-msg { font-size: .83rem; color: #555; }
        .serious-warning {
            background: #fdecea; border-color: #ef9a9a; border-left-color: #e74c3c;
        }
        .serious-warning .w-title { color: #c0392b; }
        .btn-back {
            display: block; text-align: center; padding: .8rem;
            background: #2c3e50; color: #fff; border-radius: 8px;
            text-decoration: none; margin-top: 1.5rem; font-size: .95rem;
        }
    </style>
</head>
<body>
<div class="container">
    <!-- ヘッダー -->
    <div class="header-card">
        <div class="type-label"><?= h($type === 'love_score' ? '恋愛偏差値' : '可愛さ指数') ?>アセスメント</div>
        <h1><?= h($session['nickname']) ?> さんの結果</h1>
        <div class="score-circle">
            <span class="num"><?= round($total_score) ?></span>
            <span class="label">/ 100</span>
        </div>
        <div class="grade" style="color:<?= $score_grade['color'] ?>">
            グレード <?= h($score_grade['label']) ?>
        </div>
        <div class="grade-msg"><?= h($score_grade['msg']) ?></div>
    </div>

    <!-- レーダーチャート -->
    <div class="card">
        <h2>カテゴリ別スコア</h2>
        <div class="chart-wrap">
            <svg viewBox="0 0 320 320" width="260" height="260">
                <!-- グリッド線 -->
                <?php foreach ([20,40,60,80,100] as $pct): ?>
                    <?php
                    $pts = '';
                    for ($i = 0; $i < $n; $i++) {
                        $p = polar($cx, $cy, $r_max * $pct / 100, $i, $n);
                        $pts .= "{$p['x']},{$p['y']} ";
                    }
                    ?>
                    <polygon points="<?= $pts ?>" fill="none" stroke="#e8edf2" stroke-width="1"/>
                <?php endforeach; ?>
                <!-- 軸 -->
                <?php for ($i = 0; $i < $n; $i++):
                    $p = polar($cx, $cy, $r_max, $i, $n); ?>
                    <line x1="<?= $cx ?>" y1="<?= $cy ?>" x2="<?= $p['x'] ?>" y2="<?= $p['y'] ?>"
                          stroke="#ddd" stroke-width="1"/>
                <?php endfor; ?>
                <!-- スコア多角形 -->
                <polygon points="<?= trim($polygon_points) ?>"
                         fill="rgba(102,126,234,0.3)" stroke="#667eea" stroke-width="2"/>
                <!-- ラベル -->
                <?php foreach ($chart_cats as $i => $cat):
                    $p = polar($cx, $cy, $r_max + 22, $i, $n);
                    $score = round($categories[$cat] ?? 0);
                    $label = mb_strimwidth($cat_labels[$cat] ?? $cat, 0, 8, '…');
                ?>
                    <text x="<?= $p['x'] ?>" y="<?= $p['y'] ?>" text-anchor="middle"
                          dominant-baseline="middle" font-size="9" fill="#555"><?= h($label) ?></text>
                    <text x="<?= $p['x'] ?>" y="<?= $p['y'] + 11 ?>" text-anchor="middle"
                          font-size="9" fill="#667eea" font-weight="bold"><?= $score ?></text>
                <?php endforeach; ?>
            </svg>
        </div>

        <?php foreach ($chart_cats as $cat): ?>
        <div class="cat-row">
            <div class="cat-head">
                <span><?= h($cat_labels[$cat] ?? $cat) ?></span>
                <span><?= round($categories[$cat] ?? 0) ?>点</span>
            </div>
            <div class="cat-bar">
                <div class="cat-fill" style="width:<?= round($categories[$cat] ?? 0) ?>%"></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- 警告 -->
    <?php if ($has_warning && count($warnings) > 0): ?>
    <div class="card">
        <h2>⚠️ 審査フラグ</h2>
        <?php foreach ($warnings as $w): ?>
        <div class="warning-box <?= $w['type'] === 'sincerity_warning' ? 'serious-warning' : '' ?>">
            <div class="w-title"><?= h($w['type'] === 'sincerity_warning' ? '誠実性警告' : '要注意フラグ') ?></div>
            <div class="w-msg"><?= h($w['message']) ?></div>
        </div>
        <?php endforeach; ?>
        <p style="font-size:.82rem;color:#888;margin-top:.8rem">
            上記フラグは審査担当者が確認します。本登録審査に影響する場合があります。
        </p>
    </div>
    <?php endif; ?>

    <a class="btn-back" href="index.php?id=<?= (int)$session['applicant_id'] ?>">
        アセスメントトップに戻る
    </a>
</div>
</body>
</html>
