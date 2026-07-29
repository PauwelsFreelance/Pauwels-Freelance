<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
require_login();

$portfolioCount   = (int)db()->query('SELECT COUNT(*) FROM portfolio_projects')->fetchColumn();
$tierCount        = (int)db()->query('SELECT COUNT(*) FROM configurator_tiers')->fetchColumn();
$addonCount       = (int)db()->query('SELECT COUNT(*) FROM configurator_addons')->fetchColumn();
$submissionCount  = (int)db()->query('SELECT COUNT(*) FROM submissions')->fetchColumn();
$unreadCount      = (int)db()->query('SELECT COUNT(*) FROM submissions WHERE is_read = 0')->fetchColumn();

admin_header('Dashboard', 'dashboard');
?>
<h1>Dashboard</h1>
<p class="admin-lede">Manage portfolio projects, configurator content and incoming customer submissions.</p>

<div class="card-grid">
  <div class="dash-card">
    <div class="n"><?= $portfolioCount ?></div>
    <div class="l">Portfolio projects</div>
    <a href="/admin/portfolio.php">Manage →</a>
  </div>
  <div class="dash-card">
    <div class="n"><?= $tierCount ?></div>
    <div class="l">Configurator tiers · <?= $addonCount ?> add-ons</div>
    <a href="/admin/configurator.php">Manage →</a>
  </div>
  <div class="dash-card">
    <div class="n"><?= $submissionCount ?><?php if ($unreadCount > 0): ?> <span class="badge ok"><?= $unreadCount ?> new</span><?php endif; ?></div>
    <div class="l">Customer submissions</div>
    <a href="/admin/submissions.php">View →</a>
  </div>
</div>

<?php admin_footer(); ?>
