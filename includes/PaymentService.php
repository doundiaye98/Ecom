<?php

declare(strict_types=1);

final class PaymentService
{
    public static function mode(): string
    {
        $mode = strtolower(trim((string) Env::get('PAYMENT_MODE', '')));
        if (in_array($mode, ['sandbox', 'production'], true)) {
            return $mode;
        }
        $appEnv = strtolower(trim((string) Env::get('APP_ENV', 'local')));
        return in_array($appEnv, ['production', 'prod'], true) ? 'production' : 'sandbox';
    }

    public static function isSandbox(): bool
    {
        return self::mode() === 'sandbox';
    }

    public static function publicConfig(): array
    {
        return [
            'mode' => self::mode(),
            'currency' => Env::get('PAYMENT_CURRENCY', 'XOF'),
            'country' => Env::get('PAYMENT_COUNTRY', 'SN'),
            'methods' => self::availableMethods(),
            'wave' => [
                'enabled' => self::isWaveAvailable(),
                'sandbox' => self::isSandbox() && !self::hasWaveCredentials(),
                'label' => 'Wave',
            ],
            'orange' => [
                'enabled' => self::isOrangeAvailable(),
                'sandbox' => self::isSandbox() && !self::hasOrangeCredentials(),
                'label' => 'Orange Money',
            ],
            'minAmount' => self::minAmount(),
            'maxAmount' => self::maxAmount(),
        ];
    }

    /** Contrôle avant déploiement — à appeler via api/payments.php?action=check */
    public static function deploymentCheck(): array
    {
        $issues = [];
        $warnings = [];
        $mode = self::mode();

        if (!filter_var(self::appUrl(), FILTER_VALIDATE_URL)) {
            $issues[] = 'APP_URL invalide dans .env';
        } elseif (self::mode() === 'production' && !str_starts_with(self::appUrl(), 'https://')) {
            $issues[] = 'APP_URL doit être en HTTPS en production';
        }

        if ($mode === 'production') {
            if (self::methodEnabled('wave') && !self::hasWaveCredentials()) {
                $issues[] = 'Wave activé mais WAVE_API_KEY manquante';
            }
            if (self::methodEnabled('orange') && !self::hasOrangeCredentials()) {
                $issues[] = 'Orange Money activé mais OM_CLIENT_ID / OM_CLIENT_SECRET manquants';
            }
            if (self::methodEnabled('orange') && self::get('OM_MERCHANT_KEY') === '') {
                $issues[] = 'OM_MERCHANT_KEY requis pour Orange Money en production';
            }
            if (self::methodEnabled('wave') && self::get('WAVE_WEBHOOK_SECRET') === '') {
                $warnings[] = 'WAVE_WEBHOOK_SECRET non défini — webhooks non vérifiés';
            }
            if (self::methodEnabled('orange') && self::get('OM_WEBHOOK_SECRET') === '') {
                $warnings[] = 'OM_WEBHOOK_SECRET non défini — webhooks non vérifiés';
            }
        }

        if (!extension_loaded('curl')) {
            $issues[] = 'Extension PHP cURL requise pour les paiements';
        }

        $methods = self::availableMethods();
        if (!$methods) {
            $issues[] = 'Aucun moyen de paiement disponible (vérifiez PAYMENT_METHODS et les clés API)';
        }

        return [
            'ready' => $issues === [],
            'mode' => $mode,
            'appUrl' => self::appUrl(),
            'methods' => $methods,
            'issues' => $issues,
            'warnings' => $warnings,
            'wave' => [
                'configured' => self::hasWaveCredentials(),
                'enabled' => self::methodEnabled('wave'),
            ],
            'orange' => [
                'configured' => self::hasOrangeCredentials(),
                'merchantKey' => self::get('OM_MERCHANT_KEY') !== '',
                'enabled' => self::methodEnabled('orange'),
            ],
        ];
    }

