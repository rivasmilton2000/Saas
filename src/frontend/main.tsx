import { StrictMode, useEffect, useRef, useState, type FormEvent, type ReactNode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Link, Navigate, Outlet, Route, Routes, useLocation, useNavigate } from 'react-router-dom';
import { api, csrfConfig, upload, type Plan, type User } from './api';
import { Home } from './pages/Home';
import { About } from './pages/About';
import { Footer } from './pages/Footer';
import './zentra.css';
import { Dashboard, LegacyDashboardRedirect } from './dashboard/Dashboard';
import { contactServices, contactSubjectLimit, selectedContactService, type ContactService } from '../shared/public-services';
import {usePublicNavigation} from './use-public-navigation';

function Styles({auth = false}: {auth?: boolean}) {
  const files = auth ? ['/auth-assets/vendors/css/vendor.bundle.base.css','/auth-assets/css/style.css','/auth-assets/css/auth-zentra.css']
    : ['/public-assets/bootstrap/css/bootstrap.min.css','/public-assets/fonts/font-awesome.min.css','/public-assets/fonts/themify-icons.css','/public-assets/css/fonts.css','/public-assets/css/menu.css','/public-assets/css/style.css','/public-assets/css/responsive.css'];
  return <>{files.map(href => <link key={href} rel="stylesheet" href={href} />)}<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700,800|Oswald:300,400,500,600" /></>;
}
function PublicLayout() {
  const [open,setOpen] = useState(false);
  usePublicNavigation(setOpen);
  return <><Styles /><header className="site-navbar site-navbar-target zentra-nav" role="banner"><div className="container"><div className="row align-items-center">
    <div className="col-6 col-xl-2"><h1 className="mb-0 site-logo"><Link to="/"><img src="/public-assets/img/Zentra_logo.png" alt="Zentra" style={{maxWidth:100}} /></Link></h1></div>
    <div className="col-6 d-xl-none text-right"><button className="zentra-menu" aria-label="Abrir menu" aria-controls="public-navigation" aria-expanded={open} onClick={() => setOpen(!open)}>☰</button></div>
    <nav id="public-navigation" aria-label="Navegacion principal" className={`col-12 col-xl-10 site-navigation text-right zentra-menu-links ${open ? 'is-open' : ''}`}><ul className="site-menu main-menu">
      {[['/','Inicio'],['/#servicios','Servicios'],['/about','Acerca de'],['/pricing','Planes'],['/contact','Contactanos'],['/login','Inicia Sesión']].map(([to,label]) => <li key={to}><Link className="nav-link" to={to}>{label}</Link></li>)}
    </ul></nav></div></div></header><Outlet /><Footer /></>;
}
function Banner({title}: {title: string}) { return <section className="section-top" style={{backgroundImage:'url(/public-assets/img/bg/section-top.png)',backgroundSize:'cover',backgroundPosition:'center'}}><div className="container"><div className="col-lg-12 text-center"><div className="section-top-title"><h1>{title}</h1><ul><li><Link to="/">Inicio</Link></li><li>{title}</li></ul></div></div></div></section>; }
function Feedback({message}: {message: string}) { return message ? <div className="auth-alert" role="alert">{message}</div> : null; }
function PlanCards({plans,onSelect}: {plans: Plan[]; onSelect?: (id: number) => void}) {
  return <div className="row justify-content-center">{plans.map(plan => <div className="col-lg-4 col-md-6" key={plan.id_plan}><div className={`pricing_card ${plan.destacado ? 'featured' : ''}`}>
    {Boolean(plan.destacado) && <span className="badge_plan">RECOMENDADO</span>}<h4>{plan.nombre}</h4><h2>{plan.precio === null ? 'Personalizado' : `$${Number(plan.precio).toFixed(2)}`}{plan.precio !== null && <span>/{plan.periodo}</span>}</h2><p>{plan.descripcion}</p>
    <ul>{plan.caracteristicas.map((feature,index) => <li key={index} className={feature.incluido ? '' : 'disabled'}><i className={`fa fa-${feature.incluido ? 'check' : 'times'}`} /> {feature.caracteristica}</li>)}</ul>
    {plan.personalizado || plan.precio === null ? <Link className="btn_pricing" to="/contact?plan=enterprise">Solicitar Cotización</Link> : onSelect ? <button className="btn_pricing" onClick={() => onSelect(plan.id_plan)}>Elegir {plan.nombre}</button> : <Link className={`btn_pricing ${plan.destacado ? 'active_btn' : ''}`} to={`/register?plan=${plan.id_plan}`}>{Number(plan.precio) === 0 ? 'Comenzar Gratis' : 'Elegir Plan'}</Link>}
  </div></div>)}</div>;
}
function usePlans() {
  const [plans,setPlans] = useState<Plan[]>([]), [error,setError] = useState('');
  useEffect(() => {api<Plan[]>('/public/plans').then(result => setPlans(result.data)).catch(error => setError(error.message));}, []);
  return {plans,error};
}
function Pricing() {const {plans,error}=usePlans();return <><Banner title="Planes" /><section className="pricing_area section-padding"><div className="container"><div className="section-title text-center"><h2>Planes flexibles</h2><p>Elige el plan que mejor se adapte al tamaño y operación de tu negocio.</p></div><Feedback message={error} /><PlanCards plans={plans} /></div></section></>;}
function Contact() {
  const [message,setMessage]=useState(''),[busy,setBusy]=useState(false);
  const location=useLocation(),params=new URLSearchParams(location.search);
  const requested=params.get('plan')==='enterprise'?'contabilidad':selectedContactService(params.get('service'));
  const [service,setService]=useState<ContactService>(requested);
  useEffect(()=>{setService(requested);setMessage('');},[requested,location.key]);
  async function send(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();setBusy(true);const form=event.currentTarget;
    try {const result=await api('/public/contact',Object.fromEntries(new FormData(form)));setMessage(result.message);form.reset();setService('general');}
    catch(error){setMessage((error as Error).message);}finally{setBusy(false);}
  }
  return <><Banner title="Contáctanos" /><section id="contact" className="contact_page_area section-padding"><div className="container"><div className="section-title text-center"><h2>Hablemos de tu negocio</h2><p>Cuéntanos qué necesitas para tu contabilidad, tu sitio web, tu dominio o tu hosting.</p></div><div className="row contact_info_row">
    {[['fa-envelope','Correo','zentragroup1@gmail.com'],['fa-phone','Teléfono','+503 7934-6671'],['fa-clock-o','Horario','Lunes a Domingos']].map(([icon,title,text]) => <div className="col-lg-4 col-md-6" key={title}><div className="contact_info_card"><i className={`fa ${icon}`} /><h4>{title}</h4><p>{text}</p></div></div>)}
    </div><div className="row justify-content-center"><div className="col-lg-9"><div className="contact_form_card"><Feedback message={message} /><form onSubmit={send} aria-busy={busy}><fieldset className="row" disabled={busy}>
      <div className="form-group col-md-6"><input aria-label="Nombre completo" name="name" maxLength={100} className="form-control" placeholder="Nombre completo" required /></div>
      <div className="form-group col-md-6"><input aria-label="Correo electrónico" name="email" type="email" maxLength={150} className="form-control" placeholder="Correo electrónico" required /></div>
      <div className="form-group col-md-12"><label htmlFor="contact-service">Servicio de interés</label><select id="contact-service" name="service" className="form-control" value={service} onChange={event=>setService(event.target.value as ContactService)}>{Object.entries(contactServices).map(([value,label])=><option key={value} value={value}>{label}</option>)}</select></div>
      <div className="form-group col-md-12"><input aria-label="Asunto" name="subject" maxLength={contactSubjectLimit(service)} defaultValue={params.get('plan')==='enterprise'?'Consulta sobre el plan Enterprise':''} className="form-control" placeholder="Asunto" required /></div>
      <div className="form-group col-md-12"><textarea aria-label="Mensaje" name="message" maxLength={20000} rows={6} className="form-control" placeholder="Escribe tu mensaje..." required /></div>
      <div className="col-md-12 text-center"><button disabled={busy} type="submit" className="contact_btn">{busy ? 'Enviando…' : 'Enviar Mensaje'}</button></div>
    </fieldset></form></div></div></div></div></section></>;
}
declare global { interface Window {google?: {accounts: {id: {initialize: (options: Record<string,unknown>) => void; renderButton: (element: HTMLElement, options: Record<string,unknown>) => void}}};} }
function GoogleButton({onError}: {onError: (message: string) => void}) {
  const ref=useRef<HTMLDivElement>(null),navigate=useNavigate();
  const [enabled,setEnabled]=useState(false);
  useEffect(() => {
    let canceled=false;
    csrfConfig().then(config => {
      if (!config.googleClientId || canceled) return;
      setEnabled(true);
      function initialize() {
        if (canceled || !ref.current) return;
        window.google?.accounts.id.initialize({client_id: config.googleClientId,nonce: config.googleNonce,
          callback: async ({credential}: {credential: string}) => {try {const result=await api<{redirect: string}>('/auth/google',{credential});navigate(result.data.redirect);}catch(error){onError((error as Error).message);}}});
        window.google?.accounts.id.renderButton(ref.current,{theme:'outline',size:'large',width:320});
      }
      if (window.google) initialize();
      else {let script=document.querySelector<HTMLScriptElement>('script[data-google]');if (!script) {script=document.createElement('script');script.src='https://accounts.google.com/gsi/client';script.async=true;script.dataset.google='true';document.head.append(script);}script.addEventListener('load',initialize,{once:true});}
    }).catch(error => {if (!canceled) onError(error.message);});
    return () => {canceled=true;};
  }, [navigate,onError]);
  return <div className="auth-social-stack"><div ref={ref} />{!enabled && <button className="auth-social-btn" type="button" disabled>Continuar con Google</button>}</div>;
}
function AuthPage({register=false}: {register?: boolean}) {
  const [message,setMessage]=useState(''),[busy,setBusy]=useState(false),navigate=useNavigate();
  const {plans,error}=usePlans();
  const selected=new URLSearchParams(useLocation().search).get('plan') || '';
  useEffect(() => {document.title=register ? 'Registro - Zentra' : 'Acceso - Zentra';}, [register]);
  useEffect(() => {api<{redirect:string}>('/auth/me').then(result=>navigate(result.data.redirect,{replace:true})).catch(()=>{});},[navigate]);
  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();setBusy(true);setMessage('');
    const data=Object.fromEntries(new FormData(event.currentTarget));
    try {const result=await api<{redirect?: string;checkout_url?: string}>('/auth/'+(register ? 'register' : 'login'),{...data,...(register ? {terms: data.terms === 'on',id_plan: Number(data.id_plan)} : {})});if (result.data.checkout_url) window.location.assign(result.data.checkout_url);else navigate(result.data.redirect || '/app');}
    catch(error){setMessage((error as Error).message);}finally{setBusy(false);}
  }
  return <><Styles auth /><div className="zentra-auth-body"><div className="zentra-auth-page"><main className={`auth-shell auth-shell--${register ? 'register' : 'login'}`}><section className="auth-surface">
    <Link to="/" className="auth-brand"><img src="/auth-assets/images/logo.svg" alt="Zentra" /></Link><header className="auth-header"><h1 className="auth-title">{register ? 'Crea tu cuenta' : 'Inicia sesion'}</h1><p className="auth-subtitle">{register ? 'Empieza a trabajar con Zentra.' : 'Accede a tu cuenta para continuar.'}</p></header>
    <Feedback message={message || error} /><GoogleButton onError={setMessage} /><div className="auth-divider"><span>{register ? 'o completa tu cuenta' : 'o entra con tu cuenta'}</span></div>
    <form className="auth-form" onSubmit={submit}>
      {register && <><Field name="nombre_completo" label="Nombre completo" maxLength={150} /><Field name="email" label="Correo" type="email" maxLength={160} /></>}
      <Field name="username" label={register ? 'Usuario' : 'Usuario o correo'} autoComplete="username" maxLength={register ? 50 : 160} />
      <Field name="password" label="Contraseña" type="password" autoComplete={register ? 'new-password' : 'current-password'} />
      {register && <><Field name="confirm_password" label="Confirmar contraseña" type="password" autoComplete="new-password" />
        <label className="auth-field">País<select name="pais" className="auth-input" defaultValue="El Salvador">{['El Salvador','Guatemala','Honduras','Nicaragua','Costa Rica','Panama','Mexico','Estados Unidos','Colombia','Republica Dominicana','Otro'].map(country => <option key={country}>{country}</option>)}</select></label>
        <label className="auth-field">Membresía<select name="id_plan" className="auth-input" key={plans.length} defaultValue={selected || plans.find(plan => plan.slug === 'free')?.id_plan} required>{plans.map(plan => <option key={plan.id_plan} value={plan.id_plan}>{plan.nombre} — {plan.precio === null ? 'Consultar ventas' : `$${Number(plan.precio).toFixed(2)}`}</option>)}</select></label>
        <label className="auth-check"><input type="checkbox" name="terms" required /><span>Acepto términos y condiciones.</span></label></>}
      {!register && <div className="auth-meta-row"><Link to="/contact?topic=acceso">¿Olvidaste tu contraseña?</Link></div>}
      <button className="auth-submit" type="submit" disabled={busy}>{busy ? 'Procesando…' : register ? 'Crear cuenta' : 'Iniciar sesión'}</button>
    </form><div className="auth-switch">{register ? '¿Ya tienes cuenta? ' : '¿No tienes cuenta? '}<Link to={register ? '/login' : '/register'}>{register ? 'Inicia sesión' : 'Regístrate'}</Link></div>
  </section></main></div></div></>;
}
function Field({label,...props}: {label: string} & React.InputHTMLAttributes<HTMLInputElement>) {return <div className="auth-field"><label className="auth-sr-only" htmlFor={`field-${props.name}`}>{label}</label><input id={`field-${props.name}`} className="auth-input" placeholder={label} required {...props} /></div>;}
function Protected({children,admin=false}: {children: ReactNode;admin?: boolean}) {
  const [state,setState]=useState<{user?: User;redirect?: string;error?: string}>({});
  useEffect(() => {api<{user: User;redirect: string}>('/auth/me').then(result=>setState(result.data)).catch(error=>setState({error:error.message}));}, []);
  if(state.error) return <Navigate to="/login" replace />;
  if(!state.user) return <p role="status">Cargando tu cuenta…</p>;
  if(admin && state.user.rol !== 'admin') return <Navigate to="/app" replace />;
  if(!state.user.id_plan && state.user.rol !== 'admin' && location.pathname !== '/select-plan') return <Navigate to="/select-plan" replace />;
  return children;
}
function SelectPlan() {
  const {plans,error}=usePlans(),[message,setMessage]=useState(''),navigate=useNavigate();
  async function select(id_plan: number) {try {const result=await api<{redirect?: string;checkout_url?: string}>('/billing/select-plan',{id_plan});if(result.data.checkout_url)window.location.assign(result.data.checkout_url);else navigate(result.data.redirect || '/app');}catch(error){setMessage((error as Error).message);}}
  return <><Styles /><main className="container section-padding"><h1>Selecciona tu membresía</h1><Feedback message={message||error} /><PlanCards plans={plans} onSelect={select} /></main></>;
}
function Account({embedded=false}:{embedded?:boolean}) {
  const [user,setUser]=useState<User>(),[message,setMessage]=useState(''),[busy,setBusy]=useState(false),navigate=useNavigate();
  useEffect(()=>{api<{user:User}>('/auth/me').then(result=>setUser(result.data.user)).catch(error=>setMessage(error.message));},[]);
  async function logout(){try{await api('/auth/logout',{});navigate('/login');}catch(error){setMessage((error as Error).message);}}
  async function save(event:FormEvent<HTMLFormElement>) {
    event.preventDefault();setBusy(true);const data=new FormData(event.currentTarget);
    const file=data.get('profile_photo') as File;if(!file?.size)data.delete('profile_photo');
    try{const result=await upload<{user:User}>('/account',data);setUser(result.data.user);setMessage(result.message);}catch(error){setMessage((error as Error).message);}finally{setBusy(false);}
  }
  return <>{!embedded&&<Styles auth />}<main className={embedded?'zd-profile':'zentra-account'}><Link to="/"><img width="140" src="/public-assets/img/Zentra_logo.png" alt="Zentra" /></Link><h1>{embedded?'Mi perfil':`Bienvenido, ${user?.nombre_completo || user?.username||''}`}</h1><p>Tu cuenta está activa. Rol: {user?.rol}. {user?.plan_nombre && `Plan: ${user.plan_nombre}.`}</p><Feedback message={message}/>
    {user&&<details><summary>Editar perfil</summary><form className="auth-form" onSubmit={save} key={user.username}><Field name="username" label="Usuario" defaultValue={user.username}/><Field name="nombre_completo" label="Nombre completo" defaultValue={user.nombre_completo || ''}/>
      <label className="auth-field">Foto de perfil (máximo 2 MB)<input type="file" name="profile_photo" accept="image/png,image/jpeg,image/webp,image/gif"/></label><label className="auth-check"><input name="remove_photo" type="checkbox" value="1"/><span>Quitar foto actual</span></label>
      <Field name="current_password" label="Contraseña actual" type="password" autoComplete="current-password" required={false}/><Field name="new_password" label="Nueva contraseña" type="password" autoComplete="new-password" required={false}/><Field name="confirm_password" label="Confirmar nueva contraseña" type="password" autoComplete="new-password" required={false}/><button className="auth-submit" disabled={busy}>Guardar perfil</button>
    </form></details>}<button className="auth-submit" onClick={logout}>Cerrar sesión</button></main></>;
}
function Payment({canceled=false}: {canceled?: boolean}) {
  const [message,setMessage]=useState('El acceso se habilita cuando Stripe confirma el pago.'),navigate=useNavigate(),location=useLocation();
  useEffect(()=>{
    if(canceled)return;const session_id=new URLSearchParams(location.search).get('session_id');if(!session_id)return;let canceledEffect=false;
    api<{redirect?:string}>('/billing/complete-checkout',{session_id}).then(result=>{if(canceledEffect)return;if(result.data.redirect)navigate(result.data.redirect,{replace:true});else setMessage(result.message);}).catch(error=>{if(!canceledEffect)setMessage(error.message);});
    return()=>{canceledEffect=true;};
  },[canceled,location.search,navigate]);
  return <><Styles /><main className="container section-padding"><h1>{canceled ? 'Pago cancelado' : 'Confirmación de pago'}</h1><p>{canceled ? 'Tu pago no se completó. Puedes volver a elegir una membresía.' : message}</p><Link className="btn_one" to={canceled ? '/register' : '/login'}>{canceled ? 'Volver al registro' : 'Iniciar sesión'}</Link></main></>;
}
function LegacyRedirect() {
  const path=useLocation().pathname.toLowerCase();
  if(path.includes('/pages/dashboard/'))return <LegacyDashboardRedirect/>;
  const page=path.split('/').pop();
  const to=page==='about.php'?'/about':page==='pricing.php'?'/pricing':page==='contact.php'?'/contact':page==='login.php'?'/login':page==='register.php'?'/register':page==='select-plan.php'?'/select-plan':page==='payment_success.php'?'/payment/success':page==='payment_cancel.php'?'/payment/cancel':path.includes('/admin/')?'/admin':path.includes('/app/')||path==='/src/index.php'||path.includes('dashboard')?'/app':path==='/index.php'||path==='/public/index.php'||path==='/src/public/index.php'?'/':null;
  return to ? <Navigate to={to+location.search} replace /> : <main className="container section-padding"><h1>Página no encontrada</h1><Link to="/">Volver a Zentra</Link></main>;
}
createRoot(document.getElementById('root')!).render(<StrictMode><BrowserRouter><Routes>
  <Route element={<PublicLayout />}><Route index element={<Home />} /><Route path="about" element={<About />} /><Route path="pricing" element={<Pricing />} /><Route path="contact" element={<Contact />} /></Route>
  <Route path="login" element={<AuthPage />} /><Route path="register" element={<AuthPage register />} />
  <Route path="select-plan" element={<Protected><SelectPlan /></Protected>} /><Route path="admin/*" element={<Dashboard role="admin" profile={<Account embedded/>}/>} /><Route path="app/*" element={<Dashboard role="user" profile={<Account embedded/>}/>} />
  <Route path="payment/success" element={<Payment />} /><Route path="payment/cancel" element={<Payment canceled />} /><Route path="*" element={<LegacyRedirect />} />
</Routes></BrowserRouter></StrictMode>);
