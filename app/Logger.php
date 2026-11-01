<?php
// app/Logger.php — registro de auditoria (quem fez o quê, quando e de onde)
declare(strict_types=1);

final class Logger
{
    public static function audit(string $action, ?string $entity = null, ?int $entityId = null, array $details = [], ?int $userId = null): void
    {
        try {
            Database::run(
                'INSERT INTO audit_log (user_id, action, entity, entity_id, details, ip, user_agent) VALUES (?,?,?,?,?,?,?)',
                [
                    $userId ?? ($_SESSION['user_id'] ?? null),
                    $action,
                    $entity,
                    $entityId,
                    $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
                    Security::clientIp(),
                    Security::userAgent(),
                ]
            );
        } catch (Throwable $e) {
            self::error('Falha ao gravar auditoria: ' . $e->getMessage());
        }
    }

    /** Erros técnicos vão para arquivo (nunca para a tela do usuário). */
    public static function error(string $message): void
    {
        $dir = storage_path('logs');
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        $line = sprintf("[%s] %s %s\n", date('Y-m-d H:i:s'), Security::clientIp(), str_replace(["\r", "\n"], ' ', $message));
        @file_put_contents($dir . '/erros-' . date('Y-m') . '.log', $line, FILE_APPEND | LOCK_EX);
    }
}