    public static function availableMethods(): array
    {
        $configured = array_map('trim', explode(',', (string) Env::get('PAYMENT_METHODS', 'wave,orange,cod')));
        $methods = [];

        foreach ($configured as $method) {
            if ($method === 'wave' && self::isWaveAvailable()) {
                $methods[] = 'wave';
            } elseif ($method === 'orange' && self::isOrangeAvailable()) {
                $methods[] = 'orange';
            } elseif ($method === 'cod') {
                $methods[] = 'cod';
            }
        }

        return array_values(array_unique($methods));
    }

    public static function initiate(string $method, array $payload): array
    {
        if (!in_array($method, self::availableMethods(), true)) {
            throw new InvalidArgumentException('Moyen de paiement indisponible');
        }

        $amount = (int) ($payload['amount'] ?? 0);
        $phone = normalize_phone_sn((string) ($payload['phone'] ?? ''));
        $orderRef = trim((string) ($payload['orderRef'] ?? ('PEV-' . strtoupper(bin2hex(random_bytes(4)))));

        if ($amount < self::minAmount() || $amount > self::maxAmount()) {
            throw new InvalidArgumentException(
                'Montant invalide (' . self::minAmount() . ' – ' . self::maxAmount() . ' FCFA)'
            );
        }

        if ($method !== 'cod') {
            if ($phone === '') {
                throw new InvalidArgumentException('Numéro de téléphone requis');
            }
            if (!is_valid_phone_sn($phone)) {
                throw new InvalidArgumentException('Numéro mobile sénégalais invalide (ex. 77 123 45 67)');
            }
        }

        return match ($method) {
            'wave' => self::initiateWave($amount, $phone, $orderRef, $payload),
            'orange' => self::initiateOrange($amount, $phone, $orderRef, $payload),
            'cod' => [
                'ok' => true,
                'method' => 'cod',
                'paid' => false,
                'reference' => 'COD-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)),
                'status' => 'pending',
                'message' => 'Paiement à la livraison confirmé',
            ],
            default => throw new InvalidArgumentException('Moyen de paiement non supporté'),
        };
    }

    public static function verify(string $method, string $reference): array
    {
        if ($reference === '') {
            throw new InvalidArgumentException('Référence de paiement manquante');
        }

        return match ($method) {
            'wave' => self::verifyWave($reference),
            'orange' => self::verifyOrange($reference),
            default => throw new InvalidArgumentException('Vérification non supportée pour ce moyen'),
        };
    }

    public static function handleWebhook(string $provider, string $rawBody, array $headers = []): array
    {
        $data = json_decode($rawBody, true);
        if (!is_array($data)) {
            throw new InvalidArgumentException('Payload webhook invalide');
        }

        if ($provider === 'wave') {
            self::assertWaveWebhookSignature($rawBody, $headers);
            $sessionId = (string) ($data['data']['id'] ?? $data['id'] ?? '');
            $status = (string) ($data['data']['payment_status'] ?? $data['payment_status'] ?? '');
            $clientRef = (string) ($data['data']['client_reference'] ?? $data['client_reference'] ?? '');
            return [
                'provider' => 'wave',
                'reference' => $sessionId,
                'clientReference' => $clientRef,
                'paid' => $status === 'succeeded',
                'status' => $status,
            ];
        }

        if ($provider === 'orange') {
            self::assertOrangeWebhookSignature($rawBody, $headers);
            $status = strtoupper((string) ($data['status'] ?? ''));
            $reference = (string) ($data['pay_token'] ?? $data['reference'] ?? $data['order_id'] ?? '');
            return [
                'provider' => 'orange',
                'reference' => $reference,
                'clientReference' => (string) ($data['order_id'] ?? $data['reference'] ?? ''),
                'paid' => in_array($status, ['SUCCESS', 'SUCCEEDED', 'COMPLETED'], true),
                'status' => strtolower($status ?: 'unknown'),
            ];
        }

        throw new InvalidArgumentException('Fournisseur webhook inconnu');
    }

    private static function initiateWave(int $amount, string $phone, string $orderRef, array $payload): array
    {
        if (self::shouldSimulate('wave')) {
            return self::sandboxResult('wave', $amount, $phone);
        }

        $apiUrl = rtrim(self::get('WAVE_API_URL', 'https://api.wave.com/v1'), '/');
        $body = [
            'amount' => (string) $amount,
            'currency' => Env::get('PAYMENT_CURRENCY', 'XOF'),
            'client_reference' => $orderRef,
            'success_url' => self::returnUrl('wave', 'success', $orderRef),
            'error_url' => self::returnUrl('wave', 'error', $orderRef),
        ];

        if ($phone !== '') {
            $body['restrict_payer_mobile'] = self::waveMobile($phone);
        }

        $merchantId = self::get('WAVE_MERCHANT_ID');
        if ($merchantId !== '') {
            $body['aggregated_merchant_id'] = $merchantId;
        }

        $response = self::httpPost("$apiUrl/checkout/sessions", $body, [
            'Authorization: Bearer ' . self::get('WAVE_API_KEY'),
            'Content-Type: application/json',
        ]);

        if (!$response['ok']) {
            throw new RuntimeException(self::formatApiError('Wave', $response));
        }

        $data = $response['data'];
        $paid = ($data['payment_status'] ?? '') === 'succeeded';

        return [
            'ok' => true,
            'method' => 'wave',
            'paid' => $paid,
            'reference' => (string) ($data['id'] ?? self::ref('WAVE')),
            'status' => (string) ($data['payment_status'] ?? 'processing'),
            'checkoutUrl' => $data['wave_launch_url'] ?? null,
            'message' => $paid ? 'Paiement Wave confirmé' : 'Redirection vers Wave…',
        ];
    }

    private static function initiateOrange(int $amount, string $phone, string $orderRef, array $payload): array
    {
        if (self::shouldSimulate('orange')) {
            return self::sandboxResult('orange', $amount, $phone);
        }

        if (self::get('OM_MERCHANT_KEY') === '') {
            throw new RuntimeException('OM_MERCHANT_KEY manquant dans .env');
        }

        $token = self::orangeAccessToken();
        $apiUrl = rtrim(self::get('OM_API_URL', 'https://api.orange.com/orange-money-webpay/snp/v1'), '/');

        $body = [
            'merchant_key' => self::get('OM_MERCHANT_KEY'),
            'currency' => Env::get('PAYMENT_CURRENCY', 'XOF'),
            'order_id' => $orderRef,
            'amount' => $amount,
            'return_url' => self::returnUrl('orange', 'success', $orderRef),
            'cancel_url' => self::returnUrl('orange', 'cancel', $orderRef),
            'notif_url' => self::appUrl() . '/api/payments.php?action=webhook_orange',
            'lang' => 'fr',
            'reference' => $orderRef,
        ];

        $response = self::httpPost("$apiUrl/webpayment", $body, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ]);

        if (!$response['ok']) {
            throw new RuntimeException(self::formatApiError('Orange Money', $response));
        }

        $data = $response['data'];
        return [
            'ok' => true,
            'method' => 'orange',
            'paid' => false,
            'reference' => (string) ($data['pay_token'] ?? $data['reference'] ?? self::ref('OM')),
            'status' => 'pending',
            'checkoutUrl' => $data['payment_url'] ?? $data['redirect_url'] ?? null,
            'message' => 'Redirection vers Orange Money…',
        ];
    }

