<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$input = api_input();
$action = api_action($input);

try {
    switch ($action) {
        case 'list':
            $category = trim((string) ($input['category'] ?? ''));
            $search = trim((string) ($input['search'] ?? ''));
            $products = ProductRepository::listActive(
                $category !== '' ? $category : null,
                $search !== '' ? $search : null
            );
            respond([
                'ok' => true,
                'products' => $products,
                'count' => count($products),
            ]);

        case 'get':
            $id = trim((string) ($input['id'] ?? ''));
            if ($id === '') {
                respond(['ok' => false, 'error' => 'ID manquant'], 400);
            }
            $product = ProductRepository::findById($id);
            if (!$product) {
                respond(['ok' => false, 'error' => 'Produit introuvable'], 404);
            }
            respond(['ok' => true, 'product' => $product]);

        case 'admin_list':
            Auth::requireAdmin();
            respond(['ok' => true, 'products' => ProductRepository::listAll()]);

        case 'save':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                respond(['ok' => false, 'error' => 'POST requis'], 405);
            }
            Auth::requireAdmin();

            $required = ['id', 'name', 'category', 'price', 'image'];
            foreach ($required as $field) {
                if (empty($input[$field])) {
                    respond(['ok' => false, 'error' => "Champ requis : $field"], 400);
                }
            }

            $product = ProductRepository::upsert($input);
            respond(['ok' => true, 'product' => $product]);

        case 'delete':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                respond(['ok' => false, 'error' => 'POST requis'], 405);
            }
            Auth::requireAdmin();
            $id = trim((string) ($input['id'] ?? ''));
            if ($id === '') {
                respond(['ok' => false, 'error' => 'ID manquant'], 400);
            }
            ProductRepository::delete($id);
            respond(['ok' => true]);

        default:
            respond(['ok' => false, 'error' => 'Action inconnue'], 400);
    }
} catch (Throwable $e) {
    respond(['ok' => false, 'error' => $e->getMessage()], 500);
}
