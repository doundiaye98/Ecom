<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_once dirname(__DIR__) . '/includes/OrderRepository.php';
require_once dirname(__DIR__) . '/includes/ProductRepository.php';
require_once dirname(__DIR__) . '/includes/ContactRepository.php';

admin_require_login();

$orders = OrderRepository::listAll();
$productCount = ProductRepository::countActive();
$messageCount = ContactRepository::countUnread();
$revenue = array_sum(array_map(static fn($o) => (int)($o['total'] ?? 0), $orders));

ob_start();
?>
<h1 class="section-title">Tableau de bord</h1>
<div class="admin-grid">
  <div class="stat-card"><strong><?= count($orders) ?></strong><span>Commandes</span></div>
  <div class="stat-card"><strong><?= $productCount ?></strong><span>Produits actifs</span></div>
  <div class="stat-card"><strong><?= $messageCount ?></strong><span>Messages non lus</span></div>
  <div class="stat-card"><strong><?= number_format($revenue, 0, ',', ' ') ?> FCFA</strong><span>Chiffre d'affaires</span></div>
</div>

<h2>Dernières commandes</h2>
<?php if (!$orders): ?>
  <div class="track-card"><p>Aucune commande pour le moment.</p></div>
<?php else: ?>
  <table class="admin-table">
    <thead><tr><th>ID</th><th>Client</th><th>Total</th><th>Statut</th><th>Date</th></tr></thead>
    <tbody>
      <?php foreach (array_slice($orders, 0, 8) as $order): ?>
        <tr>
          <td><a href="orders.php?id=<?= urlencode($order['id']) ?>"><?= htmlspecialchars($order['id']) ?></a></td>
          <td><?= htmlspecialchars($order['customer']['name'] ?? '') ?></td>
          <td><?= number_format((int)$order['total'], 0, ',', ' ') ?> FCFA</td>
          <td><span class="pill"><?= htmlspecialchars(order_status_labels()[$order['status']] ?? $order['status']) ?></span></td>
          <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($order['createdAt']))) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
<?php
admin_layout('Tableau de bord', ob_get_clean(), 'dashboard');
