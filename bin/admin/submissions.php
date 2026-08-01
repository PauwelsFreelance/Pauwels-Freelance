<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
require_login();

$pdo = db();

/** Returns [total, breakdown[]] or [null, []] if no tier_key or it no longer exists. */
function compute_estimate(PDO $pdo, array $submission): array
{
    if (empty($submission['tier_key'])) {
        return [null, []];
    }
    $tierStmt = $pdo->prepare('SELECT full_name, base_price_kc FROM configurator_tiers WHERE tier_key = ?');
    $tierStmt->execute([$submission['tier_key']]);
    $tier = $tierStmt->fetch();
    if (!$tier) {
        return [null, []];
    }

    $total = (int)$tier['base_price_kc'];
    $breakdown = [[$tier['full_name'], (int)$tier['base_price_kc']]];

    $keys = array_filter(array_map('trim', explode(',', (string)$submission['addon_keys'])));
    if ($keys) {
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $addonStmt = $pdo->prepare("SELECT label, price_add_kc FROM configurator_addons WHERE addon_key IN ($placeholders)");
        $addonStmt->execute($keys);
        foreach ($addonStmt->fetchAll() as $a) {
            $total += (int)$a['price_add_kc'];
            $breakdown[] = [$a['label'], (int)$a['price_add_kc']];
        }
    }

    return [$total, $breakdown];
}

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
    } elseif ($action === 'save_final_price') {
        $finalPrice = (int)($_POST['final_price_kc'] ?? 0);
        $pdo->prepare('UPDATE submissions SET final_price_kc = ? WHERE id = ?')->execute([$finalPrice, $id]);
        header('Location: /admin/submissions.php?view=' . $id . '&ok=' . urlencode('Price saved.'));
        exit;
    } elseif ($action === 'clear_final_price') {
        $pdo->prepare('UPDATE submissions SET final_price_kc = NULL WHERE id = ?')->execute([$id]);
        header('Location: /admin/submissions.php?view=' . $id . '&ok=' . urlencode('Reverted to auto-estimate.'));
        exit;
    }
    header('Location: /admin/submissions.php' . (isset($_GET['view']) ? '' : ''));
    exit;
}

$viewId = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$viewing = null;
$estimate = null;
$estimateBreakdown = [];
if ($viewId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM submissions WHERE id = ?');
    $stmt->execute([$viewId]);
    $viewing = $stmt->fetch() ?: null;
    if ($viewing && !$viewing['is_read']) {
        $pdo->prepare('UPDATE submissions SET is_read = 1 WHERE id = ?')->execute([$viewId]);
        $viewing['is_read'] = 1;
    }
    if ($viewing) {
        [$estimate, $estimateBreakdown] = compute_estimate($pdo, $viewing);
    }
}

$submissions = $pdo->query('SELECT * FROM submissions ORDER BY created_at DESC')->fetchAll();

admin_header('Submissions', 'submissions');
?>
<h1>Submissions</h1>
<p class="admin-lede">Contact form messages, including the configurator selections a visitor sent through.</p>

