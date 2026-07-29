<?php
declare(strict_types=1);

function admin_header(string $title, string $active): void
{
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($title, ENT_QUOTES) ?> — Admin — Pauwels Freelance</title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="icon" href="/assets/logo.png">
  <link rel="stylesheet" href="/admin/admin.css">
</head>
<body>
  <div class="admin-shell">
    <div class="admin-topbar">
      <div class="admin-brand">Pauwels Freelance — Admin</div>
      <nav class="admin-nav">
        <a href="/admin/index.php" class="<?= $active === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
        <a href="/admin/portfolio.php" class="<?= $active === 'portfolio' ? 'active' : '' ?>">Portfolio</a>
        <a href="/admin/configurator.php" class="<?= $active === 'configurator' ? 'active' : '' ?>">Configurator</a>
        <a href="/admin/submissions.php" class="<?= $active === 'submissions' ? 'active' : '' ?>">Submissions</a>
      </nav>
      <a class="admin-logout" href="/admin/logout.php">Log out</a>
    </div>
    <?php
}

function admin_footer(): void
{
    ?>
  </div>
</body>
</html>
    <?php
}

/** Render a flash message read from ?ok= / ?err= query params. */
function admin_flash_from_query(): void
{
    if (!empty($_GET['ok'])) {
        echo '<div class="flash ok">' . htmlspecialchars((string)$_GET['ok'], ENT_QUOTES) . '</div>';
    }
    if (!empty($_GET['err'])) {
        echo '<div class="flash error">' . htmlspecialchars((string)$_GET['err'], ENT_QUOTES) . '</div>';
    }
}
