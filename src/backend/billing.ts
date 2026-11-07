import { Body, Controller, ForbiddenException, Get, HttpCode, Inject, Injectable, Module, Post, Query, Req, ServiceUnavailableException, UnauthorizedException, UnprocessableEntityException, UseGuards } from '@nestjs/common';
import { Request } from 'express';
import { hash } from 'bcryptjs';
import Stripe from 'stripe';
import { z } from 'zod';
import { MysqlService } from '../../conexion/conexiones';
import { AuthGuard, AuthRequest, AuthService } from './auth';
import { AuditService, ok, validate } from './common';
import { MailService, Plan, PlansService, PublicModule } from './public';

const registration = z.object({
  nombre_completo: z.string().trim().min(3, 'Escribe tu nombre completo.').max(150),
  email: z.email('Necesitamos un correo valido.').max(160).transform(value => value.toLowerCase()),
  username: z.string().trim().regex(/^[A-Za-z0-9._-]{3,50}$/, 'Usa de 3 a 50 caracteres: letras, numeros, punto, guion o guion bajo.'),
  password: z.string().min(6, 'La clave debe tener al menos 6 caracteres.').refine(value => Buffer.byteLength(value) <= 72, 'La clave no puede exceder 72 bytes.'),
  confirm_password: z.string(), pais: z.string().trim().min(1).max(80).default('El Salvador'),
  id_plan: z.coerce.number().int().positive().optional(), terms: z.literal(true, 'Debes aceptar terminos y condiciones.'),
}).refine(value => value.password === value.confirm_password, 'Las claves no coinciden.');

