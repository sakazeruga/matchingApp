<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

/**
 * 管理画面専用 CSRF トークン（公開フォームのトークンと競合しないよう別キーを使う）
 */
function admin_csrf_token(): string
{
    if (empty($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['admin_csrf_token'];
}

function verify_admin_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['admin_csrf_token'] ?? '', $token)) {
        http_response_code(403);
        exit('不正なリクエストです。');
    }
    unset($_SESSION['admin_csrf_token']);
}
