import modules from './book-modules.json';
type Row = Record<string,any>;
const formatDate=(value: unknown)=>typeof value==='string' && /^\d{4}-\d{2}-\d{2}$/.test(value) ? value.split('-').reverse().join('/') : value;
export function report(book: Row,records: Row[]) {
  const module=(modules as Record<string,{nombre:string;columnas:Record<string,string>;columnas_numericas:string[]}>)[book.tipo];
  const columns=Object.entries(module.columnas).map(([key,label])=>({key,label}));
  const rows=records.map((record,index)=>{
    const row:Row={...record,no:index+1,fecha:formatDate(record.fecha),dia_emision:formatDate(record.dia_emision),fecha_emision:formatDate(record.fecha_emision_retencion || record.fecha),
      tipo_documento:record.tipo_documento_relacionado || record.tipo_dte,
      ventas_exentas_cuenta_terceros:record.ventas_exentas || '0.00',ventas_internas_gravadas_cuenta_terceros:record.ventas_internas_gravadas || '0.00',debito_fiscal_cuenta_terceros:record.debito_fiscal || '0.00',numero_anexo:record.numero_anexo || String(index+1)};
    return Object.fromEntries(columns.map(({key})=>[key,row[key] ?? '']));
  });
  const totals=Object.fromEntries(module.columnas_numericas.map(key=>[key,(rows.reduce((sum,row)=>sum+Math.round(Number(row[key] || 0)*100),0)/100).toFixed(2)]));
  const totalRow=Object.fromEntries(columns.map(({key},index)=>[key,totals[key] ?? (index===1?'TOTALES DEL MES':'')]));
  return {title:module.nombre,columnas:columns,registros:rows,filas:rows.length?[...rows,totalRow]:[],fila_totales:totalRow,totales:totals};
}
const escape=(value: unknown)=>String(value ?? '').replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]!));
export function reportHtml(book: Row,records: Row[]) {
  const data=report(book,records);
  return `<!doctype html><html lang="es"><head><meta charset="utf-8"><title>${escape(data.title)}</title><style>body{font:14px Arial;margin:24px;color:#172033}table{border-collapse:collapse;width:100%}th,td{border:1px solid #dbe4f0;padding:8px}th{background:#172033;color:white}tr:last-child{font-weight:bold}h1{color:#4b49ac}@media print{body{margin:0}}</style></head><body><h1>${escape(data.title)}</h1><p>${escape(book.empresa_nombre)} · ${escape(book.mes)}/${escape(book.anio)}</p><table><thead><tr>${data.columnas.map(column=>`<th>${escape(column.label)}</th>`).join('')}</tr></thead><tbody>${data.filas.map(row=>`<tr>${data.columnas.map(column=>`<td>${escape(row[column.key])}</td>`).join('')}</tr>`).join('')}</tbody></table></body></html>`;
}
