<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';

pdh_start_secure_session();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/user_schema.php';

$username = strtolower(trim($_POST['username'] ?? ''));
$fullname = trim($_POST['fullname'] ?? '');
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

try {
    ensure_user_approval_schema($pdo);

    if (!preg_match('/^[a-zA-Z0-9_.-]{4,50}$/', $username)) {
        throw new RuntimeException('ชื่อผู้ใช้ต้องเป็นอังกฤษ/ตัวเลข/._- และยาวอย่างน้อย 4 ตัวอักษร');
    }

    if ($fullname === '' || mb_strlen($fullname, 'UTF-8') > 150) {
        throw new RuntimeException('กรุณากรอกชื่อ-สกุล');
    }

    if (strlen($password) < 8) {
        throw new RuntimeException('รหัสผ่านต้องยาวอย่างน้อย 8 ตัวอักษร');
    }

    if ($password !== $confirmPassword) {
        throw new RuntimeException('ยืนยันรหัสผ่านไม่ตรงกัน');
    }

    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => $username]);
    if ($stmt->fetch()) {
        throw new RuntimeException('ชื่อผู้ใช้นี้ถูกใช้แล้ว');
    }

    $stmt = $pdo->prepare("
        INSERT INTO users (
            username,
            password_hash,
            fullname,
            role,
            is_active,
            approval_status,
            created_at,
            updated_at
        ) VALUES (
            :username,
            :password_hash,
            :fullname,
            'staff',
            0,
            'pending',
            NOW(),
            NOW()
        )
    ");
    $stmt->execute([
        ':username' => $username,
        ':password_hash' => pdh_password_hash_for_storage($password),
        ':fullname' => $fullname,
    ]);

    log_action($pdo, 'register_pending', 'username=' . $username);

    echo json_encode([
        'status' => 'success',
        'message' => 'ส่งคำขอสมัครเรียบร้อยแล้ว กรุณารอ admin อนุมัติและกำหนดสิทธิ์',
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
