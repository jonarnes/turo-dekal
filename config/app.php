<?php

declare(strict_types=1);

$envPath = dirname(__DIR__) . '/.env';
if (is_file($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $trimmed, 2);
        $key = trim($key);
        $value = trim($value);
        $value = trim($value, "\"'");

        if ($key === '') {
            continue;
        }

        if (getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

$env = static function (string $key, ?string $default = null): ?string {
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return (string) $value;
};

return [
    'base_path' => dirname(__DIR__),
    'public_url_path' => (string) $env('PUBLIC_URL_PATH', ''),
    'upload_max_bytes' => (int) $env('UPLOAD_MAX_BYTES', (string) (15 * 1024 * 1024)),
    'session_name' => (string) $env('SESSION_NAME', 'dekal_turo_sess'),
    'remember_me_cookie' => (string) $env('REMEMBER_ME_COOKIE', 'dekal_turo_remember'),
    'remember_me_lifetime_seconds' => (int) $env('REMEMBER_ME_LIFETIME_SECONDS', (string) (14 * 24 * 60 * 60)),
    'magic_link_lifetime_seconds' => (int) $env('MAGIC_LINK_LIFETIME_SECONDS', (string) (20 * 60)),
    'smtp' => [
        'host' => (string) $env('SMTP_HOST', ''),
        'port' => (int) $env('SMTP_PORT', '587'),
        'username' => (string) $env('SMTP_USERNAME', ''),
        'password' => (string) $env('SMTP_PASSWORD', ''),
        'secure' => (string) $env('SMTP_SECURE', 'tls'), // tls|ssl|none
        'from_email' => (string) $env('SMTP_FROM_EMAIL', 'noreply@example.com'),
        'from_name' => (string) $env('SMTP_FROM_NAME', 'Turo-dekal'),
    ],
];
