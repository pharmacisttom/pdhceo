<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

function xray_current_fiscal_year(): int
{
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok'));
    $year = (int)$now->format('Y');
    $month = (int)$now->format('n');
    return ($month >= 10 ? $year + 1 : $year) + 543;
}

function xray_fiscal_range(int $thaiFiscalYear): array
{
    if ($thaiFiscalYear < 2400) {
        $thaiFiscalYear += 543;
    }
    $endYear = $thaiFiscalYear - 543;
    $startYear = $endYear - 1;
    return [
        'fiscal_year' => $thaiFiscalYear,
        'start' => sprintf('%04d-10-01', $startYear),
        'end' => sprintf('%04d-09-30', $endYear),
    ];
}

function xray_number(array $row, string $key): float
{
    return round((float)($row[$key] ?? 0), 2);
}

function xray_text($value): string
{
    $text = trim((string)($value ?? ''));
    if ($text === '') return '-';
    if (preg_match('//u', $text)) return preg_replace('/\s+/u', ' ', $text) ?? $text;
    $converted = @iconv('TIS-620', 'UTF-8//IGNORE', $text);
    return $converted !== false && $converted !== '' ? trim((string)preg_replace('/\s+/u', ' ', $converted)) : $text;
}

function xray_modality_case(string $nameColumn = 'namexray', string $codeColumn = 'codexray'): string
{
    return "CASE WHEN UPPER(COALESCE({$codeColumn}, '')) LIKE 'CT%' OR UPPER(COALESCE({$nameColumn}, '')) LIKE '%CT%' OR {$nameColumn} LIKE '%ซีที%' THEN 'CT Scan' ELSE 'X-ray' END";
}

