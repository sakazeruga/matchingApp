<?php
declare(strict_types=1);

/**
 * PHP組み込みのmail()を使ったシンプルなメーラー。
 * PHPMailer/Symfonyに差し替える場合はこのファイルの関数内部を置き換えるだけでよい。
 */

/**
 * メール送信共通処理
 */
function _send_mail(string $to, string $subject, string $body): bool
{
    $from      = MAIL_FROM;
    $from_name = mb_encode_mimeheader(MAIL_FROM_NAME, 'UTF-8', 'B');
    $subject   = mb_encode_mimeheader($subject, 'UTF-8', 'B');

    $headers = implode("\r\n", [
        "From: {$from_name} <{$from}>",
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
    ]);

    $body_encoded = chunk_split(base64_encode($body));

    return mail($to, $subject, $body_encoded, $headers);
}

/**
 * 新規申請を管理者へ通知
 */
function mail_notify_admin(string $nickname, string $email, string $status): bool
{
    if (MAIL_ADMIN === '') {
        return false;
    }
    $status_label = $status === 'rejected' ? '自動否認' : '審査待ち';
    $body = <<<TEXT
        新しい入会申請がありました。

        ニックネーム : {$nickname}
        メール       : {$email}
        ステータス   : {$status_label}

        管理画面から内容をご確認ください。
        TEXT;

    return _send_mail(MAIL_ADMIN, '【雨雲はれる】新規入会申請', $body);
}

/**
 * 承認メールを申請者へ送信
 */
function mail_approved(string $to, string $nickname): bool
{
    $body = <<<TEXT
        {$nickname} 様

        このたびは雨雲はれるへのご応募ありがとうございます。

        審査の結果、入会をお断りしております。誠に恐れ入りますが、
        ご了承くださいますようお願い申し上げます。

        ──────────────────────────────
        雨雲はれる サポートチーム
        TEXT;

    // ※ above body is intentionally generic — approval details sent separately
    $approval_body = <<<TEXT
        {$nickname} 様

        このたびは雨雲はれるへのご応募ありがとうございます。

        審査の結果、入会が承認されました。
        下記URLよりプロフィールを設定してサービスをご利用ください。

        ──────────────────────────────
        雨雲はれる サポートチーム
        TEXT;

    return _send_mail($to, '【雨雲はれる】入会審査結果のご連絡', $approval_body);
}

/**
 * 否認メールを申請者へ送信（理由は記載しない）
 */
function mail_rejected(string $to, string $nickname): bool
{
    $body = <<<TEXT
        {$nickname} 様

        このたびは雨雲はれるへのご応募ありがとうございます。

        誠に申し訳ございませんが、審査の結果、
        今回は入会をお断りさせていただく運びとなりました。

        何卒ご理解くださいますようお願い申し上げます。

        ──────────────────────────────
        雨雲はれる サポートチーム
        TEXT;

    return _send_mail($to, '【雨雲はれる】入会審査結果のご連絡', $body);
}
