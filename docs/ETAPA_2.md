# Zentra: etapa 2

Implementada el 6 de noviembre de 2026, hora de El Salvador.
Repositorio: `C:\xampp\htdocs\saas\Saas`. No se inició la etapa 3.

## Revisión previa y diseño compartido

Se revisaron `index.php`, `user.php`, los parciales de encabezado/navbar/sidebar,
las pantallas y formularios de empresas, libros, registros, filtros, tablas y usuarios,
y `assets/css/style.css` dentro de `src/pages/dashboard`. La referencia contiene Star Admin,
tipografía Manrope, barra superior, empresa activa, menú lateral, tarjetas redondeadas,
indicadores, acciones rápidas y tablas con filtros. También contiene dependencias de SIETELSA
que no corresponden al esquema de Zentra; no se ejecutan esos controladores PHP.

La implementación activa está en `src/frontend/dashboard`: layout, contexto, componentes,
formularios modales, tarjetas, tablas, etiquetas, mensajes y estilos compartidos para ambos roles.
Reutiliza los recursos del diseño mediante `/dashboard-assets`. El menú y las pantallas se
derivan de la cuenta autenticada; no se guardan roles enviados por el navegador.
La referencia PHP no se modificó. Se conservaron los cambios locales y movimientos de carpetas.
No se encontraron instrucciones `AGENTS.md` aplicables.

## Rutas por rol

Ambas cuentas ingresan por `/login`. Nest decide el destino al autenticar.

| Usuario | Función |
|---|---|
| `/app` | Resumen por empresa y periodo, resultado, caja/bancos y actividad DTE |
| `/app/empresas` | Alta, edición, selección y baja lógica de empresas propias |
| `/app/cuentas` | Catálogo por empresa, inicialización repetible, cuentas nuevas y activación/desactivación |
| `/app/diario` | Asientos con múltiples partidas, búsqueda, estado, detalle y anulación con motivo |
| `/app/balance` | Apertura, movimientos, saldos deudores/acreedores y exportación CSV |
| `/app/libros` | Cuatro tipos de libro, periodo, filtro, registro manual, importación y exportación |
| `/app/perfil` | Perfil, contraseña y foto de la cuenta |

| Administrador | Función |
|---|---|
| `/admin` | Resumen real de cuentas, empresas, documentos, cobros y actividad |
| `/admin/usuarios` | Crear, editar, filtrar, desactivar y reactivar cuentas; proteger al último administrador |
| `/admin/empresas` | Consulta global de empresas y propietarios |
| `/admin/planes` | Precios, características, límites y estado de planes |
| `/admin/suscripciones` | Historial de suscripciones |
| `/admin/pagos` | Historial de pagos |
| `/admin/contactos` | Consulta de mensajes del sitio público |
| `/admin/bitacora` | Acciones de autenticación, contabilidad y administración |
| `/admin/configuracion` | Estado de servicios, sin revelar secretos |
| `/admin/perfil` | Perfil de la cuenta |

Las URL directas de otro rol muestran **Acceso denegado (403)** en React. Las API devuelven
HTTP 403 y una sesión ausente devuelve 401. Los datos no se descargan para luego ocultarlos
en el frontend. Las páginas antiguas bajo `src/pages/dashboard` llevan a la sección activa
del rol; sus acciones POST anteriores no se ejecutan. `/api/dashboard` y el adaptador
`/src/api/dashboard.php` responden según la sesión.

## Modelo de datos

La migración **`conexion/migrations/002_accounting.sql`** crea cuatro tablas sin ALTER, DROP,
TRUNCATE ni reconstrucción de tablas existentes:

| Tabla nueva | Campos y relaciones principales |
|---|---|
| `contabilidad_cuentas` | Empresa, código único por empresa, nombre, tipo, estado. FK a `empresas`. |
| `contabilidad_asientos` | Empresa, usuario autor, fecha, descripción, referencia, estado, versión y motivo/fecha de anulación. FK a `empresas` y `usuarios`. |
| `contabilidad_partidas` | Empresa, asiento, cuenta, detalle, Debe y Haber `DECIMAL(15,2)`. FK compuestas impiden mezclar empresas. CHECK permite un solo lado positivo. |
| `zentra_demo_registros` | Clave estable, entidad e ID del registro demo, para reutilizarlo en siguientes ejecuciones. |

Se reutilizan `usuarios`, `empresas`, `libros`, `facturas`, `facturas_disponibles`, `planes`,
`planes_caracteristicas`, `subscriptions`, `payments`, `mensajes_contacto` y `bitacora_movimientos`.
No se agregaron columnas a estas tablas ni se cambiaron sus identidades o relaciones existentes.

