<?php
require_once __DIR__ . '/../config/countries.php';
require_once __DIR__ . '/PlanModel.php';

class UsuarioModel
{
    private const ROLES = ['admin', 'user'];
    private static bool $schemaChecked = false;

    public static function ensureSchema(PDO $pdo): void
    {
        PlanModel::ensureSchema($pdo);

        if (self::$schemaChecked) {
            return;
        }

        $pdo->exec(
            "ALTER TABLE usuarios
                ADD COLUMN IF NOT EXISTS nombre_completo VARCHAR(150) NULL,
                ADD COLUMN IF NOT EXISTS foto_perfil VARCHAR(255) NULL,
                ADD COLUMN IF NOT EXISTS pais VARCHAR(80) NULL,
                ADD COLUMN IF NOT EXISTS id_plan INTEGER NULL,
                ADD COLUMN IF NOT EXISTS ultimo_login_at TIMESTAMP NULL,
                ADD COLUMN IF NOT EXISTS ultima_actividad_at TIMESTAMP NULL"
        );
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_usuarios_id_plan ON usuarios (id_plan)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_usuarios_pais ON usuarios (pais)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_usuarios_ultimo_login ON usuarios (ultimo_login_at)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_usuarios_ultima_actividad ON usuarios (ultima_actividad_at)");
        $pdo->exec(
            "DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1
                    FROM pg_constraint
                    WHERE conname = 'fk_usuarios_plan'
                ) THEN
                    ALTER TABLE usuarios
                    ADD CONSTRAINT fk_usuarios_plan
                    FOREIGN KEY (id_plan) REFERENCES planes(id_plan)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE;
                END IF;
            END
            $$;"
        );
        $pdo->exec(
            "UPDATE usuarios
             SET pais = 'El Salvador'
             WHERE pais IS NULL OR TRIM(pais) = ''"
        );

        $defaultPlan = PlanModel::getBySlug($pdo, 'free');
        if ($defaultPlan !== null) {
            $stmt = $pdo->prepare(
                "UPDATE usuarios
                 SET id_plan = ?
                 WHERE rol = 'user'
                   AND id_plan IS NULL"
            );
            $stmt->execute([(int) ($defaultPlan['id_plan'] ?? 0)]);
        }

