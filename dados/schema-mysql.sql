-- =====================================================================
-- Ministério de Mídia ADMoema · criação das tabelas (MySQL / MariaDB)
-- Como usar no phpMyAdmin (cPanel → phpMyAdmin):
--   1. Clique no banco criado (ex.: seuusuario_admoema) na coluna da esquerda.
--   2. Aba "Importar" → escolha este arquivo → "Executar".
-- O site também cria estas tabelas sozinho na primeira visita; importar é opcional.
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '-03:00';

CREATE TABLE IF NOT EXISTS respostas (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nome            VARCHAR(120) NOT NULL,
  contato         VARCHAR(160) NULL COMMENT 'WhatsApp ou e-mail (opcional)',
  frentes         TEXT NOT NULL COMMENT 'JSON com as frentes de interesse marcadas',
  edicao_video    VARCHAR(40) NULL COMMENT 'ja_edito | quero_aprender | tenho_projeto | ainda_nao (ponto de partida, pedido do Pr. Elias)',
  sabe_fazer      TEXT NOT NULL COMMENT '1. O que você já sabe fazer e poderia compartilhar conosco?',
  quer_aprender   TEXT NOT NULL COMMENT '2. O que você gostaria de aprender dentro da mídia?',
  projeto_ajudar  TEXT NOT NULL COMMENT '3. Qual projeto gostaria de ajudar a construir?',
  criado_em       DATETIME NOT NULL,
  INDEX idx_respostas_criado_em (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Respostas ao formulário das três perguntas';

CREATE TABLE IF NOT EXISTS acoes (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  tipo        VARCHAR(40) NOT NULL COMMENT 'pagina_visitada, secao_vista, cta_clicado, formulario_iniciado, formulario_enviado, formulario_erro, admin_acesso',
  detalhe     TEXT NULL COMMENT 'JSON com informações do evento',
  resposta_id INT UNSIGNED NULL,
  sessao      VARCHAR(64) NULL COMMENT 'identificador anônimo do navegador',
  ip          VARCHAR(64) NULL,
  user_agent  VARCHAR(512) NULL,
  criado_em   DATETIME NOT NULL,
  INDEX idx_acoes_tipo (tipo),
  INDEX idx_acoes_criado_em (criado_em),
  CONSTRAINT fk_acoes_resposta FOREIGN KEY (resposta_id) REFERENCES respostas(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Registro de ações dos visitantes e do painel';
