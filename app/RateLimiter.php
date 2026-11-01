<?php
// app/RateLimiter.php — bloqueio contra força bruta por IP e por conta
declare(strict_types=1);

final class RateLimiter
{
    public static function emailHash(string $email): string
    {
        return hash('sha256', mb_strtolower(trim($email)));
    }

    /** true se o IP ultrapassou o limite de falhas na janela de tempo. */
    public static function ipBlocked(string $ip): bool
    {
        $max = Config::int('LOGIN_MAX_FAILS_PER_IP', 10);
        $window = Config::int('LOGIN_WINDOW_MINUTES', 15);
        $fails = (int) Database::value(
            'SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND success = 0 AND created_at > (NOW() - INTERVAL ? MINUTE)',
            [$ip, $window]
        );
        return $fails >= $max;
    }

    public static function record(string $ip, string $email, bool $success): void
    {
        Database::run(
            'INSERT INTO login_attempts (ip, email_hash, success) VALUES (?,?,?)',
            [$ip, self::emailHash($email), $success ? 1 : 0]
        );
        // Limpeza ocasional (1 em cada 50 requisições) de registros com mais de 30 dias
        if (random_int(1, 50) === 1) {
            Database::run('DELETE FROM login_attempts WHERE created_at < (NOW() - INTERVAL 30 DAY)');
        }
    }

    /**
     * Bloqueio progressivo da conta: a partir do limite, 5 min, 10, 20, 40… até 24 h.
     * Devolve a data/hora de desbloqueio ou null.
     */
    public static function lockAccountIfNeeded(int $userId, int $failedLogins): ?string
    {
        $max = Config::int('LOGIN_MAX_FAILS_PER_ACCOUNT', 5);
        if ($failedLogins < $max) {
            return null;
        }
        $level = $failedLogins - $max; // 0, 1, 2…
        $minutes = min(5 * (2 ** min($level, 9)), 1440);
        $until = date('Y-m-d H:i:s', time() + $minutes * 60);
        Database::run('UPDATE users SET locked_until = ? WHERE id = ?', [$until, $userId]);
        return $until;
    }
}
