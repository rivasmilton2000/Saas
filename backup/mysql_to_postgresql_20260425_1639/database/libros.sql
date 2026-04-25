USE saas_contabilidad;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol ENUM('admin','user') NOT NULL DEFAULT 'user',
    estado TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS empresas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_usuario INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    iniciales VARCHAR(4) NULL,
    color_emblema VARCHAR(20) DEFAULT '#f97316',
    dui VARCHAR(20) NULL,
    nit VARCHAR(20) NULL,
    nrc VARCHAR(20) NULL,
    tipo_legal ENUM('natural','juridica') DEFAULT 'natural',
    estado TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_empresas_usuario_base FOREIGN KEY (id_usuario) REFERENCES usuarios(id)
);

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
    CONSTRAINT fk_libros_empresa_base FOREIGN KEY (id_empresa) REFERENCES empresas(id) ON DELETE CASCADE,
    CONSTRAINT fk_libros_usuario_base FOREIGN KEY (id_usuario) REFERENCES usuarios(id)
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
    credito_fiscal DECIMAL(10,2) DEFAULT 0.00,
    total_compras DECIMAL(10,2) DEFAULT 0.00,
    iva_percibido DECIMAL(10,2) DEFAULT 0.00,
    iva_retenido DECIMAL(10,2) DEFAULT 0.00,
    numero_control_completo VARCHAR(100) NULL,
    raw_json LONGTEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_factura_libro (id_libro, codigo_generacion),
    CONSTRAINT fk_facturas_libro_base FOREIGN KEY (id_libro) REFERENCES libros(id) ON DELETE CASCADE,
    CONSTRAINT fk_facturas_usuario_base FOREIGN KEY (id_usuario) REFERENCES usuarios(id)
);

CREATE TABLE IF NOT EXISTS facturas_disponibles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_usuario INT NOT NULL UNIQUE,
    total INT NOT NULL DEFAULT 50,
    consumidas INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_facturas_disponibles_usuario_base FOREIGN KEY (id_usuario) REFERENCES usuarios(id)
);

INSERT IGNORE INTO facturas_disponibles (id_usuario, total, consumidas)
SELECT id, 50, 0
FROM usuarios;