@Injectable()
export class BillingService {
  private readonly stripe: Stripe | null;
  constructor(@Inject(MysqlService) private readonly db: MysqlService, @Inject(PlansService) private readonly plans: PlansService,
    @Inject(AuthService) private readonly auth: AuthService, @Inject(MailService) private readonly mail: MailService) {
    this.stripe = process.env.STRIPE_SECRET_KEY ? new Stripe(process.env.STRIPE_SECRET_KEY, {maxNetworkRetries: 2}) : null;
  }
  private gateway() {
    if (!this.stripe) throw new ServiceUnavailableException('Stripe aun no esta configurado en este entorno.');
    return this.stripe;
  }
  private async plan(id?: number) {
    const plan = await this.plans.get(id);
    if (!plan) throw new UnprocessableEntityException('Selecciona una membresia valida para continuar.');
    if (plan.personalizado || plan.precio === null || plan.slug === 'enterprise') throw new UnprocessableEntityException('El plan Enterprise se coordina con ventas. Contactanos para personalizar la propuesta.');
    return plan;
  }
  private async checkout(plan: Plan, email: string, metadata: Record<string,string>) {
    const origin = (process.env.APP_URL || 'http://localhost:3000').replace(/\/$/, '');
    return this.gateway().checkout.sessions.create({
      mode: 'subscription', customer_email: email,
      success_url: `${origin}/payment/success?session_id={CHECKOUT_SESSION_ID}`,
      cancel_url: `${origin}/payment/cancel`, metadata, subscription_data: {metadata, ...(plan.trial_days ? {trial_period_days: plan.trial_days} : {})},
      line_items: [{quantity: 1, ...(plan.stripe_price_id ? {price: plan.stripe_price_id} : {price_data: {
        currency: plan.moneda.toLowerCase(), unit_amount: Math.round(Number(plan.precio) * 100),
        recurring: {interval: plan.billing_interval || 'month'}, product_data: {name: `Zentra ${plan.nombre}`},
      }})}],
    });
  }
  async register(input: unknown, request: Request) {
    const data = validate(registration, input);
    const plan = await this.plan(data.id_plan);
    const users = await this.db.rows('SELECT id FROM usuarios WHERE LOWER(email)=? OR username=?', [data.email,data.username]);
    if (users.length) throw new UnprocessableEntityException('Ese correo o nombre de usuario ya esta registrado.');
    const pending = await this.db.rows(`SELECT id FROM registros_pago_pendientes WHERE (email=? OR username=?) AND estado NOT IN ('completed','expired','canceled')`, [data.email,data.username]);
    if (pending.length) throw new UnprocessableEntityException('Ya existe un registro pendiente con ese correo o nombre de usuario.');
    const password = await hash(data.password, 12);
    if (Number(plan.precio) === 0) {
      const result = await this.db.run(`INSERT INTO usuarios(username,nombre_completo,email,password,rol,estado,pais,id_plan,suscripcion_estado,auth_provider)
        VALUES (?,?,?,?,'user',1,?,?,'free','local')`, [data.username,data.nombre_completo,data.email,password,data.pais,plan.id_plan]);
      const user = await this.auth.byId(result.insertId);
      await this.mail.welcome(user, plan);
      return this.auth.establish(user, request, 'register_free');
    }
    this.gateway(); // No crear borradores si no hay configuracion de Stripe.
    const result = await this.db.run(`INSERT INTO registros_pago_pendientes
      (nombre_completo,email,username,password_hash,pais,id_plan,plan_slug_snapshot,plan_nombre_snapshot,plan_precio_snapshot,auth_provider,estado)
      VALUES (?,?,?,?,?,?,?,?,?,'local','draft')`, [data.nombre_completo,data.email,data.username,password,data.pais,plan.id_plan,plan.slug,plan.nombre,plan.precio]);
    try {
      const session = await this.checkout(plan, data.email, {flow_type: 'pending_registration',pending_registration_id: String(result.insertId),plan_id: String(plan.id_plan)});
      await this.db.run("UPDATE registros_pago_pendientes SET stripe_checkout_session_id=?,stripe_status=?,estado='checkout_created' WHERE id=?", [session.id,session.payment_status,result.insertId]);
      return ok({checkout_url: session.url, mode: 'checkout'});
    } catch (error) {
      await this.db.run("UPDATE registros_pago_pendientes SET estado='expired' WHERE id=? AND estado='draft'", [result.insertId]);
      throw error;
    }
  }
  async select(input: unknown, request: AuthRequest) {
    if (request.user.rol !== 'user') throw new ForbiddenException('Solo las cuentas de usuario pueden seleccionar un plan.');
    if (request.user.id_plan && !(Number(request.user.plan_precio) > 0 && !['active','trialing'].includes(request.user.suscripcion_estado || ''))) throw new ForbiddenException('Tu cuenta ya tiene una membresia.');
    const {id_plan} = validate(z.object({id_plan: z.coerce.number().int().positive()}), input);
    const plan = await this.plan(id_plan);
    if (Number(plan.precio) === 0) {
      await this.db.run("UPDATE usuarios SET id_plan=?,suscripcion_estado='free',stripe_checkout_session_id=NULL WHERE id=?", [plan.id_plan,request.user.id]);
      const user = await this.auth.byId(request.user.id);
      await this.mail.welcome(user, plan);
      return ok({user: this.auth.publicUser(user),redirect: this.auth.destination(user)});
    }
    if (!request.user.email || !z.email().safeParse(request.user.email).success) throw new UnprocessableEntityException('Tu cuenta necesita un correo valido antes de continuar con Stripe.');
    const session = await this.checkout(plan, request.user.email, {flow_type: 'existing_user',user_id: String(request.user.id),plan_id: String(plan.id_plan)});
    await this.db.run("UPDATE usuarios SET id_plan=?,suscripcion_estado='payment_pending',stripe_checkout_session_id=? WHERE id=?", [plan.id_plan,session.id,request.user.id]);
    return ok({checkout_url: session.url, mode: 'checkout'});
  }
  async webhook(raw: Buffer | undefined, signature: string | undefined) {
    if (!process.env.STRIPE_WEBHOOK_SECRET) throw new ServiceUnavailableException('Webhook de Stripe no configurado.');
    let event: Stripe.Event;
    try { event = this.gateway().webhooks.constructEvent(raw!, signature!, process.env.STRIPE_WEBHOOK_SECRET); }
    catch { throw new UnauthorizedException('Firma de Stripe no valida.'); }
    await this.processEvent(event);
    return ok({received: true});
  }
  async complete(input:unknown,request:Request) {
    const {session_id}=validate(z.object({session_id:z.string().regex(/^cs_[A-Za-z0-9_]+$/).max(200)}),input);
    await this.processEvent({id:`return_${session_id}`,type:'checkout.session.completed',data:{object:{id:session_id}}} as Stripe.Event);
    const [row]=await this.db.rows<{id:number}>(`SELECT u.id FROM usuarios u WHERE u.stripe_checkout_session_id=?`,[session_id]);
    if(!row)return ok({pending:true},'Estamos esperando la confirmacion del pago.');
    const user=await this.auth.byId(row.id);this.auth.checkAccess(user);
    return this.auth.establish(user,request,'payment_success');
  }
  async processEvent(event: Stripe.Event) {
    let checkout: Stripe.Checkout.Session | null = null;
    let notification:{userId:number;planId:number;invoiceId:string|null;invoiceUrl:string|null;welcome:boolean}|undefined;
    if (event.type === 'checkout.session.completed') {
      checkout = await this.gateway().checkout.sessions.retrieve((event.data.object as Stripe.Checkout.Session).id, {expand: ['subscription', 'invoice']});
      if (!['paid','no_payment_required'].includes(checkout.payment_status)) return;
      const subscription = checkout.subscription as Stripe.Subscription | null;
      if (!subscription || !['active','trialing'].includes(subscription.status)) return;
    }
    await this.db.transaction(async connection => {
      const applied = await this.db.run('INSERT IGNORE INTO zentra_webhook_events(event_id,event_type) VALUES (?,?)', [event.id,event.type], connection);
      if (!applied.affectedRows) return;
      if (checkout) {
        const metadata = checkout.metadata || {};
        const subscription = checkout.subscription as Stripe.Subscription;
        let userId = Number(metadata.user_id || 0);
        const planId = Number(metadata.plan_id || 0);
        const customerId = typeof checkout.customer === 'string' ? checkout.customer : checkout.customer?.id || null;
        const invoice = typeof checkout.invoice === 'object' ? checkout.invoice : null;
        if (metadata.flow_type === 'pending_registration') {
          const [pending] = await this.db.rows<Record<string,any>>('SELECT * FROM registros_pago_pendientes WHERE id=? AND stripe_checkout_session_id=? FOR UPDATE', [Number(metadata.pending_registration_id || 0),checkout.id], connection);
          if (!pending) throw new UnprocessableEntityException('Registro pendiente no encontrado.');
          userId = pending.created_user_id;
          if (!userId) {
            const user = await this.db.run(`INSERT INTO usuarios(username,nombre_completo,email,password,pais,rol,estado,id_plan,auth_provider)
              VALUES (?,?,?,?,?,'user',1,?,?)`, [pending.username,pending.nombre_completo,pending.email,pending.password_hash,pending.pais,pending.id_plan,pending.auth_provider || 'local'], connection);
            userId = user.insertId;
          }
          await this.db.run("UPDATE registros_pago_pendientes SET created_user_id=?,estado='completed',completed_at=CURRENT_TIMESTAMP,stripe_status=? WHERE id=?", [userId,subscription.status,pending.id], connection);
        }
        if (!userId || !planId) throw new UnprocessableEntityException('Metadatos de Stripe incompletos.');
        const [user] = await this.db.rows<{id: number}>('SELECT id FROM usuarios WHERE id=? AND (stripe_checkout_session_id=? OR stripe_checkout_session_id IS NULL) FOR UPDATE', [userId,checkout.id], connection);
        if (!user) throw new UnprocessableEntityException('Checkout no asociado a la cuenta.');
        await this.db.run(`UPDATE usuarios SET id_plan=?,suscripcion_estado=?,stripe_customer_id=?,stripe_subscription_id=?,stripe_checkout_session_id=?,
          stripe_invoice_id=?,stripe_invoice_url=?,stripe_invoice_pdf_url=? WHERE id=?`, [planId,subscription.status,customerId,subscription.id,checkout.id,invoice?.id || null,invoice?.hosted_invoice_url || null,invoice?.invoice_pdf || null,userId], connection);
        await this.db.run(`INSERT INTO subscriptions(user_id,plan_id,stripe_customer_id,stripe_subscription_id,stripe_checkout_session_id,status,amount,currency)
          VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status),user_id=VALUES(user_id),plan_id=VALUES(plan_id)`, [userId,planId,customerId,subscription.id,checkout.id,subscription.status,(checkout.amount_total || 0)/100,checkout.currency], connection);
        if (invoice?.id) await this.db.run(`INSERT INTO payments(user_id,plan_id,stripe_checkout_session_id,stripe_invoice_id,amount,currency,status,receipt_url)
          VALUES (?,?,?,?,?,?,'paid',?) ON DUPLICATE KEY UPDATE status='paid',receipt_url=VALUES(receipt_url)`, [userId,planId,checkout.id,invoice.id,(invoice.amount_paid || 0)/100,invoice.currency,invoice.hosted_invoice_url], connection);
        notification={userId,planId,invoiceId:invoice?.id || null,invoiceUrl:invoice?.hosted_invoice_url || null,welcome:true};
      } else if (['customer.subscription.updated','customer.subscription.deleted'].includes(event.type)) {
        const sub = event.data.object as Stripe.Subscription;
        const state = event.type.endsWith('deleted') ? 'canceled' : sub.status;
        await this.db.run('UPDATE usuarios SET suscripcion_estado=? WHERE stripe_subscription_id=?', [state,sub.id], connection);
        await this.db.run('UPDATE subscriptions SET status=? WHERE stripe_subscription_id=?', [state,sub.id], connection);
      } else if (event.type === 'checkout.session.expired') {
        const session = event.data.object as Stripe.Checkout.Session;
        await this.db.run("UPDATE registros_pago_pendientes SET estado='expired',stripe_status='expired' WHERE stripe_checkout_session_id=? AND estado<>'completed'", [session.id], connection);
      } else if (event.type === 'invoice.paid' || event.type === 'invoice.payment_failed') {
        const invoice = event.data.object as Stripe.Invoice;
        const sub = invoice.parent?.subscription_details?.subscription;
        const subId = typeof sub === 'string' ? sub : sub?.id;
        if (!subId) return;
        const [user] = await this.db.rows<{id: number; id_plan: number}>('SELECT id,id_plan FROM usuarios WHERE stripe_subscription_id=?', [subId], connection);
        if (!user) return;
        const paid = event.type === 'invoice.paid';
        await this.db.run('UPDATE usuarios SET suscripcion_estado=?,stripe_invoice_id=?,stripe_invoice_url=?,stripe_invoice_pdf_url=? WHERE id=?', [paid ? 'active' : 'past_due',invoice.id,invoice.hosted_invoice_url,invoice.invoice_pdf,user.id], connection);
        await this.db.run('UPDATE subscriptions SET status=? WHERE stripe_subscription_id=?', [paid ? 'active' : 'past_due',subId], connection);
        await this.db.run(`INSERT INTO payments(user_id,plan_id,stripe_invoice_id,amount,currency,status,receipt_url) VALUES (?,?,?,?,?,?,?)
          ON DUPLICATE KEY UPDATE status=VALUES(status),receipt_url=VALUES(receipt_url)`, [user.id,user.id_plan,invoice.id,(invoice.amount_paid || 0)/100,invoice.currency,paid ? 'paid' : 'failed',invoice.hosted_invoice_url], connection);
        if(paid)notification={userId:user.id,planId:user.id_plan,invoiceId:invoice.id || null,invoiceUrl:invoice.hosted_invoice_url || null,welcome:false};
      }
    });
    if(notification) {
      const notice=notification as {userId:number;planId:number;invoiceId:string|null;invoiceUrl:string|null;welcome:boolean};
      const user=await this.auth.byId(notice.userId),plan=await this.plans.get(notice.planId);
      if(user&&plan&&notice.welcome)await this.mail.welcome(user,plan);
      if(user&&notice.invoiceId)await this.mail.voucher(user,notice.invoiceId,notice.invoiceUrl);
    }
  }
}

