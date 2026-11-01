<?php
// app/SocialService.php — conexão com Facebook (Página) e Instagram (conta profissional)
// via Graph API da Meta. Cada usuário conecta a PRÓPRIA conta; o token fica
// criptografado no banco (libsodium + APP_KEY) e nunca volta para a tela.
declare(strict_types=1);

final class SocialService
{
    public const NETWORKS = [
        'facebook'  => 'Facebook (Página)',
        'instagram' => 'Instagram',
    ];

    /** O Instagram aceita fotos entre 4:5 (retrato) e 1,91:1 (paisagem). */
    private const IG_FIT = ['minRatio' => 0.8, 'maxRatio' => 1.91];
    public const CAPTION_MAX = 2200;

    // ------------------------------------------------------------------
    // Contas
    // ------------------------------------------------------------------

    /** Contas conectadas do usuário, indexadas pela rede. */
    public static function accounts(int $userId): array
    {
        $rows = Database::all('SELECT * FROM social_accounts WHERE user_id = ?', [$userId]);
        $out = [];
        foreach ($rows as $row) {
            $out[$row['network']] = $row;
        }
        return $out;
    }

    /** Dados seguros para enviar ao navegador (sem token). */
    public static function publicAccounts(int $userId): array
    {
        $out = [];
        foreach (self::accounts($userId) as $network => $a) {
            $out[] = ['network' => $network, 'label' => self::NETWORKS[$network], 'name' => $a['display_name']];
        }
        return $out;
    }

    /**
     * Conecta uma Página do Facebook com o token de acesso DA PÁGINA.
     * O ID e o nome da Página são descobertos pelo próprio token.
     */
    public static function connectFacebook(int $userId, string $token): array
    {
        $token = self::cleanToken($token);
        if ($token === '') {
            return ['ok' => false, 'erro' => 'Cole o token de acesso da Página.'];
        }
        $res = self::graph('facebook', 'GET', 'me', ['fields' => 'id,name', 'metadata' => '1'], $token);
        if (!$res['ok']) {
            return ['ok' => false, 'erro' => $res['erro']];
        }
        $type = $res['data']['metadata']['type'] ?? null;
        if ($type !== null && $type !== 'page') {
            return ['ok' => false, 'erro' => 'Este token é de um perfil pessoal, não de uma Página. Gere o token da Página (veja a ajuda).'];
        }
        $id = (string) ($res['data']['id'] ?? '');
        if (!preg_match('/^\d{3,40}$/', $id)) {
            return ['ok' => false, 'erro' => 'A Meta não devolveu o ID da Página.'];
        }
        self::save($userId, 'facebook', 'facebook', $id, (string) ($res['data']['name'] ?? 'Página'), $token, null);
        Logger::audit('rede_conectada', 'user', $userId, ['rede' => 'facebook', 'conta' => $res['data']['name'] ?? $id]);
        return ['ok' => true, 'nome' => $res['data']['name'] ?? $id];
    }

    /**
     * Conecta o Instagram. Dois caminhos aceitos:
     *  - token do "Login do Instagram" (começa com IG): não precisa de ID;
     *  - token do "Login do Facebook": precisa do ID da conta do Instagram.
     */
    public static function connectInstagram(int $userId, string $token, string $igId = ''): array
    {
        $token = self::cleanToken($token);
        $igId = trim($igId);
        if ($token === '') {
            return ['ok' => false, 'erro' => 'Cole o token de acesso do Instagram.'];
        }

        if (str_starts_with($token, 'IG')) {
            $res = self::graph('instagram', 'GET', 'me', ['fields' => 'user_id,username,account_type'], $token);
            if (!$res['ok']) {
                return ['ok' => false, 'erro' => $res['erro']];
            }
            $id = (string) ($res['data']['user_id'] ?? $res['data']['id'] ?? '');
            $username = (string) ($res['data']['username'] ?? '');
            $host = 'instagram';
        } else {
            if (!preg_match('/^\d{5,40}$/', $igId)) {
                return ['ok' => false, 'erro' => 'Com token do Facebook, informe também o ID numérico da conta do Instagram.'];
            }
            $res = self::graph('facebook', 'GET', $igId, ['fields' => 'id,username'], $token);
            if (!$res['ok']) {
                return ['ok' => false, 'erro' => $res['erro']];
            }
            $id = (string) ($res['data']['id'] ?? '');
            $username = (string) ($res['data']['username'] ?? '');
            $host = 'facebook';
        }
        if (!preg_match('/^\d{3,40}$/', $id)) {
            return ['ok' => false, 'erro' => 'A Meta não devolveu o ID da conta do Instagram.'];
        }
        $name = $username !== '' ? '@' . $username : 'Instagram ' . $id;
        self::save($userId, 'instagram', $host, $id, $name, $token, null);
        Logger::audit('rede_conectada', 'user', $userId, ['rede' => 'instagram', 'conta' => $name]);
        return ['ok' => true, 'nome' => $name];
    }

