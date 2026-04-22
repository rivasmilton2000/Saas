<?php
require_once __DIR__ . '/../config/modulos.php';
require_once __DIR__ . '/../models/CentroMandoModel.php';

class CentroMandoService {

    public static function build(array $empresas, array $libros, array $cuota, PDO $pdo, int $idUsuario): array {
        $zonaHoraria = new DateTimeZone('America/El_Salvador');
        $ahoraLocal  = new DateTimeImmutable('now', $zonaHoraria);
        $mesActual   = (int) $ahoraLocal->format('n');
        $anioActual  = (int) $ahoraLocal->format('Y');
        $periodo    = self::formatPeriodo($mesActual, $anioActual);
        $catalogo   = self::getCatalogo();
        $configMap  = CentroMandoModel::getConfigByUsuario($pdo, $idUsuario);
        $avanceMap  = CentroMandoModel::getProgressByUsuario($pdo, $idUsuario, $mesActual, $anioActual);
        $notasMap   = CentroMandoModel::getNotesByUsuario($pdo, $idUsuario, $mesActual, $anioActual);
        $ocultarBienvenida = CentroMandoModel::isWelcomeHidden($pdo, $idUsuario);

        $librosActuales = [];
        foreach ($libros as $libro) {
            $empresaId = (int) ($libro['id_empresa'] ?? 0);
            $tipo      = (string) ($libro['tipo'] ?? '');
            $mes       = (int) ($libro['mes'] ?? 0);
            $anio      = (int) ($libro['anio'] ?? 0);

            if ($empresaId <= 0 || $tipo === '' || $mes !== $mesActual || $anio !== $anioActual) {
                continue;
            }

            if (!isset($librosActuales[$empresaId])) {
                $librosActuales[$empresaId] = [];
            }

            $librosActuales[$empresaId][$tipo] = $libro;
        }

        $empresasData        = [];
        $empresasAlDia       = 0;
        $empresasConfiguradas = 0;
        $primeraSinConfig    = null;

        foreach ($empresas as $empresa) {
            $empresaId     = (int) ($empresa['id'] ?? 0);
            $selectedMap   = $configMap[$empresaId] ?? [];
            $selectedKeys  = array_keys($selectedMap);
            $configurada   = $selectedKeys !== [];
            $notaMensual   = trim((string) ($notasMap[$empresaId] ?? ''));
            $groupRows     = [];
            $seleccionadas = 0;
            $completadas   = 0;

            if ($configurada) {
                $empresasConfiguradas++;
            } elseif ($primeraSinConfig === null) {
                $primeraSinConfig = [
                    'id'     => $empresaId,
                    'nombre' => (string) ($empresa['nombre'] ?? 'Empresa'),
                ];
            }

            foreach ($catalogo as $categoriaKey => $categoria) {
                $itemsRows           = [];
                $categoriaTotal      = 0;
                $categoriaCompletada = 0;

                foreach ($categoria['items'] as $itemKey => $item) {
                    if (!$configurada || !isset($selectedMap[$itemKey])) {
                        continue;
                    }

                    $categoriaTotal++;
                    $seleccionadas++;

                    $completado = false;
                    $ruta       = null;

                    if (($item['kind'] ?? '') === 'libro') {
                        $tipoLibro  = (string) ($item['libro_tipo'] ?? '');
                        $completado = isset($librosActuales[$empresaId][$tipoLibro]);
                        $modulo     = getLibroModule($tipoLibro);
                        $rutaModulo = trim((string) ($modulo['ruta'] ?? ''));
                        if ($rutaModulo !== '') {
                            $ruta = '/Saas/src/index.php?activate_company=' . $empresaId . '&redirect_to=' . rawurlencode($rutaModulo);
                        }
                    } else {
                        $completado = !empty($avanceMap[$empresaId][$itemKey]);
                    }

                    if ($completado) {
                        $categoriaCompletada++;
                        $completadas++;
                    }

                    $itemsRows[] = [
                        'key'         => $itemKey,
                        'label'       => (string) $item['label'],
                        'description' => (string) ($item['description'] ?? ''),
                        'kind'        => (string) ($item['kind'] ?? 'manual'),
                        'completado'  => $completado,
                        'ruta'        => $ruta,
                        'icon'        => (string) ($item['icon'] ?? 'mdi mdi-check'),
                    ];
                }

                if ($itemsRows === []) {
                    continue;
                }

                $groupRows[] = [
                    'key'        => $categoriaKey,
                    'label'      => (string) $categoria['label'],
                    'icon'       => (string) ($categoria['icon'] ?? 'mdi mdi-view-grid-outline'),
                    'color'      => (string) ($categoria['color'] ?? '#4b49ac'),
                    'total'      => $categoriaTotal,
                    'completado' => $categoriaCompletada,
                    'items'      => $itemsRows,
                ];
            }

            $status = self::resolveStatus($configurada, $seleccionadas, $completadas);
            if ($status['key'] === 'al_dia') {
                $empresasAlDia++;
            }

            $empresasData[] = [
                'id'                => $empresaId,
                'nombre'            => (string) ($empresa['nombre'] ?? 'Empresa'),
                'iniciales'         => (string) (($empresa['iniciales'] ?? '') !== '' ? $empresa['iniciales'] : 'EMP'),
                'color_emblema'     => (string) ($empresa['color_emblema'] ?? '#4b49ac'),
                'configurada'       => $configurada,
                'selected_keys'     => $configurada ? $selectedKeys : self::getDefaultSelectedKeys(),
                'selected_count'    => $seleccionadas,
                'completed_count'   => $completadas,
                'progress_percent'  => $seleccionadas > 0 ? (int) round(($completadas / $seleccionadas) * 100) : 0,
                'status'            => $status,
                'groups'            => $groupRows,
                'nota_mensual'      => $notaMensual,
                'hint'              => $configurada
                    ? 'Marca el avance manual y abre los libros desde aqui.'
                    : 'Haz clic en configurar para elegir que quieres controlar este mes.',
            ];
        }

        $totalEmpresas = count($empresas);
        return [
            'periodo' => [
                'mes'            => $mesActual,
                'anio'           => $anioActual,
                'label'          => $periodo,
                'dias_restantes' => max(0, ((int) $ahoraLocal->format('t')) - ((int) $ahoraLocal->format('j'))),
            ],
            'resumen' => [
                'empresas_total'        => $totalEmpresas,
                'empresas_configuradas' => $empresasConfiguradas,
                'empresas_al_dia'       => $empresasAlDia,
            ],
            'empresas' => $empresasData,
            'catalogo' => $catalogo,
            'bienvenida' => [
                'mostrar'               => !$ocultarBienvenida && $totalEmpresas > 0,
                'primera_sin_config'    => $primeraSinConfig,
            ],
        ];
    }

