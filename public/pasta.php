<?php
// public/pasta.php — fotos de um evento: galeria, visualizador em tela cheia,
// envio (equipe da mídia), edição e publicação nas redes sociais
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
$canUpload = Auth::canUpload($user);
$accounts = $canUpload ? SocialService::publicAccounts((int) $user['id']) : [];

render_header($folder['name'], ['active' => 'eventos']);
?>
<div class="page-head">
    <div>
        <p class="crumb"><a href="index.php?ano=<?= e($date->format('Y')) ?>">Eventos</a> / <?= e(format_date_br($folder['event_date'])) ?></p>
        <h1><?= e($folder['name']) ?></h1>
        <p class="muted"><?= e(ucfirst(EventNaming::weekdayName($date))) ?>, <?= e(format_date_br($folder['event_date'])) ?> ·
            <span id="photo-count"><?= count($photos) ?></span> foto(s)<?= $canUpload ? ' · ' . e(format_bytes($totalBytes)) : '' ?></p>
    </div>
    <?php if ($photos && $canUpload): ?>
        <a class="btn btn-ghost" href="api/baixar-zip.php?id=<?= (int) $folder['id'] ?>">Baixar todas (.zip)</a>
    <?php endif; ?>
</div>

<?php if ($canUpload): ?>
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
<?php endif; ?>

<?php if (!$photos): ?>
    <div class="empty" id="empty-gallery">
        <p><strong>Nenhuma foto nesta pasta ainda.</strong></p>
        <p class="muted"><?= $canUpload ? 'As fotos enviadas aparecem aqui.' : 'Assim que a equipe da mídia enviar, as fotos aparecem aqui.' ?></p>
    </div>
<?php else: ?>
    <p class="muted small gallery-hint">Toque em uma foto para ver em tela cheia, melhorar a qualidade<?= $canUpload ? ' ou publicar nas redes' : '' ?>.</p>
<?php endif; ?>

<ul class="gallery" id="gallery">
    <?php foreach ($photos as $p):
        $isHeic = $p['mime'] === 'image/heic';
        $canDelete = $isAdmin || (int) $p['user_id'] === (int) $user['id']; ?>
        <li class="photo" id="photo-<?= (int) $p['id'] ?>"
            data-id="<?= (int) $p['id'] ?>"
            data-name="<?= e($p['original_name']) ?>"
            data-by="<?= e($p['user_name']) ?>"
            data-heic="<?= $isHeic ? '1' : '0' ?>"
            data-edited="<?= $p['parent_id'] ? '1' : '0' ?>"
            data-posts="<?= (int) $p['posts'] ?>"
            data-delete="<?= $canDelete ? '1' : '0' ?>">
            <a href="api/foto.php?id=<?= (int) $p['id'] ?>&amp;v=view" class="photo-link js-open">
                <?php if ((int) $p['has_thumb']): ?>
                    <img src="api/foto.php?id=<?= (int) $p['id'] ?>&amp;v=thumb" alt="<?= e($p['original_name']) ?>" loading="lazy">
                <?php else: ?>
                    <span class="photo-noprev"><?= e(strtoupper(pathinfo($p['stored_name'], PATHINFO_EXTENSION))) ?></span>
                <?php endif; ?>
                <span class="photo-badges">
                    <?php if ($p['parent_id']): ?><span class="pbadge">Editada</span><?php endif; ?>
                    <?php if ((int) $p['posts'] > 0): ?><span class="pbadge pbadge-ok">Publicada</span><?php endif; ?>
                </span>
            </a>
            <div class="photo-meta">
                <span class="photo-name" title="<?= e($p['original_name']) ?>"><?= e($p['original_name']) ?></span>
                <span class="muted small">por <?= e($p['user_name']) ?></span>
            </div>
        </li>
    <?php endforeach; ?>
</ul>

