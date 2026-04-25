<?php

class DatabaseBackupService {
    private const TIMEZONE = 'America/El_Salvador';
    private const DEFAULT_WINDOWS_BINARIES = [
        'C:\\Program Files\\PostgreSQL\\18\\bin\\pg_dump.exe',
        'C:\\Program Files\\PostgreSQL\\17\\bin\\pg_dump.exe',
        'C:\\Program Files\\PostgreSQL\\16\\bin\\pg_dump.exe',
        'C:\\Program Files\\PostgreSQL\\15\\bin\\pg_dump.exe',
    ];

    public static function ensureDailyBackup(array $config): array {
        self::ensureStorageDirectory();

        $today = new DateTimeImmutable('now', self::timezone());
        $dailyName = self::buildDailyFileName($config, $today);
        $dailyPath = self::buildStoragePath($dailyName);

        if (is_file($dailyPath)) {
            return [
                'success'  => true,
                'created'  => false,
                'message'  => 'El backup diario de hoy ya estaba disponible.',
                'backup'   => self::mapBackupFile($dailyPath),
                'filename' => $dailyName,
            ];
        }

        return self::createBackupFile(
            $config,
            $dailyName,
            'Backup diario generado correctamente.'
        );
    }

    public static function createManualBackup(array $config): array {
        self::ensureStorageDirectory();

        $now = new DateTimeImmutable('now', self::timezone());
        $baseName = self::buildManualFileName($config, $now);
        $filename = self::findAvailableFileName($baseName);

        return self::createBackupFile(
            $config,
            $filename,
            'Backup manual generado correctamente.'
        );
    }

    public static function getOverview(array $config, int $limit = 20): array {
        self::ensureStorageDirectory();

        $backups = self::listBackups($limit);
        $todayName = self::buildDailyFileName($config, new DateTimeImmutable('now', self::timezone()));

        return [
            'storage_directory' => self::getStorageDirectory(),
            'relative_directory' => self::getRelativeStorageDirectory(),
            'pg_dump_path' => self::resolvePgDumpExecutable(),
            'today_backup' => self::findBackupByName($todayName),
            'latest_backup' => $backups[0] ?? null,
            'total_backups' => count(self::listBackups(500)),
            'backups' => $backups,
        ];
    }

    public static function findBackupByName(string $filename): ?array {
        $safeName = self::sanitizeBackupName($filename);
        $path = self::buildStoragePath($safeName);

        if (!is_file($path)) {
            return null;
        }

        return self::mapBackupFile($path);
    }

    public static function streamBackup(string $filename): void {
        $backup = self::findBackupByName($filename);
        if ($backup === null) {
            throw new RuntimeException('El archivo solicitado ya no existe.');
        }

        $path = (string) ($backup['path'] ?? '');
        $size = (int) ($backup['size_bytes'] ?? 0);

        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Content-Length: ' . $size);
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        readfile($path);
    }

    public static function listBackups(int $limit = 50): array {
        self::ensureStorageDirectory();

        $files = glob(self::buildStoragePath('*.sql')) ?: [];
        usort($files, static function (string $left, string $right): int {
            return filemtime($right) <=> filemtime($left);
        });

        if ($limit > 0) {
            $files = array_slice($files, 0, $limit);
        }

        return array_map([self::class, 'mapBackupFile'], $files);
    }

    public static function formatBytes(int $bytes): string {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = (float) $bytes;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return number_format($size, $size >= 10 || $unitIndex === 0 ? 0 : 1) . ' ' . $units[$unitIndex];
    }

    public static function formatTimestamp(?string $timestamp): string {
        if ($timestamp === null || trim($timestamp) === '') {
            return '-';
        }

        try {
            $date = new DateTimeImmutable($timestamp, self::timezone());
            return $date->format('d/m/Y h:i A');
        } catch (Throwable $exception) {
            return $timestamp;
        }
    }

    private static function createBackupFile(array $config, string $filename, string $successMessage): array {
        $path = self::buildStoragePath($filename);
        $result = self::runPgDump($config, $path);

        if (($result['success'] ?? false) !== true) {
            if (is_file($path)) {
                @unlink($path);
            }

            throw new RuntimeException((string) ($result['message'] ?? 'No se pudo generar el backup.'));
        }

        return [
            'success' => true,
            'created' => true,
            'message' => $successMessage,
            'backup' => self::mapBackupFile($path),
            'filename' => $filename,
        ];
    }

