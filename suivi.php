<?php
declare(strict_types=1);
$id = trim($_GET['id'] ?? $_GET['tracking'] ?? '');
if ($id !== '') {
    header('Location: espace-client.php?view=track&id=' . rawurlencode($id), true, 301);
} else {
    header('Location: espace-client.php?view=track', true, 301);
}
exit;
