#!/usr/bin/env node
'use strict';
/**
 * Renderiza um HTML animado (gerado por build_intro.js ou qualquer HTML com animações CSS)
 * em vídeo, congelando o tempo quadro a quadro.
 *
 * node render_video.js --in intro.html --out saida.mp4|.webm|.gif|pasta/ [--format mp4|webm|gif|png]
 *   [--width 1920] [--height 1080] [--fps 30] [--duration s] [--hold 1] [--scale 1]
 *   [--alpha] [--bg #000] [--realtime]
 */
const fs = require('fs');
const path = require('path');
const { spawn, spawnSync } = require('child_process');

const args = lerArgs(process.argv.slice(2));
if (!args.in || !args.out) sair('Uso: node render_video.js --in intro.html --out saida.mp4');

const entrada = path.resolve(args.in);
if (!fs.existsSync(entrada)) sair(`Arquivo não encontrado: ${entrada}`);
const saida = path.resolve(args.out);
const width = parseInt(args.width || '1920', 10);
const height = parseInt(args.height || '1080', 10);
const fps = parseInt(args.fps || '30', 10);
const scale = parseFloat(args.scale || '1');
const alpha = !!args.alpha;
let formato = (args.format || path.extname(saida).slice(1) || (saida.endsWith('/') ? 'png' : 'mp4')).toLowerCase();
if (formato === 'jpg' || formato === 'jpeg') formato = 'png';
if (alpha && formato === 'mp4') { console.warn('MP4 não tem transparência; usando WebM VP9 com alfa.'); formato = 'webm'; }
if (alpha && formato === 'gif') console.warn('GIF só tem transparência binária; bordas podem ficar serrilhadas.');

(async () => {
  const { chromium } = carregarPlaywright();
  const ffmpeg = formato === 'png' ? null : acharFfmpeg(formato, alpha);

  const browser = await chromium.launch({ executablePath: acharChromium() });
  const context = await browser.newContext({
    viewport: { width, height }, deviceScaleFactor: scale,
    recordVideo: args.realtime ? { dir: path.join(path.dirname(saida), '.gravacao-tmp'), size: { width, height } } : undefined,
  });
  const page = await context.newPage();
  await page.goto('file://' + entrada, { waitUntil: 'load' });
  await page.evaluate(() => document.fonts && document.fonts.ready).catch(() => {});
  await page.waitForTimeout(150);

  if (alpha || args.bg) {
    await page.addStyleTag({ content: `html,body,.intro{background:${alpha ? 'transparent' : args.bg} !important}` });
  }

  const durHtml = await page.evaluate(() => {
    const el = document.querySelector('[data-duration]');
    return el ? { d: parseFloat(el.dataset.duration), hold: parseFloat(el.dataset.hold || '1'), loop: el.dataset.loop === 'true' } : null;
  });
  const duracao = parseFloat(args.duration) || (durHtml ? durHtml.d : 4);
  const hold = args.hold !== undefined ? parseFloat(args.hold) : (durHtml && !durHtml.loop ? durHtml.hold : 0);
  const totalMs = Math.round((duracao + hold) * 1000);
  const quadros = Math.max(1, Math.round((totalMs / 1000) * fps));
  console.log(`Renderizando ${quadros} quadros (${(totalMs / 1000).toFixed(1)}s a ${fps} fps, ${width}x${height}${alpha ? ', alfa' : ''}) → ${formato}`);

  if (args.realtime) {
    await page.waitForTimeout(totalMs);
    const video = page.video();
    await context.close();
    const webmTmp = await video.path();
    await browser.close();
    if (formato === 'webm' && !ffmpeg) { fs.renameSync(webmTmp, saida); }
    else { rodarFfmpegArquivo(ffmpeg, webmTmp, saida, formato, fps); }
    fs.rmSync(path.dirname(webmTmp), { recursive: true, force: true });
    console.log(`Pronto: ${saida}`);
    return;
  }

  // congela todas as animações
  await page.evaluate(() => document.getAnimations().forEach((a) => a.pause()));

  let escrever, finalizar;
  if (formato === 'png') {
    fs.mkdirSync(saida, { recursive: true });
    escrever = (buf, i) => fs.writeFileSync(path.join(saida, `quadro-${String(i).padStart(5, '0')}.png`), buf);
    finalizar = async () => {};
  } else {
    const ff = spawn(ffmpeg.bin, argsFfmpeg(formato, alpha, fps, saida), { stdio: ['pipe', 'inherit', 'pipe'] });
    let erroFf = '';
    ff.stderr.on('data', (d) => { erroFf += d.toString(); });
    const fim = new Promise((res, rej) => ff.on('close', (c) => (c === 0 ? res() : rej(new Error('ffmpeg falhou:\n' + erroFf.slice(-1500))))));
    escrever = (buf) => new Promise((res) => (ff.stdin.write(buf) ? res() : ff.stdin.once('drain', res)));
    finalizar = async () => { ff.stdin.end(); await fim; };
  }

  for (let i = 0; i < quadros; i++) {
    const t = Math.min((i / fps) * 1000, totalMs);
    await page.evaluate((ms) => document.getAnimations().forEach((a) => { a.currentTime = ms; }), t);
    const buf = await page.screenshot({ type: 'png', omitBackground: alpha });
    await escrever(buf, i);
    if (i % Math.max(1, Math.round(fps)) === 0) process.stdout.write(`\r  ${Math.round((i / quadros) * 100)}%`);
  }
  process.stdout.write('\r  100%\n');
  await browser.close();
  await finalizar();
  console.log(`Pronto: ${saida}`);
})().catch((e) => { console.error(e.message || e); process.exit(1); });

