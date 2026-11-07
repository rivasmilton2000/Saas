import {test,expect} from '@playwright/test';
import {mkdirSync} from 'node:fs';
import {contactServices,contactSubject,webServices} from '../../src/shared/public-services';

for(const width of [1440,390]){
  test(`publico y consultas de servicios en ${width<1200?'movil':'escritorio'}`,async({page,browser,baseURL})=>{
    await page.setViewportSize({width,height:960});
    const errors:string[]=[],missing:string[]=[];page.on('pageerror',error=>errors.push(error.message));page.on('response',response=>{if(response.status()>=400&&/\/(public-assets|assets)\//.test(response.url()))missing.push(response.url());});
    await page.goto('/');await expect(page.getByRole('heading',{name:'¿Qué ofrecemos?'})).toBeVisible();await expect(page.getByText('Centraliza tu contabilidad, compras y ventas.',{exact:false})).toBeVisible();await expect(page.locator('.single_benefit')).toHaveCount(9);
    await expect(page.locator('.single_benefit').first()).toHaveCSS('padding','35px 30px');await expect(page.locator('#servicios-web .single_benefit').first()).toHaveCSS('background-color','rgb(255, 255, 255)');
    const originalStyle=await page.locator('.single_benefit').first().evaluate(node=>{const css=getComputedStyle(node);return {color:css.backgroundColor,radius:css.borderRadius,padding:css.padding,font:css.fontFamily};});
    const webStyle=await page.locator('#servicios-web .single_benefit').first().evaluate(node=>{const css=getComputedStyle(node);return {color:css.backgroundColor,radius:css.borderRadius,padding:css.padding,font:css.fontFamily};});expect(webStyle).toEqual(originalStyle);
    if(width<1200)await page.getByRole('button',{name:'Abrir menu'}).click();await page.getByRole('navigation',{name:'Navegacion principal'}).getByRole('link',{name:'Servicios',exact:true}).click();await expect(page).toHaveURL('/#servicios');await expect.poll(()=>page.evaluate(()=>window.scrollY)).toBeGreaterThan(100);
    mkdirSync('.runtime/screenshots',{recursive:true});await page.screenshot({path:`.runtime/screenshots/publico-${width}.png`,fullPage:true});
    for(const service of webServices){
      await page.goto('/#servicios-web');const card=page.locator('#servicios-web article').filter({has:page.getByRole('heading',{name:contactServices[service.id],exact:true})});await expect(card).toBeVisible();await expect(card).not.toContainText(/\$|garantiz|\d+\s*(horas|días)/i);await card.getByRole('link',{name:service.action,exact:true}).click();await expect(page).toHaveURL(`/contact?service=${service.id}`);await expect(page.getByLabel('Servicio de interés')).toHaveValue(service.id);
      await page.getByLabel('Nombre completo',{exact:true}).fill('Cliente de servicios web');const email=`${service.id}-${width}@example.com`;await page.getByLabel('Correo electrónico',{exact:true}).fill(email);await page.getByLabel('Asunto',{exact:true}).fill('Consulta desde sitio publico');await page.getByLabel('Mensaje',{exact:true}).fill('Quiero conocer las opciones para mi negocio.');
      const [request,response]=await Promise.all([page.waitForRequest(request=>request.url().endsWith('/api/public/contact')&&request.method()==='POST'),page.waitForResponse(response=>response.url().endsWith('/api/public/contact')&&response.request().method()==='POST'),page.getByRole('button',{name:'Enviar Mensaje',exact:true}).click()]);
      expect(request.postDataJSON().service).toBe(service.id);expect(response.status()).toBe(201);expect((await response.json()).data).toMatchObject({saved:true,emailSent:false,service:service.id});await expect(page.getByRole('alert')).toContainText('Mensaje guardado');await expect(page.getByLabel('Servicio de interés')).toHaveValue('general');
      await page.locator('.footer').getByRole('link',{name:contactServices[service.id],exact:true}).click();await expect(page.getByLabel('Servicio de interés')).toHaveValue(service.id);await expect(page.getByRole('alert')).toHaveCount(0);
    }
    await page.goto('/');await page.getByRole('link',{name:'Consultar servicios contables',exact:true}).click();await expect(page.getByLabel('Servicio de interés')).toHaveValue('contabilidad');
    await page.goto('/contact?service=no-definido');await expect(page.getByLabel('Servicio de interés')).toHaveValue('general');
    await page.goto('/pricing');await expect(page.locator('.pricing_card')).toHaveCount(5);await page.getByRole('link',{name:'Solicitar Cotización',exact:true}).click();await expect(page.getByLabel('Servicio de interés')).toHaveValue('contabilidad');await expect(page.getByLabel('Asunto',{exact:true})).toHaveValue('Consulta sobre el plan Enterprise');
    await page.goto('/about');await page.getByRole('link',{name:'Conocer servicios web',exact:true}).click();await expect(page).toHaveURL('/#servicios-web');await expect(page.locator('#servicios-web')).toBeVisible();await expect.poll(()=>page.evaluate(()=>document.querySelector('#servicios-web')!.getBoundingClientRect().top)).toBeLessThan(100);
    const footer=page.locator('.footer');for(const service of webServices){await footer.getByRole('link',{name:contactServices[service.id],exact:true}).click();await expect(page.getByLabel('Servicio de interés')).toHaveValue(service.id);}
    await expect.poll(()=>page.evaluate(()=>window.scrollY)).toBeLessThan(100);
    if(width<1200){await page.getByRole('button',{name:'Abrir menu'}).click();await page.getByRole('navigation').getByRole('link',{name:'Contactanos',exact:true}).click();await expect(page.getByRole('button',{name:'Abrir menu'})).toHaveAttribute('aria-expanded','false');await expect(page.getByLabel('Servicio de interés')).toHaveValue('general');}
    await expect(page.locator('a[href="#"]')).toHaveCount(0);
    await page.screenshot({path:`.runtime/screenshots/contacto-${width}.png`,fullPage:true});expect(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth)).toBeTruthy();
    const admin=await browser.newContext({baseURL});try{const csrf=(await (await admin.request.get('/api/auth/csrf')).json()).data.csrf;expect((await admin.request.post('/api/auth/login',{headers:{'X-CSRF-Token':csrf},data:{username:'demo_admin',password:'AdminDemo!2026'}})).status()).toBe(200);const messages=(await (await admin.request.get('/api/admin/contacts')).json()).data;for(const service of webServices){const message=messages.find((row:{email:string})=>row.email===`${service.id}-${width}@example.com`);expect(message.asunto).toBe(contactSubject(service.id,'Consulta desde sitio publico'));expect(message.mensaje).toBe('Quiero conocer las opciones para mi negocio.');}}finally{await admin.close();}
    expect(errors).toEqual([]);expect(missing).toEqual([]);
  });
}
