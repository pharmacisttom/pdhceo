<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

function finance_safe_utf8($value, string $fallback = 'ไม่ระบุ'): string
{
    if ($value === null || $value === '') {
        return $fallback;
    }

    $text = (string)$value;
    if (preg_match('//u', $text)) {
        return trim((string)preg_replace('/\s+/u', ' ', $text));
    }

    $converted = @iconv('TIS-620', 'UTF-8//IGNORE', $text);
    $output = $converted !== false && $converted !== '' ? $converted : $text;
    return trim((string)preg_replace('/\s+/u', ' ', $output));
}

function finance_validate_month(string $month): string
{
    $dt = DateTimeImmutable::createFromFormat('Y-m', $month);
    return $dt && $dt->format('Y-m') === $month ? $month : date('Y-m');
}

function finance_month_label(string $month): string
{
    $thaiMonths = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $dt = new DateTimeImmutable($month . '-01');
    return $thaiMonths[((int)$dt->format('n')) - 1] . ' ' . (((int)$dt->format('Y')) + 543);
}

function finance_fiscal_context(string $month): array
{
    $dt = new DateTimeImmutable($month . '-01');
    $year = (int)$dt->format('Y');
    $monthNo = (int)$dt->format('n');

    if ($monthNo >= 10) {
        $start = sprintf('%d-10', $year);
        $end = sprintf('%d-09', $year + 1);
        $fy = $year + 1 + 543;
    } else {
        $start = sprintf('%d-10', $year - 1);
        $end = sprintf('%d-09', $year);
        $fy = $year + 543;
    }

    return [
        'year' => $fy,
        'start_month' => $start,
        'end_month' => $end,
        'start_date' => $start . '-01',
        'selected_end_date' => $dt->format('Y-m-t'),
    ];
}

function finance_float(array $row, string $key): float
{
    return (float)($row[$key] ?? 0);
}

function finance_int(array $row, string $key): int
{
    return (int)($row[$key] ?? 0);
}

function finance_calc(array $row): array
{
    $treatment = finance_float($row, 'treatment_income');
    $drug = finance_float($row, 'drug_income');
    $lab = finance_float($row, 'lab_income');
    $water = finance_float($row, 'water_bill');
    $electric = finance_float($row, 'electric_bill');
    $compensation = finance_float($row, 'compensation');
    $fund = finance_float($row, 'maintenance_fund');
    $drugInventory = finance_float($row, 'inv_drug_value');
    $medicalSupply = finance_float($row, 'inv_medical_supply');
    $scienceMaterial = finance_float($row, 'inv_science_material');

    $totalIncome = $treatment + $drug + $lab;
    $utilities = $water + $electric;
    $materialCost = $drugInventory + $medicalSupply + $scienceMaterial;
    $operatingExpense = $utilities + $compensation + $materialCost;
    $operatingProfit = $totalIncome - $operatingExpense;
    $currentAssets = $fund + $drugInventory + $medicalSupply + $scienceMaterial;
    $currentLiabilities = $utilities + $compensation;

    return [
        'receipt_count' => finance_int($row, 'receipt_count'),
        'treatment_income' => $treatment,
        'drug_income' => $drug,
        'lab_income' => $lab,
        'total_income' => $totalIncome,
        'water_bill' => $water,
        'electric_bill' => $electric,
        'utilities_bill' => $utilities,
        'compensation' => $compensation,
        'maintenance_fund' => $fund,
        'inv_drug_value' => $drugInventory,
        'inv_medical_supply' => $medicalSupply,
        'inv_science_material' => $scienceMaterial,
        'material_cost' => $materialCost,
        'operating_expense' => $operatingExpense,
        'operating_profit' => $operatingProfit,
        'current_assets' => $currentAssets,
        'current_liabilities' => $currentLiabilities,
        'cash_equivalents' => $fund,
        'cash_ratio' => $currentLiabilities > 0 ? round($fund / $currentLiabilities, 2) : 0,
        'nwc' => $currentAssets - $currentLiabilities,
        'inv_turnover_rate' => finance_float($row, 'inv_turnover_rate'),
        'procurement_count' => finance_int($row, 'procurement_count'),
        'active_beds' => finance_int($row, 'active_beds'),
        'active_staff' => finance_int($row, 'active_staff'),
        'satisfaction_rate' => finance_float($row, 'satisfaction_rate'),
    ];
}

