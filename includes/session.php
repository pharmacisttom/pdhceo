<?php
declare(strict_types=1);

function pdh_start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
    );

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/pdhceo',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_name('PDHCEOSESSID');
    session_start();
}

function pdh_is_authenticated_session(): bool
{
    return !empty($_SESSION['user_id'])
        && isset($_SESSION['is_active'])
        && (int)$_SESSION['is_active'] === 1
        && isset($_SESSION['approval_status'])
        && $_SESSION['approval_status'] === 'approved';
}
