---
name: logo-motion
description: Cria animações modernas de logotipos e de sites com HTML/CSS/SVG puros (sem dependências no site), incluindo vinheta de abertura (intro/splash) com uma ou duas logos em sequência, logo desenhada traço a traço (stroke draw), reveal com brilho, flip 3D, glow em loop para marca d'água, micro-interações do cabeçalho e animações de rolagem; e exporta a mesma animação em vídeo (MP4/WebM com transparência/GIF/sequência PNG) para usar em transmissão, telão, Instagram ou apresentação. Use esta skill sempre que o usuário falar em animar uma logo, logomarca, marca, brasão, monograma, "vinheta", "abertura", "intro", "splash", "logo animada", "animação do site", "efeito na logo", "logo aparecendo", "motion" ou quiser um vídeo/GIF a partir de uma logo — mesmo que só mande o arquivo da logo e diga "dá vida nisso". Também use ao adicionar animações de entrada, hover ou scroll em uma landing page já existente.
---

# Logo Motion — animação de logos e de sites

Esta skill transforma um arquivo de logo (SVG de preferência; PNG/JPG/WebP também servem)
em uma animação profissional que roda em qualquer site estático (HostGator, GitHub Pages,
WordPress) e, opcionalmente, vira vídeo para OBS, vMix, telão, Instagram ou PowerPoint.

Tudo é HTML + CSS + SVG puro. Sem bibliotecas no site, sem build. Isso importa porque o
resultado costuma ser colado em páginas simples mantidas por voluntários, e porque
animações só de `transform`, `opacity`, `clip-path` e `stroke-dashoffset` rodam a 60 fps
até em celular fraco.

## Fluxo de trabalho

1. **Entenda o pedido e peça a logo certa.** Pergunte (ou verifique nos anexos) qual é o
   arquivo. Se só houver PNG/JPG, explique em uma frase que a animação "traço a traço"
   precisa do SVG (vetor) e ofereça as receitas que funcionam com imagem (reveal, rise,
   flip, glow, dual). Nunca redesenhe uma logo oficial "de cabeça": uma igreja, empresa ou
   instituição tem regras de marca. Use o arquivo que o usuário enviou; se não houver,
   use um marcador provisório claramente identificado e diga onde trocar.
2. **Inspecione o arquivo** com `scripts/inspect_logo.js`. Ele diz se é vetor ou imagem,
   quantos caminhos tem, cores, se há texto vivo (que precisa virar curva) e sugere a
   receita. Leia `references/recipes.md` para escolher entre as receitas.
3. **Gere a animação** com `scripts/build_intro.js`. Ele produz um HTML autônomo
   (logo embutida, sem arquivos externos) que já serve como pré-visualização e como fonte
   do vídeo. Ajuste cores, título, subtítulo e duração pelos parâmetros.
4. **Pré-visualize e confira**: abra o HTML no Chromium via Playwright e tire capturas em
   3 ou 4 instantes (início, meio, fim). Olhe as imagens: a logo está inteira? Cortada?
   O contraste com o fundo está bom? Texto legível em 360 px de largura?
5. **Integre ao site** se for o caso. Leia `references/site-motion.md` e use os arquivos
   prontos em `assets/site/` (splash de abertura que roda uma vez por sessão, com botão
   "pular", respeitando `prefers-reduced-motion`). Micro-interações do cabeçalho e
   animações de rolagem estão no mesmo arquivo.
6. **Exporte o vídeo** quando for útil (vinheta para transmissão, post, apresentação) com
   `scripts/render_video.js`. Leia `references/video-export.md` para formatos: MP4 (h264)
   para uso geral, WebM com transparência para sobrepor em transmissão, GIF para chat,
   sequência PNG para editores de vídeo.
7. **Entregue** o HTML, os arquivos de integração e o vídeo, e explique em linguagem
   simples onde colar cada coisa. Muitos usuários desta skill não têm terminal.

## Receitas disponíveis (resumo)

| Receita | Precisa de SVG? | Melhor para | Duração padrão |
| --- | --- | --- | --- |
| `draw` | Sim | logos com contorno ou monogramas; efeito "sendo desenhada" | 3,2 s |
| `reveal` | Não | qualquer logo; cortina com feixe de luz, elegante e sóbrio | 2,6 s |
| `rise` | Não | logo + texto; entrada suave com desfoque, padrão de site moderno | 2,4 s |
| `flip` | Não | logo compacta/quadrada; giro 3D com brilho | 2,6 s |
| `glow` | Não | marca d'água em loop, tela de espera, lower third | 4 s (loop) |
| `dual` | Não | duas logos em sequência (ex.: ministério + igreja), termina em lockup | 6 s |

Detalhes, variações e o CSS de cada uma em `references/recipes.md`.

## Princípios que não se negociam

- **Curta e com propósito.** Uma abertura de site passa de 2,5 s e vira incômodo; vinheta
  de vídeo pode ter 4 a 6 s. Sempre ofereça "pular" e rode só uma vez por sessão.
- **Acessível.** Respeite `prefers-reduced-motion` (mostre o estado final sem movimento).
  Nunca use flashes rápidos ou piscadas.
- **Performance.** Anime só `transform`, `opacity`, `clip-path`, `filter` leve e
  `stroke-dashoffset`. Nada de animar `width`, `top`, `box-shadow` grande.
- **Fiel à marca.** Cores da marca vêm do usuário ou do próprio arquivo (o inspetor lista).
  O fundo padrão é escuro porque valoriza quase toda logo; mude se a marca for clara.
- **Sempre olhe o resultado.** Capturas em 3 instantes antes de entregar. Um `clip-path`
  invertido ou um `viewBox` errado só aparecem na imagem.

Leia `references/principles.md` para tempos, curvas de easing e escolhas tipográficas.

## Comandos rápidos

```bash
S=.claude/skills/logo-motion            # ou o caminho onde a skill estiver instalada
node $S/scripts/inspect_logo.js logo.svg
node $S/scripts/build_intro.js --logo logo.svg --recipe draw \
  --title "Ministério de Mídia" --subtitle "ADMoema" --accent "#d4a72c" --bg "#0b1f3a" \
  --out intro.html
node $S/scripts/build_intro.js --logo logo-a.svg --logo2 logo-b.png --recipe dual \
  --title "Mídia ADMoema" --subtitle "Assembleia de Deus · Ministério do Belém" --out vinheta.html
node $S/scripts/render_video.js --in vinheta.html --out vinheta.mp4 --fps 30
node $S/scripts/render_video.js --in vinheta.html --out vinheta-alpha.webm --alpha
```

Os scripts usam Node 18+. `render_video.js` precisa de `playwright-core` (ou `playwright`)
e usa o ffmpeg que encontrar: variável `FFMPEG_PATH`, `ffmpeg` no PATH, pacote npm
`ffmpeg-static`, ou o ffmpeg do próprio Playwright (este último só gera WebM e PNG).
Instale o que faltar com `npm i playwright-core ffmpeg-static` na pasta de trabalho.
