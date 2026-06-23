<?php
declare(strict_types=1);

// 本番用ローカル設定（git管理外）があれば読み込む
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

// DB接続情報
define('DB_DSN',  getenv('DB_DSN')  ?: 'mysql:host=127.0.0.1;dbname=amagumo;charset=utf8mb4');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// Gemini API
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');
define('GEMINI_API_URL',
    'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . GEMINI_API_KEY
);

// Mail
define('MAIL_FROM',      getenv('MAIL_FROM')      ?: 'noreply@amagumo.example.com');
define('MAIL_FROM_NAME', '雨雲はれる');
define('MAIL_ADMIN',     getenv('MAIL_ADMIN')      ?: '');

// Admin
define('ADMIN_USER',      getenv('ADMIN_USER')      ?: 'admin');
define('ADMIN_PASS_HASH', getenv('ADMIN_PASS_HASH') ?: '');

// Rate limit（同一セッションで RATE_LIMIT_WINDOW 秒以内に RATE_LIMIT_MAX 回まで）
define('RATE_LIMIT_MAX',    3);
define('RATE_LIMIT_WINDOW', 600);

// 設問定義
define('QUESTIONS', [
    1 => [
        'text'    => '現在の交際状況は？',
        'type'    => 'choice',
        'options' => ['独身', '既婚', '交際中'],
        'banned'  => ['既婚', '交際中'],
    ],
    2 => [
        'text'    => 'このアプリを使う目的は？',
        'type'    => 'choice',
        'options' => ['真剣な交際相手を探したい', '友人・出会いを広げたい', '遊び相手が欲しい'],
        'banned'  => ['遊び相手が欲しい'],
    ],
    3 => [
        'text'    => '好みでない相手から話しかけられたら？',
        'type'    => 'choice',
        'options' => ['普通に話す', '丁寧に断る', '無視・拒絶する'],
        'banned'  => ['無視・拒絶する'],
    ],
    4 => [
        'text'    => '外見と内面、どちらが大事だと思いますか？',
        'type'    => 'choice',
        'options' => ['内面が大事', '両方大事', '外見がすべて'],
        'banned'  => ['外見がすべて'],
    ],
    5 => [
        'text'    => '過去にDVやハラスメントを指摘されたことはありますか？',
        'type'    => 'choice',
        'options' => ['ない', 'ある'],
        'banned'  => ['ある'],
    ],
    6 => [
        'text' => '自己紹介をしてください（趣味・性格など）',
        'type' => 'text',
    ],
    7 => [
        'text' => 'どんな出会いを求めていますか？',
        'type' => 'text',
    ],
]);

/**
 * PDO シングルトン
 */
function get_pdo(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

/**
 * CSRFトークン生成（セッション未開始なら開始する）
 */
function csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * CSRFトークン検証（失敗時は 403 で終了）
 */
function verify_csrf(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        exit('不正なリクエストです。');
    }
    // ワンタイム：使い捨て
    unset($_SESSION['csrf_token']);
}

/**
 * 出力エスケープ
 */
function h(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * レート制限（セッションベース）
 * 上限超過時は 429 で終了する
 */
function enforce_rate_limit(): void
{
    if (!isset($_SESSION['sub_times'])) {
        $_SESSION['sub_times'] = [];
    }
    $now = time();
    $_SESSION['sub_times'] = array_values(array_filter(
        $_SESSION['sub_times'],
        fn(int $t): bool => ($now - $t) < RATE_LIMIT_WINDOW
    ));
    if (count($_SESSION['sub_times']) >= RATE_LIMIT_MAX) {
        http_response_code(429);
        exit('送信回数の上限に達しました。しばらく時間をおいてから再度お試しください。');
    }
    $_SESSION['sub_times'][] = $now;
}
