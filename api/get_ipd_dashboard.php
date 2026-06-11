<?php
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/his_database.php';

function safe_utf8($str) {
    if ($str === null) return "ไม่ระบุ";
    if (preg_match('//u', (string)$str)) return (string)$str;
    $utf8 = @iconv("TIS-620", "UTF-8//IGNORE", $str);
    return $utf8 !== false ? $utf8 : $str;
}

function ipd_is_icu_ward($wardName, $wardCode): bool
{
    $name = safe_utf8($wardName ?? '');
    $code = (string)($wardCode ?? '');
    return stripos($name, 'ICU') !== false
        || stripos($code, 'ICU') !== false
        || strpos($name, 'ไอซียู') !== false
        || strpos($name, 'กึ่งวิกฤต') !== false;
}

function ipd_split_bed_usage(PDO $his, ?string $startDate = null, ?string $endDate = null, bool $activeOnly = false): array
{
    $generalBeds = 93;
    $icuBeds = 9;
    if ($activeOnly) {
        $sql = "
            SELECT COALESCE(r.roomname, i.now_ward, '') AS ward_name,
                   COALESCE(i.now_ward, '') AS ward_code,
                   COUNT(DISTINCT i.an) AS usage_value
            FROM ipd.ipd i
            LEFT JOIN (SELECT roomcode, MAX(roomname) AS roomname FROM hos.roomno GROUP BY roomcode) r
                ON i.now_ward = r.roomcode
            WHERE i.datedsc IS NULL OR i.datedsc = '0000-00-00'
            GROUP BY i.now_ward, r.roomname
        ";
        $stmt = $his->query($sql);
        $periodDays = 1;
    } else {
        $sql = "
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
        ";
        $stmt = $his->prepare($sql);
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate,
            ':end_calc' => $endDate,
            ':end_limit' => $endDate,
        ]);
        $periodDays = (new DateTimeImmutable((string)$startDate))->diff(new DateTimeImmutable((string)$endDate))->days + 1;
    }

    $generalUsage = 0;
    $icuUsage = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (ipd_is_icu_ward($row['ward_name'] ?? '', $row['ward_code'] ?? '')) {
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
        'general' => $build($generalBeds, $generalUsage, $periodDays, $activeOnly),
        'icu' => $build($icuBeds, $icuUsage, $periodDays, $activeOnly),
        'total' => $build($generalBeds + $icuBeds, $generalUsage + $icuUsage, $periodDays, $activeOnly),
        'period_days' => $periodDays,
    ];
}

