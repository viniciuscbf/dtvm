<?php
// Custódia & Liquidação (visão da ADMINISTRADORA) — página SOMENTE-LEITURA.
// Segregação real (Res. CVM 32): liquidar DVP, provisionar e creditar eventos são atos
// PRIVATIVOS do custodiante (portal /custodia/). A administradora enxerga as filas,
// CONCILIA e reflete na cota — quem produz a posição não é quem a valida.
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

$u = exigir_perfil('admin');
$msg = ''; $msgTipo = 'success';

$liquidacoes = $pdo->query("SELECT l.*, f.nome fundo_nome FROM liquidacoes l JOIN fundos f ON f.id=l.fundo_id
                            ORDER BY FIELD(l.status,'Pendente','Falha','Liquidada'), l.data_liquidacao")->fetchAll();
$pendLiq = array_filter($liquidacoes, fn($l) => $l['status'] === 'Pendente');

$eventos = $pdo->query("SELECT e.*, f.nome fundo_nome FROM eventos_corporativos e JOIN fundos f ON f.id=e.fundo_id
                        ORDER BY FIELD(e.status,'Anunciado','Provisionado','Liquidado'), e.data_pagamento")->fetchAll();
$pendEv = array_filter($eventos, fn($e) => $e['status'] !== 'Liquidado');

// espelho da posição custodiada (snapshot mais recente por fundo); divergência do Atlas destacada
$fundos = $pdo->query("SELECT * FROM fundos WHERE status='Ativo' ORDER BY pl_atual DESC")->fetchAll();
$fidSel = (int)($_GET['fundo_id'] ?? ($fundos[0]['id'] ?? 0));
$fundoSel = null;
foreach ($fundos as $f) if ((int)$f['id'] === $fidSel) $fundoSel = $f;
if (!$fundoSel && $fundos) { $fundoSel = $fundos[0]; $fidSel = (int)$fundoSel['id']; }
$posicao = $fundoSel ? carteira($pdo, $fidSel) : [];

// posição do custodiante — fonte INDEPENDENTE (gerada no avanço de dia do simulador)
ensure_posicao_custodiante($pdo);
$pcData = $pdo->prepare("SELECT MAX(data_ref) FROM posicao_custodiante WHERE fundo_id=?");
$pcData->execute([$fidSel]);
$pcData = $pcData->fetchColumn();
$posCust = [];
if ($pcData) {
    $st = $pdo->prepare("SELECT codigo, quantidade FROM posicao_custodiante WHERE fundo_id=? AND data_ref=?");
    $st->execute([$fidSel, $pcData]);
    foreach ($st->fetchAll() as $r) $posCust[$r['codigo']] = (float)$r['quantidade'];
}
// divergências reais do fundo (carteira × posição do custodiante)
$nDivPosicao = 0;
foreach ($posicao as $a) {
    $qc = array_key_exists($a['codigo'], $posCust) ? $posCust[$a['codigo']] : (float)$a['quantidade'];
    if (abs($qc - (float)$a['quantidade']) > 0.0001) $nDivPosicao++;
}

$totCustodiado = 0.0;
foreach ($fundos as $f) {
    foreach (carteira($pdo, (int)$f['id']) as $a) $totCustodiado += $a['valor_mercado'];
}

page_start('Custódia & Liquidação', 'Custódia & Liquidação', $u,
    'Guarda dos ativos, liquidação física/financeira e eventos corporativos — as funções do custodiante dentro do banco');
?>

<?php if ($msg): ?><div class="alert alert-<?= $msgTipo ?> py-2"><i class="bi bi-info-circle me-1"></i><?= e_html($msg) ?></div><?php endif; ?>

<div class="row row-cols-2 row-cols-md-4 g-3 mb-4">
  <?= kpi('Posição custodiada', moeda_compacta($totCustodiado), 'bi-safe2', count($fundos) . ' fundos') ?>
  <?= kpi('Liquidações pendentes', (string)count($pendLiq), 'bi-arrow-left-right',
          count($pendLiq) ? moeda_compacta(array_sum(array_map(fn($l) => (float)$l['valor'], $pendLiq))) . ' a liquidar' : 'fila limpa') ?>
  <?= kpi('Eventos corporativos', count($pendEv) . ' a processar', 'bi-calendar-event') ?>
  <?= kpi('Divergências de posição', $nDivPosicao > 0 ? $nDivPosicao . ' no fundo selecionado' : 'nenhuma no fundo', 'bi-exclamation-diamond') ?>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-arrow-left-right me-1"></i> Fila de liquidação física e financeira</div>
      <div class="card-body p-0" style="max-height:380px;overflow-y:auto">
        <table class="table table-hover align-middle mb-0" style="font-size:.82rem">
          <thead><tr><th>Fundo / Operação</th><th class="text-end">Financeiro</th><th>Liquida em</th><th>Status</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($liquidacoes as $l): ?>
            <tr class="<?= $l['status'] === 'Pendente' ? '' : 'text-muted' ?>">
              <td><b><?= e_html($l['fundo_nome']) ?></b><br>
                <?= badge($l['operacao'], $l['operacao'] === 'Compra' ? 'warning' : 'success') ?>
                <?= e_html($l['ativo_codigo']) ?> · <?= number_format((float)$l['quantidade'], 0, ',', '.') ?> un.
                <span class="text-muted" style="font-size:.72rem">· <?= e_html($l['contraparte']) ?></span></td>
              <td class="text-end"><?= moeda($l['valor']) ?></td>
              <td><?= data_br($l['data_liquidacao']) ?><br>
                <span class="text-muted" style="font-size:.7rem">boleta de <?= data_br($l['data_operacao']) ?></span></td>
              <td><?= badge_status($l['status'] === 'Liquidada' ? 'OK' : $l['status']) ?>
                <?= $l['confirmado_por'] ? '<br><span class="text-muted" style="font-size:.68rem">' . e_html($l['confirmado_por']) . '</span>' : '' ?></td>
              <td class="text-end">
                <?php if ($l['status'] === 'Pendente'): ?>
                  <span class="text-muted" style="font-size:.7rem"><i class="bi bi-lock me-1"></i>liquida no portal de custódia</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$liquidacoes): ?><tr><td colspan="5" class="text-muted text-center py-4">Sem operações na fila.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="card-footer text-muted" style="font-size:.72rem">Ações: D+2 pela Câmara B3 (saldo líquido multilateral). Títulos públicos: Selic liquida LBTR/DVP em tempo real (D+0). Balcão: data pactuada (D+0/D+1). A execução é do custodiante — aqui a administradora acompanha e concilia.</div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-calendar-event me-1"></i> Eventos corporativos (anunciar → provisionar → liquidar)</div>
      <div class="card-body p-0" style="max-height:380px;overflow-y:auto">
        <table class="table table-hover align-middle mb-0" style="font-size:.82rem">
          <thead><tr><th>Fundo / Evento</th><th class="text-end">Valor</th><th>Pagamento</th><th>Status</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($eventos as $ev): ?>
            <tr class="<?= $ev['status'] === 'Liquidado' ? 'text-muted' : '' ?>">
              <td><b><?= e_html($ev['fundo_nome']) ?></b><br>
                <?= badge($ev['tipo'], 'info') ?> <?= e_html($ev['ativo_codigo']) ?>
                <?php if ($ev['valor_por_unidade']): ?><span class="text-muted" style="font-size:.72rem">· R$ <?= number_format((float)$ev['valor_por_unidade'], 4, ',', '.') ?>/un.</span><?php endif; ?>
                <?php if ($ev['data_ex']): ?><br><span class="text-muted" style="font-size:.7rem">ex: <?= data_br($ev['data_ex']) ?></span><?php endif; ?></td>
              <td class="text-end"><?= moeda($ev['valor_total']) ?></td>
              <td><?= data_br($ev['data_pagamento']) ?></td>
              <td><?= badge($ev['status'], $ev['status'] === 'Liquidado' ? 'success' : ($ev['status'] === 'Provisionado' ? 'info' : 'warning')) ?></td>
              <td class="text-end">
                <?php if ($ev['status'] !== 'Liquidado'): ?>
                  <span class="text-muted" style="font-size:.7rem"><i class="bi bi-lock me-1"></i>processa no portal de custódia</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$eventos): ?><tr><td colspan="5" class="text-muted text-center py-4">Sem eventos anunciados.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="card-footer text-muted" style="font-size:.72rem">O custodiante cobra e recebe os proventos dos ativos custodiados: provisionar reconhece o direito (afeta a cota); liquidar credita o caixa na data de pagamento.</div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-safe2 me-1"></i> Espelho da posição custodiada (o que a custódia enxerga)</span>
    <form method="get">
      <select class="form-select form-select-sm" name="fundo_id" onchange="this.form.submit()">
        <?php foreach ($fundos as $f): ?>
          <option value="<?= (int)$f['id'] ?>" <?= (int)$f['id'] === $fidSel ? 'selected' : '' ?>><?= e_html($f['nome']) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0" style="font-size:.84rem">
      <thead><tr><th>Ativo</th><th>Local de guarda</th><th class="text-end">Qtde na carteira</th>
        <th class="text-end">Qtde custodiada</th><th class="text-center">Batimento</th></tr></thead>
      <tbody>
      <?php foreach ($posicao as $a):
          $qtdCust = array_key_exists($a['codigo'], $posCust) ? $posCust[$a['codigo']] : (float)$a['quantidade'];
          $divergente = abs($qtdCust - (float)$a['quantidade']) > 0.0001;   // batimento real contra a posição do custodiante
          $local = in_array($a['tipo'], ['Título Público'], true) ? 'SELIC'
                 : (in_array($a['tipo'], ['Debênture', 'CDB', 'CRI/CRA'], true) ? 'B3/Cetip' : 'B3 (Central Depositária)'); ?>
        <tr class="<?= $divergente ? 'table-danger' : '' ?>">
          <td><b><?= e_html($a['codigo']) ?></b> <?= badge($a['tipo'], 'secondary') ?></td>
          <td style="font-size:.8rem"><?= $local ?></td>
          <td class="text-end"><?= number_format((float)$a['quantidade'], 0, ',', '.') ?></td>
          <td class="text-end"><?= number_format($qtdCust, 0, ',', '.') ?></td>
          <td class="text-center"><?= $divergente
              ? '<span class="text-danger"><i class="bi bi-x-circle-fill"></i> sem lastro p/ ' . number_format((float)$a['quantidade'] - $qtdCust, 0, ',', '.') . ' un. — <a href="conciliacao.php">conciliação</a></span>'
              : '<i class="bi bi-check-circle-fill text-success"></i>' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer text-muted" style="font-size:.72rem">
    A guarda é segregada por fundo nas centrais depositárias (SELIC para títulos públicos, B3 para ações e crédito privado).
    O batimento diário carteira × custódia alimenta a Conciliação; diferença sem lastro vira alerta de IA (R5 — conciliação de custódia).
  </div>
</div>
<?php page_end();
