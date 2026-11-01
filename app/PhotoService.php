<?php
// app/PhotoService.php — recebimento em partes, validação, miniatura e registro das fotos
declare(strict_types=1);

final class PhotoService
{
    /** Extensões aceitas → tipos MIME reais aceitos para cada uma. */
    private const ALLOWED = [
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'webp' => ['image/webp'],
        'heic' => ['image/heic', 'image/heif', 'application/octet-stream'],
        'heif' => ['image/heic', 'image/heif', 'application/octet-stream'],
    ];

    private const THUMB_SIZE = 600;

    public static function maxBytes(): int
    {
        return Config::int('UPLOAD_MAX_MB', 40) * 1024 * 1024;
    }

    public static function chunkBytes(): int
    {
        return max(1, Config::int('UPLOAD_CHUNK_MB', 2)) * 1024 * 1024;
    }

    public static function allowedExtensions(): array
    {
        return array_keys(self::ALLOWED);
    }

    private static function uploadDir(int $userId, string $uploadId): string
    {
        return storage_path('tmp/uploads/' . $userId . '_' . $uploadId);
    }

    /** Partes já recebidas (permite retomar um envio interrompido). */
    public static function receivedChunks(int $userId, string $uploadId): array
    {
        $dir = self::uploadDir($userId, $uploadId);
        if (!is_dir($dir)) {
            return [];
        }
        $indexes = [];
        foreach (glob($dir . '/*.part') ?: [] as $file) {
            $indexes[] = (int) basename($file, '.part');
        }
        sort($indexes);
        return $indexes;
    }

