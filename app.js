/* Ministério de Mídia ADMoema — interações da página e registro de ações */
(function () {
  'use strict';

  const API = {
    acoes: 'api/acoes.php',
    respostas: 'api/respostas.php',
    frentes: 'api/frentes.php',
  };

  // ---------- identificador anônimo da sessão do navegador ----------
  function sessaoId() {
    try {
      let id = localStorage.getItem('admoema_midia_sessao');
      if (!id) {
        id = (crypto.randomUUID ? crypto.randomUUID() : String(Date.now()) + Math.random().toString(16).slice(2));
        localStorage.setItem('admoema_midia_sessao', id);
      }
      return id;
    } catch (_) {
      return null;
    }
  }
  const SESSAO = sessaoId();

  // ---------- registro de ações (fire-and-forget) ----------
  function registrar(tipo, detalhe) {
    const corpo = JSON.stringify({ tipo, detalhe: detalhe || null, sessao: SESSAO });
    try {
      if (navigator.sendBeacon) {
        const blob = new Blob([corpo], { type: 'application/json' });
        if (navigator.sendBeacon(API.acoes, blob)) return;
      }
      fetch(API.acoes, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: corpo, keepalive: true }).catch(() => {});
    } catch (_) { /* silencioso */ }
  }

  // visita à página
  registrar('pagina_visitada', {
    caminho: location.pathname,
    referencia: document.referrer || null,
    largura: window.innerWidth,
    idioma: navigator.language,
  });

  // seções vistas (uma vez por sessão de página)
  const secoesVistas = new Set();
  const obsSecoes = new IntersectionObserver((entradas) => {
    for (const e of entradas) {
      if (!e.isIntersecting) continue;
      const nome = e.target.dataset.secao;
      if (nome && !secoesVistas.has(nome)) {
        secoesVistas.add(nome);
        registrar('secao_vista', { secao: nome });
      }
      marcarNavAtiva(e.target.id);
    }
  }, { threshold: 0.35 });
  document.querySelectorAll('[data-secao]').forEach((s) => obsSecoes.observe(s));

  // cliques em chamadas para ação
  document.querySelectorAll('[data-cta]').forEach((el) => {
    el.addEventListener('click', () => registrar('cta_clicado', { origem: el.dataset.cta, destino: el.getAttribute('href') }));
  });

  // ---------- navegação ativa ----------
  const linksNav = Array.from(document.querySelectorAll('.nav a'));
  function marcarNavAtiva(id) {
    linksNav.forEach((a) => a.classList.toggle('ativo', a.getAttribute('href') === '#' + id));
  }

  // ---------- menu mobile ----------
  const toggle = document.querySelector('.menu-toggle');
  const menu = document.getElementById('menu-mobile');
  if (toggle && menu) {
    toggle.addEventListener('click', () => {
      const aberto = toggle.getAttribute('aria-expanded') === 'true';
      toggle.setAttribute('aria-expanded', String(!aberto));
      toggle.setAttribute('aria-label', aberto ? 'Abrir menu' : 'Fechar menu');
      menu.hidden = aberto;
    });
    menu.querySelectorAll('a').forEach((a) => a.addEventListener('click', () => {
      toggle.setAttribute('aria-expanded', 'false');
      menu.hidden = true;
    }));
  }

  // ---------- animações de entrada ----------
  const obsReveal = new IntersectionObserver((entradas) => {
    for (const e of entradas) {
      if (e.isIntersecting) { e.target.classList.add('visivel'); obsReveal.unobserve(e.target); }
    }
  }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
  document.querySelectorAll('.reveal').forEach((el) => obsReveal.observe(el));

  // ---------- frentes (checkboxes) ----------
  const FRENTES_PADRAO = [
    'Áudio e sonorização', 'Projeção', 'Transmissão ao vivo', 'Câmeras e direção', 'Fotografia',
    'Produção de vídeos', 'Design e identidade visual', 'Redes sociais', 'Sistemas e tecnologia',
  ];
  const caixaFrentes = document.getElementById('frentes-check');
  function renderizarFrentes(lista) {
    caixaFrentes.innerHTML = '';
    lista.forEach((nome, i) => {
      const label = document.createElement('label');
      const input = document.createElement('input');
      input.type = 'checkbox'; input.name = 'frentes'; input.value = nome; input.id = 'frente-' + i;
      const caixa = document.createElement('span'); caixa.className = 'caixa';
      const texto = document.createElement('span'); texto.textContent = nome;
      label.append(input, caixa, texto);
      caixaFrentes.appendChild(label);
    });
  }
  renderizarFrentes(FRENTES_PADRAO);
  fetch(API.frentes).then((r) => r.ok ? r.json() : null).then((d) => {
    if (d && Array.isArray(d.frentes) && d.frentes.length) renderizarFrentes(d.frentes);
  }).catch(() => {});

  // clicar em um card de frente pré-marca a opção no formulário
  document.querySelectorAll('.frente[data-frente]').forEach((card) => {
    card.style.cursor = 'pointer';
    card.setAttribute('role', 'button');
    card.setAttribute('tabindex', '0');
    card.setAttribute('aria-label', card.dataset.frente + ' — marcar no formulário');
    const acao = () => {
      const input = Array.from(caixaFrentes.querySelectorAll('input')).find((i) => i.value === card.dataset.frente);
      if (input) input.checked = true;
      registrar('cta_clicado', { origem: 'card-frente', frente: card.dataset.frente });
      document.getElementById('formulario').scrollIntoView({ behavior: 'smooth' });
    };
    card.addEventListener('click', acao);
    card.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); acao(); } });
  });

  // ---------- formulário ----------
  const form = document.getElementById('form-perguntas');
  const status = document.getElementById('form-status');
  const btn = document.getElementById('btn-enviar');
  const sucesso = document.getElementById('sucesso');
  let iniciou = false;

  form.addEventListener('focusin', () => {
    if (!iniciou) { iniciou = true; registrar('formulario_iniciado'); }
  }, { once: true });

  function limparErros() {
    form.querySelectorAll('.campo.invalido').forEach((c) => c.classList.remove('invalido'));
    form.querySelectorAll('.erro').forEach((e) => { e.textContent = ''; });
    status.textContent = ''; status.classList.remove('erro-geral');
  }

  function mostrarErros(erros) {
    let primeiro = null;
    for (const [campo, msg] of Object.entries(erros)) {
      const el = form.querySelector('[data-erro-para="' + campo + '"]');
      const input = form.elements[campo];
      if (el) el.textContent = msg;
      if (input) { input.closest('.campo').classList.add('invalido'); if (!primeiro) primeiro = input; }
    }
    if (primeiro) primeiro.focus({ preventScroll: false });
  }

  function validarLocal(dados) {
    const erros = {};
    if (dados.nome.length < 2) erros.nome = 'Informe seu nome.';
    if (dados.sabeFazer.length < 3) erros.sabeFazer = 'Conte o que você já sabe fazer.';
    if (dados.querAprender.length < 3) erros.querAprender = 'Conte o que gostaria de aprender.';
    if (dados.projetoAjudar.length < 3) erros.projetoAjudar = 'Conte qual projeto gostaria de ajudar.';
    return erros;
  }

  form.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    limparErros();

    const dados = {
      nome: form.nome.value.trim(),
      contato: form.contato.value.trim(),
      frentes: Array.from(form.querySelectorAll('input[name="frentes"]:checked')).map((i) => i.value),
      sabeFazer: form.sabeFazer.value.trim(),
      querAprender: form.querAprender.value.trim(),
      projetoAjudar: form.projetoAjudar.value.trim(),
      sessao: SESSAO,
    };

    const errosLocais = validarLocal(dados);
    if (Object.keys(errosLocais).length) { mostrarErros(errosLocais); return; }

    btn.disabled = true; btn.classList.add('carregando');
    try {
      const resp = await fetch(API.respostas, {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(dados),
      });
      const json = await resp.json().catch(() => ({}));
      if (resp.status === 422 && json.erros) { mostrarErros(json.erros); return; }
      if (!resp.ok || !json.ok) throw new Error(json.erro || 'Falha ao enviar.');

      document.getElementById('sucesso-titulo').textContent = json.mensagem || 'Resposta registrada!';
      form.hidden = true; sucesso.hidden = false;
      sucesso.scrollIntoView({ behavior: 'smooth', block: 'center' });
      form.reset();
    } catch (err) {
      status.textContent = 'Não foi possível enviar agora. Verifique sua conexão e tente novamente.';
      status.classList.add('erro-geral');
    } finally {
      btn.disabled = false; btn.classList.remove('carregando');
    }
  });

  document.getElementById('btn-nova').addEventListener('click', () => {
    sucesso.hidden = true; form.hidden = false; iniciou = false;
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    form.nome.focus();
  });
})();
