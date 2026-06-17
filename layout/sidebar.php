<?php
// ฟังก์ชันวิเคราะห์ตำแหน่งหน้าปัจจุบันเพื่อไฮไลท์สีเมนูอัตโนมัติ
if (!function_exists('checkActiveMenu')) {
    function checkActiveMenu($keyword) {
        $current_uri = $_SERVER['PHP_SELF'];
        if (strpos($current_uri, $keyword) !== false) {
            return 'active-menu';
        }
        return '';
    }
}

// เช็คสิทธิ์การใช้งาน หากเซสชันหลุดหรือไม่มี ให้ตั้งเป็น Admin ชั่วคราวเพื่อให้เมนูแสดงผล
$user_role = $_SESSION['role'] ?? 'Admin';

require_once __DIR__ . '/../config/his_database.php';
$medlabCategories = [];
try {
    $stmt = $his->query("SELECT id, category_name FROM medlab.lab_categories WHERE is_active = 1 ORDER BY sort_order ASC, category_name ASC");
    $medlabCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}
?>
<div class="sidebar">
    <div class="logo">
        <i class="bi bi-hospital text-info"></i> PDH CEO
    </div>

    <div class="menu-header">Executive View</div>
    
    <a href="/pdhceo/index.php" class="menu <?php echo (strpos($_SERVER['PHP_SELF'], 'modules') === false && strpos($_SERVER['PHP_SELF'], 'index.php') !== false) ? 'active-menu' : ''; ?>">
        <i class="bi bi-speedometer2"></i> ภาพรวมผู้บริหาร
    </a>
    <a href="/pdhceo/dashboard.php" class="menu <?php echo checkActiveMenu('dashboard.php'); ?>">
        <i class="bi bi-graph-up-arrow"></i> แผงควบคุมหลัก
    </a>
    <a href="/pdhceo/modules/cfo/index.php" class="menu <?php echo checkActiveMenu('modules/cfo/'); ?>">
        <i class="bi bi-briefcase-fill"></i> CFO Dashboard
    </a>
    <a href="/pdhceo/modules/supply/index.php" class="menu <?php echo checkActiveMenu('modules/supply/'); ?>">
        <i class="bi bi-capsule-pill"></i> ระบบบริหารยา
    </a>

    <div class="menu-header">Operation Modules</div>
    <a href="/pdhceo/modules/opd/index.php" class="menu <?php echo checkActiveMenu('modules/opd/'); ?>">
        <i class="bi bi-people"></i> งานผู้ป่วยนอก (OPD)
    </a>
    <a href="/pdhceo/modules/ipd/index.php" class="menu <?php echo checkActiveMenu('modules/ipd/'); ?>">
        <i class="bi bi-building"></i> งานผู้ป่วยใน (IPD)
    </a>
    <a href="/pdhceo/modules/er/index.php" class="menu <?php echo checkActiveMenu('modules/er/'); ?>">
        <i class="bi bi-heart-pulse"></i> งานอุบัติเหตุฉุกเฉิน (ER)
    </a>
    <a href="/pdhceo/modules/xray/index.php" class="menu <?php echo checkActiveMenu('modules/xray/'); ?>">
        <i class="bi bi-radioactive"></i> X-ray / CT Scan
    </a>
    <a href="/pdhceo/modules/refer/index.php" class="menu <?php echo checkActiveMenu('modules/refer/'); ?>">
        <i class="bi bi-truck-front-fill"></i> ศูนย์ส่งต่อผู้ป่วย (Refer)
    </a>
    <a href="/pdhceo/modules/telemed/index.php" class="menu <?php echo checkActiveMenu('modules/telemed/'); ?>">
        <i class="bi bi-camera-video-fill"></i> Telemed Dashboard
    </a>
    <a href="/pdhceo/modules/finance/index.php" class="menu <?php echo checkActiveMenu('modules/finance/') && !checkActiveMenu('data_entry.php') && !checkActiveMenu('governance.php') ? 'active-menu' : ''; ?>">
        <i class="bi bi-wallet2"></i> บริหารการเงินและพัสดุ
    </a>
    
    <?php 
    $allowed_roles = ['admin', 'finance', 'inventory', 'staff', 'executive'];
    if (in_array(strtolower((string)$user_role), $allowed_roles, true)): 
    ?>
        <a href="/pdhceo/modules/finance/data_entry.php" class="menu ps-4 <?php echo checkActiveMenu('data_entry.php'); ?>" style="font-size: 0.9em; opacity: 0.85;">
            <i class="bi bi-pencil-square"></i> บันทึกข้อมูลประจำเดือน
        </a>
        <a href="/pdhceo/modules/finance/governance.php" class="menu ps-4 <?php echo checkActiveMenu('governance.php'); ?>" style="font-size: 0.9em; opacity: 0.85;">
            <i class="bi bi-diagram-3-fill"></i> Mapping / Governance
        </a>
    <?php endif; ?>

    <div class="menu-header">Master Data</div>
    <a href="/pdhceo/modules/druglist.php" class="menu <?php echo checkActiveMenu('druglist.php'); ?>">
        <i class="bi bi-pills"></i> รายการยา (Drug List)
    </a>
    <a href="/pdhceo/modules/lablist.php" class="menu <?php echo checkActiveMenu('lablist.php') && !isset($_GET['cat']) ? 'active-menu' : ''; ?>">
        <i class="bi bi-microscope"></i> รายการ Lab ทั้งหมด
    </a>
    <?php foreach ($medlabCategories as $cat): ?>
        <a href="/pdhceo/modules/lablist.php?cat=<?php echo $cat['id']; ?>" class="menu ps-4 <?php echo isset($_GET['cat']) && $_GET['cat'] == $cat['id'] ? 'active-menu' : ''; ?>" style="font-size: 0.85em; opacity: 0.85; min-height: 34px;">
            <i class="bi bi-dot"></i> <?php echo htmlspecialchars($cat['category_name']); ?>
        </a>
    <?php endforeach; ?>

    <?php if (strtolower((string)$user_role) === 'admin'): ?>
        <div class="menu-header">Admin</div>
        <a href="/pdhceo/modules/admin/users.php" class="menu <?php echo checkActiveMenu('modules/admin/'); ?>">
            <i class="bi bi-person-check"></i> จัดการผู้ใช้
        </a>
    <?php endif; ?>

    <div class="menu-header" style="margin-top: 30px;">System</div>
    <a href="/pdhceo/logout.php" class="menu text-danger fw-bold hover-danger">
        <i class="bi bi-box-arrow-right"></i> ออกจากระบบ
    </a>
