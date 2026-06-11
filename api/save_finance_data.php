<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

function finance_valid_month($value): string
{
    $month = trim((string)$value);
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
        throw new RuntimeException('กรุณาระบุเดือนที่ต้องการบันทึกให้ถูกต้อง');
    }
    return $month;
}

function finance_post_number(string $key): float
{
    $value = str_replace(',', '', trim((string)($_POST[$key] ?? '0')));
    return is_numeric($value) ? (float)$value : 0.0;
}

function finance_post_int(string $key): int
{
    return (int)round(finance_post_number($key));
}

try {
    require_login();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $month_year = finance_valid_month($_GET['month_year'] ?? '');
        $stmt = $pdo->prepare('SELECT * FROM finance_monthly_data WHERE month_year = :month_year');
        $stmt->execute([':month_year' => $month_year]);
        echo json_encode([
            'status' => 'success',
            'data' => $stmt->fetch(PDO::FETCH_ASSOC) ?: null,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Method not allowed');
    }

    $month_year = finance_valid_month($_POST['month_year'] ?? '');
    $section_type = $_POST['section_type'] ?? 'Finance';
    $updated_by = $_SESSION['username'] ?? 'System';

    if (!in_array($section_type, ['Finance', 'Inventory', 'Other'], true)) {
        throw new RuntimeException('ไม่พบหมวดข้อมูลที่ต้องการบันทึก');
    }

    $pdo->beginTransaction();
    $pdo->prepare("INSERT IGNORE INTO finance_monthly_data (month_year, updated_by) VALUES (:m, :u)")
        ->execute([':m' => $month_year, ':u' => $updated_by]);

    if ($section_type === 'Finance') {
        $sql = "UPDATE finance_monthly_data SET 
                receipt_count = :receipt_count,
                treatment_income = :treatment_income,
                drug_income = :drug_income,
                lab_income = :lab_income,
                water_bill = :water_bill,
                electric_bill = :electric_bill,
                compensation = :compensation,
                maintenance_fund = :maintenance_fund,
                updated_by = :updated_by,
                updated_at = NOW()
                WHERE month_year = :month_year";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':receipt_count' => finance_post_int('receipt_count'),
            ':treatment_income' => finance_post_number('treatment_income'),
            ':drug_income' => finance_post_number('drug_income'),
            ':lab_income' => finance_post_number('lab_income'),
            ':water_bill' => finance_post_number('water_bill'),
            ':electric_bill' => finance_post_number('electric_bill'),
            ':compensation' => finance_post_number('compensation'),
            ':maintenance_fund' => finance_post_number('maintenance_fund'),
            ':updated_by' => $updated_by,
            ':month_year' => $month_year
        ]);
        
    } elseif ($section_type === 'Inventory') {
        $sql = "UPDATE finance_monthly_data SET 
                inv_drug_value = :inv_drug_value,
                inv_medical_supply = :inv_medical_supply,
                inv_science_material = :inv_science_material,
                inv_turnover_rate = :inv_turnover_rate,
                procurement_count = :procurement_count,
                updated_by = :updated_by,
                updated_at = NOW()
                WHERE month_year = :month_year";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':inv_drug_value' => finance_post_number('inv_drug_value'),
            ':inv_medical_supply' => finance_post_number('inv_medical_supply'),
            ':inv_science_material' => finance_post_number('inv_science_material'),
            ':inv_turnover_rate' => finance_post_number('inv_turnover_rate'),
            ':procurement_count' => finance_post_int('procurement_count'),
            ':updated_by' => $updated_by,
            ':month_year' => $month_year
        ]);

    } elseif ($section_type === 'Other') {
        $sql = "UPDATE finance_monthly_data SET 
                active_beds = :active_beds,
                active_staff = :active_staff,
                satisfaction_rate = :satisfaction_rate,
                updated_by = :updated_by,
                updated_at = NOW()
                WHERE month_year = :month_year";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':active_beds' => finance_post_int('active_beds'),
            ':active_staff' => finance_post_int('active_staff'),
            ':satisfaction_rate' => finance_post_number('satisfaction_rate'),
            ':updated_by' => $updated_by,
            ':month_year' => $month_year
        ]);
    }

    $pdo->commit();
    echo json_encode([
        'status' => 'success',
        'message' => 'บันทึกข้อมูลเรียบร้อยแล้ว',
        'month_year' => $month_year,
        'section_type' => $section_type,
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
