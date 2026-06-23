<?php
declare(strict_types=1);
// このページへは submit.php からのリダイレクトのみ想定。
// 直接アクセスされても害はないが、フォームへ誘導する。
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>審査受付完了 | 雨雲はれる</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Hiragino Kaku Gothic ProN', 'Meiryo', sans-serif;
            background: #f0f4f8;
            color: #333;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1rem;
        }
        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,.08);
            padding: 2.5rem 2rem;
            max-width: 480px;
            width: 100%;
            text-align: center;
        }
        .icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        h1 {
            font-size: 1.4rem;
            color: #4a90d9;
            margin-bottom: .8rem;
        }
        p {
            font-size: .95rem;
            line-height: 1.7;
            color: #555;
            margin-bottom: .6rem;
        }
        .note {
            margin-top: 1.4rem;
            padding: 1rem;
            background: #f5f9ff;
            border-left: 4px solid #4a90d9;
            border-radius: 4px;
            font-size: .85rem;
            color: #666;
            text-align: left;
        }
        .back {
            display: inline-block;
            margin-top: 1.8rem;
            padding: .6rem 1.6rem;
            background: #4a90d9;
            color: #fff;
            border-radius: 6px;
            text-decoration: none;
            font-size: .9rem;
            transition: background .15s;
        }
        .back:hover { background: #357abd; }
    </style>
</head>
<body>
<div class="card">
    <div class="icon">&#x2705;</div>
    <h1>審査を受け付けました</h1>
    <p>ご回答いただきありがとうございます。<br>内容を確認のうえ、審査結果をお知らせします。</p>
    <div class="note">
        <strong>審査について</strong><br>
        通常 3〜5 営業日以内にご登録のメールアドレスへ結果をお送りします。<br>
        迷惑メールフォルダもご確認ください。
    </div>
    <a class="back" href="index.php">トップへ戻る</a>
</div>
</body>
</html>
