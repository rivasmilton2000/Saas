CREATE DATABASE IF NOT EXISTS dte CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dte;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol ENUM('admin','user') NOT NULL DEFAULT 'user',
    estado TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS rol ENUM('admin','user') NOT NULL DEFAULT 'user' AFTER password,
    ADD COLUMN IF NOT EXISTS estado TINYINT(1) NOT NULL DEFAULT 1 AFTER rol,
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER estado;

ALTER TABLE empresas
    ADD COLUMN IF NOT EXISTS id_usuario INT NULL AFTER id,
    ADD COLUMN IF NOT EXISTS nit VARCHAR(20) NULL AFTER dui,
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

UPDATE empresas
SET id_usuario = usuario_id
WHERE id_usuario IS NULL AND usuario_id IS NOT NULL;

ALTER TABLE empresas
    MODIFY COLUMN id_usuario INT NOT NULL,
    DROP COLUMN IF EXISTS usuario_id;

ALTER TABLE empresas
    ADD COLUMN IF NOT EXISTS iniciales VARCHAR(4) NULL AFTER nombre,
    ADD COLUMN IF NOT EXISTS color_emblema VARCHAR(20) DEFAULT '#f97316' AFTER iniciales,
    ADD COLUMN IF NOT EXISTS dui VARCHAR(20) NULL AFTER color_emblema,
    ADD COLUMN IF NOT EXISTS nrc VARCHAR(20) NULL AFTER nit,
    ADD COLUMN IF NOT EXISTS tipo_legal ENUM('natural','juridica') DEFAULT 'natural' AFTER nrc,
    ADD COLUMN IF NOT EXISTS estado TINYINT(1) DEFAULT 1 AFTER tipo_legal;

ALTER TABLE empresas
    ADD INDEX IF NOT EXISTS idx_empresas_usuario (id_usuario);

ALTER TABLE empresas
    ADD CONSTRAINT fk_empresas_usuario
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id);

CREATE TABLE IF NOT EXISTS libros (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_empresa INT NOT NULL,
    id_usuario INT NOT NULL,
    tipo ENUM('compras','ventas_consumidor','ventas_contribuyente','retencion_iva') NOT NULL,
    mes TINYINT NOT NULL CHECK (mes BETWEEN 1 AND 12),
    anio YEAR NOT NULL,
    nombre VARCHAR(100) GENERATED ALWAYS AS (CONCAT(tipo, '-', anio, '-', mes)) STORED,
    estado TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_libro (id_empresa, tipo, mes, anio),
    KEY idx_libros_usuario (id_usuario),
    CONSTRAINT fk_libros_empresa FOREIGN KEY (id_empresa) REFERENCES empresas(id) ON DELETE CASCADE,
    CONSTRAINT fk_libros_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id)
);

CREATE TABLE IF NOT EXISTS facturas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_libro INT NOT NULL,
    id_usuario INT NOT NULL,
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
    compras_exentas DECIMAL(10,2) DEFAULT 0.00,
    compras_gravadas DECIMAL(10,2) DEFAULT 0.00,
    credito_fiscal DECIMAL(10,2) DEFAULT 0.00,
    impuestos_calculados DECIMAL(10,2) DEFAULT 0.00,
    fovial_otros DECIMAL(10,2) DEFAULT 0.00,
    total_compras DECIMAL(10,2) DEFAULT 0.00,
    iva_percibido DECIMAL(10,2) DEFAULT 0.00,
    iva_retenido DECIMAL(10,2) DEFAULT 0.00,
    numero_control_completo VARCHAR(100) NULL,
    import_batch_id VARCHAR(64) NULL,
    import_index INT NULL,
    source_filename VARCHAR(255) NULL,
    raw_json_hash CHAR(64) NULL,
    posible_duplicado TINYINT(1) NOT NULL DEFAULT 0,
    fuera_de_periodo TINYINT(1) NOT NULL DEFAULT 0,
    motivo_no_contabilizado VARCHAR(255) NULL,
    raw_json LONGTEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_facturas_libro (id_libro),
    KEY idx_factura_libro_codigo (id_libro, codigo_generacion),
    KEY idx_facturas_usuario (id_usuario),
    CONSTRAINT fk_facturas_libro FOREIGN KEY (id_libro) REFERENCES libros(id) ON DELETE CASCADE,
    CONSTRAINT fk_facturas_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id)
);

CREATE TABLE IF NOT EXISTS facturas_disponibles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_usuario INT NOT NULL UNIQUE,
    total INT NOT NULL DEFAULT 50,
    consumidas INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_facturas_disponibles_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id)
);
