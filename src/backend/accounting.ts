import {Body,CanActivate,ConflictException,Controller,ExecutionContext,ForbiddenException,Get,Inject,Injectable,Module,Param,Patch,Post,Query,Req,UnprocessableEntityException,UseGuards} from '@nestjs/common';
import {PoolConnection} from 'mysql2/promise';
import {z} from 'zod';
import {MysqlService} from '../../conexion/conexiones';
import {AuthGuard,AuthRequest,User} from './auth';
import {AuditService,ok,validate} from './common';

export const positiveId=z.coerce.number().int().positive();
export const isoDate=z.string().regex(/^\d{4}-\d{2}-\d{2}$/,'Fecha no valida.').refine(value=>!Number.isNaN(Date.parse(value))&&new Date(value).toISOString().slice(0,10)===value,'Fecha no valida.');
export const today=()=>new Intl.DateTimeFormat('sv-SE',{timeZone:'America/El_Salvador'}).format(new Date());
export const amount=z.string().regex(/^\d{1,13}(\.\d{1,2})?$/,'Usa un importe positivo con hasta dos decimales.');
export function cents(value:string):bigint {const [whole,fraction='']=value.split('.');return BigInt(whole)*100n+BigInt(fraction.padEnd(2,'0'));}
export function decimal(value:bigint):string {return `${value<0n?'-':''}${(value<0n?-value:value)/100n}.${((value<0n?-value:value)%100n).toString().padStart(2,'0')}`;}
export const defaultAccounts=[
  ['1101','Caja','activo'],['1102','Bancos','activo'],['1103','Cuentas por cobrar','activo'],['1201','Equipo de oficina','activo'],['1202','Inventario','activo'],
  ['2101','Cuentas por pagar','pasivo'],['2102','IVA por pagar','pasivo'],['3101','Capital','patrimonio'],['4101','Ingresos por ventas','ingreso'],['5101','Costo de ventas','gasto'],['5102','Servicios y gastos operativos','gasto'],
] as const;
const accountSchema=z.object({codigo:z.string().trim().regex(/^[0-9A-Za-z.-]{1,20}$/),nombre:z.string().trim().min(2).max(120),tipo:z.enum(['activo','pasivo','patrimonio','ingreso','gasto'])});
const entrySchema=z.object({id_empresa:positiveId,fecha:isoDate,descripcion:z.string().trim().min(3).max(255),referencia:z.string().trim().max(80).default(''),
  lineas:z.array(z.object({id_cuenta:positiveId,debe:amount,haber:amount,detalle:z.string().trim().max(255).default('')})).min(2).max(100)});
type Company={id:number;nombre:string;id_usuario:number};
type Account={id:number;id_empresa:number;codigo:string;nombre:string;tipo:string;activo:number};
type Entry={id:number;id_empresa:number;id_usuario:number;fecha:string;descripcion:string;referencia:string|null;estado:string;version:number};
type Actor=Pick<User,'id'|'username'|'rol'>;

