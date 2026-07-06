<?php
// ============================================================
// FIP — Fundo de Investimento em Participações (Private Equity).
// Modelo (Res. CVM 175 Anexo IV + práticas ABVCAP/ANBIMA):
//  • condomínio FECHADO, LPs com CAPITAL COMPROMETIDO vs INTEGRALIZADO;
//  • CHAMADAS DE CAPITAL (drawdowns) contra o compromisso;
//  • PARTICIPAÇÕES em investidas marcadas por LAUDO/valor justo (CPC 46, nível 3),
//    revisão ≤ 12 meses; método DCF/múltiplos;
//  • dividendos/JCP das investidas, DESINVESTIMENTO (exit) com ganho realizado;
//  • DISTRIBUIÇÃO aos cotistas (amortização) com WATERFALL: retorno de capital →
//    retorno preferencial (hurdle ~8%) → carried interest (20%).
//  • PL do FIP = caixa + Σ valor justo das participações (não usa ativos_carteira).
//  • Tributação: 15% flat (não come-cotas, se entidade de investimento) — Lei 14.754.
// Simplificações declaradas: waterfall e tributação em 1ª versão.
// ============================================================

const FIP_HURDLE_AA = 0.08;   // retorno preferencial anual (referência)
const FIP_CARRY = 0.20;       // carried interest do gestor

function ensure_fip(PDO $pdo): void {
    $eng = "ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec("CREATE TABLE IF NOT EXISTS fip_lps (
        id INT AUTO_INCREMENT PRIMARY KEY, fundo_id INT NOT NULL, cotista_id INT NULL,
        nome VARCHAR(150) NOT NULL, documento VARCHAR(20),
        capital_comprometido DECIMAL(18,2) DEFAULT 0, capital_integralizado DECIMAL(18,2) DEFAULT 0,
        status VARCHAR(20) DEFAULT 'Ativo', criado_em DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX idx_lp (fundo_id)) $eng");
    $pdo->exec("CREATE TABLE IF NOT EXISTS fip_chamadas (
        id INT AUTO_INCREMENT PRIMARY KEY, fundo_id INT NOT NULL, competencia VARCHAR(20),
        percentual DECIMAL(8,4), valor_total DECIMAL(18,2), data_chamada DATE, prazo DATE,
        status VARCHAR(20) DEFAULT 'Aberta', criado_em DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX idx_ch (fundo_id)) $eng");
    $pdo->exec("CREATE TABLE IF NOT EXISTS fip_chamada_lp (
        id INT AUTO_INCREMENT PRIMARY KEY, chamada_id INT NOT NULL, lp_id INT NOT NULL, fundo_id INT NOT NULL,
        valor DECIMAL(18,2), status VARCHAR(20) DEFAULT 'Pendente', pago_em DATETIME NULL, INDEX idx_chlp (chamada_id)) $eng");
    $pdo->exec("CREATE TABLE IF NOT EXISTS fip_participacoes (
        id INT AUTO_INCREMENT PRIMARY KEY, fundo_id INT NOT NULL, empresa VARCHAR(150) NOT NULL, setor VARCHAR(80),
        percentual DECIMAL(8,4), custo_aquisicao DECIMAL(18,2), valor_justo DECIMAL(18,2),
        metodo VARCHAR(30) DEFAULT 'Custo', nivel TINYINT DEFAULT 3, data_avaliacao DATE,
        status VARCHAR(20) DEFAULT 'Ativa', valor_venda DECIMAL(18,2) NULL, criado_em DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX idx_pa (fundo_id, status)) $eng");
    $pdo->exec("CREATE TABLE IF NOT EXISTS fip_avaliacoes (
        id INT AUTO_INCREMENT PRIMARY KEY, participacao_id INT NOT NULL, data_ref DATE, valor_justo DECIMAL(18,2),
        metodo VARCHAR(30), nivel TINYINT, laudo VARCHAR(300), criado_em DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX idx_av (participacao_id)) $eng");
    $pdo->exec("CREATE TABLE IF NOT EXISTS fip_distribuicoes (
        id INT AUTO_INCREMENT PRIMARY KEY, fundo_id INT NOT NULL, data_ref DATE, valor_total DECIMAL(18,2),
        retorno_capital DECIMAL(18,2) DEFAULT 0, retorno_preferencial DECIMAL(18,2) DEFAULT 0, carry DECIMAL(18,2) DEFAULT 0,
        detalhe VARCHAR(300), criado_em DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX idx_di (fundo_id)) $eng");
}

