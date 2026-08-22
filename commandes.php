<?php
declare(strict_types=1);
$query = $_SERVER['QUERY_STRING'] ?? '';
$target = 'espace-client.php?view=orders' . ($query !== '' ? '&' . $query : '');
header('Location: ' . $target, true, 301);
exit;
