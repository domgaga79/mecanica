<?php
$isHttps = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/env.php';

try {
    $client = new Google_Client();
    $client->setClientId(app_config_required('GOOGLE_CLIENT_ID'));
    $client->setClientSecret(app_config_required('GOOGLE_CLIENT_SECRET'));
    $client->setRedirectUri(app_config('GOOGLE_REDIRECT_URI', app_base_url() . '/login_google.php'));

    $client->addScope('email');
    $client->addScope('profile');
    $client->setPrompt('select_account');

    $state = bin2hex(random_bytes(32));
    $_SESSION['google_oauth_state'] = $state;
    $client->setState($state);

    header('Location: ' . $client->createAuthUrl());
    exit;
} catch (Throwable $e) {
    error_log('[GOOGLE_OAUTH] ' . $e->getMessage());
    header('Location: /login.php?erro=google_config');
    exit;
}
