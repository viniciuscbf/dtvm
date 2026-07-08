<?php
// ============================================================
// DOMÍNIO — infraestrutura compartilhada de realismo e integridade:
//  • calendário de dias úteis (feriados B3)   • transações
//  • idempotência (nonce)                       • provisão de despesas
//  • tabelas auto-criáveis: catálogo de ativos, solicitações de
//    cadastro, tickets de suporte, posição do custodiante.
// Carregado ao final de helpers.php (portanto após calcular_cota/cota_em).
// ============================================================

// ---------------- Calendário de dias úteis ----------------
// Feriados nacionais + B3 (2025-2027). Ajuste/expanda conforme necessário.
const FERIADOS = [
    '2025-01-01','2025-03-03','2025-03-04','2025-04-18','2025-04-21','2025-05-01','2025-06-19','2025-09-07','2025-10-12','2025-11-02','2025-11-15','2025-11-20','2025-12-25',
    '2026-01-01','2026-02-16','2026-02-17','2026-04-03','2026-04-21','2026-05-01','2026-06-04','2026-09-07','2026-10-12','2026-11-02','2026-11-15','2026-11-20','2026-12-25',
    '2027-01-01','2027-02-08','2027-02-09','2027-03-26','2027-04-21','2027-05-01','2027-05-27','2027-09-07','2027-10-12','2027-11-02','2027-11-15','2027-12-25',
];

function eh_feriado(string $data): bool { return in_array(substr($data, 0, 10), FERIADOS, true); }

function eh_dia_util(string $data): bool {
    $n = (int)(new DateTime($data))->format('N');   // 6=sáb, 7=dom
    return $n < 6 && !eh_feriado($data);
}

/** Próximo dia útil após $data (pula fim de semana E feriados). */
function proximo_dia_util(string $data): string {
    $d = new DateTime($data);
    do { $d->modify('+1 day'); } while (!eh_dia_util($d->format('Y-m-d')));
    return $d->format('Y-m-d');
}

/** Soma N dias úteis a uma data (para prazos regulatórios). */
function soma_dias_uteis(string $data, int $n): string {
    $d = $data;
    for ($i = 0; $i < $n; $i++) $d = proximo_dia_util($d);
    return $d;
}

