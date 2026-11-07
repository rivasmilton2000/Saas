import { Body, CanActivate, ConflictException, Controller, Delete, ExecutionContext, ForbiddenException, Get, Inject, Injectable, Module, Param, Patch, Post, Query, Req, Res, UnprocessableEntityException, UseGuards } from '@nestjs/common';
import { Response } from 'express';
import { z } from 'zod';
import { MysqlService } from '../../conexion/conexiones';
import { PoolConnection } from 'mysql2/promise';
import { AuthGuard, AuthRequest } from './auth';
import { ok, validate } from './common';
import { expand, mapDocument, validTypes } from './dte-data';
import { report, reportHtml } from './reports';
import { cents, decimal, isoDate } from './accounting';

const idSchema=z.coerce.number().int().positive();
const nullableText=z.string().trim().max(100).optional().transform(value=>value || null);
const companySchema=z.object({nombre:z.string().trim().min(1,'El nombre de la empresa es requerido.').max(100),iniciales:z.string().trim().max(4).optional(),color_emblema:z.string().regex(/^#[0-9a-fA-F]{6}$/).default('#f97316'),dui:nullableText,nit:nullableText,nrc:nullableText,tipo_legal:z.enum(['natural','juridica']).default('natural')});
const bookSchema=z.object({id_empresa:idSchema,tipo:z.enum(['compras','ventas_consumidor','ventas_contribuyente','retencion_iva']),mes:z.coerce.number().int().min(1).max(12),anio:z.coerce.number().int().min(2000).max(9999)});
const amountColumns=['ventas_internas','ventas_importacion','ventas_internas_exentas','ventas_importacion_exentas','credito_fiscal','total_compras','iva_percibido','iva_retenido','ventas_exentas','ventas_internas_gravadas','exportaciones','total_ventas_diarias_propias','ventas_cuenta_terceros','debito_fiscal','ventas_exentas_contribuyente','ventas_internas_gravadas_contribuyente','debito_fiscal_contribuyente','ventas_totales','monto_sujeto_retencion','retencion_iva_1'];

@Injectable()
class PlanGuard implements CanActivate {
  canActivate(context: ExecutionContext) {
    const request=context.switchToHttp().getRequest<AuthRequest>();
    if (request.user.rol === 'user' && !request.user.id_plan) throw new ForbiddenException('Selecciona tu membresia para continuar.');
    return true;
  }
}
@Injectable()
export class BusinessService {
  constructor(@Inject(MysqlService) private readonly db: MysqlService) {}
  async company(id: number,userId: number,connection?: PoolConnection) {
    const [company]=await this.db.rows<Record<string,any>>('SELECT * FROM empresas WHERE id=? AND id_usuario=? AND estado=1',[id,userId],connection);
    if (!company) throw new ForbiddenException('Empresa no encontrada o sin permiso.');
    return company;
  }
  async companies(userId: number) {
    const companies=await this.db.rows<Record<string,any>>('SELECT * FROM empresas WHERE id_usuario=? AND estado=1 ORDER BY nombre,id DESC',[userId]);
    const last=[...companies].sort((a,b)=>String(b.ultima_vez_usada || '').localeCompare(String(a.ultima_vez_usada || '')) || b.id-a.id)[0];
    return companies.map(company=>({...company,es_ultima_usada:company.id===last?.id}));
  }
  async saveCompany(userId: number,input: unknown,id?: number) {
    const data=validate(companySchema,input);
    let dui=data.dui?.replace(/\D/g,'') || '',nit=data.nit?.replace(/\D/g,'') || '';
    if (dui && dui.length!==9) throw new UnprocessableEntityException('El DUI debe tener formato 12345678-9.');
    if (nit && nit.length!==14) throw new UnprocessableEntityException('El NIT debe tener formato 0000-000000-000-0.');
    dui=dui ? `${dui.slice(0,8)}-${dui.slice(8)}` : '';
    nit=nit ? `${nit.slice(0,4)}-${nit.slice(4,10)}-${nit.slice(10,13)}-${nit.slice(13)}` : '';
    if (id) await this.company(id,userId);
    if ((await this.db.rows("SELECT id FROM empresas WHERE id_usuario=? AND estado=1 AND id<>? AND ((nrc=? AND nrc<>'') OR (nit=? AND nit<>''))",[userId,id || 0,data.nrc || '',nit])).length) throw new ConflictException('Ya tienes una empresa con ese NRC o NIT.');
    const initials=(data.iniciales || data.nombre.split(/\s+/).map(word=>Array.from(word)[0]).join('')).slice(0,4).toUpperCase();
    const values=[data.nombre,initials,data.color_emblema,dui || null,nit || null,data.nrc,data.tipo_legal];
    if (id) await this.db.run('UPDATE empresas SET nombre=?,iniciales=?,color_emblema=?,dui=?,nit=?,nrc=?,tipo_legal=? WHERE id=? AND id_usuario=?',[...values,id,userId]);
    else id=(await this.db.run('INSERT INTO empresas(nombre,iniciales,color_emblema,dui,nit,nrc,tipo_legal,id_usuario) VALUES (?,?,?,?,?,?,?,?)',[...values,userId])).insertId;
    return this.company(id,userId);
  }
  async deleteCompany(id: number,userId: number) {await this.company(id,userId);await this.db.run('UPDATE empresas SET estado=0 WHERE id=? AND id_usuario=?',[id,userId]);return null;}
  async book(id: number,userId: number,connection?: PoolConnection) {
    const [book]=await this.db.rows<Record<string,any>>(`SELECT l.*,e.nombre AS empresa_nombre FROM libros l JOIN empresas e ON e.id=l.id_empresa
      WHERE l.id=? AND l.id_usuario=? AND e.id_usuario=? AND e.estado=1 AND l.estado=1`,[id,userId,userId],connection);
    if (!book) throw new ForbiddenException('Libro no encontrado o sin permiso.');
    return book;
  }
  async books(userId: number,query: Record<string,unknown>) {
    const data=validate(z.object({id_empresa:idSchema.optional(),tipo:z.enum(['compras','ventas_consumidor','ventas_contribuyente','retencion_iva']).optional()}),query);
    if (data.id_empresa) {await this.company(data.id_empresa,userId);await this.db.run('UPDATE empresas SET ultima_vez_usada=CURRENT_TIMESTAMP WHERE id=? AND id_usuario=?',[data.id_empresa,userId]);}
    return this.db.rows(`SELECT l.*,e.nombre AS empresa_nombre FROM libros l JOIN empresas e ON e.id=l.id_empresa WHERE l.id_usuario=? AND e.id_usuario=? AND l.estado=1 AND e.estado=1${data.id_empresa?' AND l.id_empresa=?':''}${data.tipo?' AND l.tipo=?':''} ORDER BY l.anio DESC,l.mes DESC,l.id DESC`,[userId,userId,...(data.id_empresa?[data.id_empresa]:[]),...(data.tipo?[data.tipo]:[])]);
  }
  async createBook(userId: number,input: unknown) {
    const data=validate(bookSchema,input);
    await this.company(data.id_empresa,userId);
    await this.db.run('UPDATE empresas SET ultima_vez_usada=CURRENT_TIMESTAMP WHERE id=? AND id_usuario=?',[data.id_empresa,userId]);
    try {
      const result=await this.db.run('INSERT INTO libros(id_empresa,id_usuario,tipo,mes,anio) VALUES (?,?,?,?,?)',[data.id_empresa,userId,data.tipo,data.mes,data.anio]);
      return {book:await this.book(result.insertId,userId),exists:false};
    } catch(error) {
      if ((error as {code?:string}).code !== 'ER_DUP_ENTRY') throw error;
      const [existing]=await this.db.rows<{id:number}>('SELECT id FROM libros WHERE id_empresa=? AND tipo=? AND mes=? AND anio=?',[data.id_empresa,data.tipo,data.mes,data.anio]);
      return {book:await this.book(existing.id,userId),exists:true};
    }
  }
  async quota(userId: number,connection?: PoolConnection) {
    await this.db.run('INSERT IGNORE INTO facturas_disponibles(id_usuario,total,consumidas) VALUES (?,50,0)',[userId],connection);
    const [quota]=await this.db.rows<{total:number;consumidas:number}>('SELECT total,consumidas FROM facturas_disponibles WHERE id_usuario=?'+(connection?' FOR UPDATE':''),[userId],connection);
    return {...quota,disponibles:Math.max(0,quota.total-quota.consumidas),porcentaje:quota.total ? Math.round(quota.consumidas/quota.total*1000)/10 : 0};
  }
  async invoices(userId: number,id: number,query:Record<string,unknown>={}) {
    const book=await this.book(id,userId);
    const filter=validate(z.object({q:z.string().trim().max(100).default(''),from:isoDate.optional(),to:isoDate.optional()}),query);
    if(filter.from&&filter.to&&filter.from>filter.to)throw new UnprocessableEntityException('El rango de fechas no es valido.');
    const where=`id_libro=? AND id_usuario=? AND (codigo_generacion LIKE ? OR COALESCE(nombre_proveedor,'') LIKE ? OR COALESCE(nombre_cliente,'') LIKE ?)${filter.from?' AND fecha>=?':''}${filter.to?' AND fecha<=?':''}`;
    const values=[id,userId,...Array(3).fill(`%${filter.q}%`),...(filter.from?[filter.from]:[]),...(filter.to?[filter.to]:[])];
    const records=await this.db.rows<Record<string,any>>(`SELECT * FROM facturas WHERE ${where} ORDER BY fecha,id`,values);
    const [totals]=await this.db.rows<Record<string,string>>(`SELECT ${amountColumns.map(column=>`COALESCE(SUM(${column}),0) AS ${column}`).join(',')} FROM facturas WHERE ${where}`,values);
    return {libro:book,...report(book,records),documentos:records,totales:totals,cantidad_facturas:records.length};
  }
  async manual(userId:number,input:unknown){
    const money=z.string().regex(/^\d{1,8}(\.\d{1,2})?$/,'Importe no valido: hasta dos decimales.');
    const data=validate(z.object({id_libro:idSchema,codigo_generacion:z.string().trim().min(1).max(50),numero_control:z.string().trim().max(50).default(''),fecha:isoDate,tipo_dte:z.enum(['01','03','05','06','14']),nombre:z.string().trim().min(2).max(200),nit:z.string().trim().max(20).default(''),nrc:z.string().trim().max(20).default(''),base:money,exenta:money.default('0'),impuesto:money.default('0')}),input);
    return this.db.transaction(async conn=>{
      const book=await this.book(data.id_libro,userId,conn);
      if(!validTypes[book.tipo].includes(data.tipo_dte))throw new UnprocessableEntityException('Tipo de documento no valido para este libro.');
      const quota=await this.quota(userId,conn);if(quota.disponibles<1)throw new UnprocessableEntityException('No tienes cuota suficiente.');
      const base=decimal(cents(data.base)),exempt=decimal(cents(data.exenta)),tax=decimal(cents(data.impuesto)),total=decimal(cents(data.base)+cents(data.exenta)+cents(data.impuesto));
      if(cents(total)>9999999999n)throw new UnprocessableEntityException('El total excede el importe permitido.');
      const payload={identificacion:{codigoGeneracion:data.codigo_generacion,tipoDte:data.tipo_dte,fecEmi:data.fecha,numeroControl:data.numero_control},emisor:{nombre:data.nombre,nit:data.nit,nrc:data.nrc},receptor:{nombre:data.nombre,nit:data.nit,nrc:data.nrc},resumen:{totalGravada:base,totalExenta:exempt,totalIva:tax,totalPagar:total},origen:'registro_manual'};
      const row=mapDocument(payload,book.tipo);
      if(book.tipo==='compras')Object.assign(row,{ventas_internas:base,ventas_internas_exentas:exempt,credito_fiscal:tax,total_compras:total});
      if(book.tipo==='ventas_consumidor')Object.assign(row,{ventas_internas_gravadas:base,ventas_exentas:exempt,total_ventas_diarias_propias:decimal(cents(base)+cents(exempt))});
      if(book.tipo==='ventas_contribuyente')Object.assign(row,{ventas_internas_gravadas_contribuyente:base,ventas_exentas_contribuyente:exempt,debito_fiscal_contribuyente:tax,ventas_totales:total});
      if(book.tipo==='retencion_iva')Object.assign(row,{monto_sujeto_retencion:base,retencion_iva_1:tax,iva_retenido:tax,nit_agente_retencion:data.nit,numero_documento:data.numero_control});
      Object.assign(row,{id_libro:book.id,id_usuario:userId});const keys=Object.keys(row);
      const result=await this.db.run(`INSERT INTO facturas (${keys.map(key=>'`'+key+'`').join(',')}) VALUES (${keys.map(()=>'?').join(',')})`,keys.map(key=>row[key]),conn);
      await this.db.run('UPDATE facturas_disponibles SET consumidas=consumidas+1 WHERE id_usuario=?',[userId],conn);
      return (await this.db.rows('SELECT * FROM facturas WHERE id=?',[result.insertId],conn))[0];
    });
  }
  async import(userId: number,input: unknown) {
    const data=validate(z.object({id_libro:idSchema,documentos:z.unknown().optional(),facturas:z.unknown().optional()}).refine(value=>value.documentos!==undefined||value.facturas!==undefined,'Selecciona documentos JSON.'),input);
    const documents=expand(data.documentos ?? data.facturas);
    if (documents.length>500) throw new UnprocessableEntityException('Importa hasta 500 documentos por lote.');
    return this.db.transaction(async connection=>{
      const book=await this.book(data.id_libro,userId,connection);
      const quota=await this.quota(userId,connection); // Bloquea por usuario: evita descontar cuota dos veces en concurrencia.
      const duplicated: Record<string,any>[]=[],invalid: Record<string,any>[]=[],rows: Record<string,any>[]=[],seen=new Set<string>();
      for (const document of documents) {
        try {
          const row=mapDocument(document.payload,book.tipo);
          if (!row.codigo_generacion || String(row.codigo_generacion).length>50) throw new Error('El documento no trae codigo de generacion valido.');
          if (!validTypes[book.tipo].includes(row.tipo_dte)) throw new Error(`Tipo de DTE no valido para libro ${book.tipo}.`);
          if (seen.has(row.codigo_generacion) || (await this.db.rows('SELECT id FROM facturas WHERE id_libro=? AND codigo_generacion=?',[book.id,row.codigo_generacion],connection)).length) {duplicated.push({archivo:document.archivo,codigo_generacion:row.codigo_generacion,razon:'El documento ya existe en este libro o lote.'});continue;}
          seen.add(row.codigo_generacion);rows.push({...row,id_libro:book.id,id_usuario:userId});
        }catch(error){invalid.push({archivo:document.archivo,razon:(error as Error).message});}
      }
      if (rows.length>quota.disponibles) throw new UnprocessableEntityException('No tienes cuota suficiente para importar los documentos nuevos.');
      for (const row of rows) {
        const keys=Object.keys(row); // Todas las claves proceden de mapDocument, nunca del objeto enviado por el cliente.
        await this.db.run(`INSERT INTO facturas (${keys.map(key=>'`'+key+'`').join(',')}) VALUES (${keys.map(()=>'?').join(',')})`,keys.map(key=>row[key]),connection);
      }
      if (rows.length) await this.db.run('UPDATE facturas_disponibles SET consumidas=consumidas+? WHERE id_usuario=?',[rows.length,userId],connection);
      return {importadas:rows.length,importables:rows.length,duplicadas:duplicated,invalidas:invalid,tipos_validos:validTypes[book.tipo],cuota_restante:quota.disponibles-rows.length};
    });
  }
}

@Controller('api')
@UseGuards(AuthGuard,PlanGuard)
export class BusinessController {
  constructor(@Inject(BusinessService) private readonly business: BusinessService) {}
  @Get('empresas') async companies(@Req() req: AuthRequest) {return ok(await this.business.companies(req.user.id));}
  @Post('empresas') async createCompany(@Req() req: AuthRequest,@Body() body: unknown) {return ok(await this.business.saveCompany(req.user.id,body),'Empresa creada correctamente.');}
  @Patch('empresas/:id') async updateCompany(@Req() req: AuthRequest,@Param('id') id: string,@Body() body: unknown) {return ok(await this.business.saveCompany(req.user.id,body,validate(idSchema,id)));}
  @Delete('empresas/:id') async deleteCompany(@Req() req: AuthRequest,@Param('id') id: string) {return ok(await this.business.deleteCompany(validate(idSchema,id),req.user.id),'Empresa eliminada.');}
  @Get('libros') async books(@Req() req: AuthRequest,@Query() query: Record<string,unknown>) {return ok(await this.business.books(req.user.id,query));}
  @Post('libros') async createBook(@Req() req: AuthRequest,@Body() body: unknown) {const {book,exists}=await this.business.createBook(req.user.id,body);return {...ok(book,exists?'El libro ya existia para ese periodo.':'Libro creado correctamente.'),exists};}
  @Get('cuota/estado') async quota(@Req() req: AuthRequest) {return ok(await this.business.quota(req.user.id));}
  @Get('facturas') async invoices(@Req() req: AuthRequest,@Query('id_libro') id: string,@Query() query:Record<string,unknown>) {return ok(await this.business.invoices(req.user.id,validate(idSchema,id),query));}
  @Post('facturas/manual') async manual(@Req() req:AuthRequest,@Body() body:unknown){return ok(await this.business.manual(req.user.id,body),'Documento guardado.');}
  @Post('facturas/importar') async import(@Req() req: AuthRequest,@Body() body: unknown) {return ok(await this.business.import(req.user.id,body));}
  @Get('facturas/exportar') async export(@Req() req: AuthRequest,@Query('id_libro') id: string,@Query('formato') format: string,@Query('modo') mode: string,@Res() response: Response) {
    const data=await this.business.invoices(req.user.id,validate(idSchema,id));
    if (mode==='json') return response.json(ok({columnas:data.columnas,filas:data.filas,libro:data.libro,formato:format||'excel'}));
    if (!format || ['excel','xls','pdf'].includes(format)) {
      const html=reportHtml(data.libro,data.documentos);
      if (format==='pdf') return response.type('text/html; charset=utf-8').send(html);
      return response.type('application/vnd.ms-excel; charset=utf-8').attachment(`zentra-${data.libro.tipo}-${data.libro.anio}-${data.libro.mes}.xls`).send(html);
    }
    if (format!=='csv') throw new UnprocessableEntityException('Formato no valido. Usa excel, pdf o csv.');
    const columns=['codigo_generacion','tipo_dte','fecha','numero_control','nombre_proveedor','nombre_cliente',...amountColumns];
    const cell=(value: unknown)=>'"'+String(value ?? '').replace(/^[=+@-]/,"'$&").replaceAll('"','""')+'"';
    const csv='\uFEFF'+[columns,...data.documentos.map(row=>columns.map(column=>row[column]))].map(row=>row.map(cell).join(';')).join('\r\n');
    response.type('text/csv; charset=utf-8').attachment(`zentra-${data.libro.tipo}-${data.libro.anio}-${data.libro.mes}.csv`).send(csv);
  }
}
@Module({controllers:[BusinessController],providers:[BusinessService,PlanGuard],exports:[BusinessService]})
export class BusinessModule {}
