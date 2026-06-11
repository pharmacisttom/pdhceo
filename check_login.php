<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    echo json_encode([
        'status' => 'error',
        'message' => 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT
            id,
            username,
            password_hash,
            fullname,
            role,
            is_active,
            approval_status
        FROM users
        WHERE username = :username
        LIMIT 1
    ");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        log_action($pdo, 'login_failed', 'username=' . $username);
        echo json_encode([
            'status' => 'error',
            'message' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($user['approval_status'] === 'pending') {
        log_action($pdo, 'login_pending', 'username=' . $username);
        echo json_encode([
            'status' => 'error',
            'message' => 'บัญชีนี้ยังรอผู้ดูแลระบบอนุมัติและกำหนดสิทธิ์',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($user['approval_status'] === 'rejected') {
        log_action($pdo, 'login_rejected', 'username=' . $username);
        echo json_encode([
            'status' => 'error',
            'message' => 'บัญชีนี้ไม่ได้รับอนุมัติ กรุณาติดต่อผู้ดูแลระบบ',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ((int)$user['is_active'] !== 1) {
        log_action($pdo, 'login_inactive', 'username=' . $username);
        echo json_encode([
            'status' => 'error',
            'message' => 'บัญชีนี้ถูกปิดใช้งาน',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    session_regenerate_id(true);

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['username'] = (string)$user['username'];
    $_SESSION['fullname'] = (string)$user['fullname'];
    $_SESSION['role'] = strtolower((string)$user['role']);
    $_SESSION['is_active'] = (int)$user['is_active'];
    $_SESSION['approval_status'] = (string)$user['approval_status'];
    $_SESSION['last_activity'] = time();

    $update = $pdo->prepare('UPDATE users SET last_login = NOW(), updated_at = NOW() WHERE id = :id');
    $update->execute([':id' => $user['id']]);

    log_action($pdo, 'login_success', 'เข้าสู่ระบบ PDH CEO');

    echo json_encode([
        'status' => 'success',
        'message' => 'เข้าสู่ระบบสำเร็จ',
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log($e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'ระบบขัดข้อง กรุณาติดต่อผู้ดูแลระบบ',
    ], JSON_UNESCAPED_UNICODE);
}