    /**
     * Recebe uma parte. Quando todas chegam, monta e processa a foto.
     * Retorna ['ok'=>true,'status'=>'parcial'|'concluido'|'duplicada', ...] ou ['ok'=>false,'erro'=>...].
     */
    public static function receiveChunk(array $folder, int $userId, array $input, array $file): array
    {
        $uploadId = (string) ($input['upload_id'] ?? '');
        $index = filter_var($input['index'] ?? null, FILTER_VALIDATE_INT);
        $total = filter_var($input['total'] ?? null, FILTER_VALIDATE_INT);
        $size = filter_var($input['size'] ?? null, FILTER_VALIDATE_INT);
        $name = self::cleanOriginalName((string) ($input['name'] ?? ''));

        // Versão editada de uma foto existente (mesma pasta)
        $parent = null;
        if (!empty($input['parent_id'])) {
            $parentId = filter_var($input['parent_id'], FILTER_VALIDATE_INT);
            $parent = $parentId ? self::find($parentId) : null;
            if (!$parent || (int) $parent['folder_id'] !== (int) $folder['id']) {
                return ['ok' => false, 'erro' => 'A foto original não foi encontrada.'];
            }
        }

        if (!preg_match('/^[a-f0-9]{32}$/', $uploadId) || $index === false || $total === false || $size === false) {
            return ['ok' => false, 'erro' => 'Dados do envio inválidos.'];
        }
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED[$ext])) {
            return ['ok' => false, 'erro' => 'Tipo de arquivo não permitido. Envie fotos JPG, PNG, WEBP ou HEIC.'];
        }
        if ($size <= 0 || $size > self::maxBytes()) {
            return ['ok' => false, 'erro' => 'A foto passa do limite de ' . Config::int('UPLOAD_MAX_MB', 40) . ' MB.'];
        }
        $expectedTotal = (int) ceil($size / self::chunkBytes());
        if ($total !== $expectedTotal || $index < 0 || $index >= $total) {
            return ['ok' => false, 'erro' => 'Parte do arquivo fora da sequência.'];
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
            return ['ok' => false, 'erro' => self::uploadErrorMessage((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE))];
        }
        $isLast = $index === $total - 1;
        $expectedChunk = $isLast ? $size - ($total - 1) * self::chunkBytes() : self::chunkBytes();
        if ((int) $file['size'] !== $expectedChunk) {
            return ['ok' => false, 'erro' => 'Uma parte do arquivo chegou incompleta. Tentando de novo…', 'repetir' => true];
        }

        self::cleanupStaleUploads();

        $dir = self::uploadDir($userId, $uploadId);
        if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
            Logger::error('Sem permissão para criar ' . $dir);
            return ['ok' => false, 'erro' => 'O servidor não conseguiu receber o arquivo. Avise o administrador.'];
        }

        // Metadados do envio: devem ser os mesmos em todas as partes
        $metaFile = $dir . '/meta.json';
        $meta = ['folder_id' => (int) $folder['id'], 'name' => $name, 'size' => $size, 'total' => $total, 'parent_id' => $parent ? (int) $parent['id'] : null];
        if (is_file($metaFile)) {
            $saved = json_decode((string) file_get_contents($metaFile), true);
            if ($saved !== $meta) {
                return ['ok' => false, 'erro' => 'As partes não pertencem ao mesmo arquivo.'];
            }
        } else {
            file_put_contents($metaFile, json_encode($meta), LOCK_EX);
        }

        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $index . '.part')) {
            return ['ok' => false, 'erro' => 'Falha ao gravar parte do arquivo.', 'repetir' => true];
        }

        if (count(glob($dir . '/*.part') ?: []) < $total) {
            return ['ok' => true, 'status' => 'parcial'];
        }

        // Todas as partes chegaram: monta com trava para não processar duas vezes
        $lock = fopen($dir . '/.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            return ['ok' => true, 'status' => 'parcial'];
        }
        try {
            $assembled = $dir . '/arquivo.bin';
            $out = fopen($assembled, 'wb');
            for ($i = 0; $i < $total; $i++) {
                $part = $dir . '/' . $i . '.part';
                if (!is_file($part)) {
                    fclose($out);
                    return ['ok' => false, 'erro' => 'Faltou uma parte do arquivo. Envie de novo.'];
                }
                $in = fopen($part, 'rb');
                stream_copy_to_stream($in, $out);
                fclose($in);
            }
            fclose($out);
            clearstatcache();
            if (filesize($assembled) !== $size) {
                return ['ok' => false, 'erro' => 'O arquivo montado ficou com tamanho diferente do original. Envie de novo.'];
            }
            return self::processAssembled($folder, $userId, $assembled, $name, $ext, $parent);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
            self::removeDir($dir);
        }
    }

    /** Valida o conteúdo real do arquivo, grava na pasta do evento e registra no banco. */
    private static function processAssembled(array $folder, int $userId, string $path, string $originalName, string $ext, ?array $parent = null): array
    {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($path);
        if (!in_array($mime, self::ALLOWED[$ext], true)) {
            Logger::audit('upload_rejeitado', 'folder', (int) $folder['id'], ['nome' => $originalName, 'mime' => $mime]);
            return ['ok' => false, 'erro' => 'O conteúdo do arquivo não corresponde a uma foto válida.'];
        }

        $width = $height = null;
        if (in_array($ext, ['heic', 'heif'], true)) {
            if (!self::looksLikeHeic($path)) {
                return ['ok' => false, 'erro' => 'O arquivo HEIC está corrompido ou não é uma foto.'];
            }
            $mime = 'image/heic';
        } else {
            $info = @getimagesize($path);
            if ($info === false || $info[0] < 1 || $info[1] < 1) {
                return ['ok' => false, 'erro' => 'Não foi possível ler a imagem. O arquivo pode estar corrompido.'];
            }
            [$width, $height] = $info;
        }

        $sha256 = hash_file('sha256', $path);
        $dup = Database::one(
            'SELECT id FROM photos WHERE folder_id = ? AND sha256 = ? AND deleted_at IS NULL',
            [$folder['id'], $sha256]
        );
        if ($dup) {
            return ['ok' => true, 'status' => 'duplicada', 'mensagem' => 'Esta foto já estava na pasta.'];
        }

        $storedExt = $ext === 'jpeg' ? 'jpg' : ($ext === 'heif' ? 'heic' : $ext);
        $storedName = bin2hex(random_bytes(16)) . '.' . $storedExt;
        $targetDir = FolderService::absolutePath($folder);
        if (!is_dir($targetDir) && !@mkdir($targetDir, 0750, true)) {
            return ['ok' => false, 'erro' => 'A pasta do evento não existe mais no servidor. Avise o administrador.'];
        }
        $target = $targetDir . '/' . $storedName;
        if (!@rename($path, $target)) {
            if (!@copy($path, $target)) {
                return ['ok' => false, 'erro' => 'Falha ao gravar a foto na pasta do evento.'];
            }
            @unlink($path);
        }
        @chmod($target, 0640);

        // Versão editada herda a data da original (fica ao lado dela na galeria)
        $takenAt = $parent ? $parent['taken_at'] : ($mime === 'image/jpeg' ? self::exifDate($target) : null);
        $hasThumb = self::makeThumbnail($target, $mime, (int) $folder['id'], $storedName);

        Database::run(
            'INSERT INTO photos (folder_id, parent_id, user_id, original_name, stored_name, mime, size_bytes, width, height, sha256, taken_at, has_thumb)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [$folder['id'], $parent ? $parent['id'] : null, $userId, $originalName, $storedName, $mime, filesize($target), $width, $height, $sha256, $takenAt, $hasThumb ? 1 : 0]
        );
        $id = Database::lastId();
        Logger::audit($parent ? 'foto_editada' : 'foto_enviada', 'photo', $id, array_filter([
            'pasta' => (int) $folder['id'], 'nome' => $originalName, 'original' => $parent ? (int) $parent['id'] : null,
        ]));

        return ['ok' => true, 'status' => 'concluido', 'foto' => ['id' => $id, 'thumb' => $hasThumb]];
    }

    private static function looksLikeHeic(string $path): bool
    {
        $head = (string) file_get_contents($path, false, null, 0, 32);
        return substr($head, 4, 4) === 'ftyp'
            && in_array(substr($head, 8, 4), ['heic', 'heix', 'hevc', 'hevx', 'heim', 'heis', 'mif1', 'msf1'], true);
    }

    private static function exifDate(string $path): ?string
    {
        if (!function_exists('exif_read_data')) {
            return null;
        }
        $exif = @exif_read_data($path, 'EXIF');
        $raw = $exif['DateTimeOriginal'] ?? ($exif['DateTime'] ?? null);
        if (!is_string($raw)) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('Y:m:d H:i:s', trim($raw));
        return $date ? $date->format('Y-m-d H:i:s') : null;
    }

    /** Miniatura JPEG sem metadados (remove GPS/EXIF), já com a rotação correta. */
    private static function makeThumbnail(string $source, string $mime, int $folderId, string $storedName): bool
    {
        $dir = storage_path('thumbs/' . $folderId);
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        return self::resizeToJpeg($source, $mime, $dir . '/' . pathinfo($storedName, PATHINFO_FILENAME) . '.jpg', self::THUMB_SIZE, 82);
    }

    /**
     * Gera um JPEG redimensionado (lado maior = $maxSide), com rotação corrigida e
     * SEM metadados (EXIF/GPS). Usado em miniaturas, visualização e redes sociais.
     * $fit: null | ['minRatio' => x, 'maxRatio' => y] para recortar ao centro.
     */
    public static function resizeToJpeg(string $source, string $mime, string $dest, int $maxSide, int $quality, ?array $fit = null): bool
    {
        if (!extension_loaded('gd')) {
            return false;
        }
        $info = @getimagesize($source);
        if ($info === false) {
            return false;
        }
        // Evita estourar a memória do PHP com fotos gigantes
        $needed = (int) ($info[0] * $info[1] * 5 * 1.3);
        @ini_set('memory_limit', '512M');
        $limit = self::memoryLimitBytes();
        if ($limit > 0 && $needed + memory_get_usage() > $limit) {
            return false;
        }

        $img = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($source),
            'image/png'  => @imagecreatefrompng($source),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : false,
            default      => false,
        };
        if (!$img) {
            return false;
        }

        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $orientation = (int) (@exif_read_data($source)['Orientation'] ?? 1);
            $img = match ($orientation) {
                3 => imagerotate($img, 180, 0),
                6 => imagerotate($img, -90, 0),
                8 => imagerotate($img, 90, 0),
                default => $img,
            };
        }

        $w = imagesx($img);
        $h = imagesy($img);
        // Recorte central para caber na proporção exigida (ex.: Instagram)
        $sx = $sy = 0;
        if ($fit !== null) {
            $ratio = $w / $h;
            if ($ratio < $fit['minRatio']) {
                $newH = (int) floor($w / $fit['minRatio']);
                $sy = intdiv($h - $newH, 2);
                $h = $newH;
            } elseif ($ratio > $fit['maxRatio']) {
                $newW = (int) floor($h * $fit['maxRatio']);
                $sx = intdiv($w - $newW, 2);
                $w = $newW;
            }
        }
        $scale = min(1, $maxSide / max($w, $h));
        $tw = max(1, (int) round($w * $scale));
        $th = max(1, (int) round($h * $scale));
        $out = imagecreatetruecolor($tw, $th);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagecopyresampled($out, $img, 0, 0, $sx, $sy, $tw, $th, $w, $h);
        imagedestroy($img);

        imageinterlace($out, true);
        $ok = imagejpeg($out, $dest, $quality);
        imagedestroy($out);
        if ($ok) {
            @chmod($dest, 0640);
        }
        return $ok;
    }

    /** Versão para tela (até 2048 px, sem metadados). Gerada na primeira visualização. */
    public static function viewPath(array $photo, array $folder): ?string
    {
        $path = storage_path('thumbs/' . $photo['folder_id'] . '/' . pathinfo($photo['stored_name'], PATHINFO_FILENAME) . '_v.jpg');
        if (is_file($path)) {
            return $path;
        }
        if ($photo['mime'] === 'image/heic') {
            return null;
        }
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        return self::resizeToJpeg(self::originalPath($photo, $folder), $photo['mime'], $path, 2048, 86) ? $path : null;
    }

    public static function thumbPath(array $photo): string
    {
        return storage_path('thumbs/' . $photo['folder_id'] . '/' . pathinfo($photo['stored_name'], PATHINFO_FILENAME) . '.jpg');
    }

    public static function originalPath(array $photo, array $folder): string
    {
        return FolderService::absolutePath($folder) . '/' . $photo['stored_name'];
    }

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM photos WHERE id = ? AND deleted_at IS NULL', [$id]);
    }

    public static function listByFolder(int $folderId): array
    {
        return Database::all(
            'SELECT p.*, u.name AS user_name,
                    (SELECT COUNT(*) FROM social_posts s WHERE s.photo_id = p.id AND s.status = \'publicado\') AS posts
               FROM photos p JOIN users u ON u.id = p.user_id
              WHERE p.folder_id = ? AND p.deleted_at IS NULL
              ORDER BY COALESCE(p.taken_at, p.created_at), COALESCE(p.parent_id, p.id), p.id',
            [$folderId]
        );
    }

    /** Exclusão lógica: a foto some do sistema, mas o arquivo é mantido (permite recuperar). */
    public static function softDelete(array $photo, int $userId): void
    {
        Database::run('UPDATE photos SET deleted_at = NOW(), deleted_by = ? WHERE id = ?', [$userId, $photo['id']]);
        Logger::audit('foto_excluida', 'photo', (int) $photo['id'], ['pasta' => (int) $photo['folder_id'], 'nome' => $photo['original_name']]);
    }

    private static function cleanOriginalName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name);
        return mb_substr(trim($name), 0, 200) ?: 'foto';
    }

    /** Remove envios abandonados há mais de 24 h (executa de vez em quando). */
    private static function cleanupStaleUploads(): void
    {
        if (random_int(1, 25) !== 1) {
            return;
        }
        foreach (glob(storage_path('tmp/uploads/*'), GLOB_ONLYDIR) ?: [] as $dir) {
            if (filemtime($dir) < time() - 86400) {
                self::removeDir($dir);
            }
        }
    }

    private static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? self::removeDir($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    private static function memoryLimitBytes(): int
    {
        $value = trim((string) ini_get('memory_limit'));
        if ($value === '-1') {
            return 0;
        }
        $unit = strtolower(substr($value, -1));
        $number = (int) $value;
        return match ($unit) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    private static function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'O servidor recusou o tamanho da parte. Diminua UPLOAD_CHUNK_MB no .env.',
            UPLOAD_ERR_PARTIAL => 'O envio foi interrompido. Tentando de novo…',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'O servidor não conseguiu gravar o arquivo temporário.',
            default => 'Nenhum arquivo recebido.',
        };
    }
}
