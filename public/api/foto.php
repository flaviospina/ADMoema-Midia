<?php
// public/api/foto.php — entrega a miniatura, a foto original ou o download (somente logado)
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

Auth::requireLogin();

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$variant = in_array($_GET['v'] ?? '', ['thumb', 'full', 'download'], true) ? $_GET['v'] : 'thumb';
$photo = $id ? PhotoService::find($id) : null;
$folder = $photo ? FolderService::find((int) $photo['folder_id']) : null;
if (!$photo || !$folder) {
    http_response_code(404);
    exit;
}

if ($variant === 'thumb') {
    $path = PhotoService::thumbPath($photo);
    $mime = 'image/jpeg';
} else {
    $path = PhotoService::originalPath($photo, $folder);
    $mime = $photo['mime'];
}
if (!is_file($path)) {
    http_response_code(404);
    exit;
}

// Navegadores não exibem HEIC: nesse caso força o download
$inline = $variant !== 'download' && $mime !== 'image/heic';

if ($variant === 'download') {
    Logger::audit('foto_baixada', 'photo', (int) $photo['id']);
}

$filename = preg_replace('/[^\w.\- ]+/u', '_', $photo['original_name']);
header_remove('Cache-Control');
header('Cache-Control: private, max-age=' . ($variant === 'thumb' ? 86400 : 3600));
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment')
    . '; filename="' . str_replace('"', '', $filename) . '"; filename*=UTF-8\'\'' . rawurlencode($photo['original_name']));

while (ob_get_level() > 0) {
    ob_end_clean();
}
readfile($path);
