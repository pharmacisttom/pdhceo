<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start();

header('Content-Type: application/json; charset=utf-8');

function tis2utf8($string) {
    if (empty($string)) return "ไม่ระบุข้อมูล";
    $utf8 = @iconv("TIS-620", "UTF-8//IGNORE", (string)$string);
    return $utf8 !== false ? $utf8 : $string;
}

try {
    require_once __DIR__.'/../includes/auth.php';
    require_login();
    require_once __DIR__.'/../config/his_database.php';

    // รับค่าจากหน้าบ้านสำหรับคำนวณช่วงปีงบประมาณ
    $fiscal_year = $_GET['fiscal_year'] ?? date('Y');
    $station = $_GET['station'] ?? 'ALL';

    $start_date = ($fiscal_year - 1) . "-10-01";
    $end_date = $fiscal_year . "-09-30";

    $station_cond = "1=1";
    if ($station !== 'ALL') {
        $station_cond = "LOWER(a.station_name) = :station"; 
    }

    $params = [':start_date' => $start_date, ':end_date' => $end_date];
    if ($station !== 'ALL') {
        $params[':station'] = strtolower($station);
    }

    // --- ส่วนเพิ่มพิเศษ: ประมวลสถิติการส่งต่อเฉพาะ "วันนี้" (Real-Time Today Stats) ---
    $sql_today = "SELECT 
                    SUM(IF(LOWER(station_name) = 'opd', 1, 0)) AS opd_today,
                    SUM(IF(LOWER(station_name) = 'ward', 1, 0)) AS ward_today,
                    SUM(IF(LOWER(station_name) = 'er', 1, 0)) AS er_today
                  FROM referdb.referout 
                  WHERE refer_date = CURDATE()";
    $stmt_today = $his->query($sql_today);
    $today_res = $stmt_today->fetch(PDO::FETCH_ASSOC);

    $today_stats = [
        'opd'  => (int)($today_res['opd_today'] ?? 0),
        'ward' => (int)($today_res['ward_today'] ?? 0),
        'er'   => (int)($today_res['er_today'] ?? 0)
    ];


    // --- QUERY 1: ดึงข้อมูลสรุปแยกรายโรงพยาบาลและรายเดือนประจำปีงบประมาณ ---
    $sql = "SELECT 
                b.hospdesc AS hname,
                COUNT(a.hn) AS total,
                SUM(IF(MONTH(a.refer_date)=10, 1, 0)) AS oct,
                SUM(IF(MONTH(a.refer_date)=11, 1, 0)) AS nov,
                SUM(IF(MONTH(a.refer_date)=12, 1, 0)) AS dec_m,
                SUM(IF(MONTH(a.refer_date)=1, 1, 0)) AS jan,
                SUM(IF(MONTH(a.refer_date)=2, 1, 0)) AS feb,
                SUM(IF(MONTH(a.refer_date)=3, 1, 0)) AS mar,
                SUM(IF(MONTH(a.refer_date)=4, 1, 0)) AS apr,
                SUM(IF(MONTH(a.refer_date)=5, 1, 0)) AS may,
                SUM(IF(MONTH(a.refer_date)=6, 1, 0)) AS jun,
                SUM(IF(MONTH(a.refer_date)=7, 1, 0)) AS jul,
                SUM(IF(MONTH(a.refer_date)=8, 1, 0)) AS aug,
                SUM(IF(MONTH(a.refer_date)=9, 1, 0)) AS sep_m
            FROM referdb.referout a
            LEFT JOIN referdb.hospcode b ON a.refer_hospcode = b.hospcode
            WHERE a.refer_date BETWEEN :start_date AND :end_date
            AND {$station_cond}
            GROUP BY a.refer_hospcode, b.hospdesc
            ORDER BY total DESC";

    $stmt = $his->prepare($sql);
    $stmt->execute($params);

    $table_data = [];
    $chart_data = ['oct'=>0, 'nov'=>0, 'dec'=>0, 'jan'=>0, 'feb'=>0, 'mar'=>0, 'apr'=>0, 'may'=>0, 'jun'=>0, 'jul'=>0, 'aug'=>0, 'sep'=>0];

    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $chart_data['oct'] += (int)$r['oct']; $chart_data['nov'] += (int)$r['nov']; $chart_data['dec'] += (int)$r['dec_m'];
        $chart_data['jan'] += (int)$r['jan']; $chart_data['feb'] += (int)$r['feb']; $chart_data['mar'] += (int)$r['mar'];
        $chart_data['apr'] += (int)$r['apr']; $chart_data['may'] += (int)$r['may']; $chart_data['jun'] += (int)$r['jun'];
        $chart_data['jul'] += (int)$r['jul']; $chart_data['aug'] += (int)$r['aug']; $chart_data['sep'] += (int)$r['sep_m'];

        $table_data[] = [
            'hname' => tis2utf8($r['hname']), 'total' => (int)$r['total'],
            'oct' => (int)$r['oct'], 'nov' => (int)$r['nov'], 'dec' => (int)$r['dec_m'],
            'jan' => (int)$r['jan'], 'feb' => (int)$r['feb'], 'mar' => (int)$r['mar'],
            'apr' => (int)$r['apr'], 'may' => (int)$r['may'], 'jun' => (int)$r['jun'],
            'jul' => (int)$r['jul'], 'aug' => (int)$r['aug'], 'sep' => (int)$r['sep_m']
        ];
    }

    // --- QUERY 2: ดึงข้อมูลสรุปสัดส่วนสิทธิการรักษาประจำปีงบประมาณ ---
    $sql_pttype = "SELECT a.pttype_name, COUNT(a.hn) AS total 
                   FROM referdb.referout a 
                   WHERE a.refer_date BETWEEN :start_date AND :end_date 
                   AND {$station_cond} 
                   GROUP BY a.pttype_name 
                   ORDER BY total DESC LIMIT 6";
    
    $stmt_pt = $his->prepare($sql_pttype);
    $stmt_pt->execute($params);
    
    $pttype_labels = [];
    $pttype_values = [];
    while ($row = $stmt_pt->fetch(PDO::FETCH_ASSOC)) {
        $pttype_labels[] = tis2utf8($row['pttype_name']);
        $pttype_values[] = (int)$row['total'];
    }

    $station_sql = "SELECT LOWER(station_name) AS station, COUNT(hn) AS total
                    FROM referdb.referout
                    WHERE refer_date BETWEEN :start_date AND :end_date
                    GROUP BY LOWER(station_name)";
    $stmt_station = $his->prepare($station_sql);
    $stmt_station->execute([':start_date' => $start_date, ':end_date' => $end_date]);
    $station_breakdown = ['opd' => 0, 'ward' => 0, 'er' => 0, 'other' => 0];
    while ($row = $stmt_station->fetch(PDO::FETCH_ASSOC)) {
        $station_name = (string)($row['station'] ?? '');
        if (array_key_exists($station_name, $station_breakdown)) {
            $station_breakdown[$station_name] = (int)$row['total'];
        } else {
            $station_breakdown['other'] += (int)$row['total'];
        }
    }

    $quarter_data = [
        'labels' => ['Q1', 'Q2', 'Q3', 'Q4'],
        'data' => [
            $chart_data['oct'] + $chart_data['nov'] + $chart_data['dec'],
            $chart_data['jan'] + $chart_data['feb'] + $chart_data['mar'],
            $chart_data['apr'] + $chart_data['may'] + $chart_data['jun'],
            $chart_data['jul'] + $chart_data['aug'] + $chart_data['sep']
        ]
    ];

    $hospital_chart = [
        'labels' => array_map(fn($row) => $row['hname'], array_slice($table_data, 0, 10)),
        'data' => array_map(fn($row) => (int)$row['total'], array_slice($table_data, 0, 10))
    ];

    ob_clean();
    echo json_encode([
        'status' => 'success',
        'fiscal_year' => (int)$fiscal_year + 543,
        'fiscal_range' => [
            'start' => $start_date,
            'end' => $end_date
        ],
        'today_stats' => $today_stats, // ส่งยอดของวันนี้กลับไปหน้าบ้าน
        'table_data' => $table_data,
        'chart_data' => $chart_data,
        'pttype_data' => ['labels' => $pttype_labels, 'data' => $pttype_values],
        'station_breakdown' => $station_breakdown,
        'quarter_data' => $quarter_data,
        'hospital_chart' => $hospital_chart
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    ob_clean();
    http_response_code(200); 
    echo json_encode([
        'status' => 'error',
        'message' => 'เกิดข้อผิดพลาด SQL: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
