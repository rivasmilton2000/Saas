# Zentra: cierre de la etapa 3

Verificada el 6 de noviembre de 2026. Repositorio: `C:\xampp\htdocs\saas\Saas`.

## Servicios y diseño público

Se revisaron los originales de `src/public`, sus parciales, CSS y recursos antes de ampliar
las páginas activas de React. Se mantienen Poppins/Oswald, los azules de Zentra, contenedores,
espaciados, navegación, iconos, tarjetas redondeadas y botones del tema. Los archivos PHP y los
recursos originales permanecen como referencia; no se ejecuta PHP para servir la plataforma.

- **Inicio, `/`:** las seis tarjetas anteriores permanecen en «¿Qué ofrecemos?» y se agrega
  una fila de tres tarjetas con el mismo patrón visual: diseño y desarrollo de sitios,
  registro y configuración de dominios, hosting y publicación de sitios.
- **Oferta contable:** Gestión Empresarial describe diario, balance y libros DTE por empresa,
  y permite consultar servicios contables. La tarjeta DTE describe la gestión disponible,
  sin prometer emisión fiscal que la aplicación no implementa.
- **Acerca de, `/about`:** se amplía el texto de servicios y se enlaza la nueva fila de Inicio.
- **Navegación y pie:** «Servicios» lleva a `/#servicios`; `/#servicios-web` identifica la
  nueva fila. El pie ofrece enlaces de consulta para contabilidad y cada servicio web.
  Los iconos sociales originales se conservan como decoración: no había URLs de perfiles
  configuradas y se retiraron sus enlaces vacíos `#`.
- Los nuevos textos describen solicitudes posibles; no añaden precios, garantías ni plazos.

La implementación está en `src/frontend/pages/Home.tsx`, `WebServices.tsx`, `About.tsx`,
`Footer.tsx` y el layout/formulario de `src/frontend/main.tsx`. El catálogo de nombres y textos
compartido por React y Nest está en `src/shared/public-services.ts`.

## Contacto y correcciones

Los botones llevan al formulario existente con una selección explícita:

| Consulta | URL |
|---|---|
| General | `/contact` |
| Contabilidad | `/contact?service=contabilidad` |
| Diseño y desarrollo | `/contact?service=diseno-web` |
| Dominios | `/contact?service=dominios` |
| Hosting y publicación | `/contact?service=hosting` |

El selector «Servicio de interés» envía `service` a `POST /api/public/contact`. Nest valida
el catálogo y guarda el nombre del servicio al inicio del asunto existente; por ejemplo,
`[Registro y configuración de dominios] Consulta para mi negocio`. Administración lo ve en
`/admin/contactos`. El asunto del correo lleva la misma categoría. La consulta general y los
clientes anteriores sin `service` mantienen el asunto sin prefijo. Se respeta el límite de
150 caracteres de `mensajes_contacto.asunto` tanto en React como en Nest.

El formulario mantiene los datos si falla la petición, bloquea envíos mientras está ocupado
y confirma por separado que se guardó el mensaje cuando no pudo enviarse correo.
Una categoría desconocida en la URL selecciona Consulta general; la API rechaza categorías
desconocidas. Enterprise conserva su enlace existente y abre contabilidad con asunto contextual.
Volver a pulsar el mismo servicio después de enviar una consulta restaura su selección y limpia
la confirmación anterior, incluso cuando la URL ya coincide con el enlace.

Se corrigieron el cierre del menú móvil al navegar, el desplazamiento a anclas después de
cargar el CSS y las fuentes, y el fondo de las cabeceras públicas según el tema original.
Las pruebas comprobaron los saltos desde Acerca de y navegación, además de enlaces de tarjetas
y pie, en escritorio de 1440 px y móvil de 390 px, sin desbordamiento horizontal.

No se agregan tablas, columnas ni migraciones en esta etapa. Contacto reutiliza
`mensajes_contacto`. Todo acceso MySQL sigue usando el proveedor global `MysqlService` de
**`conexion/conexiones.ts`**, con un solo pool por instancia Nest. React consume la API.
`src/pages/dashboard` permanece disponible como referencia de las etapas anteriores.

## Levantar Zentra

MySQL de XAMPP debe estar encendido. Conservar el `.env` existente; en instalaciones nuevas,
copiar `.env.example` a `.env` y configurar `DB_*`, `SESSION_SECRET` y los orígenes indicados allí.
La base configurada debe existir.

```powershell
Set-Location C:\xampp\htdocs\saas\Saas
npm.cmd ci
npm.cmd run db:migrate
npm.cmd run build
npm.cmd start
```

