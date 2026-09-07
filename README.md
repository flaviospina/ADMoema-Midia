# Ministério de Mídia ADMoema

Landing page com a proposta de estruturação e expansão do Ministério de Mídia da
Assembleia de Deus – Ministério do Belém (Setor 124 · Moema), com o formulário das
**três perguntas para cada integrante** e banco de dados que registra as respostas e as
ações dos visitantes.

Feito para hospedagem compartilhada (HostGator): **PHP + SQLite**, sem instalação, sem
terminal. Basta subir os arquivos.

## Como publicar na HostGator (sem terminal)

### Parte 1 · Criar o banco MySQL no cPanel (uma vez só)

1. cPanel → **Bancos de Dados MySQL®**.
2. Em *Criar novo banco de dados*, digite `admoema` e clique em **Criar**. O nome final fica
   `SEUUSUARIO_admoema` (o cPanel coloca o seu usuário na frente). Anote.
3. Em *Usuários MySQL → Adicionar novo usuário*, digite `midia`, crie uma senha forte
   (use o gerador) e clique em **Criar usuário**. O nome final fica `SEUUSUARIO_midia`. Anote a senha.
4. Em *Adicionar usuário ao banco de dados*, escolha o usuário e o banco, clique em **Adicionar**,
   marque **TODOS OS PRIVILÉGIOS** e confirme.
5. (Opcional) cPanel → **phpMyAdmin** → clique no banco → aba **Importar** → envie
   `dados/schema-mysql.sql`. O site cria as tabelas sozinho se você pular este passo.

### Parte 2 · Subir os arquivos

1. **Baixe o pacote** `admoema-midia-hostgator.zip` (está na raiz deste repositório).
2. cPanel → **Arquivos → Gerenciador de Arquivos** → abra `public_html` (ou crie e entre na
   pasta `midia` para o endereço `seusite.com.br/midia`).
3. **Upload** do `.zip` → botão direito → **Extract** → apague o `.zip`. Se houver um
   `index.html` antigo na pasta, apague-o.
4. Abra `api/config.php` (botão direito → **Edit**) e preencha, com o que anotou na Parte 1:
   ```php
   const MYSQL_BANCO   = 'SEUUSUARIO_admoema';
   const MYSQL_USUARIO = 'SEUUSUARIO_midia';
   const MYSQL_SENHA   = 'a senha do usuário';
   ```
   Troque também a `SENHA_ADMIN` do painel. Salve.
5. Abra **`seusite.com.br/midia/api/instalar.php`** no navegador. Ele conecta no MySQL, cria as
   tabelas e mostra as checagens (verde = ok; vermelho = o que corrigir e como).
6. Abra o site. Para ver as respostas, use `admin.html` com a `SENHA_ADMIN`.

> Quer testar sem MySQL? Em `api/config.php` troque `BANCO_TIPO` para `'sqlite'`: o banco vira
> um arquivo em `dados/` e não precisa configurar nada.

### Senha do painel

Não é preciso gerar nada. A senha do painel é um texto que fica no arquivo
`api/config.php`, na linha:

```php
const SENHA_ADMIN = 'Midia@ADMoema2026';
```

Ela já vem preenchida com esse valor. **Recomendado:** troque por uma senha sua.
No Gerenciador de Arquivos, clique com o botão direito em `api/config.php` → **Edit**,
altere o texto entre aspas e salve.

### Painel administrativo

Acesse `seusite.com.br/midia/admin.html` (ou `/admin.html` na raiz) e informe a senha.
O painel mostra:

- totais de respostas, visitantes únicos, visitas, formulários iniciados e cliques nas chamadas para ação;
- as frentes mais marcadas;
- a tabela com as três respostas de cada integrante e o botão **Baixar respostas (CSV)** (abre no Excel);
- o registro completo de ações.

## Requisitos na hospedagem

- PHP 7.4 ou superior (a HostGator já oferece 8.x) com a extensão `pdo_mysql` (padrão).
- Um banco MySQL criado no cPanel (Parte 1 acima).


## Logos e animação (skill `logo-motion`)

- **`img/logo-midia.svg`** — logo do Ministério de Mídia em vetor (redesenhada fielmente a partir
  da imagem enviada). É ela que aparece na abertura animada, no cabeçalho e no rodapé.
- **`img/logo-belem.png`** — brasão da Assembleia de Deus – Ministério do Belém. É a logo
  principal do site: cabeçalho, topo da página inicial, rodapé e **ícone do site** (favicon).
  Coloque o arquivo `Logo_Belem.png` nessa pasta com o nome `logo-belem.png` (PNG com fundo
  transparente, pelo menos 512 px de altura). Enquanto o arquivo não existir, o site esconde
  esses espaços automaticamente e nada quebra.

O site abre com uma **animação da logo da Mídia** desenhada traço a traço (arquivos
`intro-splash.css` + `intro-splash.js` e um trecho no início do `index.html`). Ela roda uma
vez por aba, tem botão "Pular", fecha com `Esc` e é desligada para quem prefere menos
movimento no sistema.

A mesma animação em vídeo Full HD está em `img/vinheta-midia.mp4`, para transmissão, telão e
redes. A skill que gera tudo isso fica em `.claude/skills/logo-motion` (arquivo
`logo-motion.skill` para salvar no perfil) e também produz WebM com fundo transparente para
OBS/vMix, GIF e a receita `dual` (duas logos em sequência, Mídia + AD).

