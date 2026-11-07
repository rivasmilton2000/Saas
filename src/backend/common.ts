import { ArgumentsHost, Catch, ExceptionFilter, HttpException, Injectable, Inject } from '@nestjs/common';
import { Request, Response } from 'express';
import { ZodError, ZodType } from 'zod';
import { MysqlService } from '../../conexion/conexiones';
import { PoolConnection } from 'mysql2/promise';

export const ok = <T>(data: T, message = '') => ({ success: true, data, message });
export function validate<T>(schema: ZodType<T>, input: unknown): T { return schema.parse(input); }

@Catch()
export class ApiExceptionFilter implements ExceptionFilter {
  catch(error: unknown, host: ArgumentsHost) {
    const response = host.switchToHttp().getResponse<Response>();
    let status = 500, message = 'No se pudo completar la operacion.';
    if (error instanceof ZodError) { status = 422; message = error.issues[0]?.message || 'Datos no validos.'; }
    else if (error instanceof HttpException) { status = error.getStatus(); message = error.message; }
    else if ((error as {code?: string})?.code === 'ER_DUP_ENTRY') { status = 409; message = 'Ese registro ya existe.'; }
    else { console.error('Zentra API:', error instanceof Error ? error.message : 'Error interno'); }
    response.status(status).json({ success: false, data: null, message });
  }
}

@Injectable()
export class AuditService {
  constructor(@Inject(MysqlService) private readonly db: MysqlService) {}
  async record(user: {id: number; username: string; rol: string}, action: string, description: string, request?: Request, connection?: PoolConnection, module = 'auth') {
    await this.db.run(`INSERT INTO bitacora_movimientos
      (id_usuario, username_snapshot, rol_snapshot, modulo, accion, descripcion, ip_address, user_agent)
      VALUES (?,?,?,?,?,?,?,?)`,
    [user.id, user.username, user.rol, module, action, Array.from(description).slice(0,255).join(''), request?.ip || null, request?.get('user-agent')?.slice(0, 255) || null], connection);
  }
}
