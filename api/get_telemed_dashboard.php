<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/his_database.php';

header('Content-Type: application/json; charset=utf-8');

function telemed_dashboard_error(string $message, int $status = 500): never
{
    http_response_code($status);
    echo json_encode([
        'status' => 'error',
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function telemed_dashboard_app_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $serverIps = ['192.168.111.240'];
    $currentHost = $_SERVER['HTTP_HOST'] ?? '';
    $currentServerAddr = $_SERVER['SERVER_ADDR'] ?? ($_SERVER['LOCAL_ADDR'] ?? '');
    $machineIp = gethostbyname(gethostname());
    $isServer = in_array($currentServerAddr, $serverIps, true)
        || in_array($machineIp, $serverIps, true)
        || strpos($currentHost, '192.168.111.240') === 0
        || (strpos($currentHost, 'localhost') === 0 && in_array($currentServerAddr, $serverIps, true));

    $host = $isServer ? 'localhost' : '192.168.111.240';
    $user = $isServer ? 'webtomdb' : 'tomwebdbnavicat';
    $pass = $isServer ? '@TOM$DataBase10832' : '@TOM$NavicatDB10832';

    try {
        $pdo = new PDO(
            "mysql:host={$host};dbname=pdhtawan;charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        $pdo->exec("SET time_zone = '+07:00'");
    } catch (PDOException $e) {
        telemed_dashboard_error('ไม่สามารถเชื่อมต่อฐานข้อมูล Telemed ได้: ' . $e->getMessage());
    }

    return $pdo;
}

function telemed_current_fiscal_context(): array
{
    $today = new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok'));
    $year = (int)$today->format('Y');
    $month = (int)$today->format('n');

    if ($month >= 10) {
        $start = sprintf('%04d-10-01', $year);
        $end = sprintf('%04d-09-30', $year + 1);
        $fiscalYear = $year + 1 + 543;
    } else {
        $start = sprintf('%04d-10-01', $year - 1);
        $end = sprintf('%04d-09-30', $year);
        $fiscalYear = $year + 543;
    }

    return [
        'today' => $today->format('Y-m-d'),
        'start' => $start,
        'end' => $end,
        'year' => $fiscalYear,
    ];
}

function telemed_decode(?string $value): string
{
    $text = trim((string)$value);
    if ($text === '') {
        return '-';
    }

    $decoded = @iconv('TIS-620', 'UTF-8//IGNORE', $text);
    if ($decoded !== false && $decoded !== '') {
        return trim($decoded);
    }

    $decoded = @iconv('Windows-874', 'UTF-8//IGNORE', $text);
    if ($decoded !== false && $decoded !== '') {
        return trim($decoded);
    }

    return $text;
}

function telemed_has_table(PDO $pdo, string $table): bool
{
    $quotedTable = $pdo->quote($table);
    $stmt = $pdo->query("SHOW TABLES LIKE {$quotedTable}");
    return (bool)$stmt->fetchColumn();
}

function telemed_has_column(PDO $pdo, string $table, string $column): bool
{
    $quotedColumn = $pdo->quote($column);
    $safeTable = str_replace('`', '``', $table);
    $stmt = $pdo->query("SHOW COLUMNS FROM `{$safeTable}` LIKE {$quotedColumn}");
    return (bool)$stmt->fetchColumn();
}

try {
    $fy = telemed_current_fiscal_context();
    $appPdo = telemed_dashboard_app_pdo();

    $statusTable = telemed_has_table($appPdo, 'telemed_patient_status');
    $deliveryTable = telemed_has_table($appPdo, 'telemed_delivery');
    $trackingTable = telemed_has_table($appPdo, 'telemed_tracking');
    $pickupSelfColumn = $deliveryTable && telemed_has_column($appPdo, 'telemed_delivery', 'pickup_self');

    $telemedWhere = "
        FROM opd.opd o
        INNER JOIN hos.codeinhos c
            ON (o.comein = c.code OR CONCAT('IN', o.comein) = c.code)
        WHERE c.code = 'IN10'
    ";

    $stmtKpi = $his->prepare("
        SELECT
            COUNT(CASE WHEN o.regdate = :today_visits THEN 1 END) AS visits_today,
            COUNT(DISTINCT CASE WHEN o.regdate = :today_patients THEN o.hn END) AS patients_today,
            COUNT(CASE WHEN o.regdate BETWEEN :start_visits AND :end_visits THEN 1 END) AS visits_fy,
            COUNT(DISTINCT CASE WHEN o.regdate BETWEEN :start_patients AND :end_patients THEN o.hn END) AS patients_fy,
            COUNT(*) AS visits_all_time
        {$telemedWhere}
    ");
    $stmtKpi->execute([
        ':today_visits' => $fy['today'],
        ':today_patients' => $fy['today'],
        ':start_visits' => $fy['start'],
        ':end_visits' => $fy['end'],
        ':start_patients' => $fy['start'],
        ':end_patients' => $fy['end'],
    ]);
    $kpi = $stmtKpi->fetch() ?: [];

    $stmtMonthly = $his->prepare("
        SELECT
            DATE_FORMAT(o.regdate, '%Y-%m') AS ym,
            COUNT(*) AS total_visits,
            COUNT(DISTINCT o.hn) AS total_patients
        {$telemedWhere}
          AND o.regdate BETWEEN :start AND :end
        GROUP BY DATE_FORMAT(o.regdate, '%Y-%m')
        ORDER BY ym ASC
    ");
    $stmtMonthly->execute([
        ':start' => $fy['start'],
        ':end' => $fy['end'],
    ]);
    $monthlyRows = $stmtMonthly->fetchAll();

    $period = new DatePeriod(
        new DateTimeImmutable($fy['start']),
        new DateInterval('P1M'),
        (new DateTimeImmutable($fy['end']))->modify('first day of next month')
    );
    $monthlyMap = [];
    foreach ($monthlyRows as $row) {
        $monthlyMap[$row['ym']] = $row;
    }
    $monthlyLabels = [];
    $monthlyVisits = [];
    $monthlyPatients = [];
    foreach ($period as $monthDate) {
        $ym = $monthDate->format('Y-m');
        $monthlyLabels[] = $monthDate->format('M y');
        $monthlyVisits[] = (int)($monthlyMap[$ym]['total_visits'] ?? 0);
        $monthlyPatients[] = (int)($monthlyMap[$ym]['total_patients'] ?? 0);
    }

    $stmtClinic = $his->prepare("
        SELECT
            o.clinic AS clinic_code,
            MAX(cl.NAME) AS clinic_name_raw,
            COUNT(*) AS total_visits,
            COUNT(DISTINCT o.hn) AS total_patients
        FROM opd.opd o
        INNER JOIN hos.codeinhos c
            ON (o.comein = c.code OR CONCAT('IN', o.comein) = c.code)
        LEFT JOIN hos.clinic cl ON cl.code = o.clinic
        WHERE c.code = 'IN10'
          AND o.regdate BETWEEN :start AND :end
        GROUP BY o.clinic
        ORDER BY total_visits DESC, total_patients DESC
        LIMIT 10
    ");
    $stmtClinic->execute([
        ':start' => $fy['start'],
        ':end' => $fy['end'],
    ]);
    $clinicRows = array_map(static function (array $row): array {
        $row['clinic_name'] = telemed_decode($row['clinic_name_raw'] ?? '');
        unset($row['clinic_name_raw']);
        return $row;
    }, $stmtClinic->fetchAll());

    $statusSummary = [
        'labels' => [],
        'data' => [],
    ];
    if ($statusTable) {
        $stmtStatus = $appPdo->prepare("
            SELECT
                CASE
                    WHEN s.status_type = 'completed' THEN 'จบเคส'
                    WHEN s.status_type = 'discharge' THEN 'จำหน่าย/สิ้นสุดการดูแล'
                    WHEN s.status_type = 'home_delivery' THEN 'จัดส่งยา'
                    WHEN s.status_type = 'self_pickup' THEN 'มารับยาเอง'
                    WHEN s.status_type = 'other' THEN CONCAT('อื่น ๆ', IFNULL(CONCAT(' - ', NULLIF(TRIM(s.status_detail), '')), ''))
                    ELSE s.status_type
                END AS status_label,
                COUNT(*) AS total
            FROM telemed_patient_status s
            WHERE s.regdate BETWEEN :start AND :end
            GROUP BY status_label
            ORDER BY total DESC
            LIMIT 8
        ");
        $stmtStatus->execute([
            ':start' => $fy['start'],
            ':end' => $fy['end'],
        ]);
        $statusRows = $stmtStatus->fetchAll();
        $statusSummary = [
            'labels' => array_map(static fn(array $row): string => (string)$row['status_label'], $statusRows),
            'data' => array_map(static fn(array $row): int => (int)$row['total'], $statusRows),
        ];
    }

    $trackingSummary = [
        'labels' => [],
        'data' => [],
    ];
    if ($trackingTable) {
        $stmtTracking = $appPdo->prepare("
            SELECT
                COALESCE(NULLIF(TRIM(followup_status), ''), 'ไม่ระบุสถานะ') AS status_label,
                COUNT(*) AS total
            FROM telemed_tracking
            WHERE regdate BETWEEN :start AND :end
            GROUP BY status_label
            ORDER BY total DESC
            LIMIT 8
        ");
        $stmtTracking->execute([
            ':start' => $fy['start'],
            ':end' => $fy['end'],
        ]);
        $trackingRows = $stmtTracking->fetchAll();
        $trackingSummary = [
            'labels' => array_map(static fn(array $row): string => (string)$row['status_label'], $trackingRows),
            'data' => array_map(static fn(array $row): int => (int)$row['total'], $trackingRows),
        ];
    }

    $ops = [
        'with_status' => 0,
        'home_delivery' => 0,
        'self_pickup' => 0,
        'delivery_address' => 0,
        'tracking_wait' => 0,
        'tracking_sent' => 0,
        'tracking_received' => 0,
    ];

    if ($statusTable) {
        $stmtOpsStatus = $appPdo->prepare("
            SELECT COUNT(*) AS total
            FROM telemed_patient_status
            WHERE regdate BETWEEN :start AND :end
        ");
        $stmtOpsStatus->execute([
            ':start' => $fy['start'],
            ':end' => $fy['end'],
        ]);
        $ops['with_status'] = (int)$stmtOpsStatus->fetchColumn();

        $stmtPickup = $appPdo->prepare("
            SELECT
                COUNT(CASE WHEN status_type = 'home_delivery' THEN 1 END) AS home_delivery,
                COUNT(CASE WHEN status_type = 'self_pickup' THEN 1 END) AS self_pickup
            FROM telemed_patient_status
            WHERE regdate BETWEEN :start AND :end
        ");
        $stmtPickup->execute([
            ':start' => $fy['start'],
            ':end' => $fy['end'],
        ]);
        $pickupRow = $stmtPickup->fetch() ?: [];
        $ops['home_delivery'] = (int)($pickupRow['home_delivery'] ?? 0);
        $ops['self_pickup'] = (int)($pickupRow['self_pickup'] ?? 0);
    }

    if ($deliveryTable) {
        $pickupExpr = $pickupSelfColumn ? 'COALESCE(d.pickup_self, 0)' : '0';
        $stmtDeliveryOps = $appPdo->query("
            SELECT
                COUNT(CASE WHEN {$pickupExpr} = 1 THEN 1 END) AS self_pickup,
                COUNT(CASE WHEN {$pickupExpr} = 0 AND NULLIF(TRIM(COALESCE(d.address, '')), '') IS NOT NULL THEN 1 END) AS delivery_address
            FROM telemed_delivery d
        ");
        $deliveryRow = $stmtDeliveryOps->fetch() ?: [];
        $ops['delivery_address'] = (int)($deliveryRow['delivery_address'] ?? 0);
        if ($ops['self_pickup'] === 0) {
            $ops['self_pickup'] = (int)($deliveryRow['self_pickup'] ?? 0);
        }
    }

    if ($trackingTable) {
        $stmtTrackingOps = $appPdo->prepare("
            SELECT
                COUNT(CASE WHEN followup_status LIKE '%รอ%' THEN 1 END) AS tracking_wait,
                COUNT(CASE WHEN followup_status LIKE '%ส่ง%' THEN 1 END) AS tracking_sent,
                COUNT(CASE WHEN followup_status LIKE '%ได้รับ%' THEN 1 END) AS tracking_received
            FROM telemed_tracking
            WHERE regdate BETWEEN :start AND :end
        ");
        $stmtTrackingOps->execute([
            ':start' => $fy['start'],
            ':end' => $fy['end'],
        ]);
        $trackingOps = $stmtTrackingOps->fetch() ?: [];
        $ops['tracking_wait'] = (int)($trackingOps['tracking_wait'] ?? 0);
        $ops['tracking_sent'] = (int)($trackingOps['tracking_sent'] ?? 0);
        $ops['tracking_received'] = (int)($trackingOps['tracking_received'] ?? 0);
    }

    $stmtLatest = $his->prepare("
        SELECT
            o.hn,
            o.fullname,
            o.regdate,
            o.timereg,
            o.clinic AS clinic_code,
            MAX(cl.NAME) AS clinic_name_raw
        FROM opd.opd o
        INNER JOIN hos.codeinhos c
            ON (o.comein = c.code OR CONCAT('IN', o.comein) = c.code)
        LEFT JOIN hos.clinic cl ON cl.code = o.clinic
        WHERE c.code = 'IN10'
          AND o.regdate BETWEEN :start AND :end
        GROUP BY o.hn, o.fullname, o.regdate, o.timereg, o.clinic
        ORDER BY o.regdate DESC, o.timereg DESC
        LIMIT 12
    ");
    $stmtLatest->execute([
        ':start' => $fy['start'],
        ':end' => $fy['end'],
    ]);
    $latestRows = array_map(static function (array $row): array {
        return [
            'hn' => (string)$row['hn'],
            'fullname' => telemed_decode($row['fullname'] ?? ''),
            'regdate' => (string)$row['regdate'],
            'timereg' => (string)$row['timereg'],
            'clinic_code' => (string)$row['clinic_code'],
            'clinic_name' => telemed_decode($row['clinic_name_raw'] ?? ''),
        ];
    }, $stmtLatest->fetchAll());

    $daysElapsed = max((int)((new DateTimeImmutable($fy['today']))->diff(new DateTimeImmutable($fy['start']))->days) + 1, 1);

    echo json_encode([
        'status' => 'success',
        'fiscal_year' => $fy['year'],
        'fiscal_range' => [
            'start' => $fy['start'],
            'end' => $fy['end'],
            'today' => $fy['today'],
        ],
        'kpi' => [
            'visits_today' => (int)($kpi['visits_today'] ?? 0),
            'patients_today' => (int)($kpi['patients_today'] ?? 0),
            'visits_fy' => (int)($kpi['visits_fy'] ?? 0),
            'patients_fy' => (int)($kpi['patients_fy'] ?? 0),
            'visits_all_time' => (int)($kpi['visits_all_time'] ?? 0),
            'avg_per_day' => round(((int)($kpi['visits_fy'] ?? 0)) / $daysElapsed, 1),
        ],
        'operations' => $ops,
        'charts' => [
            'monthly' => [
                'labels' => $monthlyLabels,
                'visits' => $monthlyVisits,
                'patients' => $monthlyPatients,
            ],
            'status' => $statusSummary,
            'tracking' => $trackingSummary,
        ],
        'clinic_ranking' => array_map(static function (array $row): array {
            return [
                'clinic_code' => (string)$row['clinic_code'],
                'clinic_name' => (string)$row['clinic_name'],
                'total_visits' => (int)$row['total_visits'],
                'total_patients' => (int)$row['total_patients'],
            ];
        }, $clinicRows),
        'latest_visits' => $latestRows,
        'data_sources' => [
            'his' => 'opd.opd + hos.codeinhos (IN10)',
            'telemed_app' => 'pdhtawan.telemed_patient_status / telemed_delivery / telemed_tracking',
            'has_status_table' => $statusTable,
            'has_delivery_table' => $deliveryTable,
            'has_tracking_table' => $trackingTable,
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    telemed_dashboard_error($e->getMessage());
}
