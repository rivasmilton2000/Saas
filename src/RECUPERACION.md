# Zentra en XAMPP

Trabajo ejecutado el **5 de noviembre de 2026**, hora de El Salvador.
Repositorio: `C:\xampp\htdocs\saas\Saas`, rama `master`, commit base `b3d3007`.

## Estado e inicio

La base **zentra** ya fue creada en el MariaDB local y la aplicacion fue verificada mediante HTTP en Apache.

1. En el panel de XAMPP, mantener **Apache** y **MySQL** encendidos.
2. Abrir **http://localhost/saas/Saas/**.
3. Ingresar como **zentra_admin** con la clave aleatoria guardada en
   `C:\xampp\zentra-private\initial-admin.txt` (archivo local fuera de la carpeta publica).

Conexion comprobada: MariaDB **10.4.32**, `127.0.0.1:3306`; PHP **8.0.30**, PDO MySQL.
Las credenciales reales estan exclusivamente en `src/config/db.local.php`, ignorado por Git.
El nombre `zentra` esta fijado en `src/config/database.php` y en `zentra.sql`.
No se necesita ejecutar npm ni el servidor estatico de Gulp: `dist` es la demostracion HTML.

## Analisis del proyecto actual

Se buscaron instrucciones en los directorios ancestros y dentro del repositorio: no se encontro ningun `AGENTS.md` aplicable.
El arbol de trabajo no contenia SQL, migraciones, inicializadores ni respaldos.
Se revisaron tambien los SQL y respaldos disponibles en las referencias locales de Git, sin cambiar de rama.

Flujo real: `index.php` redirige a `src/index.php`; `session.php` protege el panel;
`pages/samples/login.php` invoca `AuthController::login`, que consulta `usuarios`,
comprueba `estado` y ejecuta `password_verify`; luego guarda ID, username y rol en sesion.
`pages/samples/dashboard.php` muestra username y rol; `logout.php` cierra la sesion.

La unica consulta de negocio de esta version es:

```sql
SELECT id, username, password, rol, estado FROM usuarios WHERE username = ?;
```

No hay modelos ni endpoints CRUD de empresas, libros, facturas, planes o pagos en esta rama.
Los formularios, tablas, cifras y graficos de Skydash son demostraciones estaticas.
El formulario de registro tampoco tenia backend, metodo POST ni campos con `name`;
ahora informa que se debe solicitar una cuenta al administrador y enlaza al login real.
No se inventaron tablas para esos ejemplos ni se implemento un registro publico nuevo.

## Esquema reconstruido

Archivo importable: **`zentra.sql`**, en la raiz del repositorio.
Crea y selecciona `zentra`; usa InnoDB, `utf8mb4` y `utf8mb4_unicode_ci`.

| Tabla | Columna | Tipo y restricciones |
| --- | --- | --- |
| usuarios | id | INT UNSIGNED, PRIMARY KEY, AUTO_INCREMENT |
| usuarios | username | VARCHAR(100), NOT NULL, UNIQUE |
| usuarios | password | VARCHAR(255), NOT NULL; hash compatible con PHP |
| usuarios | rol | VARCHAR(10), NOT NULL, DEFAULT 'user', CHECK admin/user |
| usuarios | estado | TINYINT(1), NOT NULL, DEFAULT 1, CHECK 0/1 |

Los tipos y valores de roles se contrastaron con `database/postgresql_schema.sql`
del historial de Zentra y con el controlador actual. El indice unico soporta la busqueda de login.
No hay relaciones ni catalogos de roles/permisos requeridos por este codigo: el rol es un campo del usuario.
Los CHECK fueron comprobados en MariaDB 10.4.32. No se utilizan collations exclusivas de MySQL 8.

