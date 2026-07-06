-- =============================================================
-- MIGRAÇÃO — Endurecimento de segurança (trilha de auditoria)
-- Aplicar em uma base já existente:
--   mysql> USE administradora; SOURCE sql/hardening.sql;
-- (o schema.sql completo já inclui esta tabela para instalações novas)
-- =============================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS auditoria (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  criado_em   DATETIME DEFAULT CURRENT_TIMESTAMP,   -- carimbo do evento
  ator        VARCHAR(120),                         -- nome/e-mail do usuário ou 'anônimo'
  perfil      VARCHAR(20),                          -- admin/gestor/custodia
  acao        VARCHAR(60) NOT NULL,                 -- 'login_ok','liquidacao_dvp',...
  entidade    VARCHAR(40),                          -- 'boleta','liquidacao','evento','mensagem_spb','sessao'
  entidade_id VARCHAR(40),                          -- id do objeto afetado
  fundo_id    INT NULL,                             -- fundo relacionado (sem FK: trilha é append-only e independente)
  detalhe     VARCHAR(400),
  ip          VARCHAR(45),
  user_agent  VARCHAR(255),
  INDEX idx_aud_data (criado_em),
  INDEX idx_aud_acao (acao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
