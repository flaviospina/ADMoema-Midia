# Exportar a animação em vídeo

`scripts/render_video.js` abre o HTML no Chromium (Playwright), **congela o tempo** de
todas as animações CSS/Web Animations e captura quadro a quadro na taxa pedida. Por isso
o vídeo sai perfeitamente estável, sem depender da velocidade da máquina, e pode ter fundo
transparente. Depois o ffmpeg monta o arquivo.

```bash
node scripts/render_video.js --in vinheta.html --out vinheta.mp4            # 1920x1080, 30 fps
node scripts/render_video.js --in vinheta.html --out vinheta.webm --alpha   # fundo transparente
node scripts/render_video.js --in vinheta.html --out vinheta.gif --width 720 --height 405 --fps 15
node scripts/render_video.js --in vinheta.html --out quadros/ --format png  # sequência PNG
node scripts/render_video.js --in vinheta.html --out story.mp4 --width 1080 --height 1920
```

Parâmetros: `--width --height` (padrão 1920×1080), `--fps` (30), `--duration` em segundos
(padrão: lido do HTML, `data-duration`), `--hold` segundos extras segurando o último quadro
(padrão 1), `--scale` (deviceScaleFactor; 1 é suficiente), `--alpha` (fundo transparente;
força WebM VP9 ou PNG), `--bg` (cor de fundo alternativa só para o vídeo).

## Qual formato

| Uso | Formato | Observação |
| --- | --- | --- |
| PowerPoint, WhatsApp, YouTube, Instagram feed | MP4 (h264, yuv420p) | dimensões pares; 1080×1920 para story/reels |
| Sobrepor na transmissão (OBS, vMix, ATEM via computador) | WebM VP9 com alfa (`--alpha`) | OBS: Fonte de Mídia; marque "Reiniciar quando ativado" |
| Editor de vídeo (Premiere, Resolve, CapCut) | Sequência PNG com alfa | importe como sequência de imagens |
| Chat, e-mail, GitHub | GIF (≤ 720 px, 12–15 fps) | GIF não tem semitransparência; use fundo sólido |

## Onde está o ffmpeg

O script procura nesta ordem: `FFMPEG_PATH`, `ffmpeg` no PATH, pacote `ffmpeg-static`
(`npm i ffmpeg-static`), ffmpeg do Playwright (`PLAYWRIGHT_BROWSERS_PATH/ffmpeg-*/`).
O ffmpeg do Playwright só tem VP8 e PNG: nesse caso o script gera WebM VP8 (sem alfa)
ou PNG e avisa. Para MP4/GIF/alfa instale `ffmpeg-static`.

## Limites do congelamento de tempo

O congelamento usa `document.getAnimations()`, então cobre animações CSS e Web Animations
API, dentro e fora de SVG inline. Não cobre animações feitas com `requestAnimationFrame`
em JS, `<video>`, ou SMIL (`<animate>` dentro do SVG). Os templates desta skill usam só
CSS justamente por isso. Se um HTML de terceiros usar rAF, grave em tempo real com
`--realtime` (usa a gravação de vídeo do Playwright; menos estável, sem alfa).

## Áudio

O script não adiciona som. Para trilha, junte depois:

```bash
ffmpeg -i vinheta.mp4 -i trilha.mp3 -c:v copy -c:a aac -shortest vinheta-com-som.mp4
```
