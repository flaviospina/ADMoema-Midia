-- Banco de dados do Ministério de Mídia ADMoema
-- SQLite. Executado automaticamente ao iniciar o servidor (idempotente).

PRAGMA journal_mode = WAL;
PRAGMA foreign_keys = ON;

-- Respostas ao formulário "Três perguntas para cada integrante"
CREATE TABLE IF NOT EXISTS respostas (
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  nome            TEXT    NOT NULL,
  contato         TEXT,                          -- e-mail ou WhatsApp (opcional)
  frentes         TEXT    NOT NULL DEFAULT '[]', -- JSON: frentes de interesse marcadas
  sabe_fazer      TEXT    NOT NULL,              -- 1. O que você já sabe fazer e poderia compartilhar conosco?
  quer_aprender   TEXT    NOT NULL,              -- 2. O que você gostaria de aprender dentro da mídia?
  projeto_ajudar  TEXT    NOT NULL,              -- 3. Qual projeto gostaria de ajudar a construir?
  criado_em       TEXT    NOT NULL DEFAULT (datetime('now', 'localtime'))
);

-- Registro de ações (log de eventos): visitas, cliques, envios de formulário, acessos ao painel etc.
CREATE TABLE IF NOT EXISTS acoes (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  tipo        TEXT    NOT NULL,                  -- ex.: pagina_visitada, secao_vista, cta_clicado, formulario_enviado, formulario_erro, admin_acesso
  detalhe     TEXT,                              -- JSON livre com informações do evento
  resposta_id INTEGER REFERENCES respostas(id) ON DELETE SET NULL,
  sessao      TEXT,                              -- identificador anônimo da sessão do navegador
  ip          TEXT,
  user_agent  TEXT,
  criado_em   TEXT    NOT NULL DEFAULT (datetime('now', 'localtime'))
);

CREATE INDEX IF NOT EXISTS idx_acoes_tipo      ON acoes (tipo);
CREATE INDEX IF NOT EXISTS idx_acoes_criado_em ON acoes (criado_em);
CREATE INDEX IF NOT EXISTS idx_respostas_criado_em ON respostas (criado_em);
