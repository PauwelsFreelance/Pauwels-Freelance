<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
require_login();

$assetsDir = __DIR__ . '/../assets/';
$errors = [];

/** Handle an uploaded image, returns the stored filename or null. */
function handle_image_upload(string $assetsDir, array &$errors): ?string
{
    if (empty($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES['image'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Image upload failed (error code ' . $file['error'] . ').';
        return null;
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        $errors[] = 'Image must be under 5MB.';
        return null;
    }
    $info = getimagesize($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!$info || !isset($allowed[$info['mime']])) {
        $errors[] = 'Image must be a JPG, PNG or WebP file.';
        return null;
    }
    $ext = $allowed[$info['mime']];
    $filename = 'project-' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $assetsDir . $filename)) {
        $errors[] = 'Could not save the uploaded image.';
        return null;
    }
    return $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        db()->prepare('DELETE FROM portfolio_projects WHERE id = ?')->execute([$id]);
        header('Location: /admin/portfolio.php?ok=' . urlencode('Project deleted.'));
        exit;
    }

    if ($action === 'save') {
        $id           = (int)($_POST['id'] ?? 0);
        $title        = trim((string)($_POST['title'] ?? ''));
        $description  = trim((string)($_POST['description'] ?? ''));
        $tags         = trim((string)($_POST['tags'] ?? ''));
        $ctaText      = trim((string)($_POST['cta_text'] ?? 'I want something like this'));
        $contactType  = trim((string)($_POST['contact_type'] ?? ''));
        $sortOrder    = (int)($_POST['sort_order'] ?? 0);
        $isPublished  = isset($_POST['is_published']) ? 1 : 0;
        $existingImage = trim((string)($_POST['existing_image'] ?? ''));

        if ($title === '' || $description === '') {
            $errors[] = 'Title and description are required.';
        }

        $uploadedImage = handle_image_upload($assetsDir, $errors);
        $image = $uploadedImage ?? $existingImage;
        if ($image === '' && empty($errors)) {
            $errors[] = 'Please upload an image for this project.';
        }

        if (empty($errors)) {
            if ($id > 0) {
                $stmt = db()->prepare(
                    'UPDATE portfolio_projects SET title=?, description=?, tags=?, image_filename=?, cta_text=?, contact_type=?, sort_order=?, is_published=? WHERE id=?'
                );
                $stmt->execute([$title, $description, $tags, $image, $ctaText, $contactType, $sortOrder, $isPublished, $id]);
                $msg = 'Project updated.';
            } else {
                $stmt = db()->prepare(
                    'INSERT INTO portfolio_projects (title, description, tags, image_filename, cta_text, contact_type, sort_order, is_published) VALUES (?,?,?,?,?,?,?,?)'
                );
                $stmt->execute([$title, $description, $tags, $image, $ctaText, $contactType, $sortOrder, $isPublished]);
                $msg = 'Project added.';
            }
            header('Location: /admin/portfolio.php?ok=' . urlencode($msg));
            exit;
        }
    }
}

$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editing = null;
if ($editId > 0) {
    $stmt = db()->prepare('SELECT * FROM portfolio_projects WHERE id = ?');
    $stmt->execute([$editId]);
    $editing = $stmt->fetch() ?: null;
}

$projects = db()->query('SELECT * FROM portfolio_projects ORDER BY sort_order ASC, id ASC')->fetchAll();

admin_header('Portfolio', 'portfolio');
?>
<h1>Portfolio</h1>
<p class="admin-lede">Projects shown on the public portfolio page, in sort order.</p>

<?php admin_flash_from_query(); ?>
<?php foreach ($errors as $e): ?>
  <div class="flash error"><?= htmlspecialchars($e, ENT_QUOTES) ?></div>
<?php endforeach; ?>

