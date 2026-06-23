<?php
declare(strict_types=1);
// 拒否理由は意図的にユーザーへ表示しない（どの回答が禁忌かを知られると迂回されるため）
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>入会審査結果 | 雨雲はれる</title>
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
            color: #c0392b;
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
            background: #fff5f5;
            border-left: 4px solid #c0392b;
            border-radius: 4px;
            font-size: .85rem;
            color: #666;
            text-align: left;
        }
        .back {
            display: inline-block;
            margin-top: 1.8rem;
            padding: .6rem 1.6rem;
            background: #888;
            color: #fff;
            border-radius: 6px;
            text-decoration: none;
            font-size: .9rem;
            transition: background .15s;
        }
        .back:hover { background: #666; }
    </style>
</head>
<body>
<div class="card">
    <div class="icon">&#x274C;</div>
    <h1>入会をお断りしています</h1>
    <p>誠に申し訳ございませんが、ご回答の内容を踏まえ、今回の入会審査を通過していただくことができませんでした。</p>
    <div class="note">
        当サービスはすべての会員が安心して利用できる環境を維持するため、
        一定の基準を設けております。ご理解のほどよろしくお願いいたします。
    </div>
    <a class="back" href="index.php">トップへ戻る</a>
</div>
</body>
</html>
