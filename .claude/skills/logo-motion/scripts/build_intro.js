#!/usr/bin/env node
'use strict';
/**
 * Gera um HTML autônomo com a animação da logo (e opcionalmente uma segunda logo).
 *
 * node build_intro.js --logo logo.svg [--logo2 outra.png] --recipe draw|reveal|rise|flip|glow|dual
 *   [--title "Texto"] [--subtitle "Texto"] [--bg #0b1f3a] [--accent #d4a72c] [--fg #fff]
 *   [--k 1] [--hold 1] [--logo-size 34] [--title-size 5] [--font Manrope] [--bg-image fundo.jpg]
 *   [--stroke-width 2] [--no-fill] [--keep-stroke] [--direction left|up|center]
 *   [--dual-order a-first|b-first] [--dual-final row|stack] [--sempre]  (--sempre: no site, roda em toda visita)
 *   --out intro.html            (HTML completo para pré-visualizar/renderizar)
 *   --site pasta/               (gera intro-splash.html/.css/.js para colar num site)
 */
const fs = require('fs');
const path = require('path');

const RAIZ = path.resolve(__dirname, '..');
const args = lerArgs(process.argv.slice(2));

const RECEITAS = {
  draw:   { duracao: 3.2, tTitle: 2.0, tSub: 2.4, tLine: 2.6, svgObrigatorio: true },
  reveal: { duracao: 2.6, tTitle: 1.4, tSub: 1.8, tLine: 2.0 },
  rise:   { duracao: 2.4, tTitle: 0.7, tSub: 1.1, tLine: 1.3 },
  flip:   { duracao: 2.6, tTitle: 1.3, tSub: 1.7, tLine: 1.9 },
  glow:   { duracao: 4.0, tTitle: 0.6, tSub: 1.0, tLine: 1.2, loop: true },
  dual:   { duracao: 6.0, tTitle: 3.4, tSub: 3.8, tLine: 4.0 },
};

if (!args.logo) sair('Informe --logo <arquivo>.');
if (!args.out && !args.site) sair('Informe --out arquivo.html ou --site pasta/.');

const logoA = carregarLogo(args.logo);
const logoB = args.logo2 ? carregarLogo(args.logo2) : null;

let receita = args.recipe;
if (!receita) receita = logoB ? 'dual' : (logoA.svg ? 'draw' : 'rise');
if (!RECEITAS[receita]) sair(`Receita desconhecida: ${receita}. Opções: ${Object.keys(RECEITAS).join(', ')}`);
if (receita === 'dual' && !logoB) sair('A receita dual precisa de --logo2.');
if (RECEITAS[receita].svgObrigatorio && !logoA.svg) sair('A receita draw precisa de uma logo em SVG. Use reveal, rise, flip ou glow para imagens.');

const cfg = RECEITAS[receita];
const k = num(args.k, 1);
const hold = num(args.hold, 1);
const opcoes = {
  bg: args.bg || '#0b1f3a',
  accent: args.accent || '#d4a72c',
  fg: args.fg || '#ffffff',
  font: args.font || 'Manrope',
  logoSize: num(args['logo-size'], receita === 'dual' ? 28 : 34),
  titleSize: num(args['title-size'], 5),
  strokeWidth: num(args['stroke-width'], 2),
  direction: args.direction || 'left',
  dualOrder: args['dual-order'] || 'a-first',
  dualFinal: args['dual-final'] || 'row',
};

// ---------------------------------------------------------------- markup
const classes = ['intro'];
if (args['no-fill']) classes.push('intro--sem-preenchimento');
if (args['keep-stroke']) classes.push('intro--manter-traco');
if (opcoes.dualFinal === 'stack') classes.push('intro--empilhado');

