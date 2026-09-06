/* abertura do site · roda uma vez por sessão, fecha com "Pular", Esc, ou sozinha */
(function () {
  'use strict';
  var el = document.getElementById('intro-splash');
  if (!el) return;
  var CHAVE = 'intro-visto';
  function marcar() { try { sessionStorage.setItem(CHAVE, '1'); } catch (e) {} }
  try { if (sessionStorage.getItem(CHAVE)) { el.remove(); return; } } catch (e) {}
  if (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) { el.remove(); marcar(); return; }

  document.documentElement.classList.add('intro-ativo');
  var duracao = parseFloat(el.dataset.duration || '2.4') * 1000;
  var espera = parseFloat(el.dataset.hold || '0.6') * 1000;
  var limite = parseFloat(el.dataset.max || '3500'); // nunca segura a página além disso
  var fechado = false;

  function fechar() {
    if (fechado) return;
    fechado = true;
    marcar();
    el.classList.add('saindo');
    setTimeout(function () {
      el.remove();
      document.documentElement.classList.remove('intro-ativo');
      document.dispatchEvent(new CustomEvent('intro:fim'));
    }, 650);
  }

  setTimeout(fechar, Math.min(duracao + espera, limite));
  setTimeout(function () { el.classList.add('intro--pode-pular'); }, 600);
  var botao = el.querySelector('.intro__pular');
  if (botao) botao.addEventListener('click', fechar);
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') fechar(); });
})();
