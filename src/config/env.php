<?php

require_once __DIR__ . '/../../vendor/autoload.php';

$workspaceRoot = dirname(__DIR__, 2);
if (class_exists('Dotenv\\Dotenv') && file_exists($workspaceRoot . '/.env')) {
    Dotenv\Dotenv::createImmutable($workspaceRoot)->safeLoad();
}
