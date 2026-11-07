# Zentra · etapa 3

Aplicacion activa: **NestJS + TypeScript**, **React + TypeScript**, MySQL/MariaDB.
Los dashboards de Usuario y Administrador comparten el diseño de `src/pages/dashboard`.
Incluyen empresas, diario de doble partida, catálogo, balance, libros DTE y administración.
El sitio público conserva el diseño original e incorpora servicios de sitios web, dominios y
hosting, con consultas clasificadas mediante el formulario de contacto existente.
El PHP anterior se conserva como referencia protegida.

Desde PowerShell, en la raiz real del repositorio:

```powershell
Set-Location C:\xampp\htdocs\saas\Saas
npm.cmd ci
# Solo en una instalacion nueva; no sobrescribir el .env local existente:
# Copy-Item .env.example .env
# Configurar DB_* y SESSION_SECRET. La base indicada por DB_NAME debe existir.
npm.cmd run db:migrate
npm.cmd run build
npm.cmd start
```

Abrir **http://localhost:3000**. Nest sirve la API y el frontend compilado.
MySQL de XAMPP debe estar encendido. Apache es opcional; su URL anterior
`http://localhost/saas/Saas/` redirige a `APP_URL` conservando la ruta.
La cuenta administrativa existente mantiene su clave.

Desarrollo con recarga:

```powershell
npm.cmd run dev
```

Abrir **http://localhost:5173**. La API sigue en el puerto 3000; detener `npm.cmd start`
antes de ejecutar `dev`. Las variables `APP_URL` y `FRONTEND_ORIGIN` deben coincidir
con los origenes configurados en `.env.example`.

Verificacion:

```powershell
npm.cmd run build:backend
npm.cmd run build:frontend
npm.cmd run typecheck:tests
npm.cmd run test:integration
npx.cmd playwright install chromium
npm.cmd run test:browser
```

Las pruebas crean y eliminan bases `zentra_test_<identificador>` en el servidor local.
La cuenta MySQL necesita permiso CREATE/DROP DATABASE para esas pruebas; la aplicacion
normal no lo necesita. No ejecutar dos suites de navegador simultaneamente: usan el puerto 3101.

Conexion unica: [`conexion/conexiones.ts`](conexion/conexiones.ts).
Sitio público, arranque y revisión final: [`docs/ETAPA_3.md`](docs/ETAPA_3.md).
Rutas por rol, modelo y credenciales demo: [`docs/ETAPA_2.md`](docs/ETAPA_2.md).

Para preparar las **dos cuentas de demostración** en desarrollo:

```powershell
# Solo si no existe; editar las claves antes del primer seed si se desean otras.
if (-not (Test-Path .env.demo)) { Copy-Item .env.demo.example .env.demo }
npm.cmd run db:migrate
npm.cmd run seed:demo
```

Con los valores del ejemplo, ingresar en **http://localhost:3000/login**:

| Rol | Usuario | Contraseña de desarrollo | Entrada |
|---|---|---|---|
| Administrador | `demo_admin` | `AdminDemo!2026` | `/admin` |
| Usuario | `demo_usuario` | `UsuarioDemo!2026` | `/app` |

`.env.demo` está ignorado por Git. El seed conserva cuentas y datos existentes, no restablece
contraseñas y se rechaza en producción. Puede ejecutarse varias veces sin duplicar filas.
Las tres cuentas anteriores se conservan; estas dos cuentas demo son adicionales.
Los pagos demo son filas ficticias, sin operaciones contra Stripe.

En una instalacion nueva, despues de las migraciones, se puede crear un administrador:

```powershell
npm.cmd run create-admin -- nombre_admin
```

El comando conserva cualquier cuenta que ya exista. Para una cuenta nueva muestra una clave
aleatoria una sola vez; tambien acepta `ZENTRA_ADMIN_PASSWORD` por variable de entorno.
Los scripts PHP antiguos y `src/package.json` pertenecen a la implementacion de referencia;
los comandos actuales se ejecutan desde el `package.json` de la raiz.
