<?php
// public/editar.php — editor para melhorar a qualidade da foto (qualquer usuário logado).
// O processamento é feito no navegador; o original nunca é alterado.
// Equipe da mídia pode salvar a versão melhorada na pasta e publicar; todos podem baixar.
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

$user = Auth::requireLogin();
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$photo = $id ? PhotoService::find($id) : null;
$folder = $photo ? FolderService::find((int) $photo['folder_id']) : null;
if (!$photo || !$folder) {
    flash('erro', 'Foto não encontrada.');
    redirect('index.php');
}

$canUpload = Auth::canUpload($user);
$hasSocial = $canUpload && SocialService::accounts((int) $user['id']);
// Equipe edita a partir do original (qualidade máxima); visualizador, da versão de tela
$source = 'api/foto.php?id=' . (int) $photo['id'] . '&v=' . ($canUpload ? 'full' : 'view');
// Sempre parte da foto original (se esta já for uma edição, a nova versão fica ligada à mesma original)
$parentId = (int) ($photo['parent_id'] ?: $photo['id']);

/** Ícones das ferramentas (SVG simples, cor herdada do texto). */
$icon = static function (string $d): string {
    return '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $d . '</svg>';
};
$tools = [
    'auto'        => ['Automático', $icon('<path d="M5 19 19 5"/><path d="M15 4l1-2 1 2 2 1-2 1-1 2-1-2-2-1z"/><path d="M5 8l.7-1.4L7 6l-1.3-.6L5 4l-.7 1.4L3 6l1.3.6z"/>')],
    'brilho'      => ['Brilho', $icon('<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>')],
    'contraste'   => ['Contraste', $icon('<circle cx="12" cy="12" r="9"/><path d="M12 3v18" /><path d="M12 3a9 9 0 0 1 0 18z" fill="currentColor"/>')],
    'cor'         => ['Cor', $icon('<path d="M12 3c3 4 6 7 6 11a6 6 0 0 1-12 0c0-4 3-7 6-11z"/>')],
    'temperatura' => ['Temperatura', $icon('<path d="M10 14V5a2 2 0 1 1 4 0v9a4 4 0 1 1-4 0z"/><path d="M12 9v7"/>')],
    'luz'         => ['Luz e sombra', $icon('<path d="M3 18h18"/><path d="M6 18a6 6 0 0 1 12 0"/><path d="M12 6V3M5.6 9.6 3.5 7.5M18.4 9.6l2.1-2.1"/>')],
    'nitidez'     => ['Nitidez', $icon('<path d="M12 3l9 16H3z"/><path d="M12 10v4"/>')],
    'vinheta'     => ['Vinheta', $icon('<rect x="3" y="4" width="18" height="16" rx="3"/><ellipse cx="12" cy="12" rx="5" ry="4"/>')],
    'estilos'     => ['Estilos', $icon('<rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="8" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/><rect x="13" y="13" width="8" height="8" rx="1.5"/>')],
    'recortar'    => ['Recortar', $icon('<path d="M6 2v16h16"/><path d="M2 6h16v16"/>')],
    'girar'       => ['Girar', $icon('<path d="M20 12a8 8 0 1 1-2.3-5.7"/><path d="M20 4v5h-5"/>')],
];

