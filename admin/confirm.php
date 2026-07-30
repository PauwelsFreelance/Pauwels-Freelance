<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
admin_session_start();

$token = (string)($_GET['token'] ?? '');
$message = '';
$ok = false;

if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    $message = 'This confirmation link is invalid.';
} else {
    $stmt = db()->prepare(
        'SELECT id, username, confirm_token_expires FROM admin_users WHERE confirm_token = ? AND is_confirmed = 0'
    );
    $stmt->execute([$token]);
    $row = $stmt->fetch();

    if (!$row) {
        $message = 'This confirmation link is invalid or has already been used.';
    } elseif (strtotime((string)$row['confirm_token_expires']) < time()) {
        db()->prepare('DELETE FROM admin_users WHERE id = ?')->execute([$row['id']]);
        $message = 'This confirmation link expired. Ask for the account request to be submitted again.';
    } else {
        $upd = db()->prepare(
            'UPDATE admin_users SET is_confirmed = 1, confirm_token = NULL, confirm_token_expires = NULL WHERE id = ?'
        );
        $upd->execute([$row['id']]);
        $ok = true;
        $message = 'Account "' . $row['username'] . '" is confirmed and can now log in.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Confirm account — Pauwels Freelance</title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="icon" href="/assets/logo.png">
  <link rel="stylesheet" href="/admin/admin.css">
</head>
<body>
  <div class="login-shell">
    <div class="login-card">
      <h1><?= $ok ? 'Account confirmed' : 'Confirmation failed' ?></h1>
      <div class="flash <?= $ok ? 'ok' : 'error' ?>"><?= htmlspecialchars($message, ENT_QUOTES) ?></div>
      <a class="back" href="/admin/login.php">← Go to login</a>
    </div>
  </div>
</body>
</html>
