<?php
declare(strict_types=1);

require_once __DIR__ . '/stripe_config.php';

function xenditSecretKey(): string
{
    loadStripeEnvironment();
    $key = trim((string)(getenv('XENDIT_SECRET_KEY') ?: ''));
    if ($key === '') {
        throw new RuntimeException('Xendit secret key is not configured.');
    }
    return $key;
}

function xenditPublicKey(): string
{
    loadStripeEnvironment();
    return trim((string)(getenv('XENDIT_PUBLIC_KEY') ?: ''));
}

function xenditBaseUrl(): string
{
    loadStripeEnvironment();
    $url = rtrim((string)(getenv('XENDIT_BASE_URL') ?: ''), '/');
    if ($url === '') {
        throw new RuntimeException('Xendit return URL is not configured.');
    }
    $isLocalDevelopmentUrl = in_array(parse_url($url, PHP_URL_HOST), ['localhost', '127.0.0.1'], true);
    if (!str_starts_with($url, 'https://') && !$isLocalDevelopmentUrl) {
        throw new RuntimeException('Xendit return URL must use HTTPS outside local development.');
    }
    return $url;
}

function xenditRequest(string $method, string $path, ?array $payload = null, array $extraHeaders = []): array
{
    $curl = curl_init('https://api.xendit.co' . $path);
    if ($curl === false) {
        throw new RuntimeException('Unable to initialize Xendit request.');
    }

    $headers = [
        'Authorization: Basic ' . base64_encode(xenditSecretKey() . ':'),
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    foreach ($extraHeaders as $header) {
        $headers[] = $header;
    }
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
    ];
    if ($payload !== null) {
        $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_THROW_ON_ERROR);
    }
    curl_setopt_array($curl, $options);
    $body = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($body === false || $error !== '') {
        throw new RuntimeException('Xendit request failed: ' . $error);
    }
    $data = json_decode($body, true);
    if ($status < 200 || $status >= 300 || !is_array($data)) {
        $message = is_array($data) ? (string)($data['message'] ?? $data['error_code'] ?? 'Unknown Xendit error') : 'Invalid Xendit response';
        throw new RuntimeException('Xendit API error (' . $status . '): ' . $message);
    }
    return $data;
}
