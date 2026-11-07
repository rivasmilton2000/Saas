import {mkdirSync,writeFileSync} from 'node:fs';
import {testEnvironment} from './test-environment';
(async()=>{
  const env=await testEnvironment();mkdirSync('.runtime',{recursive:true});writeFileSync('.runtime/browser-database.json',JSON.stringify({database:env.database,original:env.original}));
  await env.app.listen(3101,'127.0.0.1');
  let closing=false;const close=()=>{if(closing)return;closing=true;env.close().finally(()=>process.exit());};process.on('SIGINT',close);process.on('SIGTERM',close);
})().catch(error=>{console.error(error);process.exitCode=1;});
