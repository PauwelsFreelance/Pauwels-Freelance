<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
require_login();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'mark_read') {
        $pdo->prepare('UPDATE submissions SET is_read = 1 WHERE id = ?')->execute([$id]);
    } elseif ($action === 'mark_unread') {
        $pdo->prepare('UPDATE submissions SET is_read = 0 WHERE id = ?')->execute([$id]);
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM submissions WHERE id = ?')->execute([$id]);
    }
    header('Location: /admin/submissions.php' . (isset($_GET['view']) ? '' : ''));
    exit;
}

$viewId = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$viewing = null;
if ($viewId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM submissions WHERE id = ?');
    $stmt->execute([$viewId]);
    $viewing = $stmt->fetch() ?: null;
    if ($viewing && !$viewing['is_read']) {
        $pdo->prepare('UPDATE submissions SET is_read = 1 WHERE id = ?')->execute([$viewId]);
        $viewing['is_read'] = 1;
    }
}

$submissions = $pdo->query('SELECT * FROM submissions ORDER BY created_at DESC')->fetchAll();

admin_header('Submissions', 'submissions');
?>
<h1>Submissions</h1>
<p class="admin-lede">Contact form messages, including the configurator selections a visitor sent through.</p>

<?php if ($viewing): ?>
  <div class="section-block">
    <h2><?= htmlspecialchars($viewing['name'], ENT_QUOTES) ?></h2>
    <table style="margin-bottom:20px;">
      <tr><th style="width:140px;">Email</th><td><a href="mailto:<?= htmlspecialchars($viewing['email'], ENT_QUOTES) ?>"><?= htmlspecialchars($viewing['email'], ENT_QUOTES) ?></a></td></tr>
      <tr><th>Interested in</th><td><?= htmlspecialchars($viewing['project_type'], ENT_QUOTES) ?></td></tr>
      <tr><th>Received</th><td><?= htmlspecialchars($viewing['created_at'], ENT_QUOTES) ?></td></tr>
      <tr><th>Message</th><td style="white-space:pre-wrap;"><?= htmlspecialchars($viewing['message'], ENT_QUOTES) ?></td></tr>
    </table>
    <a href="/admin/submissions.php">← Back to all submissions</a>
  </div>
<?php endif; ?>

<div class="section-block">
  <table>
    <tr><th>Status</th><th>Name</th><th>Interested in</th><th>Received</th><th>Actions</th></tr>
    <?php foreach ($submissions as $s): ?>
      <tr class="<?= $s['is_read'] ? '' : 'unread' ?>">
        <td><span class="badge <?= $s['is_read'] ? 'muted' : 'ok' ?>"><?= $s['is_read'] ? 'Read' : 'New' ?></span></td>
        <td><a href="/admin/submissions.php?view=<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['name'], ENT_QUOTES) ?></a></td>
        <td><?= htmlspecialchars($s['project_type'], ENT_QUOTES) ?></td>
        <td><?= htmlspecialchars($s['created_at'], ENT_QUOTES) ?></td>
        <td class="actions">
          <a href="/admin/submissions.php?view=<?= (int)$s['id'] ?>">View</a>
          <form method="post" style="display:inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $s['is_read'] ? 'mark_unread' : 'mark_read' ?>">
            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
            <button type="submit"><?= $s['is_read'] ? 'Mark unread' : 'Mark read' ?></button>
          </form>
          <form method="post" style="display:inline" onsubmit="return confirm('Delete this submission?');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
            <button type="submit" class="danger">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$submissions): ?><tr><td colspan="5">No submissions yet.</td></tr><?php endif; ?>
  </table>
</div>

<?php admin_footer(); ?>
