<?php
// ปิด display_errors หน้าบ้านเพื่อไม่ให้มีตัวหนังสือแปลกๆ หลุดไปทำ JSON พัง
ini_set('display_errors', '0'); 
error_reporting(E_ALL);
ob_start();

header('Content-Type: application/json; charset=utf-8');

// ฟังก์ชันแปลงภาษาไทย HOSxP ป้องกันปัญหาหน้าขาว
function tis2utf8($string) {
    if (empty($string)) return "ไม่ระบุข้อมูล";
    if (preg_match('//u', (string)$string)) return (string)$string;
    $utf8 = @iconv("TIS-620", "UTF-8//IGNORE", (string)$string);
    return $utf8 !== false ? $utf8 : $string;
}

try {
    // โหลดระบบยืนยันตัวตนและการเชื่อมต่อฐานข้อมูล
    require_once __DIR__.'/../includes/auth.php';
    require_login();
    require_once __DIR__.'/../config/his_database.php';

    // รับค่าจากหน้าต่างตัวกรองของ Dashboard
    $start_date  = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $end_date    = $_GET['end_date'] ?? date('Y-m-d');
    $period_type = $_GET['period_type'] ?? 'monthly';
    $shift_filter = $_GET['shift'] ?? 'ALL';

    // 1. สร้างเงื่อนไขในการกรองรายเวร (Shift Classifier)
    $shift_condition = "1=1";
    if ($shift_filter === 'MORNING')   $shift_condition = "o.timereg BETWEEN '08:00' AND '15:59'";
    if ($shift_filter === 'AFTERNOON') $shift_condition = "o.timereg BETWEEN '16:00' AND '23:59'";
    if ($shift_filter === 'NIGHT')     $shift_condition = "(o.timereg BETWEEN '00:00' AND '07:59')";

    // 2. จัดการมิติเวลา (Time Dimension) ตามที่ผู้ใช้เลือก
    $date_group_by = "DATE_FORMAT(o.regdate, '%Y-%m')"; // Default: รายเดือน
    if ($period_type === 'daily')     $date_group_by = "DATE_FORMAT(o.regdate, '%Y-%m-%d')";
    if ($period_type === 'weekly')    $date_group_by = "CONCAT(YEAR(o.regdate), ' W', WEEK(o.regdate))";
    if ($period_type === 'quarterly') $date_group_by = "CONCAT(YEAR(o.regdate), ' Q', QUARTER(o.regdate))";
    if ($period_type === 'yearly')    $date_group_by = "YEAR(o.regdate)";

    // ขยายขอบเขตเวลาอัตโนมัติหากดูข้อมูลย้อนหลัง 5 ปี
    if ($period_type === 'yearly') {
        $start_date = date('Y-m-d', strtotime('-5 years'));
        $end_date = date('Y-m-d');
    }

    $days = (new DateTimeImmutable($start_date))->diff(new DateTimeImmutable($end_date))->days + 1;

    $sql_total = "SELECT
            COUNT(*) AS total_visits,
            COUNT(DISTINCT hn) AS unique_patients,
            SUM(CASE WHEN frequency = 1 THEN 1 ELSE 0 END) AS new_visits
        FROM opd.opd o
        WHERE o.regdate BETWEEN :start AND :end
        AND {$shift_condition}";
    $stmt_total = $his->prepare($sql_total);
    $stmt_total->execute([':start' => $start_date, ':end' => $end_date]);
    $total_res = $stmt_total->fetch(PDO::FETCH_ASSOC) ?: [];

    // --- QUERY 1: ประมวลสถิติแนวโน้มบริการ (Trend Analysis) ---
    // ปรับปรุง ORDER BY เป็น MIN(o.regdate) เพื่อแก้ปัญหา ONLY_FULL_GROUP_BY
    $sql_trend = "SELECT {$date_group_by} AS label, COUNT(*) AS total 
                  FROM opd.opd o 
                  WHERE o.regdate BETWEEN :start AND :end AND {$shift_condition}
                  GROUP BY label ORDER BY MIN(o.regdate) ASC";
    
    $stmt = $his->prepare($sql_trend);
    $stmt->execute([':start' => $start_date, ':end' => $end_date]);
    
    $trend_labels = []; $trend_values = [];
    while($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $trend_labels[] = $r['label'];
        $trend_values[] = (int)$r['total'];
    }

    // --- QUERY 2: คำนวณสัดส่วนภาระงานแยกตามเวร (Shift Distribution) ---
    $sql_shifts = "SELECT 
        SUM(CASE WHEN o.timereg BETWEEN '08:00' AND '15:59' THEN 1 ELSE 0 END) as morning,
        SUM(CASE WHEN o.timereg BETWEEN '16:00' AND '23:59' THEN 1 ELSE 0 END) as afternoon,
        SUM(CASE WHEN o.timereg BETWEEN '00:00' AND '07:59' THEN 1 ELSE 0 END) as night
        FROM opd.opd o 
        WHERE o.regdate BETWEEN :start AND :end";
    
    $stmt_shift = $his->prepare($sql_shifts);
    $stmt_shift->execute([':start' => $start_date, ':end' => $end_date]);
    $shifts_res = $stmt_shift->fetch(PDO::FETCH_ASSOC);
    $shift_values = [
        'morning' => (int)($shifts_res['morning'] ?? 0),
        'afternoon' => (int)($shifts_res['afternoon'] ?? 0),
        'night' => (int)($shifts_res['night'] ?? 0)
    ];
    $shift_names = ['morning' => 'เวรเช้า', 'afternoon' => 'เวรบ่าย', 'night' => 'เวรดึก'];
    $peak_shift_key = array_keys($shift_values, max($shift_values))[0] ?? 'morning';

    // --- QUERY 3: สรุปสถิติโรค 10 อันดับแรกสูงสุด (Top 10 ICD-10) ---
    // ดึงข้อมูลจริงจากตาราง odiag เชื่อมกับ opd กรองเฉพาะการวินิจฉัยหลัก (dxtype='1')
    $sql_diag = "SELECT 
                    d.diag AS pdx, 
                    MAX(d.descrip) AS diag_name, 
                    COUNT(DISTINCT CONCAT(o.regdate, o.hn, o.frequency)) AS total 
                 FROM opd.opd o
                 INNER JOIN opd.odiag d 
                    ON o.regdate = d.regdate 
                    AND o.hn = d.hn 
                    AND o.frequency = d.frequency
                 WHERE o.regdate BETWEEN :start AND :end 
                 AND d.dxtype = '1' 
                 AND d.diag IS NOT NULL AND d.diag != ''
                 AND {$shift_condition}
                 GROUP BY d.diag
                 ORDER BY total DESC 
                 LIMIT 10";
    
    $stmt_diag = $his->prepare($sql_diag);
    $stmt_diag->execute([':start' => $start_date, ':end' => $end_date]);
    
    $top10_diag = [];
    while($r = $stmt_diag->fetch(PDO::FETCH_ASSOC)) {
        $top10_diag[] = [
            'pdx' => $r['pdx'],
            'diag_name' => tis2utf8($r['diag_name']), // แปลงภาษาไทย
            'total' => (int)$r['total']
        ];
    }

    $sql_clinic = "SELECT c.`Name` AS clinic_name, COUNT(*) AS total
        FROM opd.opd o
        LEFT JOIN hos.clinic c ON o.clinic = c.code
        WHERE o.regdate BETWEEN :start AND :end
        AND {$shift_condition}
        GROUP BY o.clinic, c.`Name`
        ORDER BY total DESC
        LIMIT 10";
    $stmt_clinic = $his->prepare($sql_clinic);
    $stmt_clinic->execute([':start' => $start_date, ':end' => $end_date]);
    $clinic_labels = [];
    $clinic_values = [];
    while ($r = $stmt_clinic->fetch(PDO::FETCH_ASSOC)) {
        $clinic_labels[] = tis2utf8($r['clinic_name']);
        $clinic_values[] = (int)$r['total'];
    }

    $sql_ptclass = "SELECT i.`Name` AS ptclass_name, COUNT(*) AS total
        FROM opd.opd o
        LEFT JOIN hos.insclasses i ON o.ptclass = i.code
        WHERE o.regdate BETWEEN :start AND :end
        AND {$shift_condition}
        GROUP BY o.ptclass, i.`Name`
        ORDER BY total DESC
        LIMIT 8";
    $stmt_ptclass = $his->prepare($sql_ptclass);
    $stmt_ptclass->execute([':start' => $start_date, ':end' => $end_date]);
    $ptclass_labels = [];
    $ptclass_values = [];
    while ($r = $stmt_ptclass->fetch(PDO::FETCH_ASSOC)) {
        $ptclass_labels[] = tis2utf8($r['ptclass_name']);
        $ptclass_values[] = (int)$r['total'];
    }

    // ล้างบัฟเฟอร์ก่อนสร้าง JSON
    ob_clean();
    echo json_encode([
        'status' => 'success',
        'range' => [
            'start' => $start_date,
            'end' => $end_date,
            'days' => $days
        ],
        'kpi' => [
            'total_visits' => (int)($total_res['total_visits'] ?? 0),
            'unique_patients' => (int)($total_res['unique_patients'] ?? 0),
            'new_visits' => (int)($total_res['new_visits'] ?? 0),
            'avg_per_day' => $days > 0 ? round(((int)($total_res['total_visits'] ?? 0)) / $days, 1) : 0,
            'peak_shift' => $shift_names[$peak_shift_key] ?? 'ไม่ระบุ'
        ],
        'trend' => ['labels' => $trend_labels, 'values' => $trend_values],
        'shifts' => $shift_values,
        'top10_diag' => $top10_diag,
        'clinic_chart' => ['labels' => $clinic_labels, 'data' => $clinic_values],
        'ptclass_chart' => ['labels' => $ptclass_labels, 'data' => $ptclass_values]
    ], JSON_UNESCAPED_UNICODE);

} catch(Throwable $e) {
    ob_clean();
    // เปลี่ยนสถานะ HTTP เป็น 200 ដើម្បីป้องกัน Error 500 หลบซ่อน 
    // และให้แสดงกล่องแจ้งเตือน SweetAlert หน้าเว็บแทนเมื่อเกิดปัญหา SQL
    http_response_code(200); 
    echo json_encode([
        'status' => 'error', 
        'message' => 'เกิดข้อผิดพลาดจากฐานข้อมูล SQL: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
