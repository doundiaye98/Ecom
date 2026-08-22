<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/Env.php';
require_once dirname(__DIR__) . '/includes/ChatbotService.php';

Env::load();

$input = api_input();
$action = api_action($input);

if (!Env::bool('CHATBOT_ENABLED', true)) {
    respond(['ok' => false, 'error' => 'Chatbot désactivé'], 503);
}

switch ($action) {
    case 'config':
        respond(['ok' => true, 'chatbot' => ChatbotService::config()]);

    case 'message':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond(['ok' => false, 'error' => 'POST requis'], 405);
        }
        $message = trim((string) ($input['message'] ?? ''));
        $intent = trim((string) ($input['intent'] ?? ''));
        $reply = ChatbotService::reply($message, $intent !== '' ? $intent : null);
        respond(['ok' => true, 'reply' => $reply]);

    default:
        respond(['ok' => false, 'error' => 'Action inconnue'], 400);
}
