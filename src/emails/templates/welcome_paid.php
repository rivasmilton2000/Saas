<?php
$userName = trim((string) (($user['nombre_completo'] ?? '') !== '' ? $user['nombre_completo'] : ($user['username'] ?? '')));
$planName = (string) ($plan['nombre'] ?? 'Plan');
$planPrice = PlanModel::formatPriceLabel($plan);
$amountPaid = trim((string) ($payment['amount_label'] ?? $planPrice));
$reference = trim((string) ($payment['reference'] ?? $payment['session_id'] ?? ''));
$paymentDate = trim((string) ($payment['date_label'] ?? ''));
$invoiceUrl = trim((string) ($payment['invoice_url'] ?? ''));

$emailTitle = 'Bienvenido a Zentra - tu plan esta activo';
$emailPreheader = 'Tu suscripcion ya fue activada en Zentra.';
$primaryButtonUrl = $login_url ?? saasUrl('src/pages/samples/login.php');
$primaryButtonLabel = 'Entrar a Zentra';
$secondaryNote = $invoiceUrl !== '' ? 'Tambien puedes consultar tu factura o recibo desde el enlace incluido en este correo.' : '';
$invoiceLine = $invoiceUrl !== '' ? '<p style="margin:14px 0 0;font-size:14px;"><a href="' . htmlspecialchars($invoiceUrl) . '" style="color:#1d4ed8;text-decoration:none;font-weight:700;">Ver factura o recibo</a></p>' : '';
$referenceLine = $reference !== '' ? '<p style="margin:10px 0 0;color:#334155;font-size:14px;line-height:1.7;"><strong>Referencia:</strong> ' . htmlspecialchars($reference) . '</p>' : '';
$dateLine = $paymentDate !== '' ? '<p style="margin:10px 0 0;color:#334155;font-size:14px;line-height:1.7;"><strong>Fecha:</strong> ' . htmlspecialchars($paymentDate) . '</p>' : '';

$emailBodyHtml = '
  <h1 style="margin:0 0 14px;font-size:28px;line-height:1.2;color:#0f172a;">Tu plan ya esta activo</h1>
  <p style="margin:0 0 18px;color:#475569;font-size:15px;line-height:1.75;">Hola ' . htmlspecialchars($userName) . ', confirmamos la activacion de tu suscripcion en Zentra.</p>
  <div style="padding:18px;border:1px solid #dbe3ef;border-radius:16px;background:#f8fbff;">
    <p style="margin:0 0 10px;color:#1d4ed8;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;">Plan contratado</p>
    <p style="margin:0;color:#0f172a;font-size:22px;font-weight:700;">' . htmlspecialchars($planName) . '</p>
    <p style="margin:10px 0 0;color:#334155;font-size:14px;line-height:1.7;"><strong>Monto pagado:</strong> ' . htmlspecialchars($amountPaid) . '</p>
    ' . $dateLine . '
    ' . $referenceLine . '
    ' . $invoiceLine . '
  </div>
  <p style="margin:18px 0 0;color:#475569;font-size:15px;line-height:1.75;">Ya puedes entrar a tu cuenta y continuar con los modulos habilitados para tu membresia.</p>';

require __DIR__ . '/_layout.php';
