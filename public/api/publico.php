<?php
// public/api/publico.php — entrega a foto temporária que o Instagram precisa baixar
// para publicar. Sem login, mas: nome aleatório de 160 bits, só existe durante a
// publicação e expira em 1 hora. Não dá acesso a nenhuma outra foto.
declare(strict_types=1);
define('NO_SESSION', true);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

$name = (string) ($_GET['f'] ?? '');
if (!preg_match('/^[a-f0-9]{40}$/', $name)) {
    http_response_code(404);
    exit;
}
$path = SocialService::socialTmpDir() . '/' . $name . '.jpg';
if (!is_file($path) || filemtime($path) < time() - 3600) {
    http_response_code(404);
    exit;
}

header_remove('Cache-Control');
header('Cache-Control: no-store');
header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($path));
header('X-Robots-Tag: noindex, nofollow');
header('Cross-Origin-Resource-Policy: cross-origin');
while (ob_get_level() > 0) {
    ob_end_clean();
}
readfile($path);
