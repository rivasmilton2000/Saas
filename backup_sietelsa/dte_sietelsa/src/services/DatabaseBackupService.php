<?php

class DatabaseBackupService
{
    public static function download(PDO $pdo, ?string $databaseName = null): void
    {
        $databaseName = self::resolveDatabaseName($pdo, $databaseName);
        $stream = fopen('php://temp/maxmemory:5242880', 'w+');
        $transactionStarted = false;

        if ($stream === false) {
            throw new RuntimeException('No se pudo preparar el archivo temporal del backup.');
        }

        try {
            self::beginConsistentSnapshot($pdo, $transactionStarted);
            self::writeHeader($stream, $databaseName);
            self::writeDatabaseBootstrap($pdo, $stream, $databaseName);

            foreach (self::listDatabaseObjects($pdo, $databaseName) as $object) {
                if ($object['type'] === 'VIEW') {
                    self::writeView($pdo, $stream, $object['name']);
                    continue;
                }

                self::writeTable($pdo, $stream, $object['name']);
            }

            foreach (self::listRoutines($pdo, $databaseName) as $routine) {
                self::writeRoutine($pdo, $stream, $routine['name'], $routine['type']);
            }

            foreach (self::listEvents($pdo, $databaseName) as $eventName) {
                self::writeEvent($pdo, $stream, $eventName);
            }

            foreach (self::listTriggers($pdo, $databaseName) as $triggerName) {
                self::writeTrigger($pdo, $stream, $databaseName, $triggerName);
            }

            self::writeLine($stream, 'SET FOREIGN_KEY_CHECKS=1;');

            if ($transactionStarted && $pdo->inTransaction()) {
                $pdo->commit();
            }
        } catch (Throwable $exception) {
            if ($transactionStarted && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            fclose($stream);
            throw $exception;
        }

        $size = ftell($stream);
        if ($size === false) {
            $size = 0;
        }

        $filename = self::buildFilename($databaseName);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . $size);
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        rewind($stream);
        fpassthru($stream);
        fclose($stream);
        exit;
    }

