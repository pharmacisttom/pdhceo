<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/finance_governance.php';
require_once __DIR__ . '/../includes/finance_pdf_extract.php';

function governance_month($value): string
{
    $month = trim((string)$value);
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) throw new RuntimeException('เดือนข้อมูลไม่ถูกต้อง');
    return $month;
}

function governance_filename_month(string $filename): ?string
{
    $name = pathinfo($filename, PATHINFO_FILENAME);
    if (preg_match('/(?:^|[^\d])(\d{1,2})[-_](\d{2,4})(?:[^\d]|$)/u', $name, $match)) {
        $monthNo = (int)$match[1];
        $year = (int)$match[2];
        if ($monthNo >= 1 && $monthNo <= 12) {
            if ($year < 100) {
                $year += 2500;
            }
            if ($year > 2400) {
                $year -= 543;
            }
            if ($year >= 2000 && $year <= 2100) {
                return sprintf('%04d-%02d', $year, $monthNo);
            }
        }
    }

    $months = [
        'ม.ค.' => '01', 'มกราคม' => '01',
        'ก.พ.' => '02', 'กุมภาพันธ์' => '02',
        'มี.ค.' => '03', 'มีนาคม' => '03',
        'เม.ย.' => '04', 'เมษายน' => '04',
        'พ.ค.' => '05', 'พฤษภาคม' => '05',
        'มิ.ย.' => '06', 'มิถุนายน' => '06',
        'ก.ค.' => '07', 'กรกฎาคม' => '07',
        'ส.ค.' => '08', 'สิงหาคม' => '08',
        'ก.ย.' => '09', 'กันยายน' => '09',
        'ต.ค.' => '10', 'ตุลาคม' => '10',
        'พ.ย.' => '11', 'พฤศจิกายน' => '11',
        'ธ.ค.' => '12', 'ธันวาคม' => '12',
    ];
    foreach ($months as $label => $monthNo) {
        if (preg_match('/' . preg_quote($label, '/') . '\s*(\d{2,4})/u', $name, $match)) {
            $year = (int)$match[1];
            if ($year < 100) {
                $year += 2500;
            }
            if ($year > 2400) {
                $year -= 543;
            }
            if ($year >= 2000 && $year <= 2100) {
                return sprintf('%04d-%s', $year, $monthNo);
            }
        }
    }

    return null;
}

function governance_document_month(string $selectedMonth, string $filename): string
{
    return governance_filename_month($filename) ?: $selectedMonth;
}

function governance_number(array $data, string $key): float
{
    $value = str_replace(',', '', (string)($data[$key] ?? 0));
    return is_numeric($value) ? (float)$value : 0.0;
}

function governance_statement_upload_dir(): string
{
    return dirname(__DIR__) . '/uploads/finance_statements';
}

function governance_public_path(string $storedFilename): string
{
    return 'uploads/finance_statements/' . $storedFilename;
}

