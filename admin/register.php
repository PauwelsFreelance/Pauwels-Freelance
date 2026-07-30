<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

admin_session_start();

if (current_admin_id() !== null) {
    header('Location: /admin/index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    /* Honeypot — invisible to humans, irresistible to bots. */
    if (!empty($_POST['website'])) {
        $success = 'Request submitted. It needs to be approved before you can log in.';
    } elseif (registration_is_throttled()) {
        $error = 'Too many requests from this location. Please try again later.';
    } else {
        $username  = trim((string)($_POST['username'] ?? ''));
        $email     = trim((string)($_POST['email'] ?? ''));
        $password  = (string)($_POST['password'] ?? '');
        $password2 = (string)($_POST['password2'] ?? '');

        if (!preg_match('/^[a-zA-Z0-9_.-]{3,60}$/', $username)) {
            $error = 'Username must be 3–60 characters: letters, numbers, dot, dash or underscore.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 10) {
            $error = 'Password must be at least 10 characters.';
        } elseif ($password !== $password2) {
            $error = 'Passwords do not match.';
        } else {
            record_registration_attempt();

            $stmt = db()->prepare('SELECT id FROM admin_users WHERE username = ?');
            $stmt->execute([$username]);

            if ($stmt->fetch()) {
                /* Same message whether it exists or not — don't reveal usernames. */
                $success = 'Request submitted. It needs to be approved before you can log in.';
            } else {
                $token = bin2hex(random_bytes(32));
                $hash  = password_hash($password, PASSWORD_DEFAULT);

                $ins = db()->prepare(
                    'INSERT INTO admin_users (username, password_hash, is_confirmed, requested_email, confirm_token, confirm_token_expires)
                     VALUES (?, ?, 0, ?, ?, DATE_ADD(NOW(), INTERVAL 48 HOUR))'
                );
                $ins->execute([$username, $hash, $email, $token]);

                $cfg = require __DIR__ . '/../config.php';
                $host = $_SERVER['HTTP_HOST'] ?? 'pauwels-freelance.cz';
                $confirmUrl = 'https://' . $host . '/admin/confirm.php?token=' . $token;

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
                    $mail->addAddress($cfg['mail_to']); // → you, never the requester
                    $mail->addReplyTo($email, $username);

                    $mail->Subject = 'New admin account request: ' . $username;
                    $mail->Body =
                        "Someone requested a new admin account on pauwels-freelance.cz.\n\n" .
                        "Username:            {$username}\n" .
                        "Contact email given: {$email}\n\n" .
                        "If this is expected, confirm it here (link expires in 48 hours):\n" .
                        $confirmUrl . "\n\n" .
                        "If you don't recognize this request, just ignore this email — the\n" .
                        "account stays inactive and will automatically expire.";

                    $mail->send();
                    $success = 'Request submitted. It needs to be approved before you can log in.';
                } catch (Exception $e) {
                    error_log('Account request email failed: ' . $mail->ErrorInfo);
                    /* Don't leave an orphaned, unconfirmable row behind if the email failed. */
                    db()->prepare('DELETE FROM admin_users WHERE username = ? AND is_confirmed = 0')->execute([$username]);
                    $error = 'Could not send the confirmation request. Please try again shortly.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Request an account — Pauwels Freelance</title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="icon" href="/assets/logo.png">
  <link rel="stylesheet" href="/admin/admin.css">
</head>
<body>
  <div class="login-shell">
    <div class="login-card">
      <h1>Request an account</h1>

      <?php if ($error): ?>
        <div class="flash error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="flash ok"><?= htmlspecialchars($success, ENT_QUOTES) ?></div>
      <?php endif; ?>

      <?php if (!$success): ?>
      <form method="post" novalidate>
        <?= csrf_field() ?>

        <!-- Honeypot -->
        <div style="position:absolute;left:-9999px;" aria-hidden="true">
          <label for="website">Website</label>
          <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
        </div>

        <label for="username">Username</label>
        <input type="text" id="username" name="username" required autocomplete="username" autofocus
          value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES) ?>">

        <label for="email">Your email</label>
        <input type="email" id="email" name="email" required autocomplete="email"
          value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES) ?>">
        <p class="hint">Used only so the approval request can be traced back to a person — the confirmation link
          itself goes to the site owner, not to this address.</p>

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required autocomplete="new-password">

        <label for="password2">Confirm password</label>
        <input type="password" id="password2" name="password2" required autocomplete="new-password">

        <button type="submit" class="btn">Request account</button>
      </form>
      <?php endif; ?>

      <a class="back" href="/admin/login.php">← Back to login</a>
    </div>
  </div>
</body>
</html>