Antes de crear la base se consulto `information_schema`: no existian `zentra` ni
`saas_contabilidad`. El SQL no contiene DROP, TRUNCATE ni cuentas predeterminadas.
Reimportarlo conserva los datos. Si existe una tabla incompatible, no la altera automaticamente:
se debe inspeccionar y respaldar antes de adaptar sus columnas o restricciones.

## Registros historicos y datos nuevos

Se confirmo la identidad del respaldo usando su procedencia en el mismo historial Git,
el esquema y controlador de usuarios y el titulo **Acceso - Zentra** del login de esa version.
Los respaldos bajo `backup_sietelsa` pertenecen a otro proyecto y no se importaron.

Respaldo utilizado:

- Referencia: `origin/feature/admin`, commit `eb0a8cdf8d823275ccb8c6eb15438feb33534d32`.
- Ruta en Git: `backup/postgresql_daily/Saas_backup_manual_2026-07-13_19-58-46.sql`.
- Copia completa local: `C:\xampp\zentra-private\Saas_backup_manual_2026-07-13_19-58-46.postgresql.sql`.
- SHA256: `e4c7e92a0cdb7462af137ac18d96564de98625cb8c5f8ff7b531c1a96566d9b6`.
- Formato original: PostgreSQL 18.3, base `Saas`; no es importable directamente en MySQL.

**Historicos recuperados en zentra:** dos filas de `usuarios`, IDs 1 y 2 (`admin` y `user`).
Se preservaron exactamente IDs, usernames, hashes originales, roles y estados.
Sus hashes son reconocidos por PHP; sus contrasenas originales no se conocen ni se cambiaron.

**Cuenta inicial nueva:** ID 3, `zentra_admin`, rol admin, activa, con clave aleatoria generada
mediante `random_bytes` y almacenada con `password_hash(PASSWORD_DEFAULT)`.
Su acceso se probo realmente por HTTP. No habia un mecanismo de inicializacion en master;
se agrego `scripts/create-admin.php`, que no modifica cuentas existentes.

**Historicos conservados solo en el respaldo:** los campos adicionales de usuario y las otras
16 tablas de la version posterior, entre ellas 1 empresa, 6 libros, 28 facturas, planes,
suscripciones, contactos y bitacora. No se cargaron en el esquema minimo porque sus modulos
no estan presentes en master. Recuperar esa version funcional completa requiere trabajar
con su codigo posterior y adaptar su integracion PostgreSQL a MySQL por separado.

`scripts/recover-users.php` valida el SHA256, lee solamente el COPY de usuarios y usa INSERT
preparados dentro de una transaccion. Si encuentra diferencias por ID o username, revierte
la importacion sin sobrescribir registros; si ya son identicos, los conserva.

Se guardo tambien una copia MySQL con las tres cuentas resultantes en
`C:\xampp\zentra-private\zentra_after_recovery_2026-11-05.sql`.
Los archivos privados no se agregaron al repositorio ni estan disponibles por HTTP.

## Repetir la instalacion en esta PC

Desde PowerShell:

```powershell
Set-Location C:\xampp\htdocs\saas\Saas
# Solo si aun no existe la configuracion local:
if (-not (Test-Path src\config\db.local.php)) {
    Copy-Item src\config\db.local.php.example src\config\db.local.php
}
# Editar db.local.php con host, puerto, usuario y clave reales antes de continuar.
& C:\xampp\php\php.exe scripts\setup-database.php
# Opcional: recuperar exclusivamente los dos usuarios del respaldo verificado.
& C:\xampp\php\php.exe scripts\recover-users.php C:\xampp\zentra-private\Saas_backup_manual_2026-07-13_19-58-46.postgresql.sql
& C:\xampp\php\php.exe scripts\create-admin.php zentra_admin
```

No sobrescribir `db.local.php` si ya esta configurado. Los pasos ejecutados en esta PC ya
estan completos; repetir create-admin conserva la clave existente. En una instalacion nueva
el comando muestra una clave aleatoria una sola vez, que se debe guardar localmente.
Para escoger otra clave se puede definir `ZENTRA_ADMIN_PASSWORD` (12 a 72 bytes) al crear
una cuenta nueva; no se recibe una clave por argumentos ni se guarda en archivos versionados.