    public static function disconnect(int $userId, string $network): void
    {
        Database::run('DELETE FROM social_accounts WHERE user_id = ? AND network = ?', [$userId, $network]);
        Logger::audit('rede_desconectada', 'user', $userId, ['rede' => $network]);
    }

    /** Confere se o token ainda funciona. */
    public static function test(array $account): array
    {
        $token = Security::decrypt($account['token_enc']);
        if ($token === null) {
            return ['ok' => false, 'erro' => 'Não foi possível ler o token (a APP_KEY mudou?). Conecte de novo.'];
        }
        $res = $account['api_host'] === 'instagram'
            ? self::graph('instagram', 'GET', 'me', ['fields' => 'user_id,username'], $token)
            : self::graph('facebook', 'GET', $account['external_id'], ['fields' => 'id'], $token);
        Database::run('UPDATE social_accounts SET last_error = ? WHERE id = ?', [$res['ok'] ? null : mb_substr($res['erro'], 0, 500), $account['id']]);
        return $res;
    }

    private static function save(int $userId, string $network, string $host, string $externalId, string $name, string $token, ?string $expiresAt): void
    {
        Database::run(
            'INSERT INTO social_accounts (user_id, network, api_host, external_id, display_name, token_enc, token_hint, token_expires_at, token_updated_at)
             VALUES (?,?,?,?,?,?,?,?,NOW())
             ON DUPLICATE KEY UPDATE api_host = VALUES(api_host), external_id = VALUES(external_id), display_name = VALUES(display_name),
                 token_enc = VALUES(token_enc), token_hint = VALUES(token_hint), token_expires_at = VALUES(token_expires_at),
                 token_updated_at = NOW(), last_error = NULL',
            [$userId, $network, $host, $externalId, mb_substr($name, 0, 190), Security::encrypt($token), '…' . substr($token, -6), $expiresAt]
        );
    }

    private static function cleanToken(string $token): string
    {
        $token = preg_replace('/\s+/', '', $token);
        return preg_match('/^[A-Za-z0-9_\-|.]{20,1000}$/', $token) ? $token : '';
    }

    /**
     * Tokens do Login do Instagram valem 60 dias e podem ser renovados (após 24 h).
     * Renova automaticamente quando o último foi gerado há mais de 7 dias.
     */
    private static function refreshInstagramIfNeeded(array $account, string $token): string
    {
        if ($account['api_host'] !== 'instagram' || strtotime($account['token_updated_at']) > time() - 7 * 86400) {
            return $token;
        }
        $res = self::graph('instagram', 'GET', 'refresh_access_token', ['grant_type' => 'ig_refresh_token'], $token, null, false);
        $new = (string) ($res['data']['access_token'] ?? '');
        if ($res['ok'] && self::cleanToken($new) !== '') {
            $expires = isset($res['data']['expires_in']) ? date('Y-m-d H:i:s', time() + (int) $res['data']['expires_in']) : null;
            self::save((int) $account['user_id'], 'instagram', 'instagram', $account['external_id'], $account['display_name'], $new, $expires);
            Logger::audit('rede_token_renovado', 'user', (int) $account['user_id'], ['rede' => 'instagram']);
            return $new;
        }
        return $token;
    }

    // ------------------------------------------------------------------
    // Publicação
    // ------------------------------------------------------------------

    /**
     * Publica a foto nas redes escolhidas. Devolve um resultado por rede:
     * ['facebook' => ['ok'=>true,'link'=>...], 'instagram' => ['ok'=>false,'erro'=>...]]
     */
    public static function publish(array $user, array $photo, array $folder, array $networks, string $caption): array
    {
        $accounts = self::accounts((int) $user['id']);
        $results = [];
        foreach ($networks as $network) {
            if (!isset($accounts[$network])) {
                $results[$network] = ['ok' => false, 'erro' => 'Conta não conectada.'];
                continue;
            }
            try {
                $result = $network === 'facebook'
                    ? self::publishFacebook($accounts['facebook'], $photo, $folder, $caption)
                    : self::publishInstagram($accounts['instagram'], $photo, $folder, $caption);
            } catch (Throwable $e) {
                Logger::error('Publicação ' . $network . ': ' . $e->getMessage());
                $result = ['ok' => false, 'erro' => 'Erro inesperado ao publicar. Tente novamente.'];
            }

            Database::run(
                'INSERT INTO social_posts (user_id, photo_id, network, status, external_id, permalink, caption, error) VALUES (?,?,?,?,?,?,?,?)',
                [$user['id'], $photo['id'], $network, $result['ok'] ? 'publicado' : 'erro', $result['id'] ?? null,
                 $result['link'] ?? null, $caption, $result['ok'] ? null : mb_substr($result['erro'], 0, 500)]
            );
            if (!$result['ok']) {
                Database::run('UPDATE social_accounts SET last_error = ? WHERE id = ?', [mb_substr($result['erro'], 0, 500), $accounts[$network]['id']]);
            }
            Logger::audit($result['ok'] ? 'rede_publicado' : 'rede_falhou', 'photo', (int) $photo['id'], array_filter([
                'rede' => $network, 'conta' => $accounts[$network]['display_name'], 'erro' => $result['erro'] ?? null,
            ]));
            unset($result['id']);
            $results[$network] = $result;
        }
        return $results;
    }