<?php if ($viewing): ?>
  <div class="section-block">
    <?php admin_flash_from_query(); ?>
    <h2><?= htmlspecialchars($viewing['name'], ENT_QUOTES) ?></h2>
    <table style="margin-bottom:20px;">
      <tr><th style="width:140px;">Email</th><td><a href="mailto:<?= htmlspecialchars($viewing['email'], ENT_QUOTES) ?>"><?= htmlspecialchars($viewing['email'], ENT_QUOTES) ?></a></td></tr>
      <tr><th>Interested in</th><td><?= htmlspecialchars($viewing['project_type'], ENT_QUOTES) ?></td></tr>
      <tr><th>Received</th><td><?= htmlspecialchars($viewing['created_at'], ENT_QUOTES) ?></td></tr>
      <tr><th>Message</th><td style="white-space:pre-wrap;"><?= htmlspecialchars($viewing['message'], ENT_QUOTES) ?></td></tr>
    </table>

    <?php if ($estimate !== null): ?>
      <?php
        $hasOverride = $viewing['final_price_kc'] !== null;
        $displayTotal = $hasOverride ? (int)$viewing['final_price_kc'] : $estimate;
      ?>
      <table style="margin-bottom:20px;">
        <tr><th>Item</th><th style="width:160px;">Default price</th><th style="width:180px;">Update price</th></tr>
        <?php foreach ($estimateBreakdown as $i => [$label, $price]): ?>
          <tr>
            <td><?= htmlspecialchars($label, ENT_QUOTES) ?></td>
            <td><?= number_format($price, 0, ',', ' ') ?> Kč</td>
            <td><input type="number" class="price-line" data-default="<?= (int)$price ?>" min="0" step="100" value="<?= (int)$price ?>" style="width:130px;"></td>
          </tr>
        <?php endforeach; ?>
        <tr>
          <td><strong>Auto-estimate total</strong></td>
          <td><strong><?= number_format($estimate, 0, ',', ' ') ?> Kč</strong></td>
          <td><strong id="newTotalDisplay"><?= number_format($displayTotal, 0, ',', ' ') ?> Kč</strong></td>
        </tr>
      </table>

      <form method="post" style="display:flex;align-items:center;gap:14px;margin-bottom:8px;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_final_price">
        <input type="hidden" name="id" value="<?= (int)$viewing['id'] ?>">
        <input type="hidden" name="final_price_kc" id="finalPriceInput" value="<?= $displayTotal ?>">
        <button type="submit" class="btn small">Save adjusted price</button>
        <?php if ($hasOverride): ?>
          <span class="badge ok">Saved quote: <?= number_format((int)$viewing['final_price_kc'], 0, ',', ' ') ?> Kč</span>
        <?php endif; ?>
      </form>
      <?php if ($hasOverride): ?>
        <form method="post" style="display:inline;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="clear_final_price">
          <input type="hidden" name="id" value="<?= (int)$viewing['id'] ?>">
          <button type="submit" class="actions">Revert to auto-estimate</button>
        </form>
      <?php endif; ?>

      <script>
        (function () {
          var inputs = document.querySelectorAll('.price-line');
          var totalDisplay = document.getElementById('newTotalDisplay');
          var hiddenInput = document.getElementById('finalPriceInput');
          function recalc() {
            var sum = 0;
            inputs.forEach(function (el) { sum += parseInt(el.value, 10) || 0; });
            totalDisplay.textContent = sum.toLocaleString('cs-CZ') + ' Kč';
            hiddenInput.value = sum;
          }
          inputs.forEach(function (el) { el.addEventListener('input', recalc); });
        })();
      </script>
    <?php elseif (!empty($viewing['tier_key'])): ?>
      <div class="flash error">This submission references a tier or add-ons that no longer exist in the configurator — estimate unavailable.</div>
    <?php else: ?>
      <p class="hint">Submitted without the configurator, so no estimate is available.</p>
    <?php endif; ?>
    <a href="/admin/submissions.php">← Back to all submissions</a>
  </div>
<?php endif; ?>

<div class="section-block">
  <table>
    <tr><th>Status</th><th>Name</th><th>Interested in</th><th>Estimate</th><th>Received</th><th>Actions</th></tr>
    <?php foreach ($submissions as $s): ?>
      <?php
        [$rowEstimate] = compute_estimate($pdo, $s);
        $rowHasOverride = $s['final_price_kc'] !== null;
        $rowDisplay = $rowHasOverride ? (int)$s['final_price_kc'] : $rowEstimate;
      ?>
      <tr class="<?= $s['is_read'] ? '' : 'unread' ?>">
        <td><span class="badge <?= $s['is_read'] ? 'muted' : 'ok' ?>"><?= $s['is_read'] ? 'Read' : 'New' ?></span></td>
        <td><a href="/admin/submissions.php?view=<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['name'], ENT_QUOTES) ?></a></td>
        <td><?= htmlspecialchars($s['project_type'], ENT_QUOTES) ?></td>
        <td>
          <?php if ($rowDisplay !== null): ?>
            <?= number_format($rowDisplay, 0, ',', ' ') ?> Kč<?php if ($rowHasOverride): ?> <span class="badge ok" style="margin-left:4px;">quoted</span><?php endif; ?>
          <?php else: ?>—<?php endif; ?>
        </td>
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
    <?php if (!$submissions): ?><tr><td colspan="6">No submissions yet.</td></tr><?php endif; ?>
  </table>
</div>

<?php admin_footer(); ?>
