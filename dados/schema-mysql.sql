-- Ministério de Mídia ADMoema · estrutura do banco (MySQL / MariaDB)
-- Use apenas se escolher BANCO_TIPO = 'mysql' em api/config.php e preferir criar as
-- tabelas à mão no phpMyAdmin. Caso contrário, o site cria tudo sozinho.

CREATE TABLE IF NOT EXISTS respostas (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nome            VARCHAR(120) NOT NULL,
  contato         VARCHAR(160) NULL,
  frentes         TEXT NOT NULL,
  edicao_video    VARCHAR(40) NULL,
  sabe_fazer      TEXT NOT NULL,
  quer_aprender   TEXT NOT NULL,
  projeto_ajudar  TEXT NOT NULL,
  criado_em       DATETIME NOT NULL,
  INDEX idx_respostas_criado_em (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS acoes (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  tipo        VARCHAR(40) NOT NULL,
  detalhe     TEXT NULL,
  resposta_id INT UNSIGNED NULL,
  sessao      VARCHAR(64) NULL,
  ip          VARCHAR(64) NULL,
  user_agent  VARCHAR(512) NULL,
  criado_em   DATETIME NOT NULL,
  INDEX idx_acoes_tipo (tipo),
  INDEX idx_acoes_criado_em (criado_em),
  CONSTRAINT fk_acoes_resposta FOREIGN KEY (resposta_id) REFERENCES respostas(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
