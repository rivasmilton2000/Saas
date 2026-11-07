import 'reflect-metadata';
import { NestFactory } from '@nestjs/core';
import { createHash } from 'node:crypto';
import { readdirSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { ConexionModule, MysqlService } from './conexiones';

export async function migrate(db: MysqlService) {
  // DDL de MySQL tiene commit implicito: archivos pequenos, idempotentes y checksum estricto.
  await db.transaction(async connection => {
    const [lock] = await db.rows<{ locked: number }>("SELECT GET_LOCK(CONCAT(DATABASE(), ':zentra:migrate'), 15) AS locked", [], connection);
    if (lock.locked !== 1) throw new Error('Otra instancia esta ejecutando migraciones.');
    try {
      await db.run(`CREATE TABLE IF NOT EXISTS zentra_migrations (
        name varchar(191) PRIMARY KEY, checksum char(64) NOT NULL,
        applied_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4`, [], connection);
      for (const name of readdirSync(resolve('conexion/migrations')).filter(name => name.endsWith('.sql')).sort()) {
        const sql = readFileSync(resolve('conexion/migrations', name), 'utf8');
        const checksum = createHash('sha256').update(sql).digest('hex');
        const [existing] = await db.rows<{ checksum: string }>('SELECT checksum FROM zentra_migrations WHERE name=?', [name], connection);
        if (existing) {
          if (existing.checksum !== checksum) throw new Error(`Migracion modificada despues de aplicarse: ${name}`);
          continue;
        }
        try {
          for (const statement of sql.replace(/^--.*$/gm, '').split(';').map(s => s.trim()).filter(Boolean)) {
            await db.run(statement, [], connection);
          }
        } finally { await db.run('SET FOREIGN_KEY_CHECKS=1', [], connection); }
        await db.run('INSERT INTO zentra_migrations(name,checksum) VALUES (?,?)', [name, checksum], connection);
        console.log(`Aplicada ${name}`);
      }
      const catalog = JSON.parse(readFileSync(resolve('conexion/plan-catalog.json'), 'utf8')) as Record<string, any>[];
      for (const plan of catalog) {
        const [existing] = await db.rows('SELECT id_plan FROM planes WHERE slug=?', [plan.slug], connection);
        if (existing) continue; // Nunca restablecer precios o caracteristicas editados por el usuario.
        const keys = ['slug','nombre','descripcion','precio','moneda','periodo','billing_interval','is_free','trial_days','destacado','personalizado','activo','orden','limite_empresas','limite_usuarios','limite_documentos'];
        const result = await db.run(`INSERT INTO planes (${keys.join(',')}) VALUES (${keys.map(() => '?').join(',')})`, keys.map(k => plan[k] ?? null), connection);
        for (const [index, feature] of plan.caracteristicas.entries()) {
          await db.run('INSERT INTO planes_caracteristicas (id_plan,caracteristica,incluido,orden) VALUES (?,?,?,?)', [result.insertId, feature.texto, feature.incluido, index + 1], connection);
        }
      }
    } finally { await db.rows("SELECT RELEASE_LOCK(CONCAT(DATABASE(), ':zentra:migrate'))", [], connection); }
  });
}

if (require.main === module) {
  (async () => {
    const app = await NestFactory.createApplicationContext(ConexionModule, { logger: ['error', 'warn'] });
    try { await migrate(app.get(MysqlService)); }
    finally { await app.close(); }
  })().catch(error => { console.error(error.message); process.exitCode = 1; });
}
