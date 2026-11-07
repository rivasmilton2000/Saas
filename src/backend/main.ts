import 'reflect-metadata';
import { NestFactory } from '@nestjs/core';
import { NestExpressApplication } from '@nestjs/platform-express';
import express from 'express';
import session from 'express-session';
import helmet from 'helmet';
import { existsSync } from 'node:fs';
import { resolve } from 'node:path';
import { timingSafeEqual } from 'node:crypto';
import { MysqlService } from '../../conexion/conexiones';
import { MysqlSessionStore } from '../../conexion/session-store';
import { AppModule } from './app.module';
import { ApiExceptionFilter } from './common';

export async function createApp() {
  if ((process.env.SESSION_SECRET || '').length < 32) throw new Error('Configura SESSION_SECRET con al menos 32 caracteres.');
  const app = await NestFactory.create<NestExpressApplication>(AppModule, {rawBody: true, logger: process.env.NODE_ENV==='test'?['error','warn']:['error','warn','log']});
  app.enableShutdownHooks();
  app.useBodyParser('json', {limit: '10mb'});
  app.useGlobalFilters(new ApiExceptionFilter());
  const aliases: Record<string,string> = {
    '/dashboard.php':'/dashboard',
    '/empresas.php':'/empresas','/empresas/listar.php':'/empresas','/empresas/crear.php':'/empresas',
    '/libros.php':'/libros','/libros/listar.php':'/libros','/libros/listar_por_tipo.php':'/libros','/libros/crear.php':'/libros',
    '/cuota/estado.php':'/cuota/estado','/stripe/webhook.php':'/billing/webhook',
  };
  app.use((request: express.Request,response: express.Response,next: express.NextFunction) => {
    const [path,search=''] = request.url.split('?');
    if (path==='/testdb.php' || path==='/src/testdb.php') request.url='/api/health';
    const legacy = path.replace(/^\/src\/api\//,'/api/');
    if (legacy.startsWith('/api/')) {
      const suffix=legacy.slice(4);
      let target=aliases[suffix];
      if (/^\/facturas\/listar(?:_retencion_iva|_ventas_consumidor|_ventas_contribuyente)?\.php$/.test(suffix)) target='/facturas';
      if (/^\/facturas\/importar(?:_retencion_iva|_ventas_consumidor|_ventas_contribuyente)?\.php$/.test(suffix)) target='/facturas/importar';
      if (/^\/facturas\/exportar(?:_retencion_iva|_ventas_consumidor|_ventas_contribuyente)?\.php$/.test(suffix)) target='/facturas/exportar';
      if (target) request.url='/api'+target+(search?'?'+search:'');
    }
    if (/^\/(?:src\/)?dte\/api/.test(path)) return response.status(501).json({success:false,data:null,message:'La API DTE independiente conserva su contrato anterior. Usa los libros de Zentra.'});
    if (/^\/(?:src\/)?pages\/dashboard\/.*\.php/.test(path)&&request.method!=='GET') return response.status(410).json({success:false,data:null,message:'Esta accion ahora se ejecuta desde la API Nest de Zentra.'});
    next();
  });
  const allowedOrigins = [process.env.FRONTEND_ORIGIN,process.env.APP_URL].filter(Boolean) as string[];
  app.enableCors({origin: allowedOrigins,credentials: true});
  app.use(helmet({contentSecurityPolicy: {
    directives: {
      defaultSrc: ["'self'"], scriptSrc: ["'self'",'https://accounts.google.com'],
      styleSrc: ["'self'","'unsafe-inline'",'https://fonts.googleapis.com','https://accounts.google.com'],
      fontSrc: ["'self'",'data:','https://fonts.gstatic.com'], imgSrc: ["'self'",'data:','https://*.googleusercontent.com'],
      frameSrc: ['https://accounts.google.com'], connectSrc: ["'self'",'https://accounts.google.com'], upgradeInsecureRequests: null,
    },
  },crossOriginOpenerPolicy: {policy: 'same-origin-allow-popups'}}));
  app.use(session({name: 'zentra.sid',secret: process.env.SESSION_SECRET!, resave: false,saveUninitialized: false,
    store: new MysqlSessionStore(app.get(MysqlService)),cookie: {httpOnly: true,sameSite: 'lax',secure: process.env.SESSION_SECURE === 'true',maxAge: 8 * 3600_000,path: '/'}}));
  const attempts = new Map<string,{count: number; reset: number}>();
  const cleanup = setInterval(() => { for (const [key,value] of attempts) if (value.reset < Date.now()) attempts.delete(key); }, 60_000);
  cleanup.unref();
  app.getHttpServer().on('close', () => clearInterval(cleanup));
  app.use('/api', (request: express.Request,response: express.Response,next: express.NextFunction) => {
    response.setHeader('Cache-Control','no-store');
    if (['GET','HEAD','OPTIONS'].includes(request.method) || request.path === '/billing/webhook') return next();
    const origin = request.get('origin');
    if (origin && !allowedOrigins.includes(origin)) return response.status(403).json({success: false,message: 'Origen no permitido.'});
    const actual = Buffer.from(request.get('x-csrf-token') || ''), expected = Buffer.from(request.session.csrf || '');
    if (!expected.length || actual.length !== expected.length || !timingSafeEqual(actual,expected)) return response.status(403).json({success: false,message: 'Sesion del formulario vencida. Recarga e intenta nuevamente.'});
    if (['/auth/login','/auth/register','/auth/google','/public/contact'].includes(request.path)) {
      const key = `${request.ip}:${request.path}`;
      let attempt = attempts.get(key);
      if (!attempt || attempt.reset < Date.now()) { attempt = {count: 0,reset: Date.now()+15*60_000}; attempts.set(key,attempt); }
      if (++attempt.count > 50) return response.status(429).json({success: false,message: 'Demasiados intentos. Intenta mas tarde.'});
    }
    next();
  });
  app.use('/public-assets', express.static(resolve('src/public/assets'), {index: false,dotfiles: 'deny'}));
  app.use('/auth-assets', express.static(resolve('src/assets'), {index: false,dotfiles: 'deny'}));
  app.use('/dashboard-assets', express.static(resolve('src/pages/dashboard/assets'), {index:false,dotfiles:'deny'}));
  app.use('/uploads/profiles', (request:express.Request,response:express.Response,next:express.NextFunction)=>{
    if(!/^\/[a-zA-Z0-9_-]+\.(png|jpg|jpeg|gif|webp)$/i.test(request.path))return response.sendStatus(404);
    next();
  },express.static(resolve('src/uploads/profiles'),{index:false,dotfiles:'deny'}));
  const frontend = resolve('build/frontend');
  if (existsSync(resolve(frontend,'index.html'))) {
    app.use(express.static(frontend, {index: false,dotfiles: 'deny'}));
    app.use((request: express.Request,response: express.Response,next: express.NextFunction) => {
      if (request.method !== 'GET' || request.path.startsWith('/api/') || request.path.startsWith('/public-assets/') || request.path.startsWith('/auth-assets/') || request.path.startsWith('/dashboard-assets/')) return next();
      if (/\.(env|sql|md|json|ts|tsx|map|log)$/i.test(request.path) || /^\/(\.git|conexion|vendor|src\/config)(\/|$)/i.test(request.path)) return response.sendStatus(404);
      return response.sendFile(resolve(frontend,'index.html'));
    });
  }
  return app;
}

if (require.main === module) {
  createApp().then(app => app.listen(Number(process.env.PORT || 3000),process.env.HOST || '127.0.0.1'))
    .catch(error => { console.error('No se pudo iniciar Zentra:', error.message); process.exitCode = 1; });
}