    private static function verifyWave(string $reference): array
    {
        if (self::shouldSimulate('wave')) {
            return ['ok' => true, 'paid' => true, 'reference' => $reference, 'status' => 'succeeded'];
        }

        $apiUrl = rtrim(self::get('WAVE_API_URL', 'https://api.wave.com/v1'), '/');
        $response = self::httpGet("$apiUrl/checkout/sessions/$reference", [
            'Authorization: Bearer ' . self::get('WAVE_API_KEY'),
        ]);

        if (!$response['ok']) {
            throw new RuntimeException(self::formatApiError('Wave', $response));
        }

        $paid = ($response['data']['payment_status'] ?? '') === 'succeeded';
        return [
            'ok' => true,
            'paid' => $paid,
            'reference' => $reference,
            'status' => (string) ($response['data']['payment_status'] ?? 'unknown'),
        ];
    }

    private static function verifyOrange(string $reference): array
    {
        if (self::shouldSimulate('orange')) {
            return ['ok' => true, 'paid' => true, 'reference' => $reference, 'status' => 'success'];
        }

        $token = self::orangeAccessToken();
        $apiUrl = rtrim(self::get('OM_API_URL', 'https://api.orange.com/orange-money-webpay/snp/v1'), '/');
        $response = self::httpGet("$apiUrl/transactionstatus/$reference", [
            'Authorization: Bearer ' . $token,
        ]);

        if (!$response['ok']) {
            throw new RuntimeException(self::formatApiError('Orange Money', $response));
        }

        $status = strtoupper((string) ($response['data']['status'] ?? ''));
        $paid = in_array($status, ['SUCCESS', 'SUCCEEDED', 'COMPLETED'], true);

        return [
            'ok' => true,
            'paid' => $paid,
            'reference' => $reference,
            'status' => strtolower($status ?: 'unknown'),
        ];
    }