Abrir **http://localhost:3000**. Nest sirve React y `/api`. Apache es opcional: la entrada
`http://localhost/saas/Saas/` devuelve 307 hacia `APP_URL` y conserva la ruta.
Para desarrollo, detener `npm.cmd start` y ejecutar **`npm.cmd run dev`**; React abre en
**http://localhost:5173** y usa la API del puerto 3000.

Las cuentas demo se conservan: `demo_admin` → `/admin`, `demo_usuario` → `/app`, ambas desde
`/login`. Las claves de esta instalación son las del ejemplo de desarrollo documentadas en
[`ETAPA_2.md`](ETAPA_2.md); se configuran en el archivo ignorado `.env.demo`.
En una instalación que aún no tenga demos, preparar ese archivo según `.env.demo.example`
y ejecutar **`npm.cmd run seed:demo`**. No sobrescribir configuraciones propias. El seed es
repetible y no restablece claves. Esta etapa no agrega cuentas demo adicionales.

## Revisión final

Comandos ejecutados desde la raíz:

```powershell
npm.cmd run db:migrate
npm.cmd run build
npm.cmd run typecheck:tests
npm.cmd run test:integration
npm.cmd run test:browser
git diff --check
```

Para Chromium en otra computadora: `npx.cmd playwright install chromium`.
Las suites utilizan bases temporales `zentra_test_<identificador>` y requieren CREATE/DROP
DATABASE en el MySQL local. Deshabilitan las credenciales externas dentro del proceso de prueba:
no envían correos ni contactan Google o Stripe. No ejecutar dos suites de navegador a la vez
porque comparten el puerto de prueba 3101.

| Comprobación | Resultado |
|---|---|
| Compilaciones React y Nest, tipos de pruebas | Aprobadas |
| Sitio, tarjetas, estilo compartido, navegación y contacto en escritorio/móvil | Aprobados |
| Selector, cuerpo de petición, persistencia y consulta desde Administración | Aprobados para los tres servicios web y contabilidad/general |
| Categoría inválida, correo/nombre inválidos, límite del asunto | Rechazados antes de escribir; asunto de 150 caracteres guardado |
| Preparación del asunto SMTP | Aprobada con transporte simulado, sin envío real |
| Registro gratuito, duplicados, login por correo y logout | Aprobados en integración; rol público forzado a Usuario |
| Sin Google/Stripe configurados | Respuestas controladas 403/503; sin borradores de pago parciales |
| Login de ambos roles, navegación y dashboards | Aprobados |
| URL directa y API de rol incorrecto | Acceso denegado en React, 403 en Nest; sin sesión, 401 |
| Acceso a empresa/asiento de otro propietario o partidas de otra empresa | Denegado sin escrituras parciales |
| Empresas, catálogo, diario, filtros, balance, anulaciones y recarga | Aprobados |
| Importes decimales, centavos y acumulados grandes | Correctos al guardar, agregar y mostrar tras recargar |
| Libros DTE, registro manual, importación, duplicados y cuota | Aprobados |
| Administración de usuarios/planes y menús móviles | Aprobados |
| Reejecución de migraciones y seed sobre datos existentes/modificados | Aprobada sin duplicados ni sobrescrituras |

**Resultado:** siete escenarios de navegador aprobados, además de la suite de integración.
Capturas inspeccionadas en `.runtime/screenshots` (directorio local ignorado por Git).
Las bases de prueba se eliminaron al finalizar.

También se comprobó el servidor **Nest compilado en el puerto 3000** contra la base local:
`/api/health` devuelve 200 con `database: mysql`; ambos logins abren su dashboard, conservan
la sesión al recargar y la invalidan al salir. El Usuario recibe 403 en contactos administrativos.
El resumen contable conserva resultado **75.00** y diferencia Debe/Haber **0.00**. Se mantienen
cinco cuentas (tres anteriores y dos demos), dos empresas, seis asientos y trece partidas.
Se compararon las filas originales con la instantánea previa de la etapa 2: permanecen intactas.

## Integraciones externas pendientes

La configuración local indica `mail: false`, `google: false`, `stripe: false` y
`stripeWebhook: false`. Por eso no se verificaron entrega SMTP real, autenticación real
con Google ni cobro/suscripción/webhook reales de Stripe. Faltan las credenciales respectivas
`MAIL_*`, `GOOGLE_CLIENT_ID`, `STRIPE_SECRET_KEY` y `STRIPE_WEBHOOK_SECRET` en `.env`.
El contacto sí se guarda y puede revisarse en Administración sin SMTP.
La prueba de asunto de correo usa una simulación; no demuestra entrega a un buzón.

Los servicios web se ofrecen mediante consultas comerciales. El proyecto no define un proveedor
para comprar dominios o aprovisionar hosting automáticamente. No se realizaron compras ni
publicaciones externas. Ningún flujo local de esta revisión quedó bloqueado. Etapa 3 cerrada.
