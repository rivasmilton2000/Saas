import session from 'express-session';
import { MysqlService } from './conexiones';

declare module 'express-session' {
  interface SessionData { userId?: number; csrf?: string; googleNonce?: string; }
}

/** Las sesiones usan el mismo pool de negocio, tambien al reiniciar Nest. */
export class MysqlSessionStore extends session.Store {
  constructor(private readonly db: MysqlService) { super(); }
  get(sid: string, callback: (error: unknown, value?: session.SessionData | null) => void) {
    this.db.rows<{data: string}>('SELECT data FROM zentra_sessions WHERE sid=? AND expires_at > CURRENT_TIMESTAMP', [sid])
      .then(rows => callback(null, rows[0] ? JSON.parse(rows[0].data) : null)).catch(callback);
  }
  set(sid: string, value: session.SessionData, callback: (error?: unknown) => void = () => {}) {
    const expires = value.cookie.expires ? new Date(value.cookie.expires) : new Date(Date.now() + 8 * 3600_000);
    this.db.run('INSERT INTO zentra_sessions(sid,data,expires_at) VALUES (?,?,?) ON DUPLICATE KEY UPDATE data=VALUES(data),expires_at=VALUES(expires_at)', [sid, JSON.stringify(value), expires])
      .then(() => callback()).catch(callback);
  }
  destroy(sid: string, callback: (error?: unknown) => void = () => {}) {
    this.db.run('DELETE FROM zentra_sessions WHERE sid=?', [sid]).then(() => callback()).catch(callback);
  }
  touch(sid: string, value: session.SessionData, callback: (error?: unknown) => void = () => {}) { this.set(sid, value, callback); }
}
