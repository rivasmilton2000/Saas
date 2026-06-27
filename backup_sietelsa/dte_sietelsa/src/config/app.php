<?php

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 2));
}

if (!defined('APP_SLUG')) {
    define('APP_SLUG', 'admin/dte');
}

if (!defined('APP_BASE_URL')) {
    $configuredBase = getenv('DTE_BASE_URL');
    if (!is_string($configuredBase) || trim($configuredBase) === '') {
        $configuredBase = '/' . APP_SLUG;
    }

    $configuredBase = '/' . ltrim(trim($configuredBase), '/');
    define('APP_BASE_URL', rtrim($configuredBase, '/'));
}

if (!defined('APP_NAME')) {
    define('APP_NAME', 'DTE Sietelsa');
}

if (!defined('APP_DB_NAME')) {
    define('APP_DB_NAME', getenv('DB_NAME') ?: 'sietelsa');
}

if (!function_exists('app_base_url')) {
    function app_base_url(): string
    {
        return rtrim(APP_BASE_URL, '/');
    }
}

if (!function_exists('app_url')) {
    function app_url(string $path = ''): string
    {
        $path = ltrim($path, '/');

        if ($path === '') {
            return app_base_url();
        }

        return app_base_url() . '/' . $path;
    }
}

if (!function_exists('app_name')) {
    function app_name(): string
    {
        return APP_NAME;
    }
}

if (!function_exists('app_db_name')) {
    function app_db_name(): string
    {
        return APP_DB_NAME;
    }
}

if (!function_exists('redirect_to')) {
    function redirect_to(string $path): void
    {
        $path = trim($path);
        if ($path === '') {
            header('Location: ' . app_base_url() . '/');
            exit;
        }

        if ($path[0] === '/') {
            header('Location: ' . $path);
            exit;
        }

        header('Location: ' . app_url($path));
        exit;
    }
}