        self::$schemaChecked = true;
    }

    public static function getById(PDO $pdo, int $idUsuario, bool $onlyActive = true): ?array
    {
        self::ensureSchema($pdo);

        $sql = self::baseSelectSql() . " WHERE u.id = ?";
        if ($onlyActive) {
            $sql .= " AND u.estado = TRUE";
        }
        $sql .= " LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$idUsuario]);

        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        return $usuario ?: null;
    }

    public static function getByUsername(PDO $pdo, string $username): ?array
    {
        self::ensureSchema($pdo);

        $stmt = $pdo->prepare(
            self::baseSelectSql() . "
             WHERE LOWER(u.username) = LOWER(?)
             LIMIT 1"
        );
        $stmt->execute([trim($username)]);

        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        return $usuario ?: null;
    }

    public static function getActivos(PDO $pdo): array
    {
        self::ensureSchema($pdo);

        $stmt = $pdo->query(
            self::baseSelectSql(false) . "
             WHERE u.estado = TRUE
             ORDER BY CASE u.rol WHEN 'admin' THEN 0 ELSE 1 END, u.username ASC"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function getResumen(PDO $pdo): array
    {
        self::ensureSchema($pdo);

        $stmt = $pdo->query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN rol = 'admin' THEN 1 ELSE 0 END) AS admins,
                SUM(CASE WHEN rol = 'user' THEN 1 ELSE 0 END) AS users
             FROM usuarios
             WHERE estado = TRUE"
        );

        $resumen = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total'  => (int) ($resumen['total'] ?? 0),
            'admins' => (int) ($resumen['admins'] ?? 0),
            'users'  => (int) ($resumen['users'] ?? 0),
        ];
    }

    public static function getUserCountsByPlan(PDO $pdo): array
    {
        self::ensureSchema($pdo);

        $stmt = $pdo->query(
            "SELECT id_plan, COUNT(*) AS usuarios_total
             FROM usuarios
             WHERE estado = TRUE
               AND rol = 'user'
               AND id_plan IS NOT NULL
             GROUP BY id_plan"
        );

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $result = [];

        foreach ($rows as $row) {
            $result[(int) ($row['id_plan'] ?? 0)] = [
                'usuarios_total' => (int) ($row['usuarios_total'] ?? 0),
            ];
        }

        return $result;
    }

    public static function countAdmins(PDO $pdo, ?int $excludeId = null): int
    {
        self::ensureSchema($pdo);

        $sql = "SELECT COUNT(*)
                FROM usuarios
                WHERE rol = 'admin' AND estado = TRUE";
        $params = [];

        if ($excludeId !== null && $excludeId > 0) {
            $sql .= " AND id <> ?";
            $params[] = $excludeId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public static function canSelectAdminOnPublicRegister(PDO $pdo): bool
    {
        return self::countAdmins($pdo) === 0;
    }

    public static function validateNewUser(PDO $pdo, array $input, bool $allowAdminRole = false): array
    {
        $username        = trim((string) ($input['username'] ?? ''));
        $password        = (string) ($input['password'] ?? '');
        $confirmPassword = (string) ($input['confirm_password'] ?? '');
        $rolSolicitado   = self::normalizeRole($input['rol'] ?? 'user');
        $rolFinal        = $allowAdminRole ? $rolSolicitado : 'user';
        $pais            = self::normalizeCountry($input['pais'] ?? 'El Salvador');
        $planResolution  = self::resolvePlanInput($pdo, $input['id_plan'] ?? null, $rolFinal);
        $oldInput        = [
            'username' => $username,
            'rol'      => $rolFinal,
            'pais'     => $pais,
            'id_plan'  => $planResolution['id_plan'] ?? null,
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

        if (!($planResolution['ok'] ?? false)) {
            return [
                'ok'      => false,
                'message' => (string) ($planResolution['message'] ?? 'Selecciona un plan valido.'),
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
                'estado'   => true,
                'pais'     => $pais,
                'id_plan'  => $planResolution['id_plan'] ?? null,
            ],
            'old'  => $oldInput,
        ];
    }

    public static function validateUserUpdate(PDO $pdo, int $idUsuario, array $input, bool $allowAdminRole = true): array
    {
        $usuarioActual = self::getById($pdo, $idUsuario);
        $username      = trim((string) ($input['username'] ?? ''));
        $password      = (string) ($input['password'] ?? '');
        $confirm       = (string) ($input['confirm_password'] ?? '');
        $rolSolicitado = self::normalizeRole($input['rol'] ?? 'user');
        $rolFinal      = $allowAdminRole ? $rolSolicitado : 'user';
        $pais          = self::normalizeCountry($input['pais'] ?? ($usuarioActual['pais'] ?? 'El Salvador'));
        $planResolution = self::resolvePlanInput(
            $pdo,
            $input['id_plan'] ?? ($usuarioActual['id_plan'] ?? null),
            $rolFinal
        );
        $oldInput      = [
            'id'       => $idUsuario,
            'username' => $username,
            'rol'      => $rolFinal,
            'pais'     => $pais,
            'id_plan'  => $planResolution['id_plan'] ?? null,
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

        if (!($planResolution['ok'] ?? false)) {
            return [
                'ok'      => false,
                'message' => (string) ($planResolution['message'] ?? 'Selecciona un plan valido.'),
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
            'pais'     => $pais,
            'id_plan'  => $planResolution['id_plan'] ?? null,
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

    public static function create(PDO $pdo, array $data): int
    {
        self::ensureSchema($pdo);

        $stmt = $pdo->prepare(
            "INSERT INTO usuarios (
                username,
                nombre_completo,
                foto_perfil,
                password,
                rol,
                estado,
                pais,
                id_plan,
                ultimo_login_at,
                ultima_actividad_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            RETURNING id"
        );
        $stmt->execute([
            trim((string) $data['username']),
            self::nullableText($data['nombre_completo'] ?? null),
            self::nullableText($data['foto_perfil'] ?? null),
            (string) $data['password'],
            self::normalizeRole($data['rol'] ?? 'user'),
            dbBoolParam($data['estado'] ?? true),
            self::normalizeCountry($data['pais'] ?? 'El Salvador'),
            self::nullablePlanId($data['id_plan'] ?? null),
            null,
            null,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public static function update(PDO $pdo, int $idUsuario, array $data): bool
    {
        self::ensureSchema($pdo);
        $usuarioActual = self::getById($pdo, $idUsuario, false);

        $fields = [
            'username = ?',
            'nombre_completo = ?',
            'foto_perfil = ?',
            'rol = ?',
            'pais = ?',
            'id_plan = ?',
        ];
        $params = [
            trim((string) $data['username']),
            self::nullableText($data['nombre_completo'] ?? ($usuarioActual['nombre_completo'] ?? null)),
            self::nullableText($data['foto_perfil'] ?? ($usuarioActual['foto_perfil'] ?? null)),
            self::normalizeRole($data['rol'] ?? 'user'),
            self::normalizeCountry($data['pais'] ?? ($usuarioActual['pais'] ?? 'El Salvador')),
            self::nullablePlanId($data['id_plan'] ?? ($usuarioActual['id_plan'] ?? null)),
        ];

        if (isset($data['password']) && (string) $data['password'] !== '') {
            $fields[] = 'password = ?';
            $params[] = (string) $data['password'];
        }

        $params[] = $idUsuario;

        $stmt = $pdo->prepare(
            "UPDATE usuarios
             SET " . implode(', ', $fields) . "
             WHERE id = ? AND estado = TRUE"
        );

        return $stmt->execute($params);
    }

    public static function deactivate(PDO $pdo, int $idUsuario): bool
    {
        self::ensureSchema($pdo);

        $stmt = $pdo->prepare(
            "UPDATE usuarios
             SET estado = FALSE
             WHERE id = ? AND estado = TRUE"
        );
        $stmt->execute([$idUsuario]);

        return $stmt->rowCount() > 0;
    }

    public static function normalizeRole($rol, string $fallback = 'user'): string
    {
        $rol = strtolower(trim((string) $rol));

        if (in_array($rol, self::ROLES, true)) {
            return $rol;
        }

        return in_array($fallback, self::ROLES, true) ? $fallback : 'user';
    }

    public static function updateOwnProfile(PDO $pdo, int $idUsuario, array $data): bool
    {
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
             WHERE id = ? AND estado = TRUE"
        );

        return $stmt->execute($params);
    }

    public static function markLogin(PDO $pdo, int $idUsuario): void
    {
        self::ensureSchema($pdo);

        if ($idUsuario <= 0) {
            return;
        }

        $stmt = $pdo->prepare(
            "UPDATE usuarios
             SET ultimo_login_at = CURRENT_TIMESTAMP,
                 ultima_actividad_at = CURRENT_TIMESTAMP
             WHERE id = ?"
        );
        $stmt->execute([$idUsuario]);
    }

    public static function markActivity(PDO $pdo, int $idUsuario): void
    {
        self::ensureSchema($pdo);

        if ($idUsuario <= 0) {
            return;
        }

        $stmt = $pdo->prepare(
            "UPDATE usuarios
             SET ultima_actividad_at = CURRENT_TIMESTAMP
             WHERE id = ?"
        );
        $stmt->execute([$idUsuario]);
    }

    private static function usernameExists(PDO $pdo, string $username, ?int $excludeId = null): bool
    {
        self::ensureSchema($pdo);

        $sql = "SELECT id
                FROM usuarios
                WHERE LOWER(username) = LOWER(?) AND estado = TRUE";
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

    private static function validateUsername(string $username): ?string
    {
        if ($username === '') {
            return 'Debes escribir un nombre de usuario.';
        }

        if (preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username) !== 1) {
            return 'Usa de 3 a 50 caracteres: letras, numeros, punto, guion o guion bajo.';
        }

        return null;
    }

    private static function validatePasswordPair(string $password, string $confirmPassword, bool $allowEmpty): ?string
    {
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

    private static function nullableText($value): ?string
    {
        $value = trim((string) $value);
        return $value !== '' ? $value : null;
    }

    private static function nullablePlanId($value): ?int
    {
        $value = (int) $value;
        return $value > 0 ? $value : null;
    }

    private static function normalizeCountry($pais): string
    {
        return normalizeCountryValue($pais, 'El Salvador');
    }

    private static function resolvePlanInput(PDO $pdo, $idPlan, string $rol): array
    {
        if ($rol === 'admin') {
            return [
                'ok' => true,
                'id_plan' => null,
            ];
        }

        $idPlan = (int) $idPlan;
        if ($idPlan > 0) {
            $plan = PlanModel::getById($pdo, $idPlan);
            if ($plan === null || !dbBoolValue($plan['activo'] ?? true)) {
                return [
                    'ok' => false,
                    'message' => 'Selecciona un plan valido para el usuario.',
                    'id_plan' => null,
                ];
            }

            return [
                'ok' => true,
                'id_plan' => (int) ($plan['id_plan'] ?? 0),
            ];
        }

        $defaultPlan = PlanModel::getBySlug($pdo, 'free');
        if ($defaultPlan === null) {
            return [
                'ok' => false,
                'message' => 'No hay un plan base disponible. Revisa el catalogo de membresias.',
                'id_plan' => null,
            ];
        }

        return [
            'ok' => true,
            'id_plan' => (int) ($defaultPlan['id_plan'] ?? 0),
        ];
    }

    private static function baseSelectSql(bool $includePassword = true): string
    {
        $passwordColumn = $includePassword ? "u.password,\n" : '';

        return "SELECT
                    u.id,
                    u.username,
                    u.nombre_completo,
                    u.foto_perfil,
                    " . $passwordColumn . "
                    u.rol,
                    CASE WHEN u.estado THEN 1 ELSE 0 END AS estado,
                    u.created_at,
                    COALESCE(NULLIF(TRIM(u.pais), ''), 'El Salvador') AS pais,
                    u.id_plan,
                    p.slug AS plan_slug,
                    p.nombre AS plan_nombre,
                    p.precio AS plan_precio,
                    p.periodo AS plan_periodo,
                    u.ultimo_login_at,
                    u.ultima_actividad_at
                FROM usuarios u
                LEFT JOIN planes p ON p.id_plan = u.id_plan";
    }
}
