<?php

require_once 'config/his_database.php';

header('Content-Type:text/plain');

try{

$stmt=$his->query("
SELECT COUNT(*)
FROM opd.opd
WHERE regdate=CURDATE()
");

echo "OPD TODAY=".$stmt->fetchColumn();

}catch(Throwable $e){

echo $e->getMessage();

}