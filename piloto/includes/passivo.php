<?php
// ============================================================
// Motor de PASSIVO DO COTISTA e TRIBUTAÇÃO (simulação) — Lei 14.754/2023.
// O administrador fiduciário é o RESPONSÁVEL TRIBUTÁRIO: calcula e retém.
// Operações envolvem múltiplas escritas → executadas em TRANSAÇÃO (com_transacao,
// definida em includes/dominio.php) para não deixar estado inconsistente.
//
// Simplificações DECLARADAS (piloto de demonstração, não produção):
//  • o ganho é apurado pela valorização da cota vs. custo médio do cotista;
//  • aplicação/resgate são cota-neutros (entra/sai proporcionalmente);
//  • come-cotas reduz cotas do cotista e o caixa do fundo pelo tributo,
//    com STEP-UP da base (a parcela já tributada não é tributada de novo);
//  • a tabela de IOF (<30 dias) é uma aproximação linear declarada.
// ============================================================

/** IR — tabela regressiva por prazo de permanência (dias). */
function aliquota_ir_regressiva(int $dias): float {
    if ($dias <= 180) return 22.5;
    if ($dias <= 360) return 20.0;
    if ($dias <= 720) return 17.5;
    return 15.0;
}

/** Come-cotas — alíquota conforme a tributação do fundo (null = não se aplica). */
function aliquota_come_cotas(string $tributacao): ?float {
    return match ($tributacao) {
        'Curto Prazo' => 20.0,
        'Longo Prazo' => 15.0,
        default       => null,   // 'Ações'/'Isento': sem come-cotas
    };
}

/** IOF regressivo (Dec. 6.306/2007, Anexo) — tabela OFICIAL de 30 alíquotas por dia (%). */
function iof_resgate(int $dias, float $rendimento): float {
    if ($dias >= 30 || $dias < 1 || $rendimento <= 0) return 0.0;
    static $tab = [1 => 96, 2 => 93, 3 => 90, 4 => 86, 5 => 83, 6 => 80, 7 => 76, 8 => 73,
                   9 => 70, 10 => 66, 11 => 63, 12 => 60, 13 => 56, 14 => 53, 15 => 50, 16 => 46,
                   17 => 43, 18 => 40, 19 => 36, 20 => 33, 21 => 30, 22 => 26, 23 => 23, 24 => 20,
                   25 => 16, 26 => 13, 27 => 10, 28 => 6, 29 => 3];
    return round($rendimento * ($tab[$dias] ?? 0) / 100.0, 2);
}

/** Último dia útil de um mês (para as datas legais do come-cotas: mai/nov). */
function ultimo_dia_util_do_mes(int $ano, int $mes): string {
    $d = new DateTime(sprintf('%04d-%02d-%02d', $ano, $mes, (int) date('t', mktime(0, 0, 0, $mes, 1, $ano))));
    while (!eh_dia_util($d->format('Y-m-d'))) $d->modify('-1 day');
    return $d->format('Y-m-d');
}

/** Dias corridos entre duas datas (Y-m-d). */
function dias_entre(?string $de, string $ate): int {
    if (!$de) return 0;
    return max(0, (int)(new DateTime($de))->diff(new DateTime($ate))->days);
}

/** Custo médio (PM) do cotista. */
function pm_cotista(array $c): float {
    $cotas = (float)$c['cotas'];
    $custo = $c['custo_total'] !== null ? (float)$c['custo_total'] : $cotas;   // NULL = PM 1,00
    return $cotas > 0 ? $custo / $cotas : 0.0;
}

/**
 * APLICAÇÃO: cotiza pela cota vigente na data → emite cotas. Atômica.
 * @return array [cotas emitidas, cota da cotização]
 */
