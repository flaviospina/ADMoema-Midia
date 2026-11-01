<?php
// public/api/criar-pasta.php — cria a pasta do evento após a confirmação do usuário
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

$user = Auth::requireLogin();
require_post();

$date = EventNaming::parseDate($_POST['data'] ?? null);
if (!$date) {
    json_response(['ok' => false, 'erro' => 'Data inválida.'], 422);
}
if (($_POST['confirmado'] ?? '') !== '1') {
    json_response(['ok' => false, 'erro' => 'Confirme o nome da pasta antes de criar.'], 422);
}

$name = (string) ($_POST['nome'] ?? '');
// Só conta como "sugerido" se o nome for exatamente um dos sugeridos para o dia
$wasSuggested = in_array(trim($name), EventNaming::suggestionsFor($date), true);

$result = FolderService::create($date, $name, $wasSuggested, (int) $user['id']);
if (!$result['ok']) {
    json_response($result, 409);
}

json_response(['ok' => true, 'id' => (int) $result['folder']['id'], 'url' => 'pasta.php?id=' . (int) $result['folder']['id']]);
