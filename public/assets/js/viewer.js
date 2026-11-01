// public/assets/js/viewer.js — visualizador em tela cheia, compartilhar pelo celular
// e publicação nas redes sociais
'use strict';

(() => {
  const lb = document.getElementById('lightbox');
  const gallery = document.getElementById('gallery');
  if (!lb || !gallery) return;

  const $ = (id) => document.getElementById(id);
  const img = $('lb-img');
  const heicBox = $('lb-heic');
  const nameEl = $('lb-name');
  const infoEl = $('lb-info');
  const btnEdit = $('lb-edit');
  const btnShare = $('lb-share');
  const btnPublish = $('lb-publish');
  const btnDownload = $('lb-download');
  const btnDelete = $('lb-delete');
  const counter = $('photo-count');

  let items = [];
  let index = -1;
  let lastFocus = null;

  const photos = () => Array.from(gallery.querySelectorAll('.photo'));
  const current = () => items[index];

  // ---------- Compartilhar pelo celular (Web Share: WhatsApp, Instagram, etc.) ----------
  const canShareFiles = (() => {
    try {
      return !!(navigator.canShare && navigator.canShare({ files: [new File(['x'], 'a.jpg', { type: 'image/jpeg' })] }));
    } catch { return false; }
  })();

  function open(i) {
    items = photos();
    if (!items[i]) return;
    index = i;
    lastFocus = document.activeElement;
    lb.hidden = false;
    document.body.classList.add('no-scroll');
    show();
    $('lb-close').focus();
  }

  function close() {
    lb.hidden = true;
    document.body.classList.remove('no-scroll');
    img.removeAttribute('src');
    if (lastFocus) lastFocus.focus();
    history.replaceState(null, '', location.pathname + location.search.replace(/([?&])(foto|publicar)=[^&]*/g, '$1').replace(/[?&]+$/, ''));
  }

  function show() {
    const el = current();
    const d = el.dataset;
    const heic = d.heic === '1';
    nameEl.textContent = d.name;
    infoEl.textContent = ` · ${index + 1} de ${items.length} · por ${d.by}${d.edited === '1' ? ' · editada' : ''}${Number(d.posts) > 0 ? ' · publicada' : ''}`;
    heicBox.hidden = !heic;
    img.hidden = heic;
    if (!heic) {
      img.classList.add('is-loading');
      img.onload = () => img.classList.remove('is-loading');
      img.src = `api/foto.php?id=${d.id}&v=view`;
      img.alt = d.name;
    }
    btnEdit.href = `editar.php?id=${d.id}`;
    btnEdit.hidden = heic && !/iPhone|iPad|Macintosh/.test(navigator.userAgent);
    btnDownload.href = `api/foto.php?id=${d.id}&v=download`;
    btnShare.hidden = !canShareFiles || heic;
    btnDelete.hidden = d.delete !== '1';
    $('lb-prev').hidden = index === 0;
    $('lb-next').hidden = index === items.length - 1;
    // Pré-carrega a próxima
    const next = items[index + 1];
    if (next && next.dataset.heic !== '1') new Image().src = `api/foto.php?id=${next.dataset.id}&v=view`;
  }

  const go = (delta) => {
    const ni = index + delta;
    if (ni >= 0 && ni < items.length) { index = ni; show(); }
  };

  gallery.addEventListener('click', (ev) => {
    const link = ev.target.closest('.js-open');
    if (!link) return;
    ev.preventDefault();
    open(photos().indexOf(link.closest('.photo')));
  });

  $('lb-close').addEventListener('click', close);
  $('lb-prev').addEventListener('click', () => go(-1));
  $('lb-next').addEventListener('click', () => go(1));
  lb.addEventListener('click', (ev) => { if (ev.target === lb || ev.target.id === 'lb-stage') close(); });

  document.addEventListener('keydown', (ev) => {
    const pm = document.getElementById('publish-modal');
    if (lb.hidden || (pm && !pm.hidden)) return;
    if (ev.key === 'Escape') close();
    if (ev.key === 'ArrowLeft') go(-1);
    if (ev.key === 'ArrowRight') go(1);
  });

  // Deslizar para os lados no celular
  let touchX = null;
  lb.addEventListener('touchstart', (ev) => { touchX = ev.touches[0].clientX; }, { passive: true });
  lb.addEventListener('touchend', (ev) => {
    if (touchX === null) return;
    const dx = ev.changedTouches[0].clientX - touchX;
    if (Math.abs(dx) > 50) go(dx < 0 ? 1 : -1);
    touchX = null;
  });

  btnShare.addEventListener('click', async () => {
    const d = current().dataset;
    btnShare.disabled = true;
    try {
      const res = await fetch(`api/foto.php?id=${d.id}&v=view`, { credentials: 'same-origin' });
      const blob = await res.blob();
      const file = new File([blob], d.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' });
      await navigator.share({ files: [file], title: d.name });
    } catch (e) {
      if (e && e.name !== 'AbortError') App.toast('Não foi possível compartilhar neste aparelho.', 'erro');
    } finally {
      btnShare.disabled = false;
    }
  });

  btnDelete.addEventListener('click', async () => {
    const el = current();
    if (!window.confirm(`Excluir a foto "${el.dataset.name}"?`)) return;
    btnDelete.disabled = true;
    const res = await App.post('api/excluir-foto.php', { id: el.dataset.id });
    btnDelete.disabled = false;
    if (!res.ok) { App.toast(res.erro || 'Não foi possível excluir.', 'erro'); return; }
    el.remove();
    if (counter) counter.textContent = String(Math.max(0, Number(counter.textContent) - 1));
    App.toast('Foto excluída.', 'sucesso');
    items = photos();
    if (!items.length) { close(); return; }
    index = Math.min(index, items.length - 1);
    show();
  });

  // ---------- Publicar nas redes sociais ----------
  const modal = $('publish-modal');
  if (modal && btnPublish) {
    const form = $('pub-form');
    const caption = $('pub-caption');
    const countEl = $('pub-count');
    const result = $('pub-result');
    const preview = $('pub-preview');
    const submit = $('pub-submit');
    let photoId = null;

    const closeModal = () => { modal.hidden = true; btnPublish.focus(); };
    modal.querySelectorAll('.js-modal-close').forEach((b) => b.addEventListener('click', closeModal));
    modal.addEventListener('click', (ev) => { if (ev.target === modal) closeModal(); });
    modal.addEventListener('keydown', (ev) => { if (ev.key === 'Escape') { ev.stopPropagation(); closeModal(); } });

    btnPublish.addEventListener('click', () => {
      const d = current().dataset;
      photoId = d.id;
      modal.hidden = false;
      if (!form) return;
      if (d.heic === '1') {
        result.hidden = false;
        result.className = 'pub-result alert alert-aviso';
        result.textContent = 'Fotos HEIC precisam ser salvas em “Melhorar foto” antes (a versão salva fica em JPG).';
      } else {
        result.hidden = true;
      }
      if (Number(d.posts) > 0) {
        result.hidden = false;
        result.className = 'pub-result alert alert-info';
        result.textContent = 'Atenção: esta foto já foi publicada antes.';
      }
      preview.src = `api/foto.php?id=${d.id}&v=thumb`;
      if (!caption.value) caption.value = modal.dataset.signature ? `\n\n${modal.dataset.signature}` : '';
      countEl.textContent = String(caption.value.length);
      submit.disabled = false;
      submit.textContent = 'Publicar agora';
      caption.focus();
      caption.setSelectionRange(0, 0);
    });

    caption?.addEventListener('input', () => { countEl.textContent = String(caption.value.length); });

    form?.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const fd = new FormData(form);
      if (!fd.getAll('redes[]').length) { App.toast('Escolha pelo menos uma rede.', 'erro'); return; }
      fd.append('photo_id', photoId);
      submit.disabled = true;
      submit.textContent = 'Publicando… (pode levar até 30 segundos)';
      result.hidden = true;
      const res = await App.post('api/publicar.php', fd);
      submit.textContent = 'Publicar agora';
      submit.disabled = false;

      result.hidden = false;
      result.textContent = '';
      if (!res.resultados) {
        result.className = 'pub-result alert alert-erro';
        result.textContent = res.erro || 'Não foi possível publicar.';
        return;
      }
      result.className = `pub-result alert ${res.ok ? 'alert-sucesso' : 'alert-aviso'}`;
      const labels = { facebook: 'Facebook', instagram: 'Instagram' };
      let anyOk = false;
      for (const [net, r] of Object.entries(res.resultados)) {
        const line = document.createElement('div');
        if (r.ok) {
          anyOk = true;
          line.append(document.createTextNode(`✓ ${labels[net]}: publicada. `));
          if (r.link) {
            const a = document.createElement('a');
            a.href = r.link; a.target = '_blank'; a.rel = 'noopener noreferrer'; a.textContent = 'Ver publicação';
            line.append(a);
          }
        } else {
          line.textContent = `✕ ${labels[net]}: ${r.erro}`;
        }
        result.append(line);
      }
      if (anyOk) {
        const el = current();
        el.dataset.posts = String(Number(el.dataset.posts) + 1);
        caption.value = '';
        show();
      }
    });
  }

  // Abrir direto uma foto (?foto=ID) e, se pedido, a publicação (&publicar=1)
  const openId = lb.dataset.open;
  if (openId && openId !== '0') {
    const target = photos().findIndex((p) => p.dataset.id === openId);
    if (target >= 0) {
      open(target);
      if (lb.dataset.publish === '1' && btnPublish) btnPublish.click();
    }
  }
})();
