<?php
// app/bootstrap.php — carregado no início de toda página
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('APP_VERSION', '1.1.0');

require __DIR__ . '/config.php';
require __DIR__ . '/views/layout.php';
Config::load(APP_ROOT . '/.env');

date_default_timezone_set('America/Sao_Paulo');
mb_internal_encoding('UTF-8');

// Carrega as classes da pasta app/ sob demanda
spl_autoload_register(static function (string $class): void {
    $file = __DIR__ . '/' . basename(str_replace('\\', '/', $class)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// Erros: nunca exibidos na tela em produção, sempre registrados em arquivo
error_reporting(E_ALL);
ini_set('display_errors', Config::isLocal() ? '1' : '0');
ini_set('log_errors', '1');

set_exception_handler(static function (Throwable $e): void {
    Logger::error(get_class($e) . ': ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
    if (is_api_request()) {
        json_response(['ok' => false, 'erro' => 'Erro interno. Tente novamente em instantes.'], 500);
    }
    http_response_code(500);
    $detail = Config::isLocal() ? '<pre>' . e($e->getMessage()) . '</pre>' : '';
    echo '<!doctype html><meta charset="utf-8"><title>Erro</title><body style="font-family:sans-serif;padding:2rem">'
        . '<h1>Algo deu errado</h1><p>O erro foi registrado. Tente novamente em instantes.</p>' . $detail . '</body>';
    exit;
});

// ---------------------------------------------------------------------
// Funções auxiliares
// ---------------------------------------------------------------------

/** Caminho dentro da pasta de armazenamento (STORAGE_PATH ou ./storage). */
function storage_path(string $relative = ''): string
{
    static $base = null;
    if ($base === null) {
        $configured = (string) Config::get('STORAGE_PATH', '');
        $base = rtrim($configured !== '' ? $configured : APP_ROOT . '/storage', '/');
    }
    return $relative === '' ? $base : $base . '/' . ltrim($relative, '/');
}

/** Escapa texto para HTML (usar em TODA saída de dado do usuário). */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $to): never
{
    // Somente caminhos internos (evita redirecionamento aberto)
    if (preg_match('#^(https?:)?//#i', $to)) {
        $to = 'index.php';
    }
    header('Location: ' . $to, true, 303);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $messages;
}

function is_api_request(): bool
{
    return str_contains((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/api/');
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        if (is_api_request()) {
            json_response(['ok' => false, 'erro' => 'Método não permitido.'], 405);
        }
        http_response_code(405);
        exit('Método não permitido.');
    }
    Csrf::verify();
}

function format_bytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    $value = (float) $bytes;
    while ($value >= 1024 && $i < count($units) - 1) {
        $value /= 1024;
        $i++;
    }
    return number_format($value, $i === 0 ? 0 : 1, ',', '.') . ' ' . $units[$i];
}

function format_date_br(string $date): string
{
    return date('d/m/Y', strtotime($date));
}

// ---------------------------------------------------------------------
// Início da requisição
// ---------------------------------------------------------------------
Security::enforceHttps();
Security::sendHeaders();
header('Cache-Control: no-store, private');
// Endpoints públicos (ex.: foto temporária para o Instagram) não abrem sessão
if (!defined('NO_SESSION')) {
    Session::start();
}
