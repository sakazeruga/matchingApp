<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

verify_admin_csrf();

$applicant_id = filter_input(INPUT_POST, 'applicant_id', FILTER_VALIDATE_INT);
$action       = $_POST['action'] ?? '';

if (!$applicant_id || !in_array($action, ['approve', 'reject'], true)) {
    http_response_code(400);
    exit('不正なリクエストです。');
}

$pdo = get_pdo();

// 現在のステータスを確認（pending のみ操作可能）
$stmt = $pdo->prepare(
    "SELECT r.status, a.email, a.nickname
     FROM results r
     JOIN applicants a ON a.id = r.applicant_id
     WHERE r.applicant_id = :id"
);
$stmt->execute([':id' => $applicant_id]);
$row = $stmt->fetch();

if (!$row) {
    http_response_code(404);
    exit('申請が見つかりません。');
}

if ($row['status'] !== 'pending') {
    $_SESSION['flash'] = [
        'type'    => 'error',
        'message' => 'この申請はすでに処理済みです。',
    ];
    header('Location: detail.php?id=' . $applicant_id);
    exit;
}

// ステータス更新
$new_status    = ($action === 'approve') ? 'approved' : 'rejected';
$reject_reason = ($action === 'reject')  ? '管理者により否認' : null;

$stmt = $pdo->prepare(
    "UPDATE results
     SET status = :status, reject_reason = :reason
     WHERE applicant_id = :id"
);
$stmt->execute([
    ':status' => $new_status,
    ':reason' => $reject_reason,
    ':id'     => $applicant_id,
]);

// メール送信
if ($action === 'approve') {
    mail_approved($row['email'], $row['nickname']);
    $flash_msg = "#{$applicant_id} {$row['nickname']} さんを承認し、通知メールを送信しました。";
} else {
    mail_rejected($row['email'], $row['nickname']);
    $flash_msg = "#{$applicant_id} {$row['nickname']} さんを否認し、通知メールを送信しました。";
}

$_SESSION['flash'] = ['type' => 'success', 'message' => $flash_msg];
header('Location: detail.php?id=' . $applicant_id);
exit;
