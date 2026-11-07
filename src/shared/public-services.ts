/** Catalogo publico compartido por React y Nest. No contiene precios ni configuracion privada. */
export const contactServices = {
  general: 'Consulta general',
  contabilidad: 'Servicios contables',
  'diseno-web': 'Diseño y desarrollo de sitios',
  dominios: 'Registro y configuración de dominios',
  hosting: 'Hosting y publicación de sitios',
} as const;

export type ContactService = keyof typeof contactServices;
export function selectedContactService(value: string | null): ContactService {
  return value && Object.hasOwn(contactServices, value) ? value as ContactService : 'general';
}
export function contactSubject(service: ContactService, subject: string): string {
  return service === 'general' ? subject : `[${contactServices[service]}] ${subject}`;
}
export function contactSubjectLimit(service: ContactService): number {
  return 150 - (service === 'general' ? 0 : contactServices[service].length + 3);
}
export const webServices = [
  {id: 'diseno-web', icon: 'ti-layout', action: 'Consultar diseño web',
    description: 'Cuéntanos si necesitas una página para tu negocio, un catálogo de servicios o una web para presentar tu trabajo.'},
  {id: 'dominios', icon: 'ti-world', action: 'Consultar dominios',
    description: 'Solicita ayuda para elegir y registrar un dominio, configurar sus DNS o conectarlo con tu sitio.'},
  {id: 'hosting', icon: 'ti-server', action: 'Consultar hosting',
    description: 'Consulta opciones de alojamiento y apoyo para configurar el hosting y publicar tu sitio web.'},
] as const;
