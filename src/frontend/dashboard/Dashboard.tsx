import {useEffect,useState,type ReactNode} from 'react';
import {Link,NavLink,Navigate,Route,Routes,useLocation,useNavigate} from 'react-router-dom';
import {api,type User} from '../api';
import {DashboardContext,Feedback,Loading,localToday,useRemote,type Company} from './shared';
import {AccountsPage,BalancePage,BooksPage,CompaniesPage,JournalPage,UserOverview} from './user-pages';
import {AdminOverview,AdminPlans,AdminTable,AdminUsers,ServiceSettings} from './admin-pages';
import './dashboard.css';

function DashboardStyles(){return <><link rel="stylesheet" href="/dashboard-assets/vendors/css/vendor.bundle.base.css"/><link rel="stylesheet" href="/dashboard-assets/vendors/mdi/css/materialdesignicons.min.css"/><link rel="stylesheet" href="/dashboard-assets/css/style.css"/></>;}
export function LegacyDashboardRedirect(){
  const session=useRemote<{user:User;redirect:string}>('/auth/me'),path=useLocation().pathname;
  if(session.loading)return <Loading/>;if(!session.data)return <Navigate to="/login" replace/>;
  const admin=session.data.user.rol==='admin',base=admin?'/admin':'/app';
  const section=path.match(/\/dashboard\/([^/]+)/)?.[1]||'';
  const mapping:Record<string,string>=admin?{users:'usuarios',companies:'empresas',audit:'bitacora',mail:'contactos',profile:'perfil'}:{companies:'empresas',books:'libros',profile:'perfil'};
  return <Navigate to={base+(mapping[section]?'/'+mapping[section]:'')} replace/>;
}
export function Dashboard({role,profile}:{role:'admin'|'user';profile:ReactNode}){
  const session=useRemote<{user:User;redirect:string}>('/auth/me');
  if(session.loading)return <><DashboardStyles/><Loading/></>;
  if(!session.data)return <Navigate to="/login" replace/>;
  if(session.data.user.rol!==role)return <><DashboardStyles/><main className="zd-denied"><span>403</span><h1>Acceso denegado</h1><p>Tu cuenta no tiene el rol necesario para esta sección.</p><Link className="btn btn-primary" to={session.data.redirect}>Volver a mi dashboard</Link></main></>;
  if(role==='user'&&!session.data.user.id_plan)return <Navigate to="/select-plan" replace/>;
  return <DashboardShell user={session.data.user} refreshUser={session.reload} profile={profile}/>;
}
function DashboardShell({user,refreshUser,profile}:{user:User;refreshUser:()=>void;profile:ReactNode}){
  const admin=user.rol==='admin',base=admin?'/admin':'/app',navigate=useNavigate(),location=useLocation();
  const companies=useRemote<Company[]>(admin?null:'/empresas');
  const storage=`zentra.dashboard.${user.id}`;
  const [selected,setSelected]=useState(()=>Number(localStorage.getItem(storage+'.company'))||0),[menu,setMenu]=useState(false),[error,setError]=useState('');
  const date=localToday();
  const [range,setRange]=useState(()=>{try{const saved=JSON.parse(localStorage.getItem(storage+'.range')||'null');if(saved&&/^\d{4}-\d{2}-\d{2}$/.test(saved.from)&&/^\d{4}-\d{2}-\d{2}$/.test(saved.to)&&saved.from<=saved.to)return saved as {from:string;to:string};}catch{}return {from:date.slice(0,4)+'-01-01',to:date};});
  const [draft,setDraft]=useState(range);
  const companyId=companies.data?.some(company=>company.id===selected)?selected:(companies.data?.find(company=>company.es_ultima_usada)?.id||companies.data?.[0]?.id||0);
  const company=companies.data?.find(company=>company.id===companyId);
  function setCompany(id:number){setSelected(id);localStorage.setItem(storage+'.company',String(id));}
  useEffect(()=>{setMenu(false);window.scrollTo(0,0);document.title=admin?'Administración | Zentra':'Contabilidad | Zentra';},[location.pathname,admin]);
  async function logout(){try{await api('/auth/logout',{});navigate('/login');}catch(error){setError((error as Error).message);}}
  const links=admin?[
    ['', 'Resumen','view-dashboard-outline'],['usuarios','Usuarios','account-group-outline'],['empresas','Empresas','domain'],['planes','Planes','layers-outline'],['suscripciones','Suscripciones','credit-card-outline'],['pagos','Pagos','cash-multiple'],['contactos','Mensajes','email-outline'],['bitacora','Bitácora','history'],['configuracion','Servicios','cog-outline'],
  ]:[['','Resumen','view-dashboard-outline'],['empresas','Empresas','domain'],['diario','Libro diario','notebook-outline'],['cuentas','Catálogo de cuentas','format-list-bulleted'],['balance','Balance de comprobación','scale-balance'],['libros','Libros DTE','book-open-page-variant-outline']];
  return <><DashboardStyles/><DashboardContext.Provider value={{user,companyId,companies:companies.data||[],setCompany,refreshCompanies:companies.reload,from:range.from,to:range.to,refreshUser}}>
    <div className={`zd-shell ${menu?'zd-menu-open':''}`}>
      <header className="zd-navbar"><div className="zd-brand"><button className="zd-icon-button" aria-label="Alternar menú" aria-expanded={menu} onClick={()=>setMenu(!menu)}><i className="mdi mdi-menu"/></button><Link to={base}><img src="/public-assets/img/Zentra_logo.png" alt="Zentra"/></Link></div>
        <div className="zd-welcome"><span>Bienvenido, <strong>{user.nombre_completo||user.username}</strong></span><small>{admin?'Administrador · Visión general del sistema':`Usuario · ${company?.nombre||'Selecciona una empresa'}`}</small></div>
        <div className="zd-navbar-actions"><Link to={base+'/perfil'} className="zd-avatar" aria-label="Mi perfil">{(user.nombre_completo||user.username).slice(0,2).toUpperCase()}</Link><button className="zd-icon-button" aria-label="Cerrar sesión" onClick={logout}><i className="mdi mdi-logout"/></button></div>
      </header>
      {menu&&<button className="zd-sidebar-overlay" aria-label="Cerrar menú" onClick={()=>setMenu(false)}/>}
      <aside className="zd-sidebar"><p className="zd-nav-category">{admin?'Administración':'Mi contabilidad'}</p><nav aria-label="Menú del dashboard">{links.map(([path,label,icon])=><NavLink key={path} end={!path} to={base+(path?'/'+path:'')} className={({isActive})=>'zd-nav-item'+(isActive?' active':'')}><i className={`mdi mdi-${icon}`} aria-hidden="true"/><span>{label}</span></NavLink>)}<p className="zd-nav-category">Mi cuenta</p><NavLink to={base+'/perfil'} className="zd-nav-item"><i className="mdi mdi-account-circle-outline"/><span>Mi perfil</span></NavLink><Link to="/" className="zd-nav-item"><i className="mdi mdi-web"/><span>Sitio público</span></Link></nav><div className="zd-sidebar-foot"><span className="zd-dot"/> Sesión de {admin?'Administrador':'Usuario'}<small>Zentra · Gestión empresarial</small></div></aside>
      <main className="zd-main"><div className="zd-context-bar">{!admin&&<label className="zd-field zd-company-selector"><span>Empresa activa</span><select aria-label="Empresa activa" value={companyId} onChange={event=>setCompany(Number(event.target.value))}>{!companyId&&<option value="0">Sin empresas</option>}{companies.data?.map(company=><option key={company.id} value={company.id}>{company.nombre}</option>)}</select></label>}
        <form className="zd-period-filter" onSubmit={event=>{event.preventDefault();if(draft.from>draft.to){setError('La fecha inicial debe ser anterior a la final.');return;}setError('');setRange(draft);localStorage.setItem(storage+'.range',JSON.stringify(draft));}}><label className="zd-field"><span>Desde</span><input type="date" aria-label="Desde" value={draft.from} onChange={event=>setDraft({...draft,from:event.target.value})} required/></label><label className="zd-field"><span>Hasta</span><input type="date" aria-label="Hasta" value={draft.to} onChange={event=>setDraft({...draft,to:event.target.value})} required/></label><button className="btn btn-outline-primary">Aplicar periodo</button></form>
      </div><Feedback error={error||companies.error}/>{!admin&&companies.loading?<Loading/>:<Routes>
        {admin?<><Route index element={<AdminOverview/>}/><Route path="usuarios" element={<AdminUsers/>}/><Route path="planes" element={<AdminPlans/>}/><Route path="empresas" element={<AdminTable kind="companies"/>}/><Route path="suscripciones" element={<AdminTable kind="subscriptions"/>}/><Route path="pagos" element={<AdminTable kind="payments"/>}/><Route path="contactos" element={<AdminTable kind="contacts"/>}/><Route path="bitacora" element={<AdminTable kind="audit"/>}/><Route path="configuracion" element={<ServiceSettings/>}/></>:<><Route index element={<UserOverview/>}/><Route path="empresas" element={<CompaniesPage/>}/><Route path="cuentas" element={<AccountsPage/>}/><Route path="diario" element={<JournalPage/>}/><Route path="balance" element={<BalancePage/>}/><Route path="libros" element={<BooksPage/>}/></>}
        <Route path="perfil" element={profile}/><Route path="*" element={<div className="zd-empty"><h1>Página no encontrada</h1><Link to={base}>Ir al resumen</Link></div>}/>
      </Routes>}<footer className="zd-footer">© {date.slice(0,4)} Zentra. Tu información, en orden.</footer></main>
    </div>
  </DashboardContext.Provider></>;
}
