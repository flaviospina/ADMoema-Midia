// public/assets/js/uploader.js — envio de fotos em partes, com repetição automática e retomada
'use strict';

(() => {
  const root = document.getElementById('uploader');
  if (!root) return;

  const folderId = root.dataset.folder;
  const CHUNK = Number(root.dataset.chunk);
  const MAX = Number(root.dataset.max);
  const EXT = root.dataset.ext.split(',');
  const PARALLEL_FILES = 2;
  const RETRIES = 4;

  const input = document.getElementById('file-input');
  const dropzone = document.getElementById('dropzone');
  const queueEl = document.getElementById('queue');
  const queueHead = document.getElementById('queue-head');
  const summary = document.getElementById('queue-summary');
  const btnClear = document.getElementById('btn-clear');
  const gallery = document.getElementById('gallery');
  const counter = document.getElementById('photo-count');

  const items = [];
  let running = 0;

  const fmt = (b) => (b >= 1048576 ? `${(b / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(b / 1024))} KB`).replace('.', ',');
  const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

  /** ID estável por arquivo: permite continuar o envio mesmo após recarregar a página. */
  async function uploadIdFor(file) {
    const key = `${folderId}|${file.name}|${file.size}|${file.lastModified}`;
    if (window.crypto?.subtle) {
      const hash = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(key));
      return Array.from(new Uint8Array(hash)).slice(0, 16).map((b) => b.toString(16).padStart(2, '0')).join('');
    }
    return Array.from(crypto.getRandomValues(new Uint8Array(16))).map((b) => b.toString(16).padStart(2, '0')).join('');
  }

  function addFiles(fileList) {
    for (const file of fileList) {
      const ext = (file.name.split('.').pop() || '').toLowerCase();
      const item = { file, status: 'fila', progress: 0, error: '' };
      if (!EXT.includes(ext)) { item.status = 'erro'; item.error = 'Tipo não permitido (envie JPG, PNG, WEBP ou HEIC).'; }
      else if (file.size > MAX) { item.status = 'erro'; item.error = `Maior que ${fmt(MAX)}.`; }
      else if (file.size === 0) { item.status = 'erro'; item.error = 'Arquivo vazio.'; }
      item.el = renderItem(item);
      items.push(item);
    }
    updateSummary();
    pump();
  }

  function renderItem(item) {
    const li = document.createElement('li');
    li.className = 'queue-item';
    const name = document.createElement('span');
    name.className = 'q-name';
    name.textContent = item.file.name;
    const size = document.createElement('span');
    size.className = 'q-size muted small';
    size.textContent = fmt(item.file.size);
    const status = document.createElement('span');
    status.className = 'q-status small';
    const bar = document.createElement('span');
    bar.className = 'q-bar';
    bar.append(document.createElement('span'));
    li.append(name, size, status, bar);
    queueEl.prepend(li);
    item.ui = { li, status, bar: bar.firstChild };
    paint(item);
    return li;
  }

  function paint(item) {
    const labels = { fila: 'Na fila', enviando: `${Math.round(item.progress)}%`, concluido: 'Enviada ✓', duplicada: 'Já estava na pasta', erro: item.error };
    item.ui.li.dataset.status = item.status;
    item.ui.status.textContent = labels[item.status];
    item.ui.bar.style.width = `${item.status === 'concluido' || item.status === 'duplicada' ? 100 : item.progress}%`;
  }

  function updateSummary() {
    const c = (s) => items.filter((i) => i.status === s).length;
    const done = c('concluido');
    const dup = c('duplicada');
    const err = c('erro');
    const pending = c('fila') + c('enviando');
    queueHead.hidden = items.length === 0;
    summary.textContent = pending
      ? `Enviando… ${done + dup} de ${items.length - err} concluída(s)`
      : `${done} enviada(s)${dup ? ` · ${dup} repetida(s)` : ''}${err ? ` · ${err} com erro` : ''}`;
    btnClear.hidden = pending > 0;
  }

  async function pump() {
    while (running < PARALLEL_FILES) {
      const next = items.find((i) => i.status === 'fila');
      if (!next) break;
      running++;
      next.status = 'enviando';
      paint(next);
      uploadFile(next).finally(() => { running--; updateSummary(); pump(); });
    }
    updateSummary();
  }

  async function uploadFile(item) {
    const { file } = item;
    const total = Math.ceil(file.size / CHUNK);
    const uploadId = await uploadIdFor(file);

    // Retomada: pergunta ao servidor quais partes já chegaram
    let received = new Set();
    try {
      const st = await App.getJson(`api/upload.php?upload_id=${uploadId}`);
      if (st.ok) received = new Set(st.recebidas);
    } catch { /* segue do início */ }

    for (let index = 0; index < total; index++) {
      if (received.has(index) && index < total - 1) {
        item.progress = ((index + 1) / total) * 100;
        paint(item);
        continue;
      }
      const blob = file.slice(index * CHUNK, Math.min(file.size, (index + 1) * CHUNK));
      let attempt = 0;
      while (true) {
        const fd = new FormData();
        fd.append('folder_id', folderId);
        fd.append('upload_id', uploadId);
        fd.append('index', String(index));
        fd.append('total', String(total));
        fd.append('name', file.name);
        fd.append('size', String(file.size));
        fd.append('chunk', blob, 'parte');
        let res;
        try {
          res = await App.post('api/upload.php', fd);
        } catch {
          res = { ok: false, erro: 'Sem conexão. Tentando de novo…', repetir: true };
        }
        if (res.ok) {
          item.progress = ((index + 1) / total) * 100;
          if (res.status === 'concluido' || res.status === 'duplicada') {
            item.status = res.status;
            paint(item);
            if (res.status === 'concluido') addToGallery(res.foto, file.name);
            return;
          }
          paint(item);
          break;
        }
        const retryable = res.repetir || res.httpStatus >= 500 || res.httpStatus === 0 || res.httpStatus === undefined;
        if (retryable && attempt < RETRIES) {
          attempt++;
          item.ui.status.textContent = `Reconectando (${attempt}/${RETRIES})…`;
          await sleep(1000 * 2 ** attempt);
          continue;
        }
        item.status = 'erro';
        item.error = res.erro || 'Falha no envio.';
        paint(item);
        return;
      }
    }
  }

  function addToGallery(foto, name) {
    document.getElementById('empty-gallery')?.remove();
    const li = document.createElement('li');
    li.className = 'photo is-new';
    li.id = `photo-${foto.id}`;
    const a = document.createElement('a');
    a.href = `api/foto.php?id=${foto.id}&v=full`;
    a.target = '_blank';
    a.rel = 'noopener';
    a.className = 'photo-link';
    if (foto.thumb) {
      const img = document.createElement('img');
      img.src = `api/foto.php?id=${foto.id}&v=thumb`;
      img.alt = name;
      a.append(img);
    } else {
      const span = document.createElement('span');
      span.className = 'photo-noprev';
      span.textContent = (name.split('.').pop() || '').toUpperCase();
      a.append(span);
    }
    const meta = document.createElement('div');
    meta.className = 'photo-meta';
    const n = document.createElement('span');
    n.className = 'photo-name';
    n.textContent = name;
    const by = document.createElement('span');
    by.className = 'muted small';
    by.textContent = 'enviada agora';
    meta.append(n, by);
    li.append(a, meta);
    gallery.prepend(li);
    if (counter) counter.textContent = String(Number(counter.textContent) + 1);
  }

  input.addEventListener('change', () => { addFiles(input.files); input.value = ''; });

  ['dragenter', 'dragover'].forEach((t) => dropzone.addEventListener(t, (e) => { e.preventDefault(); dropzone.classList.add('is-over'); }));
  ['dragleave', 'drop'].forEach((t) => dropzone.addEventListener(t, (e) => { e.preventDefault(); dropzone.classList.remove('is-over'); }));
  dropzone.addEventListener('drop', (e) => { if (e.dataTransfer?.files?.length) addFiles(e.dataTransfer.files); });

  btnClear.addEventListener('click', () => {
    for (let i = items.length - 1; i >= 0; i--) {
      if (['concluido', 'duplicada', 'erro'].includes(items[i].status)) { items[i].el.remove(); items.splice(i, 1); }
    }
    updateSummary();
  });

  window.addEventListener('beforeunload', (e) => {
    if (items.some((i) => i.status === 'fila' || i.status === 'enviando')) { e.preventDefault(); e.returnValue = ''; }
  });
})();
