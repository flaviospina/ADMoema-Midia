<?php
// app/FolderService.php — criação e consulta das pastas de eventos
declare(strict_types=1);

final class FolderService
{
    /** Caminho físico: fotos/2026/2026-11-01_culto-com-a-familia */
    public static function relativePath(DateTimeImmutable $date, string $slug): string
    {
        return $date->format('Y') . '/' . $date->format('Y-m-d') . '_' . $slug;
    }

    public static function absolutePath(array $folder): string
    {
        return storage_path('fotos/' . $folder['rel_path']);
    }

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM folders WHERE id = ?', [$id]);
    }

    public static function findByDateSlug(string $date, string $slug): ?array
    {
        return Database::one('SELECT * FROM folders WHERE event_date = ? AND slug = ?', [$date, $slug]);
    }

    public static function listByDate(string $date): array
    {
        return Database::all(
            'SELECT f.id, f.name, f.event_date,
                    (SELECT COUNT(*) FROM photos p WHERE p.folder_id = f.id AND p.deleted_at IS NULL) AS total
               FROM folders f WHERE f.event_date = ? ORDER BY f.name',
            [$date]
        );
    }

    /**
     * Cria a pasta no banco e no servidor.
     * Devolve ['ok' => true, 'folder' => [...]] ou ['ok' => false, 'erro' => '...', 'existente' => [...]].
     */
    public static function create(DateTimeImmutable $date, string $name, bool $wasSuggested, int $userId): array
    {
        [$name, $error] = EventNaming::cleanName($name);
        if ($error) {
            return ['ok' => false, 'erro' => $error];
        }
        $slug = EventNaming::slug($name);
        $existing = self::findByDateSlug($date->format('Y-m-d'), $slug);
        if ($existing) {
            return [
                'ok' => false,
                'erro' => 'Já existe uma pasta com esse nome nesta data.',
                'existente' => ['id' => (int) $existing['id'], 'name' => $existing['name']],
            ];
        }

        $rel = self::relativePath($date, $slug);
        $abs = storage_path('fotos/' . $rel);
        if (!is_dir($abs) && !@mkdir($abs, 0750, true)) {
            Logger::error('Não foi possível criar a pasta ' . $abs);
            return ['ok' => false, 'erro' => 'O servidor não permitiu criar a pasta. Avise o administrador.'];
        }
        // Impede listagem/execução caso a pasta storage esteja dentro do public_html
        self::protectDirectory();

        try {
            Database::run(
                'INSERT INTO folders (event_date, name, slug, rel_path, was_suggested, created_by) VALUES (?,?,?,?,?,?)',
                [$date->format('Y-m-d'), $name, $slug, $rel, $wasSuggested ? 1 : 0, $userId]
            );
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') { // criada ao mesmo tempo por outra pessoa
                $existing = self::findByDateSlug($date->format('Y-m-d'), $slug);
                return [
                    'ok' => false,
                    'erro' => 'Essa pasta acabou de ser criada por outra pessoa.',
                    'existente' => $existing ? ['id' => (int) $existing['id'], 'name' => $existing['name']] : null,
                ];
            }
            throw $e;
        }
        $id = Database::lastId();
        Logger::audit('pasta_criada', 'folder', $id, ['nome' => $name, 'data' => $date->format('Y-m-d'), 'sugerido' => $wasSuggested]);
        return ['ok' => true, 'folder' => self::find($id)];
    }

    private static function protectDirectory(): void
    {
        $htaccess = storage_path('fotos/.htaccess');
        if (!is_file($htaccess)) {
            @file_put_contents($htaccess, "Require all denied\nDeny from all\nOptions -Indexes -ExecCGI\n");
        }
    }

    /** Lista para o painel, com total de fotos e uma capa. */
    public static function listAll(?int $year = null): array
    {
        $params = [];
        $where = '';
        if ($year) {
            $where = 'WHERE f.event_date BETWEEN ? AND ?';
            $params = [$year . '-01-01', $year . '-12-31'];
        }
        return Database::all(
            "SELECT f.*, u.name AS created_by_name,
                    (SELECT COUNT(*) FROM photos p WHERE p.folder_id = f.id AND p.deleted_at IS NULL) AS total,
                    (SELECT p.id FROM photos p WHERE p.folder_id = f.id AND p.deleted_at IS NULL AND p.has_thumb = 1 ORDER BY p.id LIMIT 1) AS cover_id
               FROM folders f JOIN users u ON u.id = f.created_by
               $where
              ORDER BY f.event_date DESC, f.name",
            $params
        );
    }

    public static function years(): array
    {
        return array_map('intval', array_column(
            Database::all('SELECT DISTINCT YEAR(event_date) AS y FROM folders ORDER BY y DESC'),
            'y'
        ));
    }
}
