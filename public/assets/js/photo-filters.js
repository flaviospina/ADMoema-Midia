// public/assets/js/photo-filters.js — processamento de imagem do editor (100% no navegador)
// Ajustes tonais via tabelas (LUT) por canal + cor por pixel + nitidez (máscara de nitidez).
'use strict';

window.PhotoFilters = (() => {
  const DEFAULTS = Object.freeze({
    exposure: 0, brightness: 0, contrast: 0, fade: 0,
    saturation: 0, vibrance: 0, temperature: 0, tint: 0,
    shadows: 0, highlights: 0, sharpness: 0, vignette: 0,
    grade: 'none', autoLevels: false,
  });

  const CROPS = { original: null, '1:1': 1, '4:5': 4 / 5, '9:16': 9 / 16, '16:9': 16 / 9, '3:2': 3 / 2 };

  /** Estilos prontos (aplicados sobre a correção automática, se ativa). */
  const PRESETS = [
    { id: 'natural', name: 'Original', params: {} },
    { id: 'vivido', name: 'Vívido', params: { saturation: 18, vibrance: 35, contrast: 18, sharpness: 20 } },
    { id: 'igreja', name: 'Igreja (pouca luz)', params: { exposure: 22, shadows: 45, highlights: -30, vibrance: 22, contrast: 6, sharpness: 30, temperature: 6 } },
    { id: 'retrato', name: 'Retrato', params: { temperature: 10, highlights: -15, shadows: 15, saturation: -6, vibrance: 10, contrast: 4, vignette: 18 } },
    { id: 'quente', name: 'Quente', params: { temperature: 32, vibrance: 12, contrast: 8 } },
    { id: 'frio', name: 'Frio', params: { temperature: -28, tint: -4, contrast: 10 } },
    { id: 'suave', name: 'Suave', params: { contrast: -18, shadows: 22, highlights: -18, fade: 16, saturation: -8, brightness: 8 } },
    { id: 'cinema', name: 'Cinema', params: { grade: 'cinema', contrast: 22, saturation: -12, fade: 10, vignette: 30 } },
    { id: 'pb', name: 'P&B Clássico', params: { grade: 'bw', contrast: 22, sharpness: 15 } },
    { id: 'pbdrama', name: 'P&B Dramático', params: { grade: 'bw', contrast: 50, shadows: -25, highlights: 10, vignette: 40, sharpness: 30 } },
    { id: 'vintage', name: 'Vintage', params: { grade: 'sepia', fade: 30, contrast: -10, vignette: 35, temperature: 10 } },
    { id: 'dourado', name: 'Dourado', params: { grade: 'golden', temperature: 18, contrast: 12, vibrance: 15, highlights: -10 } },
  ];

  const clamp01 = (v) => (v < 0 ? 0 : v > 1 ? 1 : v);

  // ---------------------------------------------------------------------
  // Geometria: girar, espelhar e recortar
  // ---------------------------------------------------------------------
  function renderGeometry(src, sw, sh, geo, maxSide) {
    const rot = ((geo.rotate % 360) + 360) % 360;
    const swap = rot === 90 || rot === 270;
    const rw = swap ? sh : sw;
    const rh = swap ? sw : sh;
    let cw = rw;
    let ch = rh;
    const ratio = CROPS[geo.crop] || null;
    if (ratio) {
      if (rw / rh > ratio) { cw = rh * ratio; ch = rh; } else { cw = rw; ch = rw / ratio; }
    }
    const slackX = rw - cw;
    const slackY = rh - ch;
    const cx = (slackX / 2) * (1 + (geo.offX || 0));
    const cy = (slackY / 2) * (1 + (geo.offY || 0));
    const s = Math.min(1, maxSide / Math.max(cw, ch));
    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.round(cw * s));
    canvas.height = Math.max(1, Math.round(ch * s));
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    ctx.imageSmoothingEnabled = true;
    ctx.imageSmoothingQuality = 'high';
    ctx.scale(s, s);
    ctx.translate(-cx, -cy);
    ctx.translate(rw / 2, rh / 2);
    if (geo.flipH) ctx.scale(-1, 1);
    ctx.rotate((rot * Math.PI) / 180);
    ctx.drawImage(src, -sw / 2, -sh / 2, sw, sh);
    return { canvas, slackX, slackY, scale: s };
  }

  // ---------------------------------------------------------------------
  // Análise automática
  // ---------------------------------------------------------------------
  /** Níveis por canal (corrige dominantes de cor e contraste "lavado"). */
  function computeLevels(imageData) {
    const d = imageData.data;
    const hist = [new Uint32Array(256), new Uint32Array(256), new Uint32Array(256)];
    const step = Math.max(1, Math.floor(d.length / 4 / 400000)) * 4; // amostra até ~400 mil pixels
    let n = 0;
    for (let i = 0; i < d.length; i += step) {
      hist[0][d[i]]++; hist[1][d[i + 1]]++; hist[2][d[i + 2]]++; n++;
    }
    const pct = (h, p) => {
      const target = n * p;
      let acc = 0;
      for (let v = 0; v < 256; v++) { acc += h[v]; if (acc >= target) return v; }
      return 255;
    };
    const lo = [0, 1, 2].map((c) => Math.min(pct(hist[c], 0.005), 60));
    const hi = [0, 1, 2].map((c) => Math.max(pct(hist[c], 0.995), 180));
    // Mistura com o mesmo nível para todos os canais: corrige a cor sem exagerar
    const loAll = Math.min(...lo);
    const hiAll = Math.max(...hi);
    return {
      lo: lo.map((v) => v * 0.65 + loAll * 0.35),
      hi: hi.map((v) => v * 0.65 + hiAll * 0.35),
    };
  }

  /** Sugere ajustes a partir da luminância da foto já com os níveis corrigidos. */
  function suggestAuto(imageData, levels) {
    const luts = buildLuts({ ...DEFAULTS }, levels);
    const d = imageData.data;
    const step = Math.max(1, Math.floor(d.length / 4 / 200000)) * 4;
    const hist = new Uint32Array(256);
    let sum = 0;
    let n = 0;
    for (let i = 0; i < d.length; i += step) {
      const l = (luts[0][d[i]] * 77 + luts[1][d[i + 1]] * 150 + luts[2][d[i + 2]] * 29) >> 8;
      hist[l]++; sum += l; n++;
    }
    const mean = sum / n / 255;
    let acc = 0;
    let p05 = 0;
    for (let v = 0; v < 256; v++) { acc += hist[v]; if (acc >= n * 0.05) { p05 = v / 255; break; } }
    let bright = 0;
    for (let v = 248; v < 256; v++) bright += hist[v];
    const clipped = bright / n;

    const params = { vibrance: 20, contrast: 8, sharpness: 30 };
    const exposure = (Math.log2(0.46 / Math.max(mean, 0.02)) / 1.5) * 100 * 0.8;
    params.exposure = Math.round(Math.max(-40, Math.min(60, exposure)));
    if (p05 < 0.06) params.shadows = 25;
    if (clipped > 0.02) params.highlights = -25;
    return params;
  }

  // ---------------------------------------------------------------------
  // Tons (por canal)
  // ---------------------------------------------------------------------
  function buildLuts(p, levels) {
    const out = [new Uint8ClampedArray(256), new Uint8ClampedArray(256), new Uint8ClampedArray(256)];
    const ev = Math.pow(2, (p.exposure / 100) * 1.5);
    const br = p.brightness / 100;
    const ct = p.contrast / 100;
    const sh = p.shadows / 100;
    const hl = p.highlights / 100;
    const fd = p.fade / 100;
    const t = p.temperature / 100;
    const tn = p.tint / 100;
    const wb = [1 + 0.15 * t + 0.05 * tn, 1 - 0.07 * tn, 1 - 0.15 * t + 0.05 * tn];
    const gamma = br >= 0 ? 1 / (1 + br * 0.9) : 1 - br * 0.9;
    for (let c = 0; c < 3; c++) {
      const lo = levels ? levels.lo[c] / 255 : 0;
      const hi = levels ? levels.hi[c] / 255 : 1;
      const span = Math.max(0.05, hi - lo);
      for (let i = 0; i < 256; i++) {
        let v = (i / 255 - lo) / span;
        v = clamp01(v * wb[c] * ev);
        v = Math.pow(v, gamma);
        if (sh) v += sh * 0.45 * Math.sqrt(v) * Math.pow(1 - v, 2.5) * (sh > 0 ? 1 : 0.8);
        if (hl) v += hl * 0.45 * v * v * Math.sqrt(Math.max(0, 1 - v)) * 1.3;
        v = clamp01(v);
        if (ct > 0) {
          const s = v * v * (3 - 2 * v);
          v += (s - v) * ct * 1.6;
        } else if (ct < 0) {
          v += (0.5 - v) * -ct * 0.5;
        }
        if (fd) v = v * (1 - fd * 0.22) + fd * 0.1;
        out[c][i] = clamp01(v) * 255;
      }
    }
    return out;
  }

  const GRADE = { none: 0, bw: 1, sepia: 2, cinema: 3, golden: 4 };

  // ---------------------------------------------------------------------
  // Aplicação completa (altera o ImageData)
  // ---------------------------------------------------------------------
  function apply(imageData, p, levels) {
    const d = imageData.data;
    const w = imageData.width;
    const h = imageData.height;
    const [lr, lg, lb] = buildLuts(p, p.autoLevels ? levels : null);
    const sat = p.saturation / 100;
    const vib = p.vibrance / 100;
    const grade = GRADE[p.grade] || 0;
    const vig = p.vignette / 100;
    const needColor = sat !== 0 || vib !== 0 || grade !== 0;
    const cx = w / 2;
    const cy = h / 2;
    const maxD = Math.sqrt(cx * cx + cy * cy);

    for (let y = 0, i = 0; y < h; y++) {
      const dy = (y - cy) / maxD;
      const dy2 = dy * dy;
      for (let x = 0; x < w; x++, i += 4) {
        let r = lr[d[i]];
        let g = lg[d[i + 1]];
        let b = lb[d[i + 2]];

        if (needColor) {
          const l = 0.299 * r + 0.587 * g + 0.114 * b;
          if (grade === 1) {
            r = g = b = l;
          } else {
            let f = 1 + sat;
            if (vib) {
              const mx = r > g ? (r > b ? r : b) : (g > b ? g : b);
              const mn = r < g ? (r < b ? r : b) : (g < b ? g : b);
              f += vib * (1 - (mx - mn) / 255) * (vib > 0 ? 1.2 : 1);
            }
            r = l + (r - l) * f;
            g = l + (g - l) * f;
            b = l + (b - l) * f;
            if (grade === 2) { // sépia
              const sr = 0.393 * r + 0.769 * g + 0.189 * b;
              const sg = 0.349 * r + 0.686 * g + 0.168 * b;
              const sb = 0.272 * r + 0.534 * g + 0.131 * b;
              r = r * 0.15 + sr * 0.85; g = g * 0.15 + sg * 0.85; b = b * 0.15 + sb * 0.85;
            } else if (grade === 3) { // cinema: sombras azul-petróleo, luzes alaranjadas
              const k = (l / 255 - 0.5) * 2;
              r += 18 * k; g += 3 * k; b -= 20 * k;
            } else if (grade === 4) { // dourado
              const k = l / 255;
              r += 14 * k; g += 7 * k; b -= 10 * k;
            }
          }
        }

        if (vig) {
          const dx = (x - cx) / maxD;
          const dist = Math.sqrt(dx * dx + dy2);
          if (dist > 0.42) {
            const t = (dist - 0.42) / 0.58;
            const k = 1 - vig * 0.75 * t * t * (3 - 2 * t);
            r *= k; g *= k; b *= k;
          }
        }
        d[i] = r; d[i + 1] = g; d[i + 2] = b;
      }
    }
    if (p.sharpness > 0) sharpen(imageData, p.sharpness, Math.max(1, Math.round(Math.max(w, h) / 1600)));
    return imageData;
  }

  /** Máscara de nitidez na luminância (não aumenta ruído de cor). */
  function sharpen(imageData, amount, radius) {
    const d = imageData.data;
    const w = imageData.width;
    const h = imageData.height;
    const n = w * h;
    const lum = new Uint8ClampedArray(n);
    for (let i = 0, j = 0; j < n; j++, i += 4) lum[j] = (d[i] * 77 + d[i + 1] * 150 + d[i + 2] * 29) >> 8;
    const blur = boxBlur(lum, w, h, radius);
    const k = (amount / 100) * 1.4;
    for (let i = 0, j = 0; j < n; j++, i += 4) {
      const diff = lum[j] - blur[j];
      if (diff > 2 || diff < -2) {
        const add = diff * k;
        d[i] += add; d[i + 1] += add; d[i + 2] += add;
      }
    }
  }

  function boxBlur(src, w, h, r) {
    const tmp = new Uint8ClampedArray(w * h);
    const out = new Uint8ClampedArray(w * h);
    const size = 2 * r + 1;
    for (let y = 0; y < h; y++) {
      const row = y * w;
      let sum = 0;
      for (let x = -r; x <= r; x++) sum += src[row + Math.min(w - 1, Math.max(0, x))];
      for (let x = 0; x < w; x++) {
        tmp[row + x] = sum / size;
        sum += src[row + Math.min(w - 1, x + r + 1)] - src[row + Math.max(0, x - r)];
      }
    }
    for (let x = 0; x < w; x++) {
      let sum = 0;
      for (let y = -r; y <= r; y++) sum += tmp[Math.min(h - 1, Math.max(0, y)) * w + x];
      for (let y = 0; y < h; y++) {
        out[y * w + x] = sum / size;
        sum += tmp[Math.min(h - 1, y + r + 1) * w + x] - tmp[Math.max(0, y - r) * w + x];
      }
    }
    return out;
  }

  return { DEFAULTS, CROPS, PRESETS, renderGeometry, computeLevels, suggestAuto, apply };
})();
