// public/assets/js/conta.js — desenha o QR Code do 2FA no próprio navegador (o segredo não sai do site)
'use strict';

(() => {
  const box = document.getElementById('qr');
  if (!box || typeof window.qrcode !== 'function') return;
  const qr = window.qrcode(0, 'M');
  qr.addData(box.dataset.uri);
  qr.make();
  box.innerHTML = qr.createSvgTag({ cellSize: 5, margin: 2, scalable: true });
  const svg = box.querySelector('svg');
  if (svg) svg.setAttribute('aria-label', 'QR Code para o aplicativo autenticador');
})();
