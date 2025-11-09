<?php
session_start();
// Destroy session safely
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}
session_destroy();

// Redirect to the public site main page (use SITE_URL when available)
@require_once __DIR__ . '/config.php';
$redirect = defined('SITE_URL') ? rtrim(SITE_URL, '/') . '/' : 'login.php';
header('Location: ' . $redirect);
exit;
