<?php
// ไฟล์: pdhceo/api/get_adjrw.php
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__.'/../includes/auth.php';
    require_login();
    require_once __DIR__.'/../config/his_database.php'; // เชื่อมต่อฐานข้อมูล HIS ($his)

    // ใช้คำสั่ง SQL ตามที่คุณต้องการ (เรียงลำดับจากค่า AdjRW มากที่สุดลงมา)
    $sql = "SELECT a.hn, a.an, a.dateadm, a.datedsc, a.drg, a.rw, a.adjrw 
            FROM ipd.ipd a 
            WHERE a.adjrw IS NOT NULL AND a.adjrw <> '' 
            ORDER BY CAST(a.adjrw AS DECIMAL(10,4)) DESC 
            LIMIT 100"; // ดึง 100 อันดับแรกเพื่อไม่ให้หน้าเว็บโหลดหนักเกินไป

    $stmt = $his->query($sql);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // คำนวณภาพรวม (Sum และ Average)
    $total_adjrw = 0;
    $count = count($data);
    foreach($data as $row) {
        $total_adjrw += (float)$row['adjrw'];
    }
    $cmi = ($count > 0) ? ($total_adjrw / $count) : 0;

    echo json_encode([
        'status' => 'success',
        'data' => $data,
        'summary' => [
            'total_cases' => $count,
            'sum_adjrw' => $total_adjrw,
            'avg_cmi' => $cmi
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>