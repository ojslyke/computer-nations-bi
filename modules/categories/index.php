<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/rbac.php';
requirePermission('categories.manage');

$pageTitle = 'Categories';
$errors = [];
$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $action = $_POST['action'] ?? '';
    $name = trim($_POST['name'] ?? '');

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $inUse = $pdo->prepare("SELECT COUNT(*) c FROM products WHERE category_id = ?");
        $inUse->execute([$id]);
        if ($inUse->fetch()['c'] > 0) {
            setFlash('error', "Can't delete a category that still has products in it. Move those products first.");
        } else {
            $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
            logActivity('categories.manage', "Deleted category #$id");
            setFlash('success', 'Category removed.');
        }
        redirect('modules/categories/index.php');
    }

    if ($name === '') {
        $errors[] = 'Category name is required.';
    } else {
        try {
            if ($action === 'update' && !empty($_POST['id'])) {
                $pdo->prepare("UPDATE categories SET name = ? WHERE id = ?")->execute([$name, (int)$_POST['id']]);
                setFlash('success', 'Category updated.');
            } else {
                $pdo->prepare("INSERT INTO categories (name, created_by) VALUES (?, ?)")->execute([$name, $_SESSION['user_id']]);
                setFlash('success', 'Category added.');
            }
            logActivity('categories.manage', "Saved category: $name");
            redirect('modules/categories/index.php');
        } catch (PDOException $e) {
            $errors[] = 'That category name already exists.';
        }
    }
}

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch();
}

$categories = $pdo->query(
    "SELECT c.*, u.full_name AS creator_name, COUNT(p.id) AS product_count
     FROM categories c LEFT JOIN products p ON p.category_id = c.id LEFT JOIN users u ON u.id = c.created_by
     GROUP BY c.id ORDER BY c.name"
)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<section class="panel panel-form">
  <h2><?= $editing ? 'Edit category' : 'Add category' ?></h2>
  <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= clean($err) ?></div><?php endforeach; ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
    <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
    <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>
    <div class="form-field">
      <label>Name</label>
      <input type="text" name="name" value="<?= clean($editing['name'] ?? '') ?>" required>
    </div>
    <div class="form-actions">
      <?php if ($editing): ?><a href="index.php" class="btn btn-secondary">Cancel</a><?php endif; ?>
      <button type="submit" class="btn btn-primary"><?= $editing ? 'Save changes' : 'Add category' ?></button>
    </div>
  </form>
</section>

<section class="panel">
  <?php if ($categories): ?>
  <div class="data-table-wrap">
  <table class="data-table">
    <thead><tr><th>Category</th><th>Products</th><th>Added by</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($categories as $c): ?>
      <tr>
        <td><?= clean($c['name']) ?></td>
        <td class="mono"><?= (int)$c['product_count'] ?></td>
        <td class="muted small"><?= clean($c['creator_name'] ?? '—') ?></td>
        <td class="row-actions">
          <a href="index.php?edit=<?= (int)$c['id'] ?>" class="link">Edit</a>
          <form method="post" class="js-confirm" data-confirm-title="Delete this category?" data-confirm-message="Only possible if no products are assigned to it." style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
            <button type="submit" class="link link-danger">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php else: ?>
    <p class="empty-state">No categories yet.</p>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