@Controller('api')
export class BillingController {
  constructor(@Inject(BillingService) private readonly billing: BillingService, @Inject(MysqlService) private readonly db: MysqlService) {}
  @Post('auth/register') register(@Body() input: unknown, @Req() request: Request) { return this.billing.register(input, request); }
  @Post('billing/select-plan') @UseGuards(AuthGuard) select(@Body() input: unknown, @Req() request: AuthRequest) { return this.billing.select(input, request); }
  @Post('billing/complete-checkout') complete(@Body() input:unknown,@Req() request:Request){return this.billing.complete(input,request);}
  @Post('billing/webhook') @HttpCode(200) webhook(@Req() request: Request & {rawBody?: Buffer}) { return this.billing.webhook(request.rawBody, request.get('stripe-signature')); }
  @Get('billing/status') @UseGuards(AuthGuard) async status(@Req() request: AuthRequest, @Query('session_id') sessionId: string) {
    const [pending] = await this.db.rows('SELECT status,amount,currency FROM subscriptions WHERE stripe_checkout_session_id=? AND user_id=?', [sessionId || '',request.user.id]);
    return ok(pending || null);
  }
}
@Module({imports: [PublicModule], controllers: [BillingController], providers: [BillingService], exports: [BillingService]})
export class BillingModule {}
