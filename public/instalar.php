<?php
// public/instalar.php — verificação do servidor e criação do 1º administrador.
// Depois de usada, trava sozinha (arquivo storage/install.lock). Pode apagar este arquivo do servidor.
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

$lockFile = storage_path('install.lock');
if (is_file($lockFile)) {
    http_response_code(404);
    exit('Instalação já concluída.');
}

// ---------- Verificações do ambiente ----------
$checks = [];
$add = static function (string $label, bool $ok, string $hint = '', bool $required = true) use (&$checks): void {
    $checks[] = compact('label', 'ok', 'hint', 'required');
};

$add('PHP 8.1 ou superior (atual: ' . PHP_VERSION . ')', version_compare(PHP_VERSION, '8.1.0', '>='), 'No cPanel: "Selecionar versão do PHP" → 8.2.');
foreach (['pdo_mysql' => 'Banco de dados', 'sodium' => 'Criptografia do 2FA', 'fileinfo' => 'Validação dos arquivos', 'gd' => 'Miniaturas das fotos', 'mbstring' => 'Textos com acento'] as $ext => $why) {
    $add("Extensão $ext ($why)", extension_loaded($ext), 'Ative em cPanel → Selecionar versão do PHP → Extensões.');
}
$add('Extensão zip (baixar pasta inteira)', class_exists('ZipArchive'), 'Opcional. Sem ela, o botão "Baixar todas" fica indisponível.', false);
$add('Extensão exif (data e rotação das fotos)', function_exists('exif_read_data'), 'Opcional, mas recomendada.', false);
$add('Argon2id para senhas', defined('PASSWORD_ARGON2ID'), 'Opcional. Sem ele, o sistema usa bcrypt (também seguro).', false);
$add('Arquivo .env encontrado', is_file(APP_ROOT . '/.env'), 'Copie .env.example para .env e preencha.');

$appKeyOk = (bool) preg_match('/^[a-f0-9]{64}$/i', (string) Config::get('APP_KEY', ''));
$suggestedKey = $appKeyOk ? null : bin2hex(random_bytes(32));
$add('APP_KEY configurada', $appKeyOk, 'Copie a chave gerada abaixo para APP_KEY no .env.');

$token = (string) Config::get('INSTALL_TOKEN', '');
$add('INSTALL_TOKEN definido (mínimo 16 caracteres)', mb_strlen($token) >= 16, 'Defina uma frase longa em INSTALL_TOKEN no .env.');

$dbOk = false;
$tablesOk = false;
try {
    Database::pdo();
    $dbOk = true;
    $tablesOk = (int) Database::value(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('users','login_attempts','folders','photos','audit_log')"
    ) === 5;
} catch (Throwable $e) {
    Logger::error('Instalação — banco: ' . $e->getMessage());
}
$add('Conexão com o banco de dados', $dbOk, 'Confira DB_HOST, DB_NAME, DB_USER e DB_PASS no .env (o usuário precisa estar vinculado ao banco no cPanel).');
$add('Tabelas criadas', $tablesOk, 'Importe sql/schema.sql pelo phpMyAdmin.');

foreach (['fotos', 'thumbs', 'tmp', 'logs'] as $dir) {
    $path = storage_path($dir);
    if (!is_dir($path)) {
        @mkdir($path, 0750, true);
    }
    $add("Pasta de armazenamento /$dir com permissão de escrita", is_dir($path) && is_writable($path), 'Ajuste a permissão para 755 no Gerenciador de Arquivos.');
}
$storageInPublic = str_starts_with(realpath(storage_path()) ?: '', realpath(__DIR__) ?: '-');
$add('Pasta storage fora da pasta pública', !$storageInPublic, 'A pasta storage não pode ficar dentro de public/.');

$httpsOk = Security::isHttps() || Config::isLocal();
$add('Conexão HTTPS (cadeado)', $httpsOk, 'Ative o SSL gratuito em cPanel → SSL/TLS Status (AutoSSL).');

$hasUsers = $tablesOk && (int) Database::value('SELECT COUNT(*) FROM users') > 0;
$allRequired = !in_array(false, array_map(static fn($c) => $c['ok'] || !$c['required'], $checks), true);

