import { Body, CanActivate, Controller, ExecutionContext, ForbiddenException, Get, Global, HttpCode, Inject, Injectable, Module, Post, Req, UnauthorizedException, UseGuards } from '@nestjs/common';
import { Request } from 'express';
import { randomBytes } from 'node:crypto';
import { compare } from 'bcryptjs';
import { OAuth2Client } from 'google-auth-library';
import { z } from 'zod';
import { MysqlService } from '../../conexion/conexiones';
import { AuditService, ok, validate } from './common';

export interface User {
  id: number; username: string; rol: 'admin'|'user'; estado: number;
  password?: string | null; email: string | null; nombre_completo: string | null;
  foto_perfil: string | null; pais: string | null; id_plan: number | null;
  plan_slug: string | null; plan_nombre: string | null; plan_precio: string | null;
  suscripcion_estado: string | null; google_id?: string | null;
}
export type AuthRequest = Request & { user: User };
const selectUser = `SELECT u.*,p.slug AS plan_slug,p.nombre AS plan_nombre,p.precio AS plan_precio
 FROM usuarios u LEFT JOIN planes p ON p.id_plan=u.id_plan`;

@Injectable()
export class AuthService {
  constructor(@Inject(MysqlService) private readonly db: MysqlService, @Inject(AuditService) private readonly audit: AuditService) {}
  async byId(id: number) { return (await this.db.rows<User>(`${selectUser} WHERE u.id=?`, [id]))[0]; }
  publicUser(user: User) {
    const {id,username,rol,email,nombre_completo,foto_perfil,pais,id_plan,plan_slug,plan_nombre,suscripcion_estado} = user;
    const photo=foto_perfil?.includes('/src/uploads/profiles/') ? '/uploads/profiles/'+foto_perfil.split('/src/uploads/profiles/')[1] : foto_perfil;
    return {id,username,rol,email,nombre_completo,foto_perfil:photo,pais,id_plan,plan_slug,plan_nombre,suscripcion_estado};
  }
  destination(user: User) { return user.rol === 'admin' ? '/admin' : !user.id_plan ? '/select-plan' : '/app'; }
  checkAccess(user: User) {
    if (!Number(user.estado)) throw new ForbiddenException('Usuario inactivo. Contacta al administrador.');
    if (user.rol === 'user' && (user.plan_slug === 'enterprise' || Number(user.plan_precio) > 0) && !['active','trialing'].includes(user.suscripcion_estado || '')) {
      throw new ForbiddenException('Tu membresia aun no esta activa. Completa el pago o espera la confirmacion de Stripe para continuar.');
    }
  }
  async login(input: unknown, request: Request) {
    const value = validate(z.object({ username: z.string().trim().min(1, 'Completa todos los campos.').max(160), password: z.string().min(1, 'Completa todos los campos.').max(1024) }), input);
    let user: User | undefined;
    if (value.username.includes('@')) user = (await this.db.rows<User>(`${selectUser} WHERE LOWER(u.email)=LOWER(?) LIMIT 1`, [value.username]))[0];
    user ||= (await this.db.rows<User>(`${selectUser} WHERE u.username=? LIMIT 1`, [value.username]))[0];
    if (!user) throw new UnauthorizedException('No encontramos una cuenta con ese usuario o correo.');
    if (!Number(user.estado)) throw new ForbiddenException('Usuario inactivo. Contacta al administrador.');
    if (!user.password) throw new UnauthorizedException('Esta cuenta usa acceso con Google. Continua desde ese boton.');
    // PHP PASSWORD_DEFAULT usa $2y$: bcrypt de Node usa el prefijo equivalente $2b$.
    if (!await compare(value.password, user.password.replace(/^\$2y\$/, '$2b$'))) throw new UnauthorizedException('Clave incorrecta.');
    this.checkAccess(user);
    return this.establish(user, request, 'login');
  }
  async establish(user: User, request: Request, action: string) {
    await new Promise<void>((resolve, reject) => request.session.regenerate(error => error ? reject(error) : resolve()));
    request.session.userId = user.id;
    request.session.csrf = randomBytes(32).toString('hex');
    await this.db.transaction(async connection => {
      await this.db.run('UPDATE usuarios SET ultimo_login_at=CURRENT_TIMESTAMP,ultima_actividad_at=CURRENT_TIMESTAMP WHERE id=?', [user.id], connection);
      await this.audit.record(user, action, 'Inicio de sesion correcto.', request, connection);
    });
    await new Promise<void>((resolve, reject) => request.session.save(error => error ? reject(error) : resolve()));
    return ok({ user: this.publicUser(user), redirect: this.destination(user), csrf: request.session.csrf });
  }
  async google(input: unknown, request: Request) {
    if (!process.env.GOOGLE_CLIENT_ID) throw new ForbiddenException('Google no esta configurado.');
    const { credential } = validate(z.object({ credential: z.string().min(1).max(10000) }), input);
    let payload;
    try { payload = (await new OAuth2Client(process.env.GOOGLE_CLIENT_ID).verifyIdToken({ idToken: credential, audience: process.env.GOOGLE_CLIENT_ID })).getPayload(); }
    catch { throw new UnauthorizedException('No se pudo verificar la cuenta de Google.'); }
    if (!payload?.sub || !payload.email_verified || !payload.email || payload.nonce !== request.session.googleNonce) throw new UnauthorizedException('Credencial de Google no valida.');
    delete request.session.googleNonce;
    let user = (await this.db.rows<User>(`${selectUser} WHERE u.google_id=? OR LOWER(u.email)=LOWER(?) ORDER BY u.google_id=? DESC LIMIT 1`, [payload.sub, payload.email, payload.sub]))[0];
    if (!user) {
      const username = `${payload.email.split('@')[0].replace(/[^a-zA-Z0-9._-]/g, '').slice(0, 35) || 'google'}.${randomBytes(4).toString('hex')}`;
      const result = await this.db.run(`INSERT INTO usuarios(username,email,nombre_completo,password,rol,estado,pais,auth_provider,google_id,email_verificado_at,foto_perfil)
        VALUES (?,?,?,NULL,'user',1,'El Salvador','google',?,CURRENT_TIMESTAMP,?)`, [username,payload.email,payload.name || username,payload.sub,payload.picture || null]);
      user = await this.byId(result.insertId);
    } else {
      this.checkAccess(user);
      if (user.google_id && user.google_id !== payload.sub) throw new UnauthorizedException('La cuenta de Google no coincide.');
      await this.db.run('UPDATE usuarios SET google_id=?,email_verificado_at=COALESCE(email_verificado_at,CURRENT_TIMESTAMP) WHERE id=?', [payload.sub,user.id]);
    }
    return this.establish(user, request, 'google_login');
  }
}

