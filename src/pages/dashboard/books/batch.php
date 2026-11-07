<?php
declare(strict_types=1);
require_once dirname(__DIR__,3).'/includes/accounting/bootstrap.php';
require_once SIETELSA_ROOT.'/includes/dashboard/modules.php';
require_once SIETELSA_ROOT.'/includes/accounting/RecordBatchService.php';
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')sietelsa_abort(405);
$module=is_string($_POST['module']??null)?$_POST['module']:'';
try {$info=AccountingCatalog::module($module);}catch(InvalidArgumentException $e){sietelsa_abort(422);}
$currentUser=require_dashboard_module($info['permission']);CompanyPermissionService::require($currentUser,'book.manual_create');
if(!csrf_is_valid(is_string($_POST['csrf_token']??null)?$_POST['csrf_token']:null))sietelsa_abort(419);
$context=company_context_user($currentUser);$service=new AccountingService(db(),new BookAccessService(db()));
$book=$service->book((int)($_POST['book_id']??0),$module,$context);
$active=(new CompanyService(db()))->activeCompany($currentUser,$context);
if(!$active||(int)$active['id']!==(int)$book['empresa_id'])sietelsa_abort(403);
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
try {
    if(!in_array($book['estado'],['activo','borrador'],true))throw new DomainException('El libro no admite cambios.');
    $batch=new RecordBatchService(db(),$service);$action=$_POST['action']??'';
    if($action==='load') {
        $ids=json_decode((string)($_POST['ids']??''),true,32,JSON_THROW_ON_ERROR);
        if(!is_array($ids))throw new InvalidArgumentException('Selección inválida.');
        $rows=$batch->load((int)$book['id'],$module,$ids,$context);$allowed=array_keys(RecordBatchService::fields($module));
        foreach($rows as &$row){$protected=RecordBatchService::protectedFields($row);$row=array_intersect_key($row,array_flip(array_merge($allowed,['id','numero_linea','version','origen'])));$row['protected']=$protected;}unset($row);
        echo json_encode(['success'=>true,'rows'=>$rows,'fields'=>RecordBatchService::fields($module),'types'=>$service->documentTypes($module)],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;
    }
    if(!in_array($action,['edit','delete'],true))throw new InvalidArgumentException('Acción inválida.');
    administrative_dte_reason($currentUser,$context,$_POST);
    (new AccountingRateLimiter(db()))->hit((int)$currentUser['id'],'book_batch');
    $rows=json_decode((string)($_POST['rows']??''),true,64,JSON_THROW_ON_ERROR);
    if(!is_array($rows))throw new InvalidArgumentException('Filas inválidas.');
    $count=$batch->mutate((int)$book['id'],$module,$rows,$action==='delete',(string)($_POST['confirmation']??''),$currentUser,$context);
    echo json_encode(['success'=>true,'count'=>$count],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
} catch(RecordBatchException $e) {http_response_code(422);echo json_encode(['success'=>false,'message'=>$e->getMessage(),'errors'=>$e->errors],JSON_UNESCAPED_UNICODE);}
catch(InvalidArgumentException|DomainException|JsonException $e) {http_response_code(422);echo json_encode(['success'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}
catch(Throwable $e) {app_log_exception($e,'book-batch');http_response_code(500);echo json_encode(['success'=>false,'message'=>'No fue posible guardar. No se completó la operación.'],JSON_UNESCAPED_UNICODE);}
