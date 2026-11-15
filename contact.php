<?php
/**
 * 無料サイト診断の申し込みを受け取り、創敬あてにメールで知らせる。
 * 成功したら thanks.html へ移動する。
 *
 * 公開前に、下の「設定」2行を実際のメールアドレスに書き換えること。
 */

// ---- 設定 ----------------------------------------------------------
const MAIL_TO   = '';                        // 申し込みを受け取るアドレス(例: info@soukeibros.jp)
const MAIL_FROM = 'noreply@soukeibros.jp';   // 差出人。このサーバーのドメインのアドレスにする
// --------------------------------------------------------------------

mb_language('Japanese');
mb_internal_encoding('UTF-8');
date_default_timezone_set('Asia/Tokyo');

/** 入力欄へ戻る案内つきの簡単な画面を出して終了する */
function stop(string $message, int $status = 400): void
{
    http_response_code($status);
    $safe = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html lang="ja"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<meta name="robots" content="noindex"><title>送信できませんでした|株式会社 創敬</title>'
       . '<link rel="stylesheet" href="css/style.css"></head><body><main class="page"><div class="container">'
       . '<div class="page__inner"><h1>送信できませんでした</h1><div class="prose"><p>' . $safe . '</p></div>'
       . '<p><a class="btn btn--ghost" href="./#shindan">入力に戻る</a></p></div></div></main></body></html>';
    exit;
}

/** 1行の入力値を取り出す。改行と制御文字は取り除く(メールの改ざん防止) */
function field(string $name, int $max): string
{
    $value = $_POST[$name] ?? '';
    if (!is_string($value)) {
        return '';
    }
    $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';
    return mb_substr(trim($value), 0, $max);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: ./#shindan', true, 303);
    exit;
}

// 人には見えない欄に入力があれば自動送信とみなし、何も送らずに完了画面へ進める
if (field('fax', 100) !== '') {
    header('Location: thanks.html', true, 303);
    exit;
}

$company = field('company', 100);
$url     = field('url', 300);
$email   = field('email', 200);

if ($company === '' || $url === '' || $email === '') {
    stop('会社名、サイトのURL、メールアドレスの3つをすべてご入力ください。');
}
if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) {
    stop('サイトのURLは https:// または http:// から始まる形でご入力ください。');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    stop('メールアドレスの形をご確認ください。');
}
if (MAIL_TO === '') {
    stop('ただいま受付の準備中です。お手数ですが、時間をおいてもう一度お試しください。', 503);
}

$subject = '【無料診断の申し込み】' . $company;
$body = "無料サイト診断の申し込みがありました。\n\n"
      . "会社名: {$company}\n"
      . "サイトのURL: {$url}\n"
      . "メールアドレス: {$email}\n\n"
      . '受付日時: ' . date('Y-m-d H:i') . "\n"
      . "返信期限の目安: 3営業日以内\n";
$headers = 'From: ' . MAIL_FROM . "\r\n" . 'Reply-To: ' . $email;

if (!mb_send_mail(MAIL_TO, $subject, $body, $headers)) {
    stop('送信中に問題が起きました。お手数ですが、時間をおいてもう一度お試しください。', 500);
}

header('Location: thanks.html', true, 303);
exit;
