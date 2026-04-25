<?php

class UsuarioModel {

    private const ROLES = ['admin', 'user'];
    private static bool $schemaChecked = false;

    public static function ensureSchema(PDO $pdo): void {
        if (self::$schemaChecked) {
            return;
        }

        $pdo->exec(
            "ALTER TABLE usuarios
                ADD COLUMN IF NOT EXISTS nombre_completo VARCHAR(150) NULL AFTER username,
                ADD COLUMN IF NOT EXISTS foto_perfil VARCHAR(255) NULL AFTER nombre_completo"
        );

        self::$schemaChecked = true;
    }

    public static function getById(PDO $pdo, int $idUsuario, bool $onlyActive = true): ?array {
        self::ensureSchema($pdo);

        $sql = "SELECT id, username, nombre_completo, foto_perfil, password, rol, estado, created_at
                FROM usuarios
                WHERE id = ?";

        if ($onlyActive) {
            $sql .= " AND estado = 1";
        }

        $sql .= " LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$idUsuario]);

        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        return $usuario ?: null;
    }

    public static function getByUsername(PDO $pdo, string $username): ?array {
        self::ensureSchema($pdo);

        $stmt = $pdo->prepare(
            "SELECT id, username, nombre_completo, foto_perfil, password, rol, estado, created_at
             FROM usuarios
             WHERE LOWER(username) = LOWER(?)
             LIMIT 1"
        );
        $stmt->execute([trim($username)]);

        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        return $usuario ?: null;
    }

    public static function getActivos(PDO $pdo): array {
        self::ensureSchema($pdo);

        $stmt = $pdo->query(
            "SELECT id, username, nombre_completo, foto_perfil, rol, estado, created_at
             FROM usuarios
             WHERE estado = 1
             ORDER BY FIELD(rol, 'admin', 'user'), username ASC"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getResumen(PDO $pdo): array {
        self::ensureSchema($pdo);

        $stmt = $pdo->query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN rol = 'admin' THEN 1 ELSE 0 END) AS admins,
                SUM(CASE WHEN rol = 'user' THEN 1 ELSE 0 END) AS users
             FROM usuarios
             WHERE estado = 1"
        );

