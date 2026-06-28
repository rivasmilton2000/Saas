<?php

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

if (!defined('APP_PARENT_ROOT')) {
    define('APP_PARENT_ROOT', dirname(APP_ROOT));
}

if (!defined('APP_NAME')) {
    $configuredName = getenv('DTE_APP_NAME');
    define('APP_NAME', is_string($configuredName) && trim($configuredName) !== '' ? trim($configuredName) : 'DTE');
}

if (!defined('APP_BASE_URL')) {
    $configuredBase = getenv('DTE_MODULE_BASE_URL');
    if (!is_string($configuredBase) || trim($configuredBase) === '') {
        $configuredBase = getenv('DTE_BASE_URL');
    }

    if (!is_string($configuredBase) || trim($configuredBase) === '') {
        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $marker = '/src/dte/';
        $position = stripos($scriptName, $marker);

        if ($position !== false) {
            $configuredBase = substr($scriptName, 0, $position + strlen(rtrim($marker, '/')));
        } else {
            $configuredBase = '/Saas/src/dte';
        }
    }

    $configuredBase = '/' . ltrim(trim((string) $configuredBase), '/');
    define('APP_BASE_URL', rtrim($configuredBase, '/'));
}

if (!defined('APP_PARENT_BASE_URL')) {
    $parentBase = str_replace('\\', '/', dirname(APP_BASE_URL));
    if ($parentBase === '.' || $parentBase === '') {
        $parentBase = '/';
    }

    define('APP_PARENT_BASE_URL', rtrim($parentBase, '/'));
}

if (!function_exists('app_base_url')) {
    function app_base_url(): string
    {
        return APP_BASE_URL;
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

if (!function_exists('parent_app_base_url')) {
    function parent_app_base_url(): string
    {
        return APP_PARENT_BASE_URL;
    }
}

if (!function_exists('parent_app_url')) {
    function parent_app_url(string $path = ''): string
    {
        $path = ltrim($path, '/');

        if ($path === '') {
            return parent_app_base_url();
        }

        return parent_app_base_url() . '/' . $path;
    }
}

if (!function_exists('redirect_to')) {
    function redirect_to(string $path = ''): void
    {
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

if (!function_exists('redirect_to_parent')) {
    function redirect_to_parent(string $path = ''): void
    {
        if ($path === '') {
            header('Location: ' . parent_app_base_url() . '/');
            exit;
        }

        if ($path[0] === '/') {
            header('Location: ' . $path);
            exit;
        }

        header('Location: ' . parent_app_url($path));
        exit;
    }
}
