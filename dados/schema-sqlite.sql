-- Ministério de Mídia ADMoema · estrutura do banco (SQLite)
-- Este arquivo é só para consulta: o banco dados/admoema-midia.sqlite é criado
-- automaticamente pelo site (api/db.php) na primeira visita ou ao abrir api/instalar.php.

CREATE TABLE IF NOT EXISTS respostas (
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  nome            TEXT    NOT NULL,
  contato         TEXT,                          -- WhatsApp ou e-mail (opcional)
  frentes         TEXT    NOT NULL DEFAULT '[]', -- JSON com as frentes marcadas
  edicao_video    TEXT,                          -- ja_edito | quero_aprender | tenho_projeto | ainda_nao (pedido do Pr. Elias)
  sabe_fazer      TEXT    NOT NULL,              -- 1. O que você já sabe fazer e poderia compartilhar conosco?
  quer_aprender   TEXT    NOT NULL,              -- 2. O que você gostaria de aprender dentro da mídia?
  projeto_ajudar  TEXT    NOT NULL,              -- 3. Qual projeto gostaria de ajudar a construir?
  criado_em       TEXT    NOT NULL
);

CREATE TABLE IF NOT EXISTS acoes (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  tipo        TEXT    NOT NULL,  -- pagina_visitada, secao_vista, cta_clicado, formulario_iniciado, formulario_enviado, formulario_erro, admin_acesso
  detalhe     TEXT,              -- JSON com informações do evento
  resposta_id INTEGER REFERENCES respostas(id) ON DELETE SET NULL,
  sessao      TEXT,              -- identificador anônimo do navegador
  ip          TEXT,
  user_agent  TEXT,
  criado_em   TEXT    NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_acoes_tipo          ON acoes (tipo);
CREATE INDEX IF NOT EXISTS idx_acoes_criado_em     ON acoes (criado_em);
CREATE INDEX IF NOT EXISTS idx_respostas_criado_em ON respostas (criado_em);