        $resumen = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total'  => (int) ($resumen['total'] ?? 0),
            'admins' => (int) ($resumen['admins'] ?? 0),
            'users'  => (int) ($resumen['users'] ?? 0),
        ];
    }

    public static function countAdmins(PDO $pdo, ?int $excludeId = null): int {
        self::ensureSchema($pdo);

        $sql = "SELECT COUNT(*)
                FROM usuarios
                WHERE rol = 'admin' AND estado = 1";
        $params = [];

        if ($excludeId !== null && $excludeId > 0) {
            $sql .= " AND id <> ?";
            $params[] = $excludeId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public static function canSelectAdminOnPublicRegister(PDO $pdo): bool {
        return self::countAdmins($pdo) === 0;
    }

    public static function validateNewUser(PDO $pdo, array $input, bool $allowAdminRole = false): array {
        $username        = trim((string) ($input['username'] ?? ''));
        $password        = (string) ($input['password'] ?? '');
        $confirmPassword = (string) ($input['confirm_password'] ?? '');
        $rolSolicitado   = self::normalizeRole($input['rol'] ?? 'user');
        $rolFinal        = $allowAdminRole ? $rolSolicitado : 'user';
        $oldInput        = [
            'username' => $username,
            'rol'      => $rolFinal,
        ];

        $usernameError = self::validateUsername($username);
        if ($usernameError !== null) {
            return [
                'ok'      => false,
                'message' => $usernameError,
                'old'     => $oldInput,
            ];
        }

        if (self::usernameExists($pdo, $username)) {
            return [
                'ok'      => false,
                'message' => 'Ese nombre de usuario ya existe.',
                'old'     => $oldInput,
            ];
        }

        $passwordError = self::validatePasswordPair($password, $confirmPassword, false);
        if ($passwordError !== null) {
            return [
                'ok'      => false,
                'message' => $passwordError,
                'old'     => $oldInput,
            ];
        }

        return [
            'ok'   => true,
            'data' => [
                'username' => $username,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'rol'      => $rolFinal,
                'estado'   => 1,
            ],
            'old'  => $oldInput,
        ];
    }

    public static function validateUserUpdate(PDO $pdo, int $idUsuario, array $input, bool $allowAdminRole = true): array {
        $usuarioActual = self::getById($pdo, $idUsuario);
        $username      = trim((string) ($input['username'] ?? ''));
        $password      = (string) ($input['password'] ?? '');
        $confirm       = (string) ($input['confirm_password'] ?? '');
        $rolSolicitado = self::normalizeRole($input['rol'] ?? 'user');
        $rolFinal      = $allowAdminRole ? $rolSolicitado : 'user';
        $oldInput      = [
            'id'       => $idUsuario,
            'username' => $username,
            'rol'      => $rolFinal,
        ];

        if ($usuarioActual === null) {
            return [
                'ok'      => false,
                'message' => 'El perfil que intentas editar ya no existe.',
                'old'     => $oldInput,
            ];
        }

        $usernameError = self::validateUsername($username);
        if ($usernameError !== null) {
            return [
                'ok'      => false,
                'message' => $usernameError,
                'old'     => $oldInput,
            ];
        }

        if (self::usernameExists($pdo, $username, $idUsuario)) {
            return [
                'ok'      => false,
                'message' => 'Ese nombre de usuario ya existe.',
                'old'     => $oldInput,
            ];
        }

        $passwordError = self::validatePasswordPair($password, $confirm, true);
        if ($passwordError !== null) {
            return [
                'ok'      => false,
                'message' => $passwordError,
                'old'     => $oldInput,
            ];
        }

        if (
            (string) $usuarioActual['rol'] === 'admin' &&
            $rolFinal !== 'admin' &&
            self::countAdmins($pdo, $idUsuario) === 0
        ) {
            return [
                'ok'      => false,
                'message' => 'No puedes quitar el rol admin al ultimo administrador activo.',
                'old'     => $oldInput,
            ];
        }

        $data = [
            'username' => $username,
            'rol'      => $rolFinal,
        ];

        if ($password !== '') {
            $data['password'] = password_hash($password, PASSWORD_DEFAULT);
        }

        return [
            'ok'   => true,
            'data' => $data,
            'old'  => $oldInput,
        ];
    }

    public static function create(PDO $pdo, array $data): int {
        self::ensureSchema($pdo);

        $stmt = $pdo->prepare(
            "INSERT INTO usuarios (username, nombre_completo, foto_perfil, password, rol, estado)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            trim((string) $data['username']),
            self::nullableText($data['nombre_completo'] ?? null),
            self::nullableText($data['foto_perfil'] ?? null),
            (string) $data['password'],
            self::normalizeRole($data['rol'] ?? 'user'),
            isset($data['estado']) ? (int) $data['estado'] : 1,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function update(PDO $pdo, int $idUsuario, array $data): bool {
        self::ensureSchema($pdo);
        $usuarioActual = self::getById($pdo, $idUsuario, false);

        $fields = [
            'username = ?',
            'nombre_completo = ?',
            'foto_perfil = ?',
            'rol = ?',
        ];
        $params = [
            trim((string) $data['username']),
            self::nullableText($data['nombre_completo'] ?? ($usuarioActual['nombre_completo'] ?? null)),
            self::nullableText($data['foto_perfil'] ?? ($usuarioActual['foto_perfil'] ?? null)),
            self::normalizeRole($data['rol'] ?? 'user'),
        ];

        if (isset($data['password']) && (string) $data['password'] !== '') {
            $fields[] = 'password = ?';
            $params[] = (string) $data['password'];
        }

        $params[] = $idUsuario;

        $stmt = $pdo->prepare(
            "UPDATE usuarios
             SET " . implode(', ', $fields) . "
             WHERE id = ? AND estado = 1"
        );

        return $stmt->execute($params);
    }

    public static function deactivate(PDO $pdo, int $idUsuario): bool {
        self::ensureSchema($pdo);

        $stmt = $pdo->prepare(
            "UPDATE usuarios
             SET estado = 0
             WHERE id = ? AND estado = 1"
        );
        $stmt->execute([$idUsuario]);

        return $stmt->rowCount() > 0;
    }

    public static function normalizeRole($rol, string $fallback = 'user'): string {
        $rol = strtolower(trim((string) $rol));

        if (in_array($rol, self::ROLES, true)) {
            return $rol;
        }

        return in_array($fallback, self::ROLES, true) ? $fallback : 'user';
    }

    public static function updateOwnProfile(PDO $pdo, int $idUsuario, array $data): bool {
        self::ensureSchema($pdo);

        $fields = [
            'username = ?',
            'nombre_completo = ?',
        ];
        $params = [
            trim((string) ($data['username'] ?? '')),
            self::nullableText($data['nombre_completo'] ?? null),
        ];

        if (array_key_exists('foto_perfil', $data)) {
            $fields[] = 'foto_perfil = ?';
            $params[] = self::nullableText($data['foto_perfil']);
        }

        if (!empty($data['password'])) {
            $fields[] = 'password = ?';
            $params[] = (string) $data['password'];
        }

        $params[] = $idUsuario;

        $stmt = $pdo->prepare(
            "UPDATE usuarios
             SET " . implode(', ', $fields) . "
             WHERE id = ? AND estado = 1"
        );

        return $stmt->execute($params);
    }

    private static function usernameExists(PDO $pdo, string $username, ?int $excludeId = null): bool {
        self::ensureSchema($pdo);

        $sql = "SELECT id
                FROM usuarios
                WHERE LOWER(username) = LOWER(?) AND estado = 1";
        $params = [trim($username)];

        if ($excludeId !== null && $excludeId > 0) {
            $sql .= " AND id <> ?";
            $params[] = $excludeId;
        }

        $sql .= " LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }

    private static function validateUsername(string $username): ?string {
        if ($username === '') {
            return 'Debes escribir un nombre de usuario.';
        }

        if (preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username) !== 1) {
            return 'Usa de 3 a 50 caracteres: letras, numeros, punto, guion o guion bajo.';
        }

        return null;
    }

    private static function validatePasswordPair(string $password, string $confirmPassword, bool $allowEmpty): ?string {
        if ($allowEmpty && $password === '' && $confirmPassword === '') {
            return null;
        }

        if ($password === '') {
            return $allowEmpty
                ? 'Si vas a cambiar la clave, debes escribirla completa.'
                : 'Debes escribir una clave.';
        }

        if (strlen($password) < 6) {
            return 'La clave debe tener al menos 6 caracteres.';
        }

        if ($password !== $confirmPassword) {
            return 'Las claves no coinciden.';
        }

        return null;
    }

    private static function nullableText($value): ?string {
        $value = trim((string) $value);
        return $value !== '' ? $value : null;
    }
}
