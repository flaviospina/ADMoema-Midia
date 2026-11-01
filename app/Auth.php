<?php
// app/Auth.php — autenticação: senha + 2FA, bloqueios e controle de acesso
declare(strict_types=1);

final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    /** Hash fictício usado quando o e-mail não existe (mesmo tempo de resposta). */
    private static function dummyHash(): string
    {
        static $hash = null;
        return $hash ??= Security::hashPassword('usuario-inexistente-' . bin2hex(random_bytes(8)));
    }

    /**
     * Tenta autenticar. Retorna:
     *  ['status' => 'ok'] | ['status' => '2fa'] | ['status' => 'fail'] |
     *  ['status' => 'locked', 'until' => '...'] | ['status' => 'ip_blocked']
     */
    public static function attempt(string $email, string $password): array
    {
        $ip = Security::clientIp();
        $email = mb_strtolower(trim($email));

        if (RateLimiter::ipBlocked($ip)) {
            Logger::audit('login_ip_bloqueado', null, null, ['email_hash' => RateLimiter::emailHash($email)]);
            return ['status' => 'ip_blocked'];
        }

        $user = Database::one('SELECT * FROM users WHERE email = ?', [$email]);

        if (!$user) {
            password_verify($password, self::dummyHash());
            RateLimiter::record($ip, $email, false);
            return ['status' => 'fail'];
        }

        if ($user['locked_until'] !== null && strtotime($user['locked_until']) > time()) {
            RateLimiter::record($ip, $email, false);
            return ['status' => 'locked', 'until' => $user['locked_until']];
        }

        if (!password_verify($password, $user['password_hash']) || !(int) $user['is_active']) {
            RateLimiter::record($ip, $email, false);
            $fails = (int) $user['failed_logins'] + 1;
            Database::run('UPDATE users SET failed_logins = ? WHERE id = ?', [$fails, $user['id']]);
            $until = RateLimiter::lockAccountIfNeeded((int) $user['id'], $fails);
            Logger::audit('login_falhou', 'user', (int) $user['id'], ['tentativas' => $fails, 'ativo' => (int) $user['is_active']], (int) $user['id']);
            if ($until !== null) {
                Logger::audit('conta_bloqueada', 'user', (int) $user['id'], ['ate' => $until], (int) $user['id']);
            }
            return ['status' => 'fail'];
        }

        // Senha correta: atualiza o algoritmo de hash se necessário
        if (Security::passwordNeedsRehash($user['password_hash'])) {
            Database::run('UPDATE users SET password_hash = ? WHERE id = ?', [Security::hashPassword($password), $user['id']]);
        }

        if ((int) $user['totp_enabled']) {
            Session::regenerate();
            $_SESSION['pending_2fa'] = ['uid' => (int) $user['id'], 'exp' => time() + 300, 'tries' => 0, 'email' => $email];
            return ['status' => '2fa'];
        }

        RateLimiter::record($ip, $email, true);
        self::completeLogin($user);
        return ['status' => 'ok'];
    }

    public static function hasPending2fa(): bool
    {
        $p = $_SESSION['pending_2fa'] ?? null;
        if (!$p) {
            return false;
        }
        if ($p['exp'] < time() || $p['tries'] >= 5) {
            unset($_SESSION['pending_2fa']);
            return false;
        }
        return true;
    }

    /** Segunda etapa: código do aplicativo ou código de recuperação. */
    public static function verifyPending2fa(string $code): bool
    {
        if (!self::hasPending2fa()) {
            return false;
        }
        $_SESSION['pending_2fa']['tries']++;
        $pending = $_SESSION['pending_2fa'];
        $user = Database::one('SELECT * FROM users WHERE id = ? AND is_active = 1', [$pending['uid']]);
        if (!$user) {
            unset($_SESSION['pending_2fa']);
            return false;
        }

        $ok = false;
        $usedRecovery = false;
        $code = trim($code);

        if (preg_match('/^\d{6}$/', preg_replace('/\s/', '', $code))) {
            $secret = $user['totp_secret_enc'] ? Security::decrypt($user['totp_secret_enc']) : null;
            $lastStep = $user['totp_last_step'] !== null ? (int) $user['totp_last_step'] : null;
            $step = $secret ? Totp::verify($secret, $code, $lastStep) : null;
            if ($step !== null) {
                Database::run('UPDATE users SET totp_last_step = ? WHERE id = ?', [$step, $user['id']]);
                $ok = true;
            }
        } else {
            $ok = self::consumeRecoveryCode($user, $code);
            $usedRecovery = $ok;
        }

        if (!$ok) {
            RateLimiter::record(Security::clientIp(), $pending['email'], false);
            $fails = (int) $user['failed_logins'] + 1;
            Database::run('UPDATE users SET failed_logins = ? WHERE id = ?', [$fails, $user['id']]);
            RateLimiter::lockAccountIfNeeded((int) $user['id'], $fails);
            Logger::audit('2fa_falhou', 'user', (int) $user['id'], [], (int) $user['id']);
            if ($_SESSION['pending_2fa']['tries'] >= 5) {
                unset($_SESSION['pending_2fa']);
            }
            return false;
        }

        unset($_SESSION['pending_2fa']);
        RateLimiter::record(Security::clientIp(), $pending['email'], true);
        self::completeLogin($user);
        if ($usedRecovery) {
            Logger::audit('2fa_codigo_recuperacao_usado', 'user', (int) $user['id']);
            flash('info', 'Você entrou com um código de recuperação. Ele não vale mais. Se estiver sem o celular, gere novos códigos em "Minha conta".');
        }
        return true;
    }

    private static function consumeRecoveryCode(array $user, string $code): bool
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
        if (strlen($code) !== 8) {
            return false;
        }
        $formatted = substr($code, 0, 4) . '-' . substr($code, 4, 4);
        $hashes = json_decode((string) $user['recovery_codes'], true) ?: [];
        foreach ($hashes as $i => $hash) {
            if (password_verify($formatted, $hash)) {
                unset($hashes[$i]);
                Database::run('UPDATE users SET recovery_codes = ? WHERE id = ?', [json_encode(array_values($hashes)), $user['id']]);
                return true;
            }
        }
        return false;
    }

    private static function completeLogin(array $user): void
    {
        Session::regenerate();
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['session_version'] = (int) $user['session_version'];
        $_SESSION['_created'] = time();
        unset($_SESSION['_csrf']); // token novo após o login
        Database::run(
            'UPDATE users SET failed_logins = 0, locked_until = NULL, last_login_at = NOW(), last_login_ip = ? WHERE id = ?',
            [Security::clientIp(), $user['id']]
        );
        Logger::audit('login', 'user', (int) $user['id'], [], (int) $user['id']);
        self::$loaded = false;
    }

    /** Usuário logado (lido do banco a cada requisição, para refletir bloqueios na hora). */
    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;
        self::$user = null;
        $uid = $_SESSION['user_id'] ?? null;
        if (!$uid) {
            return null;
        }
        $user = Database::one('SELECT * FROM users WHERE id = ?', [$uid]);
        if (!$user || !(int) $user['is_active'] || (int) $user['session_version'] !== (int) ($_SESSION['session_version'] ?? 0)) {
            // Conta desativada, senha trocada ou sessão revogada pelo administrador
            Session::destroy();
            Session::start();
            flash('info', 'Sua sessão foi encerrada. Entre novamente.');
            return null;
        }
        return self::$user = $user;
    }

    public static function id(): ?int
    {
        $u = self::user();
        return $u ? (int) $u['id'] : null;
    }

    public static function isAdmin(): bool
    {
        $u = self::user();
        return $u !== null && $u['role'] === 'admin';
    }

    /** O usuário precisa trocar a senha ou ativar o 2FA antes de usar o sistema? */
    public static function pendingRequirement(): ?string
    {
        $u = self::user();
        if (!$u) {
            return null;
        }
        if ((int) $u['must_change_password']) {
            return 'senha';
        }
        if ($u['role'] === 'admin' && Config::bool('REQUIRE_2FA_ADMIN', true) && !(int) $u['totp_enabled']) {
            return '2fa';
        }
        return null;
    }

    /**
     * Exige login. Com $allowPending = true a página abre mesmo com pendências
     * (usado em "Minha conta" para trocar senha / ativar 2FA).
     */
    public static function requireLogin(bool $allowPending = false): array
    {
        $user = self::user();
        if (!$user) {
            if (is_api_request()) {
                json_response(['ok' => false, 'erro' => 'Sessão expirada. Entre novamente.'], 401);
            }
            redirect('login.php');
        }
        if (!$allowPending && ($pending = self::pendingRequirement()) !== null) {
            if (is_api_request()) {
                json_response(['ok' => false, 'erro' => 'Conclua a configuração da sua conta antes de continuar.'], 403);
            }
            redirect('conta.php?pendente=' . $pending);
        }
        return $user;
    }

    public static function requireAdmin(): array
    {
        $user = self::requireLogin();
        if ($user['role'] !== 'admin') {
            Logger::audit('acesso_negado', null, null, ['uri' => $_SERVER['REQUEST_URI'] ?? '']);
            if (is_api_request()) {
                json_response(['ok' => false, 'erro' => 'Acesso restrito aos administradores.'], 403);
            }
            http_response_code(403);
            flash('erro', 'Acesso restrito aos administradores.');
            redirect('index.php');
        }
        return $user;
    }

    public static function logout(): void
    {
        if (isset($_SESSION['user_id'])) {
            Logger::audit('logout', 'user', (int) $_SESSION['user_id']);
        }
        Session::destroy();
    }

    /** Encerra todas as sessões abertas do usuário (em qualquer aparelho). */
    public static function revokeSessions(int $userId): void
    {
        Database::run('UPDATE users SET session_version = session_version + 1 WHERE id = ?', [$userId]);
    }
}
