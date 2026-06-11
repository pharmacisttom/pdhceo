<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <title>เข้าสู่ระบบ PDH CEO</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; }
        .login-card { max-width: 460px; width: 100%; border-radius: 18px; }
        .brand-dot { width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; background: #2563eb; color: #fff; font-weight: 800; }
        .nav-pills .nav-link { font-weight: 700; border-radius: 12px; }
        .nav-pills .nav-link.active { background: #2563eb; }
    </style>
</head>

<body class="login-bg">

<div class="container min-vh-100 d-flex align-items-center justify-content-center py-4">
    <div class="card login-card shadow-lg border-0">
        <div class="card-body p-4">
            <div class="text-center mb-4">
                <div class="brand-dot mb-2">PDH</div>
                <h3 class="fw-bold mb-1">PDH CEO</h3>
                <p class="text-muted mb-0">Executive Dashboard</p>
            </div>

            <ul class="nav nav-pills nav-fill bg-light rounded-4 p-1 mb-4" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#loginPane" type="button">เข้าสู่ระบบ</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#registerPane" type="button">สมัครใช้งาน</button>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="loginPane">
                    <form id="loginForm" autocomplete="off">
                        <div class="mb-3">
                            <label class="form-label">ชื่อผู้ใช้</label>
                            <input type="text" name="username" class="form-control form-control-lg" required autofocus>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">รหัสผ่าน</label>
                            <input type="password" name="password" class="form-control form-control-lg" required>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold">เข้าสู่ระบบ</button>
                    </form>
                </div>

                <div class="tab-pane fade" id="registerPane">
                    <form id="registerForm" autocomplete="off">
                        <div class="mb-3">
                            <label class="form-label">ชื่อ-สกุล</label>
                            <input type="text" name="fullname" class="form-control form-control-lg" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">ชื่อผู้ใช้</label>
                            <input type="text" name="username" class="form-control form-control-lg" minlength="4" required>
                            <div class="form-text">ใช้ตัวอักษรอังกฤษ ตัวเลข จุด ขีดล่าง หรือขีดกลาง</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">รหัสผ่าน</label>
                            <input type="password" name="password" class="form-control form-control-lg" minlength="8" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">ยืนยันรหัสผ่าน</label>
                            <input type="password" name="confirm_password" class="form-control form-control-lg" minlength="8" required>
                        </div>
                        <button type="submit" class="btn btn-success btn-lg w-100 fw-bold">ส่งคำขอให้ admin อนุมัติ</button>
                        <div class="small text-muted text-center mt-3">บัญชีใหม่จะยังเข้าใช้งานไม่ได้จนกว่า admin จะอนุมัติและกำหนดสิทธิ์</div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$('#loginForm').on('submit', function(e) {
    e.preventDefault();

    $.ajax({
        url: 'check_login.php',
        method: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(res) {
            if (res.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'เข้าสู่ระบบสำเร็จ',
                    timer: 900,
                    showConfirmButton: false
                }).then(function() {
                    window.location.href = 'index.php';
                });
            } else {
                Swal.fire({ icon: 'error', title: 'เข้าสู่ระบบไม่สำเร็จ', text: res.message });
            }
        },
        error: function() {
            Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: 'ไม่สามารถเชื่อมต่อระบบได้' });
        }
    });
});

$('#registerForm').on('submit', function(e) {
    e.preventDefault();

    $.ajax({
        url: 'api/register_user.php',
        method: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(res) {
            if (res.status === 'success') {
                Swal.fire({ icon: 'success', title: 'ส่งคำขอแล้ว', text: res.message }).then(function() {
                    $('#registerForm')[0].reset();
                    $('[data-bs-target="#loginPane"]').trigger('click');
                });
            } else {
                Swal.fire({ icon: 'warning', title: 'สมัครไม่สำเร็จ', text: res.message });
            }
        },
        error: function() {
            Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: 'ไม่สามารถส่งคำขอสมัครได้' });
        }
    });
});
</script>

<?php if (isset($_GET['timeout'])): ?>
<script>
Swal.fire({
    icon: 'warning',
    title: 'Session หมดอายุ',
    text: 'กรุณาเข้าสู่ระบบใหม่'
});
</script>
<?php endif; ?>

</body>
</html>
