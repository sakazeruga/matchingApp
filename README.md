# 雨雲はれる — 男性入会審査フォーム

マッチングアプリ「雨雲はれる」の男性入会審査システム。
禁忌肢に該当した時点で即否認。自由記述は Gemini API でスクリーニング。

## 技術スタック

- PHP 8.x
- MySQL
- Gemini 1.5 Flash API

## ディレクトリ構成

```
├── index.php                  フォーム画面
├── submit.php                 審査処理
├── complete.php               審査受付完了画面
├── rejected.php               入会不可画面
├── config.php                 設定・共通関数
├── config.local.php.example   環境変数テンプレート（要コピー）
├── mailer.php                 メール送信
├── schema.sql                 DB定義（新規構築用）
├── schema_sakura.sql          DB定義（さくらレンタルサーバー用）
├── schema_migration.sql       既存DBへのマイグレーション
├── .htaccess                  セキュリティ設定
└── admin/
    ├── login.php              管理者ログイン
    ├── logout.php             ログアウト
    ├── index.php              申請一覧
    ├── detail.php             申請詳細・承認/否認
    └── action.php             承認/否認処理
```

## セットアップ

### 1. DB作成

```bash
# 通常環境
mysql -u root -p < schema.sql

# さくらレンタルサーバー（phpMyAdminで実行）
schema_sakura.sql の内容を貼り付けて実行
```

### 2. 環境変数設定

```bash
cp config.local.php.example config.local.php
```

`config.local.php` を編集して各値を設定する。

```php
putenv('DB_DSN=mysql:host=mysql????.db.sakura.ne.jp;dbname=????;charset=utf8mb4');
putenv('DB_USER=????');
putenv('DB_PASS=????');
putenv('GEMINI_API_KEY=????');
putenv('MAIL_FROM=noreply@yourdomain.com');
putenv('MAIL_ADMIN=admin@yourdomain.com');
putenv('ADMIN_USER=admin');
putenv('ADMIN_PASS_HASH=????');
```

### 3. 管理者パスワードのハッシュ生成

```bash
php -r "echo password_hash('YOUR_PASSWORD', PASSWORD_DEFAULT);"
```

出力値を `ADMIN_PASS_HASH` に設定する。

### 4. 起動（ローカル確認）

```bash
php -S localhost:8080
```

## 審査フロー

```
申請送信
  ↓
選択肢の禁忌チェック（Q1〜Q5）
  ↓ 該当 → rejected（即否認）
自由記述を Gemini API でスクリーニング（Q6・Q7）
  ↓ 問題あり → rejected
pending（審査待ち）
  ↓
管理者が admin/ で承認 or 否認
  ↓
申請者にメール通知
```

## セキュリティ

- PDO prepared statement（SQLインジェクション対策）
- htmlspecialchars（XSS対策）
- CSRFトークン（ワンタイム消費）
- レート制限（同一セッションで10分以内3回まで）
- config.local.php は .gitignore 対象
