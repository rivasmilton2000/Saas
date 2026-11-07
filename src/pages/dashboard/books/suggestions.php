<?php
declare(strict_types=1);
require_once dirname(__DIR__,3).'/includes/accounting/bootstrap.php';
require_once SIETELSA_ROOT.'/includes/dashboard/modules.php';
$module=is_string($_GET['module']??null)?$_GET['module']:'';
try { $info=AccountingCatalog::module($module); } catch(InvalidArgumentException $e) { sietelsa_abort(422); }
$currentUser=require_dashboard_module($info['permission']);
CompanyPermissionService::require($currentUser,'book.view');
$context=company_context_user($currentUser);
$service=new AccountingService(db(),new BookAccessService(db()));
$book=$service->book((int)($_GET['book']??0),$module,$context);
$active=(new CompanyService(db()))->activeCompany($currentUser,$context);
if(!$active||(int)$active['id']!==(int)$book['empresa_id'])sietelsa_abort(403);
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
try {
    $filters=is_string($_GET['filters']??null)?json_decode($_GET['filters'],true,32,JSON_THROW_ON_ERROR):[];
    if(!is_array($filters))throw new InvalidArgumentException('Filtros inválidos.');
    $values=(new AccountingRecordQuery(db()))->suggestions((int)$book['id'],$filters,is_string($_GET['field']??null)?$_GET['field']:'',is_string($_GET['q']??null)?$_GET['q']:'');
    echo json_encode(['success'=>true,'values'=>$values],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
} catch(InvalidArgumentException|JsonException $e) {http_response_code(422);echo json_encode(['success'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}
catch(Throwable $e) {http_response_code(500);echo json_encode(['success'=>false,'message'=>'No fue posible consultar las sugerencias.'],JSON_UNESCAPED_UNICODE);}
