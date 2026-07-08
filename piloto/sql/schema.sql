-- =============================================================
-- PILOTO ADMINISTRADORA — SCHEMA (MySQL/MariaDB, XAMPP)
-- Banco sugerido: administradora (utf8mb4_unicode_ci)
-- =============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS fip_distribuicoes, fip_avaliacoes, fip_participacoes, fip_chamada_lp, fip_chamadas, fip_lps,
  derivativos_ajustes, derivativos, subclasses, precos_mercado, posicao_custodiante, ticket_mensagens, tickets, solicitacoes_cadastro_ativo, ativos_catalogo, sim_estado,
  eventos_fiscais, auditoria, onboarding_etapas, comentarios, documentos, chamados, comunicados,
  enquadramento_eventos, enquadramento_regras, repasses, partes_relacionadas, alertas_fraude,
  conciliacao, log_processamento, processamento, previsao_caixa, movimentacoes, mov_cotistas,
  documentos_abertura, lancamentos, fechamentos, tokens_acesso,
  envios_regulatorios, oficios_cvm, assembleias, eventos_corporativos, liquidacoes,
  mensagens_spb, contas_centrais, boletas, usuario_fundos, fundo_membros, senha_resets, processamento_batch, classes,
  cotistas, cdi_historico, cotas_historico, ativos_carteira, usuarios, fundos;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------- Núcleo ----------