| Flujo | Persistencia |
|---|---|
| Empresas | `empresas.id_usuario` delimita el propietario; NIT/DUI/NRC usan columnas existentes. |
| Cuentas | `contabilidad_cuentas.id_empresa`, código único y tipo inmutable. |
| Asiento | Cabecera y partidas se guardan juntas; se exige Debe = Haber y total positivo. |
| Anulación | Conserva partidas, motivo y versión; excluye el asiento de los cálculos publicados. |
| Balance | SQL agrega partidas publicadas. Apertura antes de `from`, movimientos dentro del rango, cierre hasta `to`. |
| Libros DTE | `empresas → libros → facturas`, con cuotas por usuario. Reutiliza las reglas de tipos de la etapa 1. |
| Manual DTE | Base, exenta e impuesto se validan; los importes derivados se guardan como decimales. |
| Administración | Las listas e indicadores consultan las tablas existentes; planes editados llegan al catálogo público. |

Todos los accesos nuevos, incluidas migraciones y seed, usan el `MysqlService` global de
**`conexion/conexiones.ts`**. Solo allí existe `createPool`; módulos, sesiones y servicios
comparten el proveedor. No hay acceso MySQL desde React.

Las consultas contables verifican empresa activa y propietario. El ID de una cuenta o asiento
no concede acceso. El rol Administrador consulta metadatos globales y funciones administrativas;
no usa el diario privado del Usuario ni suplanta su identidad. Todas las escrituras HTTP siguen
requiriendo sesión y CSRF.

## Cálculos y persistencia

- Los importes de partidas se validan como cadenas con hasta dos decimales. La comprobación de
  balance usa centavos `BigInt`; MySQL agrega con DECIMAL. React formatea esos decimales sin
  convertirlos a punto flotante para los importes mostrados.
- Resultado del periodo = créditos netos de ingresos − débitos netos de gastos. Activos,
  pasivos, capital aportado y caja/bancos son saldos acumulados hasta la fecha final.
- El balance incluye cuentas inactivas con movimientos históricos. Desactivarlas impide
  usarlas en asientos nuevos, sin borrar movimientos.
- Los documentos DTE y el diario son registros separados. El formulario lo informa para
  evitar contabilización doble: registrar/importar un DTE no crea automáticamente un asiento.
- Los totales de documentos se agregan en MySQL sobre los registros filtrados. Las escrituras
  y el consumo de cuota son transaccionales; un duplicado no consume cuota adicional.
- La empresa seleccionada y el rango se conservan por cuenta en el navegador. Las búsquedas,
  tipo de libro y estado usan la URL. Al recargar se vuelven a consultar los registros guardados.
- Diario y listas administrativas muestran hasta 500 registros; las búsquedas de diario y
  usuarios se aplican antes de ese límite. Los indicadores y balances agregan todos los registros
  del rango, sin ese límite.

## Migraciones, seed y acceso demo

Desde PowerShell:

```powershell
Set-Location C:\xampp\htdocs\saas\Saas
npm.cmd ci
npm.cmd run db:migrate
# Solo si todavía no existe; no sobrescribir una configuración demo propia:
if (-not (Test-Path .env.demo)) { Copy-Item .env.demo.example .env.demo }
# Editar ZENTRA_DEMO_ADMIN_PASSWORD y ZENTRA_DEMO_USER_PASSWORD antes del primer seed.
npm.cmd run seed:demo
npm.cmd run build
npm.cmd start
```

MySQL debe estar encendido y `.env` debe contener DB_* y SESSION_SECRET de la etapa 1.
Abrir **http://localhost:3000/login**. Apache conserva su redirección desde la URL anterior.

En esta instalación se usaron los valores de desarrollo del ejemplo:

| Cuenta | Contraseña de desarrollo | Rol / destino |
|---|---|---|
| `demo_admin` | `AdminDemo!2026` | Administrador → `/admin` |
| `demo_usuario` | `UsuarioDemo!2026` | Usuario → `/app` |

El archivo `.env.demo` está ignorado por Git. Las contraseñas se almacenan con bcrypt de la
aplicación. El seed rechaza producción, conserva claves existentes y se detiene si encuentra
una cuenta del mismo nombre sin marcador demo, en lugar de sobrescribirla.
Para repetirlo, mantener las mismas claves configuradas. Si se cambió la clave desde el perfil,
actualizar `.env.demo` con esa clave; el seed no restablece contraseñas.

