<?php
$userName = trim((string) (($user['nombre_completo'] ?? '') !== '' ? $user['nombre_completo'] : ($user['username'] ?? '')));
$planName = (string) ($plan['nombre'] ?? 'Free');
$planPrice = PlanModel::formatPriceLabel($plan);
$limitCompanies = $plan['limite_empresas'] ?? 1;
$limitUsers = $plan['limite_usuarios'] ?? 1;
$limitDocs = $plan['limite_documentos'] ?? 25;

$emailTitle = 'Bienvenido a Zentra';
$emailPreheader = 'Tu cuenta ya esta activa con el plan Free.';
$primaryButtonUrl = $plans_url ?? saasPublicUrl('pricing.php');
$primaryButtonLabel = 'Ver planes';
$secondaryNote = 'Puedes entrar cuando quieras con tu cuenta actual y subir de plan cuando necesites mas capacidad.';
$emailBodyHtml = '
  <h1 style="margin:0 0 14px;font-size:28px;line-height:1.2;color:#0f172a;">Bienvenido a Zentra</h1>
  <p style="margin:0 0 18px;color:#475569;font-size:15px;line-height:1.75;">Hola ' . htmlspecialchars($userName) . ', tu cuenta ya quedo creada correctamente.</p>
  <div style="padding:18px;border:1px solid #dbe3ef;border-radius:16px;background:#f8fbff;">
    <p style="margin:0 0 10px;color:#1d4ed8;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;">Plan actual</p>
    <p style="margin:0;color:#0f172a;font-size:22px;font-weight:700;">' . htmlspecialchars($planName) . ' <span style="color:#1d4ed8;font-size:16px;">' . htmlspecialchars($planPrice) . '</span></p>
    <p style="margin:14px 0 0;color:#475569;font-size:14px;line-height:1.75;">' . htmlspecialchars((string) ($plan['descripcion'] ?? 'Ideal para empezar a explorar Zentra.')) . '</p>
  </div>
  <ul style="margin:18px 0 0;padding-left:18px;color:#334155;font-size:14px;line-height:1.8;">
    <li>' . htmlspecialchars((string) $limitCompanies) . ' empresa(s)</li>
    <li>' . htmlspecialchars((string) $limitUsers) . ' usuario(s)</li>
    <li>' . htmlspecialchars(number_format((int) $limitDocs)) . ' documentos</li>
  </ul>
  <p style="margin:18px 0 0;color:#475569;font-size:15px;line-height:1.75;">Cuando quieras mas capacidad, puedes revisar los planes pagos y activar el que mejor se adapte a tu operacion.</p>';

require __DIR__ . '/_layout.php';
