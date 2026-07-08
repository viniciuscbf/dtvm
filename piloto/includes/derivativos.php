<?php
// ============================================================
// DERIVATIVOS — DI1 (futuro de DI) e DAP (futuro de cupom de IPCA).
// Mecânica central: NÃO há desembolso do principal — há MARGEM de garantia e
// AJUSTE DIÁRIO (marcação a mercado liquidada em caixa todo dia útil).
//   PU = 100.000 / (1 + taxa)^(du/252)   [B3]; ponto do DI1 = R$1,00.
// Simplificação declarada: o DAP usa a mesma mecânica de PU (ponto R$0,00025×índice
// do IPCA é aproximado); o essencial — ajuste diário em caixa — é fiel.
// Carregado ao final de dominio.php.
// ============================================================

function ensure_derivativos(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS derivativos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fundo_id INT NOT NULL,
        instrumento VARCHAR(10) NOT NULL,          -- 'DI1' / 'DAP'
        vencimento DATE NOT NULL,
        contratos INT NOT NULL,
        comprado_pu TINYINT DEFAULT 1,             -- 1=comprado em PU (aposta juros caem); 0=vendido em PU
        taxa_operacao DECIMAL(8,4) NOT NULL,       -- taxa a.a. da abertura
        taxa_atual DECIMAL(8,4) NOT NULL,
        pu_atual DECIMAL(18,6) NOT NULL,
        margem DECIMAL(18,2) DEFAULT 0,            -- garantia depositada no clearing
        status VARCHAR(12) DEFAULT 'Aberta',       -- Aberta / Encerrada
        data_ref DATE,
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_dv (fundo_id, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS derivativos_ajustes (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        deriv_id INT NOT NULL, fundo_id INT NOT NULL, data_ref DATE NOT NULL,
        pu_ant DECIMAL(18,6), pu_novo DECIMAL(18,6), ajuste DECIMAL(18,2),
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_dva (deriv_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // Generaliza a engine a futuros de PREÇO (DOL/IND) além dos de taxa/PU (DI1/DAP).
    ddl_portavel($pdo, "ALTER TABLE derivativos ADD COLUMN IF NOT EXISTS mecanica VARCHAR(10) DEFAULT 'PU'");
    ddl_portavel($pdo, "ALTER TABLE derivativos ADD COLUMN IF NOT EXISTS ponto DECIMAL(12,4) DEFAULT 1");
}

/** Perfil do derivativo pelo código: [mecanica 'PU'|'PRECO', ponto (R$/ponto), fator 'juros'|'fx'|'indice']. */
function deriv_perfil(string $codigo): array {
    $c = strtoupper($codigo);
    if (strncmp($c, 'DI1', 3) === 0) return ['PU', 1.0, 'juros'];
    if (strncmp($c, 'DAP', 3) === 0) return ['PU', 1.0, 'juros'];
    if (strncmp($c, 'DOL', 3) === 0) return ['PRECO', 10.0, 'fx'];      // futuro de dólar
    if (strncmp($c, 'IND', 3) === 0) return ['PRECO', 1.0, 'indice'];   // futuro de índice Bovespa
    return ['PRECO', 1.0, 'indice'];
}

/** Dias úteis entre $de (exclusive) e $ate (inclusive). */
function du_uteis(string $de, string $ate): int {
    if ($ate <= $de) return 0;
    $d = new DateTime($de); $fim = new DateTime($ate); $n = 0;
    while ($d < $fim) { $d->modify('+1 day'); if (eh_dia_util($d->format('Y-m-d'))) $n++; }
    return $n;
}

/** PU do futuro: 100.000 descontado pela taxa até o vencimento (base 252 d.u.). */
function pu_futuro(float $taxa, int $du): float {
    return round(100000.0 / pow(1 + $taxa, max(1, $du) / 252.0), 6);
}

/**
 * Ajuste diário de todas as posições abertas do fundo: recalcula o PU (taxa oscila),
 * credita/debita o ajuste no caixa e registra a trilha. Posições vencidas são encerradas.
 * @return float total dos ajustes do dia
 */
function ajuste_diario_derivativos(PDO $pdo, int $fid, string $data): float {
    $st = $pdo->prepare("SELECT * FROM derivativos WHERE fundo_id = ? AND status = 'Aberta'");
    $st->execute([$fid]);
    $tot = 0.0;
    foreach ($st->fetchAll() as $d) {
        if ($data >= $d['vencimento']) { $pdo->prepare("UPDATE derivativos SET status='Encerrada' WHERE id=?")->execute([$d['id']]); continue; }
        $du = du_uteis($data, $d['vencimento']);
        [$mecPerfil, $pontoPerfil, $fator] = deriv_perfil($d['instrumento']);
        if (($d['mecanica'] ?? 'PU') === 'PU') {
            // Futuro de TAXA (DI1/DAP): PU marcado pela curva do prazo + choque pequeno (estrutura a termo).
            $ehDap    = strncmp($d['instrumento'], 'DAP', 3) === 0;
            $alvo     = $ehDap ? curva_real_aa($data, $du) : curva_pre_aa($data, $du);
            $taxaNova = max(0.001, $alvo + choque_ativo($d['instrumento'], $data) * 0.5);
            $puAnt    = (float) $d['pu_atual'] > 0 ? (float) $d['pu_atual'] : pu_futuro((float) $d['taxa_operacao'], $du + 1);
            $puNovo   = pu_futuro($taxaNova, $du);
            $pdo->prepare("UPDATE derivativos SET taxa_atual=?, pu_atual=?, data_ref=? WHERE id=?")->execute([$taxaNova, $puNovo, $data, $d['id']]);
        } else {
            // Futuro de PREÇO (DOL/IND): preço marcado pelo fator do objeto (câmbio ou índice).
            $puAnt  = (float) $d['pu_atual'];
            $mult   = $fator === 'fx' ? fx_fator_dia($data) : (1 + fator_mercado_dia($data));
            $puNovo = round($puAnt * $mult, 6);
            $pdo->prepare("UPDATE derivativos SET pu_atual=?, data_ref=? WHERE id=?")->execute([$puNovo, $data, $d['id']]);
        }
        $ponto  = (float) ($d['ponto'] ?? $pontoPerfil);
        $sinal  = (int) $d['comprado_pu'] ? 1 : -1;
        $ajuste = round(($puNovo - $puAnt) * (int) $d['contratos'] * $ponto * $sinal, 2);
        $pdo->prepare("INSERT INTO derivativos_ajustes (deriv_id, fundo_id, data_ref, pu_ant, pu_novo, ajuste) VALUES (?,?,?,?,?,?)")
            ->execute([$d['id'], $fid, $data, $puAnt, $puNovo, $ajuste]);
        $pdo->prepare("INSERT INTO movimentacoes (fundo_id, data_ref, tipo, descricao, valor) VALUES (?,?,?,?,?)")
            ->execute([$fid, $data, 'Ajuste Derivativo', "Ajuste diário {$d['instrumento']} venc " . $d['vencimento'], $ajuste]);
        $pdo->prepare("UPDATE fundos SET caixa_atual = caixa_atual + ? WHERE id=?")->execute([$ajuste, $fid]);
        $tot += $ajuste;
    }
    return $tot;
}

/** Posições de derivativos abertas do fundo, com ajuste acumulado e exposição nocional. */
function posicoes_derivativos(PDO $pdo, int $fid): array {
    $st = $pdo->prepare("SELECT * FROM derivativos WHERE fundo_id = ? AND status = 'Aberta' ORDER BY vencimento, id");
    $st->execute([$fid]);
    $rows = $st->fetchAll();
    foreach ($rows as &$r) {
        $aj = $pdo->prepare("SELECT COALESCE(SUM(ajuste),0) FROM derivativos_ajustes WHERE deriv_id = ?");
        $aj->execute([(int) $r['id']]);
        $r['ajuste_acum'] = (float) $aj->fetchColumn();
        $r['nocional']    = (int) $r['contratos'] * (float) $r['pu_atual'] * (float) ($r['ponto'] ?? 1);
    }
    return $rows;
}

/**
 * Abre uma posição de derivativo (deposita margem; sem desembolso do principal).
 * $valorEntrada: para futuro de TAXA (DI1/DAP) é a taxa a.a. em % (ex.: 11,20); para futuro
 * de PREÇO (DOL/IND) é o preço do contrato. A mecânica/ponto vêm de deriv_perfil($instr).
 */
function abrir_derivativo(PDO $pdo, int $fid, string $instr, string $venc, int $contratos, bool $compradoPu, float $valorEntrada, float $margem, string $data): int {
    return com_transacao($pdo, function () use ($pdo, $fid, $instr, $venc, $contratos, $compradoPu, $valorEntrada, $margem, $data) {
        [$mec, $ponto, $fator] = deriv_perfil($instr);
        $du = du_uteis($data, $venc);
        if ($mec === 'PU') {
            $taxa = $valorEntrada / 100.0;        // % a.a. → fração
            $taxaOp = $taxa; $mark = pu_futuro($taxa, $du);
        } else {
            $taxaOp = 0.0; $mark = $valorEntrada;  // preço do contrato
        }
        $pdo->prepare("INSERT INTO derivativos (fundo_id, instrumento, vencimento, contratos, comprado_pu, taxa_operacao, taxa_atual, pu_atual, margem, data_ref, mecanica, ponto)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$fid, $instr, $venc, $contratos, $compradoPu ? 1 : 0, $taxaOp, $taxaOp, $mark, $margem, $data, $mec, $ponto]);
        return (int) $pdo->lastInsertId();
    });
}