try {
    $general_beds = 93;
    $icu_beds = 9;
    $total_beds = $general_beds + $icu_beds;
    $today = new DateTimeImmutable('today');
    $current_year = (int)$today->format('Y');
    $current_month = (int)$today->format('n');

    if ($current_month >= 10) {
        $fy_start = sprintf('%d-10-01', $current_year);
        $fy_end = sprintf('%d-09-30', $current_year + 1);
        $current_fiscal_year = $current_year + 1 + 543;
    } else {
        $fy_start = sprintf('%d-10-01', $current_year - 1);
        $fy_end = sprintf('%d-09-30', $current_year);
        $current_fiscal_year = $current_year + 543;
    }
    $today_text = $today->format('Y-m-d');
    $effective_fy_end = min($fy_end, $today_text);
    $days_elapsed = (new DateTimeImmutable($fy_start))->diff(new DateTimeImmutable($effective_fy_end))->days + 1;

    // 1. ดึงข้อมูล Real-time (สถานะเตียงปัจจุบัน)
    $sql_current = "SELECT COUNT(DISTINCT an) as active_cases FROM ipd.ipd WHERE (datedsc = '0000-00-00' OR datedsc IS NULL)";
    $stmt_curr = $his->query($sql_current);
    $curr_res = $stmt_curr->fetch(PDO::FETCH_ASSOC);
    
    $active_cases = (int)$curr_res['active_cases'];
    $available_beds = $total_beds - $active_cases;
    $display_available = $available_beds < 0 ? 0 : $available_beds; 
    $current_occ_rate = ($total_beds > 0) ? round(($active_cases / $total_beds) * 100, 2) : 0;
    $current_bed_split = ipd_split_bed_usage($his, null, null, true);

    $sql_current_cmi = "SELECT 
                SUM(CASE WHEN adjrw IS NULL OR adjrw = '' THEN 0 ELSE CAST(adjrw AS DECIMAL(10,4)) END) AS sum_adjrw
            FROM ipd.ipd
            WHERE (datedsc = '0000-00-00' OR datedsc IS NULL)";
    $stmt_current_cmi = $his->query($sql_current_cmi);
    $current_cmi_res = $stmt_current_cmi->fetch(PDO::FETCH_ASSOC);
    $sum_adjrw_current = (float)($current_cmi_res['sum_adjrw'] ?? 0);
    $cmi_current = ($active_cases > 0) ? round($sum_adjrw_current / $active_cases, 4) : 0;

    $sql_fy_current = "SELECT
                COUNT(an) AS total_admits,
                SUM(
                    CASE
                        WHEN dateadm IS NULL OR dateadm = '0000-00-00' THEN 0
                        WHEN datedsc IS NULL OR datedsc = '0000-00-00' THEN GREATEST(DATEDIFF(CURDATE(), dateadm), 1)
                        ELSE GREATEST(DATEDIFF(datedsc, dateadm), 1)
                    END
                ) AS patient_days,
                SUM(CASE WHEN adjrw IS NULL OR adjrw = '' THEN 0 ELSE CAST(adjrw AS DECIMAL(10,4)) END) AS sum_adjrw
            FROM ipd.ipd
            WHERE dateadm BETWEEN :fy_start AND :effective_fy_end
            AND dateadm != '0000-00-00'";
    $stmt_fy_current = $his->prepare($sql_fy_current);
    $stmt_fy_current->execute([
        ':fy_start' => $fy_start,
        ':effective_fy_end' => $effective_fy_end
    ]);
    $fy_current = $stmt_fy_current->fetch(PDO::FETCH_ASSOC) ?: [];
    $fy_admits = (int)($fy_current['total_admits'] ?? 0);
    $fy_patient_days = (int)($fy_current['patient_days'] ?? 0);
    $fy_sum_adjrw = (float)($fy_current['sum_adjrw'] ?? 0);
    $fy_cmi = ($fy_admits > 0) ? round($fy_sum_adjrw / $fy_admits, 4) : 0;
    $fy_occ_rate_current = ($total_beds > 0 && $days_elapsed > 0) ? round(($fy_patient_days / ($total_beds * $days_elapsed)) * 100, 2) : 0;
    $fy_bed_split = ipd_split_bed_usage($his, $fy_start, $effective_fy_end, false);

    $stmt_discharge_count = $his->prepare("SELECT COUNT(*) FROM ipd.ipd WHERE datedsc IS NOT NULL AND datedsc != '0000-00-00' AND datedsc BETWEEN :fy_start AND :effective_fy_end");
    $stmt_discharge_count->execute([
        ':fy_start' => $fy_start,
        ':effective_fy_end' => $effective_fy_end
    ]);
    $fy_discharges = (int)$stmt_discharge_count->fetchColumn();

    // 2. ดึงข้อมูลรายปีงบประมาณ (ย้อนหลัง 5 ปี) 
    $sql_fy = "SELECT 
                IF(MONTH(dateadm) >= 10, YEAR(dateadm) + 1, YEAR(dateadm)) AS fiscal_year,
                COUNT(an) AS total_admits,
                SUM(COALESCE(actlos, DATEDIFF(datedsc, dateadm), 0)) AS total_patient_days,
                SUM(COALESCE(adjrw, 0)) AS sum_adjrw
               FROM ipd.ipd 
               WHERE dateadm >= DATE_SUB(CURDATE(), INTERVAL 5 YEAR) 
               AND dateadm != '0000-00-00'
               GROUP BY fiscal_year
               ORDER BY fiscal_year DESC
               LIMIT 5";
    $stmt_fy = $his->query($sql_fy);
    
    $fy_stats = [];
    while($row = $stmt_fy->fetch(PDO::FETCH_ASSOC)) {
        $fy = (int)$row['fiscal_year'];
        $days_in_year = ($fy % 4 == 0 && ($fy % 100 != 0 || $fy % 400 == 0)) ? 366 : 365;
        
        $patient_days = (int)$row['total_patient_days'];
        $fy_occ_rate = round(($patient_days / ($total_beds * $days_in_year)) * 100, 2);
        
        $fy_stats[] = [
            'fiscal_year' => $fy + 543, 
            'total_admits' => (int)$row['total_admits'],
            'total_patient_days' => $patient_days,
            'sum_adjrw' => (float)$row['sum_adjrw'],
            'occ_rate' => $fy_occ_rate
        ];
    }

    // 3. ดึงรายชื่อผู้ป่วยที่จำหน่ายแล้วในปีงบประมาณปัจจุบัน
    $sql_list = "SELECT
                i.an,
                i.hn,
                i.regdate,
                i.datedsc,
                (
                    SELECT o.fullname
                    FROM opd.opd o
                    WHERE o.hn = i.hn
                    ORDER BY o.regdate DESC
                    LIMIT 1
                ) AS fullname,
                r.roomname AS ward_name,
                i.drg,
                i.adjrw
            FROM ipd.ipd i
            LEFT JOIN hos.roomno r ON i.now_ward = r.roomcode AND r.groupcode = 'WARD'
            WHERE i.datedsc IS NOT NULL
            AND i.datedsc != '0000-00-00'
            AND i.datedsc BETWEEN :fy_start AND :fy_end
            ORDER BY i.datedsc DESC, i.an DESC";
    
    $stmt_list = $his->prepare($sql_list);
    $stmt_list->execute([
        ':fy_start' => $fy_start,
        ':fy_end' => $fy_end
    ]);
    $discharged_list = [];
    
    while ($row = $stmt_list->fetch(PDO::FETCH_ASSOC)) {
        $adjrw = (float)$row['adjrw'];
        $discharged_list[] = [
            'an' => $row['an'],
            'hn' => $row['hn'],
            'regdate' => $row['regdate'],
            'datedsc' => $row['datedsc'],
            'fullname' => safe_utf8($row['fullname']),
            'ward_name' => safe_utf8($row['ward_name']),
            'drg' => $row['drg'],
            'adjrw' => $adjrw
        ];
    }

    $monthly_labels = [];
    $monthly_admits = [];
    $monthly_discharges = [];
    $monthly_patient_days = [];
    $monthly_adjrw = [];
    $thai_months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $month_cursor = new DateTimeImmutable(substr($fy_start, 0, 7) . '-01');
    $month_end_cursor = new DateTimeImmutable(substr($effective_fy_end, 0, 7) . '-01');

    while ($month_cursor <= $month_end_cursor) {
        $month_start = $month_cursor->format('Y-m-01');
        $month_end = min($month_cursor->format('Y-m-t'), $effective_fy_end);
        $monthly_labels[] = $thai_months[((int)$month_cursor->format('n')) - 1] . ' ' . (((int)$month_cursor->format('Y')) + 543);

        $stmt_month = $his->prepare("SELECT
                    COUNT(an) AS admits,
                    SUM(
                        CASE
                            WHEN dateadm IS NULL OR dateadm = '0000-00-00' THEN 0
                            WHEN datedsc IS NULL OR datedsc = '0000-00-00' THEN GREATEST(DATEDIFF(:end_calc, dateadm), 1)
                            ELSE GREATEST(DATEDIFF(LEAST(datedsc, :end_limit), dateadm), 1)
                        END
                    ) AS patient_days,
                    SUM(CASE WHEN adjrw IS NULL OR adjrw = '' THEN 0 ELSE CAST(adjrw AS DECIMAL(10,4)) END) AS sum_adjrw
                FROM ipd.ipd
                WHERE dateadm BETWEEN :start_date AND :end_where
                AND dateadm != '0000-00-00'");
        $stmt_month->execute([
            ':start_date' => $month_start,
            ':end_calc' => $month_end,
            ':end_limit' => $month_end,
            ':end_where' => $month_end
        ]);
        $month_summary = $stmt_month->fetch(PDO::FETCH_ASSOC) ?: [];
        $monthly_admits[] = (int)($month_summary['admits'] ?? 0);
        $monthly_patient_days[] = (int)($month_summary['patient_days'] ?? 0);
        $monthly_adjrw[] = round((float)($month_summary['sum_adjrw'] ?? 0), 4);

        $stmt_month_dc = $his->prepare("SELECT COUNT(*) FROM ipd.ipd WHERE datedsc IS NOT NULL AND datedsc != '0000-00-00' AND datedsc BETWEEN :start_date AND :end_date");
        $stmt_month_dc->execute([':start_date' => $month_start, ':end_date' => $month_end]);
        $monthly_discharges[] = (int)$stmt_month_dc->fetchColumn();

        $month_cursor = $month_cursor->modify('+1 month');
    }

    $quarter_rows = [];
    $quarter_labels = [];
    $quarter_admits = [];
    $quarter_discharges = [];
    $quarter_occ_rate = [];
    $quarter_definitions = [
        ['label' => 'Q1', 'start' => substr($fy_start, 0, 4) . '-10-01', 'end' => substr($fy_start, 0, 4) . '-12-31'],
        ['label' => 'Q2', 'start' => substr($effective_fy_end, 0, 4) . '-01-01', 'end' => substr($effective_fy_end, 0, 4) . '-03-31'],
        ['label' => 'Q3', 'start' => substr($effective_fy_end, 0, 4) . '-04-01', 'end' => substr($effective_fy_end, 0, 4) . '-06-30'],
        ['label' => 'Q4', 'start' => substr($effective_fy_end, 0, 4) . '-07-01', 'end' => substr($effective_fy_end, 0, 4) . '-09-30'],
    ];

    foreach ($quarter_definitions as $quarter) {
        $quarter_start = $quarter['start'];
        $quarter_end = min($quarter['end'], $effective_fy_end);
        $has_started = $quarter_start <= $effective_fy_end;
        $quarter_days = $has_started ? (new DateTimeImmutable($quarter_start))->diff(new DateTimeImmutable($quarter_end))->days + 1 : 0;
        $q_admits = 0;
        $q_discharges = 0;
        $q_patient_days = 0;
        $q_adjrw = 0.0;

        if ($has_started) {
            $stmt_q = $his->prepare("SELECT
                        COUNT(an) AS admits,
                        SUM(
                            CASE
                                WHEN dateadm IS NULL OR dateadm = '0000-00-00' THEN 0
                                WHEN datedsc IS NULL OR datedsc = '0000-00-00' THEN GREATEST(DATEDIFF(:end_calc, dateadm), 1)
                                ELSE GREATEST(DATEDIFF(LEAST(datedsc, :end_limit), dateadm), 1)
                            END
                        ) AS patient_days,
                        SUM(CASE WHEN adjrw IS NULL OR adjrw = '' THEN 0 ELSE CAST(adjrw AS DECIMAL(10,4)) END) AS sum_adjrw
                    FROM ipd.ipd
                    WHERE dateadm BETWEEN :start_date AND :end_where
                    AND dateadm != '0000-00-00'");
            $stmt_q->execute([
                ':start_date' => $quarter_start,
                ':end_calc' => $quarter_end,
                ':end_limit' => $quarter_end,
                ':end_where' => $quarter_end
            ]);
            $q_summary = $stmt_q->fetch(PDO::FETCH_ASSOC) ?: [];
            $q_admits = (int)($q_summary['admits'] ?? 0);
            $q_patient_days = (int)($q_summary['patient_days'] ?? 0);
            $q_adjrw = (float)($q_summary['sum_adjrw'] ?? 0);

            $stmt_q_dc = $his->prepare("SELECT COUNT(*) FROM ipd.ipd WHERE datedsc IS NOT NULL AND datedsc != '0000-00-00' AND datedsc BETWEEN :start_date AND :end_date");
            $stmt_q_dc->execute([':start_date' => $quarter_start, ':end_date' => $quarter_end]);
            $q_discharges = (int)$stmt_q_dc->fetchColumn();
        }

        $q_occ_rate = ($total_beds > 0 && $quarter_days > 0) ? round(($q_patient_days / ($total_beds * $quarter_days)) * 100, 2) : 0;
        $quarter_labels[] = $quarter['label'];
        $quarter_admits[] = $q_admits;
        $quarter_discharges[] = $q_discharges;
        $quarter_occ_rate[] = $q_occ_rate;
        $quarter_rows[] = [
            'label' => $quarter['label'],
            'start' => $quarter_start,
            'end' => $has_started ? $quarter_end : $quarter['end'],
            'admits' => $q_admits,
            'discharges' => $q_discharges,
            'patient_days' => $q_patient_days,
            'occ_rate' => $q_occ_rate,
            'sum_adjrw' => round($q_adjrw, 4),
            'cmi' => $q_admits > 0 ? round($q_adjrw / $q_admits, 4) : 0,
        ];
    }

    $stmt_ward = $his->query("SELECT
                CASE
                    WHEN i.now_ward = 'INV17' THEN 'INV17 ห้องคลอด'
                    WHEN r.roomname IS NOT NULL AND r.roomname != '' THEN r.roomname
                    WHEN i.now_ward IS NOT NULL AND i.now_ward != '' THEN i.now_ward
                    ELSE 'ไม่ระบุ'
                END AS ward_name,
                COUNT(DISTINCT i.an) AS total
            FROM ipd.ipd i
            LEFT JOIN (
                SELECT roomcode, MAX(roomname) AS roomname
                FROM hos.roomno
                GROUP BY roomcode
            ) r ON i.now_ward = r.roomcode
            WHERE i.datedsc IS NULL OR i.datedsc = '0000-00-00'
            GROUP BY ward_name
            ORDER BY total DESC");
    $ward_labels = [];
    $ward_data = [];
    while ($row = $stmt_ward->fetch(PDO::FETCH_ASSOC)) {
        $ward_labels[] = safe_utf8($row['ward_name']);
        $ward_data[] = (int)$row['total'];
    }

    $stroke_condition = "EXISTS (
        SELECT 1
        FROM opd.odiag d
        WHERE d.hn = i.hn
        AND (
            d.regdate = i.opd_date
            OR d.regdate = i.dateadm
            OR d.regdate = i.regdate
        )
        AND d.dxtype = '1'
        AND LEFT(d.diag, 3) BETWEEN 'I60' AND 'I69'
    )";

    $stmt_stroke = $his->prepare("SELECT
                COUNT(i.an) AS admits,
                SUM(
                    CASE
                        WHEN i.dateadm IS NULL OR i.dateadm = '0000-00-00' THEN 0
                        WHEN i.datedsc IS NULL OR i.datedsc = '0000-00-00' THEN GREATEST(DATEDIFF(CURDATE(), i.dateadm), 1)
                        ELSE GREATEST(DATEDIFF(i.datedsc, i.dateadm), 1)
                    END
                ) AS patient_days,
                SUM(CASE WHEN i.adjrw IS NULL OR i.adjrw = '' THEN 0 ELSE CAST(i.adjrw AS DECIMAL(10,4)) END) AS sum_adjrw
            FROM ipd.ipd i
            WHERE i.dateadm BETWEEN :fy_start AND :effective_fy_end
            AND i.dateadm != '0000-00-00'
            AND {$stroke_condition}");
    $stmt_stroke->execute([
        ':fy_start' => $fy_start,
        ':effective_fy_end' => $effective_fy_end
    ]);
    $stroke_summary_row = $stmt_stroke->fetch(PDO::FETCH_ASSOC) ?: [];
    $stroke_admits = (int)($stroke_summary_row['admits'] ?? 0);
    $stroke_patient_days = (int)($stroke_summary_row['patient_days'] ?? 0);
    $stroke_adjrw = (float)($stroke_summary_row['sum_adjrw'] ?? 0);

    $stmt_stroke_active = $his->query("SELECT COUNT(DISTINCT i.an) FROM ipd.ipd i WHERE (i.datedsc IS NULL OR i.datedsc = '0000-00-00') AND {$stroke_condition}");
    $stroke_active = (int)$stmt_stroke_active->fetchColumn();

    $stmt_stroke_dc = $his->prepare("SELECT COUNT(*) FROM ipd.ipd i WHERE i.datedsc IS NOT NULL AND i.datedsc != '0000-00-00' AND i.datedsc BETWEEN :fy_start AND :effective_fy_end AND {$stroke_condition}");
    $stmt_stroke_dc->execute([
        ':fy_start' => $fy_start,
        ':effective_fy_end' => $effective_fy_end
    ]);
    $stroke_discharges = (int)$stmt_stroke_dc->fetchColumn();

    $stmt_stroke_diag = $his->prepare("SELECT
                d.diag AS code,
                MAX(d.descrip) AS name,
                COUNT(DISTINCT i.an) AS total
            FROM ipd.ipd i
            INNER JOIN opd.odiag d
                ON d.hn = i.hn
                AND (
                    d.regdate = i.opd_date
                    OR d.regdate = i.dateadm
                    OR d.regdate = i.regdate
                )
            WHERE i.dateadm BETWEEN :fy_start AND :effective_fy_end
            AND i.dateadm != '0000-00-00'
            AND d.dxtype = '1'
            AND LEFT(d.diag, 3) BETWEEN 'I60' AND 'I69'
            GROUP BY d.diag
            ORDER BY total DESC
            LIMIT 10");
    $stmt_stroke_diag->execute([
        ':fy_start' => $fy_start,
        ':effective_fy_end' => $effective_fy_end
    ]);
    $stroke_diag_rows = [];
    while ($row = $stmt_stroke_diag->fetch(PDO::FETCH_ASSOC)) {
        $stroke_diag_rows[] = [
            'code' => $row['code'],
            'name' => safe_utf8($row['name']),
            'total' => (int)$row['total']
        ];
    }

    $stmt_rtpa_drug = $his->prepare("SELECT itemcode, Name AS name, UnitName AS unit_name, UnitPrice AS unit_price, tmt_name
            FROM hos.itemlist
            WHERE itemcode = 'RTPA'
            LIMIT 1");
    $stmt_rtpa_drug->execute();
    $rtpa_drug = $stmt_rtpa_drug->fetch(PDO::FETCH_ASSOC) ?: [
        'itemcode' => 'RTPA',
        'name' => 'ALTEPLASE (rt-PA)',
        'unit_name' => '',
        'unit_price' => 0,
        'tmt_name' => ''
    ];

    $opd_stroke_exists = "EXISTS (
        SELECT 1
        FROM opd.odiag d
        WHERE d.regdate = o.regdate
        AND d.hn = o.hn
        AND d.frequency = o.frequency
        AND d.dxtype = '1'
        AND LEFT(d.diag, 3) BETWEEN 'I60' AND 'I69'
    )";

    $stmt_rtpa_opd = $his->prepare("SELECT
                COUNT(*) AS orders,
                COUNT(DISTINCT CONCAT(o.regdate, '#', o.hn, '#', o.frequency)) AS visits,
                COUNT(DISTINCT o.hn) AS patients,
                SUM(o.amount) AS amount,
                SUM(o.price) AS price
            FROM opd.drug_order_opd o
            WHERE o.regdate BETWEEN :fy_start AND :effective_fy_end
            AND o.codedrug = 'RTPA'
            AND {$opd_stroke_exists}");
    $stmt_rtpa_opd->execute([
        ':fy_start' => $fy_start,
        ':effective_fy_end' => $effective_fy_end
    ]);
    $rtpa_opd = $stmt_rtpa_opd->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmt_rtpa_ipd = $his->prepare("SELECT
                COUNT(*) AS orders,
                COUNT(DISTINCT oi.an) AS visits,
                COUNT(DISTINCT oi.hn) AS patients,
                SUM(oi.amount) AS amount,
                SUM(oi.price) AS price
            FROM ipd.drug_order_ipd oi
            INNER JOIN ipd.ipd i ON oi.an = i.an
            WHERE oi.orderdate BETWEEN :fy_start AND :effective_fy_end
            AND oi.codedrug = 'RTPA'
            AND {$stroke_condition}");
    $stmt_rtpa_ipd->execute([
        ':fy_start' => $fy_start,
        ':effective_fy_end' => $effective_fy_end
    ]);
    $rtpa_ipd = $stmt_rtpa_ipd->fetch(PDO::FETCH_ASSOC) ?: [];

    $rtpa_month_labels = [];
    $rtpa_month_opd = [];
    $rtpa_month_ipd = [];
    $rtpa_cursor = new DateTimeImmutable(substr($fy_start, 0, 7) . '-01');
    while ($rtpa_cursor <= $month_end_cursor) {
        $month_start = $rtpa_cursor->format('Y-m-01');
        $month_end = min($rtpa_cursor->format('Y-m-t'), $effective_fy_end);
        $rtpa_month_labels[] = $thai_months[((int)$rtpa_cursor->format('n')) - 1] . ' ' . (((int)$rtpa_cursor->format('Y')) + 543);

        $stmt_rtpa_month_opd = $his->prepare("SELECT COUNT(DISTINCT CONCAT(o.regdate, '#', o.hn, '#', o.frequency))
            FROM opd.drug_order_opd o
            WHERE o.regdate BETWEEN :start_date AND :end_date
            AND o.codedrug = 'RTPA'
            AND {$opd_stroke_exists}");
        $stmt_rtpa_month_opd->execute([':start_date' => $month_start, ':end_date' => $month_end]);
        $rtpa_month_opd[] = (int)$stmt_rtpa_month_opd->fetchColumn();

        $stmt_rtpa_month_ipd = $his->prepare("SELECT COUNT(DISTINCT oi.an)
            FROM ipd.drug_order_ipd oi
            INNER JOIN ipd.ipd i ON oi.an = i.an
            WHERE oi.orderdate BETWEEN :start_date AND :end_date
            AND oi.codedrug = 'RTPA'
            AND {$stroke_condition}");
        $stmt_rtpa_month_ipd->execute([':start_date' => $month_start, ':end_date' => $month_end]);
        $rtpa_month_ipd[] = (int)$stmt_rtpa_month_ipd->fetchColumn();

        $rtpa_cursor = $rtpa_cursor->modify('+1 month');
    }

    echo json_encode([
        'status' => 'success', 
        'current_stats' => [
            'total_beds' => $total_beds,
            'active_cases' => $active_cases,
            'available_beds' => $display_available,
            'occ_rate' => $current_occ_rate,
            'overcrowd' => $active_cases > $total_beds ? ($active_cases - $total_beds) : 0,
            'cmi' => $cmi_current
        ],
        'bed_split' => [
            'current' => $current_bed_split,
            'fiscal_year' => $fy_bed_split,
            'definition' => [
                'general_beds' => $general_beds,
                'icu_beds' => $icu_beds,
                'total_beds' => $total_beds,
                'icu_rule' => 'Ward ที่มีคำว่า ICU/ไอซียู/กึ่งวิกฤต',
            ],
        ],
        'fy_summary' => [
            'admits' => $fy_admits,
            'discharges' => $fy_discharges,
            'patient_days' => $fy_patient_days,
            'sum_adjrw' => round($fy_sum_adjrw, 4),
            'cmi' => $fy_cmi,
            'occ_rate' => $fy_occ_rate_current
        ],
        'stroke_unit' => [
            'active' => $stroke_active,
            'admits' => $stroke_admits,
            'discharges' => $stroke_discharges,
            'patient_days' => $stroke_patient_days,
            'sum_adjrw' => round($stroke_adjrw, 4),
            'cmi' => $stroke_admits > 0 ? round($stroke_adjrw / $stroke_admits, 4) : 0,
            'diagnosis' => $stroke_diag_rows,
            'rtpa' => [
                'drug' => [
                    'code' => $rtpa_drug['itemcode'] ?? 'RTPA',
                    'name' => safe_utf8($rtpa_drug['name'] ?? 'ALTEPLASE (rt-PA)'),
                    'unit_name' => safe_utf8($rtpa_drug['unit_name'] ?? ''),
                    'unit_price' => (float)($rtpa_drug['unit_price'] ?? 0),
                    'tmt_name' => safe_utf8($rtpa_drug['tmt_name'] ?? '')
                ],
                'opd' => [
                    'orders' => (int)($rtpa_opd['orders'] ?? 0),
                    'visits' => (int)($rtpa_opd['visits'] ?? 0),
                    'patients' => (int)($rtpa_opd['patients'] ?? 0),
                    'amount' => (float)($rtpa_opd['amount'] ?? 0),
                    'price' => (float)($rtpa_opd['price'] ?? 0)
                ],
                'ipd' => [
                    'orders' => (int)($rtpa_ipd['orders'] ?? 0),
                    'visits' => (int)($rtpa_ipd['visits'] ?? 0),
                    'patients' => (int)($rtpa_ipd['patients'] ?? 0),
                    'amount' => (float)($rtpa_ipd['amount'] ?? 0),
                    'price' => (float)($rtpa_ipd['price'] ?? 0)
                ],
                'monthly' => [
                    'labels' => $rtpa_month_labels,
                    'opd' => $rtpa_month_opd,
                    'ipd' => $rtpa_month_ipd
                ]
            ]
        ],
        'current_fiscal_year' => $current_fiscal_year,
        'fiscal_range' => [
            'start' => $fy_start,
            'end' => $effective_fy_end
        ],
        'fy_stats' => array_reverse($fy_stats),
        'charts' => [
            'monthly' => [
                'labels' => $monthly_labels,
                'admits' => $monthly_admits,
                'discharges' => $monthly_discharges,
                'patient_days' => $monthly_patient_days,
                'sum_adjrw' => $monthly_adjrw
            ],
            'quarters' => [
                'labels' => $quarter_labels,
                'admits' => $quarter_admits,
                'discharges' => $quarter_discharges,
                'occ_rate' => $quarter_occ_rate,
                'rows' => $quarter_rows
            ],
            'ward_active' => [
                'labels' => $ward_labels,
                'data' => $ward_data
            ]
        ],
        'discharged_list' => $discharged_list,
        'active_list' => $discharged_list
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
