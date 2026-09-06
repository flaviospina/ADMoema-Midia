/* logo-motion · rolagem: .reveal, contadores e cabeçalho compacto */
(function () {
  'use strict';
  var reduzido = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

  // entrada na rolagem
  var alvos = document.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window && !reduzido) {
    var obs = new IntersectionObserver(function (entradas) {
      entradas.forEach(function (e) {
        if (e.isIntersecting) { e.target.classList.add('visivel'); obs.unobserve(e.target); }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    alvos.forEach(function (el) { obs.observe(el); });
  } else {
    alvos.forEach(function (el) { el.classList.add('visivel'); });
  }

  // contadores: <span data-contador="120" data-sufixo="+">0</span>
  var contadores = document.querySelectorAll('[data-contador]');
  if (contadores.length) {
    var animar = function (el) {
      var fim = parseFloat(el.dataset.contador), sufixo = el.dataset.sufixo || '', dur = 1200, inicio = null;
      if (reduzido) { el.textContent = fim + sufixo; return; }
      var passo = function (t) {
        if (!inicio) inicio = t;
        var p = Math.min((t - inicio) / dur, 1), e = 1 - Math.pow(1 - p, 3);
        el.textContent = Math.round(fim * e) + sufixo;
        if (p < 1) requestAnimationFrame(passo);
      };
      requestAnimationFrame(passo);
    };
    if ('IntersectionObserver' in window) {
      var obsC = new IntersectionObserver(function (es) { es.forEach(function (e) { if (e.isIntersecting) { animar(e.target); obsC.unobserve(e.target); } }); }, { threshold: 0.6 });
      contadores.forEach(function (el) { obsC.observe(el); });
    } else { contadores.forEach(animar); }
  }

  // cabeçalho compacto
  var topo = document.querySelector('.topo, header');
  if (topo) {
    var atualizar = function () { topo.classList.toggle('topo--compacto', window.scrollY > 40); };
    addEventListener('scroll', atualizar, { passive: true }); atualizar();
  }
})();
