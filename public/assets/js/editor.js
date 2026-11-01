// public/assets/js/editor.js — interface do editor "Melhorar foto"
'use strict';

(() => {
  const root = document.getElementById('editor');
  if (!root) return;

  const F = window.PhotoFilters;
  const $ = (id) => document.getElementById(id);
  const canvas = $('ed-canvas');
  const ctx = canvas.getContext('2d');
  const statusEl = $('ed-status');
  const loading = $('ed-loading');
  const compareBtn = $('ed-compare');
  const cropHint = $('ed-crop-hint');
  const PREVIEW_MAX = 1600;
  const EXPORT_MAX = 4096;

  const cfg = {
    photoId: root.dataset.photo,
    parentId: root.dataset.parent,
    folderId: root.dataset.folder,
    name: root.dataset.name,
    canSave: root.dataset.canSave === '1',
    chunk: Number(root.dataset.chunk),
  };

  let img = null;         // imagem original
  let base = null;        // cópia reduzida para a pré-visualização
  let baseData = null;    // pixels da cópia reduzida
  let levels = null;      // níveis automáticos (calculados uma vez)
  let geomCache = null;   // { key, data, slackX, slackY, scale }
  let params = { ...F.DEFAULTS };
  let geo = { rotate: 0, flipH: false, crop: 'original', offX: 0, offY: 0 };
  let preset = 'natural';
  let showBefore = false;
  let dirty = false;
  let busy = false;
  const history = [];

  // ---------- Estado ----------
  const snapshot = () => JSON.stringify({ params, geo, preset });
  function restore(json) {
    const s = JSON.parse(json);
    params = s.params; geo = s.geo; preset = s.preset;
    geomCache = null;
    syncUi();
    render();
  }
  function commit() {
    const snap = snapshot();
    if (history[history.length - 1] !== snap) history.push(snap);
    dirty = history.length > 1;
    syncButtons();
  }

  // ---------- Renderização ----------
  function ensureGeom() {
    const key = JSON.stringify(geo);
    if (geomCache && geomCache.key === key) return geomCache;
    const g = F.renderGeometry(base, base.width, base.height, geo, PREVIEW_MAX);
    const data = g.canvas.getContext('2d').getImageData(0, 0, g.canvas.width, g.canvas.height);
    geomCache = { key, data, slackX: g.slackX, slackY: g.slackY, scale: g.scale };
    return geomCache;
  }

  let frame = 0;
  function render() {
    if (!base || frame) return;
    frame = requestAnimationFrame(() => {
      frame = 0;
      const g = ensureGeom();
      if (canvas.width !== g.data.width || canvas.height !== g.data.height) {
        canvas.width = g.data.width;
        canvas.height = g.data.height;
      }
      if (showBefore) {
        ctx.putImageData(g.data, 0, 0);
        return;
      }
      const out = new ImageData(new Uint8ClampedArray(g.data.data), g.data.width, g.data.height);
      F.apply(out, params, levels);
      ctx.putImageData(out, 0, 0);
    });
  }

  // ---------- Interface ----------
  const sliders = Array.from(root.querySelectorAll('input[data-param]'));
  const toolButtons = Array.from(root.querySelectorAll('.ed-tool'));
  const panels = Array.from(root.querySelectorAll('.ed-controls'));

  const changedTools = () => ({
    auto: params.autoLevels,
    brilho: params.exposure || params.brightness,
    contraste: params.contrast || params.fade,
    cor: params.saturation || params.vibrance,
    temperatura: params.temperature || params.tint,
    luz: params.shadows || params.highlights,
    nitidez: params.sharpness,
    vinheta: params.vignette,
    estilos: preset !== 'natural',
    recortar: geo.crop !== 'original',
    girar: geo.rotate % 360 !== 0 || geo.flipH,
  });

  function syncUi() {
    sliders.forEach((s) => {
      s.value = String(params[s.dataset.param]);
      s.closest('label').querySelector('output').textContent = signed(params[s.dataset.param]);
    });
    root.querySelectorAll('#ed-crops button').forEach((b) => b.classList.toggle('is-active', b.dataset.crop === geo.crop));
    root.querySelectorAll('.ed-preset').forEach((b) => b.classList.toggle('is-active', b.dataset.preset === preset));
    cropHint.hidden = geo.crop === 'original';
    canvas.classList.toggle('is-draggable', geo.crop !== 'original');
    syncButtons();
  }

  function syncButtons() {
    const changed = changedTools();
    toolButtons.forEach((b) => b.classList.toggle('has-change', !!changed[b.dataset.tool]));
    const any = Object.values(changed).some(Boolean);
    $('ed-undo').disabled = history.length <= 1 || busy;
    $('ed-reset').disabled = !any || busy;
    compareBtn.hidden = !any;
    ['ed-download', 'ed-save', 'ed-save-publish'].forEach((id) => {
      const el = $(id);
      if (el) el.disabled = busy || !base || (id !== 'ed-download' && !any);
    });
  }

  const signed = (v) => (v > 0 ? `+${v}` : String(v));

  function selectTool(tool) {
    toolButtons.forEach((b) => {
      const on = b.dataset.tool === tool;
      b.classList.toggle('is-active', on);
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
    panels.forEach((p) => { p.hidden = p.dataset.tool !== tool; });
    if (tool === 'estilos') buildPresetThumbs();
  }

  toolButtons.forEach((b) => b.addEventListener('click', () => {
    selectTool(b.dataset.tool);
    b.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'smooth' });
  }));

  sliders.forEach((s) => {
    s.addEventListener('input', () => {
      params[s.dataset.param] = Number(s.value);
      s.closest('label').querySelector('output').textContent = signed(Number(s.value));
      syncButtons();
      render();
    });
    s.addEventListener('change', commit);
    // Duplo clique zera o ajuste
    s.addEventListener('dblclick', () => {
      params[s.dataset.param] = 0;
      syncUi(); render(); commit();
    });
  });

  $('ed-auto').addEventListener('click', () => {
    if (!levels) levels = F.computeLevels(baseData);
    params = { ...params, autoLevels: true, ...F.suggestAuto(baseData, levels) };
    syncUi(); render(); commit();
    App.toast('Melhoria automática aplicada. Ajuste o que quiser.', 'sucesso');
  });

  // Estilos
  let thumbsBuilt = false;
  function buildPresetThumbs() {
    if (thumbsBuilt || !base) return;
    thumbsBuilt = true;
    const wrap = $('ed-presets');
    const tiny = F.renderGeometry(base, base.width, base.height, { rotate: 0, flipH: false, crop: '1:1', offX: 0, offY: 0 }, 120).canvas;
    const tinyData = tiny.getContext('2d').getImageData(0, 0, tiny.width, tiny.height);
    if (!levels) levels = F.computeLevels(baseData);
    F.PRESETS.forEach((p) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'ed-preset';
      btn.dataset.preset = p.id;
      const c = document.createElement('canvas');
      c.width = tiny.width; c.height = tiny.height;
      const out = new ImageData(new Uint8ClampedArray(tinyData.data), tinyData.width, tinyData.height);
      F.apply(out, { ...F.DEFAULTS, autoLevels: params.autoLevels, ...p.params }, levels);
      c.getContext('2d').putImageData(out, 0, 0);
      const label = document.createElement('span');
      label.textContent = p.name;
      btn.append(c, label);
      btn.addEventListener('click', () => {
        preset = p.id;
        params = { ...F.DEFAULTS, autoLevels: params.autoLevels, ...p.params };
        syncUi(); render(); commit();
      });
      wrap.append(btn);
    });
    syncUi();
  }

  // Recorte
  root.querySelectorAll('#ed-crops button').forEach((b) => b.addEventListener('click', () => {
    geo = { ...geo, crop: b.dataset.crop, offX: 0, offY: 0 };
    syncUi(); render(); commit();
  }));

  // Girar / espelhar
  $('ed-rot-left').addEventListener('click', () => { geo = { ...geo, rotate: (geo.rotate + 270) % 360, offX: 0, offY: 0 }; syncUi(); render(); commit(); });
  $('ed-rot-right').addEventListener('click', () => { geo = { ...geo, rotate: (geo.rotate + 90) % 360, offX: 0, offY: 0 }; syncUi(); render(); commit(); });
  $('ed-flip').addEventListener('click', () => { geo = { ...geo, flipH: !geo.flipH }; syncUi(); render(); commit(); });

  // Arrastar para enquadrar o recorte
  let drag = null;
  canvas.addEventListener('pointerdown', (ev) => {
    if (geo.crop === 'original' || !geomCache) return;
    drag = { x: ev.clientX, y: ev.clientY };
    canvas.setPointerCapture(ev.pointerId);
  });
  canvas.addEventListener('pointermove', (ev) => {
    if (!drag || !geomCache) return;
    const k = canvas.width / canvas.clientWidth;
    const dx = (ev.clientX - drag.x) * k;
    const dy = (ev.clientY - drag.y) * k;
    drag = { x: ev.clientX, y: ev.clientY };
    const s = geomCache.scale;
    const clamp = (v) => Math.max(-1, Math.min(1, v));
    geo = {
      ...geo,
      offX: geomCache.slackX > 0 ? clamp(geo.offX - (2 * dx) / (s * geomCache.slackX)) : 0,
      offY: geomCache.slackY > 0 ? clamp(geo.offY - (2 * dy) / (s * geomCache.slackY)) : 0,
    };
    render();
  });
  const endDrag = () => { if (drag) { drag = null; commit(); } };
  canvas.addEventListener('pointerup', endDrag);
  canvas.addEventListener('pointercancel', endDrag);

  // Antes / depois
  const before = (on) => { showBefore = on; compareBtn.classList.toggle('is-on', on); render(); };
  compareBtn.addEventListener('pointerdown', (ev) => { ev.preventDefault(); before(true); });
  ['pointerup', 'pointerleave', 'pointercancel'].forEach((t) => compareBtn.addEventListener(t, () => before(false)));
  compareBtn.addEventListener('keydown', (ev) => { if (ev.key === ' ' || ev.key === 'Enter') before(true); });
  compareBtn.addEventListener('keyup', () => before(false));

  $('ed-undo').addEventListener('click', () => {
    if (history.length <= 1) return;
    history.pop();
    restore(history[history.length - 1]);
    dirty = history.length > 1;
    syncButtons();
  });
  $('ed-reset').addEventListener('click', () => {
    params = { ...F.DEFAULTS };
    geo = { rotate: 0, flipH: false, crop: 'original', offX: 0, offY: 0 };
    preset = 'natural';
    geomCache = null;
    syncUi(); render(); commit();
  });

  // ---------- Exportar em alta qualidade ----------
  const editedName = () => cfg.name.replace(/\.[^.]+$/, '').replace(/\s*\(editada\)$/i, '') + ' (editada).jpg';
  const nextFrame = () => new Promise((r) => requestAnimationFrame(() => setTimeout(r, 30)));

  async function exportBlob() {
    for (const max of [EXPORT_MAX, 2560]) {
      try {
        const g = F.renderGeometry(img, img.naturalWidth, img.naturalHeight, geo, max);
        const c2 = g.canvas.getContext('2d');
        const data = c2.getImageData(0, 0, g.canvas.width, g.canvas.height);
        F.apply(data, params, levels);
        c2.putImageData(data, 0, 0);
        const blob = await new Promise((res) => g.canvas.toBlob(res, 'image/jpeg', 0.92));
        g.canvas.width = 1; g.canvas.height = 1; // libera memória
        if (blob) return blob;
      } catch (e) { /* tenta em tamanho menor */ }
    }
    throw new Error('memória');
  }

  function setBusy(on, message = '') {
    busy = on;
    statusEl.textContent = message;
    syncButtons();
  }

  $('ed-download').addEventListener('click', async () => {
    setBusy(true, 'Gerando a foto em alta qualidade…');
    await nextFrame();
    try {
      const blob = await exportBlob();
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url; a.download = editedName();
      document.body.append(a); a.click(); a.remove();
      setTimeout(() => URL.revokeObjectURL(url), 10000);
      setBusy(false, 'Foto baixada.');
    } catch {
      setBusy(false, 'Este aparelho não teve memória para gerar a foto. Tente no computador.');
    }
  });

  async function save(publish) {
    setBusy(true, 'Gerando a foto em alta qualidade…');
    await nextFrame();
    let blob;
    try {
      blob = await exportBlob();
    } catch {
      setBusy(false, 'Este aparelho não teve memória para gerar a foto. Tente no computador.');
      return;
    }
    const res = await ChunkUpload.upload(blob, {
      folderId: cfg.folderId,
      name: editedName(),
      chunkSize: cfg.chunk,
      parentId: cfg.parentId,
      onProgress: (p) => { statusEl.textContent = `Salvando na pasta… ${Math.round(p)}%`; },
      onRetry: (n, max) => { statusEl.textContent = `Reconectando (${n}/${max})…`; },
    });
    if (!res.ok) { setBusy(false, res.erro || 'Não foi possível salvar.'); return; }
    if (res.status === 'duplicada') { setBusy(false, 'Esta mesma versão já está salva na pasta.'); return; }
    dirty = false;
    statusEl.textContent = 'Salva! A original foi mantida.';
    location.href = `pasta.php?id=${cfg.folderId}&foto=${res.foto.id}${publish ? '&publicar=1' : ''}`;
  }
  $('ed-save')?.addEventListener('click', () => save(false));
  $('ed-save-publish')?.addEventListener('click', () => save(true));

  window.addEventListener('beforeunload', (e) => { if (dirty && !busy) { e.preventDefault(); e.returnValue = ''; } });

  // ---------- Carregar a foto ----------
  img = new Image();
  img.decoding = 'async';
  img.onload = () => {
    try {
      base = F.renderGeometry(img, img.naturalWidth, img.naturalHeight, { rotate: 0, flipH: false, crop: 'original', offX: 0, offY: 0 }, PREVIEW_MAX).canvas;
      baseData = base.getContext('2d').getImageData(0, 0, base.width, base.height);
    } catch {
      loading.textContent = 'Não foi possível abrir a foto neste aparelho.';
      return;
    }
    loading.hidden = true;
    history.push(snapshot());
    syncUi();
    selectTool('auto');
    render();
  };
  img.onerror = () => {
    loading.textContent = 'Este navegador não consegue abrir este formato de foto (provavelmente HEIC). Abra pelo iPhone/Safari ou envie a foto em JPG.';
  };
  img.src = root.dataset.src;
})();
