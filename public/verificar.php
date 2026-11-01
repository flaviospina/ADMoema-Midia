<?php
// public/verificar.php — 2ª etapa do login: código do aplicativo autenticador
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

if (Auth::user()) {
    redirect('index.php');
}
if (!Auth::hasPending2fa()) {
    flash('info', 'Entre novamente com e-mail e senha.');
    redirect('login.php');
}

$error = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::verify();
    if (Auth::verifyPending2fa((string) ($_POST['codigo'] ?? ''))) {
        redirect('index.php');
    }
    if (!Auth::hasPending2fa()) {
        flash('erro', 'Muitas tentativas com código inválido. Entre novamente.');
        redirect('login.php');
    }
    $error = 'Código inválido ou expirado. Confira o horário do celular e tente o código atual.';
}

render_header('Verificação em duas etapas', ['bare' => true]);
?>
<section class="auth-card">
    <div class="auth-brand">
        <span class="brand-mark brand-mark-lg" aria-hidden="true"></span>
        <h1>Verificação em duas etapas</h1>
        <p>Abra o aplicativo autenticador e digite o código de 6 dígitos de <strong>Mídia ADMoema</strong>.</p>
    </div>
    <?php if ($error): ?>
        <div class="alert alert-erro" role="alert"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" action="verificar.php" autocomplete="off">
        <?= Csrf::field() ?>
        <label class="field">
            <span>Código</span>
            <input type="text" name="codigo" class="code-input" required autofocus
                   inputmode="numeric" autocomplete="one-time-code" maxlength="9" placeholder="000000">
        </label>
        <button type="submit" class="btn btn-primary btn-block">Confirmar</button>
    </form>
    <details class="small">
        <summary>Estou sem o celular</summary>
        <p class="muted">Digite no campo acima um dos seus códigos de recuperação (formato XXXX-XXXX). Cada código funciona uma única vez.</p>
    </details>
    <p class="center small"><a href="login.php">Voltar</a></p>
</section>
<?php render_footer();