// ---------------- Transação atômica ----------------
/** Executa $fn dentro de uma transação (aninhável). Reverte tudo se lançar. */
function com_transacao(PDO $pdo, callable $fn) {
    $externa = $pdo->inTransaction();
    if (!$externa) $pdo->beginTransaction();
    try {
        $r = $fn();
        if (!$externa) $pdo->commit();
        return $r;
    } catch (Throwable $e) {
        if (!$externa && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

// ---------------- Idempotência (anti-duplo-clique) ----------------
/** Emite um nonce de uso único e o registra na sessão. */
function nonce_campo(): string {
    $n = bin2hex(random_bytes(16));
    $_SESSION['nonces'][$n] = time();
    // limpa nonces antigos (> 2h)
    foreach (($_SESSION['nonces'] ?? []) as $k => $t) if (time() - $t > 7200) unset($_SESSION['nonces'][$k]);
    return '<input type="hidden" name="nonce" value="' . $n . '">';
}
/** Consome o nonce do POST; retorna false se ausente/já usado (duplo submit). */
function nonce_valido(): bool {
    $n = $_POST['nonce'] ?? '';
    if (is_string($n) && $n !== '' && isset($_SESSION['nonces'][$n])) { unset($_SESSION['nonces'][$n]); return true; }
    return false;
}

// ---------------- Provisão diária de despesas (accrual) ----------------
/**
 * Provisiona pro-rata dia útil a taxa de adm + gestão + custódia do fundo,
 * acumulando em fundos.provisao_despesas (reduz o PL/cota). Idempotente por dia
 * via log_processamento (etapa 'Provisão').
 */
function provisionar_despesas_dia(PDO $pdo, array $fundo, string $data): float {
    $fid = (int)$fundo['id'];   // ensure_provisao deve ser chamado FORA de transação pelo caller (DDL = commit implícito)
    // já provisionou este dia?
    $st = $pdo->prepare("SELECT COUNT(*) FROM log_processamento WHERE fundo_id=? AND data_ref=? AND etapa='Provisão'");
    $st->execute([$fid, $data]);
    if ((int)$st->fetchColumn() > 0) return 0.0;

    $pl = (float)$fundo['pl_atual'];
    $taxaCustodia = 0.0002;   // 0,02% a.a. (estimada; a de adm/gestão vêm do fundo)
    $taxaAno = (float)$fundo['taxa_adm'] + (float)$fundo['taxa_gestao'] + $taxaCustodia;
    $despesaDia = round($pl * $taxaAno / 252.0, 2);
    if ($despesaDia <= 0) return 0.0;

    $pdo->prepare("UPDATE fundos SET provisao_despesas = COALESCE(provisao_despesas,0) + ? WHERE id=?")
        ->execute([$despesaDia, $fid]);
    $pdo->prepare("INSERT INTO log_processamento (fundo_id, data_ref, etapa, nivel, mensagem) VALUES (?,?,?,?,?)")
        ->execute([$fid, $data, 'Provisão', 'INFO',
                   'Provisão de despesas do dia: ' . number_format($despesaDia, 2, ',', '.') . ' (adm+gestão+custódia pro-rata)']);
    return $despesaDia;
}

// ---------------- Tabelas auto-criáveis ----------------

function ensure_provisao(PDO $pdo): void {
    // MariaDB (XAMPP) suporta ADD COLUMN IF NOT EXISTS
    try { $pdo->exec("ALTER TABLE fundos ADD COLUMN IF NOT EXISTS provisao_despesas DECIMAL(18,2) DEFAULT 0"); }
    catch (Throwable $e) { /* coluna já existe ou engine sem suporte — ignora */ }
}

/** Catálogo de ativos + fila de solicitações de cadastro. Semeia um catálogo inicial. */
function ensure_catalogo(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS ativos_catalogo (
        id INT AUTO_INCREMENT PRIMARY KEY,
        codigo VARCHAR(40) NOT NULL UNIQUE,
        nome VARCHAR(150) NOT NULL,
        tipo VARCHAR(40) NOT NULL,
        emissor VARCHAR(150),
        cnpj_emissor VARCHAR(20),
        indexador VARCHAR(30),
        taxa VARCHAR(40),
        vencimento DATE NULL,
        fonte_preco VARCHAR(20) DEFAULT 'ANBIMA',
        status VARCHAR(20) DEFAULT 'Ativo',
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_cat_tipo (tipo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS solicitacoes_cadastro_ativo (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fundo_id INT NULL,
        solicitante VARCHAR(120),
        codigo VARCHAR(40) NOT NULL,
        nome VARCHAR(150),
        tipo VARCHAR(40) NOT NULL,
        emissor VARCHAR(150),
        indexador VARCHAR(30),
        taxa VARCHAR(40),
        vencimento DATE NULL,
        detalhe VARCHAR(400),
        status VARCHAR(20) DEFAULT 'Solicitado',
        motivo VARCHAR(300),
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
        decidido_por VARCHAR(120), decidido_em DATETIME NULL,
        INDEX idx_sol_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if ((int)$pdo->query("SELECT COUNT(*) FROM ativos_catalogo")->fetchColumn() === 0) {
        $ativos = [
            // código, nome, tipo, emissor, indexador, taxa, vencimento, fonte
            ['LFT 2028','Tesouro Selic 2028','Título Público','Tesouro Nacional','Selic','Selic','2028-03-01','ANBIMA'],
            ['LTN 2027','Tesouro Prefixado 2027','Título Público','Tesouro Nacional','Prefixado','10,8% a.a.','2027-01-01','ANBIMA'],
            ['NTN-B 2035','Tesouro IPCA+ 2035','Título Público','Tesouro Nacional','IPCA','IPCA+6,1%','2035-05-15','ANBIMA'],
            ['NTN-B 2045','Tesouro IPCA+ 2045','Título Público','Tesouro Nacional','IPCA','IPCA+6,2%','2045-05-15','ANBIMA'],
            ['CDB BTG 26','CDB BTG Pactual 2026','CDB','Banco BTG Pactual','CDI','108% CDI','2026-11-20','Comitê'],
            ['CDB ABC 27','CDB Banco ABC 2027','CDB','Banco ABC Brasil','CDI','110% CDI','2027-06-15','Comitê'],
            ['CDB DAYCOVAL 28','CDB Daycoval 2028','CDB','Banco Daycoval','CDI','112% CDI','2028-03-10','Comitê'],
            ['CDB SIM 26','CDB Banco Parceiro 2026','CDB','Banco Parceiro','CDI','109% CDI','2026-12-31','Comitê'],
            ['DEB VALE29','Debênture Vale 2029','Debênture','Vale S.A.','IPCA','IPCA+5,5%','2029-08-15','ANBIMA'],
            ['DEB ENGIE30','Debênture Engie 2030','Debênture','Engie Brasil','CDI','CDI+1,7%','2030-04-01','ANBIMA'],
            ['DEB SABESP31','Debênture Sabesp 2031','Debênture','Sabesp','IPCA','IPCA+5,9%','2031-09-01','ANBIMA'],
            ['CRI URBE28','CRI Urbe Capital 2028','CRI/CRA','Urbe Securitizadora','IPCA','IPCA+7,0%','2028-12-15','ANBIMA'],
            ['CRA AGRO25','CRA Agronegócio 2025','CRI/CRA','Eco Securitizadora','CDI','CDI+2,1%','2025-10-20','ANBIMA'],
            ['PETR4','Petrobras PN','Ação','Petróleo Brasileiro S.A.','—','—',null,'B3'],
            ['VALE3','Vale ON','Ação','Vale S.A.','—','—',null,'B3'],
            ['ITUB4','Itaú Unibanco PN','Ação','Itaú Unibanco','—','—',null,'B3'],
            ['BBAS3','Banco do Brasil ON','Ação','Banco do Brasil','—','—',null,'B3'],
            ['BBDC4','Bradesco PN','Ação','Banco Bradesco','—','—',null,'B3'],
            ['NORD3','Nordeste Participações ON','Ação','Nordeste Part.','—','—',null,'B3'],
            ['WEGE3','WEG ON','Ação','WEG S.A.','—','—',null,'B3'],
            ['BOVA11','iShares Ibovespa ETF','ETF','BlackRock','—','—',null,'B3'],
            ['IMAB11','iShares IMA-B ETF','ETF','BlackRock','—','—',null,'B3'],
            ['FIC RF SIM','Cota FIC RF Master (sim)','Cota de Fundo','Administradora','CDI','—',null,'ANBIMA'],
            ['MASTER MM SIM','Cota Fundo Master MM (sim)','Cota de Fundo','Administradora','—','—',null,'ANBIMA'],
        ];
        $ins = $pdo->prepare("INSERT INTO ativos_catalogo (codigo,nome,tipo,emissor,indexador,taxa,vencimento,fonte_preco)
                              VALUES (?,?,?,?,?,?,?,?)");
        foreach ($ativos as $a) $ins->execute($a);
    }
}

/** Sistema de tickets de suporte. */
function ensure_tickets(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS tickets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fundo_id INT NULL,
        aberto_por VARCHAR(120),
        perfil_abertura VARCHAR(20),
        tema VARCHAR(60) NOT NULL,
        assunto VARCHAR(200) NOT NULL,
        prioridade VARCHAR(10) DEFAULT 'Média',
        status VARCHAR(20) DEFAULT 'Aberto',
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
        atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_tk_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS ticket_mensagens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ticket_id INT NOT NULL,
        autor VARCHAR(120),
        perfil VARCHAR(20),
        mensagem TEXT NOT NULL,
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_tkm (ticket_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // Canal do chamado: 'gestor_admin' (gestor↔administradora) ou 'cotista_gestor' (cotista↔gestor).
    try { $pdo->exec("ALTER TABLE tickets ADD COLUMN IF NOT EXISTS canal VARCHAR(20) DEFAULT 'gestor_admin'"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE tickets ADD COLUMN IF NOT EXISTS token_cotista VARCHAR(40) NULL"); } catch (Throwable $e) {}
}

/** Temas de chamado típicos da operação de fundos. */
const TEMAS_TICKET = [
    'Cadastro de ativo', 'Boletagem / liquidação', 'Cota / precificação',
    'Conciliação / divergência', 'Aplicação / resgate de cotista', 'Tributação (come-cotas/IR)',
    'Enquadramento', 'Documento / regulatório', 'Assembleia', 'Acesso / senha',
    'Abertura / alteração de fundo', 'Outros',
];

/** Posição custodiada — fonte INDEPENDENTE da carteira da administradora (para conciliação real). */
function ensure_posicao_custodiante(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS posicao_custodiante (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fundo_id INT NOT NULL,
        data_ref DATE NOT NULL,
        codigo VARCHAR(40) NOT NULL,
        tipo VARCHAR(40),
        quantidade DECIMAL(18,6) NOT NULL,
        central VARCHAR(20),
        INDEX idx_pc (fundo_id, data_ref)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/** Central de guarda por tipo de ativo. */
function central_do_ativo(string $tipo): string {
    if ($tipo === 'Título Público') return 'SELIC';
    if (in_array($tipo, ['Debênture', 'CDB', 'CRI/CRA'], true)) return 'B3 Balcão';
    return 'B3 Depositária';
}

// ---------------- Versionamento por data (caixa e cotas) ----------------
/**
 * Saldo de caixa do fundo NA DATA (não o escalar de hoje): caixa atual menos os
 * movimentos posteriores à data. Corrige o cálculo retroativo da cota.
 */
function caixa_na_data(PDO $pdo, int $fid, string $data): float {
    $st = $pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM movimentacoes WHERE fundo_id = ? AND data_ref > ?");
    $st->execute([$fid, $data]);
    $posteriores = (float)$st->fetchColumn();
    $c = $pdo->prepare("SELECT caixa_atual FROM fundos WHERE id = ?");
    $c->execute([$fid]);
    return (float)$c->fetchColumn() - $posteriores;
}

/**
 * Total de cotas emitidas NA DATA. No dia corrente é o passivo vivo (SUM cotistas);
 * em data passada, deriva do snapshot publicado (pl / valor_cota daquela data),
 * refletindo as cotas que existiam antes de come-cotas/resgates posteriores.
 */
function total_cotas_na_data(PDO $pdo, int $fid, string $data): float {
    $ultima = ultima_data_cota($pdo, $fid);
    if (!$ultima || $data >= $ultima) return total_cotas($pdo, $fid);
    $st = $pdo->prepare("SELECT valor_cota, pl FROM cotas_historico WHERE fundo_id = ? AND data_ref = ? ORDER BY id DESC LIMIT 1");
    $st->execute([$fid, $data]);
    $h = $st->fetch();
    if ($h && (float)$h['valor_cota'] > 0) return (float)$h['pl'] / (float)$h['valor_cota'];
    return total_cotas($pdo, $fid);
}

/**
 * Credita/baixa um evento corporativo CORRETAMENTE por tipo (em transação):
 *  • Amortização → devolve principal ao caixa E baixa o PU do ativo (não é receita);
 *  • Bonificação/Desdobramento → ajusta QUANTIDADE (sem caixa);
 *  • Dividendo/JCP/Cupom → rendimento creditado no caixa.
 */
function creditar_evento_corporativo(PDO $pdo, array $ev): void {
    com_transacao($pdo, function () use ($pdo, $ev) {
        $fid = (int)$ev['fundo_id'];
        $tipo = $ev['tipo'];
        $valor = (float)($ev['valor_total'] ?? 0);
        $pdo->prepare("UPDATE eventos_corporativos SET status='Liquidado', processado_em=NOW() WHERE id=?")->execute([$ev['id']]);
        $dataSnap = ultima_data_carteira($pdo, $fid);

        if ($tipo === 'Amortização') {
            $pdo->prepare("INSERT INTO movimentacoes (fundo_id,data_ref,tipo,descricao,valor) VALUES (?,?,?,?,?)")
                ->execute([$fid, date('Y-m-d'), 'Amortização', "Amortização de {$ev['ativo_codigo']} — devolução de principal", $valor]);
            $pdo->prepare("UPDATE fundos SET caixa_atual = caixa_atual + ? WHERE id=?")->execute([$valor, $fid]);
            if ($dataSnap) {   // baixa o principal reduzindo o PU do ativo (valor de mercado cai junto)
                $st = $pdo->prepare("SELECT * FROM ativos_carteira WHERE fundo_id=? AND codigo=? AND data_ref=?");
                $st->execute([$fid, $ev['ativo_codigo'], $dataSnap]);
                if (($pos = $st->fetch()) && (float)$pos['quantidade'] > 0) {
                    $reducaoPU = $valor / (float)$pos['quantidade'];
                    $novoMam = max(0.0, (float)$pos['preco_mam'] - $reducaoPU);
                    $pdo->prepare("UPDATE ativos_carteira SET preco_mam=?, preco_referencia=? WHERE id=?")->execute([$novoMam, $novoMam, $pos['id']]);
                }
            }
        } elseif (in_array($tipo, ['Bonificação', 'Desdobramento'], true)) {
            $fator = (float)($ev['valor_por_unidade'] ?? 0);   // fração adicional (ex.: 0,10 = +10%)
            if ($dataSnap && $fator > 0) {
                $st = $pdo->prepare("SELECT * FROM ativos_carteira WHERE fundo_id=? AND codigo=? AND data_ref=?");
                $st->execute([$fid, $ev['ativo_codigo'], $dataSnap]);
                if ($pos = $st->fetch()) {
                    $nova = (float)$pos['quantidade'] * (1 + $fator);
                    $novoPm = (float)$pos['preco_medio'] / (1 + $fator);
                    $novoMam = (float)$pos['preco_mam'] / (1 + $fator);
                    $pdo->prepare("UPDATE ativos_carteira SET quantidade=?, preco_medio=?, preco_mam=?, preco_referencia=? WHERE id=?")
                        ->execute([$nova, $novoPm, $novoMam, $novoMam, $pos['id']]);
                }
            }
        } else {   // Dividendo / JCP / Cupom → rendimento
            $pdo->prepare("INSERT INTO movimentacoes (fundo_id,data_ref,tipo,descricao,valor) VALUES (?,?,?,?,?)")
                ->execute([$fid, date('Y-m-d'), 'Provento', "{$tipo} de {$ev['ativo_codigo']} creditado", $valor]);
            $pdo->prepare("UPDATE fundos SET caixa_atual = caixa_atual + ? WHERE id=?")->execute([$valor, $fid]);
        }
    });
}

/**
 * Enquadramento PRÉ-TRADE (dever do gestor — Res. CVM 175, art. 89): projeta o efeito
 * da boleta na carteira e verifica os limites do fundo ANTES do envio. Retorna [ok, violacoes].
 */
function checar_pre_trade(PDO $pdo, array $fundo, string $tipo, string $operacao, float $valor): array {
    $fid = (int)$fundo['id'];
    $pl = (float)$fundo['pl_atual'];
    if ($pl <= 0) return ['ok' => true, 'violacoes' => []];
    $ativos = carteira($pdo, $fid);
    $sinal = $operacao === 'Compra' ? 1 : -1;
    $soma = function (array $classes) use ($ativos): float {
        $s = 0.0; foreach ($ativos as $a) if (in_array($a['tipo'], $classes, true)) $s += $a['valor_mercado']; return $s;
    };
    $st = $pdo->prepare('SELECT * FROM enquadramento_regras WHERE fundo_id = ?');
    $st->execute([$fid]);
    $violacoes = [];
    foreach ($st->fetchAll() as $r) {
        $lim = (float)$r['limite'];
        switch ($r['tipo_regra']) {
            case 'max_acoes':
                $proj = ($soma(['Ação']) + ($tipo === 'Ação' ? $sinal * $valor : 0)) / $pl * 100;
                if ($proj > $lim + 1e-6) $violacoes[] = "{$r['descricao']}: iria a " . number_format($proj, 1, ',', '.') . "% (máx {$lim}%)";
                break;
            case 'max_credito_privado':
                $proj = ($soma(CLASSES_CREDITO) + (in_array($tipo, CLASSES_CREDITO, true) ? $sinal * $valor : 0)) / $pl * 100;
                if ($proj > $lim + 1e-6) $violacoes[] = "{$r['descricao']}: iria a " . number_format($proj, 1, ',', '.') . "% (máx {$lim}%)";
                break;
            case 'min_rf':
                $proj = ($soma(CLASSES_RF) + (in_array($tipo, CLASSES_RF, true) ? $sinal * $valor : 0)) / $pl * 100;
                if ($proj < $lim - 1e-6) $violacoes[] = "{$r['descricao']}: cairia a " . number_format($proj, 1, ',', '.') . "% (mín {$lim}%)";
                break;
            case 'max_ativo_unico':
                if ($operacao === 'Compra' && $valor / $pl * 100 > $lim + 1e-6)
                    $violacoes[] = "{$r['descricao']}: a operação sozinha representa " . number_format($valor / $pl * 100, 1, ',', '.') . "% (máx {$lim}%)";
                break;
        }
    }
    return ['ok' => empty($violacoes), 'violacoes' => $violacoes];
}

// ---------------- MaM: feed de preços independente ----------------
/** Fonte de preço primária por tipo de ativo (cascata de precificação). */
function fonte_por_tipo(string $tipo): string {
    if (in_array($tipo, ['Título Público', 'Debênture', 'CRI/CRA'], true)) return 'ANBIMA';
    if (in_array($tipo, ['Ação', 'ETF', 'Cota de Fundo'], true)) return 'B3';
    return 'Comitê';   // CDB e ilíquidos: comitê de precificação
}

/** Feed de preços de mercado — fonte INDEPENDENTE do preço marcado pela controladoria. */
function ensure_precos(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS precos_mercado (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        codigo VARCHAR(40) NOT NULL,
        data_ref DATE NOT NULL,
        preco DECIMAL(18,6) NOT NULL,
        fonte VARCHAR(20) DEFAULT 'ANBIMA',
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_preco (codigo, data_ref),
        INDEX idx_pm (data_ref)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

// ---------------- Passivo: KYC / suitability / PLD do cotista ----------------
function ensure_kyc_cotista(PDO $pdo): void {
    foreach ([
        "ALTER TABLE cotistas ADD COLUMN IF NOT EXISTS suitability VARCHAR(20) DEFAULT NULL",     // Conservador/Moderado/Arrojado
        "ALTER TABLE cotistas ADD COLUMN IF NOT EXISTS kyc_status VARCHAR(20) DEFAULT 'Pendente'", // Pendente/Aprovado/Reprovado
        "ALTER TABLE cotistas ADD COLUMN IF NOT EXISTS pld_status VARCHAR(20) DEFAULT 'Pendente'", // Pendente/OK/Alerta
        "ALTER TABLE cotistas ADD COLUMN IF NOT EXISTS fatca_crs VARCHAR(30) DEFAULT NULL",
        "ALTER TABLE cotistas ADD COLUMN IF NOT EXISTS termo_aceite DATETIME NULL",
    ] as $sql) { try { $pdo->exec($sql); } catch (Throwable $e) { /* coluna já existe */ } }
}

// ---------------- Classes / subclasses (Res. CVM 175) ----------------
function ensure_subclasses(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS subclasses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fundo_id INT NOT NULL,
        nome VARCHAR(120) NOT NULL,
        publico_alvo VARCHAR(60) DEFAULT 'Investidores em geral',
        aplicacao_minima DECIMAL(18,2) DEFAULT 0,
        taxa_adm DECIMAL(8,6) DEFAULT 0,
        taxa_performance DECIMAL(8,6) DEFAULT 0,
        prazo_cotizacao VARCHAR(20) DEFAULT 'D+0',
        prazo_liquidacao VARCHAR(20) DEFAULT 'D+1',
        condominio VARCHAR(20) DEFAULT 'Aberto',
        status VARCHAR(20) DEFAULT 'Ativa',
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_sub (fundo_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // Esteira formal de registro (Res. CVM 175): anexo ao regulamento, protocolo e vigência.
    foreach ([
        "ALTER TABLE subclasses ADD COLUMN IF NOT EXISTS etapa_registro VARCHAR(20) DEFAULT 'Vigente'", // Em registro/Protocolada/Vigente
        "ALTER TABLE subclasses ADD COLUMN IF NOT EXISTS regulamento_anexo VARCHAR(255) NULL",
        "ALTER TABLE subclasses ADD COLUMN IF NOT EXISTS data_vigencia DATE NULL",
        "ALTER TABLE subclasses ADD COLUMN IF NOT EXISTS protocolo_cvm VARCHAR(40) NULL",
    ] as $sql) { try { $pdo->exec($sql); } catch (Throwable $e) {} }
}

/** Tipos de fundo (FIF/FIC/FIP), master do FIC e fundo-alvo de cota-de-fundo. */
function ensure_fund_types(PDO $pdo): void {
    foreach ([
        "ALTER TABLE fundos ADD COLUMN IF NOT EXISTS tipo_fundo VARCHAR(10) DEFAULT 'FIF'",
        "ALTER TABLE fundos ADD COLUMN IF NOT EXISTS master_id INT DEFAULT NULL",
        "ALTER TABLE ativos_catalogo ADD COLUMN IF NOT EXISTS fundo_alvo_id INT DEFAULT NULL",
    ] as $sql) { try { $pdo->exec($sql); } catch (Throwable $e) { /* já existe */ } }
}

/** Garante todas as estruturas de domínio de uma vez. */
function ensure_dominio(PDO $pdo): void {
    ensure_provisao($pdo);
    ensure_catalogo($pdo);
    ensure_tickets($pdo);
    ensure_posicao_custodiante($pdo);
    ensure_precos($pdo);
    ensure_kyc_cotista($pdo);
    ensure_subclasses($pdo);
    ensure_fund_types($pdo);
    ensure_derivativos($pdo);
    ensure_fip($pdo);
    ensure_equipe($pdo);
    ensure_batch($pdo);
    ensure_regulamento($pdo);
}

require_once __DIR__ . '/marcacao.php';     // motor de marcação por indexador (usa fonte_por_tipo, definido acima)
require_once __DIR__ . '/derivativos.php';  // DI1/DAP com ajuste diário (usa eh_dia_util, com_transacao)
require_once __DIR__ . '/fip.php';          // Private Equity: LPs, chamadas, participações, laudo, waterfall
require_once __DIR__ . '/equipe.php';       // membros do fundo, permissões, convites, transferência, reset de senha
require_once __DIR__ . '/batch.php';        // processamento em lote (fechamento) resiliente por fundo
require_once __DIR__ . '/regulamento.php';  // gerador de regulamento (fundo/classe/subclasse) dirigido por schema
