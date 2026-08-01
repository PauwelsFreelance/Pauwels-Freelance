<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/admin/includes/db.php';
$cfg = require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

function fail(int $code, string $msg): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail(405, 'Method not allowed.');

/* Reject requests that clearly didn't originate from this site — blocks a
   malicious page elsewhere from silently auto-submitting this form.
   Only rejects on an actual mismatch; if a privacy tool strips both
   headers, we let it through rather than turning away real visitors. */
$allowedHost = 'pauwels-freelance.cz';
$origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
if ($origin !== '') {
    $originHost = parse_url($origin, PHP_URL_HOST);
    if ($originHost !== $allowedHost && $originHost !== 'www.' . $allowedHost) {
        fail(403, 'Request rejected.');
    }
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true) ?: $_POST;

/* Honeypot */
if (!empty($data['company'])) { echo json_encode(['success' => true]); exit; }

$name    = trim((string)($data['name'] ?? ''));
$email   = trim((string)($data['email'] ?? ''));
$type    = trim((string)($data['projectType'] ?? 'Not specified'));
$message = trim((string)($data['message'] ?? ''));
$tierKey    = trim((string)($data['tierKey'] ?? ''));
$addonKeys  = trim((string)($data['addonKeys'] ?? ''));

if ($name === '' || $email === '' || $message === '') fail(422, 'Please fill in your name, email and a message.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL))        fail(422, 'That email address is not valid.');
if (mb_strlen($name) > 200 || mb_strlen($message) > 5000) fail(422, 'That message is too long.');

/* These only ever come from our own configurator JS, but validate the
   shape anyway before it touches the database. */
if ($tierKey !== '' && !preg_match('/^[a-z0-9_]+$/', $tierKey)) $tierKey = '';
if ($addonKeys !== '' && !preg_match('/^[a-z0-9_,]+$/', $addonKeys)) $addonKeys = '';

/* Strip CR/LF from header-bound fields — prevents header injection. */
$name  = str_replace(["\r", "\n"], ' ', $name);
$email = str_replace(["\r", "\n"], '',  $email);
$type  = str_replace(["\r", "\n"], ' ', $type);

/* Store in the database for the admin panel. A DB hiccup should never
   block the email from going out, so this is isolated in its own try. */
try {
    $stmt = db()->prepare('INSERT INTO submissions (name, email, project_type, message, tier_key, addon_keys) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$name, $email, $type, $message, $tierKey ?: null, $addonKeys ?: null]);
} catch (Throwable $e) {
    error_log('Failed to store submission: ' . $e->getMessage());
}

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
