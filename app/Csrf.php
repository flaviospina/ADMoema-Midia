<?php
// app/Csrf.php — token anti-CSRF por sessão (formulários e chamadas fetch)
declare(strict_types=1);

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    public static function isValid(?string $token): bool
    {
        return is_string($token) && !empty($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $token);
    }

    /** Interrompe a requisição se o token estiver ausente ou errado. */
    public static function verify(): void
    {
        $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!self::isValid($token)) {
            Logger::audit('csrf_invalido', null, null, ['uri' => $_SERVER['REQUEST_URI'] ?? '']);
            if (is_api_request()) {
                json_response(['ok' => false, 'erro' => 'Sua sessão expirou. Recarregue a página.'], 403);
            }
            http_response_code(403);
            flash('erro', 'Sua sessão expirou ou o formulário ficou aberto por muito tempo. Tente de novo.');
            redirect($_SERVER['REQUEST_URI'] ?? 'index.php');
        }
    }
}
