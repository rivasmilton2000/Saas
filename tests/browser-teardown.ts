import {existsSync,readFileSync} from 'node:fs';
import {MysqlService} from '../conexion/conexiones';
export default async function teardown(){
  const path='.runtime/browser-database.json';if(!existsSync(path))return;
  const {database,original}=JSON.parse(readFileSync(path,'utf8'));if(!/^zentra_test_[a-f0-9]{12}$/.test(database))throw Error('Nombre de prueba invalido.');
  process.env.DB_NAME=original;const db=new MysqlService();try{await db.run(`DROP DATABASE IF EXISTS \`${database}\``);}finally{await db.onApplicationShutdown();}
}
