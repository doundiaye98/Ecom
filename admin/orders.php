<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_once dirname(__DIR__) . '/includes/OrderRepository.php';

admin_require_login();

$labels = order_status_labels();
$nextMap = [
    'pending_payment' => 'paid',
    'paid' => 'processing',
    'processing' => 'shipped',
    'shipped' => 'in_transit',
    'in_transit' => 'out_for_delivery',
    'out_for_delivery' => 'delivered',
    'delivered' => 'completed',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = trim($_POST['id'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $note = trim($_POST['note'] ?? '');
    if ($id && $status) {
        try {
            OrderRepository::updateStatus($id, $status, $note ?: ('Mise à jour admin → ' . ($labels[$status] ?? $status)));
            header('Location: orders.php?id=' . urlencode($id) . '&updated=1');
            exit;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$selectedId = trim($_GET['id'] ?? '');
$orders = OrderRepository::listAll();
$selected = $selectedId ? OrderRepository::findFormatted($selectedId) : null;

ob_start();
?>
<h1 class="section-title">Commandes</h1>
<?php if (!empty($_GET['updated'])): ?><p class="success-inline">Statut mis à jour.</p><?php endif; ?>
<?php if (!empty($error)): ?><p style="color:#b42318"><?= htmlspecialchars($error) ?></p><?php endif; ?>

<?php if ($selected): ?>
  <div class="track-card" style="margin-bottom:1.5rem">
    <h2><?= htmlspecialchars($selected['id']) ?></h2>
    <p><strong><?= htmlspecialchars($selected['customer']['name']) ?></strong> · <?= htmlspecialchars($selected['customer']['phone']) ?></p>
    <p><?= number_format((int)$selected['total'], 0, ',', ' ') ?> FCFA · <?= htmlspecialchars($labels[$selected['status']] ?? $selected['status']) ?></p>
    <p>Suivi : <?= htmlspecialchars($selected['trackingNumber']) ?></p>
    <ul>
      <?php foreach ($selected['items'] as $item): ?>
        <li><?= htmlspecialchars($item['name']) ?> × <?= (int)$item['qty'] ?> — <?= number_format($item['price'] * $item['qty'], 0, ',', ' ') ?> FCFA</li>
      <?php endforeach; ?>
    </ul>
    <?php $next = $nextMap[$selected['status']] ?? null; if ($next): ?>
      <form method="post" style="margin-top:1rem">
        <input type="hidden" name="id" value="<?= htmlspecialchars($selected['id']) ?>" />
        <input type="hidden" name="status" value="<?= htmlspecialchars($next) ?>" />
        <button class="btn btn--gold btn--sm" type="submit">Passer à : <?= htmlspecialchars($labels[$next]) ?></button>
      </form>
    <?php endif; ?>
    <a class="btn btn--ghost btn--sm" href="../suivi.html?id=<?= urlencode($selected['id']) ?>" target="_blank">Voir suivi client</a>
  </div>
<?php endif; ?>

<?php if (!$orders): ?>
  <div class="track-card"><p>Aucune commande.</p></div>
<?php else: ?>
  <table class="admin-table">
    <thead><tr><th>Commande</th><th>Client</th><th>Total</th><th>Statut</th><th>Action</th></tr></thead>
    <tbody>
      <?php foreach ($orders as $order): ?>
        <tr>
          <td><a href="orders.php?id=<?= urlencode($order['id']) ?>"><?= htmlspecialchars($order['id']) ?></a></td>
          <td><?= htmlspecialchars($order['customer']['name']) ?><br><small><?= htmlspecialchars($order['customer']['phone']) ?></small></td>
          <td><?= number_format((int)$order['total'], 0, ',', ' ') ?> FCFA</td>
          <td><?= htmlspecialchars($labels[$order['status']] ?? $order['status']) ?></td>
          <td>
            <?php $next = $nextMap[$order['status']] ?? null; if ($next): ?>
              <form method="post">
                <input type="hidden" name="id" value="<?= htmlspecialchars($order['id']) ?>" />
                <input type="hidden" name="status" value="<?= htmlspecialchars($next) ?>" />
                <button class="btn btn--gold btn--sm" type="submit">→ <?= htmlspecialchars($labels[$next]) ?></button>
              </form>
            <?php else: ?>Terminé<?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
<?php
admin_layout('Commandes', ob_get_clean(), 'orders');
