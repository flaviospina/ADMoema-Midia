<?php
// public/pasta.php — fotos de um evento + área de envio
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

$user = Auth::requireLogin();
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$folder = $id ? FolderService::find($id) : null;
if (!$folder) {
    http_response_code(404);
    flash('erro', 'Pasta não encontrada.');
    redirect('index.php');
}

$photos = PhotoService::listByFolder((int) $folder['id']);
$totalBytes = array_sum(array_map(static fn($p) => (int) $p['size_bytes'], $photos));
$date = new DateTimeImmutable($folder['event_date']);
$isAdmin = $user['role'] === 'admin';

render_header($folder['name'], ['active' => 'eventos']);
?>
<div class="page-head">
    <div>
        <p class="crumb"><a href="index.php?ano=<?= e($date->format('Y')) ?>">Eventos</a> / <?= e(format_date_br($folder['event_date'])) ?></p>
        <h1><?= e($folder['name']) ?></h1>
        <p class="muted"><?= e(ucfirst(EventNaming::weekdayName($date))) ?>, <?= e(format_date_br($folder['event_date'])) ?> ·
            <span id="photo-count"><?= count($photos) ?></span> foto(s) · <?= e(format_bytes($totalBytes)) ?></p>
    </div>
    <?php if ($photos): ?>
        <a class="btn btn-ghost" href="api/baixar-zip.php?id=<?= (int) $folder['id'] ?>">Baixar todas (.zip)</a>
    <?php endif; ?>
</div>

<section class="panel uploader" id="uploader"
         data-folder="<?= (int) $folder['id'] ?>"
         data-chunk="<?= PhotoService::chunkBytes() ?>"
         data-max="<?= PhotoService::maxBytes() ?>"
         data-ext="<?= e(implode(',', PhotoService::allowedExtensions())) ?>">
    <label class="dropzone" id="dropzone">
        <input type="file" id="file-input" multiple accept=".jpg,.jpeg,.png,.webp,.heic,.heif,image/jpeg,image/png,image/webp,image/heic,image/heif">
        <span class="dropzone-icon" aria-hidden="true"></span>
        <strong>Toque para escolher as fotos</strong>
        <span class="muted small">ou arraste para cá · JPG, PNG, WEBP ou HEIC · até <?= Config::int('UPLOAD_MAX_MB', 40) ?> MB cada</span>
    </label>
    <div class="queue-head" id="queue-head" hidden>
        <span id="queue-summary"></span>
        <button type="button" class="btn btn-small btn-ghost" id="btn-clear" hidden>Limpar lista</button>
    </div>
    <ul class="queue" id="queue"></ul>
</section>

<?php if (!$photos): ?>
    <div class="empty" id="empty-gallery">
        <p><strong>Nenhuma foto nesta pasta ainda.</strong></p>
        <p class="muted">As fotos enviadas aparecem aqui.</p>
    </div>
<?php endif; ?>

<ul class="gallery" id="gallery">
    <?php foreach ($photos as $p): ?>
        <li class="photo" id="photo-<?= (int) $p['id'] ?>">
            <a href="api/foto.php?id=<?= (int) $p['id'] ?>&amp;v=full" target="_blank" rel="noopener" class="photo-link">
                <?php if ((int) $p['has_thumb']): ?>
                    <img src="api/foto.php?id=<?= (int) $p['id'] ?>&amp;v=thumb" alt="<?= e($p['original_name']) ?>" loading="lazy">
                <?php else: ?>
                    <span class="photo-noprev"><?= e(strtoupper(pathinfo($p['stored_name'], PATHINFO_EXTENSION))) ?></span>
                <?php endif; ?>
            </a>
            <div class="photo-meta">
                <span class="photo-name" title="<?= e($p['original_name']) ?>"><?= e($p['original_name']) ?></span>
                <span class="muted small">por <?= e($p['user_name']) ?></span>
                <span class="photo-actions">
                    <a href="api/foto.php?id=<?= (int) $p['id'] ?>&amp;v=download" class="small">Baixar</a>
                    <?php if ($isAdmin || (int) $p['user_id'] === (int) $user['id']): ?>
                        <button type="button" class="link-button small danger js-delete" data-id="<?= (int) $p['id'] ?>" data-name="<?= e($p['original_name']) ?>">Excluir</button>
                    <?php endif; ?>
                </span>
            </div>
        </li>
    <?php endforeach; ?>
</ul>
<?php render_footer(['app.js', 'uploader.js']);
