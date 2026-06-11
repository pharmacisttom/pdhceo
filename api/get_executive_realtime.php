<?php
declare(strict_types=1);

ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

function safe_utf8($value): string
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

function executive_is_icu_ward($wardName, $wardCode): bool
{
    $name = safe_utf8($wardName ?? '');
    $code = (string)($wardCode ?? '');
    return stripos($name, 'ICU') !== false
        || stripos($code, 'ICU') !== false
        || strpos($name, 'ไอซียู') !== false
        || strpos($name, 'กึ่งวิกฤต') !== false;
}

function executive_bed_split(PDO $his, ?string $start = null, ?string $end = null, bool $activeOnly = false): array
{
    $generalBeds = 93;
    $icuBeds = 9;
    if ($activeOnly) {
        $stmt = $his->query("
            SELECT COALESCE(r.roomname, i.now_ward, '') AS ward_name,
                   COALESCE(i.now_ward, '') AS ward_code,
                   COUNT(DISTINCT i.an) AS usage_value
            FROM ipd.ipd i
            LEFT JOIN (SELECT roomcode, MAX(roomname) AS roomname FROM hos.roomno GROUP BY roomcode) r
                ON i.now_ward = r.roomcode
            WHERE i.datedsc IS NULL OR i.datedsc = '0000-00-00'
            GROUP BY i.now_ward, r.roomname
        ");
        $days = 1;
    } else {
        $stmt = $his->prepare("
            SELECT COALESCE(r.roomname, i.now_ward, '') AS ward_name,
                   COALESCE(i.now_ward, '') AS ward_code,
                   SUM(
                       CASE
                           WHEN i.dateadm IS NULL OR i.dateadm = '0000-00-00' THEN 0
                           WHEN i.datedsc IS NULL OR i.datedsc = '0000-00-00' THEN GREATEST(DATEDIFF(:end_calc, i.dateadm), 1)
                           ELSE GREATEST(DATEDIFF(LEAST(i.datedsc, :end_limit), i.dateadm), 1)
                       END
                   ) AS usage_value
            FROM ipd.ipd i
            LEFT JOIN (SELECT roomcode, MAX(roomname) AS roomname FROM hos.roomno GROUP BY roomcode) r
                ON i.now_ward = r.roomcode
            WHERE i.dateadm BETWEEN :start_date AND :end_date
            GROUP BY i.now_ward, r.roomname
        ");
        $stmt->execute([
            ':start_date' => $start,
            ':end_date' => $end,
            ':end_calc' => $end,
            ':end_limit' => $end,
        ]);
        $days = (new DateTimeImmutable((string)$start))->diff(new DateTimeImmutable((string)$end))->days + 1;
    }

    $generalUsage = 0;
    $icuUsage = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (executive_is_icu_ward($row['ward_name'] ?? '', $row['ward_code'] ?? '')) {
            $icuUsage += (int)($row['usage_value'] ?? 0);
        } else {
            $generalUsage += (int)($row['usage_value'] ?? 0);
        }
    }

    $build = static function (int $beds, int $usage, int $days, bool $active): array {
        return [
            'beds' => $beds,
            $active ? 'active' : 'patient_days' => $usage,
            'available' => $active ? max($beds - $usage, 0) : null,
            'overcrowd' => $active ? max($usage - $beds, 0) : null,
            'occ_rate' => $beds > 0 && $days > 0 ? round(($usage / ($beds * $days)) * 100, 2) : 0,
        ];
    };

    return [
        'general' => $build($generalBeds, $generalUsage, $days, $activeOnly),
        'icu' => $build($icuBeds, $icuUsage, $days, $activeOnly),
        'total' => $build($generalBeds + $icuBeds, $generalUsage + $icuUsage, $days, $activeOnly),
    ];
}

function fiscal_context(): array
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

    $totalBeds = 102;
    $fy = fiscal_context();
    $daysElapsed = (new DateTimeImmutable($fy['start']))->diff(new DateTimeImmutable($fy['end']))->days + 1;

    $stmt = $his->prepare('SELECT COUNT(*) FROM opd.opd WHERE regdate BETWEEN :start AND :end');
    $stmt->execute([':start' => $fy['start'], ':end' => $fy['end']]);
    $opdFy = (int)$stmt->fetchColumn();

    $stmt = $his->prepare('SELECT COUNT(*) FROM opd.opd WHERE regdate = :today');
    $stmt->execute([':today' => $fy['today']]);
    $opdToday = (int)$stmt->fetchColumn();

    $stmt = $his->prepare("
        SELECT
            COUNT(*) AS ipd_total,
            SUM(
                CASE
                    WHEN dateadm IS NULL OR dateadm = '0000-00-00' THEN 0
                    WHEN datedsc IS NULL OR datedsc = '0000-00-00' THEN GREATEST(DATEDIFF(CURDATE(), dateadm), 1)
                    ELSE GREATEST(DATEDIFF(datedsc, dateadm), 1)
                END
            ) AS patient_days,
            SUM(CASE WHEN adjrw IS NULL OR adjrw = '' THEN 0 ELSE CAST(adjrw AS DECIMAL(10,4)) END) AS sum_adjrw
        FROM ipd.ipd
        WHERE dateadm BETWEEN :start AND :end
    ");
    $stmt->execute([':start' => $fy['start'], ':end' => $fy['end']]);
    $ipdSummary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $ipdFy = (int)($ipdSummary['ipd_total'] ?? 0);
    $patientDays = (int)($ipdSummary['patient_days'] ?? 0);
    $sumAdjrw = (float)($ipdSummary['sum_adjrw'] ?? 0);
    $cmi = $ipdFy > 0 ? round($sumAdjrw / $ipdFy, 4) : 0;

    $currentBedSplit = executive_bed_split($his, null, null, true);
    $fiscalBedSplit = executive_bed_split($his, $fy['start'], $fy['end'], false);
    $activeIpd = (int)$currentBedSplit['total']['active'];
    $availableBeds = max($totalBeds - $activeIpd, 0);
    $currentOccRate = (float)$currentBedSplit['total']['occ_rate'];
    $fyOccRate = (float)$fiscalBedSplit['total']['occ_rate'];

    $stmt = $his->prepare("
        SELECT COUNT(*)
        FROM ipd.ipd
        WHERE datedsc IS NOT NULL
        AND datedsc != '0000-00-00'
        AND datedsc BETWEEN :start AND :end
    ");
    $stmt->execute([':start' => $fy['start'], ':end' => $fy['end']]);
    $dischargeFy = (int)$stmt->fetchColumn();
    $alos = $dischargeFy > 0 ? round($patientDays / $dischargeFy, 2) : 0;
    $averageDailyOpd = $daysElapsed > 0 ? round($opdFy / $daysElapsed, 1) : 0;
    $dischargeToAdmitRate = $ipdFy > 0 ? round(($dischargeFy / $ipdFy) * 100, 2) : 0;

    $trialBalanceMonth = null;
    $trialBalanceRows = 0;
    try {
        require_once __DIR__ . '/../config/database.php';
        $trial = $pdo->query("
            SELECT month_year, row_count
            FROM finance_trial_balance_imports
            ORDER BY month_year DESC
            LIMIT 1
        ")->fetch(PDO::FETCH_ASSOC) ?: [];
        $trialBalanceMonth = $trial['month_year'] ?? null;
        $trialBalanceRows = (int)($trial['row_count'] ?? 0);
    } catch (Throwable $ignored) {
        $trialBalanceMonth = null;
    }

    $managementSignals = [];
    $addSignal = static function (string $level, string $title, string $detail) use (&$managementSignals): void {
        $managementSignals[] = compact('level', 'title', 'detail');
    };
    if ((int)$currentBedSplit['icu']['overcrowd'] > 0 || (float)$currentBedSplit['icu']['occ_rate'] >= 85) {
        $addSignal('danger', 'เฝ้าระวังเตียง ICU', sprintf(
            'ใช้งาน %d/%d เตียง อัตราครองเตียง %.2f%%',
            $currentBedSplit['icu']['active'],
            $currentBedSplit['icu']['beds'],
            $currentBedSplit['icu']['occ_rate']
        ));
    }
    if ((int)$currentBedSplit['general']['overcrowd'] > 0 || (float)$currentBedSplit['general']['occ_rate'] >= 85) {
        $addSignal('warning', 'เฝ้าระวังเตียงทั่วไป', sprintf(
            'ใช้งาน %d/%d เตียง อัตราครองเตียง %.2f%%',
            $currentBedSplit['general']['active'],
            $currentBedSplit['general']['beds'],
            $currentBedSplit['general']['occ_rate']
        ));
    }
    if ($alos >= 7) {
        $addSignal('warning', 'วันนอนเฉลี่ยสูง', "ALOS {$alos} วัน ควรทบทวนผู้ป่วยที่มีวันนอนยาวและแผนจำหน่าย");
    }
    if (!$trialBalanceMonth || $trialBalanceMonth < date('Y-m', strtotime('-1 month'))) {
        $addSignal('warning', 'ข้อมูลงบทดลองยังไม่เป็นปัจจุบัน', $trialBalanceMonth
            ? "งบทดลองล่าสุด {$trialBalanceMonth} จำนวน {$trialBalanceRows} บัญชี"
            : 'ยังไม่พบงบทดลองในระบบ');
    }
    if (!$managementSignals) {
        $addSignal('success', 'สถานการณ์บริการอยู่ในเกณฑ์ติดตามปกติ', 'ยังไม่พบสัญญาณเตือนสำคัญจากเตียง วันนอน และความสดของข้อมูล');
    }

    $trendLabels = [];
    $trendOpd = [];
    $trendIpd = [];
    $trendDischarge = [];
    $thaiMonths = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $cursor = new DateTimeImmutable(substr($fy['start'], 0, 7) . '-01');
    $endMonth = new DateTimeImmutable(substr($fy['end'], 0, 7) . '-01');

    while ($cursor <= $endMonth) {
        $monthStart = $cursor->format('Y-m-01');
        $monthEnd = min($cursor->format('Y-m-t'), $fy['end']);
        $trendLabels[] = $thaiMonths[((int)$cursor->format('n')) - 1] . ' ' . (((int)$cursor->format('Y')) + 543);

        $stmt = $his->prepare('SELECT COUNT(*) FROM opd.opd WHERE regdate BETWEEN :start AND :end');
        $stmt->execute([':start' => $monthStart, ':end' => $monthEnd]);
        $trendOpd[] = (int)$stmt->fetchColumn();

        $stmt = $his->prepare('SELECT COUNT(*) FROM ipd.ipd WHERE dateadm BETWEEN :start AND :end');
        $stmt->execute([':start' => $monthStart, ':end' => $monthEnd]);
        $trendIpd[] = (int)$stmt->fetchColumn();

        $stmt = $his->prepare("
            SELECT COUNT(*)
            FROM ipd.ipd
            WHERE datedsc IS NOT NULL
            AND datedsc != '0000-00-00'
            AND datedsc BETWEEN :start AND :end
        ");
        $stmt->execute([':start' => $monthStart, ':end' => $monthEnd]);
        $trendDischarge[] = (int)$stmt->fetchColumn();

        $cursor = $cursor->modify('+1 month');
    }

    $quarterRows = [];
    $quarterLabels = [];
    $quarterOpd = [];
    $quarterIpd = [];
    $quarterDischarge = [];
    $quarterOccRate = [];
    $quarterAdjrw = [];
    $quarterDefinitions = [
        ['label' => 'Q1', 'start' => substr($fy['start'], 0, 4) . '-10-01', 'end' => substr($fy['start'], 0, 4) . '-12-31'],
        ['label' => 'Q2', 'start' => substr($fy['end'], 0, 4) . '-01-01', 'end' => substr($fy['end'], 0, 4) . '-03-31'],
        ['label' => 'Q3', 'start' => substr($fy['end'], 0, 4) . '-04-01', 'end' => substr($fy['end'], 0, 4) . '-06-30'],
        ['label' => 'Q4', 'start' => substr($fy['end'], 0, 4) . '-07-01', 'end' => substr($fy['end'], 0, 4) . '-09-30'],
    ];

    foreach ($quarterDefinitions as $quarter) {
        $quarterStart = $quarter['start'];
        $quarterEnd = min($quarter['end'], $fy['end']);
        $hasStarted = $quarterStart <= $fy['end'];
        $daysInQuarter = $hasStarted
            ? (new DateTimeImmutable($quarterStart))->diff(new DateTimeImmutable($quarterEnd))->days + 1
            : 0;

        $opdQuarter = 0;
        $ipdQuarter = 0;
        $dischargeQuarter = 0;
        $patientDaysQuarter = 0;
        $adjrwQuarter = 0.0;

        if ($hasStarted) {
            $stmt = $his->prepare('SELECT COUNT(*) FROM opd.opd WHERE regdate BETWEEN :start AND :end');
            $stmt->execute([':start' => $quarterStart, ':end' => $quarterEnd]);
            $opdQuarter = (int)$stmt->fetchColumn();

            $stmt = $his->prepare("
                SELECT
                    COUNT(*) AS ipd_total,
                    SUM(
                        CASE
                            WHEN dateadm IS NULL OR dateadm = '0000-00-00' THEN 0
                            WHEN datedsc IS NULL OR datedsc = '0000-00-00' THEN GREATEST(DATEDIFF(:end_calc, dateadm), 1)
                            ELSE GREATEST(DATEDIFF(LEAST(datedsc, :end_limit), dateadm), 1)
                        END
                    ) AS patient_days,
                    SUM(CASE WHEN adjrw IS NULL OR adjrw = '' THEN 0 ELSE CAST(adjrw AS DECIMAL(10,4)) END) AS sum_adjrw
                FROM ipd.ipd
                WHERE dateadm BETWEEN :start AND :end_where
            ");
            $stmt->execute([
                ':start' => $quarterStart,
                ':end_calc' => $quarterEnd,
                ':end_limit' => $quarterEnd,
                ':end_where' => $quarterEnd,
            ]);
            $quarterIpdSummary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $ipdQuarter = (int)($quarterIpdSummary['ipd_total'] ?? 0);
            $patientDaysQuarter = (int)($quarterIpdSummary['patient_days'] ?? 0);
            $adjrwQuarter = (float)($quarterIpdSummary['sum_adjrw'] ?? 0);

            $stmt = $his->prepare("
                SELECT COUNT(*)
                FROM ipd.ipd
                WHERE datedsc IS NOT NULL
                AND datedsc != '0000-00-00'
                AND datedsc BETWEEN :start AND :end
            ");
            $stmt->execute([':start' => $quarterStart, ':end' => $quarterEnd]);
            $dischargeQuarter = (int)$stmt->fetchColumn();
        }

        $occRateQuarter = ($totalBeds > 0 && $daysInQuarter > 0)
            ? round(($patientDaysQuarter / ($totalBeds * $daysInQuarter)) * 100, 2)
            : 0;

        $quarterLabels[] = $quarter['label'];
        $quarterOpd[] = $opdQuarter;
        $quarterIpd[] = $ipdQuarter;
        $quarterDischarge[] = $dischargeQuarter;
        $quarterOccRate[] = $occRateQuarter;
        $quarterAdjrw[] = round($adjrwQuarter, 4);
        $quarterRows[] = [
            'label' => $quarter['label'],
            'start' => $quarterStart,
            'end' => $hasStarted ? $quarterEnd : $quarter['end'],
            'opd' => $opdQuarter,
            'ipd' => $ipdQuarter,
            'discharge' => $dischargeQuarter,
            'patient_days' => $patientDaysQuarter,
            'occ_rate' => $occRateQuarter,
            'sum_adjrw' => round($adjrwQuarter, 4),
        ];
    }

    $stmt = $his->prepare("
        SELECT i.`Name` AS label, COUNT(*) AS total
        FROM opd.opd o
        LEFT JOIN hos.insclasses i ON o.ptclass = i.code
        WHERE o.regdate BETWEEN :start AND :end
        GROUP BY o.ptclass, i.`Name`
        ORDER BY total DESC
        LIMIT 6
    ");
    $stmt->execute([':start' => $fy['start'], ':end' => $fy['end']]);
    $payerLabels = [];
    $payerValues = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $payerLabels[] = safe_utf8($row['label']);
        $payerValues[] = (int)$row['total'];
    }

    $stmt = $his->prepare("
        SELECT c.`Name` AS label, COUNT(*) AS total
        FROM opd.opd o
        LEFT JOIN hos.clinic c ON o.clinic = c.code
        WHERE o.regdate BETWEEN :start AND :end
        GROUP BY o.clinic, c.`Name`
        ORDER BY total DESC
        LIMIT 8
    ");
    $stmt->execute([':start' => $fy['start'], ':end' => $fy['end']]);
    $clinicRows = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $clinicRows[] = [
            'label' => safe_utf8($row['label']),
            'total' => (int)$row['total'],
        ];
    }

    echo json_encode([
        'status' => 'success',
        'generated_at' => date('Y-m-d H:i:s'),
        'data_freshness' => [
            'his_as_of' => $fy['today'],
            'trial_balance_month' => $trialBalanceMonth,
            'trial_balance_rows' => $trialBalanceRows,
        ],
        'fiscal_year' => $fy['year'],
        'fiscal_range' => [
            'start' => $fy['start'],
            'end' => $fy['end'],
        ],
        'kpi' => [
            'opd_today' => $opdToday,
            'opd_fy' => $opdFy,
            'ipd_fy' => $ipdFy,
            'active_ipd' => $activeIpd,
            'available_beds' => $availableBeds,
            'total_beds' => $totalBeds,
            'current_occ_rate' => $currentOccRate,
            'fy_occ_rate' => $fyOccRate,
            'discharge_fy' => $dischargeFy,
            'patient_days' => $patientDays,
            'sum_adjrw' => round($sumAdjrw, 4),
            'cmi' => $cmi,
            'alos' => $alos,
            'average_daily_opd' => $averageDailyOpd,
            'discharge_to_admit_rate' => $dischargeToAdmitRate,
        ],
        'bed_split' => [
            'current' => $currentBedSplit,
            'fiscal_year' => $fiscalBedSplit,
        ],
        'management_signals' => $managementSignals,
        'charts' => [
            'service_trend' => [
                'labels' => $trendLabels,
                'opd' => $trendOpd,
                'ipd' => $trendIpd,
                'discharge' => $trendDischarge,
            ],
            'quarters' => [
                'labels' => $quarterLabels,
                'opd' => $quarterOpd,
                'ipd' => $quarterIpd,
                'discharge' => $quarterDischarge,
                'occ_rate' => $quarterOccRate,
                'sum_adjrw' => $quarterAdjrw,
                'rows' => $quarterRows,
            ],
            'bed' => [
                'labels' => ['ครองเตียง', 'เตียงว่าง'],
                'data' => [$activeIpd, $availableBeds],
            ],
            'payer' => [
                'labels' => $payerLabels,
                'data' => $payerValues,
            ],
            'clinics' => $clinicRows,
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
