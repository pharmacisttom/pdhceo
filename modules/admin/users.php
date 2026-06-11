<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_admin();
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/user_schema.php';

ensure_user_approval_schema($pdo);

$stmt = $pdo->query("
    SELECT id, username, fullname, role, is_active, approval_status, last_login, created_at, approved_at, rejected_reason
    FROM users
    ORDER BY
        CASE approval_status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END,
        created_at DESC
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
$roles = ['admin' => 'Admin', 'ceo' => 'CEO', 'manager' => 'Manager', 'executive' => 'Executive', 'finance' => 'Finance', 'inventory' => 'Inventory', 'staff' => 'Staff'];
?>

<?php include_once __DIR__ . '/../../layout/header.php'; ?>
<?php include_once __DIR__ . '/../../layout/sidebar.php'; ?>

<style>
    .content { margin-left: 260px; padding: 30px; background: #f2f5fa; min-height: 100vh; }
    .admin-card { background: #fff; border-radius: 15px; padding: 22px; box-shadow: 0 4px 10px rgba(15, 23, 42, 0.04); }
    .status-pill { border-radius: 999px; padding: 5px 10px; font-size: 12px; font-weight: 800; }
    .pending { background: #ffedd5; color: #9a3412; }
    .approved { background: #dcfce7; color: #166534; }
    .rejected { background: #fee2e2; color: #991b1b; }
    .inactive { background: #e2e8f0; color: #475569; }
    @media (max-width: 991px) { .content { margin-left: 0; padding: 18px; } }
</style>

<div class="content">
    <div class="topbar bg-white p-3 rounded-4 shadow-sm mb-4">
        <div class="row align-items-center g-3">
            <div class="col-lg-8">
                <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-person-check-fill text-primary"></i> จัดการผู้ใช้และอนุมัติสิทธิ์</h4>
                <div class="text-secondary small">ผู้ใช้สมัครใหม่ต้องรอ admin อนุมัติและกำหนดสิทธิ์ก่อนเข้าใช้งานทุกครั้ง</div>
            </div>
            <div class="col-lg-4 text-lg-end">
                <span class="badge bg-primary p-2 rounded-3">Admin Control</span>
            </div>
        </div>
    </div>

    <div class="admin-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="usersTable">
                <thead class="table-light">
                    <tr>
                        <th>ผู้ใช้</th>
                        <th>สถานะ</th>
                        <th>สิทธิ์</th>
                        <th>สมัครเมื่อ</th>
                        <th>เข้าสู่ระบบล่าสุด</th>
                        <th class="text-end">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <?php
                            $status = (string)$user['approval_status'];
                            $active = (int)$user['is_active'] === 1;
                            $statusText = [
                                'pending' => 'รออนุมัติ',
                                'approved' => $active ? 'อนุมัติแล้ว' : 'ปิดใช้งาน',
                                'rejected' => 'ปฏิเสธ',
                            ][$status] ?? $status;
                            $statusClass = $active ? $status : 'inactive';
                        ?>
                        <tr data-user-id="<?= (int)$user['id'] ?>">
                            <td>
                                <div class="fw-bold"><?= e((string)$user['fullname']) ?></div>
                                <div class="small text-muted">@<?= e((string)$user['username']) ?></div>
                                <?php if (!empty($user['rejected_reason'])): ?>
                                    <div class="small text-danger">เหตุผล: <?= e((string)$user['rejected_reason']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><span class="status-pill <?= e($statusClass) ?>"><?= e($statusText) ?></span></td>
                            <td style="min-width: 150px;">
                                <select class="form-select form-select-sm role-select">
                                    <?php foreach ($roles as $value => $label): ?>
                                        <option value="<?= e($value) ?>" <?= $user['role'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><?= e((string)$user['created_at']) ?></td>
                            <td><?= e((string)($user['last_login'] ?? '-')) ?></td>
                            <td class="text-end">
                                <?php if ($status === 'pending' || $status === 'rejected'): ?>
                                    <button class="btn btn-success btn-sm" onclick="updateUser(<?= (int)$user['id'] ?>, 'approve')"><i class="bi bi-check2-circle"></i> อนุมัติ</button>
                                <?php endif; ?>
                                <?php if ($status === 'pending'): ?>
                                    <button class="btn btn-outline-danger btn-sm" onclick="rejectUser(<?= (int)$user['id'] ?>)"><i class="bi bi-x-circle"></i> ปฏิเสธ</button>
                                <?php endif; ?>
                                <?php if ($status === 'approved' && $active): ?>
                                    <button class="btn btn-primary btn-sm" onclick="updateUser(<?= (int)$user['id'] ?>, 'save')"><i class="bi bi-save"></i> บันทึกสิทธิ์</button>
                                    <?php if ((int)$user['id'] !== (int)$_SESSION['user_id']): ?>
                                        <button class="btn btn-outline-secondary btn-sm" onclick="updateUser(<?= (int)$user['id'] ?>, 'deactivate')"><i class="bi bi-lock"></i> ปิด</button>
                                    <?php endif; ?>
                                <?php elseif ($status === 'approved' && !$active): ?>
                                    <button class="btn btn-outline-success btn-sm" onclick="updateUser(<?= (int)$user['id'] ?>, 'activate')"><i class="bi bi-unlock"></i> เปิด</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../../layout/footer.php'; ?>
<script>
function selectedRole(userId) {
    return document.querySelector(`tr[data-user-id="${userId}"] .role-select`).value;
}

function updateUser(userId, action, reason = '') {
    Swal.fire({
        title: 'ยืนยันการอัปเดตผู้ใช้?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'ยืนยัน',
        cancelButtonText: 'ยกเลิก'
    }).then(result => {
        if (!result.isConfirmed) return;
        $.ajax({
            url: '../../api/admin_update_user.php',
            method: 'POST',
            dataType: 'json',
            data: { user_id: userId, action, role: selectedRole(userId), reason },
            success: function(res) {
                if (res.status === 'success') {
                    Swal.fire({ icon: 'success', title: 'สำเร็จ', text: res.message, timer: 900, showConfirmButton: false })
                        .then(() => window.location.reload());
                } else {
                    Swal.fire({ icon: 'warning', title: 'ไม่สำเร็จ', text: res.message });
                }
            },
            error: function() {
                Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: 'ไม่สามารถอัปเดตผู้ใช้ได้' });
            }
        });
    });
}

function rejectUser(userId) {
    Swal.fire({
        title: 'เหตุผลที่ปฏิเสธ',
        input: 'text',
        inputPlaceholder: 'เช่น ข้อมูลไม่ครบ',
        showCancelButton: true,
        confirmButtonText: 'ปฏิเสธ',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#dc2626'
    }).then(result => {
        if (!result.isConfirmed) return;
        updateUser(userId, 'reject', result.value || '');
    });
}
</script>