const estilo = [
  `--bg:${opcoes.bg}`, `--accent:${opcoes.accent}`, `--fg:${opcoes.fg}`, `--k:${k}`,
  `--ls:${opcoes.logoSize}`, `--title-size:${opcoes.titleSize}`, `--stroke-width:${opcoes.strokeWidth}`,
  `--t-title:${cfg.tTitle}s`, `--t-sub:${cfg.tSub}s`, `--t-line:${cfg.tLine}s`,
  `--font:'${opcoes.font}'`,
];
if (args['bg-image']) estilo.push(`--bg-image:url('${dataUri(args['bg-image'])}')`);

const blocoLogo = (l, letra) => `<div class="intro__logo intro__logo--${letra}" style="--logo-mask:url('${l.maskUri}')">${l.html}</div>`;
let palco;
if (receita === 'dual') {
  const primeiro = opcoes.dualOrder === 'b-first' ? blocoLogo(logoB, 'a') : blocoLogo(logoA, 'a');
  const segundo = opcoes.dualOrder === 'b-first' ? blocoLogo(logoA, 'b') : blocoLogo(logoB, 'b');
  palco = `<div class="intro__lockup">${primeiro}<div class="intro__divider"></div>${segundo}</div>`;
} else {
  palco = blocoLogo(logoA, 'a');
}

const titulo = args.title ? `<h1 class="intro__title">${palavras(args.title)}</h1>` : '';
const subtitulo = args.subtitle ? `<p class="intro__subtitle">${escapar(args.subtitle)}</p>` : '';
const texto = titulo || subtitulo ? `<div class="intro__text">${titulo}${subtitulo}</div>` : '';

const markup = (extra = '') =>
`<div id="intro-splash" class="${classes.join(' ')}" data-recipe="${receita}" data-direction="${opcoes.direction}" data-duration="${(cfg.duracao * k).toFixed(2)}" data-hold="${hold}"${cfg.loop ? ' data-loop="true"' : ''}${args.sempre ? ' data-sempre="true"' : ''} style="${estilo.join(';')}" role="img" aria-label="${escapar([args.title, args.subtitle].filter(Boolean).join(' — ') || 'Logo animada')}">
  <div class="intro__flare"></div>
  <div class="intro__stage">
    ${palco}
    ${texto}
  </div>${extra}
</div>`;

// ---------------------------------------------------------------- css
const baseCss = fs.readFileSync(path.join(RAIZ, 'assets/templates/base.css'), 'utf8');
const receitaCss = fs.readFileSync(path.join(RAIZ, 'assets/recipes', `${receita}.css`), 'utf8');
const fontLink = `<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=${encodeURIComponent(opcoes.font).replace(/%20/g, '+')}:wght@400;600;700;800&display=swap" rel="stylesheet">`;

if (args.out) {
  const html = `<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>${escapar(args.title || 'Logo animada')}</title>
${fontLink}
<style>
html,body{margin:0;height:100%;background:${opcoes.bg};overflow:hidden}
${baseCss}
${receitaCss}
.intro{position:fixed;inset:0}
</style>
</head>
<body>
${markup()}
<script>
// Clique para repetir (só na pré-visualização)
document.addEventListener('click', function () {
  var el = document.getElementById('intro-splash'); var novo = el.cloneNode(true);
  el.parentNode.replaceChild(novo, el);
});
</script>
</body>
</html>
`;
  fs.mkdirSync(path.dirname(path.resolve(args.out)), { recursive: true });
  fs.writeFileSync(args.out, html);
  console.log(`HTML gerado: ${args.out}`);
}

