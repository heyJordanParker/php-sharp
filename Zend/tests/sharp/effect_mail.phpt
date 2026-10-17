--TEST--
PHP# Mail hands a message to the local mail program, with its addresses, its subject and a text or a text and HTML body
--INI--
sendmail_path={MAIL:{PWD}/effect_mail.eml}
--EXTENSIONS--
mbstring
--FILE--
<?php
require __DIR__ . '/effect_classes.inc';

use Sharp\Net\MailAddress;
use Sharp\Net\MailMessage;

$mail = new Sharp\Mail();
$from = new MailAddress('shop@example.com', 'The "Tea" Shop');
$mail->send(new MailMessage($from, [new MailAddress('ann@example.com')], 'Your receipt', "Thanks for the tea.\n"));
$sent = file_get_contents(__DIR__ . '/effect_mail.eml');
foreach (['To: ann@example.com', 'Subject: Your receipt', 'From: The \"Tea\" Shop <shop@example.com>', 'Content-Type: text/plain; charset=UTF-8', "Thanks for the tea."] as $line) {
    var_dump(str_contains($sent, $line));
}
unlink(__DIR__ . '/effect_mail.eml');

$mail->send(new MailMessage(
    $from,
    [new MailAddress('ann@example.com'), new MailAddress('bo@example.com', 'Bo')],
    'Thé',
    'plain',
    htmlBody: '<p>rich</p>',
    cc: [new MailAddress('cc@example.com')],
    replyTo: new MailAddress('help@example.com'),
));
$sent = file_get_contents(__DIR__ . '/effect_mail.eml');
foreach (['To: ann@example.com, Bo <bo@example.com>', 'Cc: cc@example.com', 'Reply-To: help@example.com', 'Subject: =?UTF-8?B?', 'multipart/alternative', 'text/html; charset=UTF-8', '<p>rich</p>'] as $line) {
    var_dump(str_contains($sent, $line));
}
unlink(__DIR__ . '/effect_mail.eml');

// A line break in a display name would start a header of its own, as `Bcc:`.
foreach ([fn () => new MailAddress('not an address'), fn () => new MailAddress('ann@example.com', "Ann\r\nBcc: eve@example.com"), fn () => new MailAddress('ann@example.com', "Ann\nBcc: eve@example.com")] as $refused) {
    try {
        $refused();
    } catch (ValueError $e) {
        echo $e::class, ': ', $e->getMessage(), "\n";
    }
}
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
ValueError: MailAddress: "not an address" is not a valid address
ValueError: MailAddress: the name of "ann@example.com" holds a line break
ValueError: MailAddress: the name of "ann@example.com" holds a line break
