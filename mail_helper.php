<?php
declare(strict_types=1);

require_once __DIR__ . '/stripe_config.php';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;

function sendFixTrackEmail(string $recipient, string $recipientName, string $subject, string $htmlBody, string $textBody): bool
{
    loadStripeEnvironment();

    $host = trim((string)(getenv('SMTP_HOST') ?: ''));
    $username = trim((string)(getenv('SMTP_USERNAME') ?: ''));
    $password = (string)(getenv('SMTP_PASSWORD') ?: '');
    $fromAddress = trim((string)(getenv('MAIL_FROM_ADDRESS') ?: ''));
    $fromName = trim((string)(getenv('MAIL_FROM_NAME') ?: 'FixTrack Service'));
    $port = (int)(getenv('SMTP_PORT') ?: 587);

    if ($host === '' || $username === '' || $password === '' || $fromAddress === '') {
        error_log('FixTrack email skipped: SMTP settings are not configured.');
        return false;
    }

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->SMTPAuth = true;
        $mail->Username = $username;
        $mail->Password = $password;
        $mail->Port = $port;
        $mail->SMTPSecure = $port === 465
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($fromAddress, $fromName);
        $mail->addAddress($recipient, $recipientName !== '' ? $recipientName : $recipient);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $textBody;
        $mail->send();
        return true;
    } catch (Throwable $exception) {
        error_log('FixTrack email error: ' . $exception->getMessage());
        return false;
    }
}
?>
