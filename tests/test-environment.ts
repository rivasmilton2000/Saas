import 'reflect-metadata';
import {randomBytes} from 'node:crypto';
import {MysqlService} from '../conexion/conexiones';
import {migrate} from '../conexion/migrate';
import {createApp} from '../src/backend/main';
import {AccountingService} from '../src/backend/accounting';
import {seedDemo} from '../scripts/seed-demo';

export const demoPasswords={admin:'AdminDemo!2026',user:'UsuarioDemo!2026'};
export async function testEnvironment(){
  const original=process.env.DB_NAME!;const database=`zentra_test_${randomBytes(6).toString('hex')}`;
  const setup=new MysqlService();await setup.run(`CREATE DATABASE \`${database}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`);await setup.onApplicationShutdown();
  process.env.DB_NAME=database;process.env.NODE_ENV='test';process.env.APP_URL='http://127.0.0.1:3101';process.env.FRONTEND_ORIGIN='http://127.0.0.1:3101';
  const app=await createApp();const db=app.get(MysqlService);await migrate(db);const demo=await seedDemo(db,app.get(AccountingService),demoPasswords);
  const close=async()=>{await app.close();process.env.DB_NAME=original;const cleanup=new MysqlService();try{if(!/^zentra_test_[a-f0-9]{12}$/.test(database))throw Error('Nombre de prueba invalido.');await cleanup.run(`DROP DATABASE IF EXISTS \`${database}\``);}finally{await cleanup.onApplicationShutdown();}};
  return {app,db,demo,database,original,close};
}
