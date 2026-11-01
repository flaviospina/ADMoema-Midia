<?php
// public/login.php — entrada no sistema (1ª etapa: e-mail e senha)
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

if (Auth::user()) {
    redirect('index.php');
}

$error = null;
$email = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::verify();
    $email = mb_substr(trim((string) ($_POST['email'] ?? '')), 0, 190);
    $password = (string) ($_POST['senha'] ?? '');

    // Campo-armadilha: robôs preenchem, pessoas não veem
    if (($_POST['site'] ?? '') !== '') {
        Logger::audit('login_robo', null, null, []);
        sleep(2);
        $error = 'E-mail ou senha incorretos.';
    } elseif ($email === '' || $password === '' || mb_strlen($password) > 256) {
        $error = 'Informe e-mail e senha.';
    } else {
        $result = Auth::attempt($email, $password);
        switch ($result['status']) {
            case 'ok':
                redirect('index.php');
            case '2fa':
                redirect('verificar.php');
            case 'locked':
                $error = 'Por segurança, esta conta está bloqueada temporariamente. Tente novamente após '
                    . date('H:i', strtotime($result['until'])) . ' ou fale com o líder da mídia.';
                break;
            case 'ip_blocked':
                $error = 'Muitas tentativas a partir desta conexão. Aguarde ' . Config::int('LOGIN_WINDOW_MINUTES', 15) . ' minutos.';
                break;
            default:
                // Mensagem genérica: não revela se o e-mail existe
                usleep(random_int(200000, 500000));
                $error = 'E-mail ou senha incorretos.';
        }
    }
}

render_header('Entrar', ['bare' => true]);
?>
<section class="auth-card">
    <div class="auth-brand">
        <span class="brand-mark brand-mark-lg" aria-hidden="true"></span>
        <h1>Mídia ADMoema</h1>
        <p>Ministério de Multimídia · Setor 124 Moema</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-erro" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="login.php" autocomplete="on" novalidate>
        <?= Csrf::field() ?>
        <div class="hp" aria-hidden="true">
            <label>Não preencha <input type="text" name="site" tabindex="-1" autocomplete="off"></label>
        </div>
        <label class="field">
            <span>E-mail</span>
            <input type="email" name="email" value="<?= e($email) ?>" required autocomplete="username" inputmode="email" autofocus>
        </label>
        <label class="field">
            <span>Senha</span>
            <span class="password-wrap">
                <input type="password" name="senha" required autocomplete="current-password" maxlength="256">
                <button type="button" class="toggle-password" aria-label="Mostrar senha">Mostrar</button>
            </span>
        </label>
        <button type="submit" class="btn btn-primary btn-block">Entrar</button>
    </form>
    <p class="muted small center">Esqueceu a senha? Peça ao líder da mídia para gerar uma senha provisória.</p>
</section>
<?php render_footer(['app.js']);
