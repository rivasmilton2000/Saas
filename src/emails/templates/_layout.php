<?php
$emailTitle = $emailTitle ?? 'Zentra';
$emailPreheader = $emailPreheader ?? '';
$emailBodyHtml = $emailBodyHtml ?? '';
$primaryButtonUrl = $primaryButtonUrl ?? '';
$primaryButtonLabel = $primaryButtonLabel ?? '';
$secondaryNote = $secondaryNote ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($emailTitle); ?></title>
</head>
<body style="margin:0;padding:0;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
  <div style="display:none;max-height:0;overflow:hidden;opacity:0;">
    <?php echo htmlspecialchars($emailPreheader); ?>
  </div>
  <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f7fb;padding:24px 12px;">
    <tr>
      <td align="center">
        <table width="620" cellpadding="0" cellspacing="0" border="0" style="width:620px;max-width:620px;background:#ffffff;border:1px solid #dbe3ef;border-radius:22px;overflow:hidden;">
          <tr>
            <td style="padding:32px 36px;background:linear-gradient(180deg,#2563eb 0%,#1d4ed8 100%);text-align:center;">
              <img src="<?php echo htmlspecialchars(saasUrl('src/assets/images/logo.svg')); ?>" alt="Zentra" style="width:140px;max-width:100%;height:auto;">
            </td>
          </tr>
          <tr>
            <td style="padding:34px 36px 18px;">
              <?php echo $emailBodyHtml; ?>
              <?php if ($primaryButtonUrl !== '' && $primaryButtonLabel !== ''): ?>
              <p style="margin:28px 0 0;">
                <a href="<?php echo htmlspecialchars($primaryButtonUrl); ?>" style="display:inline-block;padding:14px 22px;border-radius:14px;background:#2563eb;color:#ffffff;text-decoration:none;font-weight:700;">
                  <?php echo htmlspecialchars($primaryButtonLabel); ?>
                </a>
              </p>
              <?php endif; ?>
              <?php if ($secondaryNote !== ''): ?>
              <p style="margin:20px 0 0;color:#64748b;font-size:13px;line-height:1.7;">
                <?php echo htmlspecialchars($secondaryNote); ?>
              </p>
              <?php endif; ?>
            </td>
          </tr>
          <tr>
            <td style="padding:18px 36px 30px;color:#94a3b8;font-size:12px;line-height:1.7;border-top:1px solid #e2e8f0;">
              Zentra · <?php echo date('Y'); ?>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