if (args.site) {
  const pasta = args.site;
  fs.mkdirSync(pasta, { recursive: true });
  const splashCss = fs.readFileSync(path.join(RAIZ, 'assets/site/intro-splash.css'), 'utf8');
  const splashJs = fs.readFileSync(path.join(RAIZ, 'assets/site/intro-splash.js'), 'utf8');
  const botao = `\n  <button type="button" class="intro__pular" aria-label="Pular abertura">Pular</button>`;
  const inline = args.sempre ? '' : `\n<script>try{if(sessionStorage.getItem('intro-visto')){var _i=document.getElementById('intro-splash');_i&&_i.parentNode.removeChild(_i)}}catch(e){}</script>`;
  fs.writeFileSync(path.join(pasta, 'intro-splash.html'), markup(botao) + inline + '\n');
  fs.writeFileSync(path.join(pasta, 'intro-splash.css'), `/* Abertura com logo — gerado por logo-motion (receita: ${receita}) */\n${baseCss}\n${receitaCss}\n${splashCss}`);
  fs.writeFileSync(path.join(pasta, 'intro-splash.js'), splashJs);
  for (const f of ['motion.css', 'motion.js']) fs.copyFileSync(path.join(RAIZ, 'assets/site', f), path.join(pasta, f));
  console.log(`Arquivos de site gerados em ${pasta}: intro-splash.html, intro-splash.css, intro-splash.js, motion.css, motion.js`);
  console.log('Cole intro-splash.html logo após <body>, o CSS no <head> e o JS antes de </body>. Detalhes em references/site-motion.md.');
}

console.log(`Receita: ${receita} · duração ${(cfg.duracao * k).toFixed(1)}s${cfg.loop ? ' (loop)' : ''} · logo ${logoA.tipo}${logoB ? ' + ' + logoB.tipo : ''}`);

// ---------------------------------------------------------------- funções

