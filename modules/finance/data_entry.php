<?php
declare(strict_types=1);

// 1. ระบบรักษาความปลอดภัยและตรวจสอบสิทธิ์
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/database.php';

$currentMonth = date('Y-m');

// ดึงค่า Role และแปลงเป็นตัวพิมพ์เล็กทั้งหมดเพื่อป้องกันปัญหา Case-sensitive
$user_role = isset($_SESSION['role']) ? trim((string)$_SESSION['role']) : 'Staff';
$user_role_lower = strtolower($user_role);

// กำหนดกลุ่มสิทธิ์ผู้ที่มีโอกาสเข้าถึงหน้านี้ได้ทั้งหมด
$allowed_roles_lower = ['admin', 'administrator', 'finance', 'inventory', 'staff', 'executive'];

if (!in_array($user_role_lower, $allowed_roles_lower)) {
    die("<div style='padding:50px; text-align:center; font-family:sans-serif;'>
            <h2 style='color:red;'>ปฏิเสธการเข้าถึง (Access Denied)</h2>
            <p>บัญชีของคุณ ({$user_role}) ไม่มีสิทธิ์ในการเข้าถึงระบบบันทึกข้อมูลนี้</p>
            <a href='/pdhceo/index.php' style='display:inline-block; margin-top:15px; padding:10px 20px; background:#6c757d; color:white; text-decoration:none; border-radius:5px;'>กลับหน้าหลัก</a>
         </div>");
}

$finance_entries = [];
try {
    $stmt_entries = $pdo->query("
        SELECT
            f.*,
            COALESCE(NULLIF(u.fullname, ''), f.updated_by, 'System') AS recorder_name,
            CASE
                WHEN CAST(SUBSTRING(f.month_year, 6, 2) AS UNSIGNED) >= 10
                    THEN CAST(SUBSTRING(f.month_year, 1, 4) AS UNSIGNED) + 544
                ELSE CAST(SUBSTRING(f.month_year, 1, 4) AS UNSIGNED) + 543
            END AS fiscal_year
        FROM finance_monthly_data f
        LEFT JOIN users u ON u.username COLLATE utf8mb4_unicode_ci = f.updated_by COLLATE utf8mb4_unicode_ci
        ORDER BY fiscal_year DESC, f.month_year DESC
        LIMIT 120
    ");
    $finance_entries = $stmt_entries->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log($e->getMessage());
}

function finance_entry_money($value): string
{
    return number_format((float)($value ?? 0), 2);
}

function finance_entry_int($value): string
{
    return number_format((int)($value ?? 0));
}

function finance_entry_date(?string $value): string
{
    if (!$value) {
        return '-';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('d/m/Y H:i', $timestamp) : $value;
}

function finance_entry_json(array $entry): string
{
    $fields = [
        'month_year',
        'receipt_count',
        'treatment_income',
        'drug_income',
        'lab_income',
        'water_bill',
        'electric_bill',
        'compensation',
        'maintenance_fund',
        'inv_drug_value',
        'inv_medical_supply',
        'inv_science_material',
        'inv_turnover_rate',
        'procurement_count',
        'active_beds',
        'active_staff',
        'satisfaction_rate',
    ];

    $data = [];
    foreach ($fields as $field) {
        $data[$field] = (string)($entry[$field] ?? '');
    }

    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?: '{}';
}
?>

<?php include_once __DIR__ . '/../../layout/header.php'; ?>
<?php include_once __DIR__ . '/../../layout/sidebar.php'; ?>

<style>
    .content { margin-left: 260px; padding: 30px; background: #f2f5fa; min-height: 100vh; }
    .form-card { background: white; border-radius: 15px; padding: 30px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); margin-bottom: 25px; }
    .nav-pills .nav-link { border-radius: 10px; padding: 12px 20px; font-weight: bold; color: #4b5563; background-color: #f3f4f6; margin-right: 10px; transition: all 0.2s; }
    .nav-pills .nav-link.active { background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white; box-shadow: 0 4px 10px rgba(59, 130, 246, 0.3); }
    .nav-pills .nav-link:disabled { opacity: 0.5; cursor: not-allowed; }
    .form-label { font-weight: 600; color: #374151; font-size: 14px; }
    .form-control:focus { border-color: #3b82f6; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15); }
    .section-title { font-size: 16px; font-weight: bold; color: #1f2937; border-left: 4px solid #3b82f6; padding-left: 10px; margin-bottom: 20px; }
    .currency-suffix { position: relative; }
    .currency-suffix::after { content: "บาท"; position: absolute; right: 15px; top: 38px; color: #6b7280; font-weight: bold; font-size: 13px; }
    .currency-input { padding-right: 50px !important; text-align: right; font-weight: bold; font-family: 'Courier New', Courier, monospace; color: #1e3a8a; }
    .history-card { background: white; border-radius: 15px; padding: 24px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
    .history-card .table { font-size: 13px; }
    .history-card .table thead th { white-space: nowrap; background: #f8fafc; color: #334155; }
    .history-card .table td { vertical-align: middle; }
    .content .row { display: flex; flex-wrap: wrap; margin-right: -0.5rem; margin-left: -0.5rem; }
    .content .row > * { box-sizing: border-box; padding-right: 0.5rem; padding-left: 0.5rem; width: 100%; }
    .content .g-3 { row-gap: 1rem; }
    .content .col-12 { flex: 0 0 auto; width: 100%; }
    .content .col-6 { flex: 0 0 auto; width: 50%; }
    .content .d-flex { display: flex; }
    .content .flex-wrap { flex-wrap: wrap; }
    .content .justify-content-between { justify-content: space-between; }
    .content .align-items-center { align-items: center; }
    .content .gap-2 { gap: 0.5rem; }
    .content .text-end { text-align: right; }
    .content .text-center { text-align: center; }
    .content .fw-bold { font-weight: 700; }
    .content .fw-semibold { font-weight: 600; }
    .content .small { font-size: 0.875rem; }
    .content .mb-1 { margin-bottom: 0.25rem; }
    .content .mb-3 { margin-bottom: 1rem; }
    .content .mb-4 { margin-bottom: 1.5rem; }
    .content .mt-4 { margin-top: 1.5rem; }
    .content .p-2 { padding: 0.5rem; }
    .content .p-3 { padding: 1rem; }
    .content .py-2 { padding-top: 0.5rem; padding-bottom: 0.5rem; }
    .content .py-3 { padding-top: 1rem; padding-bottom: 1rem; }
    .content .px-4 { padding-left: 1.5rem; padding-right: 1.5rem; }
    .content .rounded-4 { border-radius: 1rem; }
    .content .shadow-sm { box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,.075); }
    .content .bg-white { background: #fff; }
    .content .bg-light { background: #f8fafc; }
    .content .text-dark { color: #111827; }
    .content .text-secondary, .content .text-muted { color: #64748b; }
    .content .text-primary { color: #2563eb; }
    .content .text-success { color: #059669; }
    .content .text-warning { color: #b45309; }
    .content .border { border: 1px solid #e5e7eb; }
    .content .badge { display: inline-block; padding: 0.35em 0.65em; border-radius: 0.375rem; font-size: 0.75em; line-height: 1; font-weight: 700; }
    .content .bg-primary-subtle { background: #dbeafe; }
    .content .text-info { color: #0369a1; }
    .content .bg-info-subtle { background: #e0f2fe; }
    .content .border-info-subtle { border-color: #bae6fd; }
    .content .form-control { display: block; width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; background: #fff; }
    .content .btn { display: inline-flex; align-items: center; gap: 0.35rem; border: 1px solid transparent; border-radius: 0.5rem; padding: 0.45rem 0.8rem; font-weight: 600; cursor: pointer; text-decoration: none; }
    .content .btn-success { background: #198754; color: #fff; }
    .content .btn-primary { background: #0d6efd; color: #fff; }
    .content .btn-warning { background: #ffc107; color: #111827; }
    .content .btn-outline-primary { background: #fff; border-color: #0d6efd; color: #0d6efd; }
    .content .btn-sm { padding: 0.25rem 0.5rem; font-size: 0.875rem; }
    .content .nav { display: flex; flex-wrap: wrap; gap: 0.5rem; padding-left: 0; margin-bottom: 0; list-style: none; }
    .content .nav-item { list-style: none; }
    .content .tab-pane { display: none; }
    .content .tab-pane.active, .content .tab-pane.show { display: block; opacity: 1; }
    .content .table { width: 100%; border-collapse: collapse; }
    .content .table th, .content .table td { padding: 0.75rem; border-bottom: 1px solid #e5e7eb; }
    .content .table-responsive { overflow-x: auto; }
    @media (min-width: 768px) {
        .content .col-md-3 { flex: 0 0 auto; width: 25%; }
        .content .col-md-4 { flex: 0 0 auto; width: 33.333333%; }
        .content .col-md-6 { flex: 0 0 auto; width: 50%; }
    }
</style>

<div class="content">
    <div class="topbar bg-white p-3 rounded-4 shadow-sm mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-pencil-square text-primary"></i> ระบบบันทึกข้อมูลประจำเดือน</h4>
            <div class="text-secondary small">ส่วนงานบันทึกและจัดการตัวชี้วัดองค์กร แยกตามสิทธิ์ภาระงาน</div>
        </div>
        <div>
            <span class="badge bg-light text-dark p-2 border"><i class="bi bi-person-badge text-primary"></i> สิทธิ์ของคุณ: <strong><?= htmlspecialchars($user_role) ?></strong></span>
        </div>
    </div>

    <div class="form-card py-3 mb-4">
        <div class="row align-items-center">
            <div class="col-md-3">
                <label class="form-label mb-md-0"><i class="bi bi-calendar-event"></i> เลือกเดือนที่ต้องการบันทึกข้อมูล:</label>
            </div>
            <div class="col-md-4">
                <input type="month" id="inputMonth" class="form-control bg-light fw-bold text-primary" value="<?= $currentMonth ?>">
                <div class="small text-muted mt-1" id="monthlyLoadStatus">ระบบจะโหลดข้อมูลเดิมของเดือนที่เลือกอัตโนมัติ</div>
            </div>
        </div>
    </div>

    <ul class="nav nav-pills mb-4" id="entryTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" 
                    id="finance-tab" data-bs-toggle="pill" data-bs-target="#finance-pane" type="button" role="tab"
                    >
                <i class="bi bi-wallet2 me-2"></i> จนท. การเงินและบัญชี
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" 
                    id="inventory-tab" data-bs-toggle="pill" data-bs-target="#inventory-pane" type="button" role="tab"
                    >
                <i class="bi bi-box-seam me-2"></i> จนท. คลังและพัสดุ
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" 
                    id="other-tab" data-bs-toggle="pill" data-bs-target="#other-pane" type="button" role="tab"
                    >
                <i class="bi bi-clipboard-data me-2"></i> จนท. งานสารสนเทศ / อื่นๆ
            </button>
        </li>
    </ul>

    <div class="tab-content" id="entryTabsContent">
        
        <div class="tab-pane fade show active" id="finance-pane" role="tabpanel" aria-labelledby="finance-tab">
            <div class="form-card mb-4 border border-success-subtle">
                <div class="section-title text-success">นำเข้างบทดลองรายเดือนจาก Excel</div>
                <div class="row g-3 align-items-end">
                    <div class="col-lg-7">
                        <label class="form-label">ไฟล์งบทดลอง (.xls หรือ .xlsx)</label>
                        <input type="file" class="form-control" id="trialBalanceFile" accept=".xls,.xlsx">
                        <div class="form-text">หัวตารางที่รองรับ: รหัส, บัญชี, เดบิตเดือนนี้, เครดิตเดือนนี้, เดบิตสุทธิ, เครดิตสุทธิ</div>
                    </div>
                    <div class="col-lg-5 text-lg-end">
                        <button type="button" class="btn btn-outline-success px-4 fw-bold" id="importTrialBalanceButton" disabled>
                            <i class="bi bi-file-earmark-arrow-up"></i> นำเข้างบทดลองเดือนที่เลือก
                        </button>
                    </div>
                </div>
                <div id="trialBalancePreview" class="alert alert-light border mt-3 mb-0 d-none"></div>
                <div class="table-responsive mt-4">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>เดือน</th>
                                <th>ชื่อไฟล์ล่าสุด</th>
                                <th class="text-end">จำนวนบัญชี</th>
                                <th class="text-end">เดบิตเดือนนี้</th>
                                <th class="text-end">เครดิตเดือนนี้</th>
                                <th>ผู้นำเข้า / เวลา</th>
                            </tr>
                        </thead>
                        <tbody id="trialBalanceImportHistory">
                            <tr><td colspan="6" class="text-center text-secondary py-3">กำลังโหลดประวัติการนำเข้า...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="form-card">
                <div class="section-title text-success">ข้อมูลส่วนงานการเงินและรายจ่ายสาธารณูปโภค</div>
                <form id="formFinance">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">จำนวนใบเสร็จรับเงิน (ใบ)</label>
                            <input type="number" min="0" class="form-control fw-bold" id="receipt_count" placeholder="0" required>
                        </div>
                        <div class="col-md-4 currency-suffix">
                            <label class="form-label">รายได้ตรงค่ารักษาพยาบาล</label>
                            <input type="number" min="0" step="0.01" class="form-control currency-input" id="treatment_income" placeholder="0.00" required>
                        </div>
                        <div class="col-md-4 currency-suffix">
                            <label class="form-label">รายได้ค่ายาและเวชภัณฑ์</label>
                            <input type="number" min="0" step="0.01" class="form-control currency-input" id="drug_income" placeholder="0.00" required>
                        </div>
                        <div class="col-md-4 currency-suffix">
                            <label class="form-label">รายได้ค่าบริการ Lab / X-Ray</label>
                            <input type="number" min="0" step="0.01" class="form-control currency-input" id="lab_income" placeholder="0.00" required>
                        </div>
                        <div class="col-md-4 currency-suffix">
                            <label class="form-label">ค่าน้ำประปาประจำเดือน</label>
                            <input type="number" min="0" step="0.01" class="form-control currency-input" id="water_bill" placeholder="0.00" required>
                        </div>
                        <div class="col-md-4 currency-suffix">
                            <label class="form-label">ค่าไฟฟ้าประจำเดือน</label>
                            <input type="number" min="0" step="0.01" class="form-control currency-input" id="electric_bill" placeholder="0.00" required>
                        </div>
                        <div class="col-md-6 currency-suffix">
                            <label class="form-label">ค่าตอบแทนและเงินเดือนบุคลากร</label>
                            <input type="number" min="0" step="0.01" class="form-control currency-input" id="compensation" placeholder="0.00" required>
                        </div>
                        <div class="col-md-6 currency-suffix">
                            <label class="form-label">ยอดเงินบำรุงคงเหลือสุทธิ</label>
                            <input type="number" min="0" step="0.01" class="form-control currency-input" id="maintenance_fund" placeholder="0.00" required>
                        </div>
                        <div class="col-12 text-end mt-4">
                            <button type="button" class="btn btn-success px-4 py-2 fw-bold" onclick="saveData('Finance')"><i class="bi bi-save"></i> บันทึกข้อมูลการเงิน</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="tab-pane fade" id="inventory-pane" role="tabpanel" aria-labelledby="inventory-tab">
            <div class="form-card">
                <div class="section-title text-primary">ข้อมูลมูลค่าคลังเวชภัณฑ์คงคลังและพัสดุ</div>
                <form id="formInventory">
                    <div class="row g-3">
                        <div class="col-md-6 currency-suffix">
                            <label class="form-label">มูลค่าคลังยาคงเหลือ</label>
                            <input type="number" min="0" step="0.01" class="form-control currency-input" id="inv_drug_value" placeholder="0.00">
                        </div>
                        <div class="col-md-6 currency-suffix">
                            <label class="form-label">มูลค่าคลังเวชภัณฑ์มิใช่ยาคงเหลือ</label>
                            <input type="number" min="0" step="0.01" class="form-control currency-input" id="inv_medical_supply" placeholder="0.00">
                        </div>
                        <div class="col-md-4 currency-suffix">
                            <label class="form-label">มูลค่าวัสดุวิทยาศาสตร์และการแพทย์</label>
                            <input type="number" min="0" step="0.01" class="form-control currency-input" id="inv_science_material" placeholder="0.00">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">อัตราหมุนเวียนคงคลังเฉลี่ย (เดือน)</label>
                            <input type="number" min="0" step="0.1" class="form-control fw-bold text-end" id="inv_turnover_rate" placeholder="0.0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">จำนวนรายการพัสดุที่ดำเนินการจัดซื้อจัดจ้าง (รายการ)</label>
                            <input type="number" min="0" class="form-control fw-bold text-end" id="procurement_count" placeholder="0">
                        </div>
                        <div class="col-12 text-end mt-4">
                            <button type="button" class="btn btn-primary px-4 py-2 fw-bold" onclick="saveData('Inventory')"><i class="bi bi-save"></i> บันทึกข้อมูลคลังพัสดุ</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="tab-pane fade" id="other-pane" role="tabpanel" aria-labelledby="other-tab">
            <div class="form-card">
                <div class="section-title text-warning">ข้อมูลสถิติทั่วไปและตัวชี้วัดเสริมภายนอก HIS</div>
                <form id="formOther">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">จำนวนเตียงผู้ป่วยที่เปิดให้บริการจริง (เตียง)</label>
                            <input type="number" min="0" class="form-control fw-bold text-end" id="active_beds" placeholder="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">จำนวนบุคลากรที่ปฏิบัติงานจริงประจำเดือน (คน)</label>
                            <input type="number" min="0" class="form-control fw-bold text-end" id="active_staff" placeholder="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">อัตราความพึงพอใจภาพรวมของผู้รับบริการ (%)</label>
                            <input type="number" min="0" max="100" step="0.01" class="form-control fw-bold text-end text-success" id="satisfaction_rate" placeholder="100.00">
                        </div>
                        <div class="col-12 text-end mt-4">
                            <button type="button" class="btn btn-warning px-4 py-2 fw-bold text-dark" onclick="saveData('Other')"><i class="bi bi-save"></i> บันทึกข้อมูลตัวชี้วัด</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

    </div>

    <div class="history-card mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h5 class="fw-bold mb-1 text-dark"><i class="bi bi-clock-history text-primary"></i> ประวัติการบันทึกที่ผ่านมา</h5>
                <div class="text-secondary small">แสดงรายการล่าสุดจากตาราง finance_monthly_data พร้อมชื่อผู้บันทึก</div>
            </div>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= count($finance_entries) ?> รายการ</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle w-100" id="financeHistoryTable">
                <thead>
                    <tr>
                        <th>ปีงบ</th>
                        <th>เดือน</th>
                        <th class="text-end">ใบเสร็จ</th>
                        <th class="text-end">รายได้รวม</th>
                        <th class="text-end">ค่าสาธารณูปโภค</th>
                        <th class="text-end">ค่าตอบแทน</th>
                        <th class="text-end">เงินบำรุงคงเหลือ</th>
                        <th class="text-end">มูลค่าคลังรวม</th>
                        <th class="text-end">เตียง/บุคลากร</th>
                        <th>ผู้บันทึก</th>
                        <th>อัปเดตล่าสุด</th>
                        <th class="text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($finance_entries as $entry): ?>
                        <?php
                            $income_total = (float)($entry['treatment_income'] ?? 0) + (float)($entry['drug_income'] ?? 0) + (float)($entry['lab_income'] ?? 0);
                            $utility_total = (float)($entry['water_bill'] ?? 0) + (float)($entry['electric_bill'] ?? 0);
                            $inventory_total = (float)($entry['inv_drug_value'] ?? 0) + (float)($entry['inv_medical_supply'] ?? 0) + (float)($entry['inv_science_material'] ?? 0);
                        ?>
                        <tr>
                            <td><span class="badge bg-info-subtle text-info border border-info-subtle">ปีงบ <?= e((string)($entry['fiscal_year'] ?? '-')) ?></span></td>
                            <td><span class="fw-bold text-primary"><?= e((string)($entry['month_year'] ?? '-')) ?></span></td>
                            <td class="text-end"><?= finance_entry_int($entry['receipt_count'] ?? 0) ?></td>
                            <td class="text-end"><?= finance_entry_money($income_total) ?></td>
                            <td class="text-end"><?= finance_entry_money($utility_total) ?></td>
                            <td class="text-end"><?= finance_entry_money($entry['compensation'] ?? 0) ?></td>
                            <td class="text-end"><?= finance_entry_money($entry['maintenance_fund'] ?? 0) ?></td>
                            <td class="text-end"><?= finance_entry_money($inventory_total) ?></td>
                            <td class="text-end"><?= finance_entry_int($entry['active_beds'] ?? 0) ?> / <?= finance_entry_int($entry['active_staff'] ?? 0) ?></td>
                            <td>
                                <div class="fw-semibold"><?= e((string)($entry['recorder_name'] ?? '-')) ?></div>
                                <?php if (!empty($entry['updated_by']) && (string)$entry['updated_by'] !== (string)($entry['recorder_name'] ?? '')): ?>
                                    <div class="small text-muted">@<?= e((string)$entry['updated_by']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= e(finance_entry_date($entry['updated_at'] ?? null)) ?></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick='editFinanceEntry(<?= finance_entry_json($entry) ?>)'>
                                    <i class="bi bi-pencil-square"></i> แก้ไข
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../../layout/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
let trialBalanceRows = [];
let trialBalanceFilename = '';

function trialBalanceNumber(value) {
    if (typeof value === 'number') return Number.isFinite(value) ? value : 0;
    const normalized = String(value ?? '').replace(/,/g, '').trim();
    const parsed = Number(normalized);
    return Number.isFinite(parsed) ? parsed : 0;
}

function trialBalanceMoney(value) {
    return trialBalanceNumber(value).toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function trialBalanceEscape(value) {
    return String(value ?? '').replace(/[&<>"']/g, character => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    })[character]);
}

function detectTrialBalanceMonth(filename) {
    const months = {
        'ม.ค.': '01', 'มกราคม': '01', 'ก.พ.': '02', 'กุมภาพันธ์': '02',
        'มี.ค.': '03', 'มีนาคม': '03', 'เม.ย.': '04', 'เมษายน': '04',
        'พ.ค.': '05', 'พฤษภาคม': '05', 'มิ.ย.': '06', 'มิถุนายน': '06',
        'ก.ค.': '07', 'กรกฎาคม': '07', 'ส.ค.': '08', 'สิงหาคม': '08',
        'ก.ย.': '09', 'กันยายน': '09', 'ต.ค.': '10', 'ตุลาคม': '10',
        'พ.ย.': '11', 'พฤศจิกายน': '11', 'ธ.ค.': '12', 'ธันวาคม': '12'
    };
    for (const [label, month] of Object.entries(months)) {
        const escaped = label.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const match = filename.match(new RegExp(`${escaped}[\\s._-]*(\\d{4})`));
        if (match) {
            const year = Number(match[1]) > 2400 ? Number(match[1]) - 543 : Number(match[1]);
            return `${year}-${month}`;
        }
    }
    return '';
}

function normalizeTrialBalanceHeader(value) {
    return String(value ?? '')
        .replace(/^\uFEFF/, '')
        .replace(/\s+/g, '')
        .replace(/[()]/g, '')
        .trim()
        .toLowerCase();
}

async function readTrialBalanceFile(file) {
    if (!window.XLSX) throw new Error('ไม่สามารถโหลดเครื่องมืออ่าน Excel ได้ กรุณาตรวจสอบการเชื่อมต่ออินเทอร์เน็ต');
    const workbook = XLSX.read(await file.arrayBuffer(), { type: 'array' });
    const sheet = workbook.Sheets[workbook.SheetNames[0]];
    const rawRows = XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '', raw: true });
    const expected = [
        ['รหัส', 'รหัสบัญชี'],
        ['บัญชี', 'ชื่อบัญชี'],
        ['เดบิตเดือนนี้'],
        ['เครดิตเดือนนี้'],
        ['เดบิตสุทธิ', 'ยอดเดบิตสุทธิ'],
        ['เครดิตสุทธิ', 'ยอดเครดิตสุทธิ']
    ].map(group => group.map(normalizeTrialBalanceHeader));
    const headerIndex = rawRows.slice(0, 20).findIndex(row => {
        const headers = (row || []).slice(0, 6).map(normalizeTrialBalanceHeader);
        return expected.every((aliases, index) => aliases.includes(headers[index]));
    });
    if (headerIndex < 0) {
        throw new Error('ไม่พบหัวตารางงบทดลองที่รองรับ กรุณาตรวจสอบคอลัมน์ รหัส, บัญชี, เดบิต/เครดิตเดือนนี้ และเดบิต/เครดิตสุทธิ');
    }

    return rawRows.slice(headerIndex + 1).filter(row => String(row[0] ?? '').trim() || String(row[1] ?? '').trim()).map(row => ({
        account_code: String(row[0] ?? '').trim(),
        account_name: String(row[1] ?? '').trim(),
        month_debit: trialBalanceNumber(row[2]),
        month_credit: trialBalanceNumber(row[3]),
        net_debit: trialBalanceNumber(row[4]),
        net_credit: trialBalanceNumber(row[5])
    }));
}

function renderTrialBalancePreview() {
    const preview = document.getElementById('trialBalancePreview');
    if (!trialBalanceRows.length) {
        preview.classList.add('d-none');
        return;
    }
    const totals = trialBalanceRows.reduce((sum, row) => {
        sum.monthDebit += row.month_debit;
        sum.monthCredit += row.month_credit;
        sum.netDebit += row.net_debit;
        sum.netCredit += row.net_credit;
        return sum;
    }, { monthDebit: 0, monthCredit: 0, netDebit: 0, netCredit: 0 });
    const difference = totals.monthDebit - totals.monthCredit;
    const balanceClass = Math.abs(difference) <= 0.05 ? 'text-success' : 'text-danger';
    preview.innerHTML = `<strong>${trialBalanceEscape(trialBalanceFilename)}</strong> พบ ${trialBalanceRows.length.toLocaleString('th-TH')} บัญชี
        <span class="ms-3">เดบิตเดือนนี้ ${trialBalanceMoney(totals.monthDebit)}</span>
        <span class="ms-3">เครดิตเดือนนี้ ${trialBalanceMoney(totals.monthCredit)}</span>
        <span class="ms-3 ${balanceClass}">ผลต่าง ${trialBalanceMoney(difference)}</span>`;
    preview.classList.remove('d-none');
}

async function loadTrialBalanceHistory() {
    const body = document.getElementById('trialBalanceImportHistory');
    try {
        const response = await fetch('../../api/import_finance_trial_balance.php', { credentials: 'same-origin' });
        const result = await response.json();
        if (result.status !== 'success') throw new Error(result.message || 'โหลดข้อมูลไม่สำเร็จ');
        body.innerHTML = result.imports.length ? result.imports.map(item => `<tr>
            <td class="fw-bold">${trialBalanceEscape(item.month_year)}</td>
            <td>${trialBalanceEscape(item.original_filename)}</td>
            <td class="text-end">${Number(item.row_count).toLocaleString('th-TH')}</td>
            <td class="text-end">${trialBalanceMoney(item.total_month_debit)}</td>
            <td class="text-end">${trialBalanceMoney(item.total_month_credit)}</td>
            <td>${trialBalanceEscape(item.imported_by)}<br><small class="text-secondary">${trialBalanceEscape(item.imported_at)}</small></td>
        </tr>`).join('') : '<tr><td colspan="6" class="text-center text-secondary py-3">ยังไม่มีการนำเข้างบทดลอง</td></tr>';
    } catch (error) {
        body.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-3">${trialBalanceEscape(error.message)}</td></tr>`;
    }
}

const financeMonthlyFields = [
    'receipt_count', 'treatment_income', 'drug_income', 'lab_income',
    'water_bill', 'electric_bill', 'compensation', 'maintenance_fund',
    'inv_drug_value', 'inv_medical_supply', 'inv_science_material',
    'inv_turnover_rate', 'procurement_count', 'active_beds',
    'active_staff', 'satisfaction_rate'
];

async function loadFinanceMonth(monthYear, quiet = false) {
    const status = document.getElementById('monthlyLoadStatus');
    if (!monthYear) return;
    status.textContent = `กำลังโหลดข้อมูลเดือน ${monthYear}...`;
    try {
        const response = await fetch(`../../api/save_finance_data.php?month_year=${encodeURIComponent(monthYear)}`, {
            credentials: 'same-origin'
        });
        const result = await response.json();
        if (!response.ok || result.status !== 'success') throw new Error(result.message || 'โหลดข้อมูลไม่สำเร็จ');
        financeMonthlyFields.forEach(id => setFinanceValue(id, result.data?.[id] ?? ''));
        status.textContent = result.data
            ? `โหลดข้อมูลเดิมเดือน ${monthYear} แล้ว สามารถแก้ไขและกดบันทึกแยกตามหมวดได้`
            : `เดือน ${monthYear} ยังไม่มีข้อมูลคีย์มือ สามารถเริ่มบันทึกได้`;
    } catch (error) {
        status.textContent = `โหลดข้อมูลเดือน ${monthYear} ไม่สำเร็จ: ${error.message}`;
        if (!quiet) financeNotify('โหลดข้อมูลไม่สำเร็จ', error.message, 'error');
    }
}

function initFinanceEntryPage() {
    // บังคับให้ช่องกรอกเงินแสดงทศนิยมสองตำแหน่ง .00 อัตโนมัติเมื่อผู้ใช้พิมพ์เสร็จแล้วย้ายเมาส์ออก (Blur)
    document.querySelectorAll('.currency-input').forEach(function(input) {
        input.addEventListener('blur', function() {
            let val = parseFloat(input.value);
            if (!isNaN(val)) {
                input.value = val.toFixed(2);
            }
        });
    });

    document.querySelectorAll('#entryTabs [data-bs-toggle="pill"]').forEach(function(tabButton) {
        tabButton.addEventListener('click', function() {
            document.querySelectorAll('#entryTabs .nav-link').forEach(function(btn) {
                btn.classList.remove('active');
            });
            document.querySelectorAll('#entryTabsContent .tab-pane').forEach(function(pane) {
                pane.classList.remove('show', 'active');
            });

            tabButton.classList.add('active');
            const target = document.querySelector(tabButton.getAttribute('data-bs-target'));
            if (target) {
                target.classList.add('show', 'active');
            }
        });
    });

    if (window.jQuery && $.fn.DataTable) {
        $('#financeHistoryTable').DataTable({
            order: [[0, 'desc'], [1, 'desc']],
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/th.json'
            }
        });
    }

    document.getElementById('trialBalanceFile').addEventListener('change', async function () {
        const button = document.getElementById('importTrialBalanceButton');
        button.disabled = true;
        trialBalanceRows = [];
        trialBalanceFilename = '';
        try {
            const file = this.files[0];
            if (!file) return renderTrialBalancePreview();
            trialBalanceRows = await readTrialBalanceFile(file);
            trialBalanceFilename = file.name;
            const detectedMonth = detectTrialBalanceMonth(file.name);
            if (detectedMonth) {
                document.getElementById('inputMonth').value = detectedMonth;
                await loadFinanceMonth(detectedMonth, true);
            }
            renderTrialBalancePreview();
            button.disabled = trialBalanceRows.length === 0;
        } catch (error) {
            renderTrialBalancePreview();
            financeNotify('อ่านไฟล์ไม่สำเร็จ', error.message, 'error');
        }
    });

    document.getElementById('importTrialBalanceButton').addEventListener('click', importTrialBalance);
    document.getElementById('inputMonth').addEventListener('change', function () {
        loadFinanceMonth(this.value);
    });
    loadTrialBalanceHistory();
    loadFinanceMonth(getFinanceValue('inputMonth'), true);
}

async function importTrialBalance() {
    const monthYear = getFinanceValue('inputMonth');
    if (!monthYear || !trialBalanceRows.length) {
        return financeNotify('ข้อมูลไม่ครบ', 'กรุณาเลือกเดือนและไฟล์งบทดลอง', 'warning');
    }

    const button = document.getElementById('importTrialBalanceButton');
    button.disabled = true;
    try {
        const response = await fetch('../../api/import_finance_trial_balance.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ month_year: monthYear, filename: trialBalanceFilename, rows: trialBalanceRows })
        });
        const result = await response.json();
        if (!response.ok || result.status !== 'success') throw new Error(result.message || 'นำเข้าไม่สำเร็จ');
        await loadTrialBalanceHistory();
        financeNotify('นำเข้าสำเร็จ', `${result.message} จำนวน ${result.row_count.toLocaleString('th-TH')} บัญชี`, 'success')
            .then(() => window.location.reload());
    } catch (error) {
        financeNotify('นำเข้าไม่สำเร็จ', error.message, 'error');
    } finally {
        button.disabled = false;
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initFinanceEntryPage);
} else {
    initFinanceEntryPage();
}

/*
$(document).ready(function() {
    $('.currency-input').blur(function() {
        let val = parseFloat($(this).val());
        if (!isNaN(val)) {
            $(this).val(val.toFixed(2));
        }
    });

    if ($.fn.DataTable) {
        $('#financeHistoryTable').DataTable({
            order: [[0, 'desc'], [1, 'desc']],
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/th.json'
            }
        });
    }
});
*/

function setFinanceValue(id, value) {
    const field = document.getElementById(id);
    if (field) {
        field.value = value === null || typeof value === 'undefined' ? '' : value;
    }
}

function getFinanceValue(id) {
    const field = document.getElementById(id);
    return field ? field.value : '';
}

function financeNotify(title, text, icon) {
    if (window.Swal) {
        return Swal.fire(title, text, icon);
    }
    alert(`${title}\n${text || ''}`);
    return Promise.resolve();
}

function editFinanceEntry(entry) {
    setFinanceValue('inputMonth', entry.month_year);
    setFinanceValue('receipt_count', entry.receipt_count);
    setFinanceValue('treatment_income', entry.treatment_income);
    setFinanceValue('drug_income', entry.drug_income);
    setFinanceValue('lab_income', entry.lab_income);
    setFinanceValue('water_bill', entry.water_bill);
    setFinanceValue('electric_bill', entry.electric_bill);
    setFinanceValue('compensation', entry.compensation);
    setFinanceValue('maintenance_fund', entry.maintenance_fund);
    setFinanceValue('inv_drug_value', entry.inv_drug_value);
    setFinanceValue('inv_medical_supply', entry.inv_medical_supply);
    setFinanceValue('inv_science_material', entry.inv_science_material);
    setFinanceValue('inv_turnover_rate', entry.inv_turnover_rate);
    setFinanceValue('procurement_count', entry.procurement_count);
    setFinanceValue('active_beds', entry.active_beds);
    setFinanceValue('active_staff', entry.active_staff);
    setFinanceValue('satisfaction_rate', entry.satisfaction_rate);
    document.getElementById('monthlyLoadStatus').textContent = `โหลดข้อมูลเดิมเดือน ${entry.month_year} สำหรับแก้ไขแล้ว กรุณากดบันทึกในหมวดที่แก้ไข`;

    const firstTab = document.querySelector('#finance-tab');
    if (firstTab && window.bootstrap) {
        bootstrap.Tab.getOrCreateInstance(firstTab).show();
    }

    document.getElementById('inputMonth').scrollIntoView({ behavior: 'smooth', block: 'center' });
    if (window.Swal) {
        Swal.fire({
        icon: 'info',
        title: 'โหลดข้อมูลสำหรับแก้ไขแล้ว',
        text: `เดือน ${entry.month_year || '-'}`,
        timer: 1400,
        showConfirmButton: false
        });
    }
}

function saveData(sectionType) {
    let monthYear = getFinanceValue('inputMonth');
    if(!monthYear) {
        financeNotify('แจ้งเตือน', 'กรุณาระบุเดือนประจำปีที่ต้องการบันทึกข้อมูล', 'warning');
        return;
    }

    let payload = {
        section_type: sectionType,
        month_year: monthYear
    };

    if(sectionType === 'Finance') {
        payload.receipt_count = getFinanceValue('receipt_count');
        payload.treatment_income = getFinanceValue('treatment_income');
        payload.drug_income = getFinanceValue('drug_income');
        payload.lab_income = getFinanceValue('lab_income');
        payload.water_bill = getFinanceValue('water_bill');
        payload.electric_bill = getFinanceValue('electric_bill');
        payload.compensation = getFinanceValue('compensation');
        payload.maintenance_fund = getFinanceValue('maintenance_fund');
    } else if(sectionType === 'Inventory') {
        payload.inv_drug_value = getFinanceValue('inv_drug_value');
        payload.inv_medical_supply = getFinanceValue('inv_medical_supply');
        payload.inv_science_material = getFinanceValue('inv_science_material');
        payload.inv_turnover_rate = getFinanceValue('inv_turnover_rate');
        payload.procurement_count = getFinanceValue('procurement_count');
    } else if(sectionType === 'Other') {
        payload.active_beds = getFinanceValue('active_beds');
        payload.active_staff = getFinanceValue('active_staff');
        payload.satisfaction_rate = getFinanceValue('satisfaction_rate');
    }

    if (window.Swal) {
        Swal.fire({
            title: 'กำลังตรวจสอบข้อมูล...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });
    }

    if (window.jQuery) {
        $.ajax({
        url: '../../api/save_finance_data.php',
        type: 'POST',
        data: payload,
        dataType: 'json',
        success: function(res) {
            if(res.status === 'success') {
                financeNotify('บันทึกข้อมูลเรียบร้อย!', res.message || 'ข้อมูลได้รับการอัปเดตลงสู่ฐานข้อมูลระบบแล้ว', 'success')
                    .then(() => window.location.reload());
            } else {
                financeNotify('เกิดข้อผิดพลาด', res.message || 'ไม่สามารถทำรายการได้', 'error');
            }
        },
        error: function() {
            financeNotify('ล้มเหลว', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ API บันทึกข้อมูลได้', 'error');
        }
        });
        return;
    }

    fetch('../../api/save_finance_data.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
        body: new URLSearchParams(payload).toString()
    })
        .then(response => response.json())
        .then(res => {
            if (res.status === 'success') {
                financeNotify('บันทึกข้อมูลเรียบร้อย!', res.message || 'ข้อมูลได้รับการอัปเดตลงสู่ฐานข้อมูลระบบแล้ว', 'success')
                    .then(() => window.location.reload());
            } else {
                financeNotify('เกิดข้อผิดพลาด', res.message || 'ไม่สามารถทำรายการได้', 'error');
            }
        })
        .catch(() => {
            financeNotify('ล้มเหลว', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ API บันทึกข้อมูลได้', 'error');
        });
}
</script>
