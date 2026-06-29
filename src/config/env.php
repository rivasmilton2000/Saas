<?php

require_once __DIR__ . '/../../vendor/autoload.php';

$workspaceRoot = dirname(__DIR__, 2);
if (class_exists('Dotenv\\Dotenv') && file_exists($workspaceRoot . '/.env')) {
    if (method_exists('Dotenv\\Dotenv', 'createUnsafeImmutable')) {
        Dotenv\Dotenv::createUnsafeImmutable($workspaceRoot)->safeLoad();
    } else {
        Dotenv\Dotenv::createImmutable($workspaceRoot)->safeLoad();
    }
}

if (!function_exists('envValue')) {
    /**
     * Read env vars from getenv(), $_ENV or $_SERVER so local XAMPP setups
     * behave the same even when Dotenv does not expose values through getenv().
     *
     * @param mixed $default
     * @return mixed
     */
    function envValue(string $key, $default = null)
    {
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        if (array_key_exists($key, $_SERVER)) {
            return $_SERVER[$key];
        }

        return $default;
    }
}
