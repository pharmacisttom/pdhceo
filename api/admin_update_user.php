<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/auth.php';
require_login();
require_admin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/user_schema.php';

$allowedRoles = ['admin', 'ceo', 'manager', 'executive', 'finance', 'inventory', 'staff'];
$allowedActions = ['approve', 'reject', 'save', 'activate', 'deactivate'];

try {
    ensure_user_approval_schema($pdo);

    $userId = (int)($_POST['user_id'] ?? 0);
    $action = trim($_POST['action'] ?? '');
    $role = strtolower(trim($_POST['role'] ?? 'staff'));
    $reason = trim($_POST['reason'] ?? '');

    if ($userId <= 0 || !in_array($action, $allowedActions, true)) {
        throw new RuntimeException('คำสั่งไม่ถูกต้อง');
    }

    if (!in_array($role, $allowedRoles, true)) {
        throw new RuntimeException('สิทธิ์ผู้ใช้ไม่ถูกต้อง');
    }

    if ($userId === (int)$_SESSION['user_id'] && in_array($action, ['reject', 'deactivate'], true)) {
        throw new RuntimeException('ไม่สามารถปฏิเสธหรือปิดบัญชีของตัวเองได้');
    }

    $stmt = $pdo->prepare('SELECT username FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$target) {
        throw new RuntimeException('ไม่พบผู้ใช้');
    }

    if ($action === 'approve') {
        $stmt = $pdo->prepare("
            UPDATE users
            SET role = :role,
                is_active = 1,
                approval_status = 'approved',
                approved_by = :admin_id,
                approved_at = NOW(),
                rejected_reason = NULL,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            ':role' => $role,
            ':admin_id' => $_SESSION['user_id'],
            ':id' => $userId,
        ]);
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("
            UPDATE users
            SET is_active = 0,
                approval_status = 'rejected',
                rejected_reason = :reason,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            ':reason' => $reason !== '' ? $reason : 'Admin rejected',
            ':id' => $userId,
        ]);
    } elseif ($action === 'activate') {
        $stmt = $pdo->prepare("
            UPDATE users
            SET is_active = 1,
                approval_status = 'approved',
                role = :role,
                approved_by = COALESCE(approved_by, :admin_id),
                approved_at = COALESCE(approved_at, NOW()),
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            ':role' => $role,
            ':admin_id' => $_SESSION['user_id'],
            ':id' => $userId,
        ]);
    } elseif ($action === 'deactivate') {
        $stmt = $pdo->prepare('UPDATE users SET is_active = 0, updated_at = NOW() WHERE id = :id');
        $stmt->execute([':id' => $userId]);
    } else {
        $stmt = $pdo->prepare('UPDATE users SET role = :role, updated_at = NOW() WHERE id = :id');
        $stmt->execute([':role' => $role, ':id' => $userId]);
    }

    log_action($pdo, 'admin_user_' . $action, 'target=' . $target['username'] . '; role=' . $role);

    echo json_encode([
        'status' => 'success',
        'message' => 'อัปเดตผู้ใช้เรียบร้อยแล้ว',
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
