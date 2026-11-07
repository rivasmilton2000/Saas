<?php
declare(strict_types=1);require __DIR__.'/_bootstrap.php';header('Content-Type: application/json; charset=utf-8');if(($_SERVER['REQUEST_METHOD']??'')!=='POST')sietelsa_abort(405);$token=(string)($_POST['csrf_token']??($_SERVER['HTTP_X_CSRF_TOKEN']??''));if(!csrf_is_valid($token))sietelsa_abort(419);$action=(string)($_POST['action']??'');
try{$rateLimit=in_array($action,['upload','work'],true)?[16000,3600]:[60,60];(new AccountingRateLimiter(db()))->hit((int)$currentUser['id'],'dte_'.$action,$rateLimit[0],$rateLimit[1]);$uuid=(string)($_POST['import_uuid']??'');if($action!=='create'){$scope=$importService->requireAuthorized($uuid,(int)$companyContextUser['id']);if($scope['modulo']!==$accountingModule||(int)$scope['libro_id']!==(int)($_POST['book_id']??0))throw new DomainException('La importación no corresponde al libro abierto.');if(!$activeCompany||(int)$activeCompany['id']!==(int)$scope['empresa_id'])throw new DomainException('Activa la empresa del libro antes de continuar.');}
if($action==='create'){$bookId=(int)($_POST['book_id']??0);$book=$accountingService->book($bookId,$accountingModule,$companyContextUser);if(!$activeCompany||(int)$activeCompany['id']!==(int)$book['empresa_id'])throw new DomainException('Activa la empresa del libro antes de importar.');$reason=administrative_dte_reason($currentUser,$companyContextUser,$_POST);$result=$importService->create($bookId,$accountingModule,(int)($_POST['count']??0),(int)($_POST['total_bytes']??0),$currentUser,$companyContextUser);administrative_dte_trace(db(),$currentUser,$companyContextUser,['empresa_id'=>$book['empresa_id'],'libro_id'=>$bookId,'accion'=>'json_importado','motivo'=>$reason,'nuevo'=>['uuid'=>$result['uuid'],'archivos_declarados'=>(int)($_POST['count']??0)]]);}
elseif($action==='upload'){$result=$importService->addUploads($uuid,$_FILES['files']??[],$currentUser,$companyContextUser,isset($_POST['offset'])?(int)$_POST['offset']:null);}
elseif($action==='finalize'){$importService->finalizeUpload($uuid,$currentUser,$companyContextUser);$result=['ok'=>true];}
elseif($action==='work'){
    $import=$importService->requireAuthorized($uuid,(int)$companyContextUser['id']);
    if((string)$import['modulo']!==$accountingModule||(int)$import['libro_id']!==(int)($_POST['book_id']??0))throw new DomainException('La importación no corresponde al libro abierto.');
    $worker=new DteImportWorker(db(),$dteStorage,$dteParser,$importService,'web-'.(int)$currentUser['id'].'-'.bin2hex(random_bytes(4)));
    $result=$worker->runForImport($uuid,max(1,min(50,$importService->settings()['worker_batch'])));
}
elseif($action==='cancel'){$importService->cancel($uuid,$currentUser,$companyContextUser);$result=['ok'=>true];}
elseif($action==='retry'){$result=['retried'=>$importService->retryErrors($uuid,$currentUser,$companyContextUser)];}
elseif($action==='confirm'){$importService->confirm($uuid,$currentUser,$companyContextUser);$result=['ok'=>true];}
elseif($action==='move'){$importService->moveOutsidePeriod($uuid,(int)($_POST['file_id']??0),(int)($_POST['target_book_id']??0),(string)($_POST['confirmation']??''),$currentUser,$companyContextUser);$result=['ok'=>true];}
else throw new InvalidArgumentException('Acción de importación inválida.');echo json_encode(['success'=>true,'data'=>$result],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(InvalidArgumentException|DomainException $e){http_response_code(422);echo json_encode(['success'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}
catch(Throwable $e){app_log_exception($e,'dte-import-api-'.$action);http_response_code(500);echo json_encode(['success'=>false,'message'=>'No fue posible procesar la importación. Revisa los archivos e inténtalo nuevamente.'],JSON_UNESCAPED_UNICODE);}
