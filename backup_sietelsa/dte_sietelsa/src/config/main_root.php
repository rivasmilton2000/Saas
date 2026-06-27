<?php
declare(strict_types=1);

if (!function_exists('dte_main_system_root')) {
    function dte_main_system_root(): string
    {
        static $resolvedRoot = null;
        if (is_string($resolvedRoot) && $resolvedRoot !== '') {
            return $resolvedRoot;
        }

        $baseDir = __DIR__;
        $candidates = [];

        for ($level = 2; $level <= 7; $level++) {
            $candidate = dirname($baseDir, $level);
            if ($candidate !== '' && $candidate !== '/' && $candidate !== '.') {
                $candidates[] = $candidate;
            }

            $realCandidate = realpath($candidate);
            if (is_string($realCandidate) && $realCandidate !== '' && $realCandidate !== '/') {
                $candidates[] = $realCandidate;
            }
        }

        $documentRoot = trim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        if ($documentRoot !== '') {
            $candidates[] = rtrim($documentRoot, '/');
        }

        $candidates = array_values(array_unique($candidates));

        foreach ($candidates as $candidate) {
            if (
                is_file($candidate . '/include/auth.php') &&
                is_file($candidate . '/include/conexion.php')
            ) {
                $resolvedRoot = $candidate;
                return $resolvedRoot;
            }
        }

        throw new RuntimeException('No se pudo resolver la raiz del sistema principal.');
    }
}
