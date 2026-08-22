<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

require_once dirname(__DIR__) . '/includes/CustomerRepository.php';
require_once dirname(__DIR__) . '/includes/CustomerAuth.php';

$input = api_input();
$action = api_action($input);

try {
    switch ($action) {
        case 'register':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                respond(['ok' => false, 'error' => 'POST requis'], 405);
            }
            RateLimiter::enforce('customer_register', 10, 3600);

            $customer = CustomerAuth::registerAndLogin([
                'name' => (string) ($input['name'] ?? ''),
                'phone' => (string) ($input['phone'] ?? ''),
                'email' => (string) ($input['email'] ?? ''),
                'password' => (string) ($input['password'] ?? ''),
                'address' => (string) ($input['address'] ?? ''),
                'city' => (string) ($input['city'] ?? ''),
            ]);

            respond([
                'ok' => true,
                'customer' => CustomerRepository::publicProfile($customer),
                'csrfToken' => Csrf::token(),
            ], 201);

        case 'login':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                respond(['ok' => false, 'error' => 'POST requis'], 405);
            }
            RateLimiter::enforce('customer_login', 20, 900);

            $phone = trim((string) ($input['phone'] ?? ''));
            $password = (string) ($input['password'] ?? '');
            if ($phone === '' || $password === '') {
                respond(['ok' => false, 'error' => 'Téléphone et mot de passe requis'], 400);
            }
            if (!CustomerAuth::login($phone, $password)) {
                respond(['ok' => false, 'error' => 'Identifiants incorrects'], 401);
            }
            respond([
                'ok' => true,
                'customer' => CustomerAuth::user(),
                'csrfToken' => Csrf::token(),
            ]);

        case 'logout':
            CustomerAuth::logout();
            respond(['ok' => true]);

        case 'check':
            respond([
                'ok' => true,
                'authenticated' => CustomerAuth::check(),
                'customer' => CustomerAuth::user(),
                'csrfToken' => CustomerAuth::check() ? Csrf::token() : null,
            ]);

        case 'profile':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                respond(['ok' => false, 'error' => 'POST requis'], 405);
            }
            CustomerAuth::requireCustomer();
            $token = (string) ($input['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
            if (!Csrf::validate($token)) {
                respond(['ok' => false, 'error' => 'Token CSRF invalide ou session expirée'], 403);
            }

            $updated = CustomerRepository::updateProfile(CustomerAuth::id() ?? 0, $input);
            respond([
                'ok' => true,
                'customer' => CustomerRepository::publicProfile($updated),
            ]);

        default:
            respond(['ok' => false, 'error' => 'Action inconnue'], 400);
    }
} catch (InvalidArgumentException $e) {
    respond(['ok' => false, 'error' => $e->getMessage()], 400);
} catch (Throwable $e) {
    ErrorHandler::log('API customer', $e->getMessage(), $e->getFile(), $e->getLine());
    respond(['ok' => false, 'error' => safe_error_message($e->getMessage())], 500);
}