// ---------- Criação do administrador ----------
$errors = [];
if ($allRequired && !$hasUsers && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::verify();
    $ip = Security::clientIp();
    if (RateLimiter::ipBlocked($ip)) {
        $errors[] = 'Muitas tentativas. Aguarde alguns minutos.';
    } elseif (!hash_equals($token, (string) ($_POST['token'] ?? ''))) {
        RateLimiter::record($ip, 'instalacao', false);
        $errors[] = 'Token de instalação incorreto.';
    } else {
        $name = trim(preg_replace('/\s+/u', ' ', (string) ($_POST['nome'] ?? '')));
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['senha'] ?? '');
        if (mb_strlen($name) < 3) {
            $errors[] = 'Informe o nome completo.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'E-mail inválido.';
        }
        if (!hash_equals($password, (string) ($_POST['confirmar'] ?? ''))) {
            $errors[] = 'As senhas não conferem.';
        }
        $errors = array_merge($errors, PasswordPolicy::validate($password, [$name, $email]));
        if (!$errors) {
            Database::run(
                "INSERT INTO users (name, email, password_hash, role, must_change_password, password_changed_at) VALUES (?,?,?,'admin',0,NOW())",
                [$name, $email, Security::hashPassword($password)]
            );
            $id = Database::lastId();
            Logger::audit('instalacao', 'user', $id, ['email' => $email], $id);
            file_put_contents($lockFile, date('c') . "\n", LOCK_EX);
            flash('sucesso', 'Instalação concluída! Entre com o e-mail e a senha que você acabou de criar.');
            redirect('login.php');
        }
    }
}

render_header('Instalação', ['bare' => true]);
?>
<section class="auth-card auth-card-wide">
    <div class="auth-brand">
        <span class="brand-mark brand-mark-lg" aria-hidden="true"></span>
        <h1>Instalação</h1>
        <p>Mídia ADMoema · Envio de fotos</p>
    </div>

    <h2 class="h3">1. Verificação do servidor</h2>
    <ul class="checklist">
        <?php foreach ($checks as $c): ?>
            <li class="<?= $c['ok'] ? 'ok' : ($c['required'] ? 'fail' : 'warn') ?>">
                <span><?= e($c['label']) ?></span>
                <?php if (!$c['ok'] && $c['hint']): ?><small><?= e($c['hint']) ?></small><?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if ($suggestedKey): ?>
        <div class="alert alert-aviso">Chave gerada para você (cole no .env e recarregue esta página):<br>
            <code class="js-copy">APP_KEY=<?= e($suggestedKey) ?></code></div>
    <?php endif; ?>

    <?php if ($hasUsers): ?>
        <div class="alert alert-info">Já existe usuário cadastrado. <a href="login.php">Ir para o login</a>.</div>
    <?php elseif (!$allRequired): ?>
        <div class="alert alert-erro">Corrija os itens em vermelho e recarregue a página.</div>
    <?php else: ?>
        <h2 class="h3">2. Primeiro administrador</h2>
        <?php foreach ($errors as $err): ?><div class="alert alert-erro"><?= e($err) ?></div><?php endforeach; ?>
        <form method="post" action="instalar.php" autocomplete="off">
            <?= Csrf::field() ?>
            <label class="field"><span>Token de instalação (INSTALL_TOKEN do .env)</span><input type="password" name="token" required></label>
            <label class="field"><span>Nome completo</span><input type="text" name="nome" required maxlength="120" value="<?= e($_POST['nome'] ?? '') ?>"></label>
            <label class="field"><span>E-mail</span><input type="email" name="email" required maxlength="190" value="<?= e($_POST['email'] ?? '') ?>" autocomplete="username"></label>
            <label class="field"><span>Senha</span><input type="password" name="senha" required minlength="<?= PasswordPolicy::MIN_LENGTH ?>" autocomplete="new-password" class="js-strength"></label>
            <div class="strength" aria-live="polite"><span class="strength-bar"></span><span class="strength-text small muted"></span></div>
            <label class="field"><span>Repita a senha</span><input type="password" name="confirmar" required autocomplete="new-password"></label>
            <p class="muted small">No primeiro login você vai configurar a verificação em duas etapas no celular.</p>
            <button type="submit" class="btn btn-primary btn-block">Concluir instalação</button>
        </form>
    <?php endif; ?>
</section>
<?php render_footer(['app.js']);
