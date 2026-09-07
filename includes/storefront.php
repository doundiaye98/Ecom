<?php

declare(strict_types=1);

require_once __DIR__ . '/Env.php';
require_once __DIR__ . '/ErrorHandler.php';
require_once __DIR__ . '/helpers.php';

function storefront_boot(): void
{
    if (!is_file(dirname(__DIR__) . '/config/database.php')) {
        header('Location: install.php');
        exit;
    }

    Env::load();
    ErrorHandler::register(false);
    date_default_timezone_set(Env::get('APP_TIMEZONE', 'Africa/Dakar') ?? 'Africa/Dakar');
}

function storefront_escape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function storefront_site_name(): string
{
    return Env::get('CHATBOT_BRAND_NAME', 'Native Vita') ?? 'Native Vita';
}
