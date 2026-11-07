<?php
declare(strict_types=1);
require __DIR__.'/_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, private');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')sietelsa_abort(405);
if(!csrf_is_valid((string)($_POST['csrf_token']??'')))sietelsa_abort(419);
try {
 $pdo=db();$book=(new BookAccessService($pdo))->requireAuthorized((int)($_POST['book_id']??0),(int)$companyContextUser['id'],'export');
 $active=(new CompanyService($pdo))->activeCompany($currentUser,$companyContextUser);
 if($book['tipo']!==$accountingModule||!$active||(int)$active['id']!==(int)$book['empresa_id'])sietelsa_abort(403,'permission');
 $filters=json_decode((string)($_POST['filters']??'{}'),true,64,JSON_THROW_ON_ERROR);$ids=json_decode((string)($_POST['selected_ids']??'[]'),true,64,JSON_THROW_ON_ERROR);
 if(!is_array($filters)||!is_array($ids))throw new InvalidArgumentException('El alcance solicitado no es válido.');if($ids)$filters['ids']=$ids;
 $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$pdo->beginTransaction();$preview=Annex3Rules::preview($pdo,$book,$filters);
 $data=$preview+['catalogs'=>Annex3Rules::catalogs((string)$book['periodo_inicio']),'saved'=>Annex3Rules::preferences($pdo,(int)$currentUser['id'],$book),'can_persist'=>in_array($book['estado'],['borrador','activo'],true),'period'=>$book['periodo_inicio']];$pdo->commit();
 echo json_encode(['success'=>true,'data'=>$data],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}catch(JsonException|InvalidArgumentException|DomainException $e){if(db()->inTransaction())db()->rollBack();http_response_code(422);echo json_encode(['success'=>false,'message'=>$e instanceof JsonException?'El alcance solicitado no es válido.':$e->getMessage()],JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();app_log_exception($e,'annex3-preview');http_response_code(500);echo json_encode(['success'=>false,'message'=>'No fue posible comprobar el alcance. Intenta nuevamente; si persiste, contacta al administrador.'],JSON_UNESCAPED_UNICODE);}