/** Valor justo total das participações ativas (compõe o PL do FIP). */
function valor_participacoes_fip(PDO $pdo, int $fid): float {
    try {
        $st = $pdo->prepare("SELECT COALESCE(SUM(valor_justo),0) FROM fip_participacoes WHERE fundo_id=? AND status='Ativa'");
        $st->execute([$fid]);
        return (float) $st->fetchColumn();
    } catch (Throwable $e) { return 0.0; }
}

/** Registra o compromisso de um LP (capital comprometido). */
function fip_comprometer(PDO $pdo, int $fid, string $nome, string $doc, float $comprometido): int {
    return com_transacao($pdo, function () use ($pdo, $fid, $nome, $doc, $comprometido) {
        $pdo->prepare("INSERT INTO fip_lps (fundo_id, nome, documento, capital_comprometido) VALUES (?,?,?,?)")
            ->execute([$fid, $nome, $doc, $comprometido]);
        return (int) $pdo->lastInsertId();
    });
}

/** Emite uma chamada de capital de $percentual% do comprometido, pro-rata entre os LPs. */
function fip_emitir_chamada(PDO $pdo, int $fid, float $percentual, string $data, string $prazo): int {
    return com_transacao($pdo, function () use ($pdo, $fid, $percentual, $data, $prazo) {
        $lps = $pdo->prepare("SELECT * FROM fip_lps WHERE fundo_id=? AND status='Ativo'"); $lps->execute([$fid]);
        $lps = $lps->fetchAll();
        $total = 0.0;
        foreach ($lps as $lp) $total += (float) $lp['capital_comprometido'] * $percentual / 100.0;
        $pdo->prepare("INSERT INTO fip_chamadas (fundo_id, competencia, percentual, valor_total, data_chamada, prazo)
                       VALUES (?,?,?,?,?,?)")->execute([$fid, substr($data, 0, 7), $percentual, $total, $data, $prazo]);
        $chId = (int) $pdo->lastInsertId();
        foreach ($lps as $lp) {
            $v = round((float) $lp['capital_comprometido'] * $percentual / 100.0, 2);
            if ($v > 0) $pdo->prepare("INSERT INTO fip_chamada_lp (chamada_id, lp_id, fundo_id, valor) VALUES (?,?,?,?)")
                ->execute([$chId, $lp['id'], $fid, $v]);
        }
        return $chId;
    });
}

/** LP integraliza sua parcela da chamada → entra caixa e o LP recebe cotas pela cota vigente. */
function fip_integralizar(PDO $pdo, int $chamadaLpId, string $data): void {
    com_transacao($pdo, function () use ($pdo, $chamadaLpId, $data) {
        $cl = $pdo->prepare("SELECT * FROM fip_chamada_lp WHERE id=? AND status='Pendente'"); $cl->execute([$chamadaLpId]);
        $cl = $cl->fetch();
        if (!$cl) return;
        $fid = (int) $cl['fundo_id']; $valor = (float) $cl['valor'];
        $lp = $pdo->prepare("SELECT * FROM fip_lps WHERE id=?"); $lp->execute([$cl['lp_id']]); $lp = $lp->fetch();
        $pdo->prepare("UPDATE fip_chamada_lp SET status='Pago', pago_em=NOW() WHERE id=?")->execute([$chamadaLpId]);
        $pdo->prepare("UPDATE fip_lps SET capital_integralizado = capital_integralizado + ? WHERE id=?")->execute([$valor, $cl['lp_id']]);
        $pdo->prepare("INSERT INTO movimentacoes (fundo_id, data_ref, tipo, descricao, valor) VALUES (?,?,?,?,?)")
            ->execute([$fid, $data, 'Integralização', "Integralização de chamada — {$lp['nome']}", $valor]);
        $pdo->prepare("UPDATE fundos SET caixa_atual = caixa_atual + ? WHERE id=?")->execute([$valor, $fid]);
        // emite cotas ao LP pela cota vigente (vincula a um cotista)
        $cota = cota_em($pdo, $fid, $data); if (!$cota || $cota <= 0) $cota = 1.0;
        $qtd = $valor / $cota;
        $ct = $pdo->prepare("SELECT id FROM cotistas WHERE fundo_id=? AND documento=? LIMIT 1"); $ct->execute([$fid, $lp['documento']]);
        $cid = (int) ($ct->fetchColumn() ?: 0);
        if (!$cid) {
            $pdo->prepare("INSERT INTO cotistas (fundo_id, nome, documento, tipo_pessoa, cotas, custo_total, data_entrada) VALUES (?,?,?,?,?,?,?)")
                ->execute([$fid, $lp['nome'], $lp['documento'], 'PJ', $qtd, $valor, $data]);
            $cid = (int) $pdo->lastInsertId();
            $pdo->prepare("UPDATE fip_lps SET cotista_id=? WHERE id=?")->execute([$cid, $cl['lp_id']]);
        } else {
            $pdo->prepare("UPDATE cotistas SET cotas = cotas + ?, custo_total = COALESCE(custo_total,0) + ? WHERE id=?")->execute([$qtd, $valor, $cid]);
        }
    });
}

/** Marca a parcela como inadimplente (LP perde direito de voto sobre as cotas — Res. 175 Anexo IV). */
function fip_inadimplir(PDO $pdo, int $chamadaLpId): void {
    $pdo->prepare("UPDATE fip_chamada_lp SET status='Inadimplente' WHERE id=? AND status='Pendente'")->execute([$chamadaLpId]);
}

/** Investe numa empresa: sai caixa, cria participação (valor justo inicial = custo). */
function fip_investir(PDO $pdo, int $fid, string $empresa, string $setor, float $percentual, float $custo, string $data): int {
    return com_transacao($pdo, function () use ($pdo, $fid, $empresa, $setor, $percentual, $custo, $data) {
        $pdo->prepare("INSERT INTO movimentacoes (fundo_id, data_ref, tipo, descricao, valor) VALUES (?,?,?,?,?)")
            ->execute([$fid, $data, 'Investimento FIP', "Aquisição de participação em $empresa", -$custo]);
        $pdo->prepare("UPDATE fundos SET caixa_atual = caixa_atual - ? WHERE id=?")->execute([$custo, $fid]);
        $pdo->prepare("INSERT INTO fip_participacoes (fundo_id, empresa, setor, percentual, custo_aquisicao, valor_justo, metodo, nivel, data_avaliacao, status)
                       VALUES (?,?,?,?,?,?, 'Custo', 3, ?, 'Ativa')")->execute([$fid, $empresa, $setor, $percentual, $custo, $custo, $data]);
        return (int) $pdo->lastInsertId();
    });
}

/** Reavaliação por laudo (valor justo nível 3). Muda o PL/cota do FIP. */
function fip_reavaliar(PDO $pdo, int $partId, float $novoValor, string $metodo, string $laudo, string $data): void {
    com_transacao($pdo, function () use ($pdo, $partId, $novoValor, $metodo, $laudo, $data) {
        $pdo->prepare("UPDATE fip_participacoes SET valor_justo=?, metodo=?, data_avaliacao=? WHERE id=?")->execute([$novoValor, $metodo, $data, $partId]);
        $pdo->prepare("INSERT INTO fip_avaliacoes (participacao_id, data_ref, valor_justo, metodo, nivel, laudo) VALUES (?,?,?,?,3,?)")
            ->execute([$partId, $data, $novoValor, $metodo, $laudo]);
    });
}

/** Dividendo/JCP recebido de uma investida → entra caixa. */
function fip_dividendo(PDO $pdo, int $partId, float $valor, string $data): void {
    com_transacao($pdo, function () use ($pdo, $partId, $valor, $data) {
        $p = $pdo->prepare("SELECT * FROM fip_participacoes WHERE id=?"); $p->execute([$partId]); $p = $p->fetch();
        $pdo->prepare("INSERT INTO movimentacoes (fundo_id, data_ref, tipo, descricao, valor) VALUES (?,?,?,?,?)")
            ->execute([$p['fundo_id'], $data, 'Provento', "Dividendo/JCP de {$p['empresa']}", $valor]);
        $pdo->prepare("UPDATE fundos SET caixa_atual = caixa_atual + ? WHERE id=?")->execute([$valor, $p['fundo_id']]);
    });
}

/** Desinvestimento (exit): vende a participação, entra caixa, apura ganho vs custo. */
function fip_vender(PDO $pdo, int $partId, float $valorVenda, string $data): array {
    return com_transacao($pdo, function () use ($pdo, $partId, $valorVenda, $data) {
        $p = $pdo->prepare("SELECT * FROM fip_participacoes WHERE id=? AND status='Ativa'"); $p->execute([$partId]); $p = $p->fetch();
        if (!$p) throw new RuntimeException('Participação não encontrada/já vendida.');
        $ganho = $valorVenda - (float) $p['custo_aquisicao'];
        $pdo->prepare("UPDATE fip_participacoes SET status='Vendida', valor_venda=?, valor_justo=? WHERE id=?")->execute([$valorVenda, $valorVenda, $partId]);
        $pdo->prepare("INSERT INTO movimentacoes (fundo_id, data_ref, tipo, descricao, valor) VALUES (?,?,?,?,?)")
            ->execute([$p['fundo_id'], $data, 'Desinvestimento', "Venda da participação em {$p['empresa']} (ganho " . number_format($ganho, 2, ',', '.') . ')', $valorVenda]);
        $pdo->prepare("UPDATE fundos SET caixa_atual = caixa_atual + ? WHERE id=?")->execute([$valorVenda, $p['fundo_id']]);
        return ['ganho' => $ganho, 'empresa' => $p['empresa']];
    });
}

/** Distribuição aos cotistas (amortização) com waterfall simplificado. */
function fip_distribuir(PDO $pdo, int $fid, float $valor, string $data): array {
    return com_transacao($pdo, function () use ($pdo, $fid, $valor, $data) {
        $integ = (float) $pdo->query("SELECT COALESCE(SUM(capital_integralizado),0) FROM fip_lps WHERE fundo_id=$fid")->fetchColumn();
        $jaCap = (float) $pdo->query("SELECT COALESCE(SUM(retorno_capital),0) FROM fip_distribuicoes WHERE fundo_id=$fid")->fetchColumn();
        $restanteCap = max(0.0, $integ - $jaCap);
        $retornoCapital = min($valor, $restanteCap);
        $excedente = round($valor - $retornoCapital, 2);           // parcela de "lucro" distribuída
        $carry = $excedente > 0 ? round($excedente * FIP_CARRY, 2) : 0.0;   // 20% ao gestor
        $retornoLps = round($excedente - $carry, 2);               // 80% do lucro aos LPs (preferred incluído)
        $pdo->prepare("INSERT INTO movimentacoes (fundo_id, data_ref, tipo, descricao, valor) VALUES (?,?,?,?,?)")
            ->execute([$fid, $data, 'Distribuição FIP', "Distribuição (amortização) — retorno capital " . number_format($retornoCapital, 2, ',', '.') . " + lucro", -$valor]);
        $pdo->prepare("UPDATE fundos SET caixa_atual = caixa_atual - ? WHERE id=?")->execute([$valor, $fid]);
        $pdo->prepare("INSERT INTO fip_distribuicoes (fundo_id, data_ref, valor_total, retorno_capital, retorno_preferencial, carry, detalhe)
                       VALUES (?,?,?,?,?,?,?)")->execute([$fid, $data, $valor, $retornoCapital, $retornoLps, $carry, 'Waterfall: retorno de capital → LPs → carry 20%']);
        return compact('retornoCapital', 'retornoLps', 'carry');
    });
}
