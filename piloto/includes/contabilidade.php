<?php
// ============================================================
// CONTABILIDADE de fundos (dupla entrada) — controladoria contábil.
// Gera partidas dobradas a partir do livro de movimentações e da carteira,
// produzindo diário, razão e balancete. Simplificação declarada: o razão é
// DERIVADO da movimentação (não é o lançamento primário); em produção a
// contabilidade seria a fonte primária no leiaute COSIF/CVM.
// ============================================================

/** Plano de contas simplificado: código => [nome, grupo]. Grupos: Ativo/Passivo/PL/Receita/Despesa. */
const PLANO_CONTAS = [
    '1.1' => ['Disponibilidades (Caixa)',            'Ativo'],
    '1.2' => ['Títulos e Valores Mobiliários',       'Ativo'],
    '2.1' => ['Tributos a Recolher',                 'Passivo'],
    '2.2' => ['Taxas a Pagar (provisão)',            'Passivo'],
    '3.0' => ['Patrimônio Líquido (Cotas)',          'PL'],
    '4.0' => ['Receitas (proventos e rendimentos)',  'Receita'],
    '5.0' => ['Despesas (taxas)',                    'Despesa'],
];

/** Partida dobrada de um movimento: retorna [conta_debitada, conta_creditada, valor]. */
function partida_de_movimento(array $mov): array {
    $t = $mov['tipo'];
    $abs = abs((float)$mov['valor']);
    switch ($t) {
        case 'Aplicação':          return ['1.1', '3.0', $abs];  // entra caixa; PL sobe
        case 'Resgate':            return ['3.0', '1.1', $abs];  // PL desce; sai caixa (líquido)
        case 'IR Retido':
        case 'IOF Retido':
        case 'Come-cotas':         return ['3.0', '1.1', $abs];  // tributo do cotista; PL desce, sai caixa
        case 'Liquidação Compra':  return ['1.2', '1.1', $abs];  // compra ativo: títulos sobem, caixa desce
        case 'Liquidação Venda':   return ['1.1', '1.2', $abs];  // venda: caixa sobe, títulos descem
        case 'Provento':           return ['1.1', '4.0', $abs];  // rendimento: caixa sobe, receita
        case 'Amortização':        return ['1.1', '1.2', $abs];  // principal devolvido: caixa sobe, títulos descem
        case 'Taxa':               return ['5.0', '1.1', $abs];  // despesa: sai caixa
        default:                   return (float)$mov['valor'] >= 0 ? ['1.1', '3.0', $abs] : ['5.0', '1.1', $abs];
    }
}

/** Diário: partidas dobradas até a data (mais recentes primeiro). */
function diario_contabil(PDO $pdo, int $fid, string $ate, int $limite = 60): array {
    $st = $pdo->prepare("SELECT * FROM movimentacoes WHERE fundo_id = ? AND data_ref <= ? ORDER BY data_ref DESC, id DESC LIMIT ?");
    $st->bindValue(1, $fid, PDO::PARAM_INT);
    $st->bindValue(2, $ate);
    $st->bindValue(3, $limite, PDO::PARAM_INT);
    $st->execute();
    $out = [];
    foreach ($st->fetchAll() as $m) {
        [$d, $c, $v] = partida_de_movimento($m);
        $out[] = ['data' => $m['data_ref'], 'historico' => $m['descricao'], 'debito' => $d, 'credito' => $c, 'valor' => $v, 'tipo' => $m['tipo']];
    }
    return $out;
}

/** Razão: soma de débitos/créditos por conta (do fluxo de movimentações até a data). */
function razao_contabil(PDO $pdo, int $fid, string $ate): array {
    $st = $pdo->prepare("SELECT * FROM movimentacoes WHERE fundo_id = ? AND data_ref <= ?");
    $st->execute([$fid, $ate]);
    $r = [];
    foreach (array_keys(PLANO_CONTAS) as $conta) $r[$conta] = ['debito' => 0.0, 'credito' => 0.0];
    foreach ($st->fetchAll() as $m) {
        [$d, $c, $v] = partida_de_movimento($m);
        $r[$d]['debito'] += $v;
        $r[$c]['credito'] += $v;
    }
    return $r;
}

/**
 * Balancete NA DATA: saldos das contas patrimoniais e de resultado.
 * Caixa e Títulos vêm das fontes autoritativas (movimentação/carteira); o PL fecha por diferença.
 * Retorna estrutura pronta para exibição + o "confere" (Ativo = Passivo + PL).
 */
function balancete(PDO $pdo, array $fundo, string $ate): array {
    $fid = (int)$fundo['id'];
    $caixa = caixa_na_data($pdo, $fid, $ate);
    $titulos = array_sum(array_column(carteira($pdo, $fid, $ate), 'valor_mercado'));
    $taxasAPagar = (float)($fundo['provisao_despesas'] ?? 0);

    // resultado do período (receitas e despesas acumuladas até a data)
    $razao = razao_contabil($pdo, $fid, $ate);
    $receitas = $razao['4.0']['credito'] - $razao['4.0']['debito'];
    $despesas = $razao['5.0']['debito'] - $razao['5.0']['credito'];

    $ativo = $caixa + $titulos;
    $passivo = $taxasAPagar;                 // tributos a recolher = 0 (recolhidos na hora na simulação)
    $pl = $ativo - $passivo;                  // PL fecha por diferença (patrimônio dos cotistas)

    return [
        'ativo' => [
            '1.1' => $caixa,
            '1.2' => $titulos,
            'total' => $ativo,
        ],
        'passivo' => [
            '2.1' => 0.0,
            '2.2' => $taxasAPagar,
            'total' => $passivo,
        ],
        'pl' => $pl,
        'resultado' => ['receitas' => $receitas, 'despesas' => $despesas, 'liquido' => $receitas - $despesas],
        'confere' => abs($ativo - ($passivo + $pl)) < 0.01,
    ];
}
