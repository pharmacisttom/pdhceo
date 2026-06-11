<?php
declare(strict_types=1);

ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

function safe_utf8_opd($value, string $fallback = 'ไม่ระบุ'): string
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

function validate_date_opd(string $date, string $default): string
{
    $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $date);
    return $parsed && $parsed->format('Y-m-d') === $date ? $date : $default;
}

function thai_short_date_opd(string $date): string
{
    $months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $dt = new DateTimeImmutable($date);
    return (int)$dt->format('j') . ' ' . $months[((int)$dt->format('n')) - 1] . ' ' . (((int)$dt->format('Y')) + 543);
}

function current_fiscal_range_opd(): array
{
    $today = new DateTimeImmutable('today');
    $year = (int)$today->format('Y');
    $month = (int)$today->format('n');

    if ($month >= 10) {
        $start = sprintf('%d-10-01', $year);
        $fiscalYear = $year + 1 + 543;
    } else {
        $start = sprintf('%d-10-01', $year - 1);
        $fiscalYear = $year + 543;
    }

    return [
        'start' => $start,
        'end' => $today->format('Y-m-d'),
        'fiscal_year' => $fiscalYear,
    ];
}

try {
    require_once __DIR__ . '/../includes/auth.php';
    require_login();
    require_once __DIR__ . '/../config/his_database.php';

    $fy = current_fiscal_range_opd();
    $startDate = validate_date_opd($_GET['start_date'] ?? $fy['start'], $fy['start']);
    $endDate = validate_date_opd($_GET['end_date'] ?? $fy['end'], $fy['end']);

    if ($startDate > $endDate) {
        [$startDate, $endDate] = [$endDate, $startDate];
    }

    $start = new DateTimeImmutable($startDate);
    $end = new DateTimeImmutable($endDate);
    $days = $start->diff($end)->days + 1;

    $stmt = $his->prepare("
        SELECT
            COUNT(*) AS total_visits,
            COUNT(DISTINCT hn) AS unique_patients,
            SUM(CASE WHEN frequency = 1 THEN 1 ELSE 0 END) AS first_visits,
            SUM(CASE WHEN clinic = '130' THEN 1 ELSE 0 END) AS er_visits,
            SUM(CASE WHEN result IN ('3', 'RST3') THEN 1 ELSE 0 END) AS refer_visits,
            COUNT(DISTINCT clinic) AS active_clinics,
            COUNT(DISTINCT ptclass) AS active_ptclasses
        FROM opd.opd
        WHERE regdate BETWEEN :start AND :end
    ");
    $stmt->execute([':start' => $startDate, ':end' => $endDate]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $totalVisits = (int)($summary['total_visits'] ?? 0);
    $uniquePatients = (int)($summary['unique_patients'] ?? 0);
    $firstVisits = (int)($summary['first_visits'] ?? 0);
    $erVisits = (int)($summary['er_visits'] ?? 0);
    $referVisits = (int)($summary['refer_visits'] ?? 0);
    $activeClinics = (int)($summary['active_clinics'] ?? 0);
    $activePtclasses = (int)($summary['active_ptclasses'] ?? 0);

    $stmt = $his->prepare("
        SELECT c.`Name` AS name, COUNT(*) AS total, COUNT(DISTINCT o.hn) AS patients
        FROM opd.opd o
        LEFT JOIN hos.clinic c ON o.clinic = c.code
        WHERE o.regdate BETWEEN :start AND :end
        GROUP BY o.clinic, c.`Name`
        ORDER BY total DESC
        LIMIT 12
    ");
    $stmt->execute([':start' => $startDate, ':end' => $endDate]);
    $clinic = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $clinic[] = [
            'name' => safe_utf8_opd($row['name']),
            'total' => (int)$row['total'],
            'patients' => (int)$row['patients'],
        ];
    }

    $stmt = $his->prepare("
        SELECT i.`Name` AS name, COUNT(*) AS total, COUNT(DISTINCT o.hn) AS patients
        FROM opd.opd o
        LEFT JOIN hos.insclasses i ON o.ptclass = i.code
        WHERE o.regdate BETWEEN :start AND :end
        GROUP BY o.ptclass, i.`Name`
        ORDER BY total DESC
        LIMIT 12
    ");
    $stmt->execute([':start' => $startDate, ':end' => $endDate]);
    $ptclass = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $ptclass[] = [
            'name' => safe_utf8_opd($row['name']),
            'total' => (int)$row['total'],
            'patients' => (int)$row['patients'],
        ];
    }

    $stmt = $his->prepare("
        SELECT
            SUM(CASE WHEN timereg BETWEEN '08:00' AND '15:59' THEN 1 ELSE 0 END) AS morning,
            SUM(CASE WHEN timereg BETWEEN '16:00' AND '23:59' THEN 1 ELSE 0 END) AS evening,
            SUM(CASE WHEN timereg BETWEEN '00:00' AND '07:59' THEN 1 ELSE 0 END) AS night
        FROM opd.opd
        WHERE regdate BETWEEN :start AND :end
    ");
    $stmt->execute([':start' => $startDate, ':end' => $endDate]);
    $shift = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmt = $his->prepare("
        SELECT regdate, COUNT(*) AS total, COUNT(DISTINCT hn) AS patients
        FROM opd.opd
        WHERE regdate BETWEEN :start AND :end
        GROUP BY regdate
        ORDER BY regdate
    ");
    $stmt->execute([':start' => $startDate, ':end' => $endDate]);
    $dailyMap = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $dailyMap[$row['regdate']] = [
            'total' => (int)$row['total'],
            'patients' => (int)$row['patients'],
        ];
    }

    $dailyLabels = [];
    $dailyVisits = [];
    $dailyPatients = [];
    $cursor = $start;
    while ($cursor <= $end) {
        $key = $cursor->format('Y-m-d');
        $dailyLabels[] = thai_short_date_opd($key);
        $dailyVisits[] = $dailyMap[$key]['total'] ?? 0;
        $dailyPatients[] = $dailyMap[$key]['patients'] ?? 0;
        $cursor = $cursor->modify('+1 day');
    }

    $stmt = $his->prepare("
        SELECT CAST(SUBSTRING(timereg, 1, 2) AS UNSIGNED) AS hour_label, COUNT(*) AS total
        FROM opd.opd
        WHERE regdate BETWEEN :start AND :end
        AND timereg IS NOT NULL
        AND timereg != ''
        GROUP BY hour_label
        ORDER BY hour_label
    ");
    $stmt->execute([':start' => $startDate, ':end' => $endDate]);
    $hourlyMap = array_fill(0, 24, 0);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $hour = (int)$row['hour_label'];
        if ($hour >= 0 && $hour <= 23) {
            $hourlyMap[$hour] = (int)$row['total'];
        }
    }

    $stmt = $his->prepare("
        SELECT
            COALESCE(ci.name, ci.descrip, o.result, 'ไม่ระบุ') AS name,
            COUNT(*) AS total
        FROM opd.opd o
        LEFT JOIN hos.codeinhos ci ON o.result = ci.code AND ci.codechk = 'RST'
        WHERE o.regdate BETWEEN :start AND :end
        GROUP BY name
        ORDER BY total DESC
        LIMIT 10
    ");
    $stmt->execute([':start' => $startDate, ':end' => $endDate]);
    $outcomes = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $outcomes[] = [
            'name' => safe_utf8_opd($row['name']),
            'total' => (int)$row['total'],
        ];
    }

    $stmt = $his->prepare("
        SELECT
            COALESCE(ci.name, ci.descrip, o.ae, 'ไม่ระบุ') AS name,
            COUNT(*) AS total
        FROM opd.opd o
        LEFT JOIN hos.codeinhos ci ON o.ae = ci.code AND ci.codechk = 'AE'
        WHERE o.regdate BETWEEN :start AND :end
        GROUP BY name
        ORDER BY total DESC
        LIMIT 8
    ");
    $stmt->execute([':start' => $startDate, ':end' => $endDate]);
    $ae = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $ae[] = [
            'name' => safe_utf8_opd($row['name']),
            'total' => (int)$row['total'],
        ];
    }

    $stmt = $his->prepare("
        SELECT
            od.diag,
            COALESCE(NULLIF(MAX(od.descrip), ''), od.diag) AS name,
            COUNT(*) AS total,
            COUNT(DISTINCT od.hn) AS patients
        FROM opd.odiag od
        WHERE od.regdate BETWEEN :start AND :end
        AND od.dxtype = '1'
        AND od.diag IS NOT NULL
        AND od.diag != ''
        GROUP BY od.diag
        ORDER BY total DESC
        LIMIT 15
    ");
    $stmt->execute([':start' => $startDate, ':end' => $endDate]);
    $diagnosis = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $diagnosis[] = [
            'diag' => safe_utf8_opd($row['diag'], '-'),
            'name' => safe_utf8_opd($row['name']),
            'total' => (int)$row['total'],
            'patients' => (int)$row['patients'],
        ];
    }

    echo json_encode([
        'status' => 'success',
        'range' => [
            'start' => $startDate,
            'end' => $endDate,
            'days' => $days,
            'fiscal_year' => $fy['fiscal_year'],
            'label' => thai_short_date_opd($startDate) . ' - ' . thai_short_date_opd($endDate),
        ],
        'kpi' => [
            'total_visits' => $totalVisits,
            'unique_patients' => $uniquePatients,
            'first_visits' => $firstVisits,
            'avg_per_day' => $days > 0 ? round($totalVisits / $days, 1) : 0,
            'er_visits' => $erVisits,
            'refer_visits' => $referVisits,
            'refer_rate' => $totalVisits > 0 ? round(($referVisits / $totalVisits) * 100, 2) : 0,
            'active_clinics' => $activeClinics,
            'active_ptclasses' => $activePtclasses,
        ],
        'clinic' => $clinic,
        'ptclass' => $ptclass,
        'diagnosis' => $diagnosis,
        'charts' => [
            'daily' => [
                'labels' => $dailyLabels,
                'visits' => $dailyVisits,
                'patients' => $dailyPatients,
            ],
            'hourly' => [
                'labels' => array_map(fn($h) => sprintf('%02d:00', $h), range(0, 23)),
                'values' => array_values($hourlyMap),
            ],
            'shift' => [
                'labels' => ['08:00-15:59', '16:00-23:59', '00:00-07:59'],
                'values' => [
                    (int)($shift['morning'] ?? 0),
                    (int)($shift['evening'] ?? 0),
                    (int)($shift['night'] ?? 0),
                ],
            ],
            'outcomes' => $outcomes,
            'ae' => $ae,
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
