#!/usr/bin/env node
'use strict';
/**
 * Inspeciona um arquivo de logo (SVG, PNG, JPG, WebP) e sugere a receita de animação.
 * Uso: node inspect_logo.js caminho/da/logo.svg [--json]
 */
const fs = require('fs');
const path = require('path');

const arquivo = process.argv[2];
const soJson = process.argv.includes('--json');
if (!arquivo || !fs.existsSync(arquivo)) {
  console.error('Uso: node inspect_logo.js <logo.svg|png|jpg|webp> [--json]');
  process.exit(1);
}

const buf = fs.readFileSync(arquivo);
const ext = path.extname(arquivo).toLowerCase();
let info;

if (ext === '.svg' || buf.slice(0, 200).toString('utf8').includes('<svg')) {
  info = inspecionarSvg(buf.toString('utf8'));
} else if (buf[0] === 0x89 && buf.toString('ascii', 1, 4) === 'PNG') {
  info = inspecionarPng(buf);
} else if (buf[0] === 0xff && buf[1] === 0xd8) {
  info = inspecionarJpeg(buf);
} else if (buf.toString('ascii', 0, 4) === 'RIFF' && buf.toString('ascii', 8, 12) === 'WEBP') {
  info = inspecionarWebp(buf);
} else {
  info = { tipo: 'desconhecido', avisos: ['Formato não reconhecido. Use SVG, PNG, JPG ou WebP.'] };
}

info.arquivo = arquivo;
info.tamanhoKb = Math.round(buf.length / 102.4) / 10;

if (soJson) {
  console.log(JSON.stringify(info, null, 2));
} else {
  imprimir(info);
}

// ---------------------------------------------------------------------------