try {
    require_once __DIR__ . '/../includes/auth.php';
    require_login();
    require_once __DIR__ . '/../config/his_database.php';

    $fyInput = (int)($_GET['fiscal_year'] ?? xray_current_fiscal_year());
    $fy = xray_fiscal_range($fyInput);
    $start = $fy['start'];
    $end = $fy['end'];

    $serviceSql = "
        SELECT
            'OPD' AS service_type,
            regdate AS service_date,
            hn,
            NULL AS an,
            frequency,
            orderno,
            codexray,
            namexray,
            amount,
            price,
            support,
            nonsupport,
            status_xray,
            filmgood,
            filmbad
        FROM opd.xray_order_opd
        WHERE regdate BETWEEN :start AND :end
        UNION ALL
        SELECT
            'IPD' AS service_type,
            orderdate AS service_date,
            hn,
            an,
            NULL AS frequency,
            orderno,
            codexray,
            namexray,
            amount,
            price,
            support,
            nonsupport,
            status_xray,
            filmgood,
            filmbad
        FROM ipd.xray_order_ipd
        WHERE orderdate BETWEEN :start2 AND :end2
    ";

    $stmt = $his->prepare("
        SELECT
            COUNT(*) AS order_count,
            COUNT(DISTINCT CASE WHEN service_type = 'IPD' THEN CONCAT('IPD#', COALESCE(an, hn)) ELSE CONCAT('OPD#', service_date, '#', hn, '#', COALESCE(frequency, 0)) END) AS encounter_count,
            COUNT(DISTINCT hn) AS patient_count,
            SUM(CASE WHEN COALESCE(amount, 0) > 0 THEN amount ELSE 1 END) AS exam_qty,
            SUM(CASE WHEN COALESCE(amount, 0) > 0 THEN amount * price ELSE price END) AS gross_value,
            SUM(support) AS support_value,
            SUM(nonsupport) AS nonsupport_value,
            SUM(filmgood) AS filmgood,
            SUM(filmbad) AS filmbad
        FROM ({$serviceSql}) x
    ");
    $stmt->execute([':start' => $start, ':end' => $end, ':start2' => $start, ':end2' => $end]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $modalityCase = xray_modality_case('namexray', 'codexray');
    $stmt = $his->prepare("
        SELECT
            service_type,
            {$modalityCase} AS modality,
            COUNT(*) AS order_count,
            SUM(CASE WHEN COALESCE(amount, 0) > 0 THEN amount ELSE 1 END) AS exam_qty,
            SUM(CASE WHEN COALESCE(amount, 0) > 0 THEN amount * price ELSE price END) AS gross_value,
            SUM(support) AS support_value,
            SUM(nonsupport) AS nonsupport_value
        FROM ({$serviceSql}) x
        GROUP BY service_type, modality
        ORDER BY service_type, modality
    ");
    $stmt->execute([':start' => $start, ':end' => $end, ':start2' => $start, ':end2' => $end]);
    $serviceRows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $serviceRows[] = [
            'service_type' => (string)$row['service_type'],
            'modality' => (string)$row['modality'],
            'order_count' => (int)$row['order_count'],
            'exam_qty' => xray_number($row, 'exam_qty'),
            'gross_value' => xray_number($row, 'gross_value'),
            'support_value' => xray_number($row, 'support_value'),
            'nonsupport_value' => xray_number($row, 'nonsupport_value'),
        ];
    }

    $months = [];
    $cursor = new DateTimeImmutable($start);
    $limit = new DateTimeImmutable(substr($end, 0, 7) . '-01');
    while ($cursor <= $limit) {
        $months[$cursor->format('Y-m')] = ['orders' => 0, 'ct_orders' => 0, 'xray_orders' => 0, 'gross_value' => 0.0];
        $cursor = $cursor->modify('+1 month');
    }
    $stmt = $his->prepare("
        SELECT
            DATE_FORMAT(service_date, '%Y-%m') AS ym,
            COUNT(*) AS orders,
            SUM(CASE WHEN {$modalityCase} = 'CT Scan' THEN 1 ELSE 0 END) AS ct_orders,
            SUM(CASE WHEN {$modalityCase} = 'X-ray' THEN 1 ELSE 0 END) AS xray_orders,
            SUM(CASE WHEN COALESCE(amount, 0) > 0 THEN amount * price ELSE price END) AS gross_value
        FROM ({$serviceSql}) x
        GROUP BY ym
        ORDER BY ym
    ");
    $stmt->execute([':start' => $start, ':end' => $end, ':start2' => $start, ':end2' => $end]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ym = (string)$row['ym'];
        if (!isset($months[$ym])) continue;
        $months[$ym] = [
            'orders' => (int)$row['orders'],
            'ct_orders' => (int)$row['ct_orders'],
            'xray_orders' => (int)$row['xray_orders'],
            'gross_value' => xray_number($row, 'gross_value'),
        ];
    }

    $stmt = $his->prepare("
        SELECT
            {$modalityCase} AS modality,
            COALESCE(codexray, '-') AS codexray,
            COALESCE(namexray, '-') AS namexray,
            COUNT(*) AS order_count,
            SUM(CASE WHEN COALESCE(amount, 0) > 0 THEN amount ELSE 1 END) AS exam_qty,
            SUM(CASE WHEN COALESCE(amount, 0) > 0 THEN amount * price ELSE price END) AS gross_value
        FROM ({$serviceSql}) x
        GROUP BY modality, codexray, namexray
        ORDER BY gross_value DESC
        LIMIT 15
    ");
    $stmt->execute([':start' => $start, ':end' => $end, ':start2' => $start, ':end2' => $end]);
    $topExams = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $topExams[] = [
            'modality' => (string)$row['modality'],
            'codexray' => xray_text($row['codexray']),
            'namexray' => xray_text($row['namexray']),
            'order_count' => (int)$row['order_count'],
            'exam_qty' => xray_number($row, 'exam_qty'),
            'gross_value' => xray_number($row, 'gross_value'),
        ];
    }

    $stmt = $his->prepare("
        SELECT status_xray, COUNT(*) AS total
        FROM ({$serviceSql}) x
        GROUP BY status_xray
        ORDER BY total DESC
        LIMIT 10
    ");
    $stmt->execute([':start' => $start, ':end' => $end, ':start2' => $start, ':end2' => $end]);
    $statuses = array_map(static fn(array $row): array => [
        'status_xray' => xray_text($row['status_xray'] ?? ''),
        'total' => (int)$row['total'],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));

    $opdDiagRows = [];
    try {
        $stmt = $his->prepare("
            SELECT d.diag, MAX(d.descrip) AS descrip, COUNT(DISTINCT CONCAT(o.regdate, '#', o.hn, '#', o.frequency)) AS total
            FROM opd.xray_order_opd o
            INNER JOIN opd.odiag d ON d.regdate = o.regdate AND d.hn = o.hn AND d.frequency = o.frequency
            WHERE o.regdate BETWEEN :start AND :end
              AND d.dxtype = '1'
            GROUP BY d.diag
            ORDER BY total DESC
            LIMIT 10
        ");
        $stmt->execute([':start' => $start, ':end' => $end]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $opdDiagRows[] = ['diag' => xray_text($row['diag']), 'descrip' => xray_text($row['descrip']), 'total' => (int)$row['total']];
        }
    } catch (Throwable $e) {}

    $ipdDiagRows = [];
    try {
        $stmt = $his->prepare("
            SELECT d.diag, MAX(d.descrip) AS descrip, COUNT(DISTINCT i.an) AS total
            FROM ipd.xray_order_ipd x
            INNER JOIN ipd.idiag d ON d.an = x.an
            INNER JOIN ipd.ipd i ON i.an = x.an
            WHERE x.orderdate BETWEEN :start AND :end
              AND d.dxtype = '1'
            GROUP BY d.diag
            ORDER BY total DESC
            LIMIT 10
        ");
        $stmt->execute([':start' => $start, ':end' => $end]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $ipdDiagRows[] = ['diag' => xray_text($row['diag']), 'descrip' => xray_text($row['descrip']), 'total' => (int)$row['total']];
        }
    } catch (Throwable $e) {}

    echo json_encode([
        'status' => 'success',
        'fiscal_year' => $fy['fiscal_year'],
        'period' => ['start' => $start, 'end' => $end],
        'summary' => [
            'order_count' => (int)($summary['order_count'] ?? 0),
            'encounter_count' => (int)($summary['encounter_count'] ?? 0),
            'patient_count' => (int)($summary['patient_count'] ?? 0),
            'exam_qty' => xray_number($summary, 'exam_qty'),
            'gross_value' => xray_number($summary, 'gross_value'),
            'support_value' => xray_number($summary, 'support_value'),
            'nonsupport_value' => xray_number($summary, 'nonsupport_value'),
            'filmgood' => (int)($summary['filmgood'] ?? 0),
            'filmbad' => (int)($summary['filmbad'] ?? 0),
        ],
        'service_breakdown' => $serviceRows,
        'monthly' => [
            'labels' => array_keys($months),
            'orders' => array_column($months, 'orders'),
            'ct_orders' => array_column($months, 'ct_orders'),
            'xray_orders' => array_column($months, 'xray_orders'),
            'gross_value' => array_column($months, 'gross_value'),
        ],
        'top_exams' => $topExams,
        'status_breakdown' => $statuses,
        'diagnoses' => [
            'opd' => $opdDiagRows,
            'ipd' => $ipdDiagRows,
        ],
        'source' => [
            'xray_opd' => 'opd.xray_order_opd',
            'xray_ipd' => 'ipd.xray_order_ipd',
            'opd_diag' => 'opd.odiag',
            'ipd_diag' => 'ipd.idiag',
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
