<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 既にログイン済みなら一覧へ
if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';

    if (
        ADMIN_PASS_HASH !== '' &&
        hash_equals(ADMIN_USER, $user) &&
        password_verify($pass, ADMIN_PASS_HASH)
    ) {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        header('Location: index.php');
        exit;
    }
    // 成功・失敗を同じメッセージにして列挙攻撃を防ぐ
    $error = 'ユーザー名またはパスワードが正しくありません。';
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理者ログイン | 雨雲はれる</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Hiragino Kaku Gothic ProN', 'Meiryo', sans-serif;
            background: #1a2535;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1rem;
        }
        .card {
            background: #fff;
            border-radius: 10px;
            padding: 2rem;
            width: 100%;
            max-width: 360px;
        }
        h1 { font-size: 1.2rem; color: #2c3e50; margin-bottom: 1.6rem; text-align: center; }
        .field { margin-bottom: 1.1rem; }
        label { display: block; font-size: .85rem; color: #555; margin-bottom: .3rem; }
        input[type="text"], input[type="password"] {
            width: 100%; padding: .6rem .8rem;
            border: 1px solid #ccc; border-radius: 6px; font-size: 1rem;
        }
        .error {
            background: #fff0f0; border: 1px solid #f5c6c6;
            color: #c0392b; padding: .6rem .8rem; border-radius: 6px;
            font-size: .85rem; margin-bottom: 1rem;
        }
        button {
            width: 100%; padding: .75rem;
            background: #2c3e50; color: #fff;
            border: none; border-radius: 6px;
            font-size: 1rem; cursor: pointer; transition: background .15s;
        }
        button:hover { background: #1a252f; }
    </style>
</head>
<body>
<div class="card">
    <h1>雨雲はれる 管理画面</h1>
    <?php if ($error !== ''): ?>
        <div class="error"><?= h($error) ?></div>
    <?php endif; ?>
    <form method="post">
        <div class="field">
            <label for="username">ユーザー名</label>
            <input type="text" id="username" name="username"
                   autocomplete="username" required>
        </div>
        <div class="field">
            <label for="password">パスワード</label>
            <input type="password" id="password" name="password"
                   autocomplete="current-password" required>
        </div>
        <button type="submit">ログイン</button>
    </form>
</div>
</body>
</html>
