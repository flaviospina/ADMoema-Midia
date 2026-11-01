<?php
// app/Session.php — sessão endurecida: cookie seguro, tempo de inatividade,
// tempo absoluto, vínculo com o navegador e revogação por versão.
declare(strict_types=1);

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $https = Security::isHttps();

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.sid_length', '48');
        ini_set('session.sid_bits_per_character', '6');
        ini_set('session.gc_maxlifetime', (string) (Config::int('SESSION_ABSOLUTE_HOURS', 12) * 3600));

        // Sessões gravadas na pasta do próprio sistema (não na /tmp compartilhada do servidor)
        $sessionDir = storage_path('tmp/sessions');
        if (!is_dir($sessionDir)) {
            @mkdir($sessionDir, 0700, true);
        }
        if (is_dir($sessionDir) && is_writable($sessionDir)) {
            session_save_path($sessionDir);
        }

        // Prefixo __Host- impede que outro subdomínio ou outro sistema sobrescreva o cookie
        session_name($https ? '__Host-admidia' : 'admidia');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();

        self::enforceLimits();
    }

    private static function enforceLimits(): void
    {
        $now = time();
        $fingerprint = hash('sha256', Security::userAgent());

        if (!isset($_SESSION['_created'])) {
            $_SESSION['_created'] = $now;
            $_SESSION['_last'] = $now;
            $_SESSION['_fp'] = $fingerprint;
            return;
        }

        $idle = Config::int('SESSION_IDLE_MINUTES', 30) * 60;
        $absolute = Config::int('SESSION_ABSOLUTE_HOURS', 12) * 3600;

        $expired = ($now - (int) $_SESSION['_last']) > $idle
            || ($now - (int) $_SESSION['_created']) > $absolute
            || !hash_equals((string) $_SESSION['_fp'], $fingerprint);

        if ($expired) {
            $hadUser = isset($_SESSION['user_id']);
            self::destroy();
            session_start();
            $_SESSION['_created'] = $now;
            $_SESSION['_last'] = $now;
            $_SESSION['_fp'] = $fingerprint;
            if ($hadUser) {
                flash('info', 'Sua sessão expirou. Entre novamente.');
            }
            return;
        }

        $_SESSION['_last'] = $now;

        // Troca o ID da sessão a cada 15 minutos (reduz risco de sequestro)
        if (($now - (int) ($_SESSION['_rotated'] ?? $_SESSION['_created'])) > 900) {
            session_regenerate_id(true);
            $_SESSION['_rotated'] = $now;
        }
    }

    /** Novo ID de sessão (usar sempre após login ou mudança de privilégio). */
    public static function regenerate(): void
    {
        session_regenerate_id(true);
        $_SESSION['_rotated'] = time();
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'secure'   => $params['secure'],
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
            session_destroy();
        }
    }
}
