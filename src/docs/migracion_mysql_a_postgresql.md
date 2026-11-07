# Migracion MySQL a PostgreSQL

## Base objetivo
- Motor: PostgreSQL 18+
- Host: `localhost`
- Puerto: `5432`
- Base objetivo: `Saas`
- Usuario: `postgres`
- Clave local usada en esta migracion: `1581`

## Archivos principales
- Esquema PostgreSQL: `database/postgresql_schema.sql`
- Seed PostgreSQL: `database/postgresql_seed.sql`
- Exportador/importador: `database/export_mysql_to_postgresql.php`
- Conexion del proyecto: `src/config/db.php`
- Respaldo previo: `backup/mysql_to_postgresql_20260425_1639`

## Cambio de entorno en XAMPP
- Se habilitaron `pdo_pgsql` y `pgsql` en `C:\xampp\php\php.ini`.
- Si Apache ya estaba encendido, reinicialo para que el modulo web de PHP cargue esas extensiones.
- Verificacion rapida por CLI:
  - `C:\xampp\php\php.exe -m | findstr pgsql`

## Crear la base en pgAdmin
1. Abre pgAdmin y conecta al servidor local `PostgreSQL 18`.
2. Click derecho en `Databases` -> `Create` -> `Database...`.
3. Usa el nombre `Saas`.
4. Guarda.

## Ejecutar la migracion automatica
1. Desde PowerShell en la raiz del proyecto:
   - `C:\xampp\php\php.exe database\export_mysql_to_postgresql.php --apply`
2. Ese script hace tres cosas:
   - Lee la base MySQL `saas_contabilidad`.
   - Genera/actualiza `database/postgresql_seed.sql`.
   - Crea `Saas` si no existe y aplica `postgresql_schema.sql` + `postgresql_seed.sql`.

## Ejecutar manualmente en pgAdmin
1. Crea la base `Saas`.
2. Abre Query Tool sobre esa base.
3. Ejecuta `database/postgresql_schema.sql`.
4. Ejecuta `database/postgresql_seed.sql`.

## Conexion del proyecto
- El proyecto ya quedo apuntando a PostgreSQL en `src/config/db.php`.
- DSN configurado:

```php
$dsn = "pgsql:host=localhost;port=5432;dbname=Saas";
$pdo = new PDO($dsn, "postgres", "1581", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
```

## Modulos revisados y adaptados
- Login y usuarios
- Empresas
- Libros
- Facturas
- Cuota de facturas
- Centro de mando
- Bitacora

## Compatibilidades MySQL corregidas
- `mysql:` -> `pgsql:` en PDO
- `AUTO_INCREMENT` -> `SERIAL`
- `ENUM` -> `VARCHAR` + `CHECK`
- `TINYINT(1)` -> `BOOLEAN`
- `YEAR` -> `INTEGER`
- `DATETIME` -> `TIMESTAMP`
- `NOW()` -> `CURRENT_TIMESTAMP`
- `ON DUPLICATE KEY UPDATE` -> `ON CONFLICT ... DO UPDATE`
- `FIELD(...)` -> `CASE ... END`
- `SHOW COLUMNS` -> `information_schema.columns`
- `LONGTEXT` -> `TEXT`
- `ON UPDATE CURRENT_TIMESTAMP` -> trigger `set_updated_at()`
- `lastInsertId()` -> `INSERT ... RETURNING id`
- Clausulas `AFTER ...` de `ALTER TABLE` eliminadas

## Validaciones sugeridas despues de reiniciar Apache
1. Abre `http://localhost/Saas/src/pages/samples/login.php`.
2. Prueba login con un usuario existente.
3. Revisa dashboard y empresa activa.
4. Lista empresas.
5. Abre libros de compras, ventas consumidor, ventas contribuyente y retencion.
6. Verifica lectura de facturas por libro.
7. Crea una empresa nueva.
8. Crea un libro nuevo.
9. Edita/guarda checklist del centro de mando.
10. Verifica bitacora.

## Archivos modificados
- `src/config/db.php`
- `src/controllers/AuthController.php`
- `src/models/UsuarioModel.php`
- `src/models/EmpresaModel.php`
- `src/models/LibroModel.php`
- `src/models/FacturaModel.php`
- `src/models/CentroMandoModel.php`
- `src/models/BitacoraModel.php`
- `testdb.php`
- `C:\xampp\php\php.ini`

## Archivos creados
- `database/postgresql_schema.sql`
- `database/postgresql_seed.sql` (generado por el script)
- `database/export_mysql_to_postgresql.php`
- `docs/migracion_mysql_a_postgresql.md`

## Notas
- El esquema PostgreSQL respeta nombres de tablas y columnas para no romper el frontend ni los controladores actuales.
- El seed se genera desde la base MySQL real, no solo desde los `.sql` del repositorio.
- Si cambian los datos en MySQL y quieres regenerar el seed, vuelve a ejecutar:
  - `C:\xampp\php\php.exe database\export_mysql_to_postgresql.php`
