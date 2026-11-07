-- Diario y catalogo por empresa. No altera ni elimina tablas o filas existentes.
CREATE TABLE IF NOT EXISTS contabilidad_cuentas (
  id int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_empresa int unsigned NOT NULL,
  codigo varchar(20) NOT NULL,
  nombre varchar(120) NOT NULL,
  tipo varchar(15) NOT NULL,
  activo tinyint(1) NOT NULL DEFAULT 1,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cuenta_codigo (id_empresa,codigo),
  UNIQUE KEY uq_cuenta_empresa_id (id_empresa,id),
  CONSTRAINT fk_cuenta_empresa FOREIGN KEY (id_empresa) REFERENCES empresas(id),
  CONSTRAINT ck_cuenta_tipo CHECK (tipo IN ('activo','pasivo','patrimonio','ingreso','gasto')),
  CONSTRAINT ck_cuenta_activo CHECK (activo IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS contabilidad_asientos (
  id int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_empresa int unsigned NOT NULL,
  id_usuario int unsigned NOT NULL,
  fecha date NOT NULL,
  descripcion varchar(255) NOT NULL,
  referencia varchar(80) DEFAULT NULL,
  estado varchar(12) NOT NULL DEFAULT 'publicado',
  version int unsigned NOT NULL DEFAULT 1,
  motivo_anulacion varchar(500) DEFAULT NULL,
  anulado_at datetime DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_asiento_empresa_id (id_empresa,id),
  KEY idx_asiento_periodo (id_empresa,fecha,estado),
  CONSTRAINT fk_asiento_empresa FOREIGN KEY (id_empresa) REFERENCES empresas(id),
  CONSTRAINT fk_asiento_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id),
  CONSTRAINT ck_asiento_estado CHECK (estado IN ('publicado','anulado'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS contabilidad_partidas (
  id int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_empresa int unsigned NOT NULL,
  id_asiento int unsigned NOT NULL,
  id_cuenta int unsigned NOT NULL,
  detalle varchar(255) DEFAULT NULL,
  debe decimal(15,2) NOT NULL DEFAULT 0,
  haber decimal(15,2) NOT NULL DEFAULT 0,
  CONSTRAINT fk_partida_asiento FOREIGN KEY (id_empresa,id_asiento) REFERENCES contabilidad_asientos(id_empresa,id),
  CONSTRAINT fk_partida_cuenta FOREIGN KEY (id_empresa,id_cuenta) REFERENCES contabilidad_cuentas(id_empresa,id),
  CONSTRAINT ck_partida_importes CHECK ((debe>0 AND haber=0) OR (haber>0 AND debe=0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS zentra_demo_registros (
  clave varchar(100) NOT NULL PRIMARY KEY,
  tabla varchar(40) NOT NULL,
  registro_id int unsigned NOT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