function carregarLogo(arquivo) {
  if (!fs.existsSync(arquivo)) sair(`Arquivo não encontrado: ${arquivo}`);
  const ext = path.extname(arquivo).toLowerCase();
  const buf = fs.readFileSync(arquivo);
  if (ext === '.svg' || buf.slice(0, 300).toString('utf8').includes('<svg')) {
    const svg = prepararSvg(buf.toString('utf8'));
    return { svg: true, tipo: 'svg', html: svg.inline, maskUri: 'data:image/svg+xml;base64,' + Buffer.from(svg.limpo).toString('base64') };
  }
  const mime = { '.png': 'image/png', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.webp': 'image/webp', '.gif': 'image/gif' }[ext];
  if (!mime) sair(`Formato não suportado: ${ext}`);
  const uri = `data:${mime};base64,${buf.toString('base64')}`;
  return { svg: false, tipo: ext.slice(1), html: `<img src="${uri}" alt="">`, maskUri: uri };
}

function prepararSvg(bruto) {
  let s = bruto.replace(/<\?xml[^>]*\?>/gi, '').replace(/<!DOCTYPE[^>]*>/gi, '').replace(/<!--[\s\S]*?-->/g, '').trim();
  // garante viewBox
  const abre = s.match(/<svg[^>]*>/i);
  if (!abre) sair('SVG inválido: tag <svg> não encontrada.');
  let tag = abre[0];
  if (!/\sviewBox\s*=/i.test(tag)) {
    const w = parseFloat((tag.match(/\swidth\s*=\s*["']([\d.]+)/i) || [])[1]);
    const h = parseFloat((tag.match(/\sheight\s*=\s*["']([\d.]+)/i) || [])[1]);
    if (w && h) tag = tag.replace(/<svg/i, `<svg viewBox="0 0 ${w} ${h}"`);
    else console.warn('Aviso: SVG sem viewBox e sem width/height; a escala pode ficar errada.');
  }
  tag = tag.replace(/\s(width|height)\s*=\s*["'][^"']*["']/gi, '');
  if (!/\sxmlns\s*=/i.test(tag)) tag = tag.replace(/<svg/i, '<svg xmlns="http://www.w3.org/2000/svg"');
  const limpo = s.replace(abre[0], tag);
  // versão inline: classe na raiz + preparo para traçado.
  // Percorre as tags mantendo uma pilha de <g>/<svg> para resolver fill/stroke herdados:
  // formas com preenchimento (draw--f) são traçadas na cor de destaque e depois preenchidas;
  // formas só de traço (draw--s) são desenhadas na própria cor e mantidas.
  const FORMAS = new Set(['path', 'circle', 'ellipse', 'rect', 'line', 'polyline', 'polygon']);
  const pilha = [];
  let i = 0;
  const lerProp = (attrs, nome) => {
    const m = attrs.match(new RegExp(`\\s${nome}\\s*=\\s*["']([^"']*)["']`, 'i'));
    if (m) return m[1].trim();
    const st = attrs.match(/\sstyle\s*=\s*["']([^"']*)["']/i);
    if (st) { const mm = st[1].match(new RegExp(`(?:^|;)\\s*${nome}\\s*:\\s*([^;]+)`, 'i')); if (mm) return mm[1].trim(); }
    return null;
  };
  const herdado = (attrs, nome, padrao) => {
    const proprio = lerProp(attrs, nome);
    if (proprio) return proprio;
    for (let j = pilha.length - 1; j >= 0; j--) if (pilha[j][nome]) return pilha[j][nome];
    return padrao;
  };
  const inline = limpo.replace(/<(\/?)([a-zA-Z][\w:-]*)([^>]*?)(\/?)>/g, (m, fecha, nome, attrs = '', auto) => {
    const tag = nome.toLowerCase();
    if (fecha) { if (tag === 'g' || tag === 'svg') pilha.pop(); return m; }
    if (tag === 'svg') {
      pilha.push({ fill: lerProp(attrs, 'fill'), stroke: lerProp(attrs, 'stroke') });
      return `<svg class="logo-svg" focusable="false" aria-hidden="true"${attrs}>`;
    }
    if (tag === 'g') { if (!auto) pilha.push({ fill: lerProp(attrs, 'fill'), stroke: lerProp(attrs, 'stroke') }); return m; }
    if (!FORMAS.has(tag)) return m;
    const fill = (herdado(attrs, 'fill', 'black') || '').toLowerCase();
    const stroke = (herdado(attrs, 'stroke', 'none') || '').toLowerCase();
    const temFill = fill !== 'none' && fill !== 'transparent' && !(tag === 'line' || tag === 'polyline');
    const temStroke = stroke !== 'none' && stroke !== 'transparent';
    const classe = temFill ? 'draw draw--f' : (temStroke ? 'draw draw--s' : 'draw draw--f');
    const idx = Math.min(i++, 14);
    let a = attrs;
    if (!/\spathLength\s*=/i.test(a)) a += ' pathLength="1"';
    a = /\sclass\s*=\s*["']/i.test(a) ? a.replace(/\sclass\s*=\s*(["'])/i, ` class=$1${classe} `) : a + ` class="${classe}"`;
    a = /\sstyle\s*=\s*["']/i.test(a) ? a.replace(/\sstyle\s*=\s*(["'])/i, ` style=$1--i:${idx};`) : a + ` style="--i:${idx}"`;
    return `<${nome}${a}${auto}>`;
  });
  return { limpo, inline };
}

function dataUri(arquivo) {
  const ext = path.extname(arquivo).toLowerCase();
  const mime = { '.png': 'image/png', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.webp': 'image/webp', '.svg': 'image/svg+xml' }[ext] || 'application/octet-stream';
  return `data:${mime};base64,${fs.readFileSync(arquivo).toString('base64')}`;
}

function palavras(t) {
  return String(t).trim().split(/\s+/).map((p, i) => `<span class="w" style="--i:${i}">${escapar(p)}</span>`).join(' ');
}

function escapar(t) {
  return String(t ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function num(v, padrao) { const n = parseFloat(v); return Number.isFinite(n) ? n : padrao; }

function lerArgs(lista) {
  const o = {};
  for (let i = 0; i < lista.length; i++) {
    const a = lista[i];
    if (!a.startsWith('--')) continue;
    const chave = a.slice(2);
    const prox = lista[i + 1];
    if (prox === undefined || prox.startsWith('--')) o[chave] = true;
    else { o[chave] = prox; i++; }
  }
  return o;
}

function sair(msg) { console.error('Erro: ' + msg); process.exit(1); }
