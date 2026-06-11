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
                <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-microscope text-primary"></i> รายการชันสูตร (Lab List)</h4>
                <div class="text-secondary small">ข้อมูลรายการชันสูตรจากระบบฐานข้อมูล ณ วันที่ <?= date('d/m/Y') ?></div>
            </div>
        </div>
    </div>

    <div class="chart-block">
        <div class="table-responsive">
            <table id="labTable" class="table table-hover table-striped align-middle border mb-0">
                <thead class="table-light">
                        <th>รหัสแล็บ</th>
                        <th>หมวดหมู่ (MedLab)</th>
                        <th>ชื่อแล็บ (HIS)</th>
                        <th>ชื่อแสดงผล (MedLab)</th>
                        <th>ลักษณะแล็บ</th>
                        <th>ค่าปกติ</th>
                        <th>สถานะเบิก</th>
                        <th>ราคาขาย</th>
                        <th>สถานะการใช้</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    try {
                        $cat_filter = isset($_GET['cat']) ? (int)$_GET['cat'] : null;
                        $where = "";
                        if ($cat_filter) {
                            $where = " WHERE c.id = " . $cat_filter;
                        }
                        
                        $sql = "SELECT l.*, c.category_name, i.display_name as medlab_name 
                                FROM hos.lablist l 
                                LEFT JOIN medlab.lab_items i ON i.lab_code = l.code AND i.is_active = 1
                                LEFT JOIN medlab.lab_categories c ON c.id = i.category_id AND c.is_active = 1
                                $where
                                ORDER BY l.no_use ASC, c.sort_order ASC, l.Name ASC";
                        $stmt = $his->query($sql);
                        
                        while ($row = $stmt->fetch()) {
                            // ใช้ iconv แทน mb_convert_encoding
                            $raw_name = $row['Name'] ?? '';
                            $raw_desc = $row['descrip'] ?? '';
                            $raw_norm = $row['normal'] ?? '';

                            $lab_name = mb_check_encoding($raw_name, 'UTF-8') ? $raw_name : @iconv('TIS-620', 'UTF-8//IGNORE', $raw_name);
                            $lab_desc = mb_check_encoding($raw_desc, 'UTF-8') ? $raw_desc : @iconv('TIS-620', 'UTF-8//IGNORE', $raw_desc);
                            $lab_norm = mb_check_encoding($raw_norm, 'UTF-8') ? $raw_norm : @iconv('TIS-620', 'UTF-8//IGNORE', $raw_norm);

                            $support_badge = ($row['support'] == 1) ? '<span class="badge bg-success">เบิกได้</span>' : '<span class="badge bg-danger">เบิกไม่ได้</span>';
                            $use_status = ($row['no_use'] == 0) ? '<span class="text-success"><i class="bi bi-check-circle"></i> ปกติ</span>' : '<span class="text-danger"><i class="bi bi-x-circle"></i> ยกเลิก</span>';
                            $price = number_format((float)$row['stdPrice'], 2);

                            $category_badge = $row['category_name'] ? "<span class='badge bg-primary'>{$row['category_name']}</span>" : "<span class='badge bg-secondary'>ไม่ได้จัดหมวด</span>";
                            $medlab_name = $row['medlab_name'] ? "<span class='text-primary fw-bold'>{$row['medlab_name']}</span>" : "-";

                            echo "<tr>
                                    <td class='fw-bold text-secondary'>{$row['code']}</td>
                                    <td>{$category_badge}</td>
                                    <td class='text-dark fw-bold'>{$lab_name}</td>
                                    <td>{$medlab_name}</td>
                                    <td>{$lab_desc}</td>
                                    <td>{$lab_norm}</td>
                                    <td>{$support_badge}</td>
                                    <td class='text-end fw-bold'>{$price}</td>
                                    <td>{$use_status}</td>
                                  </tr>";
                        }
                    } catch (PDOException $e) {
                        echo "<tr><td colspan='7' class='text-center text-danger fw-bold'>เกิดข้อผิดพลาด: " . $e->getMessage() . "</td></tr>";
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
        $('#labTable').DataTable({
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