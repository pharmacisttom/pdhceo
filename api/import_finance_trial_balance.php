<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/finance_governance.php';

function trial_balance_schema(PDO $pdo): void
{
    $requiredTables = [
        'finance_trial_balance_imports',
        'finance_trial_balance_rows',
        'finance_monthly_data',
    ];

    if (!finance_schema_auto_migrate_enabled()) {
        finance_assert_tables_exist($pdo, $requiredTables);

        // Keep imported months visible/editable in the monthly-entry screen without
        // overwriting any values that staff have already entered manually.
        $pdo->exec("
            INSERT IGNORE INTO finance_monthly_data (month_year, updated_by)
            SELECT month_year, imported_by
            FROM finance_trial_balance_imports
        ");
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS finance_trial_balance_imports (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            month_year CHAR(7) NOT NULL,
            original_filename VARCHAR(255) NOT NULL,
            row_count INT UNSIGNED NOT NULL DEFAULT 0,
            total_month_debit DECIMAL(18,2) NOT NULL DEFAULT 0,
            total_month_credit DECIMAL(18,2) NOT NULL DEFAULT 0,
            total_net_debit DECIMAL(18,2) NOT NULL DEFAULT 0,
            total_net_credit DECIMAL(18,2) NOT NULL DEFAULT 0,
            imported_by VARCHAR(100) NOT NULL,
            imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_finance_trial_balance_month (month_year)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS finance_trial_balance_rows (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            import_id BIGINT UNSIGNED NOT NULL,
            account_code VARCHAR(50) NOT NULL,
            account_name VARCHAR(500) NOT NULL,
            month_debit DECIMAL(18,2) NOT NULL DEFAULT 0,
            month_credit DECIMAL(18,2) NOT NULL DEFAULT 0,
            net_debit DECIMAL(18,2) NOT NULL DEFAULT 0,
            net_credit DECIMAL(18,2) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY idx_finance_trial_balance_import (import_id),
            KEY idx_finance_trial_balance_account (account_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Keep imported months visible/editable in the monthly-entry screen without
    // overwriting any values that staff have already entered manually.
    $pdo->exec("
        INSERT IGNORE INTO finance_monthly_data (month_year, updated_by)
        SELECT month_year, imported_by
        FROM finance_trial_balance_imports
    ");
}

function trial_balance_number($value): float
{
    if (is_string($value)) {
        $value = str_replace([',', ' '], '', trim($value));
    }

    if ($value === '' || $value === null || !is_numeric($value)) {
        return 0.0;
    }

    return round((float)$value, 2);
}

function trial_balance_filename_month(string $filename): ?string
{
    $months = [
        'ม.ค.' => '01', 'มกราคม' => '01', 'ก.พ.' => '02', 'กุมภาพันธ์' => '02',
        'มี.ค.' => '03', 'มีนาคม' => '03', 'เม.ย.' => '04', 'เมษายน' => '04',
        'พ.ค.' => '05', 'พฤษภาคม' => '05', 'มิ.ย.' => '06', 'มิถุนายน' => '06',
        'ก.ค.' => '07', 'กรกฎาคม' => '07', 'ส.ค.' => '08', 'สิงหาคม' => '08',
        'ก.ย.' => '09', 'กันยายน' => '09', 'ต.ค.' => '10', 'ตุลาคม' => '10',
        'พ.ย.' => '11', 'พฤศจิกายน' => '11', 'ธ.ค.' => '12', 'ธันวาคม' => '12',
    ];
    foreach ($months as $label => $month) {
        if (preg_match('/' . preg_quote($label, '/') . '[\s._-]*(\d{4})/u', $filename, $match)) {
            $year = (int)$match[1];
            if ($year > 2400) $year -= 543;
            return sprintf('%04d-%s', $year, $month);
        }
    }
    return null;
}

try {
    require_login();
    trial_balance_schema($pdo);
    finance_governance_schema($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->query("
            SELECT month_year, original_filename, row_count,
                   total_month_debit, total_month_credit,
                   total_net_debit, total_net_credit,
                   imported_by, imported_at
            FROM finance_trial_balance_imports
            ORDER BY month_year DESC
            LIMIT 36
        ");
        echo json_encode(['status' => 'success', 'imports' => $stmt->fetchAll()], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Method not allowed');
    }

    $payload = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        throw new RuntimeException('รูปแบบข้อมูลนำเข้าไม่ถูกต้อง');
    }

    $monthYear = trim((string)($payload['month_year'] ?? ''));
    $filename = trim((string)($payload['filename'] ?? ''));
    $rows = $payload['rows'] ?? [];

    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthYear)) {
        throw new RuntimeException('กรุณาระบุเดือนนำเข้าให้ถูกต้อง');
    }
    if ($filename === '' || !is_array($rows) || count($rows) === 0) {
        throw new RuntimeException('ไม่พบรายการงบทดลองสำหรับนำเข้า');
    }
    $filenameMonth = trial_balance_filename_month($filename);
    if ($filenameMonth !== null && $filenameMonth !== $monthYear) {
        throw new RuntimeException("เดือนในชื่อไฟล์ {$filenameMonth} ไม่ตรงกับเดือนที่เลือก {$monthYear}");
    }
    if (count($rows) > 10000) {
        throw new RuntimeException('ไฟล์มีจำนวนรายการมากเกินกำหนด');
    }

    $cleanRows = [];
    $totals = ['month_debit' => 0.0, 'month_credit' => 0.0, 'net_debit' => 0.0, 'net_credit' => 0.0];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $accountCode = trim((string)($row['account_code'] ?? ''));
        $accountName = trim((string)($row['account_name'] ?? ''));
        if ($accountCode === '' && $accountName === '') {
            continue;
        }
        if ($accountCode === '' || $accountName === '') {
            throw new RuntimeException('พบบัญชีที่ไม่มีรหัสหรือชื่อบัญชี');
        }

        $clean = [
            'account_code' => mb_substr($accountCode, 0, 50),
            'account_name' => mb_substr($accountName, 0, 500),
            'month_debit' => trial_balance_number($row['month_debit'] ?? 0),
            'month_credit' => trial_balance_number($row['month_credit'] ?? 0),
            'net_debit' => trial_balance_number($row['net_debit'] ?? 0),
            'net_credit' => trial_balance_number($row['net_credit'] ?? 0),
        ];

        foreach ($totals as $key => $value) {
            $totals[$key] += $clean[$key];
        }
        $cleanRows[] = $clean;
    }

    if (count($cleanRows) === 0) {
        throw new RuntimeException('ไม่พบรายการบัญชีที่สมบูรณ์');
    }
    if (abs($totals['month_debit'] - $totals['month_credit']) > 0.05) {
        throw new RuntimeException('ยอดเดบิตและเครดิตเดือนนี้ไม่สมดุล กรุณาตรวจสอบไฟล์ก่อนนำเข้า');
    }
    if (abs($totals['net_debit'] - $totals['net_credit']) > 0.05) {
        throw new RuntimeException('ยอดเดบิตสุทธิและเครดิตสุทธิไม่สมดุล กรุณาตรวจสอบไฟล์ก่อนนำเข้า');
    }

    $importedBy = (string)($_SESSION['username'] ?? 'System');
    $pdo->beginTransaction();

    $existing = $pdo->prepare('SELECT id FROM finance_trial_balance_imports WHERE month_year = :month_year FOR UPDATE');
    $existing->execute([':month_year' => $monthYear]);
    $importId = $existing->fetchColumn();

    if ($importId) {
        $pdo->prepare('DELETE FROM finance_trial_balance_rows WHERE import_id = :import_id')
            ->execute([':import_id' => $importId]);
        $update = $pdo->prepare("
            UPDATE finance_trial_balance_imports
            SET original_filename = :filename, row_count = :row_count,
                total_month_debit = :month_debit, total_month_credit = :month_credit,
                total_net_debit = :net_debit, total_net_credit = :net_credit,
                imported_by = :imported_by, imported_at = NOW()
            WHERE id = :id
        ");
        $update->execute([
            ':filename' => mb_substr($filename, 0, 255),
            ':row_count' => count($cleanRows),
            ':month_debit' => round($totals['month_debit'], 2),
            ':month_credit' => round($totals['month_credit'], 2),
            ':net_debit' => round($totals['net_debit'], 2),
            ':net_credit' => round($totals['net_credit'], 2),
            ':imported_by' => $importedBy,
            ':id' => $importId,
        ]);
    } else {
        $insertImport = $pdo->prepare("
            INSERT INTO finance_trial_balance_imports
                (month_year, original_filename, row_count, total_month_debit, total_month_credit,
                 total_net_debit, total_net_credit, imported_by)
            VALUES
                (:month_year, :filename, :row_count, :month_debit, :month_credit,
                 :net_debit, :net_credit, :imported_by)
        ");
        $insertImport->execute([
            ':month_year' => $monthYear,
            ':filename' => mb_substr($filename, 0, 255),
            ':row_count' => count($cleanRows),
            ':month_debit' => round($totals['month_debit'], 2),
            ':month_credit' => round($totals['month_credit'], 2),
            ':net_debit' => round($totals['net_debit'], 2),
            ':net_credit' => round($totals['net_credit'], 2),
            ':imported_by' => $importedBy,
        ]);
        $importId = (int)$pdo->lastInsertId();
    }

    $ensureMonthly = $pdo->prepare("
        INSERT IGNORE INTO finance_monthly_data (month_year, updated_by)
        VALUES (:month_year, :updated_by)
    ");
    $ensureMonthly->execute([
        ':month_year' => $monthYear,
        ':updated_by' => $importedBy,
    ]);

    $insertRow = $pdo->prepare("
        INSERT INTO finance_trial_balance_rows
            (import_id, account_code, account_name, month_debit, month_credit, net_debit, net_credit)
        VALUES
            (:import_id, :account_code, :account_name, :month_debit, :month_credit, :net_debit, :net_credit)
    ");
    foreach ($cleanRows as $row) {
        $insertRow->execute([':import_id' => $importId] + array_combine(
            array_map(static fn(string $key): string => ':' . $key, array_keys($row)),
            array_values($row)
        ));
    }

    $pdo->commit();
    finance_sync_account_mappings($pdo);
    $calculation = finance_recalculate_month($pdo, $monthYear);
    echo json_encode([
        'status' => 'success',
        'message' => 'นำเข้างบทดลองรายเดือนเรียบร้อยแล้ว',
        'row_count' => count($cleanRows),
        'totals' => array_map(static fn(float $value): float => round($value, 2), $totals),
        'audit' => $calculation['audit'],
        'automatic_values' => $calculation['values'],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