@Injectable()
export class AuthGuard implements CanActivate {
  constructor(@Inject(AuthService) private readonly auth: AuthService) {}
  async canActivate(context: ExecutionContext) {
    const request = context.switchToHttp().getRequest<AuthRequest>();
    const user = request.session.userId ? await this.auth.byId(request.session.userId) : null;
    if (!user) throw new UnauthorizedException('No autenticado.');
    this.auth.checkAccess(user);
    request.user = user;
    return true;
  }
}

@Controller('api/auth')
export class AuthController {
  constructor(@Inject(AuthService) private readonly auth: AuthService, @Inject(AuditService) private readonly audit: AuditService) {}
  @Get('csrf') csrf(@Req() request: Request) {
    request.session.csrf ||= randomBytes(32).toString('hex');
    request.session.googleNonce ||= randomBytes(32).toString('hex');
    return ok({ csrf: request.session.csrf, googleClientId: process.env.GOOGLE_CLIENT_ID || '', googleNonce: request.session.googleNonce });
  }
  @Post('login') @HttpCode(200) login(@Body() input: unknown, @Req() request: Request) { return this.auth.login(input, request); }
  @Post('google') @HttpCode(200) google(@Body() input: unknown, @Req() request: Request) { return this.auth.google(input, request); }
  @Get('me') @UseGuards(AuthGuard) me(@Req() request: AuthRequest) { return ok({user: this.auth.publicUser(request.user), redirect: this.auth.destination(request.user)}); }
  @Post('logout') @HttpCode(200) @UseGuards(AuthGuard)
  async logout(@Req() request: AuthRequest) {
    await this.audit.record(request.user, 'logout', 'Cierre de sesion.', request);
    await new Promise<void>((resolve, reject) => request.session.destroy(error => error ? reject(error) : resolve()));
    request.res?.clearCookie('zentra.sid', {path: '/'});
    return ok(null, 'Sesion cerrada.');
  }
}

@Global()
@Module({ controllers: [AuthController], providers: [AuthService, AuthGuard, AuditService], exports: [AuthService, AuthGuard, AuditService] })
export class AuthModule {}