// ---------------------------------------------------------------- ffmpeg

function argsFfmpeg(formato, alpha, fps, saida) {
  const base = ['-y', '-hide_banner', '-loglevel', 'error', '-f', 'image2pipe', '-framerate', String(fps), '-i', '-'];
  if (formato === 'mp4') return [...base, '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-crf', '18', '-preset', 'medium', '-movflags', '+faststart', '-vf', 'scale=trunc(iw/2)*2:trunc(ih/2)*2', saida];
  if (formato === 'webm') return alpha
    ? [...base, '-c:v', 'libvpx-vp9', '-pix_fmt', 'yuva420p', '-b:v', '0', '-crf', '24', '-auto-alt-ref', '0', saida]
    : [...base, '-c:v', 'libvpx-vp9', '-pix_fmt', 'yuv420p', '-b:v', '0', '-crf', '24', saida];
  if (formato === 'webm-vp8') return [...base, '-c:v', 'libvpx', '-pix_fmt', 'yuv420p', '-b:v', '4M', saida];
  if (formato === 'gif') return [...base, '-vf', `fps=${fps},split[s0][s1];[s0]palettegen=max_colors=200:reserve_transparent=${alpha ? 1 : 0}[p];[s1][p]paletteuse=dither=bayer:bayer_scale=4`, '-loop', '0', saida];
  sair(`Formato não suportado: ${formato}`);
}

function rodarFfmpegArquivo(ffmpeg, entradaWebm, saida, formato, fps) {
  const r = spawnSync(ffmpeg.bin, ['-y', '-hide_banner', '-loglevel', 'error', '-i', entradaWebm, ...argsFfmpeg(formato, false, fps, saida).slice(10)], { stdio: 'inherit' });
  if (r.status !== 0) sair('ffmpeg falhou na conversão da gravação.');
}

function acharFfmpeg(formato, alpha) {
  const candidatos = [];
  if (process.env.FFMPEG_PATH) candidatos.push({ bin: process.env.FFMPEG_PATH, completo: true });
  const noPath = spawnSync(process.platform === 'win32' ? 'where' : 'which', ['ffmpeg'], { encoding: 'utf8' });
  if (noPath.status === 0 && noPath.stdout.trim()) candidatos.push({ bin: noPath.stdout.trim().split('\n')[0], completo: true });
  for (const dir of [process.cwd(), __dirname, path.resolve(__dirname, '..')]) {
    try { const p = require(require.resolve('ffmpeg-static', { paths: [dir] })); if (p && fs.existsSync(p)) candidatos.push({ bin: p, completo: true }); } catch (_) {}
  }
  const pw = process.env.PLAYWRIGHT_BROWSERS_PATH;
  if (pw && fs.existsSync(pw)) {
    for (const d of fs.readdirSync(pw)) {
      if (d.startsWith('ffmpeg')) {
        const bin = path.join(pw, d, process.platform === 'win32' ? 'ffmpeg-win64.exe' : process.platform === 'darwin' ? 'ffmpeg-mac' : 'ffmpeg-linux');
        if (fs.existsSync(bin)) candidatos.push({ bin, completo: false });
      }
    }
  }
  const escolhido = candidatos.find((c) => c.completo) || candidatos[0];
  if (!escolhido) sair('ffmpeg não encontrado. Instale com `npm i ffmpeg-static` ou use --format png.');
  if (!escolhido.completo && (formato === 'mp4' || formato === 'gif' || alpha)) {
    console.warn('Só o ffmpeg do Playwright está disponível (VP8/PNG). Instale `npm i ffmpeg-static` para MP4, GIF ou alfa. Gerando WebM VP8.');
    formato = 'webm-vp8';
  }
  escolhido.formato = formato;
  return escolhido;
}

function carregarPlaywright() {
  for (const nome of ['playwright', 'playwright-core']) {
    for (const dir of [process.cwd(), __dirname, path.resolve(__dirname, '..')]) {
      try { return require(require.resolve(nome, { paths: [dir] })); } catch (_) {}
    }
  }
  sair('Playwright não encontrado. Rode `npm i playwright-core` na pasta de trabalho.');
}

function acharChromium() {
  const pw = process.env.PLAYWRIGHT_BROWSERS_PATH;
  if (pw && fs.existsSync(path.join(pw, 'chromium'))) return path.join(pw, 'chromium');
  return undefined; // deixa o Playwright resolver o navegador instalado
}

function lerArgs(lista) {
  const o = {};
  for (let i = 0; i < lista.length; i++) {
    const a = lista[i]; if (!a.startsWith('--')) continue;
    const prox = lista[i + 1];
    if (prox === undefined || prox.startsWith('--')) o[a.slice(2)] = true; else { o[a.slice(2)] = prox; i++; }
  }
  return o;
}
function sair(m) { console.error('Erro: ' + m); process.exit(1); }
