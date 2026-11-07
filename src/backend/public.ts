import { Body, Controller, Get, Inject, Injectable, Module, Post, ServiceUnavailableException } from '@nestjs/common';
import nodemailer from 'nodemailer';
import { z } from 'zod';
import { MysqlService } from '../../conexion/conexiones';
import { ok, validate } from './common';
import {contactServices,contactSubject,contactSubjectLimit,type ContactService} from '../shared/public-services';

export interface Plan {
  id_plan: number; slug: string; nombre: string; descripcion: string; precio: string | null;
  moneda: string; periodo: string; activo: number; personalizado: number; destacado: number;
  is_free: number; billing_interval: 'month'|'year'|null; stripe_price_id: string|null;
  trial_days: number|null; limite_documentos: number|null;
}
@Injectable()
export class PlansService {
  constructor(@Inject(MysqlService) private readonly db: MysqlService) {}
  async get(id?: number) {
    const [plan] = await this.db.rows<Plan>(`SELECT * FROM planes WHERE ${id ? 'id_plan=?' : "slug='free'"} AND activo=1`, id ? [id] : []);
    return plan;
  }
  async list() {
    const plans = await this.db.rows<Plan>('SELECT * FROM planes WHERE activo=1 ORDER BY orden,id_plan');
    const features = await this.db.rows<{ id_plan: number; caracteristica: string; incluido: number }>('SELECT id_plan,caracteristica,incluido FROM planes_caracteristicas ORDER BY orden,id');
    return plans.map(plan => ({...plan, caracteristicas: features.filter(feature => feature.id_plan === plan.id_plan)}));
  }
}

@Injectable()
export class MailService {
  constructor(@Inject(MysqlService) private readonly db: MysqlService) {}
  configured() { return Boolean(process.env.MAIL_HOST && process.env.MAIL_FROM && process.env.MAIL_USERNAME && process.env.MAIL_PASSWORD); }
  async send(to: string, subject: string, text: string, replyTo?: {name: string; address: string}) {
    if (!this.configured()) throw new ServiceUnavailableException('El correo no esta configurado en este entorno.');
    const transport = nodemailer.createTransport({ host: process.env.MAIL_HOST, port: Number(process.env.MAIL_PORT || 587),
      secure: process.env.MAIL_ENCRYPTION === 'ssl', requireTLS: process.env.MAIL_ENCRYPTION !== 'ssl',
      auth: {user: process.env.MAIL_USERNAME, pass: process.env.MAIL_PASSWORD} });
    await transport.sendMail({from: {name: process.env.MAIL_FROM_NAME || 'Zentra', address: process.env.MAIL_FROM!}, to, replyTo, subject, text});
  }
  async welcome(user: {id: number; email: string|null; username: string}, plan: Plan) {
    if (!user.email) return;
    const type = Number(plan.precio) > 0 ? 'welcome_paid' : 'welcome_free';
    if((await this.db.rows("SELECT id FROM email_logs WHERE user_id=? AND type=? AND status='sent' LIMIT 1",[user.id,type])).length)return;
    let status = 'sent', errorMessage: string | null = null;
    try { await this.send(user.email, 'Bienvenido a Zentra', `Hola ${user.username}, tu cuenta de Zentra con plan ${plan.nombre} esta disponible.`); }
    catch { status = 'failed'; errorMessage = 'No se pudo enviar el correo de bienvenida.'; }
    await this.db.run('INSERT INTO email_logs(user_id,email,type,reference_key,status,error_message) VALUES (?,?,?,?,?,?)', [user.id,user.email,type,`user:${user.id}:${type}`,status,errorMessage]);
  }
  async voucher(user:{id:number;email:string|null;username:string},reference:string,url:string|null) {
    if(!user.email)return;
    if((await this.db.rows("SELECT id FROM email_logs WHERE reference_key=? AND type='payment_voucher' AND status='sent' LIMIT 1",[reference])).length)return;
    let status='sent',errorMessage:string|null=null;
    try{await this.send(user.email,'Comprobante de pago Zentra',`Hola ${user.username}, tu pago fue confirmado.${url?` Puedes consultar tu comprobante en ${url}`:''}`);}catch{status='failed';errorMessage='No se pudo enviar el comprobante de pago.';}
    await this.db.run("INSERT INTO email_logs(user_id,email,type,reference_key,status,error_message) VALUES (?,?,'payment_voucher',?,?,?)",[user.id,user.email,reference,status,errorMessage]);
  }
}

@Controller('api/public')
export class PublicController {
  constructor(@Inject(MysqlService) private readonly db: MysqlService, @Inject(PlansService) private readonly plans: PlansService, @Inject(MailService) private readonly mail: MailService) {}
  @Get('plans') async list() { return ok(await this.plans.list()); }
  @Post('contact') async contact(@Body() input: unknown) {
    const data = validate(z.object({
      name: z.string().trim().min(1, 'Escribe tu nombre.').max(100), email: z.email('Email invalido.').max(150),
      subject: z.string().trim().min(1, 'Escribe el asunto.').max(150), message: z.string().trim().min(1, 'Escribe el mensaje.').max(20000),
      service: z.enum(Object.keys(contactServices) as [ContactService,...ContactService[]],{error:'Selecciona un servicio válido.'}).default('general'),
    }).refine(value=>value.subject.length<=contactSubjectLimit(value.service),'El asunto es demasiado largo para el servicio seleccionado.'), input);
    const subject=contactSubject(data.service,data.subject);
    await this.db.run('INSERT INTO mensajes_contacto(nombre,email,asunto,mensaje) VALUES (?,?,?,?)', [data.name,data.email,subject,data.message]);
    try {
      await this.mail.send(process.env.MAIL_CONTACT_TO || process.env.MAIL_FROM || '', `Consulta: ${subject}`, data.message, {name: data.name, address: data.email});
      return ok({ saved: true, emailSent: true, service:data.service }, 'Correo enviado correctamente.');
    } catch { return ok({ saved: true, emailSent: false, service:data.service }, 'Mensaje guardado. El correo no pudo enviarse en este momento.'); }
  }
}

@Module({controllers: [PublicController], providers: [PlansService, MailService], exports: [PlansService, MailService]})
export class PublicModule {}
