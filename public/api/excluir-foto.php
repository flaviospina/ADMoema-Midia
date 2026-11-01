<?php
// public/api/excluir-foto.php — exclui uma foto (quem enviou ou administrador)
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

$user = Auth::requireLogin();
require_post();

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
$photo = $id ? PhotoService::find($id) : null;
if (!$photo) {
    json_response(['ok' => false, 'erro' => 'Foto não encontrada.'], 404);
}
if ($user['role'] !== 'admin' && (int) $photo['user_id'] !== (int) $user['id']) {
    Logger::audit('acesso_negado', 'photo', (int) $photo['id'], ['acao' => 'excluir']);
    json_response(['ok' => false, 'erro' => 'Você só pode excluir as fotos que você enviou.'], 403);
}

PhotoService::softDelete($photo, (int) $user['id']);
json_response(['ok' => true]);
