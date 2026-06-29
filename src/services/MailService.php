<?php
require_once __DIR__ . '/../config/mail.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../models/EmailLogModel.php';
require_once __DIR__ . '/../models/PlanModel.php';

use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;

class MailService
{
    public static function sendWelcomeFree(PDO $pdo, array $user, array $plan): bool
    {
        return self::sendTemplate($pdo, [
            'user_id' => (int) ($user['id'] ?? 0),
            'email' => (string) ($user['email'] ?? ''),
            'name' => (string) ($user['nombre_completo'] ?? $user['username'] ?? 'Cliente'),
            'type' => 'welcome_free',
            'subject' => 'Bienvenido a Zentra',
            'template' => __DIR__ . '/../emails/templates/welcome_free.php',
            'data' => [
                'user' => $user,
                'plan' => $plan,
                'plans_url' => saasPublicUrl('pricing.php'),
                'login_url' => saasUrl('src/pages/samples/login.php'),
            ],
        ]);
    }

    public static function sendWelcomePaid(PDO $pdo, array $user, array $plan, array $payment): bool
    {
        return self::sendTemplate($pdo, [
            'user_id' => (int) ($user['id'] ?? 0),
            'email' => (string) ($user['email'] ?? ''),
            'name' => (string) ($user['nombre_completo'] ?? $user['username'] ?? 'Cliente'),
            'type' => 'welcome_paid',
            'subject' => 'Bienvenido a Zentra - tu plan esta activo',
            'template' => __DIR__ . '/../emails/templates/welcome_paid.php',
            'data' => [
                'user' => $user,
                'plan' => $plan,
                'payment' => $payment,
                'login_url' => saasUrl('src/pages/samples/login.php'),
            ],
        ]);
    }

    public static function sendPaymentVoucher(PDO $pdo, array $user, array $plan, array $payment): bool
    {
        $referenceKey = trim((string) ($payment['invoice_id'] ?? $payment['reference'] ?? $payment['session_id'] ?? ''));

        return self::sendTemplate($pdo, [
            'user_id' => (int) ($user['id'] ?? 0),
            'email' => (string) ($user['email'] ?? ''),
            'name' => (string) ($user['nombre_completo'] ?? $user['username'] ?? 'Cliente'),
            'type' => 'payment_voucher',
            'reference_key' => $referenceKey !== '' ? $referenceKey : null,
            'subject' => 'Tu comprobante de compra en Zentra',
            'template' => __DIR__ . '/../emails/templates/payment_voucher.php',
            'data' => [
                'user' => $user,
                'plan' => $plan,
                'payment' => $payment,
                'billing_url' => (string) ($payment['invoice_url'] ?? ''),
            ],
        ]);
    }

    public static function isConfigured(): bool
    {
        return mailIsConfigured();
    }

    private static function sendTemplate(PDO $pdo, array $payload): bool
    {
        $userId = (int) ($payload['user_id'] ?? 0);
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $type = trim((string) ($payload['type'] ?? 'general'));
        $referenceKey = self::nullableText($payload['reference_key'] ?? null);

        if ($email === '') {
            return false;
        }

        if ($userId > 0 && EmailLogModel::hasSuccessfulDelivery($pdo, $userId, $type, $referenceKey)) {
            return true;
        }

        if (!self::isConfigured()) {
            EmailLogModel::create($pdo, [
                'user_id' => $userId > 0 ? $userId : null,
                'email' => $email,
                'type' => $type,
                'reference_key' => $referenceKey,
                'status' => 'skipped',
                'error_message' => 'MAIL_* no esta configurado en este entorno.',
            ]);
            return false;
        }

        $html = self::renderTemplate((string) ($payload['template'] ?? ''), (array) ($payload['data'] ?? []));
        if ($html === '') {
            EmailLogModel::create($pdo, [
                'user_id' => $userId > 0 ? $userId : null,
                'email' => $email,
                'type' => $type,
                'reference_key' => $referenceKey,
                'status' => 'error',
                'error_message' => 'No se pudo renderizar la plantilla de correo.',
            ]);
            return false;
        }

        try {
            $mailer = self::buildMailer();
            $mailer->setFrom(mailConfig()['from'], mailConfig()['from_name']);
            $mailer->addAddress($email, (string) ($payload['name'] ?? 'Cliente'));
            $mailer->isHTML(true);
            $mailer->Subject = (string) ($payload['subject'] ?? 'Zentra');
            $mailer->Body = $html;
            $mailer->AltBody = trim(preg_replace('/\s+/', ' ', strip_tags($html)));
            $mailer->send();

            EmailLogModel::create($pdo, [
                'user_id' => $userId > 0 ? $userId : null,
                'email' => $email,
                'type' => $type,
                'reference_key' => $referenceKey,
                'status' => 'sent',
                'error_message' => null,
            ]);

            return true;
        } catch (MailerException $exception) {
            EmailLogModel::create($pdo, [
                'user_id' => $userId > 0 ? $userId : null,
                'email' => $email,
                'type' => $type,
                'reference_key' => $referenceKey,
                'status' => 'error',
                'error_message' => $exception->getMessage(),
            ]);
            return false;
        }
    }

    private static function buildMailer(): PHPMailer
    {
        $config = mailConfig();

        $mailer = new PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = $config['host'];
        $mailer->SMTPAuth = $config['username'] !== '';
        $mailer->Username = $config['username'];
        $mailer->Password = $config['password'];
        $mailer->Port = $config['port'] > 0 ? $config['port'] : 587;
        $mailer->CharSet = 'UTF-8';

        if ($config['encryption'] === 'ssl') {
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($config['encryption'] !== '' && $config['encryption'] !== 'none') {
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        return $mailer;
    }

    private static function renderTemplate(string $templatePath, array $data): string
    {
        if ($templatePath === '' || !is_file($templatePath)) {
            return '';
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $templatePath;
        return (string) ob_get_clean();
    }

    private static function nullableText($value): ?string
    {
        $value = trim((string) $value);
        return $value !== '' ? $value : null;
    }
}
