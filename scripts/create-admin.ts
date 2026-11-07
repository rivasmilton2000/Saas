import 'reflect-metadata';
import {NestFactory} from '@nestjs/core';
import {randomBytes} from 'node:crypto';
import {hash} from 'bcryptjs';
import {ConexionModule,MysqlService} from '../conexion/conexiones';

export async function createAdmin(db:MysqlService,username:string,password?:string) {
  if(!/^[A-Za-z0-9._-]{3,50}$/.test(username))throw new Error('Usuario no valido: usa de 3 a 50 letras, numeros, punto, guion o guion bajo.');
  const [existing]=await db.rows<{id:number}>('SELECT id FROM usuarios WHERE username=?',[username]);
  if(existing)return {id:existing.id,created:false};
  const secret=password || randomBytes(24).toString('base64url');
  if(Buffer.byteLength(secret)<12 || Buffer.byteLength(secret)>72)throw new Error('ZENTRA_ADMIN_PASSWORD debe tener entre 12 y 72 bytes.');
  const result=await db.run("INSERT INTO usuarios(username,password,rol,estado,auth_provider,suscripcion_estado) VALUES (?,?,'admin',1,'local','admin')",[username,await hash(secret,12)]);
  return {id:result.insertId,created:true,...(!password?{password:secret}:{})};
}
if(require.main===module) {
  (async()=>{
    const app=await NestFactory.createApplicationContext(ConexionModule,{logger:['error']});
    try {
      const result=await createAdmin(app.get(MysqlService),process.argv[2] || 'zentra_admin',process.env.ZENTRA_ADMIN_PASSWORD);
      console.log(result.created?'Administrador creado.':'La cuenta ya existe; se conserva su clave y configuracion.');
      if(result.password)console.log(`Guarda esta clave privada; solo se muestra una vez: ${result.password}`);
    }finally{await app.close();}
  })().catch(error=>{console.error(error.message);process.exitCode=1;});
}
