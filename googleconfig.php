<?php

require_once __DIR__ . '/vendor/autoload.php';

$client = new Google\Client();

// =====================================================
// GOOGLE OAUTH CREDENTIALS
// =====================================================

$client->setClientId('255687396164-uv80hsj0812ujka6dmv70staj1ulnslr.apps.googleusercontent.com');

$client->setClientSecret('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');

// IMPORTANT:
// This must exactly match the URI in Google Cloud Console.
$client->setRedirectUri(
    'http://localhost/redstar/google-callback.php'
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
