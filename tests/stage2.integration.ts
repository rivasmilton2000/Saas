import assert from 'node:assert/strict';
import {createHash} from 'node:crypto';
import {AccountingService} from '../src/backend/accounting';
import {seedDemo} from '../scripts/seed-demo';
import {migrate} from '../conexion/migrate';
import {testEnvironment,demoPasswords} from './test-environment';
import {contactServices,contactSubject,contactSubjectLimit,type ContactService} from '../src/shared/public-services';
import {MailService} from '../src/backend/public';

async function main(){
  const env=await testEnvironment();
  try{
    await env.app.listen(0,'127.0.0.1');const address=env.app.getHttpServer().address();assert(address&&typeof address!=='string');const base=`http://127.0.0.1:${address.port}`;
    const snapshot=async()=>{const tables=await env.db.rows<Record<string,string>>('SHOW TABLES');const values=[];for(const row of tables){const table=Object.values(row)[0];values.push([table,await env.db.rows(`SELECT * FROM \`${table}\` ORDER BY 1`)]);}return createHash('sha256').update(JSON.stringify(values)).digest('hex');};
    const before=await snapshot();await seedDemo(env.db,env.app.get(AccountingService),demoPasswords);assert.equal(await snapshot(),before,'El seed conserva todas las filas y hashes');await migrate(env.db);assert.equal(await snapshot(),before,'Las migraciones conservan los datos');
    assert.equal((await env.db.rows('SELECT id FROM usuarios')).length,2,'Exactamente dos cuentas demo');
    class Client{
      cookie='';csrf='';
      async call(path:string,body?:unknown,expected=200,method='POST'){
        const response=await fetch(base+(path.startsWith('/src/')?path:'/api'+path),{method:body===undefined?'GET':method,headers:{cookie:this.cookie,...(body===undefined?{}:{'content-type':'application/json','x-csrf-token':this.csrf})},body:body===undefined?undefined:JSON.stringify(body)});
        const cookie=response.headers.getSetCookie();if(cookie.length)this.cookie=cookie.map(value=>value.split(';')[0]).join('; ');
        const data=await response.json();assert.equal(response.status,expected,`${path}: ${JSON.stringify(data)}`);if(data.data?.csrf)this.csrf=data.data.csrf;return data.data;
      }
      async init(){await this.call('/auth/csrf');return this;}
    }
    const guest=await new Client().init();await guest.call('/health');await guest.call('/accounting/overview?company_id='+env.demo.companies[0],undefined,401);
    const plans=await guest.call('/public/plans');assert.equal(plans.length,5);
    const free=plans.find((plan:any)=>plan.slug==='free'),paid=plans.find((plan:any)=>Number(plan.precio)>0);
    const registration={nombre_completo:'Registro publico de prueba',email:'registro-publico@example.com',username:'registro_publico',password:'RegistroPublico!2026',confirm_password:'RegistroPublico!2026',pais:'El Salvador',id_plan:free.id_plan,terms:true,rol:'admin'};
    const registered=await new Client().init();await registered.call('/auth/register',{...registration,terms:false},422);
    const created=await registered.call('/auth/register',registration,201);assert.equal(created.redirect,'/app');assert.equal(created.user.rol,'user','El registro publico no permite elegir rol admin');
    await registered.call('/admin/contacts',undefined,403);await registered.call('/auth/logout',{});await registered.call('/auth/me',undefined,401);
    await registered.init();assert.equal((await registered.call('/auth/login',{username:registration.email,password:registration.password})).user.username,registration.username);await registered.call('/auth/logout',{});
    await guest.call('/auth/register',registration,422);
    const pendingBefore=(await env.db.rows('SELECT id FROM registros_pago_pendientes')).length;
    await guest.call('/auth/register',{...registration,username:'registro_pago',email:'registro-pago@example.com',id_plan:paid.id_plan},503);assert.equal((await env.db.rows('SELECT id FROM registros_pago_pendientes')).length,pendingBefore,'Sin Stripe no crea registros de pago parciales');
    await guest.call('/auth/google',{credential:'sin-configuracion'},403);
    const contact={name:'Cliente de prueba',email:'servicios@example.com',subject:'Consulta de servicio',message:'Necesito informacion para mi negocio.'};
    for(const service of Object.keys(contactServices) as ContactService[]){const result=await guest.call('/public/contact',{...contact,service},201);assert.equal(result.saved,true);assert.equal(result.emailSent,false);assert.equal(result.service,service);const [saved]=await env.db.rows<{asunto:string;mensaje:string}>('SELECT asunto,mensaje FROM mensajes_contacto WHERE email=? ORDER BY id DESC LIMIT 1',[contact.email]);assert.equal(saved.asunto,contactSubject(service,contact.subject));assert.equal(saved.mensaje,contact.message);}
    await guest.call('/public/contact',contact,201);assert.equal((await env.db.rows<{asunto:string}>('SELECT asunto FROM mensajes_contacto WHERE email=? ORDER BY id DESC LIMIT 1',[contact.email]))[0].asunto,contact.subject,'Clientes anteriores siguen funcionando sin service');
    const contactsBefore=(await env.db.rows('SELECT id FROM mensajes_contacto')).length;
    await guest.call('/public/contact',{...contact,service:'no-definido'},422);await guest.call('/public/contact',{...contact,email:'correo-invalido'},422);await guest.call('/public/contact',{...contact,name:'   '},422);await guest.call('/public/contact',{...contact,service:'hosting',subject:'x'.repeat(contactSubjectLimit('hosting')+1)},422);assert.equal((await env.db.rows('SELECT id FROM mensajes_contacto')).length,contactsBefore,'Validacion antes de escribir');
    await guest.call('/public/contact',{...contact,service:'dominios',subject:'x'.repeat(contactSubjectLimit('dominios'))},201);assert.equal((await env.db.rows<{asunto:string}>('SELECT asunto FROM mensajes_contacto WHERE email=? ORDER BY id DESC LIMIT 1',[contact.email]))[0].asunto.length,150);
    const mail=env.app.get(MailService),send=mail.send;let preparedSubject='';mail.send=async(_to,subject)=>{preparedSubject=subject;};try{const sent=await guest.call('/public/contact',{...contact,service:'diseno-web'},201);assert(sent.emailSent);assert.equal(preparedSubject,'Consulta: '+contactSubject('diseno-web',contact.subject));}finally{mail.send=send;}
    const admin=await new Client().init(),user=await new Client().init();assert.equal((await admin.call('/auth/login',{username:'demo_admin',password:demoPasswords.admin})).redirect,'/admin');assert.equal((await user.call('/auth/login',{username:'demo_usuario',password:demoPasswords.user})).redirect,'/app');
    await user.call('/admin/overview',undefined,403);await user.call('/admin/users',undefined,403);await admin.call('/accounting/accounts?company_id='+env.demo.companies[0],undefined,403);
    await user.call('/dashboard');await user.call('/src/api/dashboard.php');assert.equal((await admin.call('/admin/payments')).length,1);assert.equal((await admin.call('/admin/subscriptions')).length,1);
    const [company,otherCompany]=env.demo.companies,range=`company_id=${company}&from=${env.demo.date.slice(0,4)}-01-01&to=${env.demo.date}`;
    const summary=await user.call('/accounting/overview?'+range);assert.equal(summary.indicadores.ingresos,'100.00');assert.equal(summary.indicadores.gastos,'25.00');assert.equal(summary.indicadores.resultado,'75.00');assert.equal(summary.indicadores.efectivo,'4888.00');assert.equal(summary.indicadores.activos,'5088.00');assert.equal(summary.totales.diferencia,'0.00');assert.equal(summary.indicadores.documentos,4);
    const accounts=await user.call('/accounting/accounts?company_id='+company),foreignAccounts=await user.call('/accounting/accounts?company_id='+otherCompany);
    const byCode=Object.fromEntries(accounts.map((row:any)=>[row.codigo,row.id]));
    const entry={id_empresa:company,fecha:env.demo.date,descripcion:'Operacion exacta de centavos',referencia:'PRUEBA-CENTAVOS',lineas:[{id_cuenta:byCode['1101'],debe:'0.10',haber:'0'},{id_cuenta:byCode['1101'],debe:'0.20',haber:'0'},{id_cuenta:byCode['4101'],debe:'0',haber:'0.30'}]};
    const count=await env.db.rows('SELECT * FROM contabilidad_asientos');
    await user.call('/accounting/entries',{...entry,lineas:[{id_cuenta:byCode['1101'],debe:'0.10',haber:'0'},{id_cuenta:byCode['4101'],debe:'0',haber:'0.20'}]},422);
    await user.call('/accounting/entries',{...entry,lineas:[{id_cuenta:foreignAccounts[0].id,debe:'0.30',haber:'0'},{id_cuenta:byCode['4101'],debe:'0',haber:'0.30'}]},403);
    assert.equal((await env.db.rows('SELECT * FROM contabilidad_asientos')).length,count.length,'No hay asientos parciales');
    const posted=await user.call('/accounting/entries',entry,201);const detail=await user.call('/accounting/entries/'+posted.id);assert.equal(detail.lineas.length,3);
    const fresh=await user.call('/accounting/overview?'+range);assert.equal(fresh.indicadores.resultado,'75.30');assert.equal(fresh.totales.diferencia,'0.00');assert.deepEqual(await user.call('/accounting/overview?'+range),fresh,'Totales al recargar');
    assert.equal((await user.call('/accounting/entries?'+range+'&q=PRUEBA-CENTAVOS')).length,1);
    await user.call(`/accounting/entries/${posted.id}/void`,{motivo:'Correccion de prueba',version:posted.version},201);await user.call(`/accounting/entries/${posted.id}/void`,{motivo:'Intento repetido',version:posted.version},409);
    assert.equal((await user.call('/accounting/overview?'+range)).indicadores.resultado,'75.00');
    const custom=await user.call('/accounting/accounts',{id_empresa:company,codigo:'5199',nombre:'Cuenta adicional de prueba',tipo:'gasto'},201);await user.call('/accounting/accounts/'+custom.id,{nombre:custom.nombre,activo:false},200,'PATCH');
    await user.call('/accounting/entries',{...entry,lineas:[{id_cuenta:custom.id,debe:'0.30',haber:'0'},{id_cuenta:byCode['1101'],debe:'0',haber:'0.30'}]},403);
    const other=await admin.call('/admin/users',{username:'otro_propietario',rol:'user',password:'OtherOwner!2026',confirm_password:'OtherOwner!2026'},201);
    const edited=await admin.call('/admin/users/'+other.id,{username:'otro_propietario',nombre_completo:'Propietario editado',email:null,rol:'user'},200,'PATCH');assert.equal(edited.nombre_completo,'Propietario editado');assert.equal(edited.email,null);
    const outsider=await new Client().init();await outsider.call('/auth/login',{username:'otro_propietario',password:'OtherOwner!2026'});
    for(const path of ['/accounting/overview?'+range,'/accounting/accounts?company_id='+company,'/accounting/entries/'+posted.id,'/facturas?id_libro=1'])await outsider.call(path,undefined,403);
    await outsider.call('/accounting/entries',entry,403);
    await outsider.call(`/empresas/${company}`,{nombre:'No autorizado'},403,'PATCH');
    const books=await user.call('/libros?id_empresa='+company);assert.equal(books.length,4);const purchase=books.find((book:any)=>book.tipo==='compras');
    const manual={id_libro:purchase.id,codigo_generacion:'MANUAL-PRUEBA-01',fecha:env.demo.date,tipo_dte:'03',nombre:'Proveedor Prueba',base:'0.10',exenta:'0.20',impuesto:'0.03'};
    await user.call('/facturas/manual',manual,201);await user.call('/facturas/manual',manual,409);
    const invoices=await user.call('/facturas?id_libro='+purchase.id);assert.equal(invoices.totales.total_compras,'113.33');assert.equal((await user.call('/facturas?id_libro='+purchase.id+'&q=MANUAL-PRUEBA-01')).totales.total_compras,'0.33');assert.equal((await user.call('/cuota/estado')).consumidas,5,'Duplicado no descuenta cuota');
    for(const [type,typeDte,key,total] of [['ventas_consumidor','01','total_ventas_diarias_propias','100.30'],['ventas_contribuyente','03','ventas_totales','113.33'],['retencion_iva','14','retencion_iva_1','1.03']]){
      const book=books.find((book:any)=>book.tipo===type);await user.call('/facturas/manual',{...manual,id_libro:book.id,tipo_dte:typeDte,codigo_generacion:'MANUAL-'+type},201);assert.equal((await user.call('/facturas?id_libro='+book.id)).totales[key],total);
    }
    for(const path of ['overview','users','plans','companies','payments','subscriptions','audit','contacts','settings'])await admin.call('/admin/'+path);
    await admin.call('/admin/users/'+other.id,{},200,'DELETE');await outsider.call('/auth/me',undefined,403);
    await admin.call('/admin/users/'+other.id+'/activate',{},201);await outsider.call('/auth/me');
    const modified=await snapshot();await seedDemo(env.db,env.app.get(AccountingService),demoPasswords);assert.equal(await snapshot(),modified,'Reejecutar seed no borra cambios contables');
    const foreignByCode=Object.fromEntries(foreignAccounts.map((row:any)=>[row.codigo,row.id]));
    await user.call('/accounting/entries',{id_empresa:otherCompany,fecha:env.demo.date,descripcion:'Importes acumulados de alta precision',lineas:Array.from({length:49},()=>[{id_cuenta:foreignByCode['1102'],debe:'9000000000000.01',haber:'0'},{id_cuenta:foreignByCode['3101'],debe:'0',haber:'9000000000000.01'}]).flat()},201);
    const large=await user.call(`/accounting/overview?company_id=${otherCompany}&to=${env.demo.date}`);assert.equal(large.indicadores.activos,'441000000001750.49');assert.equal(large.totales.diferencia,'0.00');
    await user.call('/auth/logout',{});await user.call('/auth/me',undefined,401);
    console.log('PASS: MySQL compartido, migraciones, seed repetible, registro publico, contacto por servicio, dos logins y logout, permisos por rol/propietario/empresa, diario exacto, anulacion, balance persistente, catalogo y libros manuales/filtros/cuota.');
  }finally{await env.close();console.log('Base temporal eliminada.');}
}
main().catch(error=>{console.error(error);process.exitCode=1;});