    private static function beginConsistentSnapshot(PDO $pdo, bool &$transactionStarted): void
    {
        if ($pdo->inTransaction()) {
            return;
        }

        $driver = strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));

        if ($driver === 'mysql') {
            $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
            $transactionStarted = true;
            return;
        }

        $pdo->beginTransaction();
        $transactionStarted = true;
    }

    private static function resolveDatabaseName(PDO $pdo, ?string $databaseName): string
    {
        if ($databaseName !== null && trim($databaseName) !== '') {
            return trim($databaseName);
        }

        $resolved = $pdo->query('SELECT DATABASE()')->fetchColumn();
        if (!is_string($resolved) || trim($resolved) === '') {
            throw new RuntimeException('No se pudo identificar la base de datos activa.');
        }

        return trim($resolved);
    }

    private static function listDatabaseObjects(PDO $pdo, string $databaseName): array
    {
        $statement = $pdo->query('SHOW FULL TABLES FROM ' . self::quoteIdentifier($databaseName));
        $objects = [];

        while ($row = $statement->fetch(PDO::FETCH_NUM)) {
            $objects[] = [
                'name' => (string) ($row[0] ?? ''),
                'type' => strtoupper((string) ($row[1] ?? 'BASE TABLE')),
            ];
        }

        usort($objects, static function (array $left, array $right): int {
            $priority = [
                'BASE TABLE' => 0,
                'VIEW'       => 1,
            ];

            $leftPriority = $priority[$left['type']] ?? 2;
            $rightPriority = $priority[$right['type']] ?? 2;

            if ($leftPriority !== $rightPriority) {
                return $leftPriority <=> $rightPriority;
            }

            return strcmp($left['name'], $right['name']);
        });

        return $objects;
    }

    private static function writeHeader($stream, string $databaseName): void
    {
        self::writeLine($stream, '-- --------------------------------------------------------');
        self::writeLine($stream, '-- Backup generado desde ' . (function_exists('app_name') ? app_name() : 'la aplicacion'));
        self::writeLine($stream, '-- Base de datos: ' . $databaseName);
        self::writeLine($stream, '-- Fecha: ' . date('Y-m-d H:i:s'));
        self::writeLine($stream, '-- --------------------------------------------------------');
        self::writeLine($stream, '');
        self::writeLine($stream, "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';");
        self::writeLine($stream, "SET time_zone = '+00:00';");
        self::writeLine($stream, 'SET NAMES utf8mb4;');
        self::writeLine($stream, 'SET FOREIGN_KEY_CHECKS=0;');
        self::writeLine($stream, '');
    }

    private static function writeDatabaseBootstrap(PDO $pdo, $stream, string $databaseName): void
    {
        $metadata = self::databaseMetadata($pdo, $databaseName);
        $create = 'CREATE DATABASE IF NOT EXISTS ' . self::quoteIdentifier($databaseName);

        if (($metadata['charset'] ?? '') !== '') {
            $create .= ' CHARACTER SET ' . $metadata['charset'];
        }

        if (($metadata['collation'] ?? '') !== '') {
            $create .= ' COLLATE ' . $metadata['collation'];
        }

        self::writeLine($stream, $create . ';');
        self::writeLine($stream, 'USE ' . self::quoteIdentifier($databaseName) . ';');
        self::writeLine($stream, '');
    }

    private static function writeTable(PDO $pdo, $stream, string $tableName): void
    {
        $createStatement = $pdo->query('SHOW CREATE TABLE ' . self::quoteIdentifier($tableName));
        $createRow = $createStatement->fetch(PDO::FETCH_NUM);

        if (!isset($createRow[1])) {
            throw new RuntimeException('No se pudo obtener la estructura de la tabla ' . $tableName . '.');
        }

        self::writeLine($stream, '-- Tabla: ' . $tableName);
        self::writeLine($stream, 'DROP TABLE IF EXISTS ' . self::quoteIdentifier($tableName) . ';');
        self::writeLine($stream, $createRow[1] . ';');
        self::writeLine($stream, '');

        $columns = self::listTableColumns($pdo, $tableName);
        if ($columns === []) {
            self::writeLine($stream, '');
            return;
        }

        $selectStatement = $pdo->query('SELECT * FROM ' . self::quoteIdentifier($tableName));
        $quotedColumns = implode(', ', array_map([self::class, 'quoteIdentifier'], $columns));
        $rows = [];

        while ($row = $selectStatement->fetch(PDO::FETCH_ASSOC)) {
            $values = [];

            foreach ($columns as $column) {
                $values[] = self::sqlValue($pdo, $row[$column] ?? null);
            }

            $rows[] = '(' . implode(', ', $values) . ')';

            if (count($rows) >= 100) {
                self::writeInsert($stream, $tableName, $quotedColumns, $rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            self::writeInsert($stream, $tableName, $quotedColumns, $rows);
        }

        self::writeLine($stream, '');
    }

    private static function writeView(PDO $pdo, $stream, string $viewName): void
    {
        $createStatement = $pdo->query('SHOW CREATE VIEW ' . self::quoteIdentifier($viewName));
        $createRow = $createStatement->fetch(PDO::FETCH_NUM);

        if (!isset($createRow[1])) {
            throw new RuntimeException('No se pudo obtener la estructura de la vista ' . $viewName . '.');
        }

        self::writeLine($stream, '-- Vista: ' . $viewName);
        self::writeLine($stream, 'DROP VIEW IF EXISTS ' . self::quoteIdentifier($viewName) . ';');
        self::writeLine($stream, $createRow[1] . ';');
        self::writeLine($stream, '');
    }

    private static function writeRoutine(PDO $pdo, $stream, string $routineName, string $routineType): void
    {
        $routineType = strtoupper($routineType);
        $command = $routineType === 'FUNCTION' ? 'FUNCTION' : 'PROCEDURE';
        $createStatement = $pdo->query('SHOW CREATE ' . $command . ' ' . self::quoteIdentifier($routineName));
        $createRow = $createStatement->fetch(PDO::FETCH_ASSOC);
        $createKey = $command === 'FUNCTION' ? 'Create Function' : 'Create Procedure';
        $createSql = trim((string) ($createRow[$createKey] ?? ''));

        if ($createSql === '') {
            throw new RuntimeException('No se pudo obtener la definicion de la rutina ' . $routineName . '.');
        }

        self::writeLine($stream, '-- ' . ucfirst(strtolower($command)) . ': ' . $routineName);
        self::writeLine($stream, 'DROP ' . $command . ' IF EXISTS ' . self::quoteIdentifier($routineName) . ';');
        self::writeLine($stream, 'DELIMITER $$');
        self::writeLine($stream, $createSql . '$$');
        self::writeLine($stream, 'DELIMITER ;');
        self::writeLine($stream, '');
    }

    private static function writeEvent(PDO $pdo, $stream, string $eventName): void
    {
        $createStatement = $pdo->query('SHOW CREATE EVENT ' . self::quoteIdentifier($eventName));
        $createRow = $createStatement->fetch(PDO::FETCH_ASSOC);
        $createSql = trim((string) ($createRow['Create Event'] ?? ''));

        if ($createSql === '') {
            throw new RuntimeException('No se pudo obtener la definicion del evento ' . $eventName . '.');
        }

        self::writeLine($stream, '-- Evento: ' . $eventName);
        self::writeLine($stream, 'DROP EVENT IF EXISTS ' . self::quoteIdentifier($eventName) . ';');
        self::writeLine($stream, 'DELIMITER $$');
        self::writeLine($stream, $createSql . '$$');
        self::writeLine($stream, 'DELIMITER ;');
        self::writeLine($stream, '');
    }

    private static function writeTrigger(PDO $pdo, $stream, string $databaseName, string $triggerName): void
    {
        $statement = $pdo->prepare(
            'SELECT ACTION_TIMING, EVENT_MANIPULATION, EVENT_OBJECT_TABLE, ACTION_STATEMENT, DEFINER
             FROM information_schema.triggers
             WHERE trigger_schema = ? AND trigger_name = ?
             LIMIT 1'
        );
        $statement->execute([$databaseName, $triggerName]);
        $trigger = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($trigger)) {
            throw new RuntimeException('No se pudo obtener la definicion del trigger ' . $triggerName . '.');
        }

        $body = trim((string) ($trigger['ACTION_STATEMENT'] ?? ''));
        if ($body === '') {
            throw new RuntimeException('El trigger ' . $triggerName . ' no contiene sentencia exportable.');
        }

        $definer = trim((string) ($trigger['DEFINER'] ?? ''));
        $create = 'CREATE ';

        if ($definer !== '') {
            $create .= 'DEFINER=' . $definer . ' ';
        }

        $create .= 'TRIGGER '
            . self::quoteIdentifier($triggerName)
            . ' '
            . strtoupper(trim((string) ($trigger['ACTION_TIMING'] ?? '')))
            . ' '
            . strtoupper(trim((string) ($trigger['EVENT_MANIPULATION'] ?? '')))
            . ' ON '
            . self::quoteIdentifier((string) ($trigger['EVENT_OBJECT_TABLE'] ?? ''))
            . ' FOR EACH ROW '
            . $body;

        self::writeLine($stream, '-- Trigger: ' . $triggerName);
        self::writeLine($stream, 'DROP TRIGGER IF EXISTS ' . self::quoteIdentifier($triggerName) . ';');
        self::writeLine($stream, 'DELIMITER $$');
        self::writeLine($stream, $create . '$$');
        self::writeLine($stream, 'DELIMITER ;');
        self::writeLine($stream, '');
    }

    private static function listTableColumns(PDO $pdo, string $tableName): array
    {
        $statement = $pdo->query('SHOW COLUMNS FROM ' . self::quoteIdentifier($tableName));
        $columns = [];

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            if (isset($row['Field'])) {
                $columns[] = (string) $row['Field'];
            }
        }

        return $columns;
    }

    private static function listRoutines(PDO $pdo, string $databaseName): array
    {
        $statement = $pdo->prepare(
            'SELECT ROUTINE_NAME, ROUTINE_TYPE
             FROM information_schema.routines
             WHERE routine_schema = ?
             ORDER BY CASE ROUTINE_TYPE WHEN "PROCEDURE" THEN 0 ELSE 1 END, ROUTINE_NAME'
        );
        $statement->execute([$databaseName]);

        return array_map(static function (array $row): array {
            return [
                'name' => (string) ($row['ROUTINE_NAME'] ?? ''),
                'type' => strtoupper((string) ($row['ROUTINE_TYPE'] ?? 'PROCEDURE')),
            ];
        }, $statement->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    private static function listEvents(PDO $pdo, string $databaseName): array
    {
        $statement = $pdo->prepare(
            'SELECT EVENT_NAME
             FROM information_schema.events
             WHERE event_schema = ?
             ORDER BY EVENT_NAME'
        );
        $statement->execute([$databaseName]);

        return array_map(static function (array $row): string {
            return (string) ($row['EVENT_NAME'] ?? '');
        }, $statement->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    private static function listTriggers(PDO $pdo, string $databaseName): array
    {
        $statement = $pdo->prepare(
            'SELECT TRIGGER_NAME
             FROM information_schema.triggers
             WHERE trigger_schema = ?
             ORDER BY EVENT_OBJECT_TABLE, TRIGGER_NAME'
        );
        $statement->execute([$databaseName]);

        return array_map(static function (array $row): string {
            return (string) ($row['TRIGGER_NAME'] ?? '');
        }, $statement->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    private static function databaseMetadata(PDO $pdo, string $databaseName): array
    {
        $statement = $pdo->prepare(
            'SELECT DEFAULT_CHARACTER_SET_NAME AS charset_name, DEFAULT_COLLATION_NAME AS collation_name
             FROM information_schema.schemata
             WHERE schema_name = ?
             LIMIT 1'
        );
        $statement->execute([$databaseName]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return [
            'charset'   => trim((string) ($row['charset_name'] ?? '')),
            'collation' => trim((string) ($row['collation_name'] ?? '')),
        ];
    }

    private static function writeInsert($stream, string $tableName, string $quotedColumns, array $rows): void
    {
        self::writeLine(
            $stream,
            'INSERT INTO '
            . self::quoteIdentifier($tableName)
            . ' ('
            . $quotedColumns
            . ') VALUES'
        );
        self::writeLine($stream, implode(",\n", $rows) . ';');
    }

    private static function sqlValue(PDO $pdo, $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        return $pdo->quote((string) $value);
    }

    private static function buildFilename(string $databaseName): string
    {
        $safeName = strtolower(preg_replace('/[^A-Za-z0-9_-]+/', '_', $databaseName) ?? 'database');
        $safeName = trim($safeName, '_-');

        if ($safeName === '') {
            $safeName = 'database';
        }

        return $safeName . '_backup_' . date('Y-m-d_H-i-s') . '.sql';
    }

    private static function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private static function writeLine($stream, string $line): void
    {
        fwrite($stream, $line . "\n");
    }
}
