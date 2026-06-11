<?php
declare(strict_types=1);

function e(?string $text): string
{
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
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
