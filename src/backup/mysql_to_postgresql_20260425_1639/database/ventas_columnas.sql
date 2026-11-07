USE saas_contabilidad;

ALTER TABLE facturas
    ADD COLUMN IF NOT EXISTS nombre_cliente VARCHAR(200),
    ADD COLUMN IF NOT EXISTS nrc_cliente VARCHAR(20),
    ADD COLUMN IF NOT EXISTS numero_control_preimpreso VARCHAR(50),
    ADD COLUMN IF NOT EXISTS numero_control_interno VARCHAR(50),
    ADD COLUMN IF NOT EXISTS dia_emision DATE,
    ADD COLUMN IF NOT EXISTS del_numero INT,
    ADD COLUMN IF NOT EXISTS al_numero INT,
    ADD COLUMN IF NOT EXISTS codigo_generacion_desde VARCHAR(50),
    ADD COLUMN IF NOT EXISTS codigo_generacion_hasta VARCHAR(50),
    ADD COLUMN IF NOT EXISTS ventas_exentas DECIMAL(10,2) DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS ventas_internas_gravadas DECIMAL(10,2) DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS exportaciones DECIMAL(10,2) DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS total_ventas_diarias_propias DECIMAL(10,2) DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS ventas_cuenta_terceros DECIMAL(10,2) DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS debito_fiscal DECIMAL(10,2) DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS ventas_exentas_contribuyente DECIMAL(10,2) DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS ventas_internas_gravadas_contribuyente DECIMAL(10,2) DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS debito_fiscal_contribuyente DECIMAL(10,2) DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS ventas_totales DECIMAL(10,2) DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS nit_agente_retencion VARCHAR(20),
    ADD COLUMN IF NOT EXISTS fecha_emision_retencion DATE,
    ADD COLUMN IF NOT EXISTS tipo_documento_relacionado VARCHAR(20),
    ADD COLUMN IF NOT EXISTS serie_documento VARCHAR(50),
    ADD COLUMN IF NOT EXISTS numero_documento VARCHAR(50),
    ADD COLUMN IF NOT EXISTS monto_sujeto_retencion DECIMAL(10,2) DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS retencion_iva_1 DECIMAL(10,2) DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS dui_agente_retencion VARCHAR(20),
    ADD COLUMN IF NOT EXISTS numero_anexo VARCHAR(20);

ALTER TABLE empresas
    ADD COLUMN IF NOT EXISTS ultima_vez_usada TIMESTAMP NULL;
