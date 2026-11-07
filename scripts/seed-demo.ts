import 'reflect-metadata';
import {NestFactory} from '@nestjs/core';
import {config} from 'dotenv';
import {resolve} from 'node:path';
import {compare,hash} from 'bcryptjs';
import {MysqlService} from '../conexion/conexiones';
import {AppModule} from '../src/backend/app.module';
import {AccountingService,today} from '../src/backend/accounting';
import {mapDocument} from '../src/backend/dte-data';
import {User} from '../src/backend/auth';

export async function seedDemo(db:MysqlService,accounting:AccountingService,passwords:{admin:string;user:string}){
  if(process.env.NODE_ENV==='production')throw new Error('El seed de demostracion solo se permite en desarrollo o pruebas.');
  for(const value of Object.values(passwords))if(!value||Buffer.byteLength(value)<12||Buffer.byteLength(value)>72)throw new Error('Configura ambas claves demo con entre 12 y 72 bytes en .env.demo.');
  const hashes={admin:await hash(passwords.admin,12),user:await hash(passwords.user,12)};
  const date=today(),year=Number(date.slice(0,4)),month=Number(date.slice(5,7));
  return db.transaction(async conn=>{
    const [lock]=await db.rows<{locked:number}>("SELECT GET_LOCK('zentra:demo:stage2',15) locked",[],conn);
    if(lock.locked!==1)throw new Error('Otro seed esta en ejecucion.');
    try{
      const remembered=async(key:string,table:string,create:()=>Promise<number>)=>{
        const [marker]=await db.rows<{registro_id:number;tabla:string}>('SELECT registro_id,tabla FROM zentra_demo_registros WHERE clave=?',[key],conn);
        if(marker){if(marker.tabla!==table)throw new Error('Marcador demo incompatible.');return marker.registro_id;}
        const id=await create();await db.run('INSERT INTO zentra_demo_registros(clave,tabla,registro_id) VALUES (?,?,?)',[key,table,id],conn);return id;
      };
      const [plan]=await db.rows<{id_plan:number}>('SELECT id_plan FROM planes WHERE slug=\'free\' AND activo=1',[],conn);if(!plan)throw new Error('Falta el plan Free activo. Ejecuta las migraciones.');
      const ids:{admin:number;user:number}={admin:0,user:0};
      for(const role of ['admin','user'] as const){
        const username=role==='admin'?'demo_admin':'demo_usuario',email=role==='admin'?'admin.demo@zentra.example':'usuario.demo@zentra.example';
        ids[role]=await remembered(`cuenta:${role}`,'usuarios',async()=>{
          if((await db.rows('SELECT id FROM usuarios WHERE username=? OR email=?',[username,email],conn)).length)throw new Error(`La cuenta ${username} ya existe sin marcador demo; no se modifico.`);
          return (await db.run("INSERT INTO usuarios(username,nombre_completo,email,password,rol,estado,pais,id_plan,auth_provider,suscripcion_estado) VALUES (?,?,?,?,?,1,'El Salvador',?,'local',?)",[username,role==='admin'?'Administrador Demo':'Usuario Demo',email,hashes[role],role,role==='user'?plan.id_plan:null,role==='admin'?'admin':'free'],conn)).insertId;
        });
        const [account]=await db.rows<User>('SELECT * FROM usuarios WHERE id=?',[ids[role]],conn);
        if(!account||account.username!==username||account.rol!==role)throw new Error(`La identidad demo ${username} cambio. No se sobrescriben cuentas.`);
        if(!await compare(passwords[role],account.password||''))throw new Error(`La clave configurada no coincide con ${username}. El seed conserva la clave existente.`);
      }
      const user=(await db.rows<User>('SELECT * FROM usuarios WHERE id=?',[ids.user],conn))[0];
      const companies:number[]=[];
      for(const [index,name] of ['Café Aurora Demo','Estudio Nube Demo'].entries()){
        const companyId=await remembered(`empresa:${index}`,'empresas',async()=> (await db.run("INSERT INTO empresas(id_usuario,nombre,iniciales,color_emblema,nit,nrc,tipo_legal,ultima_vez_usada) VALUES (?,?,?,?,?,?,'juridica',?)",[user.id,name,index?'EN':'CA',index?'#8b5cf6':'#1f3bb3',index?'0614-010101-002-2':'0614-010101-001-1',index?'DEMO-002':'DEMO-001',index?null:`${date} 12:00:00`],conn)).insertId);
        const company=await accounting.company(companyId,user.id,conn);companies.push(company.id);
        const accounts=await accounting.initialize(companyId,user.id,conn),byCode=Object.fromEntries(accounts.map(account=>[account.codigo,account.id]));
        const journals=index?[
          {key:'apertura',description:'Capital inicial de Estudio Nube',lines:[['1102','1500.00','0'],['3101','0','1500.00']]},
          {key:'venta',description:'Servicios de diseño cobrados',lines:[['1102','250.00','0'],['4101','0','250.00']]},
        ]:[
          {key:'apertura',description:'Aporte inicial de capital',lines:[['1102','5000.00','0'],['3101','0','5000.00']]},
          {key:'venta',description:'Venta cobrada en caja',lines:[['1101','113.00','0'],['4101','0','100.00'],['2102','0','13.00']]},
          {key:'gasto',description:'Pago de servicios del local',lines:[['5102','25.00','0'],['1101','0','25.00']]},
          {key:'compra',description:'Compra de inventario por transferencia',lines:[['1202','200.00','0'],['1102','0','200.00']]},
        ];
        for(const journal of journals)await remembered(`asiento:${index}:${journal.key}`,'contabilidad_asientos',async()=> (await accounting.createEntry(user,{id_empresa:companyId,fecha:date,descripcion:journal.description,referencia:`DEMO-${index}-${journal.key}`,lineas:journal.lines.map(([code,debe,haber])=>({id_cuenta:byCode[code],debe,haber,detalle:''}))},conn)).id);
        if(index===0){
          for(const [bookIndex,type] of ['compras','ventas_consumidor','ventas_contribuyente','retencion_iva'].entries()){
            const bookId=await remembered(`libro:${type}`,'libros',async()=> (await db.run('INSERT INTO libros(id_empresa,id_usuario,tipo,mes,anio) VALUES (?,?,?,?,?)',[companyId,user.id,type,month,year],conn)).insertId);
            await remembered(`factura:${type}`,'facturas',async()=>{
              const payload={identificacion:{codigoGeneracion:`DEMO-DTE-${bookIndex+1}`,tipoDte:type==='ventas_consumidor'?'01':type==='retencion_iva'?'14':'03',fecEmi:date,numeroControl:`DEMO-2026-000${bookIndex+1}`},
                emisor:{nombre:'Proveedor Ficticio Demo',nit:'0614-010101-003-3',nrc:'DEMO-003'},receptor:{nombre:'Cliente Ficticio Demo',nrc:'DEMO-004'},resumen:{totalGravada:'100.00',totalIva:'13.00',totalPagar:'113.00',ivaRete1:type==='retencion_iva'?'1.00':'0.00'},documentoRelacionado:[{tipoDocumento:'03',numeroDocumento:'DEMO-001',montoSujetoRetencion:'100.00'}]};
              const row={...mapDocument(payload,type),id_libro:bookId,id_usuario:user.id},keys=Object.keys(row) as (keyof typeof row)[];
              return (await db.run(`INSERT INTO facturas(${keys.map(key=>'`'+key+'`').join(',')}) VALUES (${keys.map(()=>'?').join(',')})`,keys.map(key=>row[key]),conn)).insertId;
            });
          }
          await remembered('cuota:user','facturas_disponibles',async()=> (await db.run('INSERT INTO facturas_disponibles(id_usuario,total,consumidas) VALUES (?,50,4)',[user.id],conn)).insertId);
        }
      }
      await remembered('contacto:demo','mensajes_contacto',async()=> (await db.run('INSERT INTO mensajes_contacto(nombre,email,asunto,mensaje) VALUES (?,?,?,?)',['Cliente Ficticio','cliente@zentra.example','Consulta de demostración','Mensaje ficticio para probar la bandeja administrativa.'],conn)).insertId);
      const [historyPlan]=await db.rows<{id_plan:number}>("SELECT id_plan FROM planes WHERE slug='light'",[],conn);
      const subscriptionId=await remembered('suscripcion:demo','subscriptions',async()=> (await db.run("INSERT INTO subscriptions(user_id,plan_id,stripe_subscription_id,status,amount,currency,billing_interval) VALUES (?,?,?,'canceled','6.99','USD','month')",[user.id,historyPlan?.id_plan||plan.id_plan,'DEMO-SUBSCRIPTION-STAGE2'],conn)).insertId);
      await remembered('pago:demo','payments',async()=> (await db.run("INSERT INTO payments(user_id,plan_id,subscription_id,stripe_invoice_id,amount,currency,status) VALUES (?,?,?,?,'6.99','USD','paid')",[user.id,historyPlan?.id_plan||plan.id_plan,subscriptionId,'DEMO-INVOICE-STAGE2'],conn)).insertId);
      return {users:ids,companies,date};
    }finally{await db.rows("SELECT RELEASE_LOCK('zentra:demo:stage2')",[],conn);}
  });
}
if(require.main===module){
  config({path:resolve('.env.demo'),quiet:true});
  (async()=>{const app=await NestFactory.createApplicationContext(AppModule,{logger:['error']});try{const result=await seedDemo(app.get(MysqlService),app.get(AccountingService),{admin:process.env.ZENTRA_DEMO_ADMIN_PASSWORD||'',user:process.env.ZENTRA_DEMO_USER_PASSWORD||''});console.log(`Demo preparada sin duplicados: demo_admin y demo_usuario. Fecha de datos: ${result.date}. Las claves no se imprimen.`);}finally{await app.close();}})().catch(error=>{console.error(error.message);process.exitCode=1;});
}
