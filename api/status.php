<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$installed = is_file(dirname(__DIR__) . '/config/database.php');

if (!$installed) {
    echo json_encode([
        'ok' => false,
        'installed' => false,
        'message' => 'Lancez install.php pour configurer le site.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require __DIR__ . '/bootstrap.php';

try {
    $productCount = ProductRepository::countActive();
    echo json_encode([
        'ok' => true,
        'installed' => true,
        'products' => $productCount,
        'php' => PHP_VERSION,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode([
        'ok' => false,
        'installed' => true,
        'error' => 'Base de données inaccessible',
    ], JSON_UNESCAPED_UNICODE);
}