    public static function saveConfig(PDO $pdo, int $idEmpresa, int $idUsuario, array $itemKeys): array {
        $catalogo = self::flattenCatalogo();
        $validos  = [];

        foreach ($itemKeys as $itemKey) {
            $itemKey = trim((string) $itemKey);
            if ($itemKey === '' || !isset($catalogo[$itemKey])) {
                continue;
            }

            $validos[$itemKey] = true;
        }

        $keys = array_keys($validos);
        if ($keys === []) {
            return [
                'success' => false,
                'message' => 'Selecciona al menos un control para esta empresa.',
                'data'    => [
                    'selected_keys' => self::getDefaultSelectedKeys(),
                ],
            ];
        }

        CentroMandoModel::replaceConfig($pdo, $idEmpresa, $idUsuario, $keys);

        return [
            'success' => true,
            'message' => 'Checklist guardado correctamente.',
            'data'    => [
                'selected_keys' => $keys,
            ],
        ];
    }

    public static function toggleManualItem(
        PDO $pdo,
        int $idEmpresa,
        int $idUsuario,
        string $itemKey,
        bool $completado,
        int $mes,
        int $anio
    ): array {
        $catalogo = self::flattenCatalogo();
        $item     = $catalogo[$itemKey] ?? null;

        if ($item === null || ($item['kind'] ?? '') !== 'manual') {
            return [
                'success' => false,
                'message' => 'Ese control no se puede actualizar manualmente.',
                'data'    => null,
            ];
        }

        CentroMandoModel::setProgress($pdo, $idEmpresa, $idUsuario, $mes, $anio, $itemKey, $completado);

        return [
            'success' => true,
            'message' => $completado
                ? 'Control marcado como completado.'
                : 'Control marcado como pendiente.',
            'data'    => null,
        ];
    }

    public static function saveNote(
        PDO $pdo,
        int $idEmpresa,
        int $idUsuario,
        string $nota,
        int $mes,
        int $anio
    ): array {
        CentroMandoModel::saveNote($pdo, $idEmpresa, $idUsuario, $mes, $anio, $nota);

        return [
            'success' => true,
            'message' => trim($nota) === ''
                ? 'Nota del mes eliminada.'
                : 'Nota del mes guardada.',
            'data'    => null,
        ];
    }

    public static function hideWelcome(PDO $pdo, int $idUsuario): void {
        CentroMandoModel::setWelcomeHidden($pdo, $idUsuario, true);
    }

    public static function getDefaultSelectedKeys(): array {
        return [
            'libro_compras',
            'libro_ventas_ccf',
            'libro_ventas_cf',
            'declaracion_iva',
            'pago_cuenta',
            'enviado_cliente',
            'pago_confirmado',
        ];
    }

