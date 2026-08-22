<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$input = api_input();
$action = api_action($input);

if ($action !== 'send') {
    respond(['ok' => false, 'error' => 'Action inconnue'], 400);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['ok' => false, 'error' => 'POST requis'], 405);
}

RateLimiter::enforce('contact_send', Env::int('RATE_LIMIT_CONTACT', 8), 300);

$name = trim((string) ($input['name'] ?? ''));
$email = trim((string) ($input['email'] ?? ''));
$message = trim((string) ($input['message'] ?? ''));

if ($name === '' || $email === '' || $message === '') {
    respond(['ok' => false, 'error' => 'Tous les champs sont requis'], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(['ok' => false, 'error' => 'Email invalide'], 400);
}

try {
    $id = ContactRepository::create($name, $email, $message);
    respond(['ok' => true, 'id' => $id, 'message' => 'Message envoyé avec succès']);
} catch (Throwable $e) {
    respond(['ok' => false, 'error' => 'Impossible d\'envoyer le message'], 500);
}
