// public/assets/js/app.js — comportamentos comuns (sem scripts inline por causa da CSP)
'use strict';

window.App = (() => {
  const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

  /** POST com token CSRF; devolve o JSON da resposta. */
  async function post(url, data) {
    const body = data instanceof FormData ? data : new URLSearchParams(data);
    const res = await fetch(url, {
      method: 'POST',
      body,
      credentials: 'same-origin',
      headers: { 'X-CSRF-Token': csrf(), 'Accept': 'application/json' },
    });
    let json;
    try { json = await res.json(); } catch { json = { ok: false, erro: 'Resposta inesperada do servidor.' }; }
    if (res.status === 401) { window.location.href = 'login.php'; }
    json.httpStatus = res.status;
    return json;
  }

  async function getJson(url) {
    const res = await fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
    if (res.status === 401) { window.location.href = 'login.php'; }
    try { return await res.json(); } catch { return { ok: false, erro: 'Resposta inesperada do servidor.' }; }
  }

  function toast(message, type = 'info') {
    const el = document.createElement('div');
    el.className = `toast toast-${type}`;
    el.setAttribute('role', 'status');
    el.textContent = message;
    document.body.appendChild(el);
    requestAnimationFrame(() => el.classList.add('is-visible'));
    setTimeout(() => { el.classList.remove('is-visible'); setTimeout(() => el.remove(), 300); }, 4000);
  }

  // Mostrar/ocultar senha
  document.querySelectorAll('.toggle-password').forEach((btn) => {
    btn.addEventListener('click', () => {
      const input = btn.parentElement.querySelector('input');
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.textContent = show ? 'Ocultar' : 'Mostrar';
      btn.setAttribute('aria-label', show ? 'Ocultar senha' : 'Mostrar senha');
    });
  });

  // Medidor de força da senha (orientativo; a validação real é no servidor)
  document.querySelectorAll('.js-strength').forEach((input) => {
    const box = input.closest('label').nextElementSibling;
    if (!box || !box.classList.contains('strength')) return;
    const bar = box.querySelector('.strength-bar');
    const text = box.querySelector('.strength-text');
    input.addEventListener('input', () => {
      const v = input.value;
      let score = 0;
      if (v.length >= 10) score++;
      if (v.length >= 14) score++;
      if (/[a-z]/.test(v) && /[A-Z]/.test(v)) score++;
      if (/\d/.test(v)) score++;
      if (/[^a-zA-Z\d]/.test(v)) score++;
      if (v.length < 10) score = Math.min(score, 1);
      const levels = ['Muito fraca', 'Fraca', 'Razoável', 'Boa', 'Forte', 'Excelente'];
      box.dataset.level = String(score);
      bar.style.setProperty('--w', `${(score / 5) * 100}%`);
      text.textContent = v ? levels[score] : '';
    });
  });

  // Formulários com confirmação
  document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (ev) => {
      if (!window.confirm(form.dataset.confirm)) ev.preventDefault();
    });
  });

  // Selects que salvam ao mudar
  document.querySelectorAll('form.js-autosubmit select').forEach((sel) => {
    sel.addEventListener('change', () => sel.form.submit());
  });

  // Copiar ao clicar
  document.querySelectorAll('.js-copy').forEach((el) => {
    el.addEventListener('click', async () => {
      try { await navigator.clipboard.writeText(el.textContent.trim()); toast('Copiado!', 'sucesso'); } catch { /* sem permissão */ }
    });
  });

  document.querySelectorAll('.js-print').forEach((btn) => btn.addEventListener('click', () => window.print()));

  // Excluir foto
  document.addEventListener('click', async (ev) => {
    const btn = ev.target.closest('.js-delete');
    if (!btn) return;
    if (!window.confirm(`Excluir a foto "${btn.dataset.name}"?`)) return;
    btn.disabled = true;
    const res = await post('api/excluir-foto.php', { id: btn.dataset.id });
    if (res.ok) {
      document.getElementById(`photo-${btn.dataset.id}`)?.remove();
      const counter = document.getElementById('photo-count');
      if (counter) counter.textContent = String(Math.max(0, Number(counter.textContent) - 1));
      toast('Foto excluída.', 'sucesso');
    } else {
      btn.disabled = false;
      toast(res.erro || 'Não foi possível excluir.', 'erro');
    }
  });

  return { post, getJson, toast, csrf };
})();