    public static function getCatalogo(): array {
        return [
            'libros_iva' => [
                'label' => 'Libros IVA',
                'icon'  => 'mdi mdi-book-open-outline',
                'color' => '#4b49ac',
                'items' => [
                    'libro_compras' => [
                        'label'       => 'Compras',
                        'description' => 'Se completa cuando el libro del periodo existe.',
                        'kind'        => 'libro',
                        'libro_tipo'  => 'compras',
                        'icon'        => 'mdi mdi-cart-outline',
                    ],
                    'libro_ventas_ccf' => [
                        'label'       => 'Ventas CCF',
                        'description' => 'Se completa cuando existe el libro de contribuyentes.',
                        'kind'        => 'libro',
                        'libro_tipo'  => 'ventas_contribuyente',
                        'icon'        => 'mdi mdi-briefcase-outline',
                    ],
                    'libro_ventas_cf' => [
                        'label'       => 'Ventas CF',
                        'description' => 'Se completa cuando existe el libro consumidor final.',
                        'kind'        => 'libro',
                        'libro_tipo'  => 'ventas_consumidor',
                        'icon'        => 'mdi mdi-receipt-text-outline',
                    ],
                    'libro_retencion_iva' => [
                        'label'       => 'Ret. 1%',
                        'description' => 'Se completa cuando existe el libro de retencion del periodo.',
                        'kind'        => 'libro',
                        'libro_tipo'  => 'retencion_iva',
                        'icon'        => 'mdi mdi-shield-check-outline',
                    ],
                ],
            ],
            'declaraciones' => [
                'label' => 'Declaraciones',
                'icon'  => 'mdi mdi-file-document-edit-outline',
                'color' => '#3b82f6',
                'items' => [
                    'declaracion_iva' => [
                        'label'       => 'IVA',
                        'description' => 'Marca cuando la declaracion mensual ya este trabajada.',
                        'kind'        => 'manual',
                        'icon'        => 'mdi mdi-file-check-outline',
                    ],
                    'pago_cuenta' => [
                        'label'       => 'Pago a cuenta',
                        'description' => 'Marca cuando el pago a cuenta quede listo.',
                        'kind'        => 'manual',
                        'icon'        => 'mdi mdi-cash-fast',
                    ],
                ],
            ],
            'anuales' => [
                'label' => 'Anuales',
                'icon'  => 'mdi mdi-calendar-star',
                'color' => '#8b5cf6',
                'items' => [
                    'renta' => [
                        'label'       => 'Renta',
                        'description' => 'Seguimiento anual de renta.',
                        'kind'        => 'manual',
                        'icon'        => 'mdi mdi-finance',
                    ],
                    'f930' => [
                        'label'       => 'F-930',
                        'description' => 'Controla la gestion del formulario F-930.',
                        'kind'        => 'manual',
                        'icon'        => 'mdi mdi-file-chart-outline',
                    ],
                    'f915' => [
                        'label'       => 'F-915',
                        'description' => 'Controla la gestion del formulario F-915.',
                        'kind'        => 'manual',
                        'icon'        => 'mdi mdi-file-chart-outline',
                    ],
                    'f910' => [
                        'label'       => 'F-910',
                        'description' => 'Controla la gestion del formulario F-910.',
                        'kind'        => 'manual',
                        'icon'        => 'mdi mdi-file-chart-outline',
                    ],
                    'actualizacion_direccion' => [
                        'label'       => 'Act. direccion',
                        'description' => 'Marca cuando la actualizacion quede resuelta.',
                        'kind'        => 'manual',
                        'icon'        => 'mdi mdi-map-marker-edit-outline',
                    ],
                ],
            ],
            'cierre_periodo' => [
                'label' => 'Cierre del periodo',
                'icon'  => 'mdi mdi-flag-checkered',
                'color' => '#f59e0b',
                'items' => [
                    'enviado_cliente' => [
                        'label'       => 'Enviado al cliente',
                        'description' => 'Marca cuando el cierre del mes fue enviado.',
                        'kind'        => 'manual',
                        'icon'        => 'mdi mdi-send-outline',
                    ],
                    'pago_confirmado' => [
                        'label'       => 'Pago confirmado',
                        'description' => 'Marca cuando el pago del mes ya fue confirmado.',
                        'kind'        => 'manual',
                        'icon'        => 'mdi mdi-cash-check',
                    ],
                ],
            ],
        ];
    }

    private static function flattenCatalogo(): array {
        $flat = [];

        foreach (self::getCatalogo() as $categoria) {
            foreach ($categoria['items'] as $itemKey => $item) {
                $flat[$itemKey] = $item;
            }
        }

        return $flat;
    }

    private static function resolveStatus(bool $configurada, int $total, int $completadas): array {
        if (!$configurada || $total === 0) {
            return [
                'key'   => 'sin_configurar',
                'label' => 'Configurar',
                'class' => 'secondary',
            ];
        }

        if ($completadas <= 0) {
            return [
                'key'   => 'sin_iniciar',
                'label' => 'Sin iniciar',
                'class' => 'danger',
            ];
        }

        if ($completadas >= $total) {
            return [
                'key'   => 'al_dia',
                'label' => 'Al dia',
                'class' => 'success',
            ];
        }

        return [
            'key'   => 'en_progreso',
            'label' => 'En progreso',
            'class' => 'warning',
        ];
    }

    private static function formatPeriodo(int $mes, int $anio): string {
        $meses = [
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre',
        ];

        return ($meses[$mes] ?? (string) $mes) . ' ' . $anio;
    }
}
