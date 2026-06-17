<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/user_schema.php';

try {
    ensure_user_approval_schema($pdo);

    $password = (string)($argv[1] ?? getenv('PDH_BOOTSTRAP_ADMIN_PASSWORD') ?: '');
    if ($password === '' || strlen($password) < 12) {
        exit("Usage: php create_admin.php <strong-password>\n");
    }

    $check = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
    $check->execute([':username' => 'admin']);

    if ($check->fetch()) {
        exit('Admin มีอยู่แล้ว');
    }

    $stmt = $pdo->prepare("
        INSERT INTO users (
            username,
            password_hash,
            fullname,
            role,
            is_active,
            approval_status,
            approved_at,
            created_at,
            updated_at
        ) VALUES (
            :username,
            :password_hash,
            :fullname,
            'admin',
            1,
            'approved',
            NOW(),
            NOW(),
            NOW()
        )
    ");
    $stmt->execute([
        ':username' => 'admin',
        ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ':fullname' => 'ผู้ดูแลระบบ PDH CEO',
    ]);

    echo 'สร้าง Admin สำเร็จ';
} catch (Throwable $e) {
    error_log($e->getMessage());
    echo 'เกิดข้อผิดพลาด: ' . $e->getMessage();
}
