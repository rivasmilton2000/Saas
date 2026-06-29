<?php
require_once __DIR__ . '/app.php';

if (!function_exists('mailConfig')) {
    function mailConfig(): array
    {
        return [
            'host' => trim((string) envValue('MAIL_HOST', '')),
            'port' => (int) envValue('MAIL_PORT', 587),
            'username' => trim((string) envValue('MAIL_USERNAME', '')),
            'password' => (string) envValue('MAIL_PASSWORD', ''),
            'from' => trim((string) envValue('MAIL_FROM', envValue('MAIL_USERNAME', ''))),
            'from_name' => trim((string) envValue('MAIL_FROM_NAME', 'Zentra')),
            'encryption' => strtolower(trim((string) envValue('MAIL_ENCRYPTION', 'tls'))),
        ];
    }
}

if (!function_exists('mailIsConfigured')) {
    function mailIsConfigured(): bool
    {
        $config = mailConfig();
        return $config['host'] !== '' && $config['from'] !== '';
    }
}
