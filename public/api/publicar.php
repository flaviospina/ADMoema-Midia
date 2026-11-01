<?php
// public/api/publicar.php — publica uma foto nas redes conectadas do próprio usuário
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

$user = Auth::requireUploader();
require_post();
@set_time_limit(120);

$id = filter_var($_POST['photo_id'] ?? null, FILTER_VALIDATE_INT);
$photo = $id ? PhotoService::find($id) : null;
$folder = $photo ? FolderService::find((int) $photo['folder_id']) : null;
if (!$photo || !$folder) {
    json_response(['ok' => false, 'erro' => 'Foto não encontrada.'], 404);
}

$networks = array_values(array_intersect(array_keys(SocialService::NETWORKS), (array) ($_POST['redes'] ?? [])));
if (!$networks) {
    json_response(['ok' => false, 'erro' => 'Escolha pelo menos uma rede social.'], 422);
}

$caption = trim(str_replace("\r\n", "\n", (string) ($_POST['legenda'] ?? '')));
if (mb_strlen($caption) > SocialService::CAPTION_MAX) {
    json_response(['ok' => false, 'erro' => 'A legenda passa de ' . SocialService::CAPTION_MAX . ' caracteres (limite do Instagram).'], 422);
}
if (preg_match_all('/#[\p{L}\p{N}_]+/u', $caption) > 30) {
    json_response(['ok' => false, 'erro' => 'O Instagram aceita no máximo 30 hashtags.'], 422);
}

// Evita publicação repetida por clique duplo (mesma foto, mesma rede, últimos 2 minutos)
foreach ($networks as $network) {
    $recent = Database::value(
        "SELECT COUNT(*) FROM social_posts WHERE photo_id = ? AND network = ? AND user_id = ? AND status = 'publicado' AND created_at > (NOW() - INTERVAL 2 MINUTE)",
        [$photo['id'], $network, $user['id']]
    );
    if ($recent) {
        json_response(['ok' => false, 'erro' => 'Esta foto acabou de ser publicada em ' . SocialService::NETWORKS[$network] . '.'], 409);
    }
}

$results = SocialService::publish($user, $photo, $folder, $networks, $caption);
$allOk = !in_array(false, array_column($results, 'ok'), true);
json_response(['ok' => $allOk, 'resultados' => $results]);