try {
    require_once __DIR__ . '/../includes/auth.php';
    require_login();
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../config/his_database.php';
    require_once __DIR__ . '/../includes/finance_governance.php';
    finance_governance_schema($pdo);

    $month = finance_validate_month($_GET['month'] ?? date('Y-m'));
    $fy = finance_fiscal_context($month);
    $monthStart = $month . '-01';
    $monthEnd = (new DateTimeImmutable($monthStart))->format('Y-m-t');

    $stmt = $pdo->prepare('SELECT * FROM finance_monthly_data WHERE month_year = :month');
    $stmt->execute([':month' => $month]);
    $manual = $stmt->fetch(PDO::FETCH_ASSOC);
    $dataFound = (bool)$manual;
    $manual = $manual ?: ['month_year' => $month];
    $selected = finance_calc($manual);

    $stmt = $pdo->prepare("
        SELECT *
        FROM finance_monthly_data
        WHERE month_year BETWEEN :start_month AND :end_month
        ORDER BY month_year
    ");
    $stmt->execute([
        ':start_month' => $fy['start_month'],
        ':end_month' => $month,
    ]);
    $rowsByMonth = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $rowsByMonth[$row['month_year']] = $row;
    }

    $labels = [];
    $incomeTrend = [];
    $expenseTrend = [];
    $profitTrend = [];
    $materialTrend = [];
    $cashTrend = [];
    $monthRows = [];
    $cursor = new DateTimeImmutable($fy['start_month'] . '-01');
    $selectedCursor = new DateTimeImmutable($month . '-01');

    while ($cursor <= $selectedCursor) {
        $m = $cursor->format('Y-m');
        $calc = finance_calc($rowsByMonth[$m] ?? ['month_year' => $m]);
        $labels[] = finance_month_label($m);
        $incomeTrend[] = round($calc['total_income'], 2);
        $expenseTrend[] = round($calc['operating_expense'], 2);
        $profitTrend[] = round($calc['operating_profit'], 2);
        $materialTrend[] = round($calc['material_cost'], 2);
        $cashTrend[] = round($calc['cash_equivalents'], 2);
        $monthRows[] = [
            'month' => $m,
            'label' => finance_month_label($m),
            'income' => round($calc['total_income'], 2),
            'expense' => round($calc['operating_expense'], 2),
            'profit' => round($calc['operating_profit'], 2),
            'material_cost' => round($calc['material_cost'], 2),
            'cash' => round($calc['cash_equivalents'], 2),
            'data_found' => isset($rowsByMonth[$m]),
        ];
        $cursor = $cursor->modify('+1 month');
    }

    $stmt = $his->prepare("
        SELECT
            COUNT(*) AS ipd_cases,
            SUM(
                CASE
                    WHEN dateadm IS NULL OR dateadm = '0000-00-00' THEN 0
                    WHEN datedsc IS NULL OR datedsc = '0000-00-00' THEN GREATEST(DATEDIFF(:end_calc, dateadm), 1)
                    ELSE GREATEST(DATEDIFF(LEAST(datedsc, :end_limit), dateadm), 1)
                END
            ) AS patient_days,
            SUM(CASE WHEN adjrw IS NULL OR adjrw = '' THEN 0 ELSE CAST(adjrw AS DECIMAL(10,4)) END) AS sum_adjrw
        FROM ipd.ipd
        WHERE dateadm BETWEEN :start_date AND :end_date
    ");
    $stmt->execute([
        ':start_date' => $monthStart,
        ':end_date' => $monthEnd,
        ':end_calc' => $monthEnd,
        ':end_limit' => $monthEnd,
    ]);
    $ipd = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $patientDays = (int)($ipd['patient_days'] ?? 0);
    $sumAdjrw = (float)($ipd['sum_adjrw'] ?? 0);
    $ipdCases = (int)($ipd['ipd_cases'] ?? 0);
    $activeBeds = $selected['active_beds'] > 0 ? $selected['active_beds'] : 102;
    $daysInMonth = (int)(new DateTimeImmutable($monthStart))->format('t');
    $bedOccRate = ($activeBeds > 0 && $daysInMonth > 0) ? round(($patientDays / ($activeBeds * $daysInMonth)) * 100, 2) : 0;

    $stmt = $his->prepare("
        SELECT COUNT(*)
        FROM ipd.ipd
        WHERE datedsc IS NOT NULL
        AND datedsc != '0000-00-00'
        AND datedsc BETWEEN :start_date AND :end_date
    ");
    $stmt->execute([':start_date' => $monthStart, ':end_date' => $monthEnd]);
    $dischargeCount = (int)$stmt->fetchColumn();

    $fyPatientDays = [];
    $fyAdjrw = [];
    $fyOccRate = [];
    foreach ($monthRows as $row) {
        $mStart = $row['month'] . '-01';
        $mEnd = (new DateTimeImmutable($mStart))->format('Y-m-t');
        $stmt = $his->prepare("
            SELECT
                SUM(
                    CASE
                        WHEN dateadm IS NULL OR dateadm = '0000-00-00' THEN 0
                        WHEN datedsc IS NULL OR datedsc = '0000-00-00' THEN GREATEST(DATEDIFF(:end_calc, dateadm), 1)
                        ELSE GREATEST(DATEDIFF(LEAST(datedsc, :end_limit), dateadm), 1)
                    END
                ) AS patient_days,
                SUM(CASE WHEN adjrw IS NULL OR adjrw = '' THEN 0 ELSE CAST(adjrw AS DECIMAL(10,4)) END) AS sum_adjrw
            FROM ipd.ipd
            WHERE dateadm BETWEEN :start_date AND :end_date
        ");
        $stmt->execute([
            ':start_date' => $mStart,
            ':end_date' => $mEnd,
            ':end_calc' => $mEnd,
            ':end_limit' => $mEnd,
        ]);
        $hisRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $pd = (int)($hisRow['patient_days'] ?? 0);
        $monthBeds = $activeBeds;
        $monthDays = (int)(new DateTimeImmutable($mStart))->format('t');
        $fyPatientDays[] = $pd;
        $fyAdjrw[] = round((float)($hisRow['sum_adjrw'] ?? 0), 4);
        $fyOccRate[] = ($monthBeds > 0 && $monthDays > 0) ? round(($pd / ($monthBeds * $monthDays)) * 100, 2) : 0;
    }

    $payableDays = $selected['operating_expense'] > 0
        ? round(($selected['current_liabilities'] / $selected['operating_expense']) * $daysInMonth, 1)
        : 0;
    $apTargetDays = $selected['material_cost'] > 0 ? 180 : 90;
    $materialPerAdjrw = $sumAdjrw > 0 ? round($selected['material_cost'] / $sumAdjrw, 2) : 0;

    $monthlyIncomeAvg = count($incomeTrend) > 0 ? array_sum($incomeTrend) / count($incomeTrend) : 0;
    $collectionDaysProxy = $monthlyIncomeAvg > 0 ? round(($selected['maintenance_fund'] / $monthlyIncomeAvg) * 30, 1) : 0;

    $trialBalanceMonth = null;
    $trialBalanceRows = 0;
    $mappingAudit = null;
    $statementPdf = null;
    $trialBalanceExcel = null;
    $pdfAnalysis = null;
    try {
        $stmt = $pdo->prepare("
            SELECT month_year, row_count
            FROM finance_trial_balance_imports
            WHERE month_year <= :month
            ORDER BY month_year DESC
            LIMIT 1
        ");
        $stmt->execute([':month' => $month]);
        $trialBalance = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $trialBalanceMonth = $trialBalance['month_year'] ?? null;
        $trialBalanceRows = (int)($trialBalance['row_count'] ?? 0);
        if ($trialBalanceMonth) $mappingAudit = finance_mapping_audit($pdo, $trialBalanceMonth);
    } catch (Throwable $e) {
        $trialBalanceMonth = null;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT month_year, original_filename, relative_path, file_size, uploaded_at
            FROM finance_statement_documents
            WHERE month_year = :month
            AND document_type = 'monthly_statement'
            ORDER BY uploaded_at DESC
            LIMIT 1
        ");
        $stmt->execute([':month' => $month]);
        $statementPdf = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        $statementPdf = null;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT month_year, original_filename, relative_path, file_size, uploaded_at
            FROM finance_statement_documents
            WHERE month_year = :month
            AND document_type = 'trial_balance_excel'
            ORDER BY uploaded_at DESC
            LIMIT 1
        ");
        $stmt->execute([':month' => $month]);
        $trialBalanceExcel = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        $trialBalanceExcel = null;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT e.extract_status, e.metrics_json, e.reconcile_json, e.error_message, e.extracted_at
            FROM finance_statement_pdf_extracts e
            INNER JOIN finance_statement_documents d ON d.id = e.document_id
            WHERE d.month_year = :month
            AND d.document_type = 'monthly_statement'
            ORDER BY e.extracted_at DESC
            LIMIT 1
        ");
        $stmt->execute([':month' => $month]);
        $pdfAnalysis = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        $pdfAnalysis = null;
    }

    $hasIncomeTrend = array_sum(array_map('abs', $incomeTrend)) > 0;
    $hasExpenseTrend = array_sum(array_map('abs', $expenseTrend)) > 0;
    $hasMaterialTrend = array_sum(array_map('abs', $materialTrend)) > 0;
    $hasCashTrend = array_sum(array_map('abs', $cashTrend)) > 0;
    $hasPatientDays = array_sum($fyPatientDays) > 0;
    $hasAdjrw = array_sum($fyAdjrw) > 0;
    $hasApAging = $selected['current_liabilities'] > 0 && $payableDays > 0;
    $selectedIncomeReady = $selected['total_income'] > 0;
    $selectedExpenseReady = $selected['operating_expense'] > 0;
    $selectedInventoryReady = $selected['material_cost'] > 0;
    $selectedCashReady = $selected['cash_equivalents'] > 0;

    $diagnostics = [];
    $addDiagnostic = static function (string $level, string $area, string $message, string $action, string $source) use (&$diagnostics): void {
        $diagnostics[] = compact('level', 'area', 'message', 'action', 'source');
    };

    if (!$selectedIncomeReady) {
        $addDiagnostic('warning', 'โครงสร้างรายได้เดือนนี้', 'ไม่พบรายได้ค่ารักษา รายได้ยา หรือรายได้ Lab/X-Ray ของเดือนที่เลือก', 'บันทึกข้อมูลแท็บ จนท. การเงินและบัญชี หรือเชื่อม Mapping รายได้จากงบทดลอง', 'finance_monthly_data');
    }
    if (!$selectedExpenseReady) {
        $addDiagnostic('warning', 'รายได้ ค่าใช้จ่าย และกำไร/ขาดทุน', 'ไม่พบค่าใช้จ่ายดำเนินงานของเดือนที่เลือก', 'บันทึกค่าน้ำ ค่าไฟ ค่าตอบแทน และข้อมูลต้นทุนวัสดุ', 'finance_monthly_data');
    }
    if (!$selectedInventoryReady) {
        $addDiagnostic('warning', 'ต้นทุนวัสดุและ Inventory', 'ไม่พบมูลค่าคลังยา วัสดุการแพทย์ และวัสดุวิทยาศาสตร์ของเดือนที่เลือก', 'บันทึกข้อมูลแท็บ จนท. คลังและพัสดุ', 'finance_monthly_data');
    }
    if (!$selectedCashReady) {
        $addDiagnostic('warning', 'สภาพคล่องและ Cash', 'ไม่พบยอดเงินบำรุง/เงินสดคงเหลือของเดือนที่เลือก', 'บันทึกยอดเงินบำรุง หรือใช้ยอดเงินสดและเงินฝากจากงบทดลอง', 'finance_monthly_data / trial balance');
    }
    if (!$trialBalanceMonth) {
        $addDiagnostic('danger', 'งบทดลอง', 'ยังไม่พบงบทดลองที่นำเข้าสำหรับเดือนนี้หรือเดือนก่อนหน้า', 'นำเข้างบทดลอง Excel เพื่อใช้คำนวณ AR, AP, Cash, Assets และ Liabilities', 'finance_trial_balance_imports');
    } elseif ($trialBalanceMonth !== $month) {
        $addDiagnostic('info', 'งบทดลอง', "งบทดลองล่าสุดคือ {$trialBalanceMonth} ยังไม่ถึงเดือนรายงาน {$month}", 'นำเข้างบทดลองของเดือนรายงานเมื่อปิดงบแล้ว', 'finance_trial_balance_imports');
    }
    if (!$statementPdf) {
        $addDiagnostic('info', 'PDF สรุปงบการเงิน', 'ยังไม่มี PDF สรุปงบการเงินที่ผูกกับเดือนรายงานนี้', 'อัปโหลด PDF สรุปงบการเงินที่หน้า Finance Governance เพื่อใช้เป็นเอกสารอ้างอิงสำหรับผู้บริหาร', 'finance_statement_documents');
    }
    if (!$trialBalanceExcel) {
        $addDiagnostic('info', 'Excel งบทดลองฉบับเต็ม', 'ยังไม่มีไฟล์ Excel ต้นฉบับที่ผูกกับเดือนรายงานนี้ แม้ว่าข้อมูลงบทดลองอาจถูก import แล้ว', 'เก็บไฟล์ Excel งบทดลองฉบับเต็มที่หน้า Finance Governance เพื่อให้ตรวจสอบย้อนกลับกับ PDF สรุปได้', 'finance_statement_documents');
    }
    if ($mappingAudit && (float)$mappingAudit['readiness_percent'] < 100) {
        $addDiagnostic('warning', 'Account Mapping', 'ความพร้อม Mapping สำหรับสูตร CFO ' . number_format((float)$mappingAudit['readiness_percent'], 2) . '% ยังมี ' . count($mappingAudit['unmapped_codes']) . ' บัญชีที่ไม่ได้จัดหมวด', 'ตรวจทานรหัสบัญชีในหน้า Mapping / Governance', 'finance_account_mapping');
    }
    if (!$hasAdjrw) {
        $addDiagnostic('warning', 'ผลิตภาพ IPD: AdjRW', 'ไม่พบค่า Sum AdjRW ในช่วงปีงบประมาณที่เลือก', 'ตรวจการส่งออก DRG/AdjRW และวันที่จำหน่ายในระบบ HIS', 'HIS IPD');
    }
    if (!$hasPatientDays) {
        $addDiagnostic('danger', 'ผลิตภาพ IPD: Patient Days', 'ไม่พบวันนอนผู้ป่วยในสำหรับช่วงที่เลือก', 'ตรวจข้อมูล Admit/Discharge ในระบบ HIS', 'HIS IPD');
    }
    if (!$hasApAging) {
        $addDiagnostic('warning', 'AP Aging', 'ยังไม่มีข้อมูลเจ้าหนี้คงค้างและอายุเจ้าหนี้สำหรับสร้างกราฟ AP Aging', 'เชื่อม AP Aging จากระบบบัญชี หรือบันทึกยอดเจ้าหนี้แยกช่วงอายุ', 'Accounting / Trial Balance');
    }

    $chartReadiness = [
        ['chart' => 'แนวโน้มรายได้ ค่าใช้จ่าย และกำไร/ขาดทุน', 'ready' => $hasIncomeTrend && $hasExpenseTrend, 'missing' => !$hasIncomeTrend ? 'รายได้รายเดือน' : (!$hasExpenseTrend ? 'ค่าใช้จ่ายรายเดือน' : '')],
        ['chart' => 'โครงสร้างรายได้เดือนนี้', 'ready' => $selectedIncomeReady, 'missing' => $selectedIncomeReady ? '' : 'รายได้ค่ารักษา ยา และ Lab/X-Ray'],
        ['chart' => 'ต้นทุนวัสดุและสภาพคล่อง', 'ready' => $hasMaterialTrend && $hasCashTrend, 'missing' => !$hasMaterialTrend ? 'ต้นทุนวัสดุรายเดือน' : (!$hasCashTrend ? 'ยอดเงินสด/เงินบำรุง' : '')],
        ['chart' => 'ผลิตภาพ IPD', 'ready' => $hasPatientDays && $hasAdjrw, 'missing' => !$hasPatientDays ? 'Patient Days' : (!$hasAdjrw ? 'Sum AdjRW' : '')],
        ['chart' => 'AP Aging', 'ready' => $hasApAging, 'missing' => $hasApAging ? '' : 'ยอดเจ้าหนี้และช่วงอายุเจ้าหนี้'],
    ];

    $response = [
        'status' => 'success',
        'data_found' => $dataFound,
        'month' => [
            'value' => $month,
            'label' => finance_month_label($month),
            'start_date' => $monthStart,
            'end_date' => $monthEnd,
        ],
        'fiscal' => $fy,
        'kpi' => [
            'maintenance_fund' => $selected['maintenance_fund'],
            'treatment_income' => $selected['treatment_income'],
            'utilities_bill' => $selected['utilities_bill'],
            'receipt_count' => $selected['receipt_count'],
            'inv_drug_value' => $selected['inv_drug_value'],
            'inv_medical_supply' => $selected['inv_medical_supply'],
            'inv_turnover_rate' => $selected['inv_turnover_rate'],
            'satisfaction_rate' => $selected['satisfaction_rate'],
        ],
        'sections' => [
            'planfin' => [
                'total_income' => round($selected['total_income'], 2),
                'total_expense' => round($selected['operating_expense'], 2),
                'operating_profit' => round($selected['operating_profit'], 2),
                'expense_to_income' => $selected['total_income'] > 0 ? round(($selected['operating_expense'] / $selected['total_income']) * 100, 2) : 0,
                'variance_status' => 'ต้องบันทึกแผนรายไตรมาสเพิ่มเพื่อเทียบ ±5%',
            ],
            'rcm' => [
                'billing_total' => round($selected['total_income'], 2),
                'ar_proxy' => round($selected['maintenance_fund'], 2),
                'collection_days_proxy' => $collectionDaysProxy,
                'collection_target_days' => 60,
                'claim_lag_days' => null,
                'discharge_count' => $dischargeCount,
                'denial_rate' => null,
            ],
            'ap' => [
                'payable_balance_proxy' => round($selected['current_liabilities'], 2),
                'payable_days_proxy' => $payableDays,
                'payable_target_days' => $apTargetDays,
                'procurement_count' => $selected['procurement_count'],
                'aging' => [
                    ['label' => '0-30 วัน', 'value' => $selected['current_liabilities'] > 0 && $payableDays <= 30 ? round($selected['current_liabilities'], 2) : 0],
                    ['label' => '31-60 วัน', 'value' => $selected['current_liabilities'] > 0 && $payableDays > 30 && $payableDays <= 60 ? round($selected['current_liabilities'], 2) : 0],
                    ['label' => '61-90 วัน', 'value' => $selected['current_liabilities'] > 0 && $payableDays > 60 && $payableDays <= 90 ? round($selected['current_liabilities'], 2) : 0],
                    ['label' => '> 90 วัน', 'value' => $selected['current_liabilities'] > 0 && $payableDays > 90 ? round($selected['current_liabilities'], 2) : 0],
                ],
            ],
            'cost' => [
                'material_cost' => round($selected['material_cost'], 2),
                'patient_days' => $patientDays,
                'bed_count' => $activeBeds,
                'bed_occ_rate' => $bedOccRate,
                'sum_adjrw' => round($sumAdjrw, 4),
                'cmi' => $ipdCases > 0 ? round($sumAdjrw / $ipdCases, 4) : 0,
                'material_per_adjrw' => $materialPerAdjrw,
                'target_occ_rate' => 80,
            ],
            'liquidity' => [
                'current_assets' => round($selected['current_assets'], 2),
                'current_liabilities' => round($selected['current_liabilities'], 2),
                'cash_equivalents' => round($selected['cash_equivalents'], 2),
                'cash_ratio' => $selected['cash_ratio'],
                'nwc' => round($selected['nwc'], 2),
                'operating_profit' => round($selected['operating_profit'], 2),
            ],
        ],
        'income_chart' => [
            'treatment' => $selected['treatment_income'],
            'drug' => $selected['drug_income'],
            'lab' => $selected['lab_income'],
        ],
        'expense_chart' => [
            'water' => $selected['water_bill'],
            'electric' => $selected['electric_bill'],
            'compensation' => $selected['compensation'],
            'material' => $selected['material_cost'],
            'fund' => $selected['maintenance_fund'],
        ],
        'charts' => [
            'labels' => $labels,
            'income' => $incomeTrend,
            'expense' => $expenseTrend,
            'profit' => $profitTrend,
            'material' => $materialTrend,
            'cash' => $cashTrend,
            'patient_days' => $fyPatientDays,
            'sum_adjrw' => $fyAdjrw,
            'occ_rate' => $fyOccRate,
            'ap_aging' => [
                'labels' => ['0-30 วัน', '31-60 วัน', '61-90 วัน', '> 90 วัน'],
                'values' => [],
            ],
        ],
        'month_rows' => $monthRows,
        'data_diagnostics' => [
            'issues' => $diagnostics,
            'charts' => $chartReadiness,
            'trial_balance' => [
                'ready' => $trialBalanceMonth !== null,
                'month' => $trialBalanceMonth,
                'row_count' => $trialBalanceRows,
                'is_current_month' => $trialBalanceMonth === $month,
            ],
            'statement_pdf' => [
                'ready' => $statementPdf !== null,
                'month' => $statementPdf['month_year'] ?? null,
                'filename' => $statementPdf['original_filename'] ?? null,
                'relative_path' => $statementPdf['relative_path'] ?? null,
                'file_size' => isset($statementPdf['file_size']) ? (int)$statementPdf['file_size'] : 0,
                'uploaded_at' => $statementPdf['uploaded_at'] ?? null,
                'matches_trial_balance' => $statementPdf !== null && $trialBalanceMonth === $month,
            ],
            'trial_balance_excel' => [
                'ready' => $trialBalanceExcel !== null,
                'month' => $trialBalanceExcel['month_year'] ?? null,
                'filename' => $trialBalanceExcel['original_filename'] ?? null,
                'relative_path' => $trialBalanceExcel['relative_path'] ?? null,
                'file_size' => isset($trialBalanceExcel['file_size']) ? (int)$trialBalanceExcel['file_size'] : 0,
                'uploaded_at' => $trialBalanceExcel['uploaded_at'] ?? null,
                'matches_imported_trial_balance' => $trialBalanceExcel !== null && $trialBalanceMonth === $month,
            ],
            'pdf_analysis' => [
                'ready' => $pdfAnalysis !== null && ($pdfAnalysis['extract_status'] ?? '') === 'success',
                'status' => $pdfAnalysis['extract_status'] ?? null,
                'metric_count' => $pdfAnalysis ? count(json_decode((string)($pdfAnalysis['metrics_json'] ?? '[]'), true) ?: []) : 0,
                'reconcile' => $pdfAnalysis ? (json_decode((string)($pdfAnalysis['reconcile_json'] ?? '[]'), true) ?: []) : [],
                'error_message' => $pdfAnalysis['error_message'] ?? null,
                'extracted_at' => $pdfAnalysis['extracted_at'] ?? null,
            ],
            'mapping_audit' => $mappingAudit,
        ],
        'notes' => [
            'missing_direct_fields' => [
                'รายได้/ค่าใช้จ่ายตามแผน PlanFin รายไตรมาส',
                'AR แยกรายสิทธิแบบ aging จริง',
                'Claim lag time และ denial rate',
                'AP aging แยกเจ้าหนี้จากระบบบัญชีจริง',
            ],
        ],
    ];

    $stmt = $pdo->prepare("SELECT bucket, amount FROM finance_aging WHERE month_year = :month AND aging_type = 'AP'");
    $stmt->execute([':month' => $month]);
    $actualAging = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'amount', 'bucket');
    if ($actualAging) {
        $response['sections']['ap']['aging'] = [
            ['label' => '0-30 วัน', 'value' => (float)($actualAging['0-30'] ?? 0)],
            ['label' => '31-60 วัน', 'value' => (float)($actualAging['31-60'] ?? 0)],
            ['label' => '61-90 วัน', 'value' => (float)($actualAging['61-90'] ?? 0)],
            ['label' => '> 90 วัน', 'value' => (float)($actualAging['OVER_90'] ?? 0)],
        ];
    }
    $stmt = $pdo->prepare('SELECT * FROM finance_planfin WHERE month_year = :month');
    $stmt->execute([':month' => $month]);
    $response['sections']['planfin']['targets'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    $stmt = $pdo->prepare('SELECT * FROM finance_claim_quality WHERE month_year = :month');
    $stmt->execute([':month' => $month]);
    if ($claim = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $response['sections']['rcm']['claim_lag_days'] = (float)$claim['claim_lag_days'];
        $response['sections']['rcm']['denial_rate'] = (float)$claim['denial_rate'];
    }
    $response['charts']['ap_aging']['values'] = array_map(fn($row) => $row['value'], $response['sections']['ap']['aging']);

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
