<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php';
$cfg = require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

function fail(int $code, string $msg): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail(405, 'Method not allowed.');

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true) ?: $_POST;

/* Honeypot */
if (!empty($data['company'])) { echo json_encode(['success' => true]); exit; }

$name    = trim((string)($data['name'] ?? ''));
$email   = trim((string)($data['email'] ?? ''));
$type    = trim((string)($data['projectType'] ?? 'Not specified'));
$message = trim((string)($data['message'] ?? ''));

if ($name === '' || $email === '' || $message === '') fail(422, 'Please fill in your name, email and a message.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL))        fail(422, 'That email address is not valid.');
if (mb_strlen($name) > 200 || mb_strlen($message) > 5000) fail(422, 'That message is too long.');

/* Strip CR/LF from header-bound fields — prevents header injection. */
$name  = str_replace(["\r", "\n"], ' ', $name);
$email = str_replace(["\r", "\n"], '',  $email);
$type  = str_replace(["\r", "\n"], ' ', $type);

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = $cfg['smtp_host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $cfg['smtp_user'];
    $mail->Password   = $cfg['smtp_pass'];
    $mail->SMTPSecure = $cfg['smtp_secure'];
    $mail->Port       = $cfg['smtp_port'];
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom($cfg['mail_from'], $cfg['site_name']);
    $mail->addAddress($cfg['mail_to']);
    $mail->addReplyTo($email, $name);

    $mail->Subject = 'New inquiry: ' . $type;
    $mail->Body =
        "New inquiry from pauwels-freelance.cz\n" .
        "--------------------------------------\n\n" .
        "Name:          {$name}\n" .
        "Email:         {$email}\n" .
        "Interested in: {$type}\n\n" .
        "Message:\n{$message}\n";

    $mail->send();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log('Contact form failed: ' . $mail->ErrorInfo);
    fail(500, 'Sorry, the message could not be sent. Please email me directly.');
}
