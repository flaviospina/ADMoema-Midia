<?php
// public/api/upload.php — recebe as fotos em partes (POST) e informa o progresso (GET)
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

$user = Auth::requireUploader();
@set_time_limit(120);

// GET: quais partes deste envio já chegaram (para retomar após queda de conexão)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $uploadId = (string) ($_GET['upload_id'] ?? '');
    if (!preg_match('/^[a-f0-9]{32}$/', $uploadId)) {
        json_response(['ok' => false, 'erro' => 'Envio inválido.'], 422);
    }
    json_response(['ok' => true, 'recebidas' => PhotoService::receivedChunks((int) $user['id'], $uploadId)]);
}

require_post();

$folderId = filter_var($_POST['folder_id'] ?? null, FILTER_VALIDATE_INT);
$folder = $folderId ? FolderService::find($folderId) : null;
if (!$folder) {
    json_response(['ok' => false, 'erro' => 'Pasta não encontrada.'], 404);
}

$result = PhotoService::receiveChunk($folder, (int) $user['id'], $_POST, $_FILES['chunk'] ?? []);
json_response($result, $result['ok'] ? 200 : 422);
