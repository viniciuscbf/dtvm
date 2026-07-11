<?php
// Instruções & Liquidação — o custodiante executa a entrega contra pagamento (DVP)
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

$u = exigir_perfil('custodia');
$msg = ''; $msgTipo = 'success';

/** Liquidada uma boleta, a posição entra/sai da carteira (preço médio ponderado). */
function aplicar_boleta_na_carteira(PDO $pdo, array $b): void {
    $fid = (int)$b['fundo_id'];
    $dataSnap = ultima_data_carteira($pdo, $fid);
    if (!$dataSnap) return;
    $st = $pdo->prepare('SELECT * FROM ativos_carteira WHERE fundo_id = ? AND codigo = ? AND data_ref = ?');
    $st->execute([$fid, $b['ativo_codigo'], $dataSnap]);
    $pos = $st->fetch();
    if ($b['operacao'] === 'Compra') {
        if ($pos) {
            $novaQtd = (float)$pos['quantidade'] + (float)$b['quantidade'];
            $novoPm = ((float)$pos['quantidade'] * (float)$pos['preco_medio'] + (float)$b['quantidade'] * (float)$b['preco']) / $novaQtd;
            $pdo->prepare('UPDATE ativos_carteira SET quantidade = ?, preco_medio = ? WHERE id = ?')
                ->execute([$novaQtd, $novoPm, $pos['id']]);
        } else {
            $fonte = match ($b['tipo_ativo']) {
                'Ação', 'Cota de Fundo' => 'B3',
                'CDB' => 'Comitê',
                default => 'ANBIMA',
            };
            $pdo->prepare('INSERT INTO ativos_carteira (fundo_id, codigo, tipo, quantidade, preco_medio, preco_mam, preco_referencia, fonte_preco, data_ref)
                           VALUES (?,?,?,?,?,?,?,?,?)')
                ->execute([$fid, $b['ativo_codigo'], $b['tipo_ativo'], $b['quantidade'],
                           $b['preco'], $b['preco'], $b['preco'], $fonte, $dataSnap]);
        }
    } else { // Venda
        if ($pos) {
            $novaQtd = (float)$pos['quantidade'] - (float)$b['quantidade'];
            if ($novaQtd <= 0.0001) $pdo->prepare('DELETE FROM ativos_carteira WHERE id = ?')->execute([$pos['id']]);
            else $pdo->prepare('UPDATE ativos_carteira SET quantidade = ? WHERE id = ?')->execute([$novaQtd, $pos['id']]);
        }
    }
    $pdo->prepare("INSERT INTO log_processamento (fundo_id, data_ref, etapa, nivel, mensagem) VALUES (?,?,?,?,?)")
        ->execute([$fid, date('Y-m-d'), 'Posição', 'INFO',
                   "Boleta liquidada: {$b['operacao']} de {$b['quantidade']} {$b['ativo_codigo']} refletida na carteira — reprocessar a cota do dia"]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_validar()) {
    $_POST = [];
    $msg = 'Requisição inválida (proteção CSRF). Recarregue a página e tente novamente.';
    $msgTipo = 'danger';
}

/** Segmento de liquidação do instrumento: 'tpf' (Selic), 'bolsa' (Câmara B3/CCP) ou 'balcao' (NoMe). */
function segmento_liq(?string $tipoAtivo, string $codigo): string {
    if ($tipoAtivo !== null) {
        if ($tipoAtivo === 'Título Público') return 'tpf';
        return in_array($tipoAtivo, ['Ação', 'ETF', 'Cota de Fundo'], true) ? 'bolsa' : 'balcao';
    }
    if (preg_match('/^(LFT|LTN|NTN)/', $codigo)) return 'tpf';
    return preg_match('/^[A-Z]{4}\d{1,2}$/', $codigo) ? 'bolsa' : 'balcao';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // aceite de boleta do gestor → comando da ponta, conforme o segmento:
    //  · TPF: operação definitiva no Selic (SEL1052) — LBTR/DVP em tempo real, D+0 padrão;
    //  · balcão: duplo comando no NoMe (tipo de operação 052) — casa quando a contraparte comanda;
    //  · bolsa: confirmação de alocação — liquida pelo saldo líquido na janela da Câmara B3 (D+2).
    if (!empty($_POST['aceitar_boleta'])) {
        $st = $pdo->prepare("SELECT * FROM boletas WHERE id = ? AND status = 'Enviada'");
        $st->execute([(int)$_POST['aceitar_boleta']]);
        if ($b = $st->fetch()) {
            $seg = segmento_liq($b['tipo_ativo'], $b['ativo_codigo']);
            // data pactuada na boleta manda; fallback: bolsa D+2, TPF D+0, balcão D+0
            $dataLiq = $b['data_liquidacao_prevista'] ?? null;
            if (!$dataLiq) {
                $dias = $seg === 'bolsa' ? 2 : 0;
                $dl = new DateTime($b['data_operacao']);
                $n = $dias;
                while ($n > 0) { $dl->modify('+1 day'); if ((int)$dl->format('N') < 6) $n--; }
                $dataLiq = $dl->format('Y-m-d');
            }
            $pdo->prepare("INSERT INTO liquidacoes (fundo_id, data_operacao, ativo_codigo, operacao, quantidade, valor, data_liquidacao, contraparte, status, boleta_id)
                           VALUES (?,?,?,?,?,?,?,?, 'Pendente', ?)")
                ->execute([$b['fundo_id'], $b['data_operacao'], $b['ativo_codigo'], $b['operacao'],
                           $b['quantidade'], $b['valor'], $dataLiq, $b['contraparte'], $b['id']]);
            $liqId = (int)$pdo->lastInsertId();
            $pdo->prepare("UPDATE boletas SET status='Aceita', liquidacao_id=? WHERE id=?")->execute([$liqId, $b['id']]);
            if ($seg === 'tpf') {
                $pdo->prepare("INSERT INTO mensagens_spb (central, codigo, fundo_id, referencia, descricao, valor, status, recebida_em)
                               VALUES ('SELIC','SEL1052',?,?,?,?,'Recebida',NOW())")
                    ->execute([$b['fundo_id'], 'BOL-' . $b['id'],
                               "Participante requisita operação definitiva — {$b['operacao']} {$b['ativo_codigo']} (DVP LBTR, liq. " . data_br($dataLiq) . ')',
                               (float)$b['valor']]);
                $msg = 'Operação definitiva comandada no Selic (SEL1052) — DVP em tempo real na data pactuada.';
            } elseif ($seg === 'balcao') {
                $mod = $b['modalidade_liq'] ?: 'Bruta (DVP via STR)';
                $pdo->prepare("INSERT INTO mensagens_spb (central, codigo, fundo_id, referencia, descricao, valor, status, recebida_em)
                               VALUES ('B3 Balcão','052',?,?,?,?,'Recebida',NOW())")
                    ->execute([$b['fundo_id'], 'BOL-' . $b['id'],
                               "Comando da ponta registrado no NoMe (tipo 052 — compra/venda definitiva; duplo comando) — $mod, liq. " . data_br($dataLiq),
                               (float)$b['valor']]);
                $msg = 'Ponta comandada no balcão (duplo comando) — a operação casa quando a contraparte comandar a dela.';
            } else {
                // bolsa: alocação não trafega pela RSFN — a câmara casa e liquida pelo saldo líquido
                $pdo->prepare("INSERT INTO log_processamento (fundo_id, data_ref, etapa, nivel, mensagem) VALUES (?,?,?,?,?)")
                    ->execute([$b['fundo_id'], date('Y-m-d'), 'Posição', 'INFO',
                               "Alocação de {$b['operacao']} {$b['ativo_codigo']} confirmada — liquida pelo saldo líquido multilateral na janela da Câmara B3 (D+2, " . data_br($dataLiq) . ')']);
                $msg = 'Alocação confirmada — liquidação pelo saldo líquido na janela da Câmara B3 (CCP) em D+2.';
            }
            registrar_auditoria($pdo, 'boleta_aceita', ['entidade' => 'boleta', 'entidade_id' => $b['id'], 'fundo_id' => $b['fundo_id'], 'detalhe' => "Boleta {$b['operacao']} {$b['ativo_codigo']} — comando/alocação ($seg), liq. $dataLiq (liq #$liqId)"]);
        }
    } elseif (!empty($_POST['rejeitar_boleta']) && trim($_POST['motivo'] ?? '') !== '') {
        $pdo->prepare("UPDATE boletas SET status='Rejeitada', motivo=? WHERE id=? AND status='Enviada'")
            ->execute([trim($_POST['motivo']), (int)$_POST['rejeitar_boleta']]);
        registrar_auditoria($pdo, 'boleta_rejeitada', ['entidade' => 'boleta', 'entidade_id' => (int)$_POST['rejeitar_boleta'], 'detalhe' => 'Boleta rejeitada: ' . trim($_POST['motivo'])]);
        $msg = 'Boleta rejeitada — o gestor vê o motivo no portal dele.'; $msgTipo = 'warning';
    }
    elseif (!empty($_POST['liquidar'])) {
        $st = $pdo->prepare("SELECT * FROM liquidacoes WHERE id = ? AND status IN ('Pendente','Falha')");
        $st->execute([(int)$_POST['liquidar']]);
        if ($l = $st->fetch()) {
            // o ciclo é respeitado: não se liquida antes da data pactuada (a câmara/Selic também não liquidaria)
            if ($l['data_liquidacao'] > date('Y-m-d') && $l['status'] !== 'Falha') {
                $msg = 'Ainda não é a data de liquidação (' . data_br($l['data_liquidacao']) . ') — a instrução aguarda o ciclo.';
                $msgTipo = 'warning';
            } else {
            $sinal = $l['operacao'] === 'Compra' ? -1 : 1;
            // busca a boleta antes (para saber o segmento e a modalidade)
            $b = null;
            if (!empty($l['boleta_id'])) {
                $stb = $pdo->prepare('SELECT * FROM boletas WHERE id = ?');
                $stb->execute([(int)$l['boleta_id']]);
                $b = $stb->fetch() ?: null;
            }
            $seg = segmento_liq($b['tipo_ativo'] ?? null, $l['ativo_codigo']);
            com_transacao($pdo, function () use ($pdo, $l, $u, $sinal, $b, $seg) {
            $pdo->prepare("UPDATE liquidacoes SET status='Liquidada', confirmado_por=?, confirmado_em=NOW() WHERE id=?")
                ->execute([$u['nome'] . ' (custódia)', $l['id']]);
            // data contábil = data da liquidação (não a data do clique)
            $dataContabil = min($l['data_liquidacao'], date('Y-m-d'));
            $pdo->prepare("INSERT INTO movimentacoes (fundo_id, data_ref, tipo, descricao, valor) VALUES (?,?,?,?,?)")
                ->execute([$l['fundo_id'], $dataContabil,
                           $l['operacao'] === 'Compra' ? 'Liquidação Compra' : 'Liquidação Venda',
                           "DVP {$l['operacao']} {$l['ativo_codigo']} liquidada pelo custodiante",
                           $sinal * (float)$l['valor']]);
            $pdo->prepare('UPDATE fundos SET caixa_atual = caixa_atual + ? WHERE id = ?')
                ->execute([$sinal * (float)$l['valor'], $l['fundo_id']]);
            // mensagem/registro da perna financeira conforme o segmento (códigos reais do catálogo do SFN)
            if ($seg === 'tpf') {
                $pdo->prepare("INSERT INTO mensagens_spb (central, codigo, fundo_id, referencia, descricao, valor, status, recebida_em, processada_em, processada_por)
                               VALUES ('SELIC','SEL1099',?,?,?,?,'Processada',NOW(),NOW(),?)")
                    ->execute([$l['fundo_id'], 'LIQ-' . $l['id'],
                               "SEL informa movimentação financeira — DVP {$l['operacao']} {$l['ativo_codigo']} concluída (LBTR)",
                               (float)$l['valor'], $u['nome']]);
            } elseif ($seg === 'bolsa') {
                $pdo->prepare("INSERT INTO mensagens_spb (central, codigo, fundo_id, referencia, descricao, valor, status, recebida_em, processada_em, processada_por)
                               VALUES ('B3 Depositária','LDL0005',?,?,?,?,'Processada',NOW(),NOW(),?)")
                    ->execute([$l['fundo_id'], 'LIQ-' . $l['id'],
                               "Câmara B3 paga participantes credores — saldo líquido multilateral da janela (piloto liquida por operação) · {$l['ativo_codigo']}",
                               (float)$l['valor'], $u['nome']]);
            } else {
                $mod = $b['modalidade_liq'] ?? 'Bruta (DVP via STR)';
                if (str_starts_with($mod, 'Bruta')) {
                    $pdo->prepare("INSERT INTO mensagens_spb (central, codigo, fundo_id, referencia, descricao, valor, status, recebida_em, processada_em, processada_por)
                                   VALUES ('STR','STR0004',?,?,?,?,'Processada',NOW(),NOW(),?)")
                        ->execute([$l['fundo_id'], 'LIQ-' . $l['id'],
                                   "IF requisita transferência para IF — perna financeira bruta (banco liquidante) · {$l['operacao']} {$l['ativo_codigo']}",
                                   (float)$l['valor'], $u['nome']]);
                } else {
                    $pdo->prepare("INSERT INTO log_processamento (fundo_id, data_ref, etapa, nivel, mensagem) VALUES (?,?,?,?,?)")
                        ->execute([$l['fundo_id'], date('Y-m-d'), 'Posição', 'INFO',
                                   "Liquidação $mod de {$l['ativo_codigo']} — perna financeira fora da mensageria (bilateral/livre de pagamento)"]);
                }
            }
            // se a instrução veio de boleta do gestor: a posição entra/sai da carteira de verdade
            if ($b) {
                aplicar_boleta_na_carteira($pdo, $b);
                $pdo->prepare("UPDATE boletas SET status='Liquidada' WHERE id=?")->execute([$b['id']]);
            }
            });
            $msg = 'Liquidação DVP confirmada — caixa movimentado' .
                   (!empty($l['boleta_id']) ? ', posição refletida na carteira (reprocessar a cota do dia)' : '') .
                   ' e a perna financeira registrada.';
            registrar_auditoria($pdo, 'liquidacao_dvp', ['entidade' => 'liquidacao', 'entidade_id' => $l['id'], 'fundo_id' => $l['fundo_id'], 'detalhe' => "DVP {$l['operacao']} {$l['ativo_codigo']} liquidada (" . moeda($l['valor']) . ", $seg)"]);
            }
        }
    } elseif (!empty($_POST['provisionar_evento'])) {
        $pdo->prepare("UPDATE eventos_corporativos SET status='Provisionado', processado_por=?, processado_em=NOW()
                       WHERE id=? AND status='Anunciado'")->execute([$u['nome'] . ' (custódia)', (int)$_POST['provisionar_evento']]);
        registrar_auditoria($pdo, 'evento_provisionado', ['entidade' => 'evento', 'entidade_id' => (int)$_POST['provisionar_evento']]);
        $msg = 'Evento provisionado — a administradora passa a refletir o direito no PL.';
    } elseif (!empty($_POST['liquidar_evento'])) {
        $st = $pdo->prepare("SELECT * FROM eventos_corporativos WHERE id = ? AND status = 'Provisionado'");
        $st->execute([(int)$_POST['liquidar_evento']]);
        if ($ev = $st->fetch()) {
            creditar_evento_corporativo($pdo, $ev);   // amortização baixa principal; bonificação ajusta qtd; provento credita
            registrar_auditoria($pdo, 'evento_creditado', ['entidade' => 'evento', 'entidade_id' => $ev['id'], 'fundo_id' => $ev['fundo_id'], 'detalhe' => "{$ev['tipo']} {$ev['ativo_codigo']} processado"]);
            $msg = "{$ev['tipo']} de {$ev['ativo_codigo']} processado" .
                   ($ev['tipo'] === 'Amortização' ? ' — principal devolvido e PU reduzido'
                   : (in_array($ev['tipo'], ['Bonificação', 'Desdobramento'], true) ? ' — quantidade ajustada'
                   : ' — ' . moeda($ev['valor_total']) . ' creditado')) . '.';
        }
    }
}

$boletasNovas = $pdo->query("SELECT b.*, f.nome fundo_nome FROM boletas b JOIN fundos f ON f.id=b.fundo_id
                             WHERE b.status = 'Enviada' ORDER BY b.criado_em")->fetchAll();

$liquidacoes = $pdo->query("SELECT l.*, f.nome fundo_nome FROM liquidacoes l JOIN fundos f ON f.id=l.fundo_id
                            ORDER BY FIELD(l.status,'Falha','Pendente','Liquidada'), l.data_liquidacao")->fetchAll();
$eventos = $pdo->query("SELECT e.*, f.nome fundo_nome FROM eventos_corporativos e JOIN fundos f ON f.id=e.fundo_id
                        ORDER BY FIELD(e.status,'Anunciado','Provisionado','Liquidado'), e.data_pagamento")->fetchAll();

page_start('Instruções & Liquidação', 'Instruções & Liquidação', $u,
    'Entrega contra pagamento (DVP): o ativo só muda de mãos se o financeiro liquidar — e vice-versa');
?>

<?php if ($msg): ?><div class="alert alert-<?= $msgTipo ?> py-2"><i class="bi bi-check-circle me-1"></i><?= e_html($msg) ?></div><?php endif; ?>

<?php if ($boletasNovas): ?>
<div class="card mb-4 border-warning">
  <div class="card-header" style="background:#fffbeb"><i class="bi bi-receipt-cutoff me-1"></i>
    Boletas recebidas dos gestores — validar e gerar instrução de liquidação</div>
  <div class="card-body p-0">
    <table class="table align-middle mb-0" style="font-size:.84rem">
      <thead><tr><th>Fundo / Operação</th><th class="text-end">Qtde × Preço</th><th class="text-end">Financeiro</th>
        <th>Contraparte</th><th>Boletada por</th><th style="min-width:300px"></th></tr></thead>
      <tbody>
      <?php foreach ($boletasNovas as $b): ?>
        <tr>
          <td><b><?= e_html($b['fundo_nome']) ?></b><br>
            <?= badge($b['operacao'], $b['operacao'] === 'Compra' ? 'warning' : 'success') ?>
            <?= e_html($b['ativo_codigo']) ?> <span class="text-muted" style="font-size:.72rem">(<?= e_html($b['tipo_ativo']) ?>) · op. <?= data_br($b['data_operacao']) ?></span></td>
          <td class="text-end"><?= number_format((float)$b['quantidade'], 0, ',', '.') ?> × <?= number_format((float)$b['preco'], 4, ',', '.') ?></td>
          <td class="text-end"><b><?= moeda($b['valor']) ?></b></td>
          <td style="font-size:.8rem"><?= e_html($b['contraparte']) ?>
            <?php if (!empty($b['data_liquidacao_prevista']) || !empty($b['modalidade_liq'])): ?>
              <br><span class="text-muted" style="font-size:.7rem">
                <?= !empty($b['data_liquidacao_prevista']) ? 'liq. pactuada ' . data_br($b['data_liquidacao_prevista']) : '' ?>
                <?= !empty($b['modalidade_liq']) ? ' · ' . e_html($b['modalidade_liq']) : '' ?>
                <?= !empty($b['taxa_negociada']) ? ' · ' . e_html($b['taxa_negociada']) : '' ?></span>
            <?php endif; ?></td>
          <td style="font-size:.78rem"><?= e_html($b['criado_por']) ?><br>
            <span class="text-muted" style="font-size:.7rem"><?= date('d/m H:i', strtotime($b['criado_em'])) ?></span></td>
          <td>
            <?php $segB = segmento_liq($b['tipo_ativo'], $b['ativo_codigo']); ?>
            <form method="post" class="d-inline">
              <?= csrf_campo() ?><input type="hidden" name="aceitar_boleta" value="<?= (int)$b['id'] ?>">
              <button class="btn btn-sm btn-success"><i class="bi bi-check-lg me-1"></i><?=
                $segB === 'tpf' ? 'Comandar no Selic (LBTR)'
                : ($segB === 'balcao' ? 'Comandar ponta (duplo comando)'
                : 'Confirmar alocação (Câmara B3, D+2)') ?></button>
            </form>
            <form method="post" class="d-flex gap-1 mt-1">
              <?= csrf_campo() ?><input type="hidden" name="rejeitar_boleta" value="<?= (int)$b['id'] ?>">
              <input class="form-control form-control-sm" name="motivo" placeholder="motivo da rejeição…" required>
              <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="card mb-4">
  <div class="card-header"><i class="bi bi-arrow-left-right me-1"></i> Instruções de movimentação (liquidação física + financeira)</div>
  <div class="card-body p-0">
    <table class="table table-hover align-middle mb-0" style="font-size:.84rem">
      <thead><tr><th>Fundo / Operação</th><th class="text-end">Financeiro</th><th>Ciclo</th><th>Contraparte</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($liquidacoes as $l): ?>
        <tr class="<?= $l['status'] === 'Falha' ? 'table-danger' : ($l['status'] === 'Liquidada' ? 'text-muted' : '') ?>">
          <td><b><?= e_html($l['fundo_nome']) ?></b><br>
            <?= badge($l['operacao'], $l['operacao'] === 'Compra' ? 'warning' : 'success') ?>
            <?= e_html($l['ativo_codigo']) ?> · <?= number_format((float)$l['quantidade'], 0, ',', '.') ?> un.</td>
          <td class="text-end"><?= moeda($l['valor']) ?></td>
          <td style="font-size:.78rem"><?= data_br($l['data_operacao']) ?> → <?= data_br($l['data_liquidacao']) ?></td>
          <td style="font-size:.8rem"><?= e_html($l['contraparte']) ?></td>
          <td><?= badge_status($l['status'] === 'Liquidada' ? 'OK' : ($l['status'] === 'Falha' ? 'Erro' : 'Pendente')) ?>
            <?= $l['status'] === 'Falha' ? '<br><span class="text-danger" style="font-size:.7rem">falha de entrega — na B3 real: empréstimo compulsório → multa 0,5% (teto R$ 50 mil) → recompra em D+1; no balcão bruta, estorno após 30 min</span>' : '' ?></td>
          <td class="text-end">
            <?php if (in_array($l['status'], ['Pendente', 'Falha'], true)): ?>
              <?php if ($l['status'] === 'Pendente' && $l['data_liquidacao'] > date('Y-m-d')): ?>
                <span class="text-muted" style="font-size:.72rem"><i class="bi bi-hourglass-split me-1"></i>aguarda o ciclo (liq. <?= data_br($l['data_liquidacao']) ?>)</span>
              <?php else: ?>
              <form method="post" onsubmit="return confirm('Confirmar a liquidação DVP? O caixa do fundo será movimentado.')">
                <?= csrf_campo() ?><input type="hidden" name="liquidar" value="<?= (int)$l['id'] ?>">
                <button class="btn btn-sm <?= $l['status'] === 'Falha' ? 'btn-danger' : 'btn-success' ?>">
                  <i class="bi bi-check-lg me-1"></i><?= $l['status'] === 'Falha' ? 'Reliquidar' : 'Liquidar DVP' ?></button>
              </form>
              <?php endif; ?>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer text-muted" style="font-size:.72rem">
    <b>Grades reais de horário:</b> Selic 6h30–18h30 (LBTR/DVP em tempo real) · TED de cliente no STR até 17h30 ·
    Câmara B3: pagamentos dos devedores 14h10–14h50, DVP final ~15h50 (saldo líquido multilateral) · alocação de RV até 15h.
    No piloto o relógio é o do Simulator Master — a grade é informativa.
  </div>
</div>

<div class="card">
  <div class="card-header"><i class="bi bi-calendar-event me-1"></i> Eventos dos ativos custodiados (cobrança e crédito de proventos)</div>
  <div class="card-body p-0">
    <table class="table table-hover align-middle mb-0" style="font-size:.84rem">
      <thead><tr><th>Fundo / Evento</th><th class="text-end">Valor</th><th>Pagamento</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($eventos as $ev): ?>
        <tr class="<?= $ev['status'] === 'Liquidado' ? 'text-muted' : '' ?>">
          <td><b><?= e_html($ev['fundo_nome']) ?></b> · <?= badge($ev['tipo'], 'info') ?> <?= e_html($ev['ativo_codigo']) ?></td>
          <td class="text-end"><?= moeda($ev['valor_total']) ?></td>
          <td><?= data_br($ev['data_pagamento']) ?></td>
          <td><?= badge($ev['status'], $ev['status'] === 'Liquidado' ? 'success' : ($ev['status'] === 'Provisionado' ? 'info' : 'warning')) ?></td>
          <td class="text-end">
            <?php if ($ev['status'] === 'Anunciado'): ?>
              <form method="post"><?= csrf_campo() ?><input type="hidden" name="provisionar_evento" value="<?= (int)$ev['id'] ?>">
                <button class="btn btn-sm btn-outline-primary"><i class="bi bi-journal-plus me-1"></i>Provisionar</button></form>
            <?php elseif ($ev['status'] === 'Provisionado'): ?>
              <form method="post"><?= csrf_campo() ?><input type="hidden" name="liquidar_evento" value="<?= (int)$ev['id'] ?>">
                <button class="btn btn-sm btn-success"><i class="bi bi-cash-coin me-1"></i>Creditar</button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer text-muted" style="font-size:.72rem">
    As mesmas filas aparecem na visão da administradora (Custódia & Liquidação) — na vida real são duas pontas do
    mesmo processo: o custodiante executa, a administradora concilia e reflete na cota.
  </div>
</div>
<?php page_end();