## Compartilhamento nas redes sociais

Ao colar o link da página no WhatsApp, Facebook, Instagram, LinkedIn, Telegram ou X, aparece
um cartão com título, descrição e a imagem `img/og-image.jpg` (1200×630). As meta tags
(Open Graph e Twitter Card) ficam no início do `index.php` e o endereço é detectado
automaticamente; se precisar fixar, preencha `URL_SITE` em `api/config.php`.

Há também `img/og-quadrada.jpg` (1080×1080) para postar no feed do Instagram ou no status
do WhatsApp.

Se a imagem antiga continuar aparecendo depois de uma troca, o cache das redes precisa ser
limpo: Facebook/Instagram em <https://developers.facebook.com/tools/debug/>, LinkedIn em
<https://www.linkedin.com/post-inspector/>; no WhatsApp basta esperar ou enviar o link com
um `?v=2` no final.

## Ponto de partida do projeto (pedido do Pr. Elias)

Na reunião do ministério, a pedido do **Pr. Elias**, ficou definido que o Ministério de
Multimídia começa pela **edição de vídeo das transmissões** já capturadas, gerando arquivos
de vídeo de: pregação, louvor ministerial, louvor individual e louvor de qualquer ministério
da ADMoema. A página destaca isso logo após a abertura e o formulário pergunta a cada
integrante se já edita vídeo, quer aprender, tem um projeto específico ou prefere outra
frente (coluna `edicao_video` no banco; o painel e o CSV mostram o resultado).

## Conteúdo da página

1. **Início** — título, Romanos 12:5 e os pilares Servir · Comunicar · Capacitar · Evangelizar
2. **Nosso propósito** — para que a Palavra seja ouvida, compreendida, registrada, preservada e levada a quem está longe; a quem servimos
3. **Por que o trabalho de mídia importa**
4. **Nove frentes de trabalho** — frentes, não cargos; titular, apoio e aprendiz
5. **Tecnologia a serviço da igreja** — o que já construímos, onde podemos chegar, Central Digital ADMoema + ADMoema Criativo
6. **Como vamos nos organizar** — 0–30, 30–60 e 60–90 dias; cultura de ministério
7. **Nossa visão + formulário** com as três perguntas:
   1. O que você já sabe fazer e poderia compartilhar conosco?
   2. O que você gostaria de aprender dentro da mídia?
   3. Qual projeto gostaria de ajudar a construir?

## Banco de dados

Padrão: **MySQL** do servidor (phpMyAdmin). As tabelas são criadas automaticamente pelo
`api/db.php` na primeira visita (ou ao abrir `api/instalar.php`); o arquivo
`dados/schema-mysql.sql` cria as mesmas tabelas pelo phpMyAdmin, se preferir.
Alternativa sem configuração: `BANCO_TIPO = 'sqlite'` grava tudo em `dados/admoema-midia.sqlite`
(`dados/schema-sqlite.sql` para consulta).

**Tabela `respostas`** — `id`, `nome`, `contato` (opcional), `frentes` (JSON com as
frentes marcadas), `sabe_fazer` (pergunta 1), `quer_aprender` (pergunta 2),
`projeto_ajudar` (pergunta 3), `edicao_video` (`ja_edito`, `quero_aprender`,
`tenho_projeto`, `ainda_nao`), `criado_em`.

**Tabela `acoes`** (registro de ações) — `id`, `tipo` (`pagina_visitada`, `secao_vista`,
`cta_clicado`, `formulario_iniciado`, `formulario_enviado`, `formulario_erro`,
`admin_acesso`), `detalhe` (JSON), `resposta_id`, `sessao` (identificador anônimo do
navegador), `ip`, `user_agent`, `criado_em`.

As datas usam o fuso `America/Sao_Paulo` (ajustável em `api/config.php`).

## API

| Rota | Descrição |
| --- | --- |
| `GET api/frentes.php` | lista das nove frentes |
| `POST api/acoes.php` | registra uma ação do navegador |
| `POST api/respostas.php` | envia o formulário |
| `GET api/admin.php?recurso=respostas` | lista respostas (cabeçalho `X-Senha-Admin`) |
| `GET api/admin.php?recurso=acoes` | lista ações (cabeçalho `X-Senha-Admin`) |
| `GET api/admin.php?recurso=resumo` | totais e frentes mais marcadas |
| `GET api/admin.php?recurso=csv&senha=...` | download das respostas em CSV |

## Estrutura

```
index.php         landing page (com a abertura animada e as meta tags de compartilhamento)
intro-splash.*    animação de abertura da logo (gerada pela skill logo-motion)
img/              logo-midia.svg, logo-belem.png (coloque aqui) e vinheta-midia.mp4
.claude/skills/   skill logo-motion (animação de logos e do site)
styles.css        estilos
app.js            interações, formulário e registro de ações
admin.html        painel administrativo
api/config.php    senha do painel e banco (único arquivo a ajustar)
api/db.php        conexão e criação das tabelas
api/instalar.php  cria o banco e mostra as checagens (abra no navegador)
api/*.php         rotas da API
dados/            banco SQLite gerado no servidor (protegido por .htaccess) + schema-*.sql de consulta
.htaccess         configurações do Apache
```

## Testar no computador (opcional)

Com PHP instalado: `php -S localhost:8000` na pasta do projeto e abra
<http://localhost:8000>.
