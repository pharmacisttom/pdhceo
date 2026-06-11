<?php
declare(strict_types=1);

ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

function safe_utf8_er($value): string
{
    if ($value === null || $value === '') {
        return 'ไม่ระบุ';
    }

    if (preg_match('//u', (string)$value)) {
        return (string)$value;
    }

    $converted = @iconv('TIS-620', 'UTF-8//IGNORE', (string)$value);
    return $converted !== false ? $converted : (string)$value;
}

function current_fiscal_year_er(): array
{
    $today = new DateTimeImmutable('today');
    $year = (int)$today->format('Y');
    $month = (int)$today->format('n');

    if ($month >= 10) {
        $start = sprintf('%d-10-01', $year);
        $end = sprintf('%d-09-30', $year + 1);
        $fy = $year + 1 + 543;
    } else {
        $start = sprintf('%d-10-01', $year - 1);
        $end = sprintf('%d-09-30', $year);
        $fy = $year + 543;
    }

    $todayText = $today->format('Y-m-d');

    return [
        'year' => $fy,
        'start' => $start,
        'end' => min($end, $todayText),
        'today' => $todayText,
    ];
}

try {
    require_once __DIR__ . '/../includes/auth.php';
    require_login();
    require_once __DIR__ . '/../config/his_database.php';

    $fy = current_fiscal_year_er();
    $daysElapsed = (new DateTimeImmutable($fy['start']))->diff(new DateTimeImmutable($fy['end']))->days + 1;

    $stmt = $his->prepare("
        SELECT
            COUNT(*) AS er_total,
            COUNT(DISTINCT hn) AS unique_patients,
            SUM(CASE WHEN regdate = :today THEN 1 ELSE 0 END) AS er_today,
            SUM(CASE WHEN frequency = 1 THEN 1 ELSE 0 END) AS new_cases
        FROM opd.opd
        WHERE clinic = '130'
        AND regdate BETWEEN :start AND :end
    ");
    $stmt->execute([
        ':today' => $fy['today'],
        ':start' => $fy['start'],
        ':end' => $fy['end'],
    ]);
    $erSummary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $erTotal = (int)($erSummary['er_total'] ?? 0);
    $erToday = (int)($erSummary['er_today'] ?? 0);
    $uniquePatients = (int)($erSummary['unique_patients'] ?? 0);
    $newCases = (int)($erSummary['new_cases'] ?? 0);
    $avgPerDay = $daysElapsed > 0 ? round($erTotal / $daysElapsed, 1) : 0;

    $stmt = $his->prepare("
        SELECT COUNT(*)
        FROM referdb.referout
        WHERE LOWER(station_name) = 'er'
        AND refer_date BETWEEN :start AND :end
    ");
    $stmt->execute([':start' => $fy['start'], ':end' => $fy['end']]);
    $referFy = (int)$stmt->fetchColumn();

    $stmt = $his->prepare("
        SELECT COUNT(*)
        FROM referdb.referout
        WHERE LOWER(station_name) = 'er'
        AND refer_date = :today
    ");
    $stmt->execute([':today' => $fy['today']]);
    $referToday = (int)$stmt->fetchColumn();

    $stmt = $his->prepare("
        SELECT COUNT(*)
        FROM ipd.ipd i
        WHERE i.dateadm BETWEEN :start AND :end
        AND EXISTS (
            SELECT 1
            FROM opd.opd o
            WHERE o.hn = i.hn
            AND o.regdate = i.dateadm
            AND o.clinic = '130'
            LIMIT 1
        )
    ");
    $stmt->execute([':start' => $fy['start'], ':end' => $fy['end']]);
    $admitFy = (int)$stmt->fetchColumn();

    $stmt = $his->prepare("
        SELECT COUNT(*)
        FROM ipd.ipd i
        WHERE i.dateadm = :today
        AND EXISTS (
            SELECT 1
            FROM opd.opd o
            WHERE o.hn = i.hn
            AND o.regdate = i.dateadm
            AND o.clinic = '130'
            LIMIT 1
        )
    ");
    $stmt->execute([':today' => $fy['today']]);
    $admitToday = (int)$stmt->fetchColumn();

    $stmt = $his->prepare("
        SELECT
            SUM(CASE WHEN regdate = :today THEN 1 ELSE 0 END) AS death_today,
            COUNT(*) AS death_fy
        FROM opd.opd
        WHERE clinic = '130'
        AND result = 'RST5'
        AND regdate BETWEEN :start AND :end
    ");
    $stmt->execute([
        ':today' => $fy['today'],
        ':start' => $fy['start'],
        ':end' => $fy['end'],
    ]);
    $deathSummary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $deathToday = (int)($deathSummary['death_today'] ?? 0);
    $deathFy = (int)($deathSummary['death_fy'] ?? 0);

    $stmt = $his->prepare("
        SELECT name
        FROM hos.codeinhos
        WHERE code = 'RST5'
        AND codechk = 'RST'
        LIMIT 1
    ");
    $stmt->execute();
    $deathResultName = safe_utf8_er($stmt->fetchColumn() ?: 'ตายที่ห้องฉุกเฉิน');

    $stmt = $his->prepare("
        SELECT
            SUM(CASE WHEN timereg BETWEEN '08:00' AND '15:59' THEN 1 ELSE 0 END) AS morning,
            SUM(CASE WHEN timereg BETWEEN '16:00' AND '23:59' THEN 1 ELSE 0 END) AS afternoon,
            SUM(CASE WHEN timereg BETWEEN '00:00' AND '07:59' THEN 1 ELSE 0 END) AS night
        FROM opd.opd
        WHERE clinic = '130'
        AND regdate BETWEEN :start AND :end
    ");
    $stmt->execute([':start' => $fy['start'], ':end' => $fy['end']]);
    $shift = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $monthLabels = [];
    $monthEr = [];
    $monthRefer = [];
    $monthAdmit = [];
    $thaiMonths = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $cursor = new DateTimeImmutable(substr($fy['start'], 0, 7) . '-01');
    $endMonth = new DateTimeImmutable(substr($fy['end'], 0, 7) . '-01');

    while ($cursor <= $endMonth) {
        $monthStart = $cursor->format('Y-m-01');
        $monthEnd = min($cursor->format('Y-m-t'), $fy['end']);
        $monthLabels[] = $thaiMonths[((int)$cursor->format('n')) - 1] . ' ' . (((int)$cursor->format('Y')) + 543);

        $stmt = $his->prepare("SELECT COUNT(*) FROM opd.opd WHERE clinic = '130' AND regdate BETWEEN :start AND :end");
        $stmt->execute([':start' => $monthStart, ':end' => $monthEnd]);
        $monthEr[] = (int)$stmt->fetchColumn();

        $stmt = $his->prepare("
            SELECT COUNT(*)
            FROM referdb.referout
            WHERE LOWER(station_name) = 'er'
            AND refer_date BETWEEN :start AND :end
        ");
        $stmt->execute([':start' => $monthStart, ':end' => $monthEnd]);
        $monthRefer[] = (int)$stmt->fetchColumn();

        $stmt = $his->prepare("
            SELECT COUNT(*)
            FROM ipd.ipd i
            WHERE i.dateadm BETWEEN :start AND :end
            AND EXISTS (
                SELECT 1 FROM opd.opd o
                WHERE o.hn = i.hn
                AND o.regdate = i.dateadm
                AND o.clinic = '130'
                LIMIT 1
            )
        ");
        $stmt->execute([':start' => $monthStart, ':end' => $monthEnd]);
        $monthAdmit[] = (int)$stmt->fetchColumn();

        $cursor = $cursor->modify('+1 month');
    }

    $stmt = $his->prepare("
        SELECT HOUR(STR_TO_DATE(timereg, '%H:%i')) AS hour_label, COUNT(*) AS total
        FROM opd.opd
        WHERE clinic = '130'
        AND regdate = :today
        AND timereg IS NOT NULL
        AND timereg != ''
        GROUP BY hour_label
        ORDER BY hour_label
    ");
    $stmt->execute([':today' => $fy['today']]);
    $hourMap = array_fill(0, 24, 0);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $hour = (int)$row['hour_label'];
        if ($hour >= 0 && $hour <= 23) {
            $hourMap[$hour] = (int)$row['total'];
        }
    }

    $stmt = $his->prepare("
        SELECT d.diag AS code, MAX(d.descrip) AS name, COUNT(DISTINCT CONCAT(o.regdate, o.hn, o.frequency)) AS total
        FROM opd.opd o
        INNER JOIN opd.odiag d
            ON o.regdate = d.regdate
            AND o.hn = d.hn
            AND o.frequency = d.frequency
        WHERE o.clinic = '130'
        AND o.regdate BETWEEN :start AND :end
        AND d.dxtype = '1'
        AND d.diag IS NOT NULL
        AND d.diag != ''
        GROUP BY d.diag
        ORDER BY total DESC
        LIMIT 10
    ");
    $stmt->execute([':start' => $fy['start'], ':end' => $fy['end']]);
    $diagRows = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $diagRows[] = [
            'code' => $row['code'],
            'name' => safe_utf8_er($row['name']),
            'total' => (int)$row['total'],
        ];
    }

    $stmt = $his->prepare("
        SELECT d.diag AS code, MAX(d.descrip) AS name, COUNT(DISTINCT CONCAT(o.regdate, o.hn, o.frequency)) AS total
        FROM opd.opd o
        INNER JOIN opd.odiag d
            ON o.regdate = d.regdate
            AND o.hn = d.hn
            AND o.frequency = d.frequency
        WHERE o.clinic = '130'
        AND o.result = 'RST5'
        AND o.regdate BETWEEN :start AND :end
        AND d.dxtype = '1'
        AND d.diag IS NOT NULL
        AND d.diag != ''
        GROUP BY d.diag
        ORDER BY total DESC
        LIMIT 10
    ");
    $stmt->execute([':start' => $fy['start'], ':end' => $fy['end']]);
    $deathDiagnosisRows = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $deathDiagnosisRows[] = [
            'code' => $row['code'],
            'name' => safe_utf8_er($row['name']),
            'total' => (int)$row['total'],
        ];
    }

    $stmt = $his->prepare("
        SELECT i.`Name` AS label, COUNT(*) AS total
        FROM opd.opd o
        LEFT JOIN hos.insclasses i ON o.ptclass = i.code
        WHERE o.clinic = '130'
        AND o.regdate BETWEEN :start AND :end
        GROUP BY o.ptclass, i.`Name`
        ORDER BY total DESC
        LIMIT 8
    ");
    $stmt->execute([':start' => $fy['start'], ':end' => $fy['end']]);
    $payerLabels = [];
    $payerData = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $payerLabels[] = safe_utf8_er($row['label']);
        $payerData[] = (int)$row['total'];
    }

    $stmt = $his->prepare("
        SELECT COALESCE(ci.name, o.result, 'ไม่ระบุ') AS result_name, COUNT(*) AS total
        FROM opd.opd o
        LEFT JOIN hos.codeinhos ci
            ON o.result = ci.code
            AND ci.codechk = 'RST'
        WHERE o.clinic = '130'
        AND o.regdate BETWEEN :start AND :end
        GROUP BY o.result, ci.name
        ORDER BY total DESC
    ");
    $stmt->execute([':start' => $fy['start'], ':end' => $fy['end']]);
    $outcomeLabels = [];
    $outcomeData = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $outcomeLabels[] = safe_utf8_er($row['result_name']);
        $outcomeData[] = (int)$row['total'];
    }

    $stmt = $his->prepare("
        SELECT
            i.`Name` AS ptclass_name,
            COUNT(*) AS total_visits,
            COUNT(DISTINCT o.hn) AS total_patients
        FROM opd.opd o
        LEFT JOIN hos.insclasses i ON o.ptclass = i.code
        WHERE o.clinic = '130'
        AND o.regdate = :today
        GROUP BY o.ptclass, i.`Name`
        ORDER BY total_visits DESC
    ");
    $stmt->execute([':today' => $fy['today']]);
    $todayPayerRows = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $todayPayerRows[] = [
            'ptclass_name' => safe_utf8_er($row['ptclass_name']),
            'total_visits' => (int)$row['total_visits'],
            'total_patients' => (int)$row['total_patients'],
        ];
    }

    echo json_encode([
        'status' => 'success',
        'generated_at' => date('Y-m-d H:i:s'),
        'fiscal_year' => $fy['year'],
        'fiscal_range' => [
            'start' => $fy['start'],
            'end' => $fy['end'],
        ],
        'kpi' => [
            'er_today' => $erToday,
            'er_fy' => $erTotal,
            'unique_patients' => $uniquePatients,
            'new_cases' => $newCases,
            'avg_per_day' => $avgPerDay,
            'refer_today' => $referToday,
            'refer_fy' => $referFy,
            'admit_today' => $admitToday,
            'admit_fy' => $admitFy,
            'death_today' => $deathToday,
            'death_fy' => $deathFy,
            'death_result_name' => $deathResultName,
            'death_rate' => $erTotal > 0 ? round(($deathFy / $erTotal) * 100, 2) : 0,
            'refer_rate' => $erTotal > 0 ? round(($referFy / $erTotal) * 100, 2) : 0,
            'admit_rate' => $erTotal > 0 ? round(($admitFy / $erTotal) * 100, 2) : 0,
        ],
        'charts' => [
            'monthly' => [
                'labels' => $monthLabels,
                'er' => $monthEr,
                'refer' => $monthRefer,
                'admit' => $monthAdmit,
            ],
            'shift' => [
                'labels' => ['เวรเช้า', 'เวรบ่าย', 'เวรดึก'],
                'data' => [
                    (int)($shift['morning'] ?? 0),
                    (int)($shift['afternoon'] ?? 0),
                    (int)($shift['night'] ?? 0),
                ],
            ],
            'hourly_today' => [
                'labels' => array_map(fn($hour) => sprintf('%02d:00', $hour), range(0, 23)),
                'data' => array_values($hourMap),
            ],
            'payer' => [
                'labels' => $payerLabels,
                'data' => $payerData,
            ],
            'outcome' => [
                'labels' => $outcomeLabels,
                'data' => $outcomeData,
            ],
        ],
        'top_diagnosis' => $diagRows,
        'death_diagnosis' => $deathDiagnosisRows,
        'today_payer_summary' => $todayPayerRows,
        'recent_today' => $todayPayerRows,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(200);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
