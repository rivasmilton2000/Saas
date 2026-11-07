import {Body,CanActivate,Controller,Delete,ExecutionContext,ForbiddenException,Get,Inject,Injectable,Module,Param,Patch,Post,Query,Req,UnprocessableEntityException,UseGuards} from '@nestjs/common';
import {hash} from 'bcryptjs';
import {z} from 'zod';
import {MysqlService} from '../../conexion/conexiones';
import {AuthGuard,AuthRequest} from './auth';
import {AuditService,ok,validate} from './common';
import {isoDate,today} from './accounting';

@Injectable()
export class AdminGuard implements CanActivate {
  canActivate(context: ExecutionContext) {
    if(context.switchToHttp().getRequest<AuthRequest>().user.rol!=='admin')throw new ForbiddenException('Acceso solo para administradores.');
    return true;
  }
}
const idSchema=z.coerce.number().int().positive();
const userSchema=z.object({username:z.string().trim().regex(/^[A-Za-z0-9._-]{3,50}$/),nombre_completo:z.string().trim().max(150).nullable().optional(),
  rol:z.enum(['admin','user']).default('user'),pais:z.string().trim().max(80).default('El Salvador'),id_plan:z.coerce.number().int().positive().nullable().optional(),
  email:z.email().max(160).nullable().optional(),password:z.string().max(72).optional(),confirm_password:z.string().optional()});
const userColumns='u.id,u.username,u.nombre_completo,u.email,u.rol,u.estado,u.pais,u.id_plan,u.created_at,u.ultimo_login_at,u.ultima_actividad_at,u.suscripcion_estado,p.nombre AS plan_nombre';

