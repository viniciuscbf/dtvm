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

/** IOF regressivo simplificado para resgates com menos de 30 dias. Aproximação linear. */
function iof_resgate(int $dias, float $rendimento): float {
    if ($dias >= 30 || $rendimento <= 0) return 0.0;
    $percent = max(0.0, (30 - $dias)) * (100.0 / 30.0);   // ~96,7% no dia 1 → 0% no dia 30
    return round($rendimento * $percent / 100.0, 2);
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
        $aliq = aliquota_ir_regressiva($dias);
        $ir = round($rendimento * $aliq / 100.0, 2);
        $iof = iof_resgate($dias, $rendimento);
        $liquido = round($bruto - $ir - $iof, 2);

        $novasCotas = (float)$c['cotas'] - $cotasResg;
        if ($novasCotas < 0.0000001) $novasCotas = 0.0;
        $custoAtual = $c['custo_total'] !== null ? (float)$c['custo_total'] : (float)$c['cotas'];
        $novoCusto = max(0.0, $custoAtual - $custoResg);

        $pdo->prepare('UPDATE cotistas SET cotas = ?, custo_total = ? WHERE id = ?')
            ->execute([$novasCotas, $novoCusto, $cotistaId]);
        $pdo->prepare('INSERT INTO mov_cotistas (fundo_id, cotista_id, data_ref, tipo, valor, cotas, data_cotizacao, data_liquidacao)
                       VALUES (?,?,?,?,?,?,?,?)')
            ->execute([$fundoId, $cotistaId, $data, 'Resgate', $bruto, $cotasResg, $data, $data]);

        // eventos fiscais
        if ($ir > 0) {
            $pdo->prepare('INSERT INTO eventos_fiscais (fundo_id, cotista_id, tipo, competencia, data_ref, base_calculo, aliquota, valor_tributo, cotas_reduzidas, detalhe)
                           VALUES (?,?,?,?,?,?,?,?,?,?)')
                ->execute([$fundoId, $cotistaId, 'IR Resgate', substr($data, 0, 7), $data, $rendimento, $aliq, $ir, 0,
                           "IR de {$aliq}% sobre rendimento de " . number_format($rendimento, 2, ',', '.') . " ({$dias} dias)"]);
        }
        if ($iof > 0) {
            $pdo->prepare('INSERT INTO eventos_fiscais (fundo_id, cotista_id, tipo, competencia, data_ref, base_calculo, aliquota, valor_tributo, cotas_reduzidas, detalhe)
                           VALUES (?,?,?,?,?,?,?,?,?,?)')
                ->execute([$fundoId, $cotistaId, 'IOF Resgate', substr($data, 0, 7), $data, $rendimento, 0, $iof, 0,
                           "IOF por resgate em {$dias} dias (< 30)"]);
        }

        // caixa: lançamentos separados por destino (líquido ao cotista + tributos à Receita)
        $pdo->prepare("INSERT INTO movimentacoes (fundo_id, data_ref, tipo, descricao, valor) VALUES (?,?,?,?,?)")
            ->execute([$fundoId, $data, 'Resgate', 'Resgate líquido ao cotista', -$liquido]);
        if ($ir > 0) $pdo->prepare("INSERT INTO movimentacoes (fundo_id, data_ref, tipo, descricao, valor) VALUES (?,?,?,?,?)")
            ->execute([$fundoId, $data, 'IR Retido', 'IR retido no resgate (recolhido à Receita)', -$ir]);
        if ($iof > 0) $pdo->prepare("INSERT INTO movimentacoes (fundo_id, data_ref, tipo, descricao, valor) VALUES (?,?,?,?,?)")
            ->execute([$fundoId, $data, 'IOF Retido', 'IOF retido no resgate (recolhido à Receita)', -$iof]);
        $pdo->prepare('UPDATE fundos SET caixa_atual = caixa_atual - ? WHERE id = ?')->execute([$bruto, $fundoId]);

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
            $pdo->prepare("INSERT INTO movimentacoes (fundo_id, data_ref, tipo, descricao, valor) VALUES (?,?,?,?,?)")
                ->execute([$fundoId, $data, 'Come-cotas', "Come-cotas {$competencia} recolhido à Receita ({$n} cotistas)", -$total]);
            $pdo->prepare('UPDATE fundos SET caixa_atual = caixa_atual - ? WHERE id = ?')->execute([$total, $fundoId]);
        }

        return ['aplicavel' => true, 'total' => $total, 'n' => $n, 'aliquota' => $aliq];
    });
}
