<?php
declare(strict_types=1);
require_once __DIR__.'/_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET')sietelsa_abort(405);
try {
    if(isset($_GET['q'])&&!is_string($_GET['q']))throw new InvalidArgumentException('Búsqueda inválida.');
    $data=$auditQuery->users((string)($_GET['q']??''),max(1,(int)($_GET['page']??1)),$auditDteScope);
    echo json_encode(['ok'=>true]+$data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}catch(Throwable $e){
    $id=sietelsa_record_incident($e,500,['module'=>'audit','action'=>'users']);
    http_response_code(500);
    echo json_encode(['ok'=>false,'message'=>'No fue posible cargar los usuarios. Intenta nuevamente.','incident_id'=>$id],JSON_UNESCAPED_UNICODE);
}
