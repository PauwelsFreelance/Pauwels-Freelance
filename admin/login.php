<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
admin_session_start();

if (current_admin_id() !== null) {
    header('Location: /admin/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (login_is_throttled()) {
        $error = 'Too many failed attempts. Please wait 15 minutes and try again.';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        $stmt = db()->prepare('SELECT id, password_hash, is_confirmed FROM admin_users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        $passwordOk = $user && password_verify($password, $user['password_hash']);

        if ($passwordOk && !$user['is_confirmed']) {
            $error = 'This account is awaiting approval. You\'ll be able to log in once it\'s confirmed.';
        } elseif ($passwordOk) {
            clear_failed_logins();
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$user['id'];

            $upd = db()->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = ?');
            $upd->execute([$user['id']]);

            header('Location: /admin/index.php');
            exit;
        } else {
            record_failed_login();
            $error = 'Incorrect username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin login — Pauwels Freelance</title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="icon" href="/assets/logo.png">
  <link rel="stylesheet" href="/admin/admin.css">
</head>
<body>
  <div class="login-shell">
    <div class="login-card">
      <h1>Admin login</h1>

      <?php if ($error): ?>
        <div class="flash error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
      <?php endif; ?>

      <form method="post" novalidate>
        <?= csrf_field() ?>
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required autocomplete="username" autofocus>

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">

        <button type="submit" class="btn">Log in</button>
      </form>

      <a class="back" href="/index.html">← Back to the site</a>
      <a class="back" href="/admin/register.php">Request an account</a>
    </div>
  </div>
</body>
</html>
