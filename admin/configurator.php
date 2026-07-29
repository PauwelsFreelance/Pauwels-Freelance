<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
require_login();

$pdo = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    // ---------- Tiers (+ features + presets) ----------
    if ($action === 'save_tier') {
        $id       = (int)($_POST['id'] ?? 0);
        $tierKey  = trim((string)($_POST['tier_key'] ?? ''));
        $tag      = trim((string)($_POST['tag'] ?? ''));
        $name     = trim((string)($_POST['name'] ?? ''));
        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $duration = trim((string)($_POST['duration_text'] ?? ''));
        $sort     = (int)($_POST['sort_order'] ?? 0);
        $published = isset($_POST['is_published']) ? 1 : 0;
        $featuresRaw = (string)($_POST['features'] ?? '');
        $features = array_values(array_filter(array_map('trim', explode("\n", $featuresRaw)), fn($l) => $l !== ''));
        $presetAddonIds = array_map('intval', $_POST['presets'] ?? []);

        if ($tierKey === '' || $tag === '' || $name === '' || $fullName === '') {
            $errors[] = 'Tier key, tag, name and full name are all required.';
        } else {
            $pdo->beginTransaction();
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE configurator_tiers SET tier_key=?, tag=?, name=?, full_name=?, duration_text=?, sort_order=?, is_published=? WHERE id=?');
                $stmt->execute([$tierKey, $tag, $name, $fullName, $duration, $sort, $published, $id]);
                $tierId = $id;
            } else {
                $stmt = $pdo->prepare('INSERT INTO configurator_tiers (tier_key, tag, name, full_name, duration_text, sort_order, is_published) VALUES (?,?,?,?,?,?,?)');
                $stmt->execute([$tierKey, $tag, $name, $fullName, $duration, $sort, $published]);
                $tierId = (int)$pdo->lastInsertId();
            }

            $pdo->prepare('DELETE FROM configurator_tier_features WHERE tier_id = ?')->execute([$tierId]);
            $insFeat = $pdo->prepare('INSERT INTO configurator_tier_features (tier_id, feature_text, sort_order) VALUES (?,?,?)');
            foreach ($features as $i => $f) {
                $insFeat->execute([$tierId, $f, $i]);
            }

            $pdo->prepare('DELETE FROM configurator_presets WHERE tier_id = ?')->execute([$tierId]);
            $insPreset = $pdo->prepare('INSERT IGNORE INTO configurator_presets (tier_id, addon_id) VALUES (?,?)');
            foreach ($presetAddonIds as $aid) {
                $insPreset->execute([$tierId, $aid]);
            }

            $pdo->commit();
            header('Location: /admin/configurator.php?ok=' . urlencode('Tier saved.') . '#tiers');
            exit;
        }
    }

    if ($action === 'delete_tier') {
        $pdo->prepare('DELETE FROM configurator_tiers WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
        header('Location: /admin/configurator.php?ok=' . urlencode('Tier deleted.') . '#tiers');
        exit;
    }

    // ---------- Add-on categories ----------
    if ($action === 'save_category') {
        $id    = (int)($_POST['id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        $sort  = (int)($_POST['sort_order'] ?? 0);
        if ($title === '') {
            $errors[] = 'Category title is required.';
        } else {
            if ($id > 0) {
                $pdo->prepare('UPDATE configurator_addon_categories SET title=?, sort_order=? WHERE id=?')->execute([$title, $sort, $id]);
            } else {
                $pdo->prepare('INSERT INTO configurator_addon_categories (title, sort_order) VALUES (?,?)')->execute([$title, $sort]);
            }
            header('Location: /admin/configurator.php?ok=' . urlencode('Category saved.') . '#addons');
            exit;
        }
    }

    if ($action === 'delete_category') {
        $pdo->prepare('DELETE FROM configurator_addon_categories WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
        header('Location: /admin/configurator.php?ok=' . urlencode('Category deleted (and its add-ons).') . '#addons');
        exit;
    }

    // ---------- Add-ons ----------
    if ($action === 'save_addon') {
        $id         = (int)($_POST['id'] ?? 0);
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $key        = trim((string)($_POST['addon_key'] ?? ''));
        $label      = trim((string)($_POST['label'] ?? ''));
        $sort       = (int)($_POST['sort_order'] ?? 0);

        if ($categoryId <= 0 || $key === '' || $label === '') {
            $errors[] = 'Category, key and label are required for an add-on.';
        } elseif (!preg_match('/^[a-z0-9_]+$/', $key)) {
            $errors[] = 'Add-on key must be lowercase letters, numbers or underscores only.';
        } else {
            if ($id > 0) {
                $pdo->prepare('UPDATE configurator_addons SET category_id=?, addon_key=?, label=?, sort_order=? WHERE id=?')->execute([$categoryId, $key, $label, $sort, $id]);
            } else {
                $pdo->prepare('INSERT INTO configurator_addons (category_id, addon_key, label, sort_order) VALUES (?,?,?,?)')->execute([$categoryId, $key, $label, $sort]);
            }
            header('Location: /admin/configurator.php?ok=' . urlencode('Add-on saved.') . '#addons');
            exit;
        }
    }

    if ($action === 'delete_addon') {
        $pdo->prepare('DELETE FROM configurator_addons WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
        header('Location: /admin/configurator.php?ok=' . urlencode('Add-on deleted.') . '#addons');
        exit;
    }
}

$tiers = $pdo->query('SELECT * FROM configurator_tiers ORDER BY sort_order ASC, id ASC')->fetchAll();
$featuresByTier = [];
foreach ($pdo->query('SELECT * FROM configurator_tier_features ORDER BY sort_order ASC') as $f) {
    $featuresByTier[$f['tier_id']][] = $f['feature_text'];
}
$presetsByTier = [];
foreach ($pdo->query('SELECT tier_id, addon_id FROM configurator_presets') as $p) {
    $presetsByTier[$p['tier_id']][] = (int)$p['addon_id'];
}

$categories = $pdo->query('SELECT * FROM configurator_addon_categories ORDER BY sort_order ASC, id ASC')->fetchAll();
$addons = $pdo->query('SELECT * FROM configurator_addons ORDER BY category_id ASC, sort_order ASC')->fetchAll();
$addonsByCategory = [];
foreach ($addons as $a) {
    $addonsByCategory[$a['category_id']][] = $a;
}

$editTierId = isset($_GET['edit_tier']) ? (int)$_GET['edit_tier'] : 0;
$editTier = null;
foreach ($tiers as $t) {
    if ((int)$t['id'] === $editTierId) { $editTier = $t; break; }
}
$editAddonId = isset($_GET['edit_addon']) ? (int)$_GET['edit_addon'] : 0;
$editAddon = null;
foreach ($addons as $a) {
    if ((int)$a['id'] === $editAddonId) { $editAddon = $a; break; }
}
$editCatId = isset($_GET['edit_category']) ? (int)$_GET['edit_category'] : 0;
$editCat = null;
foreach ($categories as $c) {
    if ((int)$c['id'] === $editCatId) { $editCat = $c; break; }
}

admin_header('Configurator', 'configurator');
?>
<h1>Configurator</h1>
<p class="admin-lede">Edit the tiers, features and add-on checklist that power the configurator page.</p>

<?php admin_flash_from_query(); ?>
<?php foreach ($errors as $e): ?><div class="flash error"><?= htmlspecialchars($e, ENT_QUOTES) ?></div><?php endforeach; ?>

<div class="section-block" id="tiers">
  <h2><?= $editTier ? 'Edit tier' : 'Add a tier' ?></h2>
  <form class="admin-form" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_tier">
    <input type="hidden" name="id" value="<?= $editTier ? (int)$editTier['id'] : 0 ?>">

    <div class="row2">
      <div>
        <label for="tier_key">Tier key (internal, e.g. "small")</label>
        <input type="text" id="tier_key" name="tier_key" required value="<?= htmlspecialchars($editTier['tier_key'] ?? '', ENT_QUOTES) ?>">
      </div>
      <div>
        <label for="tag">Tag (e.g. "Small")</label>
        <input type="text" id="tag" name="tag" required value="<?= htmlspecialchars($editTier['tag'] ?? '', ENT_QUOTES) ?>">
      </div>
    </div>

    <label for="name">Name (e.g. "One-page site")</label>
    <input type="text" id="name" name="name" required value="<?= htmlspecialchars($editTier['name'] ?? '', ENT_QUOTES) ?>">

    <label for="full_name">Full name (e.g. "Small project — one-page site")</label>
    <input type="text" id="full_name" name="full_name" required value="<?= htmlspecialchars($editTier['full_name'] ?? '', ENT_QUOTES) ?>">
    <p class="hint">This is what gets sent to the contact form and shown as "You're asking about: …".</p>

    <div class="row2">
      <div>
        <label for="duration_text">Duration text (e.g. "About 1–2 weeks")</label>
        <input type="text" id="duration_text" name="duration_text" value="<?= htmlspecialchars($editTier['duration_text'] ?? '', ENT_QUOTES) ?>">
      </div>
      <div>
        <label for="sort_order">Sort order</label>
        <input type="number" id="sort_order" name="sort_order" value="<?= (int)($editTier['sort_order'] ?? 0) ?>">
      </div>
    </div>

    <label for="features">Feature list (one per line)</label>
    <textarea id="features" name="features" style="min-height:130px;"><?= htmlspecialchars(implode("\n", $featuresByTier[$editTier['id'] ?? 0] ?? []), ENT_QUOTES) ?></textarea>

    <label>Pre-selected add-ons for this tier</label>
    <div class="checklist">
      <?php foreach ($addons as $a): ?>
        <label>
          <input type="checkbox" name="presets[]" value="<?= (int)$a['id'] ?>"
            <?= in_array((int)$a['id'], $presetsByTier[$editTier['id'] ?? 0] ?? [], true) ? 'checked' : '' ?>>
          <?= htmlspecialchars($a['label'], ENT_QUOTES) ?>
        </label>
      <?php endforeach; ?>
    </div>

    <label style="margin-top:18px;">
      <input type="checkbox" name="is_published" style="width:auto;display:inline-block;margin-right:8px;" <?= ($editTier === null || $editTier['is_published']) ? 'checked' : '' ?>>
      Published
    </label>

    <div class="actions-row">
      <button type="submit" class="btn"><?= $editTier ? 'Save changes' : 'Add tier' ?></button>
      <?php if ($editTier): ?><a href="/admin/configurator.php#tiers">Cancel</a><?php endif; ?>
    </div>
  </form>

  <table>
    <tr><th>Order</th><th>Tag</th><th>Name</th><th>Duration</th><th>Status</th><th>Actions</th></tr>
    <?php foreach ($tiers as $t): ?>
      <tr>
        <td><?= (int)$t['sort_order'] ?></td>
        <td><?= htmlspecialchars($t['tag'], ENT_QUOTES) ?></td>
        <td><?= htmlspecialchars($t['name'], ENT_QUOTES) ?></td>
        <td><?= htmlspecialchars($t['duration_text'], ENT_QUOTES) ?></td>
        <td><span class="badge <?= $t['is_published'] ? 'ok' : 'muted' ?>"><?= $t['is_published'] ? 'Published' : 'Hidden' ?></span></td>
        <td class="actions">
          <a href="/admin/configurator.php?edit_tier=<?= (int)$t['id'] ?>#tiers">Edit</a>
          <form method="post" style="display:inline" onsubmit="return confirm('Delete this tier? This also removes its features and presets.');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_tier">
            <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
            <button type="submit" class="danger">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>

<div class="section-block" id="addons">
  <h2><?= $editCat ? 'Edit category' : 'Add a category' ?></h2>
  <form class="admin-form" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_category">
    <input type="hidden" name="id" value="<?= $editCat ? (int)$editCat['id'] : 0 ?>">
    <div class="row2">
      <div>
        <label for="cat_title">Category title</label>
        <input type="text" id="cat_title" name="title" required value="<?= htmlspecialchars($editCat['title'] ?? '', ENT_QUOTES) ?>">
      </div>
      <div>
        <label for="cat_sort">Sort order</label>
        <input type="number" id="cat_sort" name="sort_order" value="<?= (int)($editCat['sort_order'] ?? 0) ?>">
      </div>
    </div>
    <div class="actions-row">
      <button type="submit" class="btn"><?= $editCat ? 'Save changes' : 'Add category' ?></button>
      <?php if ($editCat): ?><a href="/admin/configurator.php#addons">Cancel</a><?php endif; ?>
    </div>
  </form>

  <h2><?= $editAddon ? 'Edit add-on' : 'Add an add-on' ?></h2>
  <form class="admin-form" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_addon">
    <input type="hidden" name="id" value="<?= $editAddon ? (int)$editAddon['id'] : 0 ?>">

    <label for="category_id">Category</label>
    <select id="category_id" name="category_id" required>
      <?php foreach ($categories as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= ($editAddon && (int)$editAddon['category_id'] === (int)$c['id']) ? 'selected' : '' ?>>
          <?= htmlspecialchars($c['title'], ENT_QUOTES) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <div class="row2">
      <div>
        <label for="addon_key">Key (lowercase, no spaces)</label>
        <input type="text" id="addon_key" name="addon_key" required value="<?= htmlspecialchars($editAddon['addon_key'] ?? '', ENT_QUOTES) ?>">
      </div>
      <div>
        <label for="addon_sort">Sort order</label>
        <input type="number" id="addon_sort" name="sort_order" value="<?= (int)($editAddon['sort_order'] ?? 0) ?>">
      </div>
    </div>

    <label for="label">Label (shown to visitors)</label>
    <input type="text" id="label" name="label" required value="<?= htmlspecialchars($editAddon['label'] ?? '', ENT_QUOTES) ?>">

    <div class="actions-row">
      <button type="submit" class="btn"><?= $editAddon ? 'Save changes' : 'Add add-on' ?></button>
      <?php if ($editAddon): ?><a href="/admin/configurator.php#addons">Cancel</a><?php endif; ?>
    </div>
  </form>

  <?php foreach ($categories as $c): ?>
    <h2 style="margin-top:30px;font-size:15px;"><?= htmlspecialchars($c['title'], ENT_QUOTES) ?></h2>
    <table style="margin-bottom:20px;">
      <tr><th>Order</th><th>Key</th><th>Label</th><th>Actions</th></tr>
      <?php foreach ($addonsByCategory[$c['id']] ?? [] as $a): ?>
        <tr>
          <td><?= (int)$a['sort_order'] ?></td>
          <td><code><?= htmlspecialchars($a['addon_key'], ENT_QUOTES) ?></code></td>
          <td><?= htmlspecialchars($a['label'], ENT_QUOTES) ?></td>
          <td class="actions">
            <a href="/admin/configurator.php?edit_addon=<?= (int)$a['id'] ?>#addons">Edit</a>
            <form method="post" style="display:inline" onsubmit="return confirm('Delete this add-on?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete_addon">
              <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
              <button type="submit" class="danger">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($addonsByCategory[$c['id']])): ?><tr><td colspan="4">No add-ons in this category yet.</td></tr><?php endif; ?>
    </table>
    <form method="post" style="margin-bottom:36px" onsubmit="return confirm('Delete category \'<?= htmlspecialchars($c['title'], ENT_QUOTES) ?>\' and all its add-ons?');">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete_category">
      <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
      <button type="submit" class="actions danger" style="background:none;border:none;font-family:var(--font-mono);font-size:11.5px;cursor:pointer;">Delete this category</button>
    </form>
  <?php endforeach; ?>
</div>

<?php admin_footer(); ?>
