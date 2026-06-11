<?php
declare(strict_types=1);

// ระบบความปลอดภัยตรวจสอบสิทธิ์ผู้บริหาร
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/functions.php';

// เรียกใช้ฐานข้อมูล HIS ตามปกติ
require_once __DIR__ . '/../config/his_database.php';

$currentMonth = date('Y-m');
$user_role = $_SESSION['role'] ?? 'Executive';
?>

<?php include_once __DIR__ . '/../layout/header.php'; ?>
<?php include_once __DIR__ . '/../layout/sidebar.php'; ?>

<style>
    .chart-block { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); margin-bottom: 25px; }
</style>

<div class="content" style="margin-left: 260px; padding: 30px; background: #f2f5fa; min-height: 100vh;">
    
    <div class="topbar bg-white p-3 rounded-4 shadow-sm mb-4">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-pills text-success"></i> รายการยาและเวชภัณฑ์ (Drug List)</h4>
                <div class="text-secondary small">ข้อมูลรายการยาจากระบบฐานข้อมูล ณ วันที่ <?= date('d/m/Y') ?></div>
            </div>
            <div class="col-md-4 text-end">
                <a href="druglistexp.php" class="btn btn-success btn-sm"><i class="bi bi-file-earmark-excel"></i> ส่งออกรายงาน</a>
            </div>
        </div>
    </div>

    <div class="chart-block">
        <div class="table-responsive">
            <table id="drugTable" class="table table-hover table-striped align-middle border mb-0">
                <thead class="table-light">
                    <tr>   
                        <th>รหัสยา</th>
                        <th>ชื่อยา</th>
                        <th>บรรจุ</th>
                        <th>สถานะเบิก</th>
                        <th>ราคาขาย</th>
                        <th>HAD</th>
                        <th>รหัสTMT</th>
                        <th>สถานะการใช้</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    try {
                        $sql = "SELECT * FROM hos.itemlist WHERE itemtype IN ('ITEM_IN1','ITEM_IN2')";
                        $stmt = $his->query($sql);
                        
                        while ($row = $stmt->fetch()) {
                            // ใช้ iconv แทน mb_convert_encoding เพื่อความเสถียรบน PHP 8+
                            $raw_name = $row['Name'] ?? '';
                            $raw_unit = $row['UnitName'] ?? '';
                            
                            $drug_name = mb_check_encoding($raw_name, 'UTF-8') ? $raw_name : @iconv('TIS-620', 'UTF-8//IGNORE', $raw_name);
                            $unit_name = mb_check_encoding($raw_unit, 'UTF-8') ? $raw_unit : @iconv('TIS-620', 'UTF-8//IGNORE', $raw_unit);

                            $support_badge = ($row['support'] == 1) ? '<span class="badge bg-success">เบิกได้</span>' : '<span class="badge bg-danger">เบิกไม่ได้</span>';
                            $had_status = ($row['high_alert_drug'] == 1) ? '<span class="text-danger fw-bold">ฉุกเฉิน</span>' : '<span class="text-success">ทั่วไป</span>';
                            $use_status = ($row['no_use'] == 0) ? '<span class="text-success"><i class="bi bi-check-circle"></i> ปกติ</span>' : '<span class="text-danger"><i class="bi bi-x-circle"></i> ยกเลิก</span>';
                            $price = number_format((float)$row['UnitPrice'], 2);

                            echo "<tr>
                                    <td class='fw-bold text-secondary'>{$row['itemcode']}</td>
                                    <td class='text-dark fw-bold'>{$drug_name}</td>
                                    <td>{$unit_name}</td>
                                    <td>{$support_badge}</td>
                                    <td class='text-end fw-bold'>{$price}</td>
                                    <td>{$had_status}</td>
                                    <td>{$row['tmt_code']}</td>
                                    <td>{$use_status}</td>
                                  </tr>";
                        }
                    } catch (PDOException $e) {
                        echo "<tr><td colspan='8' class='text-center text-danger fw-bold'>เกิดข้อผิดพลาด: " . $e->getMessage() . "</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../layout/footer.php'; ?>

<script>
$(document).ready(function() {
    if ($.fn.DataTable) {
        $('#drugTable').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/th.json"
            },
            "pageLength": 10,
            "bLengthChange": true,
            "searching": true,
            "ordering": true,
            "order": [[1, "asc"]]
        });
    }
});
</script>