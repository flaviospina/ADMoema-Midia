// public/assets/js/chunk-upload.js — envio de um arquivo em partes, com repetição
// automática e retomada. Usado pelo envio de fotos e pelo editor.
'use strict';

window.ChunkUpload = (() => {
  const RETRIES = 4;
  const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
  const hex = (bytes) => Array.from(bytes).map((b) => b.toString(16).padStart(2, '0')).join('');

  /** ID de 32 caracteres. Com "key", é sempre o mesmo (permite retomar após recarregar). */
  async function makeId(key) {
    if (key && window.crypto?.subtle) {
      const hash = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(key));
      return hex(new Uint8Array(hash).slice(0, 16));
    }
    return hex(crypto.getRandomValues(new Uint8Array(16)));
  }

  /**
   * Envia "blob". Opções: folderId, name, chunkSize, parentId, resumeKey,
   * onProgress(0..100), onRetry(tentativa, max).
   * Resolve com a resposta final do servidor ({ok, status, foto} ou {ok:false, erro}).
   */
  async function upload(blob, opts) {
    const chunk = opts.chunkSize;
    const total = Math.max(1, Math.ceil(blob.size / chunk));
    const uploadId = await makeId(opts.resumeKey);
    const progress = opts.onProgress || (() => {});

    let received = new Set();
    if (opts.resumeKey) {
      try {
        const st = await App.getJson(`api/upload.php?upload_id=${uploadId}`);
        if (st.ok) received = new Set(st.recebidas);
      } catch { /* começa do início */ }
    }

    for (let index = 0; index < total; index++) {
      if (received.has(index) && index < total - 1) {
        progress(((index + 1) / total) * 100);
        continue;
      }
      const part = blob.slice(index * chunk, Math.min(blob.size, (index + 1) * chunk));
      for (let attempt = 0; ; attempt++) {
        const fd = new FormData();
        fd.append('folder_id', String(opts.folderId));
        fd.append('upload_id', uploadId);
        fd.append('index', String(index));
        fd.append('total', String(total));
        fd.append('name', opts.name);
        fd.append('size', String(blob.size));
        if (opts.parentId) fd.append('parent_id', String(opts.parentId));
        fd.append('chunk', part, 'parte');

        let res;
        try {
          res = await App.post('api/upload.php', fd);
        } catch {
          res = { ok: false, erro: 'Sem conexão. Tentando de novo…', repetir: true };
        }
        if (res.ok) {
          progress(((index + 1) / total) * 100);
          if (res.status === 'concluido' || res.status === 'duplicada') return res;
          break;
        }
        const retryable = res.repetir || !res.httpStatus || res.httpStatus >= 500;
        if (retryable && attempt < RETRIES) {
          if (opts.onRetry) opts.onRetry(attempt + 1, RETRIES);
          await sleep(1000 * 2 ** (attempt + 1));
          continue;
        }
        return { ok: false, erro: res.erro || 'Falha no envio.' };
      }
    }
    return { ok: false, erro: 'O servidor não confirmou o recebimento.' };
  }

  return { upload, makeId };
})();