Alternativa phpMyAdmin: abrir `http://localhost/phpmyadmin/`, usar **Importar**, seleccionar
el archivo local `zentra.sql` y ejecutar; el archivo crea y selecciona `zentra` por si mismo.
La cuenta inicial se crea despues mediante el comando PHP. No importar el respaldo PostgreSQL
en phpMyAdmin. `.htaccess` impide descargar SQL, configuracion, Git y scripts por HTTP.

## Verificaciones ejecutadas

- Importacion real por PDO y por el cliente MySQL de XAMPP, seleccion de `zentra` y charset.
- Reimportacion del SQL, setup, recuperacion historica y create-admin: conteo y huella de las
  cinco columnas de todas las filas permanecieron identicos.
- Alta, consulta, edicion y baja SQL con usuario de prueba; defaults, username unico,
  restricciones de rol/estado y texto Unicode de cuatro bytes.
- HTTP: entrada, proteccion de ambos paneles, login correcto, campos vacios, usuario inexistente,
  clave incorrecta, cuenta inactiva, intento de inyeccion SQL y entradas enviadas como arrays.
- Claves con espacios, regeneracion de sesion, lectura del username/rol, bloqueo de login
  para usuarios ya autenticados, logout y denegacion posterior de acceso.
- Login real de `zentra_admin`; 19 recursos HTML de ambos paneles sin archivos ausentes;
  paginas de formularios y tablas accesibles.
- Configuracion, respaldo SQL, Git, scripts y pruebas devuelven HTTP 403.
- Sintaxis de los 28 PHP comprobados, `node --check` para dashboard.js y `git diff --check`.

El comando reproducible de integracion es:

```powershell
& C:\xampp\php\php.exe tests\verify.php http://localhost/saas/Saas
# Para comprobar tambien la cuenta inicial guardada localmente:
$env:ZENTRA_TEST_CREDENTIALS = 'C:\xampp\zentra-private\initial-admin.txt'
& C:\xampp\php\php.exe tests\verify.php http://localhost/saas/Saas
Remove-Item Env:\ZENTRA_TEST_CREDENTIALS
```

Las pruebas borran exclusivamente los usuarios que crean y comprueban el conteo final;
quedaron **tres usuarios y ningun registro de prueba**. El autoincremento puede tener saltos.
No se hizo revision visual en un navegador ni login con las claves historicas desconocidas.
No hay CRUD de negocio ni registro publico implementados en esta rama para probar por interfaz.

## Archivos cambiados

- `zentra.sql`: esquema reproducible, sin datos historicos ni claves.
- `.gitignore`, `.htaccess`: configuracion privada y bloqueo de descargas HTTP.
- `src/config/database.php`, `db.php`, `db.local.php.example`: conexion local a zentra.
- `src/config/db.local.php`: credenciales locales reales, ignoradas por Git.
- `src/config/session.php`, `src/controllers/AuthController.php`: rutas segun la ubicacion
  del proyecto, validacion de entradas, password sin trim y cierre completo de sesion.
- `src/partials/_sidebar.php`: enlaces del panel corregidos.
- `src/pages/samples/register.php`: aviso del estado real del registro y enlace de acceso.
- `src/index.php`, `src/assets/js/dashboard.js`: se quitaron dependencias DataTables ausentes
  de una pantalla que no tiene esa tabla; la inicializacion queda condicionada a su existencia.
- `scripts/setup-database.php`, `create-admin.php`, `recover-users.php`: herramientas CLI.
- `tests/verify.php`: integracion SQL y HTTP con limpieza de datos de prueba.
- `RECUPERACION.md`: diagnostico, procedencia, resultados y pasos operativos.
