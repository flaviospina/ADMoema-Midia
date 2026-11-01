<?php
// public/api/foto.php — entrega a foto (somente com login)
//   v=thumb    miniatura 600 px
//   v=view     versão para tela, até 2048 px, sem metadados (GPS/EXIF)
//   v=full     arquivo original (equipe da mídia) — usado pelo editor
//   v=download baixar
// O perfil "visualizador" recebe sempre a versão sem metadados, nunca o original.
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

$user = Auth::requireLogin();

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$variant = in_array($_GET['v'] ?? '', ['thumb', 'view', 'full', 'download'], true) ? $_GET['v'] : 'thumb';
$photo = $id ? PhotoService::find($id) : null;
$folder = $photo ? FolderService::find((int) $photo['folder_id']) : null;
if (!$photo || !$folder) {
    http_response_code(404);
    exit;
}

$canUpload = Auth::canUpload($user);
$path = null;
$mime = 'image/jpeg';
$downloadName = $photo['original_name'];

if ($variant === 'thumb') {
    $path = PhotoService::thumbPath($photo);
} elseif ($variant === 'view' || !$canUpload) {
    $path = PhotoService::viewPath($photo, $folder);
    if ($path !== null && $variant === 'download') {
        $downloadName = pathinfo($photo['original_name'], PATHINFO_FILENAME) . '.jpg';
    }
    if ($path === null && $canUpload) {
        // Sem versão de tela (ex.: HEIC): equipe recebe o original
        $path = PhotoService::originalPath($photo, $folder);
        $mime = $photo['mime'];
    }
} else {
    $path = PhotoService::originalPath($photo, $folder);
    $mime = $photo['mime'];
}

if ($path === null || !is_file($path)) {
    http_response_code(404);
    exit;
}

// Navegadores não exibem HEIC: nesse caso força o download
$inline = $variant !== 'download' && $mime !== 'image/heic';

if ($variant === 'download') {
    Logger::audit('foto_baixada', 'photo', (int) $photo['id']);
}

$filename = preg_replace('/[^\w.\- ]+/u', '_', $downloadName);
header_remove('Cache-Control');
header('Cache-Control: private, max-age=' . ($variant === 'thumb' ? 86400 : 3600));
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment')
    . '; filename="' . str_replace('"', '', $filename) . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));

while (ob_get_level() > 0) {
    ob_end_clean();
}
readfile($path);
