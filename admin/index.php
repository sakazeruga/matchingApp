<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$allowed_statuses = ['', 'pending', 'approved', 'rejected'];
$filter = $_GET['status'] ?? '';
if (!in_array($filter, $allowed_statuses, true)) {
    $filter = '';
}

$pdo = get_pdo();

// ステータス別件数
$counts = $pdo->query(
    "SELECT status, COUNT(*) AS cnt FROM results GROUP BY status"
)->fetchAll();
$count_map = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
foreach ($counts as $row) {
    $count_map[$row['status']] = (int)$row['cnt'];
}
$count_all = array_sum($count_map);

// 一覧取得
if ($filter === '') {
    $stmt = $pdo->prepare(
        "SELECT a.id, a.nickname, a.email, a.submitted_at, r.status
         FROM applicants a
         JOIN results r ON r.applicant_id = a.id
         ORDER BY a.submitted_at DESC"
    );
    $stmt->execute();
} else {
    $stmt = $pdo->prepare(
        "SELECT a.id, a.nickname, a.email, a.submitted_at, r.status
         FROM applicants a
         JOIN results r ON r.applicant_id = a.id
         WHERE r.status = :status
         ORDER BY a.submitted_at DESC"
    );
    $stmt->execute([':status' => $filter]);
}
$applicants = $stmt->fetchAll();

$status_labels = [
    'pending'  => ['label' => '審査待ち', 'color' => '#e67e22'],
    'approved' => ['label' => '承認済み', 'color' => '#27ae60'],
    'rejected' => ['label' => '否認済み', 'color' => '#c0392b'],
];

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>申請一覧 | 管理画面</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Hiragino Kaku Gothic ProN','Meiryo',sans-serif; background: #f0f4f8; color: #333; }
        .topbar {
            background: #2c3e50; color: #fff;
            padding: .8rem 1.5rem; display: flex;
            align-items: center; justify-content: space-between;
        }
        .topbar h1 { font-size: 1rem; }
        .topbar a { color: #aab; font-size: .85rem; text-decoration: none; }
        .topbar a:hover { color: #fff; }
        .container { max-width: 960px; margin: 1.5rem auto; padding: 0 1rem; }
        .tabs { display: flex; gap: .5rem; margin-bottom: 1.2rem; flex-wrap: wrap; }
        .tab {
            padding: .4rem 1rem; border-radius: 20px;
            font-size: .85rem; text-decoration: none;
            background: #fff; color: #555; border: 1px solid #ddd;
            transition: background .15s;
        }
        .tab.active { background: #2c3e50; color: #fff; border-color: #2c3e50; }
        .tab:hover:not(.active) { background: #e8edf2; }
        .flash {
            padding: .7rem 1rem; border-radius: 6px;
            margin-bottom: 1rem; font-size: .9rem;
        }
        .flash.success { background: #eafaf1; border: 1px solid #a9dfbf; color: #1e8449; }
        .flash.error   { background: #fdedec; border: 1px solid #f5b7b1; color: #922b21; }
        table { width: 100%; border-collapse: collapse; background: #fff;
                border-radius: 8px; overflow: hidden;
                box-shadow: 0 1px 6px rgba(0,0,0,.07); }
        th { background: #2c3e50; color: #fff; padding: .7rem 1rem;
             font-size: .85rem; text-align: left; }
        td { padding: .7rem 1rem; font-size: .9rem; border-bottom: 1px solid #eee; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f8f9fb; }
        .badge {
            display: inline-block; padding: 2px 8px; border-radius: 12px;
            font-size: .78rem; font-weight: bold; color: #fff;
        }
        .detail-link {
            color: #2980b9; text-decoration: none; font-size: .85rem;
        }
        .detail-link:hover { text-decoration: underline; }
        .empty { text-align: center; padding: 2rem; color: #999; font-size: .9rem; }
    </style>
</head>
<body>
<div class="topbar">
    <h1>雨雲はれる 管理画面</h1>
    <a href="logout.php">ログアウト</a>
</div>
<div class="container">
    <?php if ($flash): ?>
    <div class="flash <?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
    <?php endif; ?>

    <div class="tabs">
        <a href="index.php" class="tab <?= $filter === '' ? 'active' : '' ?>">
            全件 (<?= $count_all ?>)
        </a>
        <a href="index.php?status=pending" class="tab <?= $filter === 'pending' ? 'active' : '' ?>">
            審査待ち (<?= $count_map['pending'] ?>)
        </a>
        <a href="index.php?status=approved" class="tab <?= $filter === 'approved' ? 'active' : '' ?>">
            承認済み (<?= $count_map['approved'] ?>)
        </a>
        <a href="index.php?status=rejected" class="tab <?= $filter === 'rejected' ? 'active' : '' ?>">
            否認済み (<?= $count_map['rejected'] ?>)
        </a>
    </div>

    <?php if (empty($applicants)): ?>
        <div class="empty">該当する申請はありません。</div>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>ニックネーム</th>
                <th>メールアドレス</th>
                <th>申請日時</th>
                <th>ステータス</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($applicants as $row): ?>
            <?php $s = $status_labels[$row['status']]; ?>
            <tr>
                <td><?= h((string)$row['id']) ?></td>
                <td><?= h($row['nickname']) ?></td>
                <td><?= h($row['email']) ?></td>
                <td><?= h($row['submitted_at']) ?></td>
                <td>
                    <span class="badge" style="background:<?= $s['color'] ?>">
                        <?= h($s['label']) ?>
                    </span>
                </td>
                <td>
                    <a class="detail-link" href="detail.php?id=<?= (int)$row['id'] ?>">詳細</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
</body>
</html>