<!-- Visualizador em tela cheia -->
<div class="lightbox" id="lightbox" hidden role="dialog" aria-modal="true" aria-label="Visualizar foto"
     data-can-upload="<?= $canUpload ? '1' : '0' ?>"
     data-has-social="<?= $accounts ? '1' : '0' ?>"
     data-open="<?= (int) ($_GET['foto'] ?? 0) ?>"
     data-publish="<?= ($_GET['publicar'] ?? '') === '1' ? '1' : '0' ?>">
    <div class="lb-top">
        <div class="lb-title"><strong id="lb-name"></strong><span id="lb-info" class="small"></span></div>
        <button type="button" class="lb-icon" id="lb-close" aria-label="Fechar">✕</button>
    </div>
    <div class="lb-stage" id="lb-stage">
        <button type="button" class="lb-nav lb-prev" id="lb-prev" aria-label="Foto anterior">‹</button>
        <img id="lb-img" alt="">
        <div class="lb-heic" id="lb-heic" hidden>Pré-visualização indisponível para HEIC. Use “Baixar”.</div>
        <button type="button" class="lb-nav lb-next" id="lb-next" aria-label="Próxima foto">›</button>
    </div>
    <div class="lb-actions">
        <a class="lb-btn" id="lb-edit" href="#"><span class="lb-ico ico-magic" aria-hidden="true"></span>Melhorar foto</a>
        <button type="button" class="lb-btn" id="lb-share" hidden><span class="lb-ico ico-share" aria-hidden="true"></span>Compartilhar</button>
        <?php if ($canUpload): ?>
            <button type="button" class="lb-btn" id="lb-publish"><span class="lb-ico ico-send" aria-hidden="true"></span>Publicar nas redes</button>
        <?php endif; ?>
        <a class="lb-btn" id="lb-download" href="#"><span class="lb-ico ico-down" aria-hidden="true"></span>Baixar</a>
        <button type="button" class="lb-btn lb-danger" id="lb-delete" hidden><span class="lb-ico ico-trash" aria-hidden="true"></span>Excluir</button>
    </div>
</div>

<?php if ($canUpload): ?>
<!-- Publicar nas redes sociais -->
<div class="modal" id="publish-modal" hidden role="dialog" aria-modal="true" aria-labelledby="pub-title"
     data-accounts="<?= e(json_encode($accounts, JSON_UNESCAPED_UNICODE)) ?>"
     data-signature="<?= e((string) ($user['social_signature'] ?? '')) ?>">
    <div class="modal-box">
        <h2 id="pub-title">Publicar nas redes sociais</h2>
        <?php if (!$accounts): ?>
            <p>Você ainda não conectou nenhuma rede social.</p>
            <p class="muted small">Cada pessoa conecta a própria conta do Facebook ou do Instagram em <strong>Minha conta → Redes sociais</strong>. Leva uns 10 minutos na primeira vez.</p>
            <div class="actions">
                <a class="btn btn-primary" href="conta.php#redes">Conectar minhas redes</a>
                <button type="button" class="btn btn-ghost js-modal-close">Fechar</button>
            </div>
        <?php else: ?>
            <div class="pub-grid">
                <img id="pub-preview" alt="" class="pub-preview">
                <form id="pub-form" class="pub-form">
                    <fieldset class="options">
                        <legend class="field-label">Publicar em</legend>
                        <?php foreach ($accounts as $a): ?>
                            <label class="option">
                                <input type="checkbox" name="redes[]" value="<?= e($a['network']) ?>" checked>
                                <span><strong><?= e($a['label']) ?></strong> · <?= e($a['name']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                    <label class="field">
                        <span>Legenda</span>
                        <textarea name="legenda" id="pub-caption" rows="6" maxlength="<?= SocialService::CAPTION_MAX ?>"></textarea>
                    </label>
                    <p class="muted small"><span id="pub-count">0</span>/<?= SocialService::CAPTION_MAX ?> caracteres · No Instagram, fotos muito altas ou muito largas são recortadas ao centro (de 4:5 a 1,91:1).</p>
                    <div id="pub-result" class="pub-result" hidden></div>
                    <div class="actions">
                        <button type="submit" class="btn btn-primary" id="pub-submit">Publicar agora</button>
                        <button type="button" class="btn btn-ghost js-modal-close">Cancelar</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
<?php render_footer(array_values(array_filter(['app.js', $canUpload ? 'chunk-upload.js' : null, $canUpload ? 'uploader.js' : null, 'viewer.js'])));