<div class="section-block">
  <h2><?= $editing ? 'Edit project' : 'Add a project' ?></h2>
  <form class="admin-form" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $editing ? (int)$editing['id'] : 0 ?>">
    <input type="hidden" name="existing_image" value="<?= htmlspecialchars($editing['image_filename'] ?? '', ENT_QUOTES) ?>">

    <label for="title">Title</label>
    <input type="text" id="title" name="title" required value="<?= htmlspecialchars($editing['title'] ?? '', ENT_QUOTES) ?>">

    <label for="description">Description</label>
    <textarea id="description" name="description" required><?= htmlspecialchars($editing['description'] ?? '', ENT_QUOTES) ?></textarea>

    <label for="tags">Tags (comma-separated)</label>
    <input type="text" id="tags" name="tags" placeholder="PHP, SQL, JavaScript" value="<?= htmlspecialchars($editing['tags'] ?? '', ENT_QUOTES) ?>">

    <label for="image">Project image <?= $editing ? '(leave empty to keep current: ' . htmlspecialchars($editing['image_filename'], ENT_QUOTES) . ')' : '' ?></label>
    <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp">
    <p class="hint">JPG, PNG or WebP, under 5MB. Uploads go straight into <code>/assets/</code>.</p>

    <div class="row2">
      <div>
        <label for="cta_text">Button text</label>
        <input type="text" id="cta_text" name="cta_text" value="<?= htmlspecialchars($editing['cta_text'] ?? 'I want something like this', ENT_QUOTES) ?>">
      </div>
      <div>
        <label for="sort_order">Sort order</label>
        <input type="number" id="sort_order" name="sort_order" value="<?= (int)($editing['sort_order'] ?? 0) ?>">
      </div>
    </div>

    <label for="contact_type">Contact form pre-fill text</label>
    <input type="text" id="contact_type" name="contact_type" placeholder="Inquiry about: Project Name" value="<?= htmlspecialchars($editing['contact_type'] ?? '', ENT_QUOTES) ?>">
    <p class="hint">Shown to the visitor as "You're asking about: …" when they click the button.</p>

    <label style="margin-top:18px;">
      <input type="checkbox" name="is_published" style="width:auto;display:inline-block;margin-right:8px;" <?= ($editing === null || $editing['is_published']) ? 'checked' : '' ?>>
      Published (visible on the public site)
    </label>

    <div class="actions-row">
      <button type="submit" class="btn"><?= $editing ? 'Save changes' : 'Add project' ?></button>
      <?php if ($editing): ?><a href="/admin/portfolio.php">Cancel</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="section-block">
  <h2>All projects</h2>
  <table>
    <tr><th>Order</th><th>Image</th><th>Title</th><th>Tags</th><th>Status</th><th>Actions</th></tr>
    <?php foreach ($projects as $p): ?>
      <tr>
        <td><?= (int)$p['sort_order'] ?></td>
        <td><img src="/assets/<?= htmlspecialchars($p['image_filename'], ENT_QUOTES) ?>" alt="" style="width:64px;height:44px;object-fit:cover;border-radius:2px;"></td>
        <td><?= htmlspecialchars($p['title'], ENT_QUOTES) ?></td>
        <td><?php foreach (array_filter(array_map('trim', explode(',', $p['tags']))) as $t): ?><span class="tag-pill"><?= htmlspecialchars($t, ENT_QUOTES) ?></span><?php endforeach; ?></td>
        <td><span class="badge <?= $p['is_published'] ? 'ok' : 'muted' ?>"><?= $p['is_published'] ? 'Published' : 'Hidden' ?></span></td>
        <td class="actions">
          <a href="/admin/portfolio.php?edit=<?= (int)$p['id'] ?>">Edit</a>
          <form method="post" style="display:inline" onsubmit="return confirm('Delete this project?');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <button type="submit" class="danger">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$projects): ?><tr><td colspan="6">No projects yet.</td></tr><?php endif; ?>
  </table>
</div>

<?php admin_footer(); ?>
