<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$session_timeout = 1800;

if (!empty($_SESSION['user_id'])) {
    if (
        isset($_SESSION['last_activity']) &&
        time() - (int)$_SESSION['last_activity'] > $session_timeout
    ) {
        session_unset();
        session_destroy();
        header('Location: /pdhceo/login.php?timeout=1');
        exit;
    }

    $_SESSION['last_activity'] = time();
}

function require_login(): void
{
    if (
        empty($_SESSION['user_id']) ||
        (isset($_SESSION['is_active']) && (int)$_SESSION['is_active'] !== 1) ||
        (isset($_SESSION['approval_status']) && $_SESSION['approval_status'] !== 'approved')
    ) {
        $_SESSION = [];
        header('Location: /pdhceo/login.php');
        exit;
    }
}

function require_role(array $roles): void
{
    $currentRole = strtolower((string)($_SESSION['role'] ?? ''));
    $allowedRoles = array_map(fn($role) => strtolower((string)$role), $roles);

    if ($currentRole === '' || !in_array($currentRole, $allowedRoles, true)) {
        http_response_code(403);
        exit('ไม่มีสิทธิ์เข้าถึงหน้านี้');
    }
}

function current_user_role(): string
{
    return strtolower((string)($_SESSION['role'] ?? ''));
}

function is_admin_user(): bool
{
    return current_user_role() === 'admin';
}

function require_admin(): void
{
    require_role(['admin']);
}
