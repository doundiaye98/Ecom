<?php

declare(strict_types=1);

final class CustomerAuth
{
    public static function login(string $phone, string $password): bool
    {
        $customer = CustomerRepository::findByPhone($phone);
        if (!$customer || !CustomerRepository::verifyPassword($customer, $password)) {
            return false;
        }

        Auth::startSession();
        session_regenerate_id(true);
        $_SESSION['customer_id'] = (int) $customer['id'];
        $_SESSION['customer_logged_in'] = true;

        require_once __DIR__ . '/Csrf.php';
        if (empty($_SESSION['_csrf_token'])) {
            Csrf::init();
        } else {
            Csrf::regenerate();
        }

        CustomerRepository::linkOrders((int) $customer['id'], $customer['phoneNorm']);

        return true;
    }

    public static function registerAndLogin(array $input): array
    {
        $customer = CustomerRepository::register($input);

        Auth::startSession();
        session_regenerate_id(true);
        $_SESSION['customer_id'] = (int) $customer['id'];
        $_SESSION['customer_logged_in'] = true;

        require_once __DIR__ . '/Csrf.php';
        Csrf::regenerate();

        return $customer;
    }

    public static function logout(): void
    {
        Auth::startSession();
        unset($_SESSION['customer_id'], $_SESSION['customer_logged_in']);
    }

    public static function check(): bool
    {
        Auth::startSession();
        return !empty($_SESSION['customer_logged_in']) && !empty($_SESSION['customer_id']);
    }

    public static function id(): ?int
    {
        if (!self::check()) {
            return null;
        }
        return (int) $_SESSION['customer_id'];
    }

    public static function user(): ?array
    {
        $id = self::id();
        if ($id === null) {
            return null;
        }
        $customer = CustomerRepository::findById($id);
        return $customer ? CustomerRepository::publicProfile($customer) : null;
    }

    public static function requireCustomer(): void
    {
        if (!self::check()) {
            respond(['ok' => false, 'error' => 'Connexion requise pour accéder à votre espace client'], 401);
        }
    }
}
