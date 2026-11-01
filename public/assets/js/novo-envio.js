// public/assets/js/novo-envio.js — sugere o nome da pasta pela data e pede confirmação
'use strict';

(() => {
  const wizard = document.getElementById('folder-wizard');
  if (!wizard) return;

  const $ = (id) => document.getElementById(id);
  const dateInput = $('event-date');
  const stepName = $('step-name');
  const weekdayLabel = $('weekday-label');
  const existingBox = $('existing-box');
  const existingList = $('existing-list');
  const optionsBox = $('options-box');
  const optionsList = $('options-list');
  const confirmBox = $('confirm-box');
  const preview = $('folder-preview');
  const customBox = $('custom-box');
  const customName = $('custom-name');
  const customLabel = $('custom-label');
  const customPreview = $('custom-preview');
  const btnBack = $('btn-back');
  const errorBox = $('wizard-error');

  let state = { data: null, dataBr: '', sugestoes: [], escolhida: null };

  const show = (el, visible) => { el.hidden = !visible; };
  const cap = (s) => s.charAt(0).toUpperCase() + s.slice(1);

  function setError(message, existing) {
    errorBox.textContent = '';
    if (!message) { show(errorBox, false); return; }
    errorBox.append(document.createTextNode(message + ' '));
    if (existing) {
      const a = document.createElement('a');
      a.href = `pasta.php?id=${existing.id}`;
      a.textContent = `Abrir a pasta “${existing.name}”`;
      errorBox.append(a);
    }
    show(errorBox, true);
  }

  async function loadSuggestion() {
    setError('');
    if (!dateInput.value) { show(stepName, false); return; }
    const res = await App.getJson(`api/sugestao.php?data=${encodeURIComponent(dateInput.value)}`);
    if (!res.ok) { setError(res.erro || 'Não foi possível carregar a sugestão.'); return; }

    state = { data: res.data, dataBr: res.data_br, sugestoes: res.sugestoes, escolhida: res.sugestoes[0] || null };
    weekdayLabel.textContent = `${cap(res.dia_semana)}, ${res.data_br}`;
    show(stepName, true);

    // Pastas já existentes nesta data
    existingList.textContent = '';
    res.existentes.forEach((f) => {
      const li = document.createElement('li');
      const a = document.createElement('a');
      a.href = `pasta.php?id=${f.id}`;
      a.className = 'existing-item';
      const name = document.createElement('strong');
      name.textContent = f.nome;
      const meta = document.createElement('span');
      meta.className = 'muted small';
      meta.textContent = `${f.total} foto(s) · enviar para esta pasta →`;
      a.append(name, meta);
      li.append(a);
      existingList.append(li);
    });
    show(existingBox, res.existentes.length > 0);

    // Opções (sábado: jovens / adolescentes / ambos)
    optionsList.textContent = '';
    if (res.sugestoes.length > 1) {
      res.sugestoes.forEach((nome, i) => {
        const label = document.createElement('label');
        label.className = 'option';
        const radio = document.createElement('input');
        radio.type = 'radio';
        radio.name = 'sugestao';
        radio.value = nome;
        radio.checked = i === 0;
        radio.addEventListener('change', () => { state.escolhida = nome; renderPreview(); });
        label.append(radio, document.createTextNode(' ' + nome));
        optionsList.append(label);
      });
    }
    show(optionsBox, res.sugestoes.length > 1);

    if (res.sugestoes.length > 0) {
      renderPreview();
      show(confirmBox, true);
      show(customBox, false);
      show(btnBack, true);
      customLabel.textContent = 'Nome da pasta';
    } else {
      // Segunda e sexta não têm culto fixo: pede o nome direto
      show(confirmBox, false);
      show(customBox, true);
      show(btnBack, false);
      customLabel.textContent = `${cap(res.dia_semana)} não tem culto fixo. Qual é o nome do evento?`;
      customName.value = '';
      updateCustomPreview();
      customName.focus();
    }
  }

  function renderPreview() {
    preview.textContent = `${state.dataBr} – ${state.escolhida}`;
  }

  function updateCustomPreview() {
    const v = customName.value.trim().replace(/\s+/g, ' ');
    customPreview.textContent = v ? `${state.dataBr} – ${v}` : '—';
  }

  async function createFolder(nome, button) {
    setError('');
    button.disabled = true;
    const original = button.textContent;
    button.textContent = 'Criando pasta…';
    const res = await App.post('api/criar-pasta.php', { data: state.data, nome, confirmado: '1' });
    if (res.ok) {
      window.location.href = res.url;
      return;
    }
    button.disabled = false;
    button.textContent = original;
    setError(res.erro || 'Não foi possível criar a pasta.', res.existente);
  }

  dateInput.addEventListener('change', loadSuggestion);

  $('btn-accept').addEventListener('click', (ev) => {
    if (state.escolhida) createFolder(state.escolhida, ev.currentTarget);
  });

  $('btn-custom').addEventListener('click', () => {
    show(confirmBox, false);
    show(optionsBox, false);
    show(customBox, true);
    customName.value = state.escolhida || '';
    updateCustomPreview();
    customName.focus();
    customName.select();
  });

  btnBack.addEventListener('click', () => {
    show(customBox, false);
    show(confirmBox, true);
    show(optionsBox, state.sugestoes.length > 1);
    setError('');
  });

  customName.addEventListener('input', updateCustomPreview);

  customBox.addEventListener('submit', (ev) => {
    ev.preventDefault();
    const nome = customName.value.trim().replace(/\s+/g, ' ');
    if (nome.length < 3) { setError('O nome da pasta precisa ter pelo menos 3 caracteres.'); return; }
    if (!window.confirm(`Criar a pasta "${state.dataBr} – ${nome}"?`)) return;
    createFolder(nome, $('btn-create-custom'));
  });

  loadSuggestion();
})();
