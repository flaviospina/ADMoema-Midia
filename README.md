# Ministério de Mídia ADMoema

Landing page moderna com a proposta de estruturação e expansão do Ministério de Mídia da
Assembleia de Deus – Ministério do Belém (Setor 124 · Moema), com formulário das
**três perguntas para cada integrante** e banco de dados para registro das respostas e das ações
dos visitantes.

## Conteúdo da página

1. **Início** — título, versículo (Romanos 12:5) e os quatro pilares: Servir · Comunicar · Capacitar · Evangelizar
2. **Nosso propósito** — para que a Palavra seja ouvida, compreendida, registrada, preservada e levada a quem está longe; a quem servimos
3. **Por que o trabalho de mídia importa** — "ninguém é apenas um operador de equipamento"
4. **Nove frentes de trabalho** — frentes, não cargos; princípio titular / apoio / aprendiz
5. **Tecnologia a serviço da igreja** — o que já construímos, onde podemos chegar, Central Digital ADMoema + ADMoema Criativo
6. **Como vamos nos organizar** — plano de 0–30, 30–60 e 60–90 dias e cultura de ministério
7. **Nossa visão + formulário** — as três perguntas:
   1. O que você já sabe fazer e poderia compartilhar conosco?
   2. O que você gostaria de aprender dentro da mídia?
   3. Qual projeto gostaria de ajudar a construir?

## Como rodar

Requisitos: Node.js 18 ou superior.

```bash
npm install
cp .env.example .env      # ajuste PORT, DB_PATH e ADMIN_TOKEN
ADMIN_TOKEN=meu-token npm start
```

Abra <http://localhost:3000>. O banco SQLite é criado automaticamente em `data/admoema-midia.db`
na primeira execução (ou com `npm run db:init`).

| Comando | O que faz |
| --- | --- |
| `npm start` | inicia o servidor |
| `npm run dev` | inicia com recarga automática (`node --watch`) |
| `npm run db:init` | cria o banco e as tabelas sem subir o servidor |
| `npm run db:export` | exporta respostas e ações em CSV para `data/exports/` |

## Painel administrativo

Acesse `/admin` e informe o `ADMIN_TOKEN` definido no ambiente. O painel mostra:

- totais de respostas, visitantes únicos, visitas, formulários iniciados e cliques em chamadas para ação;
- frentes mais marcadas;
- tabela de respostas com as três perguntas (com download em CSV);
- registro completo de ações.

## Banco de dados

Arquivo: `db/schema.sql` (SQLite, executado de forma idempotente na inicialização).

### Tabela `respostas`

| Coluna | Descrição |
| --- | --- |
| `id` | identificador |
| `nome` | nome do integrante |
| `contato` | WhatsApp ou e-mail (opcional) |
| `frentes` | JSON com as frentes de interesse marcadas |
| `sabe_fazer` | pergunta 1 |
| `quer_aprender` | pergunta 2 |
| `projeto_ajudar` | pergunta 3 |
| `criado_em` | data e hora do envio |

### Tabela `acoes` (registro de ações)

| Coluna | Descrição |
| --- | --- |
| `id` | identificador |
| `tipo` | `pagina_visitada`, `secao_vista`, `cta_clicado`, `formulario_iniciado`, `formulario_enviado`, `formulario_erro`, `admin_acesso` |
| `detalhe` | JSON com informações do evento (seção, origem do clique, frentes etc.) |
| `resposta_id` | vínculo com a resposta, quando a ação for um envio de formulário |
| `sessao` | identificador anônimo do navegador |
| `ip`, `user_agent` | origem da requisição |
| `criado_em` | data e hora |

## API

| Método e rota | Descrição |
| --- | --- |
| `GET /api/health` | verificação de saúde |
| `GET /api/frentes` | lista das nove frentes |
| `POST /api/acoes` | registra uma ação do navegador (`{ tipo, detalhe, sessao }`) |
| `POST /api/respostas` | envia o formulário (`{ nome, contato, frentes[], sabeFazer, querAprender, projetoAjudar, sessao }`) |
| `GET /api/admin/respostas` | lista respostas (cabeçalho `x-admin-token`) |
| `GET /api/admin/acoes?tipo=` | lista ações (cabeçalho `x-admin-token`) |
| `GET /api/admin/resumo` | totais e frentes mais marcadas (cabeçalho `x-admin-token`) |

## Estrutura

```
server.js          servidor Express e rotas da API
db/schema.sql      definição das tabelas
db/database.js     acesso ao SQLite (better-sqlite3)
db/init.js         cria o banco
db/export.js       exporta CSV
public/index.html  landing page
public/styles.css  estilos
public/app.js      interações, formulário e registro de ações
public/admin.html  painel administrativo
data/              arquivo do banco (ignorado pelo git)
```
