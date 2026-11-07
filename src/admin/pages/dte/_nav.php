<?php
declare(strict_types=1);

$dteTabs = [
    'dte' => 'Uso',
    'dte.documents' => 'Documentos',
    'dte.books' => 'Libros',
    'dte.quotas' => 'Cuotas',
];
?>
<div class="mb-4">
  <ul class="nav nav-tabs">
    <?php foreach ($dteTabs as $tabKey => $tabLabel): ?>
    <li class="nav-item">
      <a class="nav-link <?php echo ($routeKey ?? 'dte') === $tabKey ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($adminBaseUrl . '?page=' . $tabKey); ?>">
        <?php echo htmlspecialchars($tabLabel); ?>
      </a>
    </li>
    <?php endforeach; ?>
  </ul>
</div>