CREATE TABLE fundos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(150) NOT NULL,
  cnpj VARCHAR(20),
  classe VARCHAR(50),                 -- 'Renda Fixa','Ações','Multimercado'
  publico_alvo VARCHAR(60),           -- 'Investidores em geral','Qualificados','Profissionais'
  condominio VARCHAR(20) DEFAULT 'Aberto',
  status VARCHAR(30) DEFAULT 'Ativo', -- 'Em abertura','Ativo','Encerrado'
  gestora VARCHAR(120),
  benchmark VARCHAR(30) DEFAULT 'CDI',
  tributacao VARCHAR(20) DEFAULT 'Longo Prazo',   -- 'Longo Prazo'/'Curto Prazo'/'Ações' (regra de come-cotas)
  provisao_despesas DECIMAL(18,2) DEFAULT 0,      -- accrual de taxas (adm+gestão+custódia) que reduz o PL/cota
  tipo_fundo VARCHAR(10) DEFAULT 'FIF',           -- FIF / FIC / FIP
  master_id INT DEFAULT NULL,                     -- FIC → fundo master
  taxa_adm DECIMAL(8,6) DEFAULT 0.0008,      -- a.a. (0,08%)
  taxa_gestao DECIMAL(8,6) DEFAULT 0.007,
  taxa_performance DECIMAL(8,6) DEFAULT 0,   -- sobre o que exceder o benchmark
  caixa_atual DECIMAL(18,2) DEFAULT 0,
  pl_atual DECIMAL(18,2) DEFAULT 0,
  cota_atual DECIMAL(18,8) DEFAULT 1,
  data_abertura DATE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  senha VARCHAR(255) NOT NULL,        -- hash bcrypt (password_hash / password_verify)
  perfil ENUM('admin','gestor','custodia') NOT NULL,  -- cotista NÃO tem usuário: entra por token; custodia = mesa do banco custodiante
  fundo_id INT NULL,
  gestora VARCHAR(150) NULL,
  telefone VARCHAR(30) NULL,
  kyc_status VARCHAR(20) DEFAULT 'Pendente',
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vínculo N:N usuário ↔ fundo: uma conta de gestor pode ter vários fundos (FIC/master, subclasses)
CREATE TABLE usuario_fundos (
  usuario_id INT NOT NULL,
  fundo_id INT NOT NULL,
  PRIMARY KEY (usuario_id, fundo_id),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  FOREIGN KEY (fundo_id) REFERENCES fundos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Boletas de operação do gestor (compra/venda) → aceite da custódia → liquidação DVP
CREATE TABLE boletas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fundo_id INT NOT NULL,
  data_operacao DATE NOT NULL,
  operacao ENUM('Compra','Venda') NOT NULL,
  ativo_codigo VARCHAR(30) NOT NULL,
  tipo_ativo VARCHAR(40) NOT NULL,          -- 'CDB','Debênture','Ação','Título Público','CRI/CRA','Cota de Fundo'
  quantidade DECIMAL(18,2) NOT NULL,
  preco DECIMAL(18,6) NOT NULL,             -- preço unitário negociado
  valor DECIMAL(18,2) NOT NULL,             -- financeiro (qtd × preço)
  contraparte VARCHAR(120),
  status ENUM('Enviada','Aceita','Rejeitada','Liquidada') DEFAULT 'Enviada',
  motivo VARCHAR(300) NULL,                 -- motivo de rejeição pela custódia
  liquidacao_id INT NULL,                   -- instrução de liquidação gerada no aceite
  criado_por VARCHAR(100),
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (fundo_id) REFERENCES fundos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tokens de acesso do cotista (gerados e revogados pelo gestor)
CREATE TABLE tokens_acesso (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fundo_id INT NOT NULL,
  token CHAR(36) NOT NULL UNIQUE,     -- UUID v4 (36 caracteres)
  nivel ENUM('realtime','delay_1m','delay_3m') NOT NULL DEFAULT 'delay_3m',
  descricao VARCHAR(120),             -- ex.: 'Família Silva', 'Investidor institucional X'
  status ENUM('Ativo','Revogado') DEFAULT 'Ativo',
  criado_por VARCHAR(100),
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  revogado_em DATETIME NULL,
  FOREIGN KEY (fundo_id) REFERENCES fundos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fechamento diário de cota: prévia da administradora → aprovação do gestor (D-1)
CREATE TABLE fechamentos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fundo_id INT NOT NULL,
  data_ref DATE NOT NULL,
  versao TINYINT NOT NULL DEFAULT 1,  -- reprocessamento gera nova versão
  valor_cota DECIMAL(18,8) NOT NULL,
  pl DECIMAL(18,2) NOT NULL,
  variacao_dia DECIMAL(10,6) NULL,
  status ENUM('Em processamento','Aguardando aprovação','Aprovada','Rejeitada','Republicada','Reaberta') NOT NULL,
  liberado_download TINYINT DEFAULT 0,  -- dados do dia só baixáveis após aprovação/liberação
  calculada_em DATETIME NULL,
  decidido_por VARCHAR(100) NULL,       -- gestor que aprovou/rejeitou
  decidido_em DATETIME NULL,
  motivo VARCHAR(400) NULL,             -- motivo da rejeição / observação
  UNIQUE KEY uk_fech (fundo_id, data_ref, versao),
  FOREIGN KEY (fundo_id) REFERENCES fundos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lançamentos e ajustes da controladoria (trilha de toda correção)
CREATE TABLE lancamentos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fundo_id INT NOT NULL,
  data_ref DATE NOT NULL,
  tipo ENUM('Ajuste de preço','Correção de quantidade','Movimentação de caixa','Provento','Evento corporativo','Taxa/Despesa') NOT NULL,
  ativo_codigo VARCHAR(30) NULL,
  descricao VARCHAR(300) NOT NULL,
  valor_antigo DECIMAL(18,6) NULL,
  valor_novo DECIMAL(18,6) NULL,
  valor_caixa DECIMAL(18,2) NULL,
  autor VARCHAR(100) NOT NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (fundo_id) REFERENCES fundos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Custódia (funções do custodiante: guarda, liquidação, eventos) ----------

-- Contas segregadas por fundo nas infraestruturas de mercado (o "mapa de guarda")
CREATE TABLE contas_centrais (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fundo_id INT NULL,                        -- NULL = conta própria do banco custodiante
  central ENUM('SELIC','B3 Depositária','B3 Balcão','STR/Reservas') NOT NULL,
  numero_conta VARCHAR(30) NOT NULL,
  titularidade VARCHAR(120),                -- 'Fundo X (conta individualizada)' / 'Banco (conta própria)'
  status VARCHAR(20) DEFAULT 'Ativa',
  FOREIGN KEY (fundo_id) REFERENCES fundos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mensageria RSFN/SPB (SELIC, STR, B3) — a "caixa de entrada" técnica do custodiante
CREATE TABLE mensagens_spb (
  id INT AUTO_INCREMENT PRIMARY KEY,
  central ENUM('SELIC','STR','B3 Depositária','B3 Balcão') NOT NULL,
  codigo VARCHAR(20) NOT NULL,              -- ex.: 'SEL1052','STR0008','MOV0001' (estilo catálogo RSFN)
  fundo_id INT NULL,
  referencia VARCHAR(60),                   -- nº da operação/instrução relacionada
  descricao VARCHAR(300) NOT NULL,
  valor DECIMAL(18,2) NULL,
  status ENUM('Recebida','Processada','Erro') DEFAULT 'Recebida',
  recebida_em DATETIME NOT NULL,
  processada_em DATETIME NULL,
  processada_por VARCHAR(100) NULL,
  FOREIGN KEY (fundo_id) REFERENCES fundos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fila de liquidação física e financeira das operações (D+1/D+2)
CREATE TABLE liquidacoes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fundo_id INT NOT NULL,
  data_operacao DATE NOT NULL,
  ativo_codigo VARCHAR(30) NOT NULL,
  operacao ENUM('Compra','Venda') NOT NULL,
  quantidade DECIMAL(18,2) NOT NULL,
  valor DECIMAL(18,2) NOT NULL,             -- valor financeiro da operação
  data_liquidacao DATE NOT NULL,            -- D+1 / D+2 conforme o ativo
  contraparte VARCHAR(120),
  status ENUM('Pendente','Liquidada','Falha') DEFAULT 'Pendente',
  boleta_id INT NULL,                       -- se veio de boleta do gestor, liquidar atualiza a carteira
  confirmado_por VARCHAR(100) NULL,
  confirmado_em DATETIME NULL,
  FOREIGN KEY (fundo_id) REFERENCES fundos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Eventos corporativos dos ativos custodiados (proventos, amortizações, bonificações)
CREATE TABLE eventos_corporativos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fundo_id INT NOT NULL,
  ativo_codigo VARCHAR(30) NOT NULL,
  tipo ENUM('Dividendo','JCP','Cupom','Amortização','Bonificação','Desdobramento') NOT NULL,
  data_anuncio DATE NOT NULL,
  data_ex DATE NULL,
  data_pagamento DATE NOT NULL,
  valor_por_unidade DECIMAL(18,6) NULL,
  valor_total DECIMAL(18,2) NULL,
  status ENUM('Anunciado','Provisionado','Liquidado') DEFAULT 'Anunciado',
  processado_por VARCHAR(100) NULL,
  processado_em DATETIME NULL,
  FOREIGN KEY (fundo_id) REFERENCES fundos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Regulatório (obrigações do administrador fiduciário junto a CVM/ANBIMA) ----------

-- Envios periódicos: informe diário (1 d.u.), balancete/CDA/perfil mensal (10 d.u.), DFs anuais
CREATE TABLE envios_regulatorios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fundo_id INT NOT NULL,
  destino ENUM('CVM','ANBIMA') NOT NULL,
  tipo VARCHAR(60) NOT NULL,                -- 'Informe Diário','Balancete','CDA','Perfil Mensal','Demonstrações Contábeis','Estatísticas ANBIMA'
  competencia VARCHAR(10) NOT NULL,         -- 'AAAA-MM-DD' (diário) ou 'AAAA-MM' (mensal) ou 'AAAA' (anual)
  prazo DATE NULL,                          -- prazo regulamentar de envio
  status ENUM('Enviado','Pendente','Erro','Aguardando cota') DEFAULT 'Pendente',
  protocolo VARCHAR(40) NULL,
  enviado_em DATETIME NULL,
  mensagem VARCHAR(300) NULL,
  FOREIGN KEY (fundo_id) REFERENCES fundos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ofícios e notificações recebidas do regulador (com prazo de resposta)
CREATE TABLE oficios_cvm (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fundo_id INT NULL,                        -- NULL = ofício geral (circular)
  origem VARCHAR(40) DEFAULT 'CVM',         -- 'CVM','ANBIMA','Banco Central'
  numero VARCHAR(60) NOT NULL,
  assunto VARCHAR(200) NOT NULL,
  teor TEXT,
  recebido_em DATE NOT NULL,
  prazo_resposta DATE NULL,
  status ENUM('Recebido','Em resposta','Respondido','Ciente') DEFAULT 'Recebido',
  resposta TEXT NULL,
  respondido_por VARCHAR(100) NULL,
  respondido_em DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Assembleias de cotistas (alteração de regulamento, prestadores, taxas, DFs)
CREATE TABLE assembleias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fundo_id INT NOT NULL,
  tipo ENUM('AGO','AGE') DEFAULT 'AGE',
  pauta VARCHAR(400) NOT NULL,
  origem VARCHAR(60) DEFAULT 'Administradora',  -- 'Solicitação do gestor','Administradora','Cotistas (5%)'
  data_convocacao DATE NULL,
  data_realizacao DATE NULL,
  modo VARCHAR(30) DEFAULT 'Eletrônica',        -- CVM 175: assembleia pode ser 100% eletrônica
  status ENUM('Solicitada','Convocada','Realizada','Cancelada') DEFAULT 'Solicitada',
  quorum VARCHAR(60) NULL,
  resultado VARCHAR(400) NULL,
  criado_por VARCHAR(100),
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (fundo_id) REFERENCES fundos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Checklist documental da abertura de fundo (exigências reais CVM 175)
CREATE TABLE documentos_abertura (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fundo_id INT NOT NULL,
  categoria ENUM('Gestora','Responsável','Fundo') NOT NULL,
  nome VARCHAR(200) NOT NULL,
  obrigatorio TINYINT DEFAULT 1,
  status ENUM('Pendente','Recebido','Aprovado','Rejeitado') DEFAULT 'Pendente',
  arquivo VARCHAR(200) NULL,          -- nome do arquivo enviado (upload simulado)
  motivo VARCHAR(300) NULL,           -- motivo de rejeição
  atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (fundo_id) REFERENCES fundos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ativos_carteira (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fundo_id INT NOT NULL,
  codigo VARCHAR(30) NOT NULL,        -- 'PETR4','LTN 2028','DEB VALE29'
  tipo VARCHAR(40) NOT NULL,          -- 'Ação','Título Público','Debênture','CDB','Cota de Fundo','Futuro'
  quantidade DECIMAL(18,6) NOT NULL,
  preco_medio DECIMAL(18,6) NOT NULL, -- custo médio de aquisição
  preco_mam DECIMAL(18,6) NOT NULL,   -- preço marcado a mercado
  preco_referencia DECIMAL(18,6),     -- referência de mercado (regra R1 de fraude)
  fonte_preco VARCHAR(30) DEFAULT 'B3', -- 'B3','ANBIMA','Comitê'
  data_ref DATE,                      -- snapshot diário: a carteira existe por data (visão retroativa)
  INDEX idx_ativo_fundo_data (fundo_id, data_ref),
  FOREIGN KEY (fundo_id) REFERENCES fundos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cotas_historico (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fundo_id INT NOT NULL,
  data_ref DATE NOT NULL,
  valor_cota DECIMAL(18,8) NOT NULL,
  pl DECIMAL(18,2) NOT NULL,
  INDEX idx_fundo_data (fundo_id, data_ref),
  FOREIGN KEY (fundo_id) REFERENCES fundos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cdi_historico (
  id INT AUTO_INCREMENT PRIMARY KEY,
  data_ref DATE NOT NULL UNIQUE,
  fator_diario DECIMAL(12,10) NOT NULL  -- ex.: 1.0003968 (CDI ~10,5% a.a.)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cotistas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fundo_id INT NOT NULL,
  nome VARCHAR(150) NOT NULL,
  documento VARCHAR(20),
  tipo_pessoa VARCHAR(2) DEFAULT 'PF',
  cotas DECIMAL(18,6) NOT NULL,
  custo_total DECIMAL(18,2) DEFAULT NULL,   -- base de custo p/ apuração de ganho (NULL = PM 1,00)
  suitability VARCHAR(20) DEFAULT NULL,     -- Conservador/Moderado/Arrojado
  kyc_status VARCHAR(20) DEFAULT 'Pendente',
  pld_status VARCHAR(20) DEFAULT 'Pendente',
  fatca_crs VARCHAR(30) DEFAULT NULL,
  termo_aceite DATETIME NULL,
  data_entrada DATE,
  FOREIGN KEY (fundo_id) REFERENCES fundos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mov_cotistas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fundo_id INT NOT NULL,
  cotista_id INT NOT NULL,
  data_ref DATE NOT NULL,
  tipo VARCHAR(20) NOT NULL,          -- 'Aplicação','Resgate'
  valor DECIMAL(18,2) NOT NULL,
  cotas DECIMAL(18,6) NOT NULL,
  data_cotizacao DATE,
  data_liquidacao DATE,
  FOREIGN KEY (fundo_id) REFERENCES fundos(id),
  FOREIGN KEY (cotista_id) REFERENCES cotistas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Caixa ----------
CREATE TABLE movimentacoes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fundo_id INT NOT NULL,
  data_ref DATE NOT NULL,
  tipo VARCHAR(40) NOT NULL,          -- 'Aplicação','Resgate','Provento','Taxa','Liquidação Compra','Liquidação Venda'
  descricao VARCHAR(200),
  valor DECIMAL(18,2) NOT NULL,       -- + entrada / - saída
  FOREIGN KEY (fundo_id) REFERENCES fundos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE previsao_caixa (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fundo_id INT NOT NULL,
  data_prevista DATE NOT NULL,
  tipo VARCHAR(40) NOT NULL,          -- 'Provento a receber','Resgate agendado','Taxa a pagar','Vencimento de título'
  descricao VARCHAR(200),
  valor DECIMAL(18,2) NOT NULL,
  FOREIGN KEY (fundo_id) REFERENCES fundos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Operação ----------
CREATE TABLE processamento (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fundo_id INT NOT NULL,
  data_ref DATE NOT NULL,
  etapa VARCHAR(40) NOT NULL,         -- 'Posição','Preços','Caixa','Conciliação','Cota','ANBIMA'
  ordem TINYINT NOT NULL,
  status VARCHAR(20) NOT NULL,        -- 'OK','Rodando','Pendente','Erro'
  horario TIME NULL,
  mensagem VARCHAR(250),
  FOREIGN KEY (fundo_id) REFERENCES fundos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE log_processamento (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fundo_id INT NOT NULL,
  data_ref DATE NOT NULL,
  etapa VARCHAR(40),
  nivel VARCHAR(10) DEFAULT 'INFO',   -- 'INFO','WARN','ERRO'
  mensagem VARCHAR(400),
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (fundo_id) REFERENCES fundos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE conciliacao (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fundo_id INT NOT NULL,
  data_ref DATE NOT NULL,
  origem VARCHAR(60) NOT NULL,        -- 'Posição × Custodiante','Operações × Gestor','Caixa × Extrato'
  situacao VARCHAR(20) NOT NULL,      -- 'Conciliado','Divergente','Resolvido'
  classificacao VARCHAR(20),          -- 'Timing','Erro','Suspeita'
  detalhe VARCHAR(400),
  valor_diferenca DECIMAL(18,2) DEFAULT 0,
  resolucao VARCHAR(400),
  resolvido_por VARCHAR(100),
  resolvido_em DATETIME NULL,
  FOREIGN KEY (fundo_id) REFERENCES fundos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Risco / IA ----------
CREATE TABLE alertas_fraude (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fundo_id INT NOT NULL,
  data_ref DATE NOT NULL,
  regra VARCHAR(5) NOT NULL,          -- 'R1'..'R7'
  tipo VARCHAR(60) NOT NULL,          -- 'Preço fora da curva','Parte relacionada',...
  severidade VARCHAR(10) NOT NULL,    -- 'Alta','Média','Baixa'
  explicacao VARCHAR(500) NOT NULL,   -- linguagem natural: por que a IA sinalizou
  evidencia VARCHAR(400),             -- números/fatos
  status VARCHAR(30) DEFAULT 'Aberto',-- 'Aberto','Em revisão','Escalado','Falso positivo','Encerrado'
  tratado_por VARCHAR(100),
  tratado_em DATETIME NULL,
  justificativa VARCHAR(400),
  FOREIGN KEY (fundo_id) REFERENCES fundos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE partes_relacionadas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fundo_id INT NOT NULL,
  origem VARCHAR(100) NOT NULL,       -- nó de origem (ex.: 'Aurora Capital (gestora)')
  destino VARCHAR(100) NOT NULL,      -- nó de destino (ex.: 'XYZ Securitizadora')
  tipo_vinculo VARCHAR(80),           -- 'sócio','contraparte','emissor','mesmo endereço'
  suspeito TINYINT DEFAULT 0,
  FOREIGN KEY (fundo_id) REFERENCES fundos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE enquadramento_regras (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fundo_id INT NOT NULL,
  descricao VARCHAR(200) NOT NULL,
  tipo_regra VARCHAR(30) NOT NULL,    -- 'min_rf','max_ativo_unico','max_credito_privado','max_acoes','max_caixa'
  limite DECIMAL(8,4) NOT NULL,       -- em % (ex.: 80.0)
  FOREIGN KEY (fundo_id) REFERENCES fundos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE enquadramento_eventos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fundo_id INT NOT NULL,
  data_evento DATE NOT NULL,
  regra VARCHAR(200),
  causa VARCHAR(20),                  -- 'Passivo','Ativo'
  situacao VARCHAR(30),               -- 'Em aberto','Reenquadrado'
  prazo_reenquadramento DATE,
  FOREIGN KEY (fundo_id) REFERENCES fundos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Relacionamento / receita ----------
CREATE TABLE comunicados (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fundo_id INT NULL,                  -- NULL = geral, para todos os fundos
  titulo VARCHAR(200) NOT NULL,
  mensagem TEXT,
  data_pub DATE NOT NULL,
  lido TINYINT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE chamados (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fundo_id INT NOT NULL,
  usuario_id INT NOT NULL,
  assunto VARCHAR(200) NOT NULL,
  mensagem TEXT NOT NULL,
  status VARCHAR(20) DEFAULT 'Aberto',-- 'Aberto','Em análise','Respondido'
  resposta TEXT,
  respondido_por VARCHAR(100),
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  respondido_em DATETIME NULL,
  FOREIGN KEY (fundo_id) REFERENCES fundos(id),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE repasses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fundo_id INT NOT NULL,
  competencia VARCHAR(7) NOT NULL,    -- '2026-06'
  pl_medio DECIMAL(18,2),
  taxa_adm_valor DECIMAL(18,2) NOT NULL,   -- max(0,08% a.a./12 × PL médio ; R$ 100)
  piso_aplicado TINYINT DEFAULT 0,
  parte_banco DECIMAL(18,2) NOT NULL,      -- 25%
  parte_adm DECIMAL(18,2) NOT NULL,        -- 75%
  taxa_gestao_valor DECIMAL(18,2) DEFAULT 0,
  taxa_custodia_valor DECIMAL(18,2) DEFAULT 0,
  status VARCHAR(20) DEFAULT 'Apurado',    -- 'Apurado','Instruído','Pago'
  FOREIGN KEY (fundo_id) REFERENCES fundos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE documentos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fundo_id INT NOT NULL,
  nome VARCHAR(200) NOT NULL,
  tipo VARCHAR(50),                   -- 'Regulamento','Lâmina','Política','Informe'
  versao VARCHAR(10) DEFAULT 'v1',
  data_doc DATE,
  FOREIGN KEY (fundo_id) REFERENCES fundos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE comentarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fundo_id INT NOT NULL,
  autor VARCHAR(100) NOT NULL,
  texto VARCHAR(500) NOT NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (fundo_id) REFERENCES fundos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE onboarding_etapas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fundo_id INT NOT NULL,
  ordem TINYINT NOT NULL,
  etapa VARCHAR(80) NOT NULL,         -- 'Cadastro','Documentos','Análise KYC/PLD','Registro CVM','CNPJ Receita','Conta custodiante','Fundo apto'
  status VARCHAR(20) NOT NULL,        -- 'Concluída','Em andamento','Pendente'
  data_conclusao DATE NULL,
  responsavel VARCHAR(100),
  FOREIGN KEY (fundo_id) REFERENCES fundos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Segurança: trilha de auditoria (append-only) ----------
-- Registro de acessos e ações — evidência do art. 13, V da Res. CVM 32.
-- Sem FK em fundo_id de propósito: a trilha é independente e nunca deve
-- ser bloqueada/cascateada por operações nas demais tabelas.
CREATE TABLE auditoria (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  criado_em   DATETIME DEFAULT CURRENT_TIMESTAMP,
  ator        VARCHAR(120),
  perfil      VARCHAR(20),
  acao        VARCHAR(60) NOT NULL,
  entidade    VARCHAR(40),
  entidade_id VARCHAR(40),
  fundo_id    INT NULL,
  detalhe     VARCHAR(400),
  ip          VARCHAR(45),
  user_agent  VARCHAR(255),
  INDEX idx_aud_data (criado_em),
  INDEX idx_aud_acao (acao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Passivo: eventos fiscais (come-cotas, IR e IOF no resgate) ----------
CREATE TABLE eventos_fiscais (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  fundo_id    INT NOT NULL,
  cotista_id  INT NULL,
  tipo        ENUM('Come-cotas','IR Resgate','IOF Resgate') NOT NULL,
  competencia VARCHAR(10),
  data_ref    DATE NOT NULL,
  base_calculo    DECIMAL(18,2) DEFAULT 0,
  aliquota        DECIMAL(6,2)  DEFAULT 0,
  valor_tributo   DECIMAL(18,2) NOT NULL,
  cotas_reduzidas DECIMAL(18,6) DEFAULT 0,
  detalhe     VARCHAR(300),
  criado_em   DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ef_fundo (fundo_id, tipo),
  FOREIGN KEY (fundo_id) REFERENCES fundos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
