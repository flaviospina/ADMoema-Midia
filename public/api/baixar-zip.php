<?php
// public/api/baixar-zip.php — baixa todas as fotos de uma pasta em um único .zip
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

Auth::requireLogin();

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$folder = $id ? FolderService::find($id) : null;
if (!$folder) {
    http_response_code(404);
    exit('Pasta não encontrada.');
}
if (!class_exists('ZipArchive')) {
    flash('erro', 'O servidor não tem suporte a arquivos .zip. Baixe as fotos individualmente.');
    redirect('../pasta.php?id=' . (int) $folder['id']);
}

$photos = PhotoService::listByFolder((int) $folder['id']);
if (!$photos) {
    flash('info', 'A pasta não tem fotos para baixar.');
    redirect('../pasta.php?id=' . (int) $folder['id']);
}

@set_time_limit(0);
$tmpDir = storage_path('tmp');
if (!is_dir($tmpDir)) {
    @mkdir($tmpDir, 0700, true);
}
$zipPath = $tmpDir . '/zip_' . bin2hex(random_bytes(8)) . '.zip';
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    throw new RuntimeException('Não foi possível criar o arquivo zip.');
}

$used = [];
foreach ($photos as $p) {
    $path = PhotoService::originalPath($p, $folder);
    if (!is_file($path)) {
        continue;
    }
    // Nomes repetidos ganham sufixo (2), (3)…
    $name = preg_replace('/[\/\\\\:*?"<>|]+/', '_', $p['original_name']);
    $base = pathinfo($name, PATHINFO_FILENAME);
    $ext = pathinfo($name, PATHINFO_EXTENSION);
    $candidate = $name;
    $n = 2;
    while (isset($used[mb_strtolower($candidate)])) {
        $candidate = $base . ' (' . $n++ . ')' . ($ext !== '' ? '.' . $ext : '');
    }
    $used[mb_strtolower($candidate)] = true;
    $zip->addFile($path, $candidate);
    $zip->setCompressionName($candidate, ZipArchive::CM_STORE); // fotos já são comprimidas
}
$zip->close();

Logger::audit('pasta_baixada_zip', 'folder', (int) $folder['id'], ['fotos' => count($used)]);

$baseName = $folder['event_date'] . ' - ' . $folder['name'];
$downloadName = $baseName . '.zip';
header_remove('Cache-Control');
header('Cache-Control: private, no-store');
header('Content-Type: application/zip');
header('Content-Length: ' . filesize($zipPath));
header('Content-Disposition: attachment; filename="' . EventNaming::slug($baseName) . '.zip"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
while (ob_get_level() > 0) {
    ob_end_clean();
}
readfile($zipPath);
@unlink($zipPath);