Datos ficticios creados:

- Exactamente dos cuentas demo adicionales; se conservaron las tres cuentas anteriores.
- Dos empresas de `demo_usuario`: **Café Aurora Demo** y **Estudio Nube Demo**.
- Once cuentas contables por empresa; seis asientos en total y trece partidas.
- Cuatro libros y cuatro documentos de ejemplo, uno por cada tipo de libro.
- Una suscripción histórica cancelada y un pago ficticio de $6.99, sin consultar ni cobrar en Stripe.
- Un mensaje de contacto y los registros de auditoría correspondientes.

Para Café Aurora, con el periodo que incluye el día del seed, el resultado esperado inicial es:
ingresos **$100.00**, gastos **$25.00**, resultado **$75.00**, caja/bancos **$4,888.00**,
activos **$5,088.00**, pasivos **$13.00**, capital aportado **$5,000.00** y Debe/Haber **$5,338.00**.
Los datos locales se cargaron el **6 de noviembre de 2026**. Si se cambia el periodo, los
indicadores reflejan las fechas guardadas, no números estáticos del seed.

Se ejecutó el seed dos veces en la base local y se comparó una huella de todas las filas
de negocio entre ejecuciones: sin cambios ni duplicados. También se verificó que todas las
filas anteriores a la migración/seed permanecieran presentes e idénticas.

## Verificación

Comandos ejecutados:

```powershell
npm.cmd run typecheck:tests
npm.cmd run build
npm.cmd run test:integration
npm.cmd run test:browser
npm.cmd run seed:demo
npm.cmd run seed:demo
git diff --check
```

Las pruebas de integración y navegador crean una base temporal independiente y la eliminan
al terminar. Requieren permiso CREATE/DROP DATABASE en el MySQL local; la aplicación no lo requiere.
Para instalar Chromium en otra computadora: `npx.cmd playwright install chromium`.

| Prueba | Resultado |
|---|---|
| Compilación React y Nest, tipos de scripts/tests | Pasa |
| React → API Nest → MySQL con proveedor compartido | Pasa |
| Login de ambos roles y destino correcto | Pasa |
| URL directa de rol equivocado y API de rol incorrecto | Pasa: UI denegada, API 403 |
| Asiento o empresa de otro propietario | Pasa: 403, sin escrituras |
| Cuenta contable de otra empresa en un asiento | Pasa: 403, sin cabecera ni partidas parciales |
| Asiento descuadrado y cuenta inactiva | Pasa: rechazo, sin escrituras parciales |
| Diario, detalle, anulación y repetición con versión anterior | Pasa: conserva historial; repetición 409 |
| $0.10 + $0.20 y totales acumulados grandes | Pasa sin perder centavos |
| Balance, resultado y filtros tras recargar | Pasa |
| Alta de empresa y catálogo básico tras recargar | Pasa |
| Cuatro libros: registro manual, totales, duplicados y cuota | Pasa |
| Importación JSON y repetición del mismo documento desde React | Pasa, duplicado sin segunda importación |
| Usuarios: alta, edición, correo vacío, desactivación y reactivación | Pasa |
| Plan editado y precio persistente | Pasa |
| Navegación administrativa y menú móvil | Pasa |
| Segundo seed con datos ya creados/modificados | Pasa, sin duplicados ni sobrescrituras |

Las capturas de escritorio de ambos roles y de móvil se guardan en `.runtime/screenshots`
durante la prueba de navegador; se inspeccionaron visualmente. No se alteró el diseño público.
Resultado final: **cinco escenarios de navegador aprobados**, además de la suite de integración.
Se verificaron también ambos logins, consultas MySQL, recarga y logout contra el Nest compilado
en `http://localhost:3000`, con las dos cuentas demo de la base local.

## Límites de esta etapa

No se activan módulos ajenos al contrato de datos de Zentra solo por estar presentes en la
referencia: builder, correo IMAP, copias de seguridad de SIETELSA y configuración fiscal avanzada.
Las tablas `dte_*` anteriores se conservan separadas; los libros activos usan `empresas`, `libros`
y `facturas`. No se migraron datos entre esos dos conjuntos ni se inventó un mapeo de identidades.

Los cobros, Google y SMTP reales siguen dependiendo de las credenciales externas indicadas en
la etapa 1; las operaciones contables, dashboards y seed de esta etapa no dependen de ellas.
Las exportaciones DTE mantienen Excel HTML e impresión a PDF de la etapa 1; no se afirma
una certificación fiscal ni se generan envíos a Hacienda.
