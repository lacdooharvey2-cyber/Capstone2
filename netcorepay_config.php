<?php
declare(strict_types=1);

require_once __DIR__ . '/stripe_config.php';

function netcorepaySecretKey(): string
{
    loadStripeEnvironment();
    $key = trim((string)(getenv('NETCOREPAY_SECRET_KEY') ?: ''));
    if ($key === '') {
        throw new RuntimeException('NetCorePay secret key is not configured.');
    }
    return $key;
}

function netcorepayBaseUrl(): string
{
    loadStripeEnvironment();
    return rtrim((string)(getenv('NETCOREPAY_API_URL') ?: 'https://netcorepay.com'), '/');
}

function netcorepayReturnUrl(): string
{
    loadStripeEnvironment();
    $url = rtrim((string)(getenv('NETCOREPAY_RETURN_URL') ?: 'http://localhost/RedStar'), '/');
    if ($url === '') {
        throw new RuntimeException('NetCorePay return URL is not configured.');
    }
    return $url;
}

function netcorepayRequest(string $method, string $path, ?array $payload = null, array $extraHeaders = []): array
{
    $curl = curl_init(netcorepayBaseUrl() . $path);
    if ($curl === false) {
        throw new RuntimeException('Unable to initialize NetCorePay request.');
    }

    $headers = [
        'Authorization: Bearer ' . netcorepaySecretKey(),
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    foreach ($extraHeaders as $header) {
        $headers[] = $header;
    }

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
    ]);
    if ($payload !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload, JSON_THROW_ON_ERROR));
    }

    $body = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($body === false || $error !== '') {
        throw new RuntimeException('NetCorePay request failed: ' . $error);
    }
    $data = json_decode($body, true);
    if ($status < 200 || $status >= 300 || !is_array($data)) {
        $message = is_array($data)
            ? (string)($data['message'] ?? $data['error'] ?? 'Unknown NetCorePay error')
            : 'Invalid NetCorePay response';
        throw new RuntimeException('NetCorePay API error (' . $status . '): ' . $message);
    }
    return $data;
}
