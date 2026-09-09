<?php

require_once __DIR__ . '/vendor/autoload.php';

$client = new Google\Client();

// =====================================================
// GOOGLE OAUTH CREDENTIALS
// =====================================================

$client->setClientId(getenv('GOOGLE_CLIENT_ID') ?: '255687396164-9ag3a2n8phjnj1a9sla9i1ohaqquk5m8.apps.googleusercontent.com');

$client->setClientSecret(getenv('GOOGLE_CLIENT_SECRET') ?: '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');

// IMPORTANT:
// This must exactly match the URI in Google Cloud Console.
$client->setRedirectUri(
    getenv('GOOGLE_REDIRECT_URI') ?: 'http://localhost/RedStar/google_callback.php'
);

// =====================================================
// SCOPES
// =====================================================

$client->setScopes([
    'openid',
    'email',
    'profile'
]);

// Good practice for OAuth
$client->setIncludeGrantedScopes(true);