@Injectable()
export class UserRoleGuard implements CanActivate {
  canActivate(context:ExecutionContext){const {user}=context.switchToHttp().getRequest<AuthRequest>();if(user.rol!=='user')throw new ForbiddenException('Este espacio contable corresponde al rol Usuario.');if(!user.id_plan)throw new ForbiddenException('Selecciona tu membresia para continuar.');return true;}
}
@Injectable()
export class AccountingService {
  constructor(@Inject(MysqlService) private readonly db:MysqlService,@Inject(AuditService) private readonly audit:AuditService){}
  async company(companyId:number,userId:number,connection?:PoolConnection,lock=false){
    const [company]=await this.db.rows<Company>('SELECT id,nombre,id_usuario FROM empresas WHERE id=? AND id_usuario=? AND estado=1'+(lock?' FOR UPDATE':''),[companyId,userId],connection);
    if(!company)throw new ForbiddenException('Empresa no encontrada o sin permiso.');return company;
  }
  async initialize(companyId:number,userId:number,connection?:PoolConnection){
    const action=async(conn:PoolConnection)=>{await this.company(companyId,userId,conn,true);for(const [codigo,nombre,tipo] of defaultAccounts)await this.db.run('INSERT IGNORE INTO contabilidad_cuentas(id_empresa,codigo,nombre,tipo) VALUES (?,?,?,?)',[companyId,codigo,nombre,tipo],conn);return this.accounts(companyId,userId,conn);};
    return connection?action(connection):this.db.transaction(action);
  }
  async accounts(companyId:number,userId:number,connection?:PoolConnection){await this.company(companyId,userId,connection);return this.db.rows<Account>('SELECT * FROM contabilidad_cuentas WHERE id_empresa=? ORDER BY codigo',[companyId],connection);}
  async createAccount(companyId:number,userId:number,input:unknown){const data=validate(accountSchema,input);return this.db.transaction(async conn=>{await this.company(companyId,userId,conn,true);const result=await this.db.run('INSERT INTO contabilidad_cuentas(id_empresa,codigo,nombre,tipo) VALUES (?,?,?,?)',[companyId,data.codigo,data.nombre,data.tipo],conn);return (await this.db.rows<Account>('SELECT * FROM contabilidad_cuentas WHERE id=?',[result.insertId],conn))[0];});}
  async changeAccount(id:number,userId:number,input:unknown){
    const data=validate(z.object({nombre:z.string().trim().min(2).max(120),activo:z.boolean()}),input);
    return this.db.transaction(async conn=>{const [account]=await this.db.rows<Account>('SELECT c.* FROM contabilidad_cuentas c JOIN empresas e ON e.id=c.id_empresa WHERE c.id=? AND e.id_usuario=? AND e.estado=1 FOR UPDATE',[id,userId],conn);if(!account)throw new ForbiddenException('Cuenta no encontrada o sin permiso.');await this.db.run('UPDATE contabilidad_cuentas SET nombre=?,activo=? WHERE id=?',[data.nombre,data.activo,id],conn);return {...account,...data};});
  }
  async createEntry(actor:Actor,input:unknown,connection?:PoolConnection):Promise<Entry>{
    const data=validate(entrySchema,input);
    let debit=0n,credit=0n;
    for(const line of data.lineas){const d=cents(line.debe),c=cents(line.haber);if((d>0n)===(c>0n))throw new UnprocessableEntityException('Cada partida requiere un importe en Debe o en Haber.');debit+=d;credit+=c;}
    if(debit!==credit || debit===0n)throw new UnprocessableEntityException('El asiento debe cuadrar: total Debe igual a total Haber.');
    const action=async(conn:PoolConnection)=>{
      await this.company(data.id_empresa,actor.id,conn,true);
      const ids=[...new Set(data.lineas.map(line=>line.id_cuenta))];
      const accounts=await this.db.rows<Account>(`SELECT * FROM contabilidad_cuentas WHERE id_empresa=? AND activo=1 AND id IN (${ids.map(()=>'?').join(',')}) FOR UPDATE`,[data.id_empresa,...ids],conn);
      if(accounts.length!==ids.length)throw new ForbiddenException('Las cuentas deben estar activas y pertenecer a la misma empresa.');
      const result=await this.db.run('INSERT INTO contabilidad_asientos(id_empresa,id_usuario,fecha,descripcion,referencia) VALUES (?,?,?,?,?)',[data.id_empresa,actor.id,data.fecha,data.descripcion,data.referencia||null],conn);
      for(const line of data.lineas)await this.db.run('INSERT INTO contabilidad_partidas(id_empresa,id_asiento,id_cuenta,detalle,debe,haber) VALUES (?,?,?,?,?,?)',[data.id_empresa,result.insertId,line.id_cuenta,line.detalle||null,decimal(cents(line.debe)),decimal(cents(line.haber))],conn);
      await this.audit.record(actor,'crear_asiento',`Registro asiento ${result.insertId}: ${data.descripcion}`,undefined,conn,'contabilidad');
      return (await this.db.rows<Entry>('SELECT * FROM contabilidad_asientos WHERE id=?',[result.insertId],conn))[0];
    };
    return connection?action(connection):this.db.transaction(action);
  }
  range(input:Record<string,unknown>){const date=today();return validate(z.object({company_id:positiveId,from:isoDate.default(date.slice(0,4)+'-01-01'),to:isoDate.default(date),q:z.string().trim().max(100).default(''),estado:z.enum(['publicado','anulado','todos']).default('publicado')}).refine(value=>value.from<=value.to,'El rango de fechas no es valido.'),input);}
  async entries(userId:number,input:Record<string,unknown>){
    const filter=this.range(input);await this.company(filter.company_id,userId);
    return this.db.rows(`SELECT a.*,SUM(p.debe) total_debe,SUM(p.haber) total_haber,COUNT(p.id) partidas FROM contabilidad_asientos a
      JOIN contabilidad_partidas p ON p.id_asiento=a.id AND p.id_empresa=a.id_empresa WHERE a.id_empresa=? AND a.fecha BETWEEN ? AND ?
      AND (?='todos' OR a.estado=?) AND (a.descripcion LIKE ? OR COALESCE(a.referencia,'') LIKE ?) GROUP BY a.id ORDER BY a.fecha DESC,a.id DESC LIMIT 500`,[filter.company_id,filter.from,filter.to,filter.estado,filter.estado,`%${filter.q}%`,`%${filter.q}%`]);
  }
  async entry(id:number,userId:number,connection?:PoolConnection){
    const [entry]=await this.db.rows<Entry>(`SELECT a.* FROM contabilidad_asientos a JOIN empresas e ON e.id=a.id_empresa WHERE a.id=? AND e.id_usuario=? AND e.estado=1`,[id,userId],connection);
    if(!entry)throw new ForbiddenException('Asiento no encontrado o sin permiso.');
    const lines=await this.db.rows('SELECT p.*,c.codigo,c.nombre FROM contabilidad_partidas p JOIN contabilidad_cuentas c ON c.id=p.id_cuenta WHERE p.id_asiento=? ORDER BY p.id',[id],connection);return {...entry,lineas:lines};
  }
  async voidEntry(id:number,actor:Actor,input:unknown){
    const data=validate(z.object({motivo:z.string().trim().min(5).max(500),version:z.number().int().positive()}),input);
    await this.db.transaction(async conn=>{
      const entry=await this.entry(id,actor.id,conn);await this.company(entry.id_empresa,actor.id,conn,true);
      const result=await this.db.run("UPDATE contabilidad_asientos SET estado='anulado',version=version+1,motivo_anulacion=?,anulado_at=CURRENT_TIMESTAMP WHERE id=? AND version=? AND estado='publicado'",[data.motivo,id,data.version],conn);
      if(result.affectedRows!==1)throw new ConflictException('El asiento ya cambio o fue anulado. Recarga el listado.');
      await this.audit.record(actor,'anular_asiento',`Anulo asiento ${id}: ${data.motivo}`,undefined,conn,'contabilidad');
    });return this.entry(id,actor.id);
  }
  async overview(userId:number,input:Record<string,unknown>){
    const filter=this.range(input);
    return this.db.transaction(async conn=>{
      const company=await this.company(filter.company_id,userId,conn);
      const rows=await this.db.rows<Account&{apertura:string;debe:string;haber:string;cierre:string}>(`SELECT c.*,
        COALESCE(SUM(CASE WHEN a.fecha<? THEN p.debe-p.haber ELSE 0 END),0) apertura,
        COALESCE(SUM(CASE WHEN a.fecha BETWEEN ? AND ? THEN p.debe ELSE 0 END),0) debe,
        COALESCE(SUM(CASE WHEN a.fecha BETWEEN ? AND ? THEN p.haber ELSE 0 END),0) haber,
        COALESCE(SUM(p.debe-p.haber),0) cierre FROM contabilidad_cuentas c
        LEFT JOIN contabilidad_partidas p ON p.id_cuenta=c.id AND p.id_empresa=c.id_empresa
          AND p.id_asiento IN (SELECT id FROM contabilidad_asientos WHERE id_empresa=? AND estado='publicado' AND fecha<=?)
        LEFT JOIN contabilidad_asientos a ON a.id=p.id_asiento WHERE c.id_empresa=? GROUP BY c.id ORDER BY c.codigo`,[filter.from,filter.from,filter.to,filter.from,filter.to,company.id,filter.to,company.id],conn);
      const signed=(value:string)=>value.startsWith('-')?-cents(value.slice(1)):cents(value);
      const sum=(type:string,field:'cierre'|'debe'|'haber')=>rows.filter(row=>row.tipo===type).reduce((total,row)=>total+signed(row[field]),0n);
      const revenue=sum('ingreso','haber')-sum('ingreso','debe'),expense=sum('gasto','debe')-sum('gasto','haber');
      const totals={debe:rows.reduce((n,row)=>n+signed(row.debe),0n),haber:rows.reduce((n,row)=>n+signed(row.haber),0n)};
      const [counts]=await this.db.rows<{asientos:number}>("SELECT COUNT(*) asientos FROM contabilidad_asientos WHERE id_empresa=? AND estado='publicado' AND fecha BETWEEN ? AND ?",[company.id,filter.from,filter.to],conn);
      const [books]=await this.db.rows<{libros:number;documentos:number;compras:string;ventas:string}>(`SELECT
        (SELECT COUNT(*) FROM libros WHERE id_empresa=? AND estado=1) libros,COUNT(f.id) documentos,
        COALESCE(SUM(CASE WHEN l.tipo='compras' THEN f.total_compras ELSE 0 END),0) compras,
        COALESCE(SUM(CASE WHEN l.tipo='ventas_consumidor' THEN f.total_ventas_diarias_propias WHEN l.tipo='ventas_contribuyente' THEN f.ventas_totales ELSE 0 END),0) ventas
        FROM facturas f JOIN libros l ON l.id=f.id_libro WHERE l.id_empresa=? AND l.estado=1 AND f.fecha BETWEEN ? AND ?`,[company.id,company.id,filter.from,filter.to],conn);
      return {empresa:company,from:filter.from,to:filter.to,cuentas:rows,
        indicadores:{ingresos:decimal(revenue),gastos:decimal(expense),resultado:decimal(revenue-expense),activos:decimal(sum('activo','cierre')),pasivos:decimal(-sum('pasivo','cierre')),patrimonio:decimal(-sum('patrimonio','cierre')),
          efectivo:decimal(rows.filter(row=>['1101','1102'].includes(row.codigo)).reduce((n,row)=>n+signed(row.cierre),0n)),asientos:counts.asientos,...books},
        totales:{debe:decimal(totals.debe),haber:decimal(totals.haber),diferencia:decimal(totals.debe-totals.haber)}};
    });
  }
}
@Controller('api/accounting')
@UseGuards(AuthGuard,UserRoleGuard)
export class AccountingController {
  constructor(@Inject(AccountingService) private readonly accounting:AccountingService){}
  @Get('overview') async overview(@Req() req:AuthRequest,@Query() query:Record<string,unknown>){return ok(await this.accounting.overview(req.user.id,query));}
  @Get('accounts') async accounts(@Req() req:AuthRequest,@Query('company_id') id:string){return ok(await this.accounting.accounts(validate(positiveId,id),req.user.id));}
  @Post('accounts/initialize') async initialize(@Req() req:AuthRequest,@Body() body:unknown){const {id_empresa}=validate(z.object({id_empresa:positiveId}),body);return ok(await this.accounting.initialize(id_empresa,req.user.id));}
  @Post('accounts') async createAccount(@Req() req:AuthRequest,@Body() body:Record<string,unknown>){return ok(await this.accounting.createAccount(validate(positiveId,body.id_empresa),req.user.id,body));}
  @Patch('accounts/:id') async changeAccount(@Req() req:AuthRequest,@Param('id') id:string,@Body() body:unknown){return ok(await this.accounting.changeAccount(validate(positiveId,id),req.user.id,body));}
  @Get('entries') async entries(@Req() req:AuthRequest,@Query() query:Record<string,unknown>){return ok(await this.accounting.entries(req.user.id,query));}
  @Get('entries/:id') async entry(@Req() req:AuthRequest,@Param('id') id:string){return ok(await this.accounting.entry(validate(positiveId,id),req.user.id));}
  @Post('entries') async create(@Req() req:AuthRequest,@Body() body:unknown){return ok(await this.accounting.createEntry(req.user,body),'Asiento registrado.');}
  @Post('entries/:id/void') async cancel(@Req() req:AuthRequest,@Param('id') id:string,@Body() body:unknown){return ok(await this.accounting.voidEntry(validate(positiveId,id),req.user,body),'Asiento anulado.');}
}
@Module({controllers:[AccountingController],providers:[AccountingService,UserRoleGuard],exports:[AccountingService,UserRoleGuard]})
export class AccountingModule {}
