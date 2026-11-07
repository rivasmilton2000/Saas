// Reglas recuperadas de services/{DteDataService,VentasLibroService,RetencionIvaService,ValidadorDTE}.php.
export type Document = Record<string, any>;
export const validTypes: Record<string,string[]> = {compras:['03','05','06'],ventas_consumidor:['01'],ventas_contribuyente:['03','05','06'],retencion_iva:['14']};
const key = (value: string) => value.toLowerCase().replace(/[^a-z0-9]/g,'');
function candidates(object: any, keys: string[], depth = 0): any[] {
  if (!object || typeof object !== 'object' || depth > 30) return [];
  return Object.entries(object).flatMap(([name,value]) => [...(keys.includes(key(name)) && typeof value !== 'object' ? [value] : []),...candidates(value,keys,depth+1)]);
}
export function text(object: any, keys: string[]): string | null {
  for (const name of keys) if (object?.[name] !== undefined && object[name] !== null && typeof object[name] !== 'object' && String(object[name]).trim()) return String(object[name]).trim();
  const value = candidates(object,keys.map(key)).find(value => value !== null && String(value).trim());
  return value === undefined ? null : String(value).trim();
}
function money(object: any, keys: string[]) {
  const value = text(object,keys), number = Number(value?.replace(/[, ]/g,'') || 0);
  return Number.isFinite(number) ? Math.round((number + Number.EPSILON)*100)/100 : 0;
}
export function normalize(input: any, depth = 0): Document {
  if (depth > 12) throw new Error('Documento anidado en exceso.');
  if (typeof input === 'string') {
    const value = input.replace(/^\uFEFF/,'').trim();
    try {input = JSON.parse(value);} catch {
      const parts = value.split('.');
      if (parts.length !== 3) throw new Error('No se pudo leer el JSON.');
      input = JSON.parse(Buffer.from(parts[1],'base64url').toString('utf8'));
    }
  }
  if (!input || typeof input !== 'object' || Array.isArray(input)) throw new Error('El documento debe ser un objeto JSON.');
  if (input.identificacion || input.codigoGeneracion) return input;
  for (const name of ['dteJson','dte','documento','documentoFiscal','comprobante','documentoJson','json','payload','data','body','result','resultado','respuesta','contenido','archivo','item','documentoFirmado','dteFirmado']) {
    if (!input[name]) continue;
    try {const value=normalize(input[name],depth+1);if (value.identificacion || value.codigoGeneracion) return {...value,...(input.selloRecepcion ? {selloRecepcion:input.selloRecepcion} : {}),...(input.respuestaMH ? {respuestaMH:input.respuestaMH} : {})};} catch { /* Probar el siguiente contenedor. */ }
  }
  return input;
}
export function expand(input: any): {archivo: string; payload: any}[] {
  if (typeof input === 'string') {try{input=JSON.parse(input.replace(/^\uFEFF/,''));}catch{return [{archivo:'Documento',payload:input}];}}
  if (Array.isArray(input)) return input.flatMap((item,index) => {
    if (item?.payload !== undefined) return expand(item.payload).map(value=>({...value,archivo:item.nombre_archivo || item.archivo || `documento_${index+1}.json`}));
    return [{archivo:`documento_${index+1}.json`,payload:item}];
  });
  if (input && typeof input === 'object') for (const name of ['facturas','documentos','dtes','comprobantes','items','detalle','data','resultado','lote','loteDte']) if (Array.isArray(input[name])) return expand(input[name]);
  return [{archivo:'Documento',payload:input}];
}
function date(value: string | null) {
  if (!value) return new Intl.DateTimeFormat('sv-SE',{timeZone:'America/El_Salvador'}).format(new Date());
  const normalized=value.slice(0,10);
  if (!/^\d{4}-\d{2}-\d{2}$/.test(normalized) || Number.isNaN(Date.parse(normalized)) || new Date(normalized).toISOString().slice(0,10)!==normalized) throw new Error('Fecha no valida.');
  return normalized;
}
export function mapDocument(input: Document, type: string): Document {
  const payload=normalize(input), id=payload.identificacion || {}, summary=payload.resumen || {}, emitter=payload.emisor || {}, receiver=payload.receptor || payload.destinatario || {};
  const provider=Object.keys(emitter).length ? emitter : Object.keys(receiver).length ? receiver : payload.sujetoExcluido || {};
  const code=text(id,['codigoGeneracion']) || text(payload,['codigoGeneracion']);
  const control=text(id,['numeroControl']) || text(payload,['numeroControl']);
  const stamps=candidates(payload,['sellorecepcion','sellorecibido','sellorecepcionmh','sellorecibidomh','selloderecepcion']).map(String).filter(Boolean).sort((a,b)=>b.length-a.length);
  const result: Document = {
    codigo_generacion:code,tipo_dte:text(id,['tipoDte']) || text(payload,['tipoDte']),sello_recepcion:stamps[0] || null,
    numero_control:control,numero_control_completo:control,fecha:date(text(id,['fecEmi','fechaEmision']) || text(payload,['fecEmi','fechaEmision'])),
    nrc:text(provider,['nrc']),nit:text(provider,['nit']),nombre_proveedor:text(provider,['nombre']),
    ventas_internas:money(summary,['totalGravada','subTotalVentas','comprasGravadas','gravadasLocales']),
    ventas_importacion:money(summary,['importacion','totalImportaciones','comprasImportacion','gravadaImportacion']),
    ventas_internas_exentas:money(summary,['totalExenta','exenta','comprasExentas']),
    ventas_importacion_exentas:money(summary,['totalNoSuj','noSujeta','importacionExenta']),
    credito_fiscal:money(summary,['creditoFiscal','totalIva','ivaTotal']),
    total_compras:money(summary,['totalCompras','montoTotalOperacion','totalPagar','totalOperacion']),
    iva_percibido:money(summary,['ivaPercibido1','ivaPerci1','ivaPercibido']),iva_retenido:money(summary,['ivaRetenido1','ivaRete1','ivaRetenido']),
    raw_json:JSON.stringify(payload),
  };
  if (type.startsWith('ventas_')) {
    const exempt=money(summary,['totalExentas','totalExenta','exenta']);
    const taxable=money(summary,['subTotalVentas','totalGravadas','totalGravada']);
    const debit=money(summary,['totalIva','debitoFiscal','ivaTotal']);
    const pre=control?.match(/(\d+)(?!.*\d)/)?.[1]?.replace(/^0+(?=\d)/,'') || null;
    Object.assign(result,{nombre_cliente:text(receiver,['nombre']),nrc_cliente:text(receiver,['nrc']),numero_control_interno:control,numero_control_preimpreso:pre});
    if (type === 'ventas_consumidor') Object.assign(result,{
      dia_emision:result.fecha,del_numero:pre,al_numero:pre,codigo_generacion_desde:code,codigo_generacion_hasta:code,
      ventas_exentas:exempt,ventas_internas_gravadas:taxable,exportaciones:money(summary,['exportaciones','exportacion','totalExportaciones']),ventas_cuenta_terceros:0,
      total_ventas_diarias_propias:Math.round((exempt+taxable+money(summary,['exportaciones','exportacion','totalExportaciones']))*100)/100,
    });
    else Object.assign(result,{ventas_exentas_contribuyente:exempt,ventas_internas_gravadas_contribuyente:taxable,debito_fiscal_contribuyente:debit,ventas_totales:Math.round((exempt+taxable+debit)*100)/100});
  }
  if (type === 'retencion_iva') {
    let related=payload.documentoRelacionado || payload.documentoRelacionado1 || payload.documento || payload.docRelacionado || payload.cuerpoDocumento || {};
    if (Array.isArray(related)) related=related[0] || {};
    Object.assign(result,{
      nit_agente_retencion:text(emitter,['nit']),dui_agente_retencion:text(emitter,['dui']),fecha_emision_retencion:result.fecha,
      tipo_documento_relacionado:text(related,['tipoDocumento','tipoDoc','tipo']) || text(payload,['tipoDocumento','tipoDoc']),
      serie_documento:text(related,['serie','serieDocumento','serieControl']) || text(payload,['serieDocumento','serie']),
      numero_documento:text(related,['numeroDocumento','numDocumento','numeroControl','numero']) || text(payload,['numeroDocumento','numDocumento']),
      monto_sujeto_retencion:money(related,['montoSujetoRetencion','montoSujeto','montoBase','baseImponible','monto']) || money(summary,['montoSujetoRetencion','montoSujeto','baseImponible','subTotal']) || money(payload,['montoSujetoRetencion','montoSujeto']),
      retencion_iva_1:result.iva_retenido || money(summary,['retencionIva1','ivaRetenido1','ivaRete1','montoRetencion','retencion']) || money(payload,['retencionIva1','montoRetencion']),
      numero_anexo:text(related,['numeroAnexo','numAnexo']) || text(payload,['numeroAnexo','numAnexo']),
    });
    result.iva_retenido=result.retencion_iva_1;
  }
  return result;
}