    private static function runPgDump(array $config, string $targetPath): array {
        $pgDumpExecutable = self::resolvePgDumpExecutable();
        $command = [
            $pgDumpExecutable,
            '--host=' . (string) ($config['host'] ?? 'localhost'),
            '--port=' . (string) ($config['port'] ?? '5432'),
            '--username=' . (string) ($config['user'] ?? 'postgres'),
            '--format=plain',
            '--encoding=UTF8',
            '--clean',
            '--if-exists',
            '--create',
            '--restrict-key=saasbackup',
            '--no-owner',
            '--no-privileges',
            '--file=' . $targetPath,
            (string) ($config['dbname'] ?? ''),
        ];

        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $previousPassword = getenv('PGPASSWORD');
        putenv('PGPASSWORD=' . (string) ($config['pass'] ?? ''));

        $process = proc_open(
            $command,
            $descriptors,
            $pipes,
            dirname($targetPath)
        );

        if (!is_resource($process)) {
            if ($previousPassword === false) {
                putenv('PGPASSWORD');
            } else {
                putenv('PGPASSWORD=' . $previousPassword);
            }

            return [
                'success' => false,
                'message' => 'No se pudo iniciar pg_dump desde PHP.',
            ];
        }

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($previousPassword === false) {
            putenv('PGPASSWORD');
        } else {
            putenv('PGPASSWORD=' . $previousPassword);
        }

        if ($exitCode !== 0) {
            $message = trim((string) $stderr) !== ''
                ? trim((string) $stderr)
                : (trim((string) $stdout) !== '' ? trim((string) $stdout) : 'pg_dump devolvio un error.');

            return [
                'success' => false,
                'message' => $message,
            ];
        }

        return [
            'success' => true,
            'message' => 'Backup generado.',
        ];
    }

    private static function resolvePgDumpExecutable(): string {
        $customPath = trim((string) getenv('PG_DUMP_PATH'));
        if ($customPath !== '' && is_file($customPath)) {
            return $customPath;
        }

        foreach (self::DEFAULT_WINDOWS_BINARIES as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('No se encontro pg_dump.exe. Instala PostgreSQL o define PG_DUMP_PATH.');
    }

    private static function ensureStorageDirectory(): void {
        $directory = self::getStorageDirectory();
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('No se pudo crear la carpeta de backups.');
        }

        $htaccessPath = self::buildStoragePath('.htaccess');
        if (!is_file($htaccessPath)) {
            file_put_contents(
                $htaccessPath,
                "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n"
            );
        }
    }

    private static function mapBackupFile(string $path): array {
        $fileName = basename($path);
        $timestamp = filemtime($path) ?: time();
        $createdAt = (new DateTimeImmutable('@' . $timestamp))
            ->setTimezone(self::timezone())
            ->format(DATE_ATOM);
        $isDaily = strpos($fileName, '_manual_') === false;
        $todayToken = (new DateTimeImmutable('now', self::timezone()))->format('Y-m-d');

        return [
            'name' => $fileName,
            'path' => $path,
            'relative_path' => str_replace('\\', '/', self::getRelativeStorageDirectory() . '/' . $fileName),
            'size_bytes' => (int) filesize($path),
            'size_label' => self::formatBytes((int) filesize($path)),
            'created_at' => $createdAt,
            'created_at_label' => self::formatTimestamp($createdAt),
            'is_today' => str_contains($fileName, $todayToken),
            'type' => $isDaily ? 'Diario' : 'Manual',
        ];
    }

    private static function buildDailyFileName(array $config, DateTimeImmutable $date): string {
        return (string) ($config['dbname'] ?? 'database') . '_backup_' . $date->format('Y-m-d') . '.sql';
    }

    private static function buildManualFileName(array $config, DateTimeImmutable $date): string {
        return (string) ($config['dbname'] ?? 'database') . '_backup_manual_' . $date->format('Y-m-d_H-i-s') . '.sql';
    }

    private static function findAvailableFileName(string $baseName): string {
        $directory = self::getStorageDirectory();
        $extension = pathinfo($baseName, PATHINFO_EXTENSION);
        $nameOnly = pathinfo($baseName, PATHINFO_FILENAME);
        $candidate = $baseName;
        $counter = 1;

        while (is_file($directory . DIRECTORY_SEPARATOR . $candidate)) {
            $candidate = $nameOnly . '_' . str_pad((string) $counter, 2, '0', STR_PAD_LEFT) . '.' . $extension;
            $counter++;
        }

        return $candidate;
    }

    private static function sanitizeBackupName(string $filename): string {
        $cleanName = basename(trim($filename));

        if ($cleanName === '' || !preg_match('/^[A-Za-z0-9._-]+\.sql$/', $cleanName)) {
            throw new RuntimeException('El nombre del backup solicitado no es valido.');
        }

        return $cleanName;
    }

    private static function getStorageDirectory(): string {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . 'postgresql_daily';
    }

    private static function getRelativeStorageDirectory(): string {
        return 'backup/postgresql_daily';
    }

    private static function buildStoragePath(string $filename): string {
        return self::getStorageDirectory() . DIRECTORY_SEPARATOR . $filename;
    }

    private static function timezone(): DateTimeZone {
        return new DateTimeZone(self::TIMEZONE);
    }
}
