<?php
declare(strict_types=1);

function loadStripeEnvironment(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }

    $envPath = __DIR__ . DIRECTORY_SEPARATOR . '.env';
    if (is_readable($envPath)) {
        foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if ($value !== '' && (($value[0] ?? '') === '"' || ($value[0] ?? '') === "'")) {
                $value = trim($value, "\"'");
            }
            if ($name !== '' && getenv($name) === false) {
                putenv($name . '=' . $value);
            }
        }
    }

    $loaded = true;
}

function stripeSecretKey(): string
{
    loadStripeEnvironment();
    $key = getenv('STRIPE_SECRET_KEY') ?: '';
    if ($key === '' || !str_starts_with($key, 'sk_')) {
        throw new RuntimeException('Stripe secret key is not configured.');
    }
    return $key;
}

function stripeBaseUrl(): string
{
    loadStripeEnvironment();
    return rtrim(getenv('STRIPE_BASE_URL') ?: 'http://localhost/RedStar', '/');
}
?>