function passivo_aplicar(PDO $pdo, int $fundoId, int $cotistaId, float $valor, string $data): array {
    return com_transacao($pdo, function () use ($pdo, $fundoId, $cotistaId, $valor, $data) {
        $cota = cota_em($pdo, $fundoId, $data);
        if (!$cota || $cota <= 0) $cota = 1.0;
        $qtd = $valor / $cota;

        $pdo->prepare('UPDATE cotistas SET cotas = cotas + ?, custo_total = COALESCE(custo_total,0) + ? WHERE id = ?')
            ->execute([$qtd, $valor, $cotistaId]);
        $pdo->prepare('INSERT INTO mov_cotistas (fundo_id, cotista_id, data_ref, tipo, valor, cotas, data_cotizacao, data_liquidacao)
                       VALUES (?,?,?,?,?,?,?,?)')
            ->execute([$fundoId, $cotistaId, $data, 'Aplicação', $valor, $qtd, $data, $data]);
        $pdo->prepare("INSERT INTO movimentacoes (fundo_id, data_ref, tipo, descricao, valor) VALUES (?,?,?,?,?)")
            ->execute([$fundoId, $data, 'Aplicação', 'Aplicação de cotista (cotização)', $valor]);
        $pdo->prepare('UPDATE fundos SET caixa_atual = caixa_atual + ? WHERE id = ?')->execute([$valor, $fundoId]);

        return ['cotas' => $qtd, 'cota' => $cota];
    });
}

/**
 * RESGATE por valor bruto desejado: cotiza, apura rendimento vs. PM, retém IR e IOF.
 * O caixa registra lançamentos SEPARADOS: líquido ao cotista, IR à Receita, IOF à Receita. Atômico.
 */
function passivo_resgatar(PDO $pdo, int $fundoId, int $cotistaId, float $valorBruto, string $data): array {
    return com_transacao($pdo, function () use ($pdo, $fundoId, $cotistaId, $valorBruto, $data) {
        $st = $pdo->prepare('SELECT * FROM cotistas WHERE id = ? AND fundo_id = ?');
        $st->execute([$cotistaId, $fundoId]);
        $c = $st->fetch();
        if (!$c) throw new RuntimeException('Cotista não encontrado.');

        $cota = cota_em($pdo, $fundoId, $data);
        if (!$cota || $cota <= 0) $cota = 1.0;

        $patrimonio = (float)$c['cotas'] * $cota;
        $bruto = min($valorBruto, $patrimonio);
        if ($bruto <= 0) throw new RuntimeException('Valor de resgate inválido ou saldo insuficiente.');

        $cotasResg = $bruto / $cota;
        $pm = pm_cotista($c);
        $custoResg = $pm * $cotasResg;
        $rendimento = max(0.0, $bruto - $custoResg);

        $dias = dias_entre($c['data_entrada'] ?? null, $data);
        // Ordem correta: IOF é retido PRIMEIRO; o IR incide sobre o rendimento LÍQUIDO de IOF.
        $iof = iof_resgate($dias, $rendimento);
        $baseIr = max(0.0, $rendimento - $iof);
        $aliq = aliquota_ir_regressiva($dias);
        $ir = round($baseIr * $aliq / 100.0, 2);
        $liquido = round($bruto - $ir - $iof, 2);
        $dLiqCot  = mais_dias_uteis($data, 2);   // pagamento ao cotista em D+2 (prazo de liquidação)
        $dLiqTrib = mais_dias_uteis($data, 3);   // recolhimento dos tributos (DARF) ~D+3

        $novasCotas = (float)$c['cotas'] - $cotasResg;
        if ($novasCotas < 0.0000001) $novasCotas = 0.0;
        $custoAtual = $c['custo_total'] !== null ? (float)$c['custo_total'] : (float)$c['cotas'];
        $novoCusto = max(0.0, $custoAtual - $custoResg);

        $pdo->prepare('UPDATE cotistas SET cotas = ?, custo_total = ? WHERE id = ?')
            ->execute([$novasCotas, $novoCusto, $cotistaId]);
        $pdo->prepare('INSERT INTO mov_cotistas (fundo_id, cotista_id, data_ref, tipo, valor, cotas, data_cotizacao, data_liquidacao)
                       VALUES (?,?,?,?,?,?,?,?)')
            ->execute([$fundoId, $cotistaId, $data, 'Resgate', $bruto, $cotasResg, $data, $dLiqCot]);

        // eventos fiscais
        if ($ir > 0) {
            $pdo->prepare('INSERT INTO eventos_fiscais (fundo_id, cotista_id, tipo, competencia, data_ref, base_calculo, aliquota, valor_tributo, cotas_reduzidas, detalhe)
                           VALUES (?,?,?,?,?,?,?,?,?,?)')
                ->execute([$fundoId, $cotistaId, 'IR Resgate', substr($data, 0, 7), $data, $baseIr, $aliq, $ir, 0,
                           "IR de {$aliq}% sobre rendimento líquido de IOF de " . number_format($baseIr, 2, ',', '.') . " ({$dias} dias)"]);
        }
        if ($iof > 0) {
            $pdo->prepare('INSERT INTO eventos_fiscais (fundo_id, cotista_id, tipo, competencia, data_ref, base_calculo, aliquota, valor_tributo, cotas_reduzidas, detalhe)
                           VALUES (?,?,?,?,?,?,?,?,?,?)')
                ->execute([$fundoId, $cotistaId, 'IOF Resgate', substr($data, 0, 7), $data, $rendimento, 0, $iof, 0,
                           "IOF por resgate em {$dias} dias (< 30)"]);
        }

        // Em vez de baixar o caixa AGORA, registra PASSIVOS que liquidam nas datas devidas
        // (cotista em D+2; tributos no DARF ~D+3). A cota fica estável: a redução de cotas é
        // compensada pelo passivo criado; a liquidação futura é neutra na cota.
        registrar_passivo($pdo, $fundoId, 'Valores a pagar (resgate)', $liquido, $data, $dLiqCot, 'Resgate líquido ao cotista #' . $cotistaId);
        if ($ir > 0)  registrar_passivo($pdo, $fundoId, 'Tributos a recolher (IR)',  $ir,  $data, $dLiqTrib, 'IR retido no resgate');
        if ($iof > 0) registrar_passivo($pdo, $fundoId, 'Tributos a recolher (IOF)', $iof, $data, $dLiqTrib, 'IOF retido no resgate');

        return compact('bruto', 'ir', 'iof', 'liquido', 'dias', 'aliq') + ['cotas' => $cotasResg, 'rendimento' => $rendimento];
    });
}

/**
 * COME-COTAS do fundo (maio/novembro): tributa o rendimento de cada cotista,
 * reduzindo cotas e fazendo STEP-UP da base. O caixa do fundo paga o tributo. Atômico e idempotente.
 */
function passivo_come_cotas(PDO $pdo, int $fundoId, string $competencia, string $data): array {
    return com_transacao($pdo, function () use ($pdo, $fundoId, $competencia, $data) {
        $st = $pdo->prepare('SELECT * FROM fundos WHERE id = ?');
        $st->execute([$fundoId]);
        $fundo = $st->fetch();
        $trib = $fundo['tributacao'] ?? 'Longo Prazo';
        if (($fundo['classe'] ?? '') === 'Ações') $trib = 'Ações';
        $aliq = aliquota_come_cotas($trib);
        if ($aliq === null) return ['aplicavel' => false, 'total' => 0.0, 'n' => 0, 'aliquota' => null];

        // Come-cotas só ocorre no ÚLTIMO DIA ÚTIL de MAIO e NOVEMBRO. Valida a competência e
        // força a data oficial (ignora a data passada, se divergir da regra legal).
        $cy = (int) substr($competencia, 0, 4); $cm = (int) substr($competencia, 5, 2);
        if (!in_array($cm, [5, 11], true)) {
            return ['aplicavel' => true, 'total' => 0.0, 'n' => 0, 'aliquota' => $aliq, 'data_invalida' => true];
        }
        $data = ultimo_dia_util_do_mes($cy, $cm);

        $ja = $pdo->prepare("SELECT COUNT(*) FROM eventos_fiscais WHERE fundo_id = ? AND tipo = 'Come-cotas' AND competencia = ?");
        $ja->execute([$fundoId, $competencia]);
        if ((int)$ja->fetchColumn() > 0) return ['aplicavel' => true, 'total' => 0.0, 'n' => 0, 'aliquota' => $aliq, 'repetido' => true];

        $cota = cota_em($pdo, $fundoId, $data);
        if (!$cota || $cota <= 0) $cota = 1.0;

        $cotistas = $pdo->prepare('SELECT * FROM cotistas WHERE fundo_id = ?');
        $cotistas->execute([$fundoId]);
        $total = 0.0; $n = 0;
        foreach ($cotistas->fetchAll() as $c) {
            $valorMercado = (float)$c['cotas'] * $cota;
            $custo = $c['custo_total'] !== null ? (float)$c['custo_total'] : (float)$c['cotas'];
            $rendimento = max(0.0, $valorMercado - $custo);
            $tributo = round($rendimento * $aliq / 100.0, 2);
            if ($tributo <= 0) continue;

            $cotasReduzidas = $tributo / $cota;
            $novasCotas = max(0.0, (float)$c['cotas'] - $cotasReduzidas);
            $novoCusto = $novasCotas * $cota;   // STEP-UP: base sobe para a cota atual

            $pdo->prepare('UPDATE cotistas SET cotas = ?, custo_total = ? WHERE id = ?')
                ->execute([$novasCotas, $novoCusto, $c['id']]);
            $pdo->prepare('INSERT INTO eventos_fiscais (fundo_id, cotista_id, tipo, competencia, data_ref, base_calculo, aliquota, valor_tributo, cotas_reduzidas, detalhe)
                           VALUES (?,?,?,?,?,?,?,?,?,?)')
                ->execute([$fundoId, $c['id'], 'Come-cotas', $competencia, $data, $rendimento, $aliq, $tributo, $cotasReduzidas,
                           "Come-cotas {$aliq}% sobre rendimento de " . number_format($rendimento, 2, ',', '.')]);
            $total += $tributo; $n++;
        }

        if ($total > 0) {
            // o tributo do come-cotas vira PASSIVO "a recolher" até o DARF (~D+3), não sai na hora
            $dLiqTrib = mais_dias_uteis($data, 3);
            registrar_passivo($pdo, $fundoId, 'Tributos a recolher (Come-cotas)', $total, $data, $dLiqTrib, "Come-cotas {$competencia} ({$n} cotistas)");
        }

        return ['aplicavel' => true, 'total' => $total, 'n' => $n, 'aliquota' => $aliq];
    });
}

// ============================================================
// ORDENS DO PORTAL DO COTISTA — a "porta de entrada" do dinheiro, como na vida real:
//  · APLICAÇÃO: o cotista cria a ordem e paga por TED ou Pix a partir de conta DE SUA
//    TITULARIDADE (regra de PLD — recurso de terceiro é devolvido, Res. CVM 50, art. 20).
//    Pix usa QR dinâmico com txid, que casa o crédito com a ordem automaticamente.
//    Cartão de crédito NÃO existe em fundo; espécie é vedada na prática (PLD); DOC foi extinto.
//  · Confirmado o recebimento (extrato) e a titularidade, a administradora COTIZA
//    (passivo_aplicar) pela cota da data do recurso disponível.
//  · RESGATE: pago exclusivamente na conta bancária CADASTRADA do cotista (mesma
//    titularidade), líquido de IOF/IR retidos na fonte (passivo_resgatar).
// ============================================================

/** DDL das ordens + conta bancária cadastrada do cotista + vínculo token→cotista. FORA de transação. */
function ensure_ordens_passivo(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS ordens_passivo (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fundo_id INT NOT NULL,
        cotista_id INT NOT NULL,
        tipo ENUM('Aplicação','Resgate') NOT NULL,
        valor DECIMAL(18,2) NOT NULL,
        metodo VARCHAR(10) NULL,            -- 'Pix' | 'TED' (aplicação); resgate sai p/ conta cadastrada
        txid VARCHAR(35) NULL,              -- Pix dinâmico: txid casa o crédito com a ordem
        pagador_doc VARCHAR(24) NULL,       -- documento do pagador efetivo (validação de titularidade)
        status VARCHAR(30) NOT NULL DEFAULT 'Aguardando pagamento',
        motivo VARCHAR(300) NULL,
        mov_ref VARCHAR(60) NULL,           -- referência da cotização/pagamento gerado
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
        confirmado_por VARCHAR(100) NULL,
        confirmado_em DATETIME NULL,
        INDEX idx_op (fundo_id, status),
        INDEX idx_op_cot (cotista_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // conta bancária CADASTRADA do cotista (mesma titularidade): origem esperada da aplicação
    // e destino OBRIGATÓRIO do resgate — prática universal de PLD.
    ddl_portavel($pdo, "ALTER TABLE cotistas ADD COLUMN IF NOT EXISTS banco VARCHAR(60) NULL");
    ddl_portavel($pdo, "ALTER TABLE cotistas ADD COLUMN IF NOT EXISTS agencia VARCHAR(10) NULL");
    ddl_portavel($pdo, "ALTER TABLE cotistas ADD COLUMN IF NOT EXISTS conta VARCHAR(20) NULL");
    ddl_portavel($pdo, "ALTER TABLE cotistas ADD COLUMN IF NOT EXISTS pix_chave VARCHAR(80) NULL");
    // token do portal pode ser vinculado a UM cotista (habilita movimentação self-service)
    ddl_portavel($pdo, "ALTER TABLE tokens_acesso ADD COLUMN IF NOT EXISTS cotista_id INT NULL");
    // múltiplas contas bancárias por CONTA de acesso (todas da MESMA titularidade — o CPF é o da
    // conta); a PRINCIPAL é espelhada em cotistas.banco/agencia/conta (admin/custódia leem de lá)
    $pdo->exec("CREATE TABLE IF NOT EXISTS cotista_contas_bancarias (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conta_id INT NOT NULL,              -- cotista_contas.id
        banco VARCHAR(60) NOT NULL,
        agencia VARCHAR(10) NULL,
        conta VARCHAR(20) NOT NULL,
        pix_chave VARCHAR(80) NULL,
        principal TINYINT DEFAULT 0,
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ccb (conta_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // resgate grava o SNAPSHOT da conta de destino escolhida (imutável mesmo se o cadastro mudar)
    ddl_portavel($pdo, "ALTER TABLE ordens_passivo ADD COLUMN IF NOT EXISTS conta_destino VARCHAR(160) NULL");
}

/** Contas bancárias da CONTA de acesso; na 1ª chamada importa a conta única da era anterior (cotistas.banco). */
function contas_bancarias_da_conta(PDO $pdo, int $contaId): array {
    $st = $pdo->prepare('SELECT * FROM cotista_contas_bancarias WHERE conta_id = ? ORDER BY principal DESC, id');
    $st->execute([$contaId]);
    $rows = $st->fetchAll();
    if (!$rows) {
        $st = $pdo->prepare("SELECT banco, agencia, conta, pix_chave FROM cotistas
                             WHERE conta_id = ? AND banco IS NOT NULL AND banco <> '' LIMIT 1");
        $st->execute([$contaId]);
        if ($v = $st->fetch()) {
            $pdo->prepare('INSERT INTO cotista_contas_bancarias (conta_id, banco, agencia, conta, pix_chave, principal)
                           VALUES (?,?,?,?,?,1)')
                ->execute([$contaId, $v['banco'], $v['agencia'], $v['conta'], $v['pix_chave']]);
            return contas_bancarias_da_conta($pdo, $contaId);
        }
    }
    return $rows;
}

/** Espelha a conta PRINCIPAL nos vínculos (cotistas.*) — compatibilidade com admin/custódia. */
function espelhar_conta_principal(PDO $pdo, int $contaId): void {
    $st = $pdo->prepare('SELECT * FROM cotista_contas_bancarias WHERE conta_id = ? AND principal = 1 LIMIT 1');
    $st->execute([$contaId]);
    $p = $st->fetch() ?: null;
    $pdo->prepare('UPDATE cotistas SET banco=?, agencia=?, conta=?, pix_chave=? WHERE conta_id=?')
        ->execute([$p['banco'] ?? null, $p['agencia'] ?? null, $p['conta'] ?? null, $p['pix_chave'] ?? null, $contaId]);
}

/** Valor bruto BLOQUEADO em resgates ainda não pagos ('Solicitado') — desconta do saldo disponível. */
function resgates_pendentes(PDO $pdo, int $cotistaId): float {
    $st = $pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM ordens_passivo
                         WHERE cotista_id = ? AND tipo = 'Resgate' AND status = 'Solicitado'");
    $st->execute([$cotistaId]);
    return (float)$st->fetchColumn();
}

/** Mínimos definidos no REGULAMENTO do fundo (reg_dados); 0 = o regulamento não fixa mínimo. */
function minimos_do_fundo(array $fundo): array {
    $d = [];
    if (!empty($fundo['reg_dados'])) {
        $j = json_decode((string)$fundo['reg_dados'], true);
        if (is_array($j)) $d = $j;
    }
    return ['aplicacao'    => (float)($d['aplicacao_minima']    ?? 0),
            'movimentacao' => (float)($d['movimentacao_minima'] ?? 0),
            'saldo'        => (float)($d['saldo_minimo']        ?? 0)];
}

/** Cria a ordem de APLICAÇÃO (status 'Aguardando pagamento'; Pix ganha txid). */
function ordem_criar_aplicacao(PDO $pdo, int $fundoId, int $cotistaId, float $valor, string $metodo): array {
    $metodo = $metodo === 'Pix' ? 'Pix' : 'TED';
    $txid = $metodo === 'Pix' ? strtoupper(bin2hex(random_bytes(12))) : null;   // 24 chars, padrão txid
    $pdo->prepare("INSERT INTO ordens_passivo (fundo_id, cotista_id, tipo, valor, metodo, txid)
                   VALUES (?,?,?,?,?,?)")
        ->execute([$fundoId, $cotistaId, 'Aplicação', $valor, $metodo, $txid]);
    return ['id' => (int)$pdo->lastInsertId(), 'txid' => $txid, 'metodo' => $metodo];
}

/** Cria a ordem de RESGATE (status 'Solicitado'; conta_destino = snapshot da conta escolhida). */
function ordem_criar_resgate(PDO $pdo, int $fundoId, int $cotistaId, float $valorBruto, ?string $contaDestino = null): int {
    $pdo->prepare("INSERT INTO ordens_passivo (fundo_id, cotista_id, tipo, valor, status, conta_destino)
                   VALUES (?,?,?,?, 'Solicitado', ?)")
        ->execute([$fundoId, $cotistaId, 'Resgate', $valorBruto, $contaDestino]);
    return (int)$pdo->lastInsertId();
}
