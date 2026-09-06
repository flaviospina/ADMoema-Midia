# Princípios de motion para logos e sites

## Tempo

| Elemento | Duração | Por quê |
| --- | --- | --- |
| Micro-interação (hover, clique) | 150–300 ms | precisa parecer resposta imediata |
| Entrada de um elemento na rolagem | 500–700 ms | visível, mas não atrasa a leitura |
| Logo aparecendo em site | 1,2–2,5 s no total | acima disso o usuário quer pular |
| Vinheta de vídeo | 4–6 s | dá tempo de ler nome e subtítulo em telão |
| Loop de marca d'água | 4–8 s por ciclo | lento o bastante para não distrair |

Regra prática: **stagger** (escalonar) elementos com 60–120 ms entre eles. Mais que isso
parece lento; menos parece tudo junto.

## Easing (curvas)

- Entrada: `cubic-bezier(.2,.7,.2,1)` (rápido no começo, assenta suave). É a curva padrão dos
  arquivos desta skill.
- Saída: `cubic-bezier(.4,0,1,1)` (acelera e some).
- Traçado (`draw`): `ease-in-out` fica orgânico, como caneta.
- Nunca `linear` para movimento; só para rotação contínua ou brilho passando.

## Escala e desfoque

Entradas boas combinam três coisas pequenas: `opacity 0→1`, `scale .92→1` (não menos que
.85, senão parece pop-up) e `filter: blur(8px)→0`. O desfoque dá a sensação "de cinema"
sem custo alto, mas limite a 8–12 px e a elementos pequenos; blur em tela inteira pesa.

## SVG: o que verificar antes de animar

- `viewBox` presente. Sem ele o SVG não escala. O inspetor avisa.
- Texto vivo (`<text>`) depende da fonte instalada. Peça exportação "texto em curvas".
- `<image>` dentro do SVG é uma foto embutida, não vetor: `draw` não funciona nela.
- Muitos caminhos (200+) em `draw` ficam lentos e confusos. Agrupe ou use `reveal`.
- Camadas com `id` úteis (ex.: `simbolo`, `texto`) permitem animar partes separadas:
  símbolo primeiro, texto depois.

## Cores e fundo

- Fundo escuro (azul-marinho, grafite) valoriza logos douradas, brancas e coloridas.
- Logo escura sobre fundo escuro some: inverta para fundo claro ou coloque a logo em um
  "medalhão" claro.
- Um brilho (`glow`) só funciona com uma cor de destaque; use a cor secundária da marca.

## Acessibilidade

```css
@media (prefers-reduced-motion: reduce) {
  .intro * { animation: none !important; transition: none !important; }
  .intro { opacity: 1; }          /* mostra o estado final direto */
}
```

Ofereça sempre um botão "Pular" visível após 600 ms e feche o splash com `Esc`.
Nunca pisque mais de 3 vezes por segundo (risco de fotossensibilidade).

## Performance

- Só `transform`, `opacity`, `clip-path`, `stroke-dashoffset` e `filter` leve.
- `will-change: transform, opacity` apenas no elemento que vai animar, e remova depois.
- Logo em SVG inline pesa pouco; PNG grande (2000 px) como `data:` URI incha o HTML.
  Redimensione para no máximo 1200 px no lado maior.
- Splash de site: CSS inline no `<head>` para não piscar antes de carregar o arquivo CSS.
