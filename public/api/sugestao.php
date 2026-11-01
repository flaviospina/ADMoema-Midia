<?php
// public/api/sugestao.php — nome sugerido para a data + pastas que já existem nela
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

Auth::requireUploader();

$date = EventNaming::parseDate($_GET['data'] ?? null);
if (!$date) {
    json_response(['ok' => false, 'erro' => 'Data inválida.'], 422);
}

json_response([
    'ok'         => true,
    'data'       => $date->format('Y-m-d'),
    'data_br'    => $date->format('d/m/Y'),
    'dia_semana' => EventNaming::weekdayName($date),
    'sugestoes'  => EventNaming::suggestionsFor($date),
    'existentes' => array_map(static fn($f) => [
        'id'    => (int) $f['id'],
        'nome'  => $f['name'],
        'total' => (int) $f['total'],
    ], FolderService::listByDate($date->format('Y-m-d'))),
]);