function inspecionarSvg(svg) {
  const contar = (tag) => (svg.match(new RegExp(`<${tag}[\\s/>]`, 'g')) || []).length;
  const formas = { path: contar('path'), circle: contar('circle'), ellipse: contar('ellipse'), rect: contar('rect'), line: contar('line'), polyline: contar('polyline'), polygon: contar('polygon') };
  const totalFormas = Object.values(formas).reduce((a, b) => a + b, 0);
  const viewBox = (svg.match(/<svg[^>]*\sviewBox\s*=\s*["']([^"']+)["']/i) || [])[1] || null;
  const largura = (svg.match(/<svg[^>]*\swidth\s*=\s*["']([^"']+)["']/i) || [])[1] || null;
  const altura = (svg.match(/<svg[^>]*\sheight\s*=\s*["']([^"']+)["']/i) || [])[1] || null;
  const cores = new Set();
  for (const m of svg.matchAll(/(?:fill|stroke|stop-color)\s*[:=]\s*["']?\s*(#[0-9a-f]{3,8}|rgba?\([^)]*\)|hsla?\([^)]*\)|[a-z]{3,20})/gi)) {
    const c = m[1].toLowerCase();
    if (!['none', 'inherit', 'currentcolor', 'transparent', 'url'].includes(c)) cores.add(c);
  }
  const ids = [...svg.matchAll(/<g[^>]*\sid\s*=\s*["']([^"']+)["']/gi)].map((m) => m[1]);
  const temTexto = /<text[\s>]/i.test(svg);
  const temImagem = /<image[\s>]/i.test(svg);
  const temGradiente = /<(linear|radial)Gradient/i.test(svg);
  const temSmil = /<animate/i.test(svg);
  const avisos = [];
  if (!viewBox) avisos.push('Sem viewBox: o SVG não escala corretamente. build_intro.js tenta criar um a partir de width/height.');
  if (temTexto) avisos.push('Há <text> vivo: a fonte pode mudar em outro computador. Peça exportação com "texto em curvas".');
  if (temImagem) avisos.push('Há <image> embutida (bitmap dentro do SVG): a receita draw não desenha essa parte.');
  if (totalFormas > 200) avisos.push(`${totalFormas} formas: draw fica lento e confuso. Prefira reveal/rise ou agrupe as camadas.`);
  if (totalFormas === 0 && !temImagem) avisos.push('Nenhuma forma encontrada. O arquivo pode estar vazio ou usar <use>/<symbol>.');
  if (temSmil) avisos.push('Há animação SMIL (<animate>) no arquivo; ela não é congelada pelo render_video.js.');

  let receita = 'reveal';
  if (totalFormas > 0 && totalFormas <= 200 && !temImagem) receita = 'draw';
  return {
    tipo: 'svg', vetor: true, viewBox, largura, altura, formas, totalFormas, cores: [...cores].slice(0, 12),
    grupos: ids.slice(0, 20), temTexto, temImagem, temGradiente, receitaSugerida: receita,
    tracadoPossivel: receita === 'draw', avisos,
  };
}

function inspecionarPng(b) {
  const largura = b.readUInt32BE(16), altura = b.readUInt32BE(20);
  const tipoCor = b[25]; // 4 e 6 têm canal alfa
  const temTrns = b.includes(Buffer.from('tRNS'));
  const alfa = tipoCor === 4 || tipoCor === 6 || temTrns;
  const avisos = [];
  if (!alfa) avisos.push('PNG sem transparência: o retângulo da imagem vai aparecer. Peça a versão com fundo transparente (ou o SVG).');
  if (Math.max(largura, altura) < 600) avisos.push('Resolução baixa para telão/vídeo Full HD. Peça um arquivo maior ou o SVG.');
  if (Math.max(largura, altura) > 2400) avisos.push('Imagem muito grande: o HTML fica pesado. Redimensione para ~1200 px no lado maior.');
  avisos.push('Imagem (não vetor): a receita draw não se aplica. Use reveal, rise, flip, glow ou dual. Se existir SVG da logo, prefira-o.');
  return { tipo: 'png', vetor: false, largura, altura, transparencia: alfa, receitaSugerida: 'reveal', tracadoPossivel: false, avisos };
}

function inspecionarJpeg(b) {
  let i = 2, largura = null, altura = null;
  while (i < b.length) {
    if (b[i] !== 0xff) { i++; continue; }
    const marcador = b[i + 1];
    if (marcador >= 0xc0 && marcador <= 0xcf && ![0xc4, 0xc8, 0xcc].includes(marcador)) {
      altura = b.readUInt16BE(i + 5); largura = b.readUInt16BE(i + 7); break;
    }
    i += 2 + b.readUInt16BE(i + 2);
  }
  return {
    tipo: 'jpeg', vetor: false, largura, altura, transparencia: false, receitaSugerida: 'reveal', tracadoPossivel: false,
    avisos: ['JPEG nunca tem transparência: o fundo da foto aparece. Use reveal (que aceita retângulo) ou peça PNG transparente/SVG.'],
  };
}

function inspecionarWebp(b) {
  const chunk = b.toString('ascii', 12, 16);
  let largura = null, altura = null, alfa = false;
  if (chunk === 'VP8X') {
    largura = 1 + b.readUIntLE(24, 3); altura = 1 + b.readUIntLE(27, 3); alfa = !!(b[20] & 0x10);
  } else if (chunk === 'VP8 ') {
    largura = b.readUInt16LE(26) & 0x3fff; altura = b.readUInt16LE(28) & 0x3fff;
  } else if (chunk === 'VP8L') {
    const bits = b.readUInt32LE(21); largura = (bits & 0x3fff) + 1; altura = ((bits >> 14) & 0x3fff) + 1; alfa = !!((bits >> 28) & 1);
  }
  return { tipo: 'webp', vetor: false, largura, altura, transparencia: alfa, receitaSugerida: 'reveal', tracadoPossivel: false,
    avisos: alfa ? [] : ['WebP sem transparência: o retângulo aparece.'] };
}

function imprimir(i) {
  console.log(`Arquivo: ${i.arquivo} (${i.tamanhoKb} KB)`);
  console.log(`Tipo: ${i.tipo}${i.vetor ? ' (vetor)' : ' (imagem)'}`);
  if (i.tipo === 'svg') {
    console.log(`viewBox: ${i.viewBox || 'AUSENTE'}   width/height: ${i.largura || '-'} x ${i.altura || '-'}`);
    console.log(`Formas: ${i.totalFormas} (${Object.entries(i.formas).filter(([, n]) => n).map(([k, n]) => `${k}: ${n}`).join(', ') || 'nenhuma'})`);
    if (i.grupos.length) console.log(`Grupos com id: ${i.grupos.join(', ')}`);
    console.log(`Texto vivo: ${i.temTexto ? 'sim' : 'não'} · Imagem embutida: ${i.temImagem ? 'sim' : 'não'} · Gradiente: ${i.temGradiente ? 'sim' : 'não'}`);
  } else {
    console.log(`Dimensões: ${i.largura} x ${i.altura} px · Transparência: ${i.transparencia ? 'sim' : 'não'}`);
  }
  if (i.cores && i.cores.length) console.log(`Cores encontradas: ${i.cores.join(', ')}`);
  console.log(`Receita sugerida: ${i.receitaSugerida}${i.tracadoPossivel ? ' (traçado traço a traço possível)' : ''}`);
  if (i.avisos && i.avisos.length) { console.log('Avisos:'); i.avisos.forEach((a) => console.log(`  - ${a}`)); }
}
