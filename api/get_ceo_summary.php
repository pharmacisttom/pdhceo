<?php
// 1. เปิด Error Reporting เพื่อช่วยดักจับปัญหาระหว่างพัฒนา
ini_set('display_errors', '1');
error_reporting(E_ALL);

// 2. ป้องกันตัวเว้นวรรคหรือขยะที่อาจหลุดมาก่อนเริ่มสคริปต์ (ป้องกันหน้าขาว)
ob_start();

header('Content-Type: application/json; charset=utf-8');

// 3. ฟังก์ชันแปลงภาษาไทย HOSxP (TIS-620) ให้เป็น UTF-8 เพื่อรองรับ JSON
function tis2utf8($string) {
    if ($string === null || $string === '') return $string;
    $utf8 = @iconv("TIS-620", "UTF-8//IGNORE", (string)$string);
    return $utf8 !== false ? $utf8 : $string;
}

try {
    // โหลดระบบตรวจสอบสิทธิ์และฐานข้อมูล
    require_once __DIR__.'/../includes/auth.php';
    require_login();
    require_once __DIR__.'/../config/his_database.php';

    $today = date('Y-m-d');

    // ==========================================
    // ส่วนที่ 1: ดึงยอดผู้ใช้บริการประจำวัน (KPI Cards)
    // ==========================================

    // 1. ยอดผู้ป่วยนอกรวม (OPD)
    $stmt_opd = $his->prepare("SELECT COUNT(*) FROM opd.opd WHERE regdate = ?");
    $stmt_opd->execute([$today]);
    $opd = (int)$stmt_opd->fetchColumn();

    // 2. ยอดผู้ป่วยนอกรายใหม่ (New OPD : Frequency = 1)
    $stmt_new_opd = $his->prepare("SELECT COUNT(*) FROM opd.opd WHERE regdate = ? AND frequency = 1");
    $stmt_new_opd->execute([$today]);
    $opd_new = (int)$stmt_new_opd->fetchColumn();

    // 3. ยอดผู้ป่วยในปัจจุบัน (IPD Admit)
    $stmt_ipd = $his->query("SELECT COUNT(*) FROM ipd.ipd WHERE datedsc = '0000-00-00' OR datedsc IS NULL");
    $ipd = (int)$stmt_ipd->fetchColumn();

    // 4. ยอดอุบัติเหตุฉุกเฉิน (ER : Clinic 130)
    $stmt_er = $his->prepare("SELECT COUNT(*) FROM opd.opd WHERE regdate = ? AND clinic = '130'");
    $stmt_er->execute([$today]);
    $er = (int)$stmt_er->fetchColumn();

    // 5. ยอดส่งต่อ (Refer Out : Result 3 หรือ RST3)
    $stmt_refer = $his->prepare("SELECT COUNT(*) FROM opd.opd WHERE regdate = ? AND result IN ('3', 'RST3')");
    $stmt_refer->execute([$today]);
    $refer = (int)$stmt_refer->fetchColumn();

    // 6. ความซับซ้อนโรคเฉลี่ย (AdjRW ของเดือนนี้)
    $stmt_adjrw = $his->query("
        SELECT ROUND(AVG(adjrw), 2) as adjrw 
        FROM ipd.ipd 
        WHERE MONTH(regdate) = MONTH(CURDATE()) 
        AND YEAR(regdate) = YEAR(CURDATE()) 
        AND adjrw IS NOT NULL AND adjrw > 0
    ");
    $row_adjrw = $stmt_adjrw->fetch(PDO::FETCH_ASSOC);
    $adjrw = ($row_adjrw && $row_adjrw['adjrw'] !== null) ? $row_adjrw['adjrw'] : "0.00";

    // 7. อัตราครองเตียง (Occupancy Rate)
    $bedcount = 102; // ฐานเตียง
    $occ = ($bedcount > 0) ? round(($ipd / $bedcount) * 100, 2) : 0;

    // ==========================================
    // ส่วนที่ 2: ดึงข้อมูลกราฟสัดส่วนสิทธิการรักษา
    // ==========================================
    $stmt_fin = $his->prepare("
        SELECT i.Name as name, COUNT(o.hn) as total
        FROM opd.opd o
        LEFT JOIN hos.insclasses i ON o.ptclass = i.code
        WHERE o.regdate = ?
        GROUP BY o.ptclass, i.Name
        ORDER BY total DESC
        LIMIT 5
    ");
    $stmt_fin->execute([$today]);
    $fin_data = $stmt_fin->fetchAll(PDO::FETCH_ASSOC);

    $fin_labels = [];
    $fin_values = [];
    foreach($fin_data as $row) {
        $fin_labels[] = tis2utf8($row['name']) ?? 'ไม่ระบุสิทธิ';
        $fin_values[] = (int)$row['total'];
    }

    // ==========================================
    // ส่วนที่ 3: ดึงข้อมูลกราฟแนวโน้ม 7 วันย้อนหลัง
    // ==========================================
    $chart_labels = [];
    $chart_opd = [];
    $chart_ipd = [];
    $thai_days = ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'];

    for ($i = 6; $i >= 0; $i--) {
        $target_date = date('Y-m-d', strtotime("-$i days"));
        $day_index = date('w', strtotime($target_date));
        $chart_labels[] = $thai_days[$day_index];

        $s1 = $his->prepare("SELECT COUNT(*) FROM opd.opd WHERE regdate = ?");
        $s1->execute([$target_date]);
        $chart_opd[] = (int)$s1->fetchColumn();

        $s2 = $his->prepare("SELECT COUNT(*) FROM ipd.ipd WHERE regdate = ?");
        $s2->execute([$target_date]);
        $chart_ipd[] = (int)$s2->fetchColumn();
    }

    // ==========================================
    // ส่งออกข้อมูลทั้งหมดให้หน้าเว็บ (JSON Output)
    // ==========================================
    $response = [
        'status' => 'success',
        'opd_total' => $opd,
        'opd_new_total' => $opd_new,  // <--- เพิ่มตัวแปรผู้ป่วยใหม่
        'ipd_total' => $ipd,
        'er_total' => $er,
        'refer_total' => $refer,
        'adjrw' => $adjrw,
        'occ_rate' => $occ,
        'finance_chart' => [
            'labels' => $fin_labels,
            'data' => $fin_values
        ],
        'chart' => [
            'labels' => $chart_labels,
            'opd' => $chart_opd,
            'ipd' => $chart_ipd
        ],
        // สำหรับกราฟ 5 ปี ให้ส่งค่า null ไปก่อน เพื่อให้ JS ฝั่งหน้าบ้าน (index.php) ใช้กราฟจำลอง (Mock Data) สวยๆ ทำงานไปก่อน
        // (เพราะการ Query ข้อมูลย้อนหลัง 5 ปีสดๆ ทุกครั้งที่เปิดหน้าเว็บ จะทำให้เซิร์ฟเวอร์ HIMPRO ทำงานหนักมากครับ)
        'history_5years' => null 
    ];

    ob_clean(); // ล้างขยะที่อาจค้างอยู่ใน Buffer
    
    // เข้ารหัสเป็น JSON
    $json = json_encode($response, JSON_UNESCAPED_UNICODE);
    
    if ($json === false) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'JSON Encode Error: ' . json_last_error_msg()
        ]);
    } else {
        echo $json; // พิมพ์ JSON ที่สมบูรณ์ออกไป
    }

} catch(Throwable $e) {
    ob_clean(); // ถ้าเกิด Error ให้ล้างหน้าจอให้สะอาดก่อน
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'System Error: ' . $e->getMessage(),
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
}
?>