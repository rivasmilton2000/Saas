import 'reflect-metadata';
import { Global, Injectable, Module, OnApplicationShutdown, OnModuleInit } from '@nestjs/common';
import { config } from 'dotenv';
import { resolve } from 'node:path';
import { createPool, Pool, PoolConnection, ResultSetHeader, RowDataPacket } from 'mysql2/promise';

config({ path: resolve(process.cwd(), '.env'), quiet: true });

export function mysqlOptions() {
  for (const key of ['DB_HOST', 'DB_USER', 'DB_NAME']) {
    if (!process.env[key]) throw new Error(`Falta ${key} en las variables de entorno.`);
  }
  const port = Number(process.env.DB_PORT || 3306);
  const connectionLimit = Number(process.env.DB_POOL_SIZE || 10);
  if (!Number.isInteger(port) || port < 1 || port > 65535 || !Number.isInteger(connectionLimit) || connectionLimit < 1) {
    throw new Error('DB_PORT o DB_POOL_SIZE no valido.');
  }
  return {
    host: process.env.DB_HOST!, port, user: process.env.DB_USER!,
    password: process.env.DB_PASSWORD || '', database: process.env.DB_NAME!,
    connectionLimit, waitForConnections: true, queueLimit: 100,
    charset: 'utf8mb4_unicode_ci', timezone: '-06:00', dateStrings: true,
    multipleStatements: false, decimalNumbers: false,
  };
}

/** Un solo proveedor y un solo pool por contexto Nest; nunca crear pools en los modulos. */
@Injectable()
export class MysqlService implements OnModuleInit, OnApplicationShutdown {
  private readonly pool: Pool;
  constructor() {
    this.pool = createPool(mysqlOptions());
    this.pool.on('connection', connection => { connection.query("SET time_zone = '-06:00'"); });
  }
  async onModuleInit() { await this.rows('SELECT 1 AS ok'); }
  async rows<T = RowDataPacket>(sql: string, values: unknown[] = [], connection?: PoolConnection): Promise<T[]> {
    const [rows] = await (connection || this.pool).execute(sql, values as Parameters<Pool['execute']>[1]);
    return rows as T[];
  }
  async run(sql: string, values: unknown[] = [], connection?: PoolConnection): Promise<ResultSetHeader> {
    const [result] = await (connection || this.pool).execute(sql, values as Parameters<Pool['execute']>[1]);
    return result as ResultSetHeader;
  }
  async transaction<T>(action: (connection: PoolConnection) => Promise<T>): Promise<T> {
    const connection = await this.pool.getConnection();
    try {
      await connection.beginTransaction();
      const result = await action(connection);
      await connection.commit();
      return result;
    } catch (error) { await connection.rollback(); throw error; }
    finally { connection.release(); }
  }
  async onApplicationShutdown() { await this.pool.end(); }
}

@Global()
@Module({ providers: [MysqlService], exports: [MysqlService] })
export class ConexionModule {}
