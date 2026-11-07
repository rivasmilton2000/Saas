export interface User {id: number; username: string; rol: 'admin'|'user'; email: string|null; nombre_completo: string|null; id_plan: number|null; plan_nombre: string|null;}
export interface Plan {id_plan: number; slug: string; nombre: string; descripcion: string; precio: string|null; periodo: string; destacado: number; personalizado: number; caracteristicas: {caracteristica: string; incluido: number}[];}
interface Envelope<T> {success: boolean; data: T; message: string;}
let csrf = '';
let csrfRequest: Promise<{csrf: string;googleClientId: string;googleNonce: string}> | undefined;
export function csrfConfig() {
  return csrfRequest ||= fetchCsrf().finally(() => {csrfRequest=undefined;});
}
async function fetchCsrf() {
  const response = await fetch('/api/auth/csrf', {credentials: 'include'});
  const body = await response.json() as Envelope<{csrf: string; googleClientId: string; googleNonce: string}>;
  if (!response.ok) throw new Error(body.message || 'No se pudo conectar con Zentra.');
  csrf = body.data.csrf;
  return body.data;
}
export async function api<T>(path: string, data?: unknown, method = 'POST'): Promise<Envelope<T>> {
  if (data !== undefined && !csrf) await csrfConfig();
  const response = await fetch(`/api${path}`, {method: data === undefined ? 'GET' : method, credentials: 'include',
    headers: data === undefined ? {} : {'Content-Type': 'application/json','X-CSRF-Token': csrf},
    body: data === undefined ? undefined : JSON.stringify(data)});
  const body = await response.json() as Envelope<T>;
  if (!response.ok || !body.success) throw new Error(body.message || 'No se pudo completar la operacion.');
  const result = body.data as {csrf?: string} | null;
  if (result?.csrf) csrf = result.csrf;
  return body;
}
export async function upload<T>(path:string,data:FormData):Promise<Envelope<T>> {
  if(!csrf)await csrfConfig();
  const response=await fetch('/api'+path,{method:'POST',credentials:'include',headers:{'X-CSRF-Token':csrf},body:data});
  const body=await response.json() as Envelope<T>;
  if(!response.ok || !body.success)throw new Error(body.message || 'No se pudo guardar el perfil.');
  return body;
}
