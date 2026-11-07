// Conversion reproducible del contenido publico estatico; las interacciones viven en React.
import {parseFragment} from 'parse5';
import {readFileSync,writeFileSync,mkdirSync} from 'node:fs';
mkdirSync('src/frontend/pages', {recursive: true});
const route = value => value.replaceAll('assets/', '/public-assets/').replace(/(?:\.\.\/src\/pages\/samples\/)?login\.php/g, '/login')
  .replace(/\bindex\.php/g, '/').replace(/\babout\.php/g, '/about').replace(/\bpricing\.php/g, '/pricing').replace(/\bcontact\.php/g, '/contact');
const aliases = {class:'className',for:'htmlFor',tabindex:'tabIndex',colspan:'colSpan',rowspan:'rowSpan',readonly:'readOnly',autofocus:'autoFocus',autocomplete:'autoComplete',maxlength:'maxLength',frameborder:'frameBorder',allowfullscreen:'allowFullScreen',srcset:'srcSet'};
const booleans = new Set(['required','disabled','checked','multiple','readOnly','autoFocus','allowFullScreen']);
const voids = new Set(['img','input','br','hr','meta','link','source','wbr','area','embed','param','track','col']);
function jsx(node) {
  if (node.nodeName === '#text') return node.value.trim() ? `{${JSON.stringify(node.value)}}` : '';
  if (!node.tagName) return (node.childNodes || []).map(jsx).join('\n');
  if (['script','head'].includes(node.tagName)) return '';
  let attrs = '';
  for (const {name, value} of node.attrs || []) {
    const key = aliases[name] || name;
    if (/^on/.test(key)) continue;
    if (key === 'style') {
      const style = Object.fromEntries(value.split(';').filter(s => s.includes(':')).map(s => {const colon=s.indexOf(':');return [s.slice(0,colon).trim().replace(/-([a-z])/g,(_,c)=>c.toUpperCase()),route(s.slice(colon+1).trim())];}));
      attrs += ` style={${JSON.stringify(style)}}`;
    } else if (booleans.has(key)) attrs += ` ${key}`;
    else if (['rows','cols','tabIndex','colSpan','rowSpan','maxLength'].includes(key)) attrs += ` ${key}={${Number(value)}}`;
    else attrs += ` ${key}=${JSON.stringify(route(value))}`;
  }
  return `<${node.tagName}${attrs}${voids.has(node.tagName) ? ' />' : `>${(node.childNodes||[]).map(jsx).join('\n')}</${node.tagName}>`}`;
}
for (const [file, name] of [['index','Home'],['about','About']]) {
  let html = readFileSync(`src/public/${file}.php`,'utf8').replace(/<\?[\s\S]*?\?>/g,'');
  html = html.match(/<body[^>]*>([\s\S]*?)<\/body>/i)[1].replace(/<!-- START PRELOADER -->[\s\S]*?<!-- END PRELOADER -->/,'');
  writeFileSync(`src/frontend/pages/${name}.tsx`, `// Contenido y clases conservados desde src/public/${file}.php.\nexport function ${name}() { return <>${jsx(parseFragment(html))}</>; }\n`);
}
const footer = readFileSync('src/public/partials/footer.php','utf8').replaceAll('```','');
writeFileSync('src/frontend/pages/Footer.tsx',`export function Footer() { return <>${jsx(parseFragment(footer))}</>; }\n`);
