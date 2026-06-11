<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

function cfo_month(string $value): string
{
    $date = DateTimeImmutable::createFromFormat('Y-m', $value);
    return $date && $date->format('Y-m') === $value ? $value : date('Y-m');
}

function cfo_period_mode(string $value): string
{
    return in_array($value, ['month', 'quarter', 'fiscal'], true) ? $value : 'fiscal';
}

function cfo_number(array $row, string $key): float
{
    return (float)($row[$key] ?? 0);
}

function cfo_ratio_days(float $balance, float $cumulativeMovement, int $days): ?float
{
    if ($balance <= 0 || $cumulativeMovement <= 0 || $days <= 0) {
        return null;
    }
    return round($balance / ($cumulativeMovement / $days), 1);
}

function cfo_status(?float $value, float $target, string $mode = 'max'): string
{
    if ($value === null) {
        return 'pending';
    }
    return $mode === 'min'
        ? ($value >= $target ? 'good' : 'bad')
        : ($value <= $target ? 'good' : 'bad');
}

function cfo_utf8($value): string
{
    $text = (string)($value ?? '');
    if ($text === '' || preg_match('//u', $text)) {
        return trim($text);
    }
    $converted = @iconv('TIS-620', 'UTF-8//IGNORE', $text);
    return trim($converted !== false ? $converted : $text);
}

function cfo_is_icu(string $wardName, string $wardCode): bool
{
    $name = cfo_utf8($wardName);
    return stripos($name, 'ICU') !== false
        || stripos($wardCode, 'ICU') !== false
        || strpos($name, 'ไอซียู') !== false
        || strpos($name, 'กึ่งวิกฤต') !== false;
}

function cfo_metric(string $code, string $name, ?float $value, ?float $target, string $mode, string $unit, string $formula, string $source): array
{
    return [
        'code' => $code,
        'name' => $name,
        'value' => $value === null ? null : round($value, 2),
        'target' => $target,
        'mode' => $mode,
        'unit' => $unit,
        'formula' => $formula,
        'source' => $source,
        'status' => $target === null ? 'pending' : cfo_status($value, $target, $mode),
    ];
}

function cfo_contains(string $text, array $needles): bool
{
    foreach ($needles as $needle) {
        if ($needle !== '' && strpos($text, $needle) !== false) {
            return true;
        }
    }
    return false;
}