render_header('Melhorar foto', ['full' => true]);
?>
<section class="editor" id="editor"
         data-photo="<?= (int) $photo['id'] ?>"
         data-parent="<?= $parentId ?>"
         data-folder="<?= (int) $folder['id'] ?>"
         data-name="<?= e($photo['original_name']) ?>"
         data-src="<?= e($source) ?>"
         data-can-save="<?= $canUpload ? '1' : '0' ?>"
         data-has-social="<?= $hasSocial ? '1' : '0' ?>"
         data-chunk="<?= PhotoService::chunkBytes() ?>">

    <div class="ed-top">
        <a class="ed-back" href="pasta.php?id=<?= (int) $folder['id'] ?>&amp;foto=<?= (int) $photo['id'] ?>">‹ Voltar</a>
        <div class="ed-title"><strong>Melhorar foto</strong><span class="muted small"><?= e($photo['original_name']) ?></span></div>
        <div class="ed-top-actions">
            <button type="button" class="ed-chip" id="ed-undo" disabled>Desfazer</button>
            <button type="button" class="ed-chip" id="ed-reset" disabled>Original</button>
        </div>
    </div>

    <div class="ed-stage" id="ed-stage">
        <canvas id="ed-canvas" aria-label="Pré-visualização da foto"></canvas>
        <div class="ed-loading" id="ed-loading">Carregando a foto…</div>
        <button type="button" class="ed-compare" id="ed-compare" hidden>Segure para ver o antes</button>
        <p class="ed-crop-hint" id="ed-crop-hint" hidden>Arraste a foto para ajustar o enquadramento</p>
    </div>

    <div class="ed-panel" id="ed-panel">
        <!-- Controles de cada ferramenta -->
        <div class="ed-controls" data-tool="auto" hidden>
            <p class="small">Corrige automaticamente cor, exposição, sombras e nitidez, analisando a foto. Depois você pode ajustar cada item.</p>
            <button type="button" class="btn btn-primary" id="ed-auto">✨ Aplicar melhoria automática</button>
        </div>
        <div class="ed-controls" data-tool="brilho" hidden>
            <label class="ed-slider"><span>Exposição <output></output></span><input type="range" min="-100" max="100" value="0" data-param="exposure"></label>
            <label class="ed-slider"><span>Brilho <output></output></span><input type="range" min="-100" max="100" value="0" data-param="brightness"></label>
        </div>
        <div class="ed-controls" data-tool="contraste" hidden>
            <label class="ed-slider"><span>Contraste <output></output></span><input type="range" min="-100" max="100" value="0" data-param="contrast"></label>
            <label class="ed-slider"><span>Esmaecer (efeito fosco) <output></output></span><input type="range" min="0" max="100" value="0" data-param="fade"></label>
        </div>
        <div class="ed-controls" data-tool="cor" hidden>
            <label class="ed-slider"><span>Saturação <output></output></span><input type="range" min="-100" max="100" value="0" data-param="saturation"></label>
            <label class="ed-slider"><span>Vibração (realça cores apagadas) <output></output></span><input type="range" min="-100" max="100" value="0" data-param="vibrance"></label>
        </div>
        <div class="ed-controls" data-tool="temperatura" hidden>
            <label class="ed-slider ed-slider-temp"><span>Temperatura <output></output></span><input type="range" min="-100" max="100" value="0" data-param="temperature"></label>
            <label class="ed-slider ed-slider-tint"><span>Matiz (verde ↔ magenta) <output></output></span><input type="range" min="-100" max="100" value="0" data-param="tint"></label>
        </div>
        <div class="ed-controls" data-tool="luz" hidden>
            <label class="ed-slider"><span>Sombras (clareia partes escuras) <output></output></span><input type="range" min="-100" max="100" value="0" data-param="shadows"></label>
            <label class="ed-slider"><span>Realces (recupera partes estouradas) <output></output></span><input type="range" min="-100" max="100" value="0" data-param="highlights"></label>
        </div>
        <div class="ed-controls" data-tool="nitidez" hidden>
            <label class="ed-slider"><span>Nitidez <output></output></span><input type="range" min="0" max="100" value="0" data-param="sharpness"></label>
        </div>
        <div class="ed-controls" data-tool="vinheta" hidden>
            <label class="ed-slider"><span>Vinheta (escurece as bordas) <output></output></span><input type="range" min="0" max="100" value="0" data-param="vignette"></label>
        </div>
        <div class="ed-controls" data-tool="estilos" hidden>
            <div class="ed-presets" id="ed-presets"></div>
        </div>
        <div class="ed-controls" data-tool="recortar" hidden>
            <div class="ed-options" id="ed-crops">
                <button type="button" data-crop="original" class="is-active">Original</button>
                <button type="button" data-crop="1:1">Quadrado 1:1</button>
                <button type="button" data-crop="4:5">Instagram 4:5</button>
                <button type="button" data-crop="9:16">Stories 9:16</button>
                <button type="button" data-crop="16:9">Paisagem 16:9</button>
                <button type="button" data-crop="3:2">Foto 3:2</button>
            </div>
        </div>
        <div class="ed-controls" data-tool="girar" hidden>
            <div class="ed-options">
                <button type="button" id="ed-rot-left">↺ Girar à esquerda</button>
                <button type="button" id="ed-rot-right">↻ Girar à direita</button>
                <button type="button" id="ed-flip">⇋ Espelhar</button>
            </div>
        </div>

        <nav class="ed-tools" aria-label="Ferramentas">
            <?php foreach ($tools as $key => [$label, $svg]): ?>
                <button type="button" class="ed-tool" data-tool="<?= e($key) ?>" aria-pressed="false">
                    <?= $svg ?><span><?= e($label) ?></span><i class="ed-dot" aria-hidden="true"></i>
                </button>
            <?php endforeach; ?>
        </nav>

        <div class="ed-actions">
            <button type="button" class="btn btn-ghost" id="ed-download" disabled>Baixar</button>
            <?php if ($canUpload): ?>
                <button type="button" class="btn btn-ghost" id="ed-save" disabled>Salvar na pasta</button>
                <button type="button" class="btn btn-primary" id="ed-save-publish" disabled>Salvar e publicar</button>
            <?php endif; ?>
        </div>
        <p class="ed-status small muted" id="ed-status" role="status"></p>
    </div>
</section>
<?php render_footer(['app.js', 'photo-filters.js', 'chunk-upload.js', 'editor.js']);
