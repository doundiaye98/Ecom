<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/PaymentService.php';
require_once dirname(__DIR__) . '/includes/ChatbotService.php';

$input = api_input();
$action = api_action($input);

switch ($action) {
    case 'public':
        $settings = SettingsRepository::allPublic();
        if (Env::get('WHATSAPP_NUMBER')) {
            $settings['whatsapp'] = Env::get('WHATSAPP_NUMBER');
        }
        $settings['payments'] = PaymentService::publicConfig();
        $settings['chatbot'] = ChatbotService::config();
        respond(['ok' => true, 'settings' => $settings]);

    case 'save':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond(['ok' => false, 'error' => 'POST requis'], 405);
        }
        api_require_admin_csrf($input);

        $allowed = ['shipping_fee', 'free_shipping_from', 'site_name', 'whatsapp', 'contact_email'];
        foreach ($allowed as $key) {
            if (isset($input[$key])) {
                SettingsRepository::set($key, (string) $input[$key]);
            }
        }
        respond(['ok' => true, 'settings' => SettingsRepository::allPublic()]);

    default:
        respond(['ok' => false, 'error' => 'Action inconnue'], 400);
}
