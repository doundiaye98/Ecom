<?php

declare(strict_types=1);

final class RateLimiter
{
    public static function allow(string $bucket, int $maxRequests = 60, int $windowSeconds = 60): bool
    {
        $ip = client_ip();
        $key = hash('sha256', $bucket . '|' . $ip);
        $dir = dirname(__DIR__) . '/storage/cache/rate_limit';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $path = $dir . '/' . $key . '.json';
        $now = time();
        $data = ['count' => 0, 'reset' => $now + $windowSeconds];

        $fp = @fopen($path, 'c+');
        if ($fp === false) {
            return true;
        }

        try {
            if (!flock($fp, LOCK_EX)) {
                return true;
            }

            $raw = stream_get_contents($fp);
            if ($raw !== false && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            }

            if ($now >= (int) ($data['reset'] ?? 0)) {
                $data = ['count' => 0, 'reset' => $now + $windowSeconds];
            }

            $data['count'] = (int) ($data['count'] ?? 0) + 1;
            $allowed = $data['count'] <= $maxRequests;

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($data));
            fflush($fp);
            flock($fp, LOCK_UN);

            return $allowed;
        } finally {
            fclose($fp);
        }
    }

    public static function enforce(string $bucket, int $maxRequests = 60, int $windowSeconds = 60): void
    {
        if (!self::allow($bucket, $maxRequests, $windowSeconds)) {
            respond([
                'ok' => false,
                'error' => 'Trop de requêtes. Réessayez dans quelques instants.',
                'retryAfter' => $windowSeconds,
            ], 429);
        }
    }
}

function client_ip(): string
{
    $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    foreach ($headers as $header) {
        $value = $_SERVER[$header] ?? '';
        if ($value === '') {
            continue;
        }
        $ip = trim(explode(',', (string) $value)[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }
    return '0.0.0.0';
}