    private static function publishFacebook(array $account, array $photo, array $folder, string $caption): array
    {
        $token = Security::decrypt($account['token_enc']);
        if ($token === null) {
            return ['ok' => false, 'erro' => 'Token ilegível. Conecte o Facebook de novo em Minha conta.'];
        }
        $file = self::prepareImage($photo, $folder, 2048, null);
        if (!$file['ok']) {
            return $file;
        }
        try {
            $res = self::graph('facebook', 'POST', $account['external_id'] . '/photos', [
                'message'   => $caption,
                'published' => 'true',
            ], $token, $file['path']);
        } finally {
            @unlink($file['path']);
        }
        if (!$res['ok']) {
            return ['ok' => false, 'erro' => $res['erro']];
        }
        $postId = (string) ($res['data']['post_id'] ?? $res['data']['id'] ?? '');
        return ['ok' => true, 'id' => $postId, 'link' => $postId !== '' ? 'https://www.facebook.com/' . rawurlencode($postId) : null];
    }

    private static function publishInstagram(array $account, array $photo, array $folder, string $caption): array
    {
        $token = Security::decrypt($account['token_enc']);
        if ($token === null) {
            return ['ok' => false, 'erro' => 'Token ilegível. Conecte o Instagram de novo em Minha conta.'];
        }
        $appUrl = rtrim((string) Config::get('APP_URL', ''), '/');
        if (!Config::isLocal() && !str_starts_with($appUrl, 'https://')) {
            return ['ok' => false, 'erro' => 'Configure APP_URL com https:// no .env: o Instagram precisa buscar a foto no site.'];
        }
        $token = self::refreshInstagramIfNeeded($account, $token);
        $host = $account['api_host'];
        $igId = $account['external_id'];

        // O Instagram não recebe o arquivo: ele baixa a foto de um endereço público temporário
        $file = self::prepareImage($photo, $folder, 1440, self::IG_FIT);
        if (!$file['ok']) {
            return $file;
        }
        try {
            $imageUrl = $appUrl . '/api/publico.php?f=' . basename($file['path'], '.jpg');
            $res = self::graph($host, 'POST', $igId . '/media', ['image_url' => $imageUrl, 'caption' => $caption], $token);
            if (!$res['ok']) {
                return ['ok' => false, 'erro' => $res['erro']];
            }
            $containerId = (string) ($res['data']['id'] ?? '');

            // Aguarda o Instagram processar a imagem (normalmente poucos segundos)
            $status = 'IN_PROGRESS';
            for ($i = 0; $i < 10 && $status === 'IN_PROGRESS'; $i++) {
                $check = self::graph($host, 'GET', $containerId, ['fields' => 'status_code'], $token);
                $status = $check['ok'] ? (string) ($check['data']['status_code'] ?? 'FINISHED') : 'ERROR';
                if ($status === 'IN_PROGRESS') {
                    usleep(1500000);
                }
            }
            if ($status !== 'FINISHED' && $status !== 'IN_PROGRESS') {
                return ['ok' => false, 'erro' => 'O Instagram não conseguiu processar a foto (status ' . $status . ').'];
            }

            $pub = self::graph($host, 'POST', $igId . '/media_publish', ['creation_id' => $containerId], $token);
            if (!$pub['ok']) {
                return ['ok' => false, 'erro' => $pub['erro']];
            }
            $mediaId = (string) ($pub['data']['id'] ?? '');
            $link = self::graph($host, 'GET', $mediaId, ['fields' => 'permalink'], $token);
            return ['ok' => true, 'id' => $mediaId, 'link' => $link['data']['permalink'] ?? null];
        } finally {
            @unlink($file['path']);
        }
    }

