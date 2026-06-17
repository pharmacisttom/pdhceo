<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

function supply_number(array $row, string $key): float
{
    return round((float)($row[$key] ?? 0), 2);
}

function supply_fiscal_year($value): int
{
    $year = (int)$value;
    if ($year < 1900 || $year > 3000) {
        require_once __DIR__ . '/../config/invc_database.php';
        return invc_current_fiscal_year();
    }
    return $year;
}

function supply_fetch_summary(PDO $pdo, int $fiscalYear): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            fiscal_year,
            stock_as_of_date,
            total_stock_qty,
            rpst_total_qty,
            plan_qty,
            plan_value,
            buy_qty,
            buy_value,
            plan_progress_pct,
            synced_at
        FROM app_invc_supply_planning_summary
        WHERE fiscal_year = :fy
        LIMIT 1
    ");
    $stmt->execute([':fy' => $fiscalYear]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

try {
    require_once __DIR__ . '/../includes/auth.php';
    require_login();
    require_once __DIR__ . '/../config/invc_database.php';

    $requestedFiscalYear = supply_fiscal_year($_GET['fiscal_year'] ?? invc_current_fiscal_year());
    $pdo = invc_get_pdo();

    $summary = supply_fetch_summary($pdo, $requestedFiscalYear);
    $resolvedFiscalYear = $requestedFiscalYear;
    if (!$summary) {
        $alternateFiscalYear = $requestedFiscalYear > 2400 ? $requestedFiscalYear - 543 : $requestedFiscalYear + 543;
        $summary = supply_fetch_summary($pdo, $alternateFiscalYear);
        if ($summary) {
            $resolvedFiscalYear = $alternateFiscalYear;
        }
    }

    $quarterly = [];
    if ($summary) {
        $stmt = $pdo->prepare("
            SELECT
                quarter_label,
                rpst_total_qty,
                synced_at
            FROM app_invc_supply_planning_quarterly
            WHERE fiscal_year = :fy
            ORDER BY FIELD(quarter_label, 'Q1', 'Q2', 'Q3', 'Q4')
        ");
        $stmt->execute([':fy' => $resolvedFiscalYear]);
        $quarterly = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $syncStatus = null;
    if ($summary) {
        try {
            $stmt = $pdo->prepare("
                SELECT
                    status,
                    started_at,
                    finished_at,
                    rows_header,
                    rows_item,
                    rows_summary,
                    message
                FROM app_invc_sync_runs
                WHERE sync_key = 'invc_procurement_weekly'
                  AND fiscal_year = :fy
                ORDER BY run_id DESC
                LIMIT 1
            ");
            $stmt->execute([':fy' => $resolvedFiscalYear]);
            $syncStatus = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) {
            $syncStatus = null;
        }
    }

    $vendorSummary = [
        'vendor_count' => 0,
        'po_count' => 0,
        'item_count' => 0,
        'total_cost' => 0.0,
        'total_cost_received' => 0.0,
        'top_vendors' => [],
        'recent_purchase_items' => [],
    ];
    if ($summary) {
        $stmt = $pdo->prepare("
            SELECT
                COUNT(DISTINCT vendor_code) AS vendor_count,
                COUNT(DISTINCT po_no) AS po_count,
                COALESCE(SUM(total_item), 0) AS item_count,
                COALESCE(SUM(total_cost), 0) AS total_cost,
                COALESCE(SUM(total_cost_received), 0) AS total_cost_received
            FROM app_invc_purchase_headers
            WHERE fiscal_year = :fy
        ");
        $stmt->execute([':fy' => $resolvedFiscalYear]);
        $vendorTotals = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $vendorSummary['vendor_count'] = (int)($vendorTotals['vendor_count'] ?? 0);
        $vendorSummary['po_count'] = (int)($vendorTotals['po_count'] ?? 0);
        $vendorSummary['item_count'] = supply_number($vendorTotals, 'item_count');
        $vendorSummary['total_cost'] = supply_number($vendorTotals, 'total_cost');
        $vendorSummary['total_cost_received'] = supply_number($vendorTotals, 'total_cost_received');

        $stmt = $pdo->prepare("
            SELECT
                vendor_code,
                company_name,
                COUNT(DISTINCT po_no) AS po_count,
                COALESCE(SUM(total_item), 0) AS item_count,
                COALESCE(SUM(total_cost), 0) AS total_cost,
                COALESCE(SUM(total_cost_received), 0) AS total_cost_received,
                MAX(synced_at) AS synced_at
            FROM app_invc_purchase_headers
            WHERE fiscal_year = :fy
            GROUP BY vendor_code, company_name
            ORDER BY total_cost DESC
            LIMIT 10
        ");
        $stmt->execute([':fy' => $resolvedFiscalYear]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $vendorSummary['top_vendors'][] = [
                'vendor_code' => (string)($row['vendor_code'] ?? ''),
                'company_name' => (string)($row['company_name'] ?? ''),
                'po_count' => (int)($row['po_count'] ?? 0),
                'item_count' => supply_number($row, 'item_count'),
                'total_cost' => supply_number($row, 'total_cost'),
                'total_cost_received' => supply_number($row, 'total_cost_received'),
                'synced_at' => $row['synced_at'] ?? null,
            ];
        }

        $stmt = $pdo->prepare("
            SELECT
                po_no,
                po_date,
                vendor_code,
                company_name,
                working_code,
                drug_name,
                qty_order_pack,
                po_unit,
                buy_unit_cost,
                buy_value,
                synced_at
            FROM app_invc_purchase_items
            WHERE fiscal_year = :fy
            ORDER BY po_date DESC, po_no DESC
            LIMIT 15
        ");
        $stmt->execute([':fy' => $resolvedFiscalYear]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $vendorSummary['recent_purchase_items'][] = [
                'po_no' => (string)($row['po_no'] ?? ''),
                'po_date' => $row['po_date'] ?? null,
                'vendor_code' => (string)($row['vendor_code'] ?? ''),
                'company_name' => (string)($row['company_name'] ?? ''),
                'working_code' => (string)($row['working_code'] ?? ''),
                'drug_name' => (string)($row['drug_name'] ?? ''),
                'qty_order_pack' => supply_number($row, 'qty_order_pack'),
                'po_unit' => (string)($row['po_unit'] ?? ''),
                'buy_unit_cost' => supply_number($row, 'buy_unit_cost'),
                'buy_value' => supply_number($row, 'buy_value'),
                'synced_at' => $row['synced_at'] ?? null,
            ];
        }
    }

    $quarters = ['Q1' => 0.0, 'Q2' => 0.0, 'Q3' => 0.0, 'Q4' => 0.0];
    $quarterRows = [
        'Q1' => ['quarter_label' => 'Q1', 'rpst_total_qty' => 0.0, 'synced_at' => null],
        'Q2' => ['quarter_label' => 'Q2', 'rpst_total_qty' => 0.0, 'synced_at' => null],
        'Q3' => ['quarter_label' => 'Q3', 'rpst_total_qty' => 0.0, 'synced_at' => null],
        'Q4' => ['quarter_label' => 'Q4', 'rpst_total_qty' => 0.0, 'synced_at' => null],
    ];
    $quarterSyncedAt = null;
    foreach ($quarterly as $row) {
        $label = (string)($row['quarter_label'] ?? '');
        if (array_key_exists($label, $quarters)) {
            $quarters[$label] = supply_number($row, 'rpst_total_qty');
            $quarterRows[$label] = [
                'quarter_label' => $label,
                'rpst_total_qty' => supply_number($row, 'rpst_total_qty'),
                'synced_at' => $row['synced_at'] ?? null,
            ];
        }
        if (!empty($row['synced_at'])) {
            $quarterSyncedAt = max((string)$quarterSyncedAt, (string)$row['synced_at']);
        }
    }

    $hasData = (bool)$summary;
    $planValue = $hasData ? supply_number($summary, 'plan_value') : 0.0;
    $buyValue = $hasData ? supply_number($summary, 'buy_value') : 0.0;
    $progress = $hasData ? (float)($summary['plan_progress_pct'] ?? 0) : 0.0;
    if ($hasData && $progress <= 0 && $planValue > 0) {
        $progress = round(($buyValue / $planValue) * 100, 2);
    }
    $planQty = $hasData ? supply_number($summary, 'plan_qty') : 0.0;
    $buyQty = $hasData ? supply_number($summary, 'buy_qty') : 0.0;
    $remainingQty = round(max($planQty - $buyQty, 0), 2);
    $remainingValue = round(max($planValue - $buyValue, 0), 2);

    echo json_encode([
        'status' => 'success',
        'data_found' => $hasData,
        'requested_fiscal_year' => $requestedFiscalYear,
        'fiscal_year' => $resolvedFiscalYear,
        'source' => [
            'server' => '192.168.111.240',
            'database' => 'himtoinvc',
            'tables' => [
                'app_invc_supply_planning_summary',
                'app_invc_supply_planning_quarterly',
                'app_invc_sync_runs',
            ],
            'rule' => 'Shared MySQL cache only',
        ],
        'summary' => $hasData ? [
            'stock_as_of_date' => $summary['stock_as_of_date'] ?? null,
            'total_stock_qty' => supply_number($summary, 'total_stock_qty'),
            'rpst_total_qty' => supply_number($summary, 'rpst_total_qty'),
            'plan_qty' => $planQty,
            'plan_value' => $planValue,
            'buy_qty' => $buyQty,
            'buy_value' => $buyValue,
            'remaining_qty' => $remainingQty,
            'remaining_value' => $remainingValue,
            'plan_progress_pct' => round($progress, 2),
            'synced_at' => $summary['synced_at'] ?? $quarterSyncedAt,
        ] : null,
        'quarters' => [
            'labels' => array_keys($quarters),
            'rpst_total_qty' => array_values($quarters),
            'rows' => array_values($quarterRows),
            'total' => array_sum($quarters),
        ],
        'sync_status' => $syncStatus,
        'vendor_purchases' => $vendorSummary,
        'message' => $hasData ? null : 'No synced data yet',
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    $message = $e->getMessage();
    if (str_contains($message, 'not allowed to connect') || str_contains($message, 'No connection could be made')) {
        $message = 'ไม่สามารถเชื่อมต่อฐานข้อมูล MySQL cache himtoinvc ได้ กรุณาตรวจสอบสิทธิ์ผู้ใช้ฐานข้อมูลและ host ของ web server';
    }
    echo json_encode([
        'status' => 'error',
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE);
}
