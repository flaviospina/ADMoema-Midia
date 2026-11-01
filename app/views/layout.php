<?php
// app/views/layout.php — cabeçalho e rodapé comuns
declare(strict_types=1);

function render_header(string $title, array $options = []): void
{
    $user = $options['user'] ?? Auth::user();
    $bare = $options['bare'] ?? false; // telas de login, sem menu
    $active = $options['active'] ?? '';
    $pending = $user ? Auth::pendingRequirement() : null;
    ?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
    <title><?= e($title) ?> · Mídia ADMoema</title>
    <link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="assets/css/app.css?v=<?= APP_VERSION ?>">
</head>
<body class="<?= $bare ? 'is-bare' : '' ?>">
<?php if (!$bare && $user): ?>
<header class="topbar">
    <a class="brand" href="index.php">
        <span class="brand-mark" aria-hidden="true"></span>
        <span class="brand-text"><strong>Mídia</strong> ADMoema</span>
    </a>
    <nav class="nav" aria-label="Menu principal">
        <?php if ($pending === null): ?>
            <a href="index.php" class="<?= $active === 'eventos' ? 'is-active' : '' ?>">Eventos</a>
            <a href="novo-envio.php" class="<?= $active === 'novo' ? 'is-active' : '' ?>">Enviar fotos</a>
            <?php if ($user['role'] === 'admin'): ?>
                <a href="usuarios.php" class="<?= $active === 'usuarios' ? 'is-active' : '' ?>">Usuários</a>
                <a href="auditoria.php" class="<?= $active === 'auditoria' ? 'is-active' : '' ?>">Registro</a>
            <?php endif; ?>
        <?php endif; ?>
        <a href="conta.php" class="<?= $active === 'conta' ? 'is-active' : '' ?>">Minha conta</a>
        <form method="post" action="logout.php" class="nav-logout">
            <?= Csrf::field() ?>
            <button type="submit" class="link-button">Sair</button>
        </form>
    </nav>
</header>
<?php endif; ?>
<main class="<?= $bare ? 'auth-main' : 'container' ?>">
<?php foreach (take_flashes() as $flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
<?php endforeach;
}

function render_footer(array $scripts = []): void
{
    ?>
</main>
<footer class="footer">Ministério de Multimídia · AD Ministério do Belém · Setor 124 Moema</footer>
<?php foreach ($scripts as $script): ?>
<script src="assets/js/<?= e($script) ?>?v=<?= APP_VERSION ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
<?php
}
