<?php

class CentroMandoModel {

    private static bool $schemaChecked = false;

    public static function getConfigByUsuario(PDO $pdo, int $idUsuario): array {
        self::ensureSchema($pdo);

        $stmt = $pdo->prepare(
            "SELECT id_empresa, item_key
             FROM centro_mando_config
             WHERE id_usuario = ?"
        );
        $stmt->execute([$idUsuario]);

        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $empresaId = (int) ($row['id_empresa'] ?? 0);
            $itemKey   = (string) ($row['item_key'] ?? '');

            if ($empresaId <= 0 || $itemKey === '') {
                continue;
            }

            if (!isset($map[$empresaId])) {
                $map[$empresaId] = [];
            }

            $map[$empresaId][$itemKey] = true;
        }

        return $map;
    }

    public static function replaceConfig(PDO $pdo, int $idEmpresa, int $idUsuario, array $itemKeys): void {
        self::ensureSchema($pdo);

        $pdo->beginTransaction();

        try {
            $delete = $pdo->prepare(
                "DELETE FROM centro_mando_config
                 WHERE id_empresa = ? AND id_usuario = ?"
            );
            $delete->execute([$idEmpresa, $idUsuario]);

            if ($itemKeys !== []) {
                $insert = $pdo->prepare(
                    "INSERT INTO centro_mando_config (id_empresa, id_usuario, item_key)
                     VALUES (?, ?, ?)"
                );

                foreach ($itemKeys as $itemKey) {
                    $insert->execute([$idEmpresa, $idUsuario, $itemKey]);
                }
            }

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public static function getProgressByUsuario(PDO $pdo, int $idUsuario, int $mes, int $anio): array {
        self::ensureSchema($pdo);

        $stmt = $pdo->prepare(
            "SELECT id_empresa,
                    item_key,
                    CASE WHEN completado THEN 1 ELSE 0 END AS completado
             FROM centro_mando_avance
             WHERE id_usuario = ? AND mes = ? AND anio = ?"
        );
        $stmt->execute([$idUsuario, $mes, $anio]);

        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $empresaId  = (int) ($row['id_empresa'] ?? 0);
            $itemKey    = (string) ($row['item_key'] ?? '');
            $completado = (int) ($row['completado'] ?? 0) === 1;

            if ($empresaId <= 0 || $itemKey === '') {
                continue;
            }

            if (!isset($map[$empresaId])) {
                $map[$empresaId] = [];
            }

            $map[$empresaId][$itemKey] = $completado;
        }

        return $map;
    }

    public static function setProgress(
        PDO $pdo,
        int $idEmpresa,
        int $idUsuario,
        int $mes,
        int $anio,
        string $itemKey,
        bool $completado
    ): void {
        self::ensureSchema($pdo);

        $stmt = $pdo->prepare(
            "INSERT INTO centro_mando_avance (
                id_empresa,
                id_usuario,
                mes,
                anio,
                item_key,
                completado,
                completed_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                id_usuario = VALUES(id_usuario),
                completado = VALUES(completado),
                completed_at = VALUES(completed_at),
                updated_at = CURRENT_TIMESTAMP"
        );
        $stmt->execute([
            $idEmpresa,
            $idUsuario,
            $mes,
            $anio,
            $itemKey,
            dbBoolParam($completado),
            $completado ? date('Y-m-d H:i:s') : null,
        ]);
    }

    public static function getNotesByUsuario(PDO $pdo, int $idUsuario, int $mes, int $anio): array {
        self::ensureSchema($pdo);

        $stmt = $pdo->prepare(
            "SELECT id_empresa, nota
             FROM centro_mando_notas
             WHERE id_usuario = ? AND mes = ? AND anio = ?"
        );
        $stmt->execute([$idUsuario, $mes, $anio]);

        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $empresaId = (int) ($row['id_empresa'] ?? 0);
            if ($empresaId <= 0) {
                continue;
            }

            $map[$empresaId] = trim((string) ($row['nota'] ?? ''));
        }

        return $map;
    }

    public static function saveNote(
        PDO $pdo,
        int $idEmpresa,
        int $idUsuario,
        int $mes,
        int $anio,
        string $nota
    ): void {
        self::ensureSchema($pdo);

        $nota = trim($nota);
        if ($nota === '') {
            $stmt = $pdo->prepare(
                "DELETE FROM centro_mando_notas
                 WHERE id_empresa = ? AND id_usuario = ? AND mes = ? AND anio = ?"
            );
            $stmt->execute([$idEmpresa, $idUsuario, $mes, $anio]);
            return;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO centro_mando_notas (
                id_empresa,
                id_usuario,
                mes,
                anio,
                nota
            ) VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                id_usuario = VALUES(id_usuario),
                nota = VALUES(nota),
                updated_at = CURRENT_TIMESTAMP"
        );
        $stmt->execute([$idEmpresa, $idUsuario, $mes, $anio, $nota]);
    }

    public static function isWelcomeHidden(PDO $pdo, int $idUsuario): bool {
        self::ensureSchema($pdo);

        $stmt = $pdo->prepare(
            "SELECT CASE WHEN ocultar_bienvenida THEN 1 ELSE 0 END AS ocultar_bienvenida
             FROM centro_mando_preferencias
             WHERE id_usuario = ?
             LIMIT 1"
        );
        $stmt->execute([$idUsuario]);

        $row = $stmt->fetch();
        return $row ? ((int) ($row['ocultar_bienvenida'] ?? 0) === 1) : false;
    }

    public static function setWelcomeHidden(PDO $pdo, int $idUsuario, bool $ocultar): void {
        self::ensureSchema($pdo);

        $stmt = $pdo->prepare(
            "INSERT INTO centro_mando_preferencias (id_usuario, ocultar_bienvenida)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE
                ocultar_bienvenida = VALUES(ocultar_bienvenida),
                updated_at = CURRENT_TIMESTAMP"
        );
        $stmt->execute([$idUsuario, dbBoolParam($ocultar)]);
    }

    private static function ensureSchema(PDO $pdo): void {
        if (dbDriverName($pdo) === 'mysql') {
            dbRequireTable($pdo, 'centro_mando_config');
            self::$schemaChecked = true;
            return;
        }
        if (self::$schemaChecked) {
            return;
        }

        self::$schemaChecked = true;

        try {
            $pdo->exec(
                "CREATE OR REPLACE FUNCTION set_updated_at()
                 RETURNS trigger AS $$
                 BEGIN
                     NEW.updated_at = CURRENT_TIMESTAMP;
                     RETURN NEW;
                 END;
                 $$ LANGUAGE plpgsql"
            );

            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS centro_mando_config (
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
                )"
            );
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_centro_mando_config_usuario ON centro_mando_config (id_usuario)");

            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS centro_mando_avance (
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
                )"
            );
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_centro_mando_avance_usuario ON centro_mando_avance (id_usuario)");
            $pdo->exec("DROP TRIGGER IF EXISTS trg_centro_mando_avance_updated_at ON centro_mando_avance");
            $pdo->exec(
                "CREATE TRIGGER trg_centro_mando_avance_updated_at
                 BEFORE UPDATE ON centro_mando_avance
                 FOR EACH ROW
                 EXECUTE FUNCTION set_updated_at()"
            );

            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS centro_mando_notas (
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
                )"
            );
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_centro_mando_notas_usuario ON centro_mando_notas (id_usuario)");
            $pdo->exec("DROP TRIGGER IF EXISTS trg_centro_mando_notas_updated_at ON centro_mando_notas");
            $pdo->exec(
                "CREATE TRIGGER trg_centro_mando_notas_updated_at
                 BEFORE UPDATE ON centro_mando_notas
                 FOR EACH ROW
                 EXECUTE FUNCTION set_updated_at()"
            );

            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS centro_mando_preferencias (
                    id_usuario INTEGER PRIMARY KEY,
                    ocultar_bienvenida BOOLEAN NOT NULL DEFAULT FALSE,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    CONSTRAINT fk_centro_mando_preferencias_usuario
                        FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE ON UPDATE CASCADE
                )"
            );
            $pdo->exec("DROP TRIGGER IF EXISTS trg_centro_mando_preferencias_updated_at ON centro_mando_preferencias");
            $pdo->exec(
                "CREATE TRIGGER trg_centro_mando_preferencias_updated_at
                 BEFORE UPDATE ON centro_mando_preferencias
                 FOR EACH ROW
                 EXECUTE FUNCTION set_updated_at()"
            );
        } catch (Throwable $exception) {
            // No bloqueamos la app si la migracion automatica falla.
        }
    }
}
