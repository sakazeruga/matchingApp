<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: index.php');
    exit;
}

$pdo = get_pdo();

$stmt = $pdo->prepare(
    "SELECT a.id, a.nickname, a.email, a.submitted_at,
            r.status, r.reject_reason, r.created_at AS result_at
     FROM applicants a
     JOIN results r ON r.applicant_id = a.id
     WHERE a.id = :id"
);
$stmt->execute([':id' => $id]);
$applicant = $stmt->fetch();

if (!$applicant) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT question_id, answer FROM responses
     WHERE applicant_id = :id ORDER BY question_id"
);
$stmt->execute([':id' => $id]);
$responses = $stmt->fetchAll();

$response_map = [];
foreach ($responses as $r) {
    $response_map[(int)$r['question_id']] = $r['answer'];
}

$token = admin_csrf_token();

$status_labels = [
    'pending'  => ['label' => '審査待ち', 'color' => '#e67e22'],
    'approved' => ['label' => '承認済み', 'color' => '#27ae60'],
    'rejected' => ['label' => '否認済み', 'color' => '#c0392b'],
];
$sl = $status_labels[$applicant['status']];

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>申請詳細 #<?= (int)$id ?> | 管理画面</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Hiragino Kaku Gothic ProN','Meiryo',sans-serif; background: #f0f4f8; color: #333; }
        .topbar {
            background: #2c3e50; color: #fff;
            padding: .8rem 1.5rem; display: flex;
            align-items: center; justify-content: space-between;
        }
        .topbar a { color: #aab; font-size: .85rem; text-decoration: none; }
        .topbar a:hover { color: #fff; }
        .topbar h1 { font-size: 1rem; }
        .container { max-width: 720px; margin: 1.5rem auto; padding: 0 1rem; }
        .flash {
            padding: .7rem 1rem; border-radius: 6px;
            margin-bottom: 1rem; font-size: .9rem;
        }
        .flash.success { background: #eafaf1; border: 1px solid #a9dfbf; color: #1e8449; }
        .flash.error   { background: #fdedec; border: 1px solid #f5b7b1; color: #922b21; }
        .card {
            background: #fff; border-radius: 10px;
            box-shadow: 0 1px 6px rgba(0,0,0,.07);
            padding: 1.5rem; margin-bottom: 1.2rem;
        }
        .card h2 { font-size: 1rem; color: #2c3e50; margin-bottom: 1rem;
                   padding-bottom: .5rem; border-bottom: 2px solid #eee; }
        .info-grid {
            display: grid; grid-template-columns: 140px 1fr;
            gap: .5rem 1rem; font-size: .9rem;
        }
        .info-grid dt { color: #888; }
        .info-grid dd { color: #333; word-break: break-all; }
        .badge {
            display: inline-block; padding: 2px 10px; border-radius: 12px;
            font-size: .82rem; font-weight: bold; color: #fff;
        }
        .qa-item { margin-bottom: 1.1rem; }
        .qa-item:last-child { margin-bottom: 0; }
        .q-label {
            font-size: .8rem; font-weight: bold;
            color: #fff; background: #2c3e50;
            display: inline-block; padding: 2px 8px;
            border-radius: 4px; margin-bottom: .3rem;
        }
        .q-text { font-size: .88rem; color: #555; margin-bottom: .3rem; }
        .a-text {
            background: #f8f9fb; border-left: 3px solid #4a90d9;
            padding: .5rem .8rem; border-radius: 0 4px 4px 0;
            font-size: .9rem; white-space: pre-wrap; word-break: break-word;
        }
        .actions { display: flex; gap: .8rem; flex-wrap: wrap; }
        .btn {
            padding: .6rem 1.4rem; border: none; border-radius: 6px;
            font-size: .95rem; cursor: pointer; transition: opacity .15s;
        }
        .btn:hover { opacity: .85; }
        .btn-approve { background: #27ae60; color: #fff; }
        .btn-reject  { background: #c0392b; color: #fff; }
        .back { color: #2980b9; text-decoration: none; font-size: .88rem; }
        .back:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="topbar">
    <h1>申請詳細 #<?= (int)$id ?></h1>
    <div style="display:flex;gap:1.2rem">
        <a href="index.php">← 一覧へ</a>
        <a href="logout.php">ログアウト</a>
    </div>
</div>
<div class="container">
    <?php if ($flash): ?>
    <div class="flash <?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
    <?php endif; ?>

    <!-- 基本情報 -->
    <div class="card">
        <h2>申請者情報</h2>
        <dl class="info-grid">
            <dt>ID</dt>
            <dd><?= (int)$applicant['id'] ?></dd>
            <dt>ニックネーム</dt>
            <dd><?= h($applicant['nickname']) ?></dd>
            <dt>メールアドレス</dt>
            <dd><?= h($applicant['email']) ?></dd>
            <dt>申請日時</dt>
            <dd><?= h($applicant['submitted_at']) ?></dd>
            <dt>ステータス</dt>
            <dd>
                <span class="badge" style="background:<?= $sl['color'] ?>">
                    <?= h($sl['label']) ?>
                </span>
            </dd>
            <?php if ($applicant['reject_reason']): ?>
            <dt>否認理由</dt>
            <dd><?= h($applicant['reject_reason']) ?></dd>
            <?php endif; ?>
        </dl>
    </div>

    <!-- 回答内容 -->
    <div class="card">
        <h2>回答内容</h2>
        <?php foreach (QUESTIONS as $qid => $q): ?>
        <div class="qa-item">
            <div class="q-label">Q<?= $qid ?></div>
            <div class="q-text"><?= h($q['text']) ?></div>
            <div class="a-text"><?= h($response_map[$qid] ?? '（未回答）') ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- アクション（審査待ちのみ表示） -->
    <?php if ($applicant['status'] === 'pending'): ?>
    <div class="card">
        <h2>審査アクション</h2>
        <div class="actions">
            <form method="post" action="action.php"
                  onsubmit="return confirm('承認しますか？')">
                <input type="hidden" name="csrf_token"    value="<?= h($token) ?>">
                <input type="hidden" name="applicant_id"  value="<?= (int)$id ?>">
                <input type="hidden" name="action"        value="approve">
                <button class="btn btn-approve" type="submit">承認する</button>
            </form>
            <form method="post" action="action.php"
                  onsubmit="return confirm('否認しますか？')">
                <input type="hidden" name="csrf_token"    value="<?= h($token) ?>">
                <input type="hidden" name="applicant_id"  value="<?= (int)$id ?>">
                <input type="hidden" name="action"        value="reject">
                <button class="btn btn-reject" type="submit">否認する</button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
