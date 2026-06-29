<?php
require_once __DIR__ . '/env.php';

if (!function_exists('saasBasePath')) {
    function saasBasePath(): string
    {
        $configured = trim((string) envValue('APP_PATH', ''));
        if ($configured !== '') {
            return '/' . trim($configured, '/');
        }

        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        foreach (['/src/', '/public/'] as $marker) {
            $position = strpos($scriptName, $marker);
            if ($position !== false) {
                $prefix = rtrim(substr($scriptName, 0, $position), '/');
                return $prefix !== '' ? $prefix : '';
            }
        }

        $fallback = basename(dirname(__DIR__, 2));
        return $fallback !== '' ? '/' . $fallback : '/Saas';
    }
}

if (!function_exists('saasBaseUrl')) {
    function saasBaseUrl(): string
    {
        $configured = trim((string) envValue('APP_URL', ''));
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        $scheme = ($https !== '' && $https !== 'off') ? 'https' : 'http';
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));

        return $scheme . '://' . $host . saasBasePath();
    }
}

if (!function_exists('saasUrl')) {
    function saasUrl(string $path = ''): string
    {
        $path = ltrim($path, '/');
        return $path === ''
            ? saasBaseUrl()
            : saasBaseUrl() . '/' . $path;
    }
}

if (!function_exists('saasPublicUrl')) {
    function saasPublicUrl(string $path = ''): string
    {
        $path = ltrim($path, '/');
        $relative = 'public' . ($path !== '' ? '/' . $path : '');
        return saasUrl($relative);
    }
}
