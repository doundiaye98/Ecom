<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$input = api_input();
$action = api_action($input);

switch ($action) {
    case 'login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond(['ok' => false, 'error' => 'POST requis'], 405);
        }
        $username = trim((string) ($input['username'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        if ($username === '' || $password === '') {
            respond(['ok' => false, 'error' => 'Identifiants requis'], 400);
        }
        if (!Auth::login($username, $password)) {
            respond(['ok' => false, 'error' => 'Identifiants incorrects'], 401);
        }
        respond(['ok' => true, 'user' => Auth::user(), 'csrfToken' => Csrf::token()]);

    case 'logout':
        Auth::logout();
        respond(['ok' => true]);

    case 'check':
        respond([
            'ok' => true,
            'authenticated' => Auth::check(),
            'user' => Auth::user(),
            'csrfToken' => Auth::check() ? Csrf::token() : null,
        ]);

    default:
        respond(['ok' => false, 'error' => 'Action inconnue'], 400);
}
