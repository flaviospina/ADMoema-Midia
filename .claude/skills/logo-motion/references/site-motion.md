# Animação no site: splash de abertura, cabeçalho e rolagem

Os arquivos prontos estão em `assets/site/`:

- `intro-splash.css` e `intro-splash.js` — abertura com a logo, roda **uma vez por sessão**,
  botão "Pular", fecha com `Esc`, respeita `prefers-reduced-motion`, e nunca bloqueia a
  página por mais de 3 s (há um temporizador de segurança).
- `motion.css` — micro-interações do cabeçalho (logo com brilho ao passar o mouse, link com
  sublinhado deslizante), botões com reflexo, e classes `.reveal` / `.reveal--left` /
  `.reveal--scale` para entrada na rolagem.
- `motion.js` — `IntersectionObserver` para as classes `.reveal` e contador para números.

## Integrar o splash em um site existente

1. Gere a animação com `build_intro.js` usando `--site` em vez de `--out`. Isso escreve
   `intro-splash.html` (só o trecho a colar), copia `intro-splash.css` e `intro-splash.js`
   com a receita escolhida embutida, e não inclui `<html>/<body>`.
2. No `index.html`:
   - dentro do `<head>`, antes do CSS do site:
     `<link rel="stylesheet" href="intro-splash.css">`
   - logo após `<body>`: cole o conteúdo de `intro-splash.html`
   - antes de `</body>`: `<script src="intro-splash.js" defer></script>`
3. Abra a página, veja a abertura, recarregue: não deve aparecer de novo na mesma aba
   (é `sessionStorage`). Para testar de novo, abra em aba anônima ou rode no console
   `sessionStorage.removeItem('intro-visto')`.

Por que uma vez por sessão e não sempre? Quem navega entre seções e volta não quer
esperar a logo de novo. Por que `sessionStorage` e não `localStorage`? Para a pessoa ver a
abertura de novo amanhã; é parte da identidade do site, não um aviso legal.

## Cabeçalho (logo pequena)

Use em `motion.css`:

- `.marca:hover .marca__logo` → rotação de 6° e brilho que passa (400 ms). Discreto, mas
  vivo.
- Ao rolar mais de 40 px o `<header>` ganha `.topo--compacto` (altura menor, fundo mais
  sólido). `motion.js` cuida disso.
- Nunca anime a logo do cabeçalho em loop: ela compete com o conteúdo.

## Rolagem

Classes de `motion.css`:

| Classe | Movimento |
| --- | --- |
| `.reveal` | sobe 22 px e aparece |
| `.reveal--left` / `.reveal--right` | entra do lado |
| `.reveal--scale` | cresce de .94 |
| `.reveal--draw` | em um SVG inline, desenha os traços ao entrar (usa `pathLength="1"`) |
| `[data-stagger]` no pai | filhos escalonados 80 ms |
| `[data-contador="120"]` | número sobe de 0 até 120 ao entrar na tela |

Quando o site já tem um sistema de `.reveal` (comum), não duplique: só adicione as
variantes que faltam e mantenha a classe `visivel`/`visible` que o site já usa. Leia o CSS
existente antes.

## Parallax e efeitos de fundo

Brilhos flutuando no hero (`.hero__glow` com `translate` em 12–14 s) são baratos e dão
profundidade. Parallax de fundo com `background-attachment: fixed` não funciona no iOS;
use `transform: translateY(calc(var(--scroll) * -0.15px))` atualizado por `motion.js`
apenas se o usuário pedir explicitamente; é fácil exagerar.

## Checklist antes de entregar

- [ ] Splash some sozinho em até 3 s mesmo se algo falhar (temporizador).
- [ ] Botão "Pular" visível e funcional; `Esc` fecha.
- [ ] Segunda visita na mesma aba não mostra o splash.
- [ ] `prefers-reduced-motion`: splash não aparece (ou aparece estático e some rápido).
- [ ] Nada de rolagem horizontal em 360 px (elementos que entram "do lado" precisam de
      `overflow-x: clip` no pai).
- [ ] Capturas em 3 instantes conferidas.