try {
    require_once __DIR__ . '/../includes/auth.php';
    require_login();
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../config/his_database.php';
    require_once __DIR__ . '/../includes/finance_governance.php';
    finance_governance_schema($pdo);

    $month = cfo_month((string)($_GET['month'] ?? date('Y-m')));
    $periodMode = cfo_period_mode((string)($_GET['period_mode'] ?? 'fiscal'));
    $selectedDate = new DateTimeImmutable($month . '-01');
    $monthStart = $selectedDate->format('Y-m-01');
    $monthEnd = $selectedDate->format('Y-m-t');
    $today = new DateTimeImmutable(date('Y-m-d'));
    $reportEndDate = new DateTimeImmutable($monthEnd);
    if ($reportEndDate > $today) {
        $reportEndDate = $today;
    }
    $reportEnd = $reportEndDate->format('Y-m-d');
    $year = (int)$selectedDate->format('Y');
    $monthNumber = (int)$selectedDate->format('n');
    $fiscalStart = ($monthNumber >= 10 ? $year : $year - 1) . '-10-01';
    $fiscalStartMonth = substr($fiscalStart, 0, 7);
    $fiscalYear = ($monthNumber >= 10 ? $year + 1 : $year) + 543;
    $quarterStartMonth = match (true) {
        $monthNumber >= 10 => 10,
        $monthNumber >= 7 => 7,
        $monthNumber >= 4 => 4,
        default => 1,
    };
    $quarterStartYear = $quarterStartMonth === 10 && $monthNumber < 10 ? $year - 1 : $year;
    $quarterStart = sprintf('%04d-%02d-01', $quarterStartYear, $quarterStartMonth);
    $analysisStart = match ($periodMode) {
        'month' => $monthStart,
        'quarter' => $quarterStart,
        default => $fiscalStart,
    };
    $analysisStartMonth = substr($analysisStart, 0, 7);
    $daysElapsed = max(1, (int)((new DateTimeImmutable($analysisStart))->diff($reportEndDate)->days + 1));
    $periodLabel = match ($periodMode) {
        'month' => 'รายเดือน',
        'quarter' => 'สะสมรายไตรมาส',
        default => 'สะสมปีงบประมาณ',
    };

    $stmt = $pdo->prepare("
        SELECT *
        FROM finance_monthly_data
        WHERE month_year BETWEEN :start_month AND :end_month
        ORDER BY month_year
    ");
    $stmt->execute([':start_month' => $analysisStartMonth, ':end_month' => $month]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT *
        FROM finance_monthly_auto
        WHERE month_year BETWEEN :start_month AND :end_month
        ORDER BY month_year
    ");
    $stmt->execute([':start_month' => $analysisStartMonth, ':end_month' => $month]);
    $autoRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $rowsByMonth = [];
    foreach ($rows as $row) {
        $rowMonth = (string)($row['month_year'] ?? '');
        if ($rowMonth !== '') {
            $rowsByMonth[$rowMonth] = $row;
        }
    }
    $autoRowsByMonth = [];
    foreach ($autoRows as $row) {
        $rowMonth = (string)($row['month_year'] ?? '');
        if ($rowMonth !== '') {
            $autoRowsByMonth[$rowMonth] = $row;
        }
    }

    $periodMonths = [];
    $monthCursor = new DateTimeImmutable($analysisStartMonth . '-01');
    $monthLimit = new DateTimeImmutable($month . '-01');
    while ($monthCursor <= $monthLimit) {
        $periodMonths[] = $monthCursor->format('Y-m');
        $monthCursor = $monthCursor->modify('+1 month');
    }

    $mergeMonthlyRow = static function (array $manual, array $auto, string $rowMonth): array {
        $row = $manual;
        $row['month_year'] = $rowMonth;
        $fieldMap = [
            'treatment_income' => 'treatment_income',
            'drug_income' => 'drug_income',
            'lab_income' => 'lab_income',
            'water_bill' => 'water_bill',
            'electric_bill' => 'electric_bill',
            'compensation' => 'compensation',
            'maintenance_fund' => 'maintenance_fund',
            'inv_drug_value' => 'inv_drug_value',
            'inv_medical_supply' => 'inv_medical_supply',
            'inv_science_material' => 'inv_science_material',
        ];
        foreach ($fieldMap as $manualField => $autoField) {
            if (cfo_number($row, $manualField) <= 0 && array_key_exists($autoField, $auto)) {
                $row[$manualField] = $auto[$autoField];
            }
        }
        if (cfo_number($row, 'maintenance_fund') <= 0 && array_key_exists('cash_balance', $auto)) {
            $row['maintenance_fund'] = $auto['cash_balance'];
        }
        return $row;
    };

    $selected = [];
    $cumulativePurchases = 0.0;
    $cumulativeUsage = 0.0;
    $cumulativeIncome = 0.0;
    $cumulativeTreatment = 0.0;
    $cumulativeExpense = 0.0;
    $cumulativeCompensation = 0.0;
    $cumulativeDrug = 0.0;
    $cumulativeLab = 0.0;
    $cumulativeGeneralMaterial = 0.0;
    $trendLabels = [];
    $trendIncome = [];
    $trendExpense = [];
    $trendProfit = [];
    $financeFirstMonth = null;
    $financeLastMonth = null;
    $financeMeaningfulFirstMonth = null;
    $financeMeaningfulLastMonth = null;
    $chartMonthsWithData = [];
    $chartMissingMonths = [];

    foreach ($periodMonths as $rowMonth) {
        $manualRow = $rowsByMonth[$rowMonth] ?? [];
        $autoRow = $autoRowsByMonth[$rowMonth] ?? [];
        $row = $mergeMonthlyRow($manualRow, $autoRow, $rowMonth);
        if ($rowMonth !== '') {
            $financeFirstMonth ??= $rowMonth;
            $financeLastMonth = $rowMonth;
        }
        $inventory = cfo_number($row, 'inv_drug_value')
            + cfo_number($row, 'inv_medical_supply')
            + cfo_number($row, 'inv_science_material');
        $cumulativePurchases += $inventory;
        $cumulativeUsage += $inventory;
        $manualIncome = cfo_number($manualRow, 'treatment_income') + cfo_number($manualRow, 'drug_income') + cfo_number($manualRow, 'lab_income');
        $autoIncome = cfo_number($autoRow, 'total_revenue');
        $income = $manualIncome > 0
            ? $manualIncome
            : ($autoIncome > 0 ? $autoIncome : cfo_number($row, 'treatment_income') + cfo_number($row, 'drug_income') + cfo_number($row, 'lab_income'));
        $cumulativeTreatment += cfo_number($row, 'treatment_income');
        $compensation = cfo_number($row, 'compensation');
        $drugMaterial = cfo_number($row, 'inv_drug_value');
        $labMaterial = cfo_number($row, 'inv_science_material');
        $generalMaterial = cfo_number($row, 'inv_medical_supply');
        $manualExpense = cfo_number($manualRow, 'water_bill')
            + cfo_number($manualRow, 'electric_bill')
            + cfo_number($manualRow, 'compensation')
            + cfo_number($manualRow, 'inv_drug_value')
            + cfo_number($manualRow, 'inv_medical_supply')
            + cfo_number($manualRow, 'inv_science_material');
        $autoExpense = cfo_number($autoRow, 'total_expense');
        $expense = $manualExpense > 0
            ? $manualExpense
            : ($autoExpense > 0 ? $autoExpense : cfo_number($row, 'water_bill') + cfo_number($row, 'electric_bill') + $compensation + $inventory);
        $meaningfulTotal = $income + $expense + cfo_number($row, 'maintenance_fund')
            + cfo_number($row, 'receipt_count') + cfo_number($row, 'procurement_count')
            + cfo_number($row, 'active_beds') + cfo_number($row, 'active_staff');
        if ($meaningfulTotal > 0 && $rowMonth !== '') {
            $financeMeaningfulFirstMonth ??= $rowMonth;
            $financeMeaningfulLastMonth = $rowMonth;
        }
        $cumulativeIncome += $income;
        $cumulativeExpense += $expense;
        $cumulativeCompensation += $compensation;
        $cumulativeDrug += $drugMaterial;
        $cumulativeLab += $labMaterial;
        $cumulativeGeneralMaterial += $generalMaterial;
        $trendLabels[] = (string)($row['month_year'] ?? '');
        $trendIncome[] = round($income, 2);
        $trendExpense[] = round($expense, 2);
        $trendProfit[] = round($income - $expense, 2);
        if ($income > 0 || $expense > 0) {
            $chartMonthsWithData[] = $rowMonth;
        } else {
            $chartMissingMonths[] = $rowMonth;
        }
        if ($rowMonth === $month) {
            $selected = $row;
        }
    }

    $chartCompleteness = [
        'expected_months' => count($periodMonths),
        'months_with_data' => count($chartMonthsWithData),
        'missing_months' => $chartMissingMonths,
        'percent' => count($periodMonths) > 0 ? round((count($chartMonthsWithData) / count($periodMonths)) * 100, 2) : 0,
    ];

    $supplemental = [
        'planfin' => null,
        'aging_rows' => 0,
        'claim_quality' => null,
        'cost_center_rows' => 0,
        'asset_rows' => 0,
        'inventory_actual_issues' => 0.0,
    ];
    $stmt = $pdo->prepare('SELECT SUM(actual_issues) FROM finance_inventory_usage WHERE month_year BETWEEN :start_month AND :end_month');
    $stmt->execute([':start_month' => $analysisStartMonth, ':end_month' => $month]);
    $supplemental['inventory_actual_issues'] = (float)$stmt->fetchColumn();
    if ($supplemental['inventory_actual_issues'] > 0) $cumulativeUsage = $supplemental['inventory_actual_issues'];
    $stmt = $pdo->prepare('SELECT * FROM finance_planfin WHERE month_year = :month');
    $stmt->execute([':month' => $month]);
    $supplemental['planfin'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM finance_aging WHERE month_year = :month');
    $stmt->execute([':month' => $month]);
    $supplemental['aging_rows'] = (int)$stmt->fetchColumn();
    $stmt = $pdo->prepare('SELECT * FROM finance_claim_quality WHERE month_year = :month');
    $stmt->execute([':month' => $month]);
    $supplemental['claim_quality'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM finance_cost_center WHERE month_year BETWEEN :start_month AND :end_month');
    $stmt->execute([':start_month' => $analysisStartMonth, ':end_month' => $month]);
    $supplemental['cost_center_rows'] = (int)$stmt->fetchColumn();
    $supplemental['asset_rows'] = (int)$pdo->query("SELECT COUNT(*) FROM finance_asset_register WHERE status = 'active'")->fetchColumn();

    $inventoryEnding = cfo_number($selected, 'inv_drug_value')
        + cfo_number($selected, 'inv_medical_supply')
        + cfo_number($selected, 'inv_science_material');
    $payableBalance = cfo_number($selected, 'water_bill')
        + cfo_number($selected, 'electric_bill')
        + cfo_number($selected, 'compensation');

    $apDays = cfo_ratio_days($payableBalance, $cumulativePurchases, $daysElapsed);
    $inventoryDays = cfo_ratio_days($inventoryEnding, $cumulativeUsage, $daysElapsed);

    $trialRows = [];
    $trialBalanceReady = false;
    $trialBalanceMonth = null;
    try {
        $stmt = $pdo->prepare("
            SELECT r.account_code, r.account_name, r.month_debit, r.month_credit, r.net_debit, r.net_credit,
                   m.is_cash, m.is_ar_uc, m.is_ar_csmbs, m.is_ar_sss, m.is_ar_other, m.is_ap, m.is_inventory,
                   m.is_current_asset, m.is_fixed_asset, m.is_current_liability, m.is_longterm_liability,
                   m.is_equity_fund, m.is_revenue, m.is_revenue_operating, m.is_revenue_non_operating,
                   m.is_depreciation, m.is_finance_cost, m.is_project_grant
            FROM finance_trial_balance_rows r
            INNER JOIN finance_trial_balance_imports i ON i.id = r.import_id
            LEFT JOIN finance_account_mapping m ON m.account_code = r.account_code
            WHERE i.month_year = (
                SELECT MAX(month_year)
                FROM finance_trial_balance_imports
                WHERE month_year <= :month
            )
        ");
        $stmt->execute([':month' => $month]);
        $trialRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $trialBalanceReady = count($trialRows) > 0;
        if ($trialBalanceReady) {
            $stmt = $pdo->prepare('SELECT MAX(month_year) FROM finance_trial_balance_imports WHERE month_year <= :month');
            $stmt->execute([':month' => $month]);
            $trialBalanceMonth = $stmt->fetchColumn() ?: null;

            $stmt = $pdo->prepare("
                SELECT r.account_code,
                       SUM(r.month_debit) AS period_debit,
                       SUM(r.month_credit) AS period_credit
                FROM finance_trial_balance_rows r
                INNER JOIN finance_trial_balance_imports i ON i.id = r.import_id
                WHERE i.month_year BETWEEN :start_month AND :end_month
                GROUP BY r.account_code
            ");
            $stmt->execute([':start_month' => $analysisStartMonth, ':end_month' => $month]);
            $periodMovements = [];
            while ($movement = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $periodMovements[(string)$movement['account_code']] = $movement;
            }
            foreach ($trialRows as &$trialRow) {
                $movement = $periodMovements[(string)($trialRow['account_code'] ?? '')] ?? null;
                if ($movement) {
                    $trialRow['month_debit'] = $movement['period_debit'];
                    $trialRow['month_credit'] = $movement['period_credit'];
                }
            }
            unset($trialRow);
        }
    } catch (Throwable $e) {
        $trialRows = [];
    }

    $tb = [
        'cash' => 0.0, 'ar_uc' => 0.0, 'ar_uc_revenue' => 0.0, 'ar_csmbs' => 0.0, 'ar_csmbs_revenue' => 0.0,
        'ar_sss' => 0.0, 'ar_other' => 0.0, 'fixed_assets' => 0.0,
        'longterm_liabilities' => 0.0, 'equity_fund' => 0.0, 'finance_cost' => 0.0, 'project_grant' => 0.0,
        'payable' => 0.0, 'inventory' => 0.0, 'inventory_usage' => 0.0, 'current_assets' => 0.0,
        'current_liabilities' => 0.0, 'total_assets' => 0.0, 'depreciation' => 0.0,
        'income' => 0.0, 'expense' => 0.0,
    ];
    foreach ($trialRows as $trialRow) {
        $code = trim((string)($trialRow['account_code'] ?? ''));
        $name = cfo_utf8($trialRow['account_name'] ?? '');
        $monthDebit = (float)($trialRow['month_debit'] ?? 0);
        $netDebit = max((float)($trialRow['net_debit'] ?? 0) - (float)($trialRow['net_credit'] ?? 0), 0);
        $netCredit = max((float)($trialRow['net_credit'] ?? 0) - (float)($trialRow['net_debit'] ?? 0), 0);

        $hasMappedCategory = array_sum(array_map(
            static fn(string $key): int => (int)($trialRow[$key] ?? 0),
            [
                'is_cash', 'is_ar_uc', 'is_ar_csmbs', 'is_ar_sss', 'is_ar_other', 'is_ap', 'is_inventory',
                'is_current_asset', 'is_fixed_asset', 'is_current_liability', 'is_longterm_liability',
                'is_equity_fund', 'is_revenue', 'is_revenue_operating', 'is_revenue_non_operating',
                'is_depreciation', 'is_finance_cost', 'is_project_grant',
            ]
        )) > 0;
        if ($hasMappedCategory) {
            if ((int)$trialRow['is_cash']) $tb['cash'] += $netDebit;
            if ((int)$trialRow['is_ar_uc']) {
                $tb['ar_uc'] += $netDebit;
                $tb['ar_uc_revenue'] += $monthDebit;
            }
            if ((int)$trialRow['is_ar_csmbs']) {
                $tb['ar_csmbs'] += $netDebit;
                $tb['ar_csmbs_revenue'] += $monthDebit;
            }
            if ((int)$trialRow['is_ar_sss']) $tb['ar_sss'] += $netDebit;
            if ((int)$trialRow['is_ar_other']) $tb['ar_other'] += $netDebit;
            if ((int)$trialRow['is_ap']) $tb['payable'] += $netCredit;
            if ((int)$trialRow['is_inventory']) {
                $tb['inventory'] += $netDebit;
                $tb['inventory_usage'] += (float)($trialRow['month_credit'] ?? 0);
            }
            if ((int)$trialRow['is_current_asset']) $tb['current_assets'] += $netDebit;
            if ((int)$trialRow['is_fixed_asset']) $tb['fixed_assets'] += $netDebit;
            if ((int)$trialRow['is_current_liability']) $tb['current_liabilities'] += $netCredit;
            if ((int)$trialRow['is_longterm_liability']) $tb['longterm_liabilities'] += $netCredit;
            if ((int)$trialRow['is_equity_fund']) $tb['equity_fund'] += $netCredit;
            if ((int)$trialRow['is_revenue'] || (int)$trialRow['is_revenue_operating'] || (int)$trialRow['is_revenue_non_operating']) $tb['income'] += max((float)($trialRow['month_credit'] ?? 0) - $monthDebit, 0);
            if ((int)$trialRow['is_depreciation']) $tb['depreciation'] += $monthDebit;
            if ((int)$trialRow['is_finance_cost']) $tb['finance_cost'] += max($monthDebit - (float)($trialRow['month_credit'] ?? 0), 0);
            if ((int)$trialRow['is_project_grant']) $tb['project_grant'] += max((float)($trialRow['month_credit'] ?? 0) - $monthDebit, 0);
            if (str_starts_with($code, '1') || (int)$trialRow['is_current_asset'] || (int)$trialRow['is_fixed_asset']) $tb['total_assets'] += $netDebit;
            if (str_starts_with($code, '5') || (int)$trialRow['is_depreciation'] || (int)$trialRow['is_finance_cost']) $tb['expense'] += max($monthDebit - (float)($trialRow['month_credit'] ?? 0), 0);
            continue;
        }

        if (cfo_contains($name, ['เงินสด', 'เงินฝากธนาคาร', 'เงินฝากคลัง'])) $tb['cash'] += $netDebit;
        if (cfo_contains($name, ['ลูกหนี้ค่ารักษา UC'])) {
            $tb['ar_uc'] += $netDebit;
            $tb['ar_uc_revenue'] += $monthDebit;
        }
        if (cfo_contains($name, ['กรมบัญชีกลาง'])) {
            $tb['ar_csmbs'] += $netDebit;
            $tb['ar_csmbs_revenue'] += $monthDebit;
        }
        if (cfo_contains($name, ['เจ้าหนี้'])) $tb['payable'] += $netCredit;
        if (str_starts_with($code, '1105')) {
            $tb['inventory'] += $netDebit;
            $tb['inventory_usage'] += (float)($trialRow['month_credit'] ?? 0);
        }
        if (str_starts_with($code, '11')) $tb['current_assets'] += $netDebit;
        if (str_starts_with($code, '21')) $tb['current_liabilities'] += $netCredit;
        if (str_starts_with($code, '1')) $tb['total_assets'] += $netDebit;
        if (cfo_contains($name, ['ค่าเสื่อมราคา']) && !cfo_contains($name, ['ค่าเสื่อมราคาสะสม'])) $tb['depreciation'] += $monthDebit;
        if (str_starts_with($code, '4')) $tb['income'] += max((float)($trialRow['month_credit'] ?? 0) - $monthDebit, 0);
        if (str_starts_with($code, '5')) $tb['expense'] += max($monthDebit - (float)($trialRow['month_credit'] ?? 0), 0);
    }

    if ($trialBalanceReady) {
        $payableBalance = $tb['payable'] > 0 ? $tb['payable'] : $payableBalance;
        $inventoryEnding = $tb['inventory'] > 0 ? $tb['inventory'] : $inventoryEnding;
        $apDays = cfo_ratio_days($payableBalance, max($cumulativePurchases, $tb['inventory_usage']), $daysElapsed);
        $inventoryDays = cfo_ratio_days($inventoryEnding, max($cumulativeUsage, $tb['inventory_usage']), $daysElapsed);
    }
    $trialDays = $daysElapsed;
    $arUcDays = cfo_ratio_days($tb['ar_uc'], $tb['ar_uc_revenue'], $trialDays);
    $arCsmbsDays = cfo_ratio_days($tb['ar_csmbs'], $tb['ar_csmbs_revenue'], $trialDays);

    $stmt = $his->prepare("
        SELECT
            COALESCE(r.roomname, i.now_ward, '') AS ward_name,
            COALESCE(i.now_ward, '') AS ward_code,
            SUM(
                CASE
                    WHEN i.dateadm IS NULL OR i.dateadm = '0000-00-00' THEN 0
                    WHEN i.datedsc IS NULL OR i.datedsc = '0000-00-00' THEN GREATEST(DATEDIFF(:end_calc, i.dateadm), 1)
                    ELSE GREATEST(DATEDIFF(LEAST(i.datedsc, :end_limit), i.dateadm), 1)
                END
            ) AS patient_days
        FROM ipd.ipd i
        LEFT JOIN (
            SELECT roomcode, MAX(roomname) AS roomname
            FROM hos.roomno
            GROUP BY roomcode
        ) r ON i.now_ward = r.roomcode
        WHERE i.dateadm BETWEEN :start_date AND :end_date
        GROUP BY i.now_ward, r.roomname
    ");
    $stmt->execute([
        ':start_date' => $analysisStart,
        ':end_date' => $reportEnd,
        ':end_calc' => $reportEnd,
        ':end_limit' => $reportEnd,
    ]);
    $generalPatientDays = 0;
    $icuPatientDays = 0;
    while ($bedRow = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (cfo_is_icu((string)($bedRow['ward_name'] ?? ''), (string)($bedRow['ward_code'] ?? ''))) {
            $icuPatientDays += (int)($bedRow['patient_days'] ?? 0);
        } else {
            $generalPatientDays += (int)($bedRow['patient_days'] ?? 0);
        }
    }
    $daysInPeriod = $daysElapsed;
    $generalBeds = 93;
    $icuBeds = 9;
    $totalBeds = 102;
    $generalOcc = round(($generalPatientDays / ($generalBeds * $daysInPeriod)) * 100, 2);
    $icuOcc = round(($icuPatientDays / ($icuBeds * $daysInPeriod)) * 100, 2);
    $totalOcc = round((($generalPatientDays + $icuPatientDays) / ($totalBeds * $daysInPeriod)) * 100, 2);

    $stmt = $his->query("
        SELECT
            COALESCE(r.roomname, i.now_ward, '') AS ward_name,
            COALESCE(i.now_ward, '') AS ward_code,
            COUNT(DISTINCT i.an) AS active_count
        FROM ipd.ipd i
        LEFT JOIN (
            SELECT roomcode, MAX(roomname) AS roomname
            FROM hos.roomno
            GROUP BY roomcode
        ) r ON i.now_ward = r.roomcode
        WHERE i.datedsc IS NULL OR i.datedsc = '0000-00-00'
        GROUP BY i.now_ward, r.roomname
    ");
    $generalActive = 0;
    $icuActive = 0;
    while ($activeRow = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (cfo_is_icu((string)($activeRow['ward_name'] ?? ''), (string)($activeRow['ward_code'] ?? ''))) {
            $icuActive += (int)($activeRow['active_count'] ?? 0);
        } else {
            $generalActive += (int)($activeRow['active_count'] ?? 0);
        }
    }

    $stmt = $his->prepare("SELECT COUNT(*) FROM opd.opd WHERE regdate BETWEEN :start_date AND :end_date");
    $stmt->execute([':start_date' => $analysisStart, ':end_date' => $reportEnd]);
    $opdVisits = (int)$stmt->fetchColumn();

    $stmt = $his->prepare("
        SELECT SUM(CASE WHEN adjrw IS NULL OR adjrw = '' THEN 0 ELSE CAST(adjrw AS DECIMAL(12,4)) END)
        FROM ipd.ipd
        WHERE datedsc IS NOT NULL AND datedsc != '0000-00-00' AND datedsc BETWEEN :start_date AND :end_date
    ");
    $stmt->execute([':start_date' => $analysisStart, ':end_date' => $reportEnd]);
    $sumAdjrw = (float)$stmt->fetchColumn();

    $lastFiscalStart = (new DateTimeImmutable($analysisStart))->modify('-1 year')->format('Y-m-d');
    $lastReportEnd = $reportEndDate->modify('-1 year')->format('Y-m-d');
    $stmt->execute([':start_date' => $lastFiscalStart, ':end_date' => $lastReportEnd]);
    $lastSumAdjrw = (float)$stmt->fetchColumn();
    $adjrwGrowth = $lastSumAdjrw > 0 ? (($sumAdjrw - $lastSumAdjrw) / $lastSumAdjrw) * 100 : null;

    $totalCost = $cumulativeExpense;
    $netIncome = $cumulativeIncome - $cumulativeExpense;
    $opIncomeShare = $cumulativeIncome > 0 ? $cumulativeTreatment / $cumulativeIncome : 0;
    $opAllocatedCost = $totalCost * $opIncomeShare;
    $ipAllocatedCost = $totalCost - $opAllocatedCost;
    $unitCostOp = $opdVisits > 0 && $opIncomeShare > 0 ? $opAllocatedCost / $opdVisits : null;
    $unitCostIp = $sumAdjrw > 0 ? $ipAllocatedCost / $sumAdjrw : null;
    $lcRatio = $totalCost > 0 ? ($cumulativeCompensation / $totalCost) * 100 : null;
    $mcDrugRatio = $totalCost > 0 ? ($cumulativeDrug / $totalCost) * 100 : null;
    $mcLabRatio = $totalCost > 0 ? ($cumulativeLab / $totalCost) * 100 : null;
    $mcGeneralRatio = $totalCost > 0 ? ($cumulativeGeneralMaterial / $totalCost) * 100 : null;
    $outcomeIncome = $cumulativeIncome > 0 ? $cumulativeIncome : $tb['income'];
    $outcomeExpense = $cumulativeIncome > 0 ? $cumulativeExpense : $tb['expense'];
    $outcomeNetIncome = $outcomeIncome > 0 ? $outcomeIncome - $outcomeExpense : null;
    $operatingMargin = $outcomeNetIncome !== null ? ($outcomeNetIncome / $outcomeIncome) * 100 : null;
    $ebitda = $outcomeNetIncome !== null ? $outcomeNetIncome + $tb['depreciation'] : null;
    $roa = $tb['total_assets'] > 0 && $outcomeNetIncome !== null ? ($outcomeNetIncome / $tb['total_assets']) * 100 : null;
    $currentAssets = $tb['current_assets'] > 0 ? $tb['current_assets'] : cfo_number($selected, 'maintenance_fund') + $inventoryEnding;
    $currentLiabilities = $tb['current_liabilities'] > 0 ? $tb['current_liabilities'] : $payableBalance;
    $cash = $tb['cash'] > 0 ? $tb['cash'] : cfo_number($selected, 'maintenance_fund');
    $nwc = $currentAssets - $currentLiabilities;
    $cashRatio = $currentLiabilities > 0 ? $cash / $currentLiabilities : null;
    $outcomeSource = $cumulativeIncome > 0 ? 'Finance' : 'Trial Balance';
    if ($cumulativeIncome <= 0 && $trialBalanceMonth && ($trendIndex = array_search($trialBalanceMonth, $trendLabels, true)) !== false) {
        $trendIncome[$trendIndex] = round($tb['income'], 2);
        $trendExpense[$trendIndex] = round($tb['expense'], 2);
        $trendProfit[$trendIndex] = round($tb['income'] - $tb['expense'], 2);
    }

    $managementMetrics = [
        cfo_metric('1.3.1.1', 'Unit Cost OP', $unitCostOp, null, 'max', 'บาท/Visit', 'ต้นทุน OP ที่จัดสรร ÷ จำนวน Visit OP สะสม', 'Finance + HIS OPD'),
        cfo_metric('1.3.1.2', 'Unit Cost IP', $unitCostIp, null, 'max', 'บาท/AdjRW', 'ต้นทุน IP ที่จัดสรร ÷ Sum AdjRW', 'Finance + HIS IPD'),
        cfo_metric('1.3.1.3', 'สัดส่วน LC', $lcRatio, null, 'max', '%', 'ค่าตอบแทนบุคลากร ÷ Total Cost × 100', 'Finance'),
        cfo_metric('1.3.1.4', 'สัดส่วน MC ยา', $mcDrugRatio, null, 'max', '%', 'ต้นทุนยา ÷ Total Cost × 100', 'Finance'),
        cfo_metric('1.3.1.5', 'สัดส่วน MC วัสดุวิทย์', $mcLabRatio, null, 'max', '%', 'ต้นทุนวัสดุวิทยาศาสตร์ ÷ Total Cost × 100', 'Finance'),
        cfo_metric('1.3.1.6', 'สัดส่วน MC ทั่วไป', $mcGeneralRatio, null, 'max', '%', 'ต้นทุนวัสดุทั่วไป ÷ Total Cost × 100', 'Finance'),
        cfo_metric('1.3.3.1', 'Bed Occupancy', $totalOcc, 80, 'min', '%', 'Patient Days ÷ (102 เตียง × วันในงวด) × 100', 'HIS IPD'),
        cfo_metric('1.3.3.2', 'Sum AdjRW Growth', $adjrwGrowth, 5, 'min', '%', '(AdjRW ปีนี้ - ปีก่อน) ÷ ปีก่อน × 100', 'HIS IPD'),
    ];

    $outcomes = [
        cfo_metric('2.1.1', 'Operating Margin Ratio', $operatingMargin, 0, 'min', '%', '(รายได้ดำเนินงาน - ค่าใช้จ่ายดำเนินงาน) ÷ รายได้ × 100', $outcomeSource),
        cfo_metric('2.1.2', 'EBITDA', $ebitda, 0, 'min', 'บาท', 'กำไรสุทธิ + ค่าเสื่อมราคา', $outcomeSource === 'Trial Balance' ? 'Trial Balance' : 'Finance + Trial Balance'),
        cfo_metric('2.1.3', 'Return on Asset (ROA)', $roa, 0, 'min', '%', 'กำไรสุทธิ ÷ สินทรัพย์รวม × 100', $outcomeSource === 'Trial Balance' ? 'Trial Balance' : 'Finance + Trial Balance'),
        cfo_metric('2.2.1', 'Net Working Capital', $nwc, 0, 'min', 'บาท', 'สินทรัพย์หมุนเวียน - หนี้สินหมุนเวียน', 'Trial Balance'),
        cfo_metric('2.2.2', 'Cash Ratio', $cashRatio, 0.8, 'min', 'เท่า', 'เงินสดและเงินฝาก ÷ หนี้สินหมุนเวียน', 'Trial Balance'),
    ];

    $monthDistance = static function (?string $from, string $to): ?int {
        if (!$from) return null;
        $fromDate = DateTimeImmutable::createFromFormat('Y-m-d', $from . '-01');
        $toDate = DateTimeImmutable::createFromFormat('Y-m-d', $to . '-01');
        if (!$fromDate || !$toDate) return null;
        return (((int)$toDate->format('Y') - (int)$fromDate->format('Y')) * 12)
            + ((int)$toDate->format('n') - (int)$fromDate->format('n'));
    };
    $trialLag = $monthDistance($trialBalanceMonth, $month);
    $financeLag = $monthDistance($financeMeaningfulLastMonth, $month);
    $periodStatus = static function (?int $lag): string {
        if ($lag === null) return 'missing';
        if ($lag <= 0) return 'current';
        if ($lag === 1) return 'watch';
        return 'stale';
    };
    $analysisPeriods = [
        [
            'name' => 'Finance Monthly',
            'start' => $financeMeaningfulFirstMonth,
            'end' => $financeMeaningfulLastMonth,
            'record_start' => $financeFirstMonth,
            'record_end' => $financeLastMonth,
            'status' => $periodStatus($financeLag),
            'lag_months' => $financeLag,
            'detail' => $financeMeaningfulLastMonth
                ? 'ใช้ข้อมูลที่มีตัวเลขจริงจากแบบบันทึกรายเดือน'
                : 'มีรายการเดือน แต่ยังไม่มีตัวเลขสำหรับวิเคราะห์',
        ],
        [
            'name' => 'Trial Balance',
            'start' => $trialBalanceMonth,
            'end' => $trialBalanceMonth,
            'status' => $periodStatus($trialLag),
            'lag_months' => $trialLag,
            'detail' => $trialBalanceReady
                ? count($trialRows) . ' บัญชี ใช้คำนวณ AR, AP, Cash, Assets และ Outcome'
                : 'ยังไม่มีงบทดลองสำหรับช่วงรายงาน',
        ],
        [
            'name' => 'HIS OPD/IPD สะสมปีงบประมาณ',
            'start' => $analysisStart,
            'end' => $reportEnd,
            'status' => 'current',
            'lag_months' => 0,
            'detail' => 'ใช้ Visit, Patient Days, Occupancy และ Sum AdjRW ถึงวันที่รายงาน',
        ],
        [
            'name' => 'สถานะเตียงเดือนรายงาน',
            'start' => $analysisStart,
            'end' => $reportEnd,
            'status' => 'current',
            'lag_months' => 0,
            'detail' => 'คำนวณเตียงทั่วไป 93 เตียง และ ICU 9 เตียง',
        ],
        [
            'name' => 'ช่วงเปรียบเทียบ AdjRW ปีก่อน',
            'start' => $lastFiscalStart,
            'end' => $lastReportEnd,
            'status' => $lastSumAdjrw > 0 ? 'current' : 'missing',
            'lag_months' => null,
            'detail' => $lastSumAdjrw > 0 ? 'ใช้ช่วงเดียวกันของปีก่อน' : 'ไม่พบ AdjRW ปีก่อนสำหรับคำนวณ Growth',
        ],
    ];

    echo json_encode([
        'status' => 'success',
        'data_found' => count($rows) > 0 || count($autoRows) > 0,
        'period' => [
            'month' => $month,
            'mode' => $periodMode,
            'label' => $periodLabel,
            'analysis_start' => $analysisStart,
            'fiscal_start' => $fiscalStart,
            'month_end' => $monthEnd,
            'report_end' => $reportEnd,
            'fiscal_year' => $fiscalYear,
            'days_elapsed' => $daysElapsed,
        ],
        'analysis_periods' => $analysisPeriods,
        'tps' => [
            'ap' => [
                'code' => '1.2.1',
                'value' => $apDays,
                'target' => 90,
                'status' => cfo_status($apDays, 90),
                'balance' => round($payableBalance, 2),
                'cumulative' => round($cumulativePurchases, 2),
                'source' => $trialBalanceReady ? 'Trial Balance + Finance' : 'Finance',
            ],
            'ar_uc' => [
                'code' => '1.2.2',
                'value' => $arUcDays,
                'target' => 60,
                'status' => cfo_status($arUcDays, 60),
                'balance' => round($tb['ar_uc'], 2),
                'source' => 'Trial Balance',
            ],
            'ar_csmbs' => [
                'code' => '1.2.3',
                'value' => $arCsmbsDays,
                'target' => 60,
                'status' => cfo_status($arCsmbsDays, 60),
                'balance' => round($tb['ar_csmbs'], 2),
                'source' => 'Trial Balance',
            ],
            'inventory' => [
                'code' => '1.2.4',
                'value' => $inventoryDays,
                'target' => 60,
                'status' => cfo_status($inventoryDays, 60),
                'balance' => round($inventoryEnding, 2),
                'cumulative' => round($cumulativeUsage, 2),
                'source' => $trialBalanceReady ? 'Trial Balance + Finance' : 'Finance',
            ],
        ],
        'management' => $managementMetrics,
        'outcomes' => $outcomes,
        'supplemental' => $supplemental,
        'chart_completeness' => $chartCompleteness,
        'summary' => [
            'income' => round($outcomeIncome, 2),
            'expense' => round($outcomeExpense, 2),
            'net_income' => $outcomeNetIncome === null ? null : round($outcomeNetIncome, 2),
            'cash' => round($cash, 2),
            'current_assets' => round($currentAssets, 2),
            'current_liabilities' => round($currentLiabilities, 2),
            'total_assets' => round($tb['total_assets'], 2),
            'depreciation' => round($tb['depreciation'], 2),
            'opd_visits' => $opdVisits,
            'sum_adjrw' => round($sumAdjrw, 4),
        ],
        'charts' => [
            'labels' => $trendLabels,
            'income' => $trendIncome,
            'expense' => $trendExpense,
            'profit' => $trendProfit,
            'cost_structure' => [
                'labels' => ['LC', 'MC ยา', 'MC วัสดุวิทย์', 'MC ทั่วไป', 'CC/อื่นๆ'],
                'values' => [
                    round($cumulativeCompensation, 2),
                    round($cumulativeDrug, 2),
                    round($cumulativeLab, 2),
                    round($cumulativeGeneralMaterial, 2),
                    round(max($totalCost - $cumulativeCompensation - $cumulativeDrug - $cumulativeLab - $cumulativeGeneralMaterial, 0), 2),
                ],
            ],
        ],
        'data_readiness' => [
            ['name' => 'Finance Monthly', 'ready' => $chartCompleteness['months_with_data'] > 0, 'detail' => $chartCompleteness['months_with_data'] . '/' . $chartCompleteness['expected_months'] . ' เดือนมีตัวเลขสำหรับกราฟ'],
            ['name' => 'Trial Balance', 'ready' => $trialBalanceReady, 'detail' => $trialBalanceReady ? count($trialRows) . ' บัญชี · อ้างอิง ' . $trialBalanceMonth : 'ยังไม่มีงบทดลองก่อนเดือนที่เลือก'],
            ['name' => 'HIS OPD', 'ready' => $opdVisits > 0, 'detail' => number_format($opdVisits) . ' visits สะสม'],
            ['name' => 'HIS IPD / AdjRW', 'ready' => $sumAdjrw > 0, 'detail' => number_format($sumAdjrw, 4) . ' AdjRW สะสม'],
            ['name' => 'PlanFin / Budget', 'ready' => $supplemental['planfin'] !== null, 'detail' => $supplemental['planfin'] ? 'มีเป้าหมายและงบประมาณเดือนรายงาน' : 'ยังไม่มี PlanFin เดือนรายงาน'],
            ['name' => 'AR / AP Aging', 'ready' => $supplemental['aging_rows'] > 0, 'detail' => number_format($supplemental['aging_rows']) . ' ช่วงอายุหนี้'],
            ['name' => 'Claim Quality', 'ready' => $supplemental['claim_quality'] !== null, 'detail' => $supplemental['claim_quality'] ? 'มี Claim lag และ Denial rate' : 'ยังไม่มีข้อมูลคุณภาพ Claim'],
            ['name' => 'Cost Center', 'ready' => $supplemental['cost_center_rows'] > 0, 'detail' => number_format($supplemental['cost_center_rows']) . ' หน่วยต้นทุนในช่วงวิเคราะห์'],
            ['name' => 'Asset Register', 'ready' => $supplemental['asset_rows'] > 0, 'detail' => number_format($supplemental['asset_rows']) . ' สินทรัพย์ใช้งาน'],
            ['name' => 'Inventory Actual Issues', 'ready' => $supplemental['inventory_actual_issues'] > 0, 'detail' => number_format($supplemental['inventory_actual_issues'], 2) . ' บาท'],
        ],
        'beds' => [
            'general' => [
                'beds' => $generalBeds,
                'active' => $generalActive,
                'available' => max($generalBeds - $generalActive, 0),
                'patient_days' => $generalPatientDays,
                'occupancy' => $generalOcc,
            ],
            'icu' => [
                'beds' => $icuBeds,
                'active' => $icuActive,
                'available' => max($icuBeds - $icuActive, 0),
                'patient_days' => $icuPatientDays,
                'occupancy' => $icuOcc,
            ],
            'total' => [
                'beds' => $totalBeds,
                'active' => $generalActive + $icuActive,
                'available' => max($totalBeds - $generalActive - $icuActive, 0),
                'patient_days' => $generalPatientDays + $icuPatientDays,
                'occupancy' => $totalOcc,
            ],
        ],
        'notes' => [
            $chartCompleteness['expected_months'] > 0
                ? 'ความครบถ้วนกราฟการเงิน ' . $chartCompleteness['months_with_data'] . '/' . $chartCompleteness['expected_months'] . ' เดือน' . (count($chartCompleteness['missing_months']) > 0 ? ' ขาด ' . implode(', ', $chartCompleteness['missing_months']) : '')
                : 'ยังไม่มีช่วงเดือนสำหรับกราฟการเงิน',
            'AR UC และ AR ข้าราชการคำนวณจากบัญชีลูกหนี้ในงบทดลองล่าสุดที่ไม่เกินเดือนรายงาน',
            'การแยก ICU ใช้ชื่อหรือรหัส ward ที่มีคำว่า ICU/ไอซียู/กึ่งวิกฤต',
            'Unit Cost และสัดส่วนต้นทุนเป็นการจัดสรรจากข้อมูล Finance ที่มีอยู่ ควรเชื่อม Costing Software เพื่อใช้เกณฑ์ benchmark อย่างเป็นทางการ',
            $trialBalanceReady ? 'งบทดลองที่ใช้อ้างอิงล่าสุด: ' . $trialBalanceMonth : 'ยังไม่พบงบทดลองสำหรับช่วงที่เลือก',
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
