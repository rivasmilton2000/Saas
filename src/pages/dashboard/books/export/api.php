<?php
declare(strict_types=1);
require __DIR__.'/_bootstrap.php';header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, private');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')sietelsa_abort(405);if(!csrf_is_valid((string)($_POST['csrf_token']??'')))sietelsa_abort(419);
try{
 (new AccountingRateLimiter(db()))->hit((int)$currentUser['id'],'book_export',30,3600);
 $values=[];foreach(['filters'=>'{}','columns'=>'[]','selected_ids'=>'[]','classification'=>'{}'] as $key=>$default){$values[$key]=json_decode((string)($_POST[$key]??$default),true,64,JSON_THROW_ON_ERROR);if(!is_array($values[$key]))throw new InvalidArgumentException('La solicitud de exportación no es válida.');}
 $filters=$values['filters'];if($values['selected_ids'])$filters['ids']=$values['selected_ids'];
 $book=(new BookAccessService(db()))->requireAuthorized((int)($_POST['book_id']??0),(int)$companyContextUser['id'],'export');if($book['tipo']!==$accountingModule)sietelsa_abort(403,'permission');
 $job=$exportService->create((int)$book['id'],(string)($_POST['format']??''),$filters,$values['columns'],$currentUser,$companyContextUser,[
 'classification'=>$values['classification'],'classification_policy'=>(string)($_POST['classification_policy']??'fill_missing'),'replace_confirmation'=>(string)($_POST['replace_confirmation']??''),'persist_classification'=>($_POST['persist_classification']??'')==='1','admin_reason'=>(string)($_POST['admin_reason']??''),'scope_fingerprint'=>(string)($_POST['scope_fingerprint']??''),'expected_count'=>(int)($_POST['expected_count']??-1),'remember'=>($_POST['remember_classification']??'')==='1']);
 if(!$job['async'])(new ExportWorker(db(),$exportService,$exportStorage,'web-'.getmypid()))->processUuid($job['uuid']);echo json_encode(['success'=>true,'data'=>$exportService->status($job['uuid'],$companyContextUser)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
}catch(OfficialExportValidationException $e){http_response_code(422);echo json_encode(['success'=>false,'message'=>'Corrige los datos indicados en cada registro.','errors'=>$e->errors],JSON_UNESCAPED_UNICODE);
}catch(JsonException|InvalidArgumentException|DomainException $e){http_response_code(422);echo json_encode(['success'=>false,'message'=>$e instanceof JsonException?'La solicitud de exportación no es válida.':$e->getMessage()],JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){app_log_exception($e,'accounting-export-api');http_response_code(500);echo json_encode(['success'=>false,'message'=>'No fue posible preparar la exportación. Inténtalo nuevamente.'],JSON_UNESCAPED_UNICODE);}
