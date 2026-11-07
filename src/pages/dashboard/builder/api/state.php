<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

try {
    $service = new WebsiteService(db());
    $draft = $service->draft((int) $currentUser['id']);
    $images = (new WebsiteImageManager(db()))->active();
    foreach ($images as &$image) {
        $image['url'] = app_url((string) $image['ruta']);
        $image['thumbnail_url'] = app_url((string) ($image['miniatura_ruta'] ?: $image['ruta']));
        $image['sources'] = WebsiteImageManager::sources((string) $image['ruta']);
    }
    unset($image);
    $logo = 'includes/img/logoSietelsa.png';
    $images[] = ['id'=>null, 'ruta'=>$logo, 'nombre_original'=>'Logo original de SIETELSA', 'url'=>app_url($logo), 'thumbnail_url'=>app_url($logo)];
    // Ofrecer también las imágenes originales en la misma biblioteca del editor.
    $knownPaths = array_column($images, 'ruta');
    $defaults = WebsiteSchema::defaults();
    foreach (WebsiteSchema::definitions() as $path => $definition) {
        if ($definition['type'] !== 'image') continue;
        foreach ([WebsiteValidator::get($defaults, $path), WebsiteValidator::get($draft['content'], $path)] as $asset) {
            if (!is_string($asset) || !str_starts_with($asset, 'src/website/assets/img/') || !is_file(SIETELSA_ROOT . '/' . $asset) || in_array($asset, $knownPaths, true)) continue;
            $images[] = ['id' => null, 'ruta' => $asset, 'nombre_original' => basename($asset), 'url' => app_url($asset), 'thumbnail_url' => app_url($asset)];
            $knownPaths[] = $asset;
        }
    }
    $assetRoot = SIETELSA_ROOT . '/src/website/assets/img';
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($assetRoot, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file->isFile() || !preg_match('/\.(?:jpe?g|png|webp|gif|bmp|avif)$/iD', $file->getFilename())) continue;
        $asset = 'src/website/assets/img/' . str_replace('\\', '/', substr($file->getPathname(), strlen($assetRoot) + 1));
        if (in_array($asset, $knownPaths, true)) continue;
        $images[] = ['id'=>null, 'ruta'=>$asset, 'nombre_original'=>$file->getFilename(), 'url'=>app_url($asset), 'thumbnail_url'=>app_url($asset)];
        $knownPaths[] = $asset;
    }
    builder_api_response([
        'ok' => true,
        'draft' => [
            'id' => $draft['id'],
            'revision' => $draft['revision'],
            'values' => WebsiteValidator::flatten($draft['content']),
            'updated_at' => $draft['updated_at'],
            'editor' => $draft['editor'],
        ],
        'definitions' => WebsiteSchema::definitions(),
        'history' => $service->history(),
        'images' => $images,
    ]);
} catch (Throwable $exception) {
    builder_api_error($exception);
}
