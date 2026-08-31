<?php

session_start();

require_once __DIR__ . '/googleconfig.php';

// Generate random state
$state = bin2hex(random_bytes(32));

$_SESSION['oauth2_state'] = $state;

$client->setState($state);

// Generate Google Login URL
$authUrl = $client->createAuthUrl();

// Redirect to Google
header('Location: ' . $authUrl);
exit();

?>