    private static function orangeAccessToken(): string
    {
        $clientId = self::get('OM_CLIENT_ID');
        $clientSecret = self::get('OM_CLIENT_SECRET');
        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('Identifiants Orange Money manquants dans .env');
        }

        $auth = base64_encode($clientId . ':' . $clientSecret);
        $response = self::httpPost('https://api.orange.com/oauth/v3/token', [
            'grant_type' => 'client_credentials',
        ], [
            'Authorization: Basic ' . $auth,
            'Content-Type: application/x-www-form-urlencoded',
        ], true);

        if (!$response['ok'] || empty($response['data']['access_token'])) {
            throw new RuntimeException('Impossible d\'obtenir le token Orange Money');
        }

        return (string) $response['data']['access_token'];
    }

    private static function sandboxResult(string $method, int $amount, string $phone): array
    {
        if (!self::isSandbox()) {
            throw new RuntimeException(
                'Paiement simulé interdit en production. Configurez les clés API dans .env'
            );
        }

        usleep(600000);

        return [
            'ok' => true,
            'method' => $method,
            'paid' => true,
            'reference' => self::ref(strtoupper($method === 'orange' ? 'OM' : 'WAVE')),
            'status' => 'succeeded',
            'sandbox' => true,
            'message' => 'Paiement simulé (PAYMENT_MODE=sandbox)',
            'phone' => $phone,
            'amount' => $amount,
        ];
    }

    private static function shouldSimulate(string $method): bool
    {
        if (!self::isSandbox()) {
            return false;
        }
        return match ($method) {
            'wave' => !self::hasWaveCredentials(),
            'orange' => !self::hasOrangeCredentials(),
            default => false,
        };
    }

    private static function isWaveAvailable(): bool
    {
        if (!self::methodEnabled('wave')) {
            return false;
        }
        if (self::isSandbox() && !self::hasWaveCredentials()) {
            return true;
        }
        return self::hasWaveCredentials();
    }

    private static function isOrangeAvailable(): bool
    {
        if (!self::methodEnabled('orange')) {
            return false;
        }
        if (self::isSandbox() && !self::hasOrangeCredentials()) {
            return true;
        }
        return self::hasOrangeCredentials() && self::get('OM_MERCHANT_KEY') !== '';
    }

    private static function methodEnabled(string $method): bool
    {
        $list = array_map('trim', explode(',', (string) Env::get('PAYMENT_METHODS', 'wave,orange,cod')));
        if (!in_array($method, $list, true)) {
            return false;
        }
        return match ($method) {
            'wave' => Env::bool('WAVE_ENABLED', true),
            'orange' => Env::bool('ORANGE_MONEY_ENABLED', true),
            'cod' => true,
            default => false,
        };
    }

    private static function hasWaveCredentials(): bool
    {
        return self::get('WAVE_API_KEY') !== '';
    }

    private static function hasOrangeCredentials(): bool
    {
        return self::get('OM_CLIENT_ID') !== '' && self::get('OM_CLIENT_SECRET') !== '';
    }

    private static function minAmount(): int
    {
        return max(1, Env::int('PAYMENT_MIN_AMOUNT', 200));
    }

    private static function maxAmount(): int
    {
        return max(self::minAmount(), Env::int('PAYMENT_MAX_AMOUNT', 5000000));
    }

    private static function returnUrl(string $method, string $status, string $orderRef): string
    {
        return self::appUrl() . '/checkout.html?' . http_build_query([
            'payment' => $method,
            'status' => $status,
            'ref' => $orderRef,
        ]);
    }

    private static function waveMobile(string $phone): string
    {
        $digits = normalize_phone_sn($phone);
        if (str_starts_with($digits, '221')) {
            return '+' . $digits;
        }
        return $phone;
    }

    private static function assertWaveWebhookSignature(string $rawBody, array $headers): void
    {
        $secret = self::get('WAVE_WEBHOOK_SECRET');
        if ($secret === '' || self::isSandbox()) {
            return;
        }
        $signature = $headers['Wave-Signature'] ?? $headers['wave-signature'] ?? '';
        if ($signature === '') {
            throw new RuntimeException('Signature Wave manquante');
        }
        // Wave: t=timestamp,v1=signature — vérification simplifiée HMAC
        if (!preg_match('/v1=([a-f0-9]+)/i', $signature, $m)) {
            throw new RuntimeException('Format signature Wave invalide');
        }
        $expected = hash_hmac('sha256', $rawBody, $secret);
        if (!hash_equals($expected, $m[1])) {
            throw new RuntimeException('Signature Wave invalide');
        }
    }

    private static function assertOrangeWebhookSignature(string $rawBody, array $headers): void
    {
        $secret = self::get('OM_WEBHOOK_SECRET');
        if ($secret === '' || self::isSandbox()) {
            return;
        }
        $signature = $headers['X-Signature'] ?? $headers['x-signature'] ?? '';
        if ($signature === '') {
            return;
        }
        $expected = hash_hmac('sha256', $rawBody, $secret);
        if (!hash_equals($expected, $signature)) {
            throw new RuntimeException('Signature Orange Money invalide');
        }
    }

    private static function formatApiError(string $provider, array $response): string
    {
        $msg = $response['error'] ?? 'Erreur API';
        $code = $response['code'] ?? 0;
        return "$provider ($code) : $msg";
    }

    private static function get(string $key, string $default = ''): string
    {
        return Env::get($key, $default) ?? $default;
    }

    private static function ref(string $prefix): string
    {
        return $prefix . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    }

    private static function appUrl(): string
    {
        return rtrim(Env::get('APP_URL', 'http://localhost/Ecom') ?? 'http://localhost/Ecom', '/');
    }

    private static function httpPost(string $url, array $body, array $headers = [], bool $form = false): array
    {
        $ch = curl_init($url);
        $payload = $form ? http_build_query($body) : json_encode($body, JSON_UNESCAPED_UNICODE);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return ['ok' => false, 'error' => $curlError ?: 'Connexion API impossible', 'code' => 0, 'data' => []];
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $data = ['raw' => $raw];
        }

        return [
            'ok' => $code >= 200 && $code < 300,
            'code' => $code,
            'data' => $data,
            'error' => ($code >= 200 && $code < 300) ? null : ($data['message'] ?? $data['error'] ?? $data['code'] ?? 'Erreur API'),
        ];
    }

    private static function httpGet(string $url, array $headers = []): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return ['ok' => false, 'error' => $curlError ?: 'Connexion API impossible', 'code' => 0, 'data' => []];
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $data = ['raw' => $raw];
        }

        return [
            'ok' => $code >= 200 && $code < 300,
            'code' => $code,
            'data' => $data,
            'error' => ($code >= 200 && $code < 300) ? null : ($data['message'] ?? $data['error'] ?? 'Erreur API'),
        ];
    }
}
