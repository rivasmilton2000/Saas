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
            "SELECT id_empresa, item_key, completado
             FROM centro_mando_avance
             WHERE id_usuario = ? AND mes = ? AND anio = ?"
        );
        $stmt->execute([$idUsuario, $mes, $anio]);

        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $empresaId   = (int) ($row['id_empresa'] ?? 0);
            $itemKey     = (string) ($row['item_key'] ?? '');
            $completado  = (int) ($row['completado'] ?? 0) === 1;

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
            $completado ? 1 : 0,
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
                nota = VALUES(nota),
                updated_at = CURRENT_TIMESTAMP"
        );
        $stmt->execute([$idEmpresa, $idUsuario, $mes, $anio, $nota]);
    }

    public static function isWelcomeHidden(PDO $pdo, int $idUsuario): bool {
        self::ensureSchema($pdo);

        $stmt = $pdo->prepare(
            "SELECT ocultar_bienvenida
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
        $stmt->execute([$idUsuario, $ocultar ? 1 : 0]);
    }

    private static function ensureSchema(PDO $pdo): void {
        if (self::$schemaChecked) {
            return;
        }

        self::$schemaChecked = true;

        try {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS centro_mando_config (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    id_empresa INT NOT NULL,
                    id_usuario INT NOT NULL,
                    item_key VARCHAR(80) NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_empresa_item (id_empresa, item_key),
                    KEY idx_centro_mando_config_usuario (id_usuario),
                    CONSTRAINT fk_centro_mando_config_empresa
                        FOREIGN KEY (id_empresa) REFERENCES empresas(id) ON DELETE CASCADE,
                    CONSTRAINT fk_centro_mando_config_usuario
                        FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
                )"
            );

            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS centro_mando_avance (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    id_empresa INT NOT NULL,
                    id_usuario INT NOT NULL,
                    mes TINYINT NOT NULL,
                    anio YEAR NOT NULL,
                    item_key VARCHAR(80) NOT NULL,
                    completado TINYINT(1) NOT NULL DEFAULT 0,
                    completed_at DATETIME NULL,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_empresa_periodo_item (id_empresa, mes, anio, item_key),
                    KEY idx_centro_mando_avance_usuario (id_usuario),
                    CONSTRAINT fk_centro_mando_avance_empresa
                        FOREIGN KEY (id_empresa) REFERENCES empresas(id) ON DELETE CASCADE,
                    CONSTRAINT fk_centro_mando_avance_usuario
                        FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
                )"
            );

            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS centro_mando_notas (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    id_empresa INT NOT NULL,
                    id_usuario INT NOT NULL,
                    mes TINYINT NOT NULL,
                    anio YEAR NOT NULL,
                    nota TEXT NULL,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_empresa_periodo_nota (id_empresa, mes, anio),
                    KEY idx_centro_mando_notas_usuario (id_usuario),
                    CONSTRAINT fk_centro_mando_notas_empresa
                        FOREIGN KEY (id_empresa) REFERENCES empresas(id) ON DELETE CASCADE,
                    CONSTRAINT fk_centro_mando_notas_usuario
                        FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
                )"
            );

            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS centro_mando_preferencias (
                    id_usuario INT PRIMARY KEY,
                    ocultar_bienvenida TINYINT(1) NOT NULL DEFAULT 0,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    CONSTRAINT fk_centro_mando_preferencias_usuario
                        FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
                )"
            );
        } catch (Throwable $exception) {
            // No bloqueamos la app si la migracion automatica falla.
        }
    }
}
