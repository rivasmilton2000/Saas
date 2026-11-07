-- PostgreSQL schema for Saas Contabilidad
SET client_encoding = 'UTF8';
SET TIME ZONE 'America/El_Salvador';

CREATE OR REPLACE FUNCTION set_updated_at()
RETURNS trigger AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TABLE IF NOT EXISTS usuarios (
    id SERIAL PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    nombre_completo VARCHAR(150) NULL,
    foto_perfil VARCHAR(255) NULL,
    password VARCHAR(255) NOT NULL,
    rol VARCHAR(10) NOT NULL DEFAULT 'user',
    estado BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT usuarios_rol_check CHECK (rol IN ('admin', 'user'))
);

CREATE TABLE IF NOT EXISTS empresas (
    id SERIAL PRIMARY KEY,
    id_usuario INTEGER NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    iniciales VARCHAR(4) NULL,
    color_emblema VARCHAR(20) DEFAULT '#f97316',
    dui VARCHAR(20) NULL,
    nit VARCHAR(20) NULL,
    nrc VARCHAR(20) NULL,
    tipo_legal VARCHAR(15) DEFAULT 'natural',
    estado BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultima_vez_usada TIMESTAMP NULL,
    CONSTRAINT empresas_tipo_legal_check CHECK (tipo_legal IN ('natural', 'juridica')),
    CONSTRAINT fk_empresas_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_empresas_usuario ON empresas (id_usuario);

CREATE TABLE IF NOT EXISTS libros (
    id SERIAL PRIMARY KEY,
    id_empresa INTEGER NOT NULL,
    id_usuario INTEGER NOT NULL,
    tipo VARCHAR(30) NOT NULL,
    mes SMALLINT NOT NULL CHECK (mes BETWEEN 1 AND 12),
    anio INTEGER NOT NULL CHECK (anio BETWEEN 2000 AND 9999),
    nombre VARCHAR(100) GENERATED ALWAYS AS ((tipo || '-' || anio::text || '-' || mes::text)) STORED,
    estado BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT libros_tipo_check CHECK (tipo IN ('compras', 'ventas_consumidor', 'ventas_contribuyente', 'retencion_iva')),
    CONSTRAINT unique_libro UNIQUE (id_empresa, tipo, mes, anio),
    CONSTRAINT fk_libros_empresa
        FOREIGN KEY (id_empresa) REFERENCES empresas(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_libros_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_libros_usuario ON libros (id_usuario);

CREATE TABLE IF NOT EXISTS facturas (
    id SERIAL PRIMARY KEY,
    id_libro INTEGER NOT NULL,
    id_usuario INTEGER NOT NULL,
    codigo_generacion VARCHAR(50) NOT NULL,
    sello_recepcion TEXT NULL,
    numero_control VARCHAR(50) NULL,
    tipo_dte VARCHAR(10) NULL,
    fecha DATE NOT NULL,
    nrc VARCHAR(20) NULL,
    nit VARCHAR(20) NULL,
    nombre_proveedor VARCHAR(200) NULL,
    ventas_internas DECIMAL(10,2) DEFAULT 0.00,
    ventas_importacion DECIMAL(10,2) DEFAULT 0.00,
    ventas_internas_exentas DECIMAL(10,2) DEFAULT 0.00,
    ventas_importacion_exentas DECIMAL(10,2) DEFAULT 0.00,
    credito_fiscal DECIMAL(10,2) DEFAULT 0.00,
    total_compras DECIMAL(10,2) DEFAULT 0.00,
    iva_percibido DECIMAL(10,2) DEFAULT 0.00,
    iva_retenido DECIMAL(10,2) DEFAULT 0.00,
    numero_control_completo VARCHAR(100) NULL,
    raw_json TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    nombre_cliente VARCHAR(200) NULL,
    nrc_cliente VARCHAR(20) NULL,
    numero_control_preimpreso VARCHAR(50) NULL,
    numero_control_interno VARCHAR(50) NULL,
    dia_emision DATE NULL,
    del_numero INTEGER NULL,
    al_numero INTEGER NULL,
    codigo_generacion_desde VARCHAR(50) NULL,
    codigo_generacion_hasta VARCHAR(50) NULL,
    ventas_exentas DECIMAL(10,2) DEFAULT 0.00,
    ventas_internas_gravadas DECIMAL(10,2) DEFAULT 0.00,
    exportaciones DECIMAL(10,2) DEFAULT 0.00,
    total_ventas_diarias_propias DECIMAL(10,2) DEFAULT 0.00,
    ventas_cuenta_terceros DECIMAL(10,2) DEFAULT 0.00,
    debito_fiscal DECIMAL(10,2) DEFAULT 0.00,
    ventas_exentas_contribuyente DECIMAL(10,2) DEFAULT 0.00,
    ventas_internas_gravadas_contribuyente DECIMAL(10,2) DEFAULT 0.00,
    debito_fiscal_contribuyente DECIMAL(10,2) DEFAULT 0.00,
    ventas_totales DECIMAL(10,2) DEFAULT 0.00,
    nit_agente_retencion VARCHAR(20) NULL,
    fecha_emision_retencion DATE NULL,
    tipo_documento_relacionado VARCHAR(20) NULL,
    serie_documento VARCHAR(50) NULL,
    numero_documento VARCHAR(50) NULL,
    monto_sujeto_retencion DECIMAL(10,2) DEFAULT 0.00,
    retencion_iva_1 DECIMAL(10,2) DEFAULT 0.00,
    dui_agente_retencion VARCHAR(20) NULL,
    numero_anexo VARCHAR(20) NULL,
    CONSTRAINT unique_factura_libro UNIQUE (id_libro, codigo_generacion),
    CONSTRAINT fk_facturas_libro
        FOREIGN KEY (id_libro) REFERENCES libros(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_facturas_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_facturas_usuario ON facturas (id_usuario);

CREATE TABLE IF NOT EXISTS facturas_disponibles (
    id SERIAL PRIMARY KEY,
    id_usuario INTEGER NOT NULL UNIQUE,
    total INTEGER NOT NULL DEFAULT 50,
    consumidas INTEGER NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_facturas_disponibles_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE ON UPDATE CASCADE
);
DROP TRIGGER IF EXISTS trg_facturas_disponibles_updated_at ON facturas_disponibles;
CREATE TRIGGER trg_facturas_disponibles_updated_at
BEFORE UPDATE ON facturas_disponibles
FOR EACH ROW
EXECUTE FUNCTION set_updated_at();

CREATE TABLE IF NOT EXISTS centro_mando_config (
    id SERIAL PRIMARY KEY,
    id_empresa INTEGER NOT NULL,
    id_usuario INTEGER NOT NULL,
    item_key VARCHAR(80) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT unique_empresa_item UNIQUE (id_empresa, item_key),
    CONSTRAINT fk_centro_mando_config_empresa
        FOREIGN KEY (id_empresa) REFERENCES empresas(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_centro_mando_config_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_centro_mando_config_usuario ON centro_mando_config (id_usuario);

CREATE TABLE IF NOT EXISTS centro_mando_avance (
    id SERIAL PRIMARY KEY,
    id_empresa INTEGER NOT NULL,
    id_usuario INTEGER NOT NULL,
    mes SMALLINT NOT NULL CHECK (mes BETWEEN 1 AND 12),
    anio INTEGER NOT NULL CHECK (anio BETWEEN 2000 AND 9999),
    item_key VARCHAR(80) NOT NULL,
    completado BOOLEAN NOT NULL DEFAULT FALSE,
    completed_at TIMESTAMP NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT unique_empresa_periodo_item UNIQUE (id_empresa, mes, anio, item_key),
    CONSTRAINT fk_centro_mando_avance_empresa
        FOREIGN KEY (id_empresa) REFERENCES empresas(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_centro_mando_avance_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_centro_mando_avance_usuario ON centro_mando_avance (id_usuario);
DROP TRIGGER IF EXISTS trg_centro_mando_avance_updated_at ON centro_mando_avance;
CREATE TRIGGER trg_centro_mando_avance_updated_at
BEFORE UPDATE ON centro_mando_avance
FOR EACH ROW
EXECUTE FUNCTION set_updated_at();

CREATE TABLE IF NOT EXISTS centro_mando_notas (
    id SERIAL PRIMARY KEY,
    id_empresa INTEGER NOT NULL,
    id_usuario INTEGER NOT NULL,
    mes SMALLINT NOT NULL CHECK (mes BETWEEN 1 AND 12),
    anio INTEGER NOT NULL CHECK (anio BETWEEN 2000 AND 9999),
    nota TEXT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT unique_empresa_periodo_nota UNIQUE (id_empresa, mes, anio),
    CONSTRAINT fk_centro_mando_notas_empresa
        FOREIGN KEY (id_empresa) REFERENCES empresas(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_centro_mando_notas_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_centro_mando_notas_usuario ON centro_mando_notas (id_usuario);
DROP TRIGGER IF EXISTS trg_centro_mando_notas_updated_at ON centro_mando_notas;
CREATE TRIGGER trg_centro_mando_notas_updated_at
BEFORE UPDATE ON centro_mando_notas
FOR EACH ROW
EXECUTE FUNCTION set_updated_at();

CREATE TABLE IF NOT EXISTS centro_mando_preferencias (
    id_usuario INTEGER PRIMARY KEY,
    ocultar_bienvenida BOOLEAN NOT NULL DEFAULT FALSE,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_centro_mando_preferencias_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE ON UPDATE CASCADE
);
DROP TRIGGER IF EXISTS trg_centro_mando_preferencias_updated_at ON centro_mando_preferencias;
CREATE TRIGGER trg_centro_mando_preferencias_updated_at
BEFORE UPDATE ON centro_mando_preferencias
FOR EACH ROW
EXECUTE FUNCTION set_updated_at();

CREATE TABLE IF NOT EXISTS bitacora_movimientos (
    id SERIAL PRIMARY KEY,
    id_usuario INTEGER NOT NULL,
    username_snapshot VARCHAR(100) NOT NULL,
    rol_snapshot VARCHAR(20) NULL,
    modulo VARCHAR(80) NOT NULL,
    accion VARCHAR(120) NOT NULL,
    descripcion VARCHAR(255) NOT NULL,
    entidad_tipo VARCHAR(80) NULL,
    entidad_id INTEGER NULL,
    contexto_json TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_bitacora_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_bitacora_usuario ON bitacora_movimientos (id_usuario);
CREATE INDEX IF NOT EXISTS idx_bitacora_fecha ON bitacora_movimientos (created_at);
CREATE INDEX IF NOT EXISTS idx_bitacora_modulo ON bitacora_movimientos (modulo);
