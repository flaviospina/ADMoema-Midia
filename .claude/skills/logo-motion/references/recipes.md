# Catálogo de receitas

Cada receita é um arquivo CSS em `assets/recipes/<nome>.css`, escrito contra a mesma
estrutura HTML gerada por `scripts/build_intro.js` (abaixo). Você pode copiar o CSS
direto para um site: basta manter as classes.

```html
<div class="intro" data-recipe="draw" style="--bg:#0b1f3a; --accent:#d4a72c; --fg:#fff; --k:1">
  <div class="intro__stage">
    <div class="intro__logo intro__logo--a"> <svg …> ou <img …> </div>
    <div class="intro__divider"></div>                <!-- só na dual -->
    <div class="intro__logo intro__logo--b"> … </div> <!-- só na dual -->
    <div class="intro__text">
      <h1 class="intro__title"><span class="w">Ministério</span> <span class="w">de</span> …</h1>
      <p class="intro__subtitle">…</p>
    </div>
  </div>
  <div class="intro__flare"></div>
</div>
```

`--k` é o multiplicador de tempo (1 = duração padrão; 1.5 = 50% mais lenta).
Os tempos abaixo estão para `--k:1`.

---

## `draw` — desenhada traço a traço  (precisa de SVG)

**Efeito:** os contornos da logo são "desenhados" com a cor de destaque, depois o
preenchimento original surge por baixo; o título entra palavra por palavra.

**Como funciona:** cada `path/line/polyline/polygon/circle/ellipse/rect` recebe
`pathLength="1"`, o que permite `stroke-dasharray:1; stroke-dashoffset:1` sem medir o
comprimento real. `build_intro.js` faz isso automaticamente.

**Linha do tempo:** 0–1,8 s traçado (escalonado 40 ms por elemento, máximo 12 níveis) ·
1,4–2,4 s preenchimento · 2,0 s título · 2,4 s subtítulo · total 3,2 s.

**Variações:** `--stroke-width 3` para logos finas; `--no-fill` para ficar só o contorno
(bom em fundo escuro e telão).

**Quando evitar:** PNG (não há caminhos); SVG com 200+ elementos; logo feita de `<image>`.

## `reveal` — cortina com feixe de luz  (qualquer arquivo)

**Efeito:** a logo aparece por um `clip-path` que abre da esquerda para a direita enquanto
um feixe de luz diagonal cruza a marca; leve zoom-out no final.

**Linha do tempo:** 0,2–1,3 s abertura · 0,9–1,7 s feixe · 1,4 s título · 1,8 s subtítulo ·
total 2,6 s.

**Variações:** `--direction up` (abre de baixo para cima), `--direction center` (abre do
centro, íris). Sem título fica ótimo como bumper de 2 s.

## `rise` — subida suave com desfoque  (qualquer arquivo)

**Efeito:** a logo sobe 24 px, sai do desfoque e assenta com `scale .92→1`; título entra
palavra por palavra; uma linha fina se estende sob o texto.

**Linha do tempo:** 0–1,1 s logo · 0,7 s título · 1,1 s subtítulo · 1,3 s linha ·
total 2,4 s. É a receita padrão para abertura de site (curta e discreta).

## `flip` — giro 3D com brilho  (qualquer arquivo)

**Efeito:** a logo gira 90°→0° em `rotateY` com perspectiva, um reflexo passa pela face e
uma sombra assenta. Funciona melhor com logo quadrada ou monograma.

**Linha do tempo:** 0–1,2 s giro · 0,9–1,5 s reflexo · 1,3 s título · total 2,6 s.

**Cuidado:** em PNG com fundo transparente o reflexo precisa de `mask-image` (já incluso);
em JPG o retângulo da imagem aparece — use `reveal`.

## `glow` — pulso luminoso em loop  (qualquer arquivo)

**Efeito:** respiração de brilho na cor de destaque (`drop-shadow` animada via opacity de
uma cópia desfocada, não via `filter` no elemento principal) e um shimmer diagonal a cada
ciclo. Loop infinito.

**Uso:** tela de espera ("já começamos"), marca d'água em transmissão (`--alpha` no
vídeo), rodapé animado sutil.

**Ciclo:** 4 s. Sem título por padrão; com `--title` o texto ganha shimmer sincronizado.

## `dual` — duas logos em sequência, termina em lockup  (qualquer arquivo)

**Efeito pensado para "ministério + igreja", "produto + empresa", "evento + patrocinador":**
1. Logo A entra (rise) e segura sozinha, centralizada.
2. Logo A desliza para a esquerda e reduz um pouco; um divisor vertical cresce; a logo B
   entra pela direita com desfoque.
3. As duas seguram lado a lado (lockup), título e subtítulo aparecem embaixo.

**Linha do tempo:** 0–1,1 s A · 2,2–3,2 s transição + B · 3,4 s título · 3,8 s subtítulo ·
total 6 s. Ordem: passe a logo principal em `--logo` e a institucional em `--logo2`.

**Variações:** `--dual-order b-first` inverte; `--dual-final stack` empilha (A em cima,
B embaixo), útil em vídeo vertical (9:16).

---

## Escolhendo

- Tem SVG e a logo tem contornos ou é um monograma? `draw`. É o efeito que as pessoas
  mais reconhecem como "animação da logo".
- Só tem PNG? `reveal` (sóbrio) ou `rise` (moderno). Se a logo é quadrada, `flip`.
- Duas marcas? `dual`, sempre. Não tente encaixar duas logos numa receita de uma.
- Vai ficar em loop numa tela? `glow`.
- Abertura de site: `rise` ou `reveal` com `--k .85` (mais rápida). Vinheta de vídeo:
  qualquer uma com `--k 1.2` e `--hold 1.5` (segura o quadro final 1,5 s).

## Ajustes finos comuns

- Logo parece pequena: `--logo-size 44` (porcentagem da menor dimensão da tela; padrão 34).
- Título muito grande em celular: o template usa `clamp()`; passe `--title-size 5` (vw).
- Fundo com textura: `--bg-image caminho.jpg` coloca uma imagem com véu escuro por cima.
- Fonte: `--font "Manrope"` (Google Fonts; o HTML inclui o `<link>` e um fallback do sistema).
