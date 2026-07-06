-- =============================================================
-- MIGRAÇÃO — Passivo do cotista e tributação (Lei 14.754/2023)
-- Aplicar em base existente:
--   mysql> USE administradora; SOURCE sql/passivo.sql;
-- (o schema.sql atualizado já inclui tudo isto para instalações novas)
-- Requer MariaDB (XAMPP) por causa do ADD COLUMN IF NOT EXISTS.
-- =============================================================
SET NAMES utf8mb4;

ALTER TABLE fundos   ADD COLUMN IF NOT EXISTS tributacao  VARCHAR(20) DEFAULT 'Longo Prazo';  -- 'Longo Prazo'/'Curto Prazo'/'Ações'
ALTER TABLE cotistas ADD COLUMN IF NOT EXISTS custo_total DECIMAL(18,2) DEFAULT NULL;         -- base de custo p/ apuração de ganho

-- Backfill: PM 1,00 para cotistas existentes; fundos de ações sem come-cotas
UPDATE cotistas SET custo_total = cotas WHERE custo_total IS NULL;
UPDATE fundos   SET tributacao  = 'Ações' WHERE classe = 'Ações';

-- Eventos fiscais gerados pelo motor (come-cotas, IR e IOF no resgate)
CREATE TABLE IF NOT EXISTS eventos_fiscais (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  fundo_id    INT NOT NULL,
  cotista_id  INT NULL,
  tipo        ENUM('Come-cotas','IR Resgate','IOF Resgate') NOT NULL,
  competencia VARCHAR(10),                         -- 'AAAA-MM'
  data_ref    DATE NOT NULL,
  base_calculo   DECIMAL(18,2) DEFAULT 0,          -- rendimento tributado
  aliquota       DECIMAL(6,2)  DEFAULT 0,
  valor_tributo  DECIMAL(18,2) NOT NULL,
  cotas_reduzidas DECIMAL(18,6) DEFAULT 0,         -- come-cotas: cotas abatidas
  detalhe     VARCHAR(300),
  criado_em   DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ef_fundo (fundo_id, tipo),
  FOREIGN KEY (fundo_id) REFERENCES fundos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
