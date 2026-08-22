<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_once dirname(__DIR__) . '/includes/ContactRepository.php';

admin_require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        ContactRepository::markRead($id);
    }
    header('Location: messages.php');
    exit;
}

$messages = ContactRepository::listRecent(100);

ob_start();
?>
<h1 class="section-title">Messages contact</h1>
<?php if (!$messages): ?>
  <div class="track-card"><p>Aucun message reçu.</p></div>
<?php else: ?>
  <?php foreach ($messages as $msg): ?>
    <div class="track-card" style="margin-bottom:1rem; opacity:<?= $msg['is_read'] ? '.75' : '1' ?>">
      <div class="track-card__head">
        <div>
          <h3><?= htmlspecialchars($msg['name']) ?></h3>
          <p class="muted"><?= htmlspecialchars($msg['email']) ?> · <?= htmlspecialchars(date('d/m/Y H:i', strtotime($msg['created_at']))) ?></p>
        </div>
        <?php if (!$msg['is_read']): ?><span class="pill">Nouveau</span><?php endif; ?>
      </div>
      <p><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
      <?php if (!$msg['is_read']): ?>
        <form method="post" style="margin-top:.75rem">
          <input type="hidden" name="id" value="<?= (int)$msg['id'] ?>" />
          <button class="btn btn--ghost btn--sm" type="submit">Marquer comme lu</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
<?php
admin_layout('Messages', ob_get_clean(), 'messages');
