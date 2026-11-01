<?php
// public/index.php — painel: pastas de eventos
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

$user = Auth::requireLogin();

$years = FolderService::years();
$year = filter_var($_GET['ano'] ?? null, FILTER_VALIDATE_INT) ?: ($years[0] ?? (int) date('Y'));
$folders = FolderService::listAll($year);

render_header('Eventos', ['active' => 'eventos']);
?>
<div class="page-head">
    <div>
        <h1>Eventos</h1>
        <p class="muted">Fotos dos cultos e eventos, organizadas por data.</p>
    </div>
    <?php if (Auth::canUpload($user)): ?>
        <a href="novo-envio.php" class="btn btn-primary">+ Enviar fotos</a>
    <?php endif; ?>
</div>

<?php if ($years): ?>
<nav class="chips" aria-label="Filtrar por ano">
    <?php foreach ($years as $y): ?>
        <a href="index.php?ano=<?= $y ?>" class="chip <?= $y === $year ? 'is-active' : '' ?>"><?= $y ?></a>
    <?php endforeach; ?>
</nav>
<?php endif; ?>

<?php if (!$folders): ?>
    <div class="empty">
        <p><strong>Nenhum evento em <?= e($year) ?> ainda.</strong></p>
        <p class="muted"><?= Auth::canUpload($user) ? 'Clique em “Enviar fotos” para criar a primeira pasta.' : 'As fotos aparecem aqui assim que a equipe da mídia enviar.' ?></p>
    </div>
<?php else: ?>
    <ul class="folder-grid">
        <?php foreach ($folders as $f): ?>
            <li>
                <a class="folder-card" href="pasta.php?id=<?= (int) $f['id'] ?>">
                    <span class="folder-cover">
                        <?php if ($f['cover_id']): ?>
                            <img src="api/foto.php?id=<?= (int) $f['cover_id'] ?>&amp;v=thumb" alt="" loading="lazy">
                        <?php else: ?>
                            <span class="folder-cover-empty" aria-hidden="true"></span>
                        <?php endif; ?>
                    </span>
                    <span class="folder-info">
                        <span class="folder-date"><?= e(format_date_br($f['event_date'])) ?> · <?= e(EventNaming::weekdayName(new DateTimeImmutable($f['event_date']))) ?></span>
                        <span class="folder-name"><?= e($f['name']) ?></span>
                        <span class="folder-meta"><?= (int) $f['total'] ?> foto<?= (int) $f['total'] === 1 ? '' : 's' ?></span>
                    </span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
<?php render_footer(['app.js']);
