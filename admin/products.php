<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_once dirname(__DIR__) . '/includes/ProductRepository.php';

admin_require_login();

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        ProductRepository::upsert([
            'id' => trim($_POST['id'] ?? ''),
            'name' => trim($_POST['name'] ?? ''),
            'category' => trim($_POST['category'] ?? 'beaute'),
            'categoryLabel' => trim($_POST['category_label'] ?? 'Beauté'),
            'price' => (int) ($_POST['price'] ?? 0),
            'badge' => trim($_POST['badge'] ?? '') ?: null,
            'image' => trim($_POST['image'] ?? ''),
            'short' => trim($_POST['short'] ?? ''),
            'desc' => trim($_POST['desc'] ?? ''),
            'benefits' => array_values(array_filter(array_map('trim', explode("\n", $_POST['benefits'] ?? '')))),
            'stock' => (int) ($_POST['stock'] ?? 100),
            'isActive' => isset($_POST['is_active']) ? 1 : 0,
        ]);
        $message = 'Produit enregistré.';
    } elseif ($action === 'delete') {
        ProductRepository::delete(trim($_POST['id'] ?? ''));
        $message = 'Produit désactivé.';
    }
}

$products = ProductRepository::listAll();
$editId = trim($_GET['edit'] ?? '');
$edit = $editId ? ProductRepository::findById($editId, false) : null;

ob_start();
?>
<h1 class="section-title">Produits</h1>
<?php if ($message): ?><p class="success-inline"><?= htmlspecialchars($message) ?></p><?php endif; ?>

<div class="track-card" style="margin-bottom:1.5rem">
  <h2><?= $edit ? 'Modifier le produit' : 'Nouveau produit' ?></h2>
  <form method="post" class="form-grid">
    <input type="hidden" name="action" value="save" />
    <div class="form-row"><label>ID (slug)</label><input name="id" required value="<?= htmlspecialchars($edit['id'] ?? '') ?>" <?= $edit ? 'readonly' : '' ?> /></div>
    <div class="form-row"><label>Nom</label><input name="name" required value="<?= htmlspecialchars($edit['name'] ?? '') ?>" /></div>
    <div class="form-row"><label>Catégorie</label><input name="category" required value="<?= htmlspecialchars($edit['category'] ?? 'beaute') ?>" /></div>
    <div class="form-row"><label>Label catégorie</label><input name="category_label" value="<?= htmlspecialchars($edit['categoryLabel'] ?? 'Beauté') ?>" /></div>
    <div class="form-row"><label>Prix (FCFA)</label><input name="price" type="number" required value="<?= (int)($edit['price'] ?? 0) ?>" /></div>
    <div class="form-row"><label>Badge</label><input name="badge" value="<?= htmlspecialchars($edit['badge'] ?? '') ?>" /></div>
    <div class="form-row"><label>Image (chemin)</label><input name="image" required value="<?= htmlspecialchars($edit['image'] ?? 'img/') ?>" /></div>
    <div class="form-row"><label>Stock</label><input name="stock" type="number" value="<?= (int)($edit['stock'] ?? 100) ?>" /></div>
    <div class="form-row"><label>Résumé court</label><input name="short" value="<?= htmlspecialchars($edit['short'] ?? '') ?>" /></div>
    <div class="form-row"><label>Description</label><textarea name="desc" rows="3"><?= htmlspecialchars($edit['desc'] ?? '') ?></textarea></div>
    <div class="form-row"><label>Bienfaits (1 par ligne)</label><textarea name="benefits" rows="4"><?= htmlspecialchars(implode("\n", $edit['benefits'] ?? [])) ?></textarea></div>
    <label><input type="checkbox" name="is_active" <?= ($edit['isActive'] ?? true) ? 'checked' : '' ?> /> Produit actif</label>
    <button class="btn btn--gold" type="submit">Enregistrer</button>
    <?php if ($edit): ?><a class="btn btn--ghost" href="products.php">Annuler</a><?php endif; ?>
  </form>
</div>

<table class="admin-table">
  <thead><tr><th>Produit</th><th>Catégorie</th><th>Prix</th><th>Stock</th><th>Statut</th><th></th></tr></thead>
  <tbody>
    <?php foreach ($products as $product): ?>
      <tr>
        <td><strong><?= htmlspecialchars($product['name']) ?></strong><br><small><?= htmlspecialchars($product['id']) ?></small></td>
        <td><?= htmlspecialchars($product['categoryLabel']) ?></td>
        <td><?= number_format((int)$product['price'], 0, ',', ' ') ?> FCFA</td>
        <td><?= (int)$product['stock'] ?></td>
        <td><?= $product['isActive'] ? 'Actif' : 'Inactif' ?></td>
        <td><a href="products.php?edit=<?= urlencode($product['id']) ?>">Modifier</a></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php
admin_layout('Produits', ob_get_clean(), 'products');
