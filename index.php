<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>男性入会審査フォーム | 雨雲はれる</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Hiragino Kaku Gothic ProN', 'Meiryo', sans-serif;
            background: #f0f4f8;
            color: #333;
            padding: 2rem 1rem;
        }
        .container {
            max-width: 640px;
            margin: 0 auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,.08);
            padding: 2rem;
        }
        h1 {
            font-size: 1.4rem;
            color: #4a90d9;
            margin-bottom: .4rem;
        }
        .subtitle {
            font-size: .85rem;
            color: #888;
            margin-bottom: 2rem;
        }
        .field { margin-bottom: 1.6rem; }
        label.field-label {
            display: block;
            font-weight: bold;
            margin-bottom: .5rem;
            font-size: .95rem;
        }
        .q-num {
            display: inline-block;
            background: #4a90d9;
            color: #fff;
            border-radius: 4px;
            padding: 1px 7px;
            font-size: .8rem;
            margin-right: .4rem;
        }
        input[type="text"] {
            width: 100%;
            padding: .6rem .8rem;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 1rem;
        }
        .radio-group { display: flex; flex-direction: column; gap: .5rem; }
        .radio-group label {
            display: flex;
            align-items: center;
            gap: .5rem;
            padding: .5rem .8rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            cursor: pointer;
            transition: background .15s;
        }
        .radio-group label:hover { background: #f5f9ff; }
        .radio-group input[type="radio"]:checked + span {
            font-weight: bold;
            color: #4a90d9;
        }
        textarea {
            width: 100%;
            padding: .6rem .8rem;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: .95rem;
            resize: vertical;
            min-height: 100px;
        }
        .required { color: #e05; margin-left: .2rem; }
        button[type="submit"] {
            display: block;
            width: 100%;
            padding: .9rem;
            background: #4a90d9;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1.05rem;
            cursor: pointer;
            transition: background .15s;
        }
        button[type="submit"]:hover { background: #357abd; }
        .note {
            font-size: .78rem;
            color: #999;
            margin-top: 1.2rem;
            text-align: center;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>雨雲はれる — 男性入会審査</h1>
    <p class="subtitle">すべての項目にお答えください。虚偽の申告は登録取り消しの対象となります。</p>

    <form method="post" action="submit.php">
        <input type="hidden" name="csrf_token" value="<?= h($token) ?>">

        <!-- ニックネーム -->
        <div class="field">
            <label class="field-label" for="nickname">
                ニックネーム<span class="required">*</span>
            </label>
            <input type="text" id="nickname" name="nickname"
                   maxlength="50" required placeholder="例：たろう">
        </div>

        <!-- メールアドレス -->
        <div class="field">
            <label class="field-label" for="email">
                メールアドレス<span class="required">*</span>
            </label>
            <input type="email" id="email" name="email"
                   maxlength="255" required placeholder="例：taro@example.com">
            <p style="font-size:.78rem;color:#999;margin-top:.3rem">
                審査結果をこちらへお送りします。
            </p>
        </div>

        <!-- 設問ループ -->
        <?php foreach (QUESTIONS as $qid => $q): ?>
        <div class="field">
            <label class="field-label">
                <span class="q-num">Q<?= $qid ?></span>
                <?= h($q['text']) ?><span class="required">*</span>
            </label>

            <?php if ($q['type'] === 'choice'): ?>
                <div class="radio-group">
                    <?php foreach ($q['options'] as $option): ?>
                    <label>
                        <input type="radio"
                               name="q<?= $qid ?>"
                               value="<?= h($option) ?>"
                               required>
                        <span><?= h($option) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>

            <?php else: ?>
                <textarea name="q<?= $qid ?>"
                          rows="4"
                          maxlength="1000"
                          required
                          placeholder="自由にお書きください（1000文字以内）"></textarea>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <button type="submit">審査を申し込む</button>
        <p class="note">送信後の変更はできません。内容をよくご確認のうえ送信してください。</p>
    </form>
</div>
</body>
</html>