@Injectable()
export class AdminService {
  constructor(@Inject(MysqlService) private readonly db: MysqlService,@Inject(AuditService) private readonly audit: AuditService) {}
  async users(search='',status='activo',role='') {return this.db.rows(`SELECT ${userColumns} FROM usuarios u LEFT JOIN planes p ON p.id_plan=u.id_plan WHERE (?='todos' OR u.estado=?) AND (?='' OR u.rol=?) AND (u.username LIKE ? OR COALESCE(u.email,'') LIKE ?) ORDER BY u.id DESC LIMIT 500`,[status,status==='inactivo'?0:1,role,role,`%${search.slice(0,100)}%`,`%${search.slice(0,100)}%`]);}
  async overview(input:Record<string,unknown>){
    const date=today();const range=validate(z.object({from:isoDate.default(date.slice(0,4)+'-01-01'),to:isoDate.default(date)}).refine(value=>value.from<=value.to,'Rango de fechas no valido.'),input);
    const [users]=await this.db.rows(`SELECT COUNT(*) usuarios,COALESCE(SUM(estado=1),0) activos,COALESCE(SUM(estado=0),0) inactivos,COALESCE(SUM(rol='admin'),0) administradores FROM usuarios`);
    const [activity]=await this.db.rows(`SELECT (SELECT COUNT(*) FROM empresas WHERE estado=1) empresas,
      (SELECT COUNT(*) FROM libros WHERE estado=1) libros,(SELECT COUNT(*) FROM facturas WHERE fecha BETWEEN ? AND ?) documentos,
      (SELECT COUNT(*) FROM mensajes_contacto WHERE fecha_envio>=? AND fecha_envio<DATE_ADD(?,INTERVAL 1 DAY)) mensajes,
      (SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='paid' AND created_at>=? AND created_at<DATE_ADD(?,INTERVAL 1 DAY)) cobros`,[range.from,range.to,range.from,range.to,range.from,range.to]);
    const plans=await this.db.rows('SELECT p.id_plan,p.nombre,p.precio,COUNT(u.id) usuarios FROM planes p LEFT JOIN usuarios u ON u.id_plan=p.id_plan GROUP BY p.id_plan ORDER BY p.orden');
    const latest=await this.db.rows('SELECT id,username_snapshot,modulo,accion,descripcion,created_at FROM bitacora_movimientos ORDER BY id DESC LIMIT 8');
    return {indicadores:{...(users as object),...(activity as object)},planes:plans,actividad:latest,...range};
  }
  async saveUser(request:AuthRequest,input:unknown,id?:number) {
    const creating=!id;
    const data=validate(userSchema,input);
    if((!id || data.password) && (!data.password || Buffer.byteLength(data.password)<6 || Buffer.byteLength(data.password)>72 || data.password!==data.confirm_password))throw new UnprocessableEntityException('La clave debe tener entre 6 y 72 bytes y coincidir con la confirmacion.');
    const password=data.password ? await hash(data.password,12) : null;
    return this.db.transaction(async connection=>{
      // Bloquear siempre el conjunto de administradores antes de cambiar roles o estado.
      const admins=await this.db.rows<{id:number}>("SELECT id FROM usuarios WHERE rol='admin' AND estado=1 ORDER BY id FOR UPDATE",[],connection);
      if(id) {
        const [user]=await this.db.rows<{id:number;rol:string;estado:number}>('SELECT id,rol,estado FROM usuarios WHERE id=? FOR UPDATE',[id],connection);
        if(!user?.estado)throw new UnprocessableEntityException('Usuario no encontrado.');
        if(user.rol==='admin' && data.rol!=='admin' && admins.length<=1)throw new ForbiddenException('No puedes quitar el rol admin al ultimo administrador activo.');
      }
      let planId:number|null=null,subscriptionState=data.rol==='admin'?'admin':'free';
      if(data.rol==='user') {
        const [plan]=await this.db.rows<{id_plan:number;precio:string|null}>(`SELECT id_plan,precio FROM planes WHERE ${data.id_plan?'id_plan=?':"slug='free'"} AND activo=1`,data.id_plan?[data.id_plan]:[],connection);
        if(!plan)throw new UnprocessableEntityException('Selecciona un plan valido.');planId=plan.id_plan;subscriptionState=Number(plan.precio)>0?'active':'free';
      }
      if(id) {
        await this.db.run(`UPDATE usuarios SET username=?,nombre_completo=?,${data.email!==undefined?'email=?,':''}rol=?,pais=?,id_plan=?${password?',password=?':''} WHERE id=?`,[data.username,data.nombre_completo||null,...(data.email!==undefined?[data.email]:[]),data.rol,data.pais,planId,...(password?[password]:[]),id],connection);
      } else {
        const result=await this.db.run(`INSERT INTO usuarios(username,nombre_completo,email,password,rol,estado,pais,id_plan,auth_provider,suscripcion_estado) VALUES (?,?,?,?,?,1,?,?,'local',?)`,[data.username,data.nombre_completo||null,data.email||null,password,data.rol,data.pais,planId,subscriptionState],connection);id=result.insertId;
      }
      await this.audit.record(request.user,creating?'crear_usuario':'guardar_usuario',`Guardo usuario ${id}.`,request,connection,'administracion');
      return (await this.db.rows(`SELECT ${userColumns} FROM usuarios u LEFT JOIN planes p ON p.id_plan=u.id_plan WHERE u.id=?`,[id],connection))[0];
    });
  }
  async deactivate(request:AuthRequest,id:number) {
    if(id===request.user.id)throw new ForbiddenException('No puedes desactivar tu propia cuenta.');
    await this.db.transaction(async connection=>{
      const admins=await this.db.rows<{id:number}>("SELECT id FROM usuarios WHERE rol='admin' AND estado=1 ORDER BY id FOR UPDATE",[],connection);
      if(admins.length<=1 && admins.some(admin=>admin.id===id))throw new ForbiddenException('No puedes desactivar al ultimo administrador.');
      await this.db.run('UPDATE usuarios SET estado=0 WHERE id=?',[id],connection);
      await this.audit.record(request.user,'desactivar_usuario',`Desactivo usuario ${id}.`,request,connection,'administracion');
    });return null;
  }
  async activate(request:AuthRequest,id:number){
    return this.db.transaction(async connection=>{
      const [user]=await this.db.rows<{id:number;estado:number}>('SELECT id,estado FROM usuarios WHERE id=? FOR UPDATE',[id],connection);
      if(!user)throw new UnprocessableEntityException('Usuario no encontrado.');
      if(!user.estado){await this.db.run('UPDATE usuarios SET estado=1 WHERE id=?',[id],connection);await this.audit.record(request.user,'activar_usuario',`Activo usuario ${id}.`,request,connection,'administracion');}
      return null;
    });
  }
  async updatePlan(id:number,input:unknown,request:AuthRequest) {
    const data=validate(z.object({nombre:z.string().trim().min(1).max(80),descripcion:z.string().trim().max(10000).nullable().optional(),precio:z.coerce.number().nonnegative().max(99999999).nullable(),
      moneda:z.string().regex(/^[A-Z]{3}$/).default('USD'),periodo:z.string().max(20).default('mes'),billing_interval:z.enum(['month','year']).nullable(),destacado:z.boolean(),personalizado:z.boolean(),activo:z.boolean(),
      limite_empresas:z.number().int().nonnegative().nullable(),limite_usuarios:z.number().int().nonnegative().nullable(),limite_documentos:z.number().int().nonnegative().nullable(),
      caracteristicas:z.array(z.object({caracteristica:z.string().trim().min(1).max(255),incluido:z.boolean()})).max(100)}),input);
    return this.db.transaction(async connection=>{
      const [plan]=await this.db.rows('SELECT id_plan FROM planes WHERE id_plan=? FOR UPDATE',[id],connection);if(!plan)throw new UnprocessableEntityException('El plan seleccionado no existe.');
      const {caracteristicas,...values}=data;
      const keys=Object.keys(values) as (keyof typeof values)[];
      await this.db.run(`UPDATE planes SET ${keys.map(key=>'`'+key+'`=?').join(',')} WHERE id_plan=?`,[...keys.map(key=>values[key]),id],connection);
      await this.db.run('DELETE FROM planes_caracteristicas WHERE id_plan=?',[id],connection);
      for(const [index,feature] of caracteristicas.entries())await this.db.run('INSERT INTO planes_caracteristicas(id_plan,caracteristica,incluido,orden) VALUES (?,?,?,?)',[id,feature.caracteristica,feature.incluido,index+1],connection);
      await this.audit.record(request.user,'editar_plan',`Edito plan ${id}.`,request,connection,'administracion');
      return (await this.db.rows('SELECT * FROM planes WHERE id_plan=?',[id],connection))[0];
    });
  }
}
@Controller('api/admin')
@UseGuards(AuthGuard,AdminGuard)
export class AdminController {
  constructor(@Inject(MysqlService) private readonly db:MysqlService,@Inject(AdminService) private readonly admin:AdminService) {}
  @Get('overview') async overview(@Query() query:Record<string,unknown>){return ok(await this.admin.overview(query));}
  @Get('users') async users(@Query('q') search:string,@Query('estado') status:string,@Query('rol') role:string){const filter=validate(z.object({estado:z.enum(['activo','inactivo','todos']).default('activo'),rol:z.enum(['admin','user','']).default('')}),{estado:status,rol:role});return ok(await this.admin.users(search,filter.estado,filter.rol));}
  @Post('users/:id/activate') async activate(@Req() req:AuthRequest,@Param('id') id:string){return ok(await this.admin.activate(req,validate(idSchema,id)),'Cuenta activada.');}
  @Get('contacts') async contacts(){return ok(await this.db.rows('SELECT * FROM mensajes_contacto ORDER BY id DESC LIMIT 500'));}
  @Post('users') async create(@Req() req:AuthRequest,@Body() body:unknown){return ok(await this.admin.saveUser(req,body));}
  @Patch('users/:id') async update(@Req() req:AuthRequest,@Param('id') id:string,@Body() body:unknown){return ok(await this.admin.saveUser(req,body,validate(idSchema,id)));}
  @Delete('users/:id') async remove(@Req() req:AuthRequest,@Param('id') id:string){return ok(await this.admin.deactivate(req,validate(idSchema,id)));}
  @Get('plans') async plans(){const plans=await this.db.rows<Record<string,unknown>>('SELECT * FROM planes ORDER BY orden,id_plan'),features=await this.db.rows<Record<string,unknown>>('SELECT * FROM planes_caracteristicas ORDER BY orden,id');return ok(plans.map(plan=>({...plan,caracteristicas:features.filter(feature=>feature.id_plan===plan.id_plan)})));}
  @Patch('plans/:id') async updatePlan(@Req() req:AuthRequest,@Param('id') id:string,@Body() body:unknown){return ok(await this.admin.updatePlan(validate(idSchema,id),body,req));}
  @Get('companies') async companies(){return ok(await this.db.rows('SELECT e.*,u.username FROM empresas e JOIN usuarios u ON u.id=e.id_usuario WHERE e.estado=1 ORDER BY e.id DESC'));}
  @Get('subscriptions') async subscriptions(){return ok(await this.db.rows('SELECT s.*,u.username,p.nombre AS plan_nombre FROM subscriptions s LEFT JOIN usuarios u ON u.id=s.user_id LEFT JOIN planes p ON p.id_plan=s.plan_id ORDER BY s.id DESC LIMIT 500'));}
  @Get('payments') async payments(){return ok(await this.db.rows('SELECT p.*,u.username FROM payments p LEFT JOIN usuarios u ON u.id=p.user_id ORDER BY p.id DESC LIMIT 500'));}
  @Get('audit') async audit(){return ok(await this.db.rows('SELECT * FROM bitacora_movimientos ORDER BY id DESC LIMIT 500'));}
  @Get('settings') settings(){return ok({mysql:true,stripe:Boolean(process.env.STRIPE_SECRET_KEY),stripeWebhook:Boolean(process.env.STRIPE_WEBHOOK_SECRET),google:Boolean(process.env.GOOGLE_CLIENT_ID),mail:Boolean(process.env.MAIL_HOST&&process.env.MAIL_FROM)});}
}
@Module({controllers:[AdminController],providers:[AdminGuard,AdminService],exports:[AdminService]})
export class AdminModule {}