    /** JPEG temporário, sem metadados, no tamanho/proporção aceitos pela rede. */
    private static function prepareImage(array $photo, array $folder, int $maxSide, ?array $fit): array
    {
        if ($photo['mime'] === 'image/heic') {
            return ['ok' => false, 'erro' => 'Fotos HEIC não podem ser publicadas. Abra a foto em "Melhorar foto" e salve uma versão: ela fica em JPG.'];
        }
        $dir = self::socialTmpDir();
        self::cleanupTmp($dir);
        $path = $dir . '/' . bin2hex(random_bytes(20)) . '.jpg';
        if (!PhotoService::resizeToJpeg(PhotoService::originalPath($photo, $folder), $photo['mime'], $path, $maxSide, 90, $fit)) {
            return ['ok' => false, 'erro' => 'Não foi possível preparar a imagem para publicar.'];
        }
        return ['ok' => true, 'path' => $path];
    }

    public static function socialTmpDir(): string
    {
        $dir = storage_path('tmp/social');
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        return $dir;
    }

    /** Apaga arquivos temporários esquecidos há mais de 1 hora. */
    private static function cleanupTmp(string $dir): void
    {
        foreach (glob($dir . '/*.jpg') ?: [] as $file) {
            if (filemtime($file) < time() - 3600) {
                @unlink($file);
            }
        }
    }

    // ------------------------------------------------------------------
    // Chamada HTTP à Graph API (sempre por cURL)
    // ------------------------------------------------------------------

    private static function baseUrl(string $host): string
    {
        // Em desenvolvimento é possível apontar para um servidor simulado
        $override = (string) Config::get('META_API_BASE_OVERRIDE', '');
        if ($override !== '' && Config::isLocal()) {
            return rtrim($override, '/') . '/' . $host;
        }
        return $host === 'instagram' ? 'https://graph.instagram.com' : 'https://graph.facebook.com';
    }

    /** @return array{ok: bool, data: array, erro: string} */
    private static function graph(string $host, string $method, string $path, array $params, string $token, ?string $filePath = null, bool $versioned = true): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'data' => [], 'erro' => 'O servidor não tem cURL habilitado.'];
        }
        $version = preg_match('/^v\d+\.\d+$/', (string) Config::get('META_GRAPH_VERSION', '')) ? Config::get('META_GRAPH_VERSION') : 'v26.0';
        $url = self::baseUrl($host) . '/' . ($versioned ? $version . '/' : '') . ltrim($path, '/');
        $params['access_token'] = $token;

        $ch = curl_init();
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_USERAGENT      => 'MidiaADMoema/' . APP_VERSION,
            CURLOPT_SSL_VERIFYPEER => true,
        ];
        if ($method === 'GET') {
            $options[CURLOPT_URL] = $url . '?' . http_build_query($params);
        } else {
            $options[CURLOPT_URL] = $url;
            $options[CURLOPT_POST] = true;
            if ($filePath !== null) {
                $params['source'] = new CURLFile($filePath, 'image/jpeg', 'foto.jpg');
                $options[CURLOPT_POSTFIELDS] = $params; // multipart
            } else {
                $options[CURLOPT_POSTFIELDS] = http_build_query($params);
            }
        }
        curl_setopt_array($ch, $options);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            Logger::error('Graph API (' . $host . ' ' . $path . '): ' . $curlError);
            return ['ok' => false, 'data' => [], 'erro' => 'Sem resposta da Meta. Verifique a internet do servidor e tente de novo.'];
        }
        $data = json_decode((string) $body, true);
        if (!is_array($data)) {
            return ['ok' => false, 'data' => [], 'erro' => 'Resposta inesperada da Meta (HTTP ' . $status . ').'];
        }
        if ($status >= 400 || isset($data['error'])) {
            return ['ok' => false, 'data' => $data, 'erro' => self::errorMessage($data['error'] ?? [])];
        }
        return ['ok' => true, 'data' => $data, 'erro' => ''];
    }

    /** Traduz os erros mais comuns da Meta. */
    private static function errorMessage(array $error): string
    {
        $code = (int) ($error['code'] ?? 0);
        $message = mb_substr((string) ($error['message'] ?? 'erro desconhecido'), 0, 300);
        return match (true) {
            $code === 190 => 'O token expirou ou foi revogado. Gere um novo e atualize em Minha conta → Redes sociais.',
            in_array($code, [10, 200, 3], true) || ($code >= 200 && $code < 300) => 'A conta não deu permissão para publicar. Gere o token marcando as permissões de publicação (veja a ajuda). Detalhe: ' . $message,
            in_array($code, [4, 17, 32, 613], true) => 'Limite de publicações da Meta atingido. Tente mais tarde.',
            $code === 100 => 'A Meta recusou o pedido: ' . $message,
            $code === 9004 || $code === 36003 => 'O Instagram não aceitou a imagem (formato ou proporção). Detalhe: ' . $message,
            default => 'A Meta respondeu: ' . $message,
        };
    }
}