function governance_statement_documents(PDO $pdo, string $month): array
{
    $stmt = $pdo->prepare("
        SELECT d.*,
               e.extract_status,
               e.metrics_json,
               e.reconcile_json,
               e.error_message AS extract_error,
               e.extracted_at,
               i.id AS trial_balance_id,
               COALESCE(i.row_count, 0) AS trial_balance_rows,
               CASE
                   WHEN d.id IS NOT NULL AND i.id IS NOT NULL THEN 'matched'
                   WHEN d.id IS NOT NULL THEN 'pdf_only'
                   ELSE 'missing_pdf'
               END AS match_status
        FROM finance_statement_documents d
        LEFT JOIN finance_trial_balance_imports i ON i.month_year = d.month_year
        LEFT JOIN finance_statement_pdf_extracts e ON e.document_id = d.id
        WHERE d.month_year = :month
        ORDER BY d.uploaded_at DESC
    ");
    $stmt->execute([':month' => $month]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function governance_existing_statement_document(PDO $pdo, string $month, string $documentType): ?array
{
    $stmt = $pdo->prepare("
        SELECT *
        FROM finance_statement_documents
        WHERE month_year = :month
          AND document_type = :document_type
        LIMIT 1
    ");
    $stmt->execute([':month' => $month, ':document_type' => $documentType]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function governance_statement_document_archive(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT d.*,
               e.extract_status,
               e.metrics_json,
               e.reconcile_json,
               e.error_message AS extract_error,
               e.extracted_at,
               i.id AS trial_balance_id,
               COALESCE(i.row_count, 0) AS trial_balance_rows,
               CASE
                   WHEN d.id IS NOT NULL AND i.id IS NOT NULL THEN 'matched'
                   WHEN d.id IS NOT NULL THEN 'pdf_only'
                   ELSE 'missing_pdf'
               END AS match_status
        FROM finance_statement_documents d
        LEFT JOIN finance_trial_balance_imports i ON i.month_year = d.month_year
        LEFT JOIN finance_statement_pdf_extracts e ON e.document_id = d.id
        ORDER BY d.month_year DESC, d.uploaded_at DESC
        LIMIT 80
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

try {
    require_login();
    finance_governance_schema($pdo);
    finance_pdf_schema($pdo);
    finance_sync_account_mappings($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $month = (string)($_GET['month'] ?? $pdo->query('SELECT MAX(month_year) FROM finance_trial_balance_imports')->fetchColumn() ?: date('Y-m'));
        $month = governance_month($month);
        $stmt = $pdo->prepare("
            SELECT m.*, COALESCE(r.net_debit,0) AS net_debit, COALESCE(r.net_credit,0) AS net_credit
            FROM finance_account_mapping m
            LEFT JOIN finance_trial_balance_imports i ON i.month_year = :month
            LEFT JOIN finance_trial_balance_rows r ON r.import_id = i.id AND r.account_code = m.account_code
            ORDER BY m.account_code
        ");
        $stmt->execute([':month' => $month]);
        $audit = finance_mapping_audit($pdo, $month);
        $autoStmt = $pdo->prepare('SELECT * FROM finance_monthly_auto WHERE month_year = :month');
        $autoStmt->execute([':month' => $month]);
        $counts = [];
        foreach (['finance_planfin','finance_aging','finance_claim_quality','finance_cost_center','finance_asset_register','finance_inventory_usage','finance_statement_documents'] as $table) {
            $counts[$table] = (int)$pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
        }
        echo json_encode([
            'status' => 'success',
            'month' => $month,
            'mappings' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'audit' => $audit,
            'automatic' => $autoStmt->fetch(PDO::FETCH_ASSOC) ?: null,
            'statement_documents' => governance_statement_documents($pdo, $month),
            'statement_document_archive' => governance_statement_document_archive($pdo),
            'supplemental_counts' => $counts,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('Method not allowed');
    $data = str_starts_with((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'multipart/form-data')
        ? $_POST
        : json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($data)) throw new RuntimeException('รูปแบบข้อมูลไม่ถูกต้อง');
    $action = (string)($data['action'] ?? '');
    $user = (string)($_SESSION['username'] ?? 'System');

    if ($action === 'upload_statement_pdf') {
        $month = governance_month($data['month_year'] ?? '');
        $file = $_FILES['statement_pdf'] ?? null;
        if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('ไม่พบไฟล์ PDF หรืออัปโหลดไม่สำเร็จ');
        }

        $original = (string)($file['name'] ?? '');
        $month = governance_document_month($month, $original);
        $existingDocument = governance_existing_statement_document($pdo, $month, 'monthly_statement');
        if ($existingDocument && (string)($data['force_overwrite'] ?? '') !== '1') {
            throw new RuntimeException('เดือน ' . $month . ' มี PDF สรุปอยู่แล้ว: ' . (string)$existingDocument['original_filename'] . ' กรุณาตรวจไฟล์ในระบบก่อนอัปโหลดทับ');
        }
        $tmp = (string)($file['tmp_name'] ?? '');
        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > 25 * 1024 * 1024) {
            throw new RuntimeException('ไฟล์ PDF ต้องมีขนาดไม่เกิน 25 MB');
        }
        if (strtolower(pathinfo($original, PATHINFO_EXTENSION)) !== 'pdf') {
            throw new RuntimeException('รองรับเฉพาะไฟล์ .pdf เท่านั้น');
        }

        $mime = 'application/pdf';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detected = $finfo ? finfo_file($finfo, $tmp) : false;
            if ($finfo) finfo_close($finfo);
            if (is_string($detected) && $detected !== '') $mime = $detected;
        }
        if (!in_array($mime, ['application/pdf', 'application/octet-stream'], true)) {
            throw new RuntimeException('ชนิดไฟล์ไม่ถูกต้อง กรุณาเลือก PDF เท่านั้น');
        }

        $uploadDir = governance_statement_upload_dir();
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('ไม่สามารถสร้างโฟลเดอร์เก็บ PDF ได้');
        }

        $stored = $month . '-statement-' . bin2hex(random_bytes(8)) . '.pdf';
        $target = $uploadDir . '/' . $stored;
        if (!move_uploaded_file($tmp, $target)) {
            throw new RuntimeException('ไม่สามารถบันทึกไฟล์ PDF ได้');
        }

        $pdo->prepare("
            INSERT INTO finance_statement_documents
                (month_year, document_type, original_filename, stored_filename, relative_path, file_size, mime_type, note, uploaded_by)
            VALUES
                (:month_year, 'monthly_statement', :original_filename, :stored_filename, :relative_path, :file_size, :mime_type, :note, :uploaded_by)
            ON DUPLICATE KEY UPDATE
                original_filename=VALUES(original_filename),
                stored_filename=VALUES(stored_filename),
                relative_path=VALUES(relative_path),
                file_size=VALUES(file_size),
                mime_type=VALUES(mime_type),
                note=VALUES(note),
                uploaded_by=VALUES(uploaded_by),
                uploaded_at=CURRENT_TIMESTAMP
        ")->execute([
            ':month_year' => $month,
            ':original_filename' => basename($original),
            ':stored_filename' => $stored,
            ':relative_path' => governance_public_path($stored),
            ':file_size' => $size,
            ':mime_type' => $mime,
            ':note' => (string)($data['note'] ?? ''),
            ':uploaded_by' => $user,
        ]);

        $docStmt = $pdo->prepare("
            SELECT id
            FROM finance_statement_documents
            WHERE month_year = :month_year
            AND document_type = 'monthly_statement'
            LIMIT 1
        ");
        $docStmt->execute([':month_year' => $month]);
        $documentId = (int)$docStmt->fetchColumn();
        $extractResult = $documentId > 0
            ? finance_pdf_extract_document($pdo, $documentId, $month, $target)
            : ['status' => 'failed', 'metrics' => [], 'reconcile' => []];

        echo json_encode([
            'status' => 'success',
            'message' => 'บันทึก PDF สรุปงบการเงินและผูกกับเดือนงบทดลองแล้ว',
            'month' => $month,
            'pdf_extract' => [
                'status' => $extractResult['status'] ?? 'failed',
                'metric_count' => count($extractResult['metrics'] ?? []),
                'reconcile_count' => count($extractResult['reconcile'] ?? []),
            ],
            'documents' => governance_statement_documents($pdo, $month),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'upload_trial_balance_excel_document') {
        $month = governance_month($data['month_year'] ?? '');
        $file = $_FILES['trial_balance_excel'] ?? null;
        if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('ไม่พบไฟล์ Excel หรืออัปโหลดไม่สำเร็จ');
        }

        $original = (string)($file['name'] ?? '');
        $month = governance_document_month($month, $original);
        $existingDocument = governance_existing_statement_document($pdo, $month, 'trial_balance_excel');
        if ($existingDocument && (string)($data['force_overwrite'] ?? '') !== '1') {
            throw new RuntimeException('เดือน ' . $month . ' มี Excel งบทดลองอยู่แล้ว: ' . (string)$existingDocument['original_filename'] . ' กรุณาตรวจไฟล์ในระบบก่อนอัปโหลดทับ');
        }
        $tmp = (string)($file['tmp_name'] ?? '');
        $size = (int)($file['size'] ?? 0);
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if ($size <= 0 || $size > 25 * 1024 * 1024) {
            throw new RuntimeException('ไฟล์ Excel ต้องมีขนาดไม่เกิน 25 MB');
        }
        if (!in_array($extension, ['xls', 'xlsx'], true)) {
            throw new RuntimeException('รองรับเฉพาะไฟล์ .xls หรือ .xlsx เท่านั้น');
        }

        $uploadDir = governance_statement_upload_dir();
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('ไม่สามารถสร้างโฟลเดอร์เก็บเอกสารได้');
        }

        $stored = $month . '-trial-balance-' . bin2hex(random_bytes(8)) . '.' . $extension;
        $target = $uploadDir . '/' . $stored;
        if (!move_uploaded_file($tmp, $target)) {
            throw new RuntimeException('ไม่สามารถบันทึกไฟล์ Excel ได้');
        }

        $pdo->prepare("
            INSERT INTO finance_statement_documents
                (month_year, document_type, original_filename, stored_filename, relative_path, file_size, mime_type, note, uploaded_by)
            VALUES
                (:month_year, 'trial_balance_excel', :original_filename, :stored_filename, :relative_path, :file_size, :mime_type, :note, :uploaded_by)
            ON DUPLICATE KEY UPDATE
                original_filename=VALUES(original_filename),
                stored_filename=VALUES(stored_filename),
                relative_path=VALUES(relative_path),
                file_size=VALUES(file_size),
                mime_type=VALUES(mime_type),
                note=VALUES(note),
                uploaded_by=VALUES(uploaded_by),
                uploaded_at=CURRENT_TIMESTAMP
        ")->execute([
            ':month_year' => $month,
            ':original_filename' => basename($original),
            ':stored_filename' => $stored,
            ':relative_path' => governance_public_path($stored),
            ':file_size' => $size,
            ':mime_type' => $extension === 'xlsx'
                ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                : 'application/vnd.ms-excel',
            ':note' => (string)($data['note'] ?? 'ไฟล์งบทดลองฉบับเต็มสำหรับคำนวณ CFO'),
            ':uploaded_by' => $user,
        ]);

        echo json_encode([
            'status' => 'success',
            'message' => 'บันทึกไฟล์ Excel งบทดลองฉบับเต็มและผูกกับเดือนรายงานแล้ว',
            'month' => $month,
            'documents' => governance_statement_documents($pdo, $month),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'save_mapping') {
        $code = trim((string)($data['account_code'] ?? ''));
        if ($code === '') throw new RuntimeException('ไม่พบรหัสบัญชี');
        $flags = [
            'is_cash','is_ar_uc','is_ar_csmbs','is_ar_sss','is_ar_other','is_ap','is_inventory',
            'is_current_asset','is_fixed_asset','is_current_liability','is_longterm_liability','is_equity_fund',
            'is_revenue','is_revenue_operating','is_revenue_non_operating','is_lc','is_mc','is_cc',
            'is_depreciation','is_finance_cost','is_project_grant','is_op','is_ip','is_reviewed',
        ];
        $params = [':account_code' => $code, ':auto_field' => ($data['auto_field'] ?? '') ?: null, ':value_basis' => (string)($data['value_basis'] ?? 'month_debit'), ':note' => (string)($data['note'] ?? ''), ':updated_by' => $user];
        foreach ($flags as $flag) $params[':' . $flag] = !empty($data[$flag]) ? 1 : 0;
        $pdo->prepare("
            UPDATE finance_account_mapping SET
                is_cash=:is_cash,is_ar_uc=:is_ar_uc,is_ar_csmbs=:is_ar_csmbs,is_ap=:is_ap,
                is_ar_sss=:is_ar_sss,is_ar_other=:is_ar_other,is_inventory=:is_inventory,
                is_current_asset=:is_current_asset,is_fixed_asset=:is_fixed_asset,
                is_current_liability=:is_current_liability,is_longterm_liability=:is_longterm_liability,
                is_equity_fund=:is_equity_fund,is_revenue=:is_revenue,
                is_revenue_operating=:is_revenue_operating,is_revenue_non_operating=:is_revenue_non_operating,
                is_lc=:is_lc,is_mc=:is_mc,is_cc=:is_cc,is_depreciation=:is_depreciation,
                is_finance_cost=:is_finance_cost,is_project_grant=:is_project_grant,
                is_op=:is_op,is_ip=:is_ip,is_reviewed=:is_reviewed,
                auto_field=:auto_field,value_basis=:value_basis,note=:note,updated_by=:updated_by
            WHERE account_code=:account_code
        ")->execute($params);
        $months = $pdo->query('SELECT month_year FROM finance_trial_balance_imports ORDER BY month_year')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($months as $month) finance_recalculate_month($pdo, (string)$month);
        echo json_encode(['status' => 'success', 'message' => 'บันทึก Mapping และคำนวณข้อมูลย้อนหลังแล้ว'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'recalculate') {
        $result = finance_recalculate_month($pdo, governance_month($data['month'] ?? ''));
        echo json_encode(['status' => 'success', 'message' => 'คำนวณข้อมูลอัตโนมัติแล้ว', 'result' => $result], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $month = $action === 'save_asset' ? date('Y-m') : governance_month($data['month_year'] ?? '');
    if ($action === 'save_planfin') {
        $pdo->prepare("INSERT INTO finance_planfin (month_year,revenue_target,expense_budget,investment_budget,note,updated_by) VALUES (:m,:r,:e,:i,:n,:u) ON DUPLICATE KEY UPDATE revenue_target=VALUES(revenue_target),expense_budget=VALUES(expense_budget),investment_budget=VALUES(investment_budget),note=VALUES(note),updated_by=VALUES(updated_by)")
            ->execute([':m'=>$month,':r'=>governance_number($data,'revenue_target'),':e'=>governance_number($data,'expense_budget'),':i'=>governance_number($data,'investment_budget'),':n'=>(string)($data['note']??''),':u'=>$user]);
    } elseif ($action === 'save_aging') {
        $type = (string)($data['aging_type'] ?? 'AP');
        foreach (['0-30','31-60','61-90','OVER_90'] as $bucket) {
            $pdo->prepare("INSERT INTO finance_aging (month_year,aging_type,bucket,amount,updated_by) VALUES (:m,:t,:b,:a,:u) ON DUPLICATE KEY UPDATE amount=VALUES(amount),updated_by=VALUES(updated_by)")
                ->execute([':m'=>$month,':t'=>$type,':b'=>$bucket,':a'=>governance_number($data,$bucket),':u'=>$user]);
        }
    } elseif ($action === 'save_claim') {
        $pdo->prepare("INSERT INTO finance_claim_quality (month_year,claim_count,claim_lag_days,denial_count,denial_rate,updated_by) VALUES (:m,:c,:l,:d,:r,:u) ON DUPLICATE KEY UPDATE claim_count=VALUES(claim_count),claim_lag_days=VALUES(claim_lag_days),denial_count=VALUES(denial_count),denial_rate=VALUES(denial_rate),updated_by=VALUES(updated_by)")
            ->execute([':m'=>$month,':c'=>(int)governance_number($data,'claim_count'),':l'=>governance_number($data,'claim_lag_days'),':d'=>(int)governance_number($data,'denial_count'),':r'=>governance_number($data,'denial_rate'),':u'=>$user]);
    } elseif ($action === 'save_cost_center') {
        $pdo->prepare("INSERT INTO finance_cost_center (month_year,cost_center_code,cost_center_name,service_type,lc_cost,mc_cost,cc_cost,updated_by) VALUES (:m,:c,:n,:s,:l,:mc,:cc,:u) ON DUPLICATE KEY UPDATE cost_center_name=VALUES(cost_center_name),service_type=VALUES(service_type),lc_cost=VALUES(lc_cost),mc_cost=VALUES(mc_cost),cc_cost=VALUES(cc_cost),updated_by=VALUES(updated_by)")
            ->execute([':m'=>$month,':c'=>(string)$data['cost_center_code'],':n'=>(string)$data['cost_center_name'],':s'=>(string)($data['service_type']??'SHARED'),':l'=>governance_number($data,'lc_cost'),':mc'=>governance_number($data,'mc_cost'),':cc'=>governance_number($data,'cc_cost'),':u'=>$user]);
    } elseif ($action === 'save_inventory_usage') {
        $pdo->prepare("INSERT INTO finance_inventory_usage (month_year,inventory_type,beginning_balance,purchases,actual_issues,ending_balance,updated_by) VALUES (:m,:t,:b,:p,:a,:e,:u) ON DUPLICATE KEY UPDATE beginning_balance=VALUES(beginning_balance),purchases=VALUES(purchases),actual_issues=VALUES(actual_issues),ending_balance=VALUES(ending_balance),updated_by=VALUES(updated_by)")
            ->execute([':m'=>$month,':t'=>(string)($data['inventory_type']??'DRUG'),':b'=>governance_number($data,'beginning_balance'),':p'=>governance_number($data,'purchases'),':a'=>governance_number($data,'actual_issues'),':e'=>governance_number($data,'ending_balance'),':u'=>$user]);
    } elseif ($action === 'save_asset') {
        $pdo->prepare("INSERT INTO finance_asset_register (asset_code,asset_name,asset_group,cost_center_code,acquisition_date,acquisition_cost,accumulated_depreciation,monthly_depreciation,status,updated_by) VALUES (:c,:n,:g,:cc,:d,:a,:ad,:md,:s,:u) ON DUPLICATE KEY UPDATE asset_name=VALUES(asset_name),asset_group=VALUES(asset_group),cost_center_code=VALUES(cost_center_code),acquisition_date=VALUES(acquisition_date),acquisition_cost=VALUES(acquisition_cost),accumulated_depreciation=VALUES(accumulated_depreciation),monthly_depreciation=VALUES(monthly_depreciation),status=VALUES(status),updated_by=VALUES(updated_by)")
            ->execute([':c'=>(string)$data['asset_code'],':n'=>(string)$data['asset_name'],':g'=>(string)($data['asset_group']??''),':cc'=>(string)($data['cost_center_code']??''),':d'=>($data['acquisition_date']??'')?:null,':a'=>governance_number($data,'acquisition_cost'),':ad'=>governance_number($data,'accumulated_depreciation'),':md'=>governance_number($data,'monthly_depreciation'),':s'=>(string)($data['status']??'active'),':u'=>$user]);
    } else {
        throw new RuntimeException('ไม่รู้จักคำสั่งบันทึกข้อมูล');
    }
    echo json_encode(['status' => 'success', 'message' => 'บันทึกข้อมูลเรียบร้อยแล้ว'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
