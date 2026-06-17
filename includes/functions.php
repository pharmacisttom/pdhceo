<?php
declare(strict_types=1);

function e(?string $text): string
{
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

function pdh_password_hash_for_storage(string $password): string
{
    $mode = strtolower((string)(getenv('PDH_PASSWORD_HASH_MODE') ?: 'sha256'));

    if (in_array($mode, ['bcrypt', 'native', 'password_hash'], true)) {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    return hash('sha256', $password);
}

function pdh_password_verify(string $password, string $storedHash): bool
{
    $storedHash = trim($storedHash);

    // Keep compatibility with legacy/shared web apps that store SHA-256 hex hashes.
    if (preg_match('/^[a-f0-9]{64}$/i', $storedHash)) {
        return hash_equals(strtolower($storedHash), hash('sha256', $password));
    }

    return password_verify($password, $storedHash);
}

function log_action(PDO $pdo, string $action, string $detail = ''): void
{
    try {
        $stmt = $pdo->prepare("
            INSERT INTO access_logs(user_id, username, action, detail, ip_address, user_agent)
            VALUES(:user_id, :username, :action, :detail, :ip, :ua)
        ");

        $stmt->execute([
            ':user_id' => $_SESSION['user_id'] ?? null,
            ':username' => $_SESSION['username'] ?? null,
            ':action' => $action,
            ':detail' => $detail,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':ua' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Throwable $e) {
        error_log('access log write failed: ' . $e->getMessage());
    }
}
