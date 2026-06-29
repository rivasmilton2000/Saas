<?php
require_once __DIR__ . '/app.php';

if (!function_exists('googleConfig')) {
    function googleConfig(): array
    {
        return [
            'client_id' => trim((string) envValue('GOOGLE_CLIENT_ID', '')),
            'certs_url' => 'https://www.googleapis.com/oauth2/v1/certs',
            'issuers' => [
                'accounts.google.com',
                'https://accounts.google.com',
            ],
        ];
    }
}

if (!function_exists('googleIsConfigured')) {
    function googleIsConfigured(): bool
    {
        return googleConfig()['client_id'] !== '';
    }
}

if (!function_exists('googleAuthNonce')) {
    function googleAuthNonce(bool $refresh = false): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if ($refresh || empty($_SESSION['google_auth_nonce'])) {
            $_SESSION['google_auth_nonce'] = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
        }

        return (string) $_SESSION['google_auth_nonce'];
    }
}

if (!function_exists('googlePeekAuthNonce')) {
    function googlePeekAuthNonce(): ?string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $nonce = trim((string) ($_SESSION['google_auth_nonce'] ?? ''));
        return $nonce !== '' ? $nonce : null;
    }
}

if (!function_exists('googleClearAuthNonce')) {
    function googleClearAuthNonce(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        unset($_SESSION['google_auth_nonce']);
    }
}
