<?php
declare(strict_types=1);

const MYSQL_DSN = 'mysql:host=localhost;dbname=saas_contabilidad;charset=utf8mb4';
const MYSQL_USER = 'root';
const MYSQL_PASS = '';

const POSTGRES_HOST = 'localhost';
const POSTGRES_PORT = '5432';
const POSTGRES_ADMIN_DB = 'postgres';
const POSTGRES_TARGET_DB = 'Saas';
const POSTGRES_USER = 'postgres';
const POSTGRES_PASS = '1581';

const TABLE_ORDER = [
    'usuarios',
    'empresas',
    'libros',
    'facturas',
    'facturas_disponibles',
    'centro_mando_config',
    'centro_mando_avance',
    'centro_mando_notas',
    'centro_mando_preferencias',
    'bitacora_movimientos',
];

const IDENTITY_TABLES = [
    'usuarios',
    'empresas',
    'libros',
    'facturas',
    'facturas_disponibles',
    'centro_mando_config',
    'centro_mando_avance',
    'centro_mando_notas',
    'bitacora_movimientos',
];

const BOOLEAN_COLUMNS = [
    'usuarios'                 => ['estado'],
    'empresas'                 => ['estado'],
    'libros'                   => ['estado'],
    'centro_mando_avance'      => ['completado'],
    'centro_mando_preferencias'=> ['ocultar_bienvenida'],
];

const EXCLUDED_COLUMNS = [
    'libros' => ['nombre'],
];

main($argv);

function main(array $argv): void {
    $apply = in_array('--apply', $argv, true);
    $schemaFile = __DIR__ . DIRECTORY_SEPARATOR . 'postgresql_schema.sql';
    $seedFile = __DIR__ . DIRECTORY_SEPARATOR . 'postgresql_seed.sql';

    if (!is_file($schemaFile)) {
        throw new RuntimeException('No existe el archivo de esquema PostgreSQL: ' . $schemaFile);
    }

    $mysql = createPdo(MYSQL_DSN, MYSQL_USER, MYSQL_PASS);
    $seedSql = buildSeedSql($mysql);
    file_put_contents($seedFile, $seedSql);
    echo "Seed generado en: {$seedFile}" . PHP_EOL;

    if (!$apply) {
        echo "Usa --apply para crear la base destino e importar el esquema y datos." . PHP_EOL;
        return;
    }

    $postgresAdmin = createPdo(
        sprintf('pgsql:host=%s;port=%s;dbname=%s', POSTGRES_HOST, POSTGRES_PORT, POSTGRES_ADMIN_DB),
        POSTGRES_USER,
        POSTGRES_PASS
    );
    ensureDatabase($postgresAdmin, POSTGRES_TARGET_DB);

    $postgresTarget = createPdo(
        sprintf('pgsql:host=%s;port=%s;dbname=%s', POSTGRES_HOST, POSTGRES_PORT, POSTGRES_TARGET_DB),
        POSTGRES_USER,
        POSTGRES_PASS
    );
    $postgresTarget->exec("SET TIME ZONE 'America/El_Salvador'");
    $postgresTarget->exec((string) file_get_contents($schemaFile));
    $postgresTarget->exec($seedSql);

    echo 'Migracion aplicada en PostgreSQL: ' . POSTGRES_TARGET_DB . PHP_EOL;
}

function createPdo(string $dsn, string $user, string $pass): PDO {
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
}

function ensureDatabase(PDO $pdo, string $databaseName): void {
    $stmt = $pdo->prepare('SELECT 1 FROM pg_database WHERE datname = ?');
    $stmt->execute([$databaseName]);

    if ($stmt->fetchColumn() !== false) {
        return;
    }

    $pdo->exec('CREATE DATABASE ' . pgQuoteIdentifier($databaseName) . " WITH ENCODING 'UTF8'");
}

function buildSeedSql(PDO $mysql): string {
    $lines = [];
    $lines[] = '-- PostgreSQL seed generated from MySQL';
    $lines[] = '-- Generated at ' . date('Y-m-d H:i:s');
    $lines[] = "SET client_encoding = 'UTF8';";
    $lines[] = "SET TIME ZONE 'America/El_Salvador';";
    $lines[] = 'BEGIN;';
    $lines[] = 'TRUNCATE TABLE bitacora_movimientos, centro_mando_preferencias, centro_mando_notas, centro_mando_avance, centro_mando_config, facturas_disponibles, facturas, libros, empresas, usuarios RESTART IDENTITY CASCADE;';
    $lines[] = '';

    foreach (TABLE_ORDER as $table) {
        $rows = fetchTableRows($mysql, $table);
        $lines[] = '-- Table: ' . $table;

        if ($rows === []) {
            $lines[] = '-- No rows';
            $lines[] = '';
            continue;
        }

        $columns = array_values(array_filter(
            array_keys($rows[0]),
            static fn(string $column): bool => !in_array($column, EXCLUDED_COLUMNS[$table] ?? [], true)
        ));
        foreach ($rows as $row) {
            $values = [];
            foreach ($columns as $column) {
                $values[] = pgLiteral(
                    $row[$column] ?? null,
                    in_array($column, BOOLEAN_COLUMNS[$table] ?? [], true)
                );
            }

            $lines[] = sprintf(
                'INSERT INTO %s (%s) VALUES (%s);',
                $table,
                implode(', ', $columns),
                implode(', ', $values)
            );
        }

        $lines[] = '';
    }

    foreach (IDENTITY_TABLES as $table) {
        $lines[] = sprintf(
            "SELECT setval(pg_get_serial_sequence('%s', 'id'), COALESCE((SELECT MAX(id) FROM %s), 1), COALESCE((SELECT MAX(id) FROM %s), 0) > 0);",
            $table,
            $table,
            $table
        );
    }

    $lines[] = 'COMMIT;';
    $lines[] = '';

    return implode(PHP_EOL, $lines);
}

function fetchTableRows(PDO $mysql, string $table): array {
    $orderBy = match ($table) {
        'usuarios',
        'empresas',
        'libros',
        'facturas',
        'facturas_disponibles',
        'centro_mando_config',
        'centro_mando_avance',
        'centro_mando_notas',
        'bitacora_movimientos' => ' ORDER BY id ASC',
        'centro_mando_preferencias' => ' ORDER BY id_usuario ASC',
        default => '',
    };

    $stmt = $mysql->query('SELECT * FROM `' . $table . '`' . $orderBy);
    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

function pgLiteral($value, bool $isBoolean = false): string {
    if ($value === null) {
        return 'NULL';
    }

    if ($isBoolean) {
        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 't', 'true', 'y', 'yes', 'on'], true) ? 'TRUE' : 'FALSE';
    }

    return dollarQuote((string) $value);
}

function dollarQuote(string $value): string {
    static $counter = 0;

    do {
        $counter++;
        $tag = '$pg' . $counter . '$';
    } while (str_contains($value, $tag));

    return $tag . $value . $tag;
}

function pgQuoteIdentifier(string $identifier): string {
    return '"' . str_replace('"', '""', $identifier) . '"';
}