</div>

<style>
    .sidebar {
        height: 100vh;
        min-height: 100vh;
        overflow-y: auto;
        overflow-x: hidden;
        scrollbar-width: thin;
        scrollbar-color: rgba(255,255,255,.35) transparent;
    }
    .sidebar::-webkit-scrollbar { width: 7px; }
    .sidebar::-webkit-scrollbar-track { background: transparent; }
    .sidebar::-webkit-scrollbar-thumb {
        background: rgba(255,255,255,.32);
        border-radius: 999px;
    }
    .menu {
        display: flex;
        align-items: center;
        gap: 10px;
        min-height: 44px;
        line-height: 1.25;
        white-space: normal;
    }
    .menu i {
        width: 22px;
        margin-right: 0 !important;
        flex: 0 0 22px;
        text-align: center;
    }
    .logo {
        position: sticky;
        top: 0;
        z-index: 2;
        background: linear-gradient(180deg, #0F172A 0%, rgba(15,23,42,.94) 100%);
        margin: -25px -25px 18px;
        padding: 25px 25px 18px;
    }
    /* สไตล์เสริมสำหรับปุ่ม Logout ให้ดูชัดเจนขึ้นเมื่อเอาเมาส์ชี้ */
    .hover-danger {
        transition: 0.2s;
    }
    .hover-danger:hover {
        background-color: #fee2e2 !important;
        color: #dc2626 !important;
        border-radius: 10px;
    }
    @media (max-width: 991px) {
        .sidebar {
            position: relative !important;
            width: 100% !important;
            height: auto;
            min-height: auto;
            max-height: 48vh;
            padding: 16px;
            border-radius: 0 0 18px 18px;
        }
        .logo {
            margin: -16px -16px 12px;
            padding: 16px;
            font-size: 22px;
        }
        .menu-header {
            margin: 14px 0 8px 4px;
        }
        .menu {
            padding: 10px 12px;
            margin-bottom: 6px;
            min-height: 40px;
        }
    }
</style>
