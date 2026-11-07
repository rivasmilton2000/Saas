<?php
declare(strict_types=1);
require_once dirname(__DIR__,3).'/includes/users/bootstrap.php';
$currentUser=require_authentication();
$json=str_contains(strtolower($_SERVER['HTTP_ACCEPT']??''),'application/json');
$newPath=null;$manager=new AvatarManager();
try {
    if(($_SERVER['REQUEST_METHOD']??'')!=='POST') {http_response_code(405);throw new DomainException('Utiliza el formulario de tu cuenta.');}
    if(!csrf_is_valid(is_string($_POST['csrf_token']??null)?$_POST['csrf_token']:null)) {http_response_code(419);throw new DomainException('La sesión del formulario venció. Actualiza la página.');}
    if(isset($_POST['user_id'])&&(int)$_POST['user_id']!==(int)$currentUser['id']) {http_response_code(403);throw new DomainException('Solo puedes modificar tu propia foto.');}
    $action=(string)($_POST['action']??'');
    db()->beginTransaction();
    if($action==='upload') {
        $newPath=$manager->store($_FILES['avatar']??[],['x'=>$_POST['crop_x']??50,'y'=>$_POST['crop_y']??50,'zoom'=>$_POST['crop_zoom']??1]);
        user_service()->setAvatar((int)$currentUser['id'],'personalizado',$newPath,null,(int)$currentUser['id']);
    } elseif($action==='reset') {
        user_service()->setAvatar((int)$currentUser['id'],'predeterminado',null,null,(int)$currentUser['id']);
    } elseif($action==='google') {
        $identity=db()->prepare('SELECT 1 FROM identidades_autenticacion WHERE usuario_id=? AND proveedor="google"');$identity->execute([$currentUser['id']]);
        if(!$identity->fetchColumn())throw new DomainException('Vincula y verifica tu cuenta de Google antes de utilizar su foto.');
        $candidate=$_SESSION['account_google_photo']??[];
        $url=(int)($candidate['id']??0)===(int)$currentUser['id']&&(int)($candidate['time']??0)>time()-600 ? ($candidate['url']??null) : ($currentUser['google_avatar_url']??null);
        if(!AvatarManager::googleUrlIsValid($url))throw new DomainException('Google no entregó una foto válida. Obtén nuevamente la foto o elige un archivo.');
        $url .= (str_contains($url,'?')?'&':'?').'sietelsa_avatar='.bin2hex(random_bytes(6));
        user_service()->setAvatar((int)$currentUser['id'],'google',null,$url,(int)$currentUser['id']);
        unset($_SESSION['account_google_photo']);
    } else throw new DomainException('Selecciona una fuente de foto válida.');
    db()->commit();
    $updated=find_user_by_id((int)$currentUser['id']);
    if($json){header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode(['ok'=>true,'url'=>user_avatar_url($updated),'source'=>$updated['avatar_tipo']],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
    flash_set('success','Foto actualizada','Tu elección quedó guardada en tu cuenta.');
} catch(Throwable $exception) {
    if(db()->inTransaction())db()->rollBack();
    if($newPath!==null)$manager->delete($newPath);
    $message=user_safe_error_message($exception,'No fue posible guardar la foto. Reintenta más tarde.');
    if($json){if(http_response_code()<400)http_response_code(422);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode(['ok'=>false,'message'=>$message],JSON_UNESCAPED_UNICODE);exit;}
    flash_set('error','Foto de perfil',$message);
}
header('Location: '.dashboard_url_for($currentUser));exit;
