<?php
$userName = trim((string) (($user['nombre_completo'] ?? '') !== '' ? $user['nombre_completo'] : ($user['username'] ?? '')));
$planName = (string) ($plan['nombre'] ?? 'Plan');
$amountPaid = trim((string) ($payment['amount_label'] ?? PlanModel::formatPriceLabel($plan)));
$reference = trim((string) ($payment['reference'] ?? $payment['invoice_id'] ?? $payment['session_id'] ?? ''));
$paymentDate = trim((string) ($payment['date_label'] ?? ''));
$invoiceUrl = trim((string) ($billing_url ?? $payment['invoice_url'] ?? ''));

$emailTitle = 'Tu comprobante de compra en Zentra';
$emailPreheader = 'Adjuntamos el resumen de tu compra y el enlace al recibo.';
$primaryButtonUrl = $invoiceUrl !== '' ? $invoiceUrl : ($payment['portal_url'] ?? '');
$primaryButtonLabel = $invoiceUrl !== '' ? 'Abrir recibo' : 'Ver detalle';
$secondaryNote = 'Si necesitas soporte con tu cobro, responde a este correo o contactanos desde el sitio.';
$referenceLine = $reference !== '' ? '<p style="margin:10px 0 0;color:#334155;font-size:14px;line-height:1.7;"><strong>Referencia:</strong> ' . htmlspecialchars($reference) . '</p>' : '';
$dateLine = $paymentDate !== '' ? '<p style="margin:10px 0 0;color:#334155;font-size:14px;line-height:1.7;"><strong>Fecha:</strong> ' . htmlspecialchars($paymentDate) . '</p>' : '';

$emailBodyHtml = '
  <h1 style="margin:0 0 14px;font-size:28px;line-height:1.2;color:#0f172a;">Tu comprobante de compra</h1>
  <p style="margin:0 0 18px;color:#475569;font-size:15px;line-height:1.75;">Hola ' . htmlspecialchars($userName) . ', aqui tienes el resumen de tu cobro en Zentra.</p>
  <div style="padding:18px;border:1px solid #dbe3ef;border-radius:16px;background:#ffffff;">
    <p style="margin:0;color:#0f172a;font-size:16px;line-height:1.7;"><strong>Plan:</strong> ' . htmlspecialchars($planName) . '</p>
    <p style="margin:10px 0 0;color:#0f172a;font-size:16px;line-height:1.7;"><strong>Total:</strong> ' . htmlspecialchars($amountPaid) . '</p>
    ' . $dateLine . '
    ' . $referenceLine . '
  </div>';

require __DIR__ . '/_layout.php';
