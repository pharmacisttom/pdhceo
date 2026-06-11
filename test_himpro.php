<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors',1);

require_once __DIR__.'/config/his_database.php';

echo "<h2>ทดสอบ HIMPRO</h2>";

try{

    echo "เชื่อมต่อ PDO สำเร็จ<br><br>";

    $stmt=$his->query("
        SELECT COUNT(*) total
        FROM opd.opd
        LIMIT 1
    ");

    $r=$stmt->fetch();

    echo "จำนวน OPD : ".$r['total'];

}catch(Throwable $e){

    echo "<pre>";
    echo $e->getMessage();
    echo "</pre>";

}