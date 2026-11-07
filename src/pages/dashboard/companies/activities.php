<?php
declare(strict_types=1);
require __DIR__.'/_bootstrap.php';header('Content-Type: application/json; charset=utf-8');header('Cache-Control: private, no-store');
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET')sietelsa_abort(405);
try{echo json_encode(['success'=>true,'activities'=>EconomicActivityCatalog::search(db(),(string)($_GET['q']??''),(string)($_GET['section']??''))],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);}catch(InvalidArgumentException $e){http_response_code(422);echo json_encode(['success'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}catch(Throwable $e){app_log_exception($e,'economic-activities');http_response_code(500);echo json_encode(['success'=>false,'message'=>'El catálogo no está disponible. Intenta nuevamente.'],JSON_UNESCAPED_UNICODE);}
