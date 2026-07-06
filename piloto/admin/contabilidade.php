<?php
// Contabilidade do fundo — balancete, DRE, razão e diário (dupla entrada).
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/contabilidade.php';

$u = exigir_perfil('admin');

$fundos = $pdo->query("SELECT * FROM fundos WHERE status='Ativo' ORDER BY pl_atual DESC")->fetchAll();
if (isset($_GET['fundo_id'])) $_SESSION['admin_fundo_id'] = (int)$_GET['fundo_id'];
$fid = (int)($_SESSION['admin_fundo_id'] ?? ($fundos[0]['id'] ?? 0));
$fundo = null;
foreach ($fundos as $f) if ((int)$f['id'] === $fid) $fundo = $f;
if (!$fundo && $fundos) { $fundo = $fundos[0]; $fid = (int)$fundo['id']; }

$datas = $fundo ? datas_carteira($pdo, $fid) : [];
$ate = $_GET['data'] ?? ($datas[0] ?? date('Y-m-d'));
if ($datas && !in_array($ate, $datas, true)) $ate = $datas[0];

$bal = $fundo ? balancete($pdo, $fundo, $ate) : null;
$diario = $fundo ? diario_contabil($pdo, $fid, $ate) : [];
$razao = $fundo ? razao_contabil($pdo, $fid, $ate) : [];

page_start('Contabilidade', 'Contabilidade', $u,
    'Balancete, demonstração de resultado, razão e diário (partidas dobradas) — a controladoria contábil do fundo');
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <form method="get" class="d-flex gap-2">
    <select name="fundo_id" class="form-select form-select-sm" onchange="this.form.submit()">
      <?php foreach ($fundos as $f): ?>
        <option value="<?= (int)$f['id'] ?>" <?= (int)$f['id'] === $fid ? 'selected' : '' ?>><?= e_html($f['nome']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="data" class="form-select form-select-sm" onchange="this.form.submit()">
      <?php foreach ($datas as $d): ?>
        <option value="<?= $d ?>" <?= $d === $ate ? 'selected' : '' ?>><?= data_br($d) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <?php if ($bal): ?>
    <span style="font-size:.82rem"><?= $bal['confere']
      ? '<span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>Balancete confere (Ativo = Passivo + PL)</span>'
      : '<span class="text-danger"><i class="bi bi-x-circle-fill me-1"></i>Balancete não fecha</span>' ?></span>
  <?php endif; ?>
</div>

<?php if ($bal): ?>
<div class="row g-3 mb-4">
  <!-- Balanço patrimonial -->
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-clipboard-data me-1"></i> Balancete patrimonial — <?= data_br($ate) ?></div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0" style="font-size:.85rem">
          <tbody>
            <tr class="table-light"><th colspan="2">ATIVO</th></tr>
            <tr><td>1.1 <?= PLANO_CONTAS['1.1'][0] ?></td><td class="text-end"><?= moeda($bal['ativo']['1.1']) ?></td></tr>
            <tr><td>1.2 <?= PLANO_CONTAS['1.2'][0] ?></td><td class="text-end"><?= moeda($bal['ativo']['1.2']) ?></td></tr>
            <tr class="fw-bold"><td>Total do Ativo</td><td class="text-end"><?= moeda($bal['ativo']['total']) ?></td></tr>
            <tr class="table-light"><th colspan="2">PASSIVO E PATRIMÔNIO LÍQUIDO</th></tr>
            <tr><td>2.1 <?= PLANO_CONTAS['2.1'][0] ?></td><td class="text-end"><?= moeda($bal['passivo']['2.1']) ?></td></tr>
            <tr><td>2.2 <?= PLANO_CONTAS['2.2'][0] ?></td><td class="text-end"><?= moeda($bal['passivo']['2.2']) ?></td></tr>
            <tr><td>3.0 <?= PLANO_CONTAS['3.0'][0] ?></td><td class="text-end"><b><?= moeda($bal['pl']) ?></b></td></tr>
            <tr class="fw-bold"><td>Total Passivo + PL</td><td class="text-end"><?= moeda($bal['passivo']['total'] + $bal['pl']) ?></td></tr>
          </tbody>
        </table>
      </div>
      <div class="card-footer text-muted" style="font-size:.72rem">
        Caixa e Títulos vêm das fontes autoritativas (movimentação e carteira); o PL fecha por diferença. Simulação de dupla entrada derivada da movimentação.
      </div>
    </div>
  </div>

  <!-- DRE + Razão -->
  <div class="col-lg-6 d-flex flex-column gap-3">
    <div class="card">
      <div class="card-header"><i class="bi bi-graph-up-arrow me-1"></i> Resultado acumulado (período)</div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0" style="font-size:.85rem">
          <tbody>
            <tr><td>(+) Receitas (proventos/rendimentos)</td><td class="text-end text-success"><?= moeda($bal['resultado']['receitas']) ?></td></tr>
            <tr><td>(−) Despesas (taxas)</td><td class="text-end text-danger"><?= moeda($bal['resultado']['despesas']) ?></td></tr>
            <tr class="fw-bold"><td>(=) Resultado líquido</td><td class="text-end <?= $bal['resultado']['liquido'] >= 0 ? 'text-success' : 'text-danger' ?>"><?= moeda($bal['resultado']['liquido']) ?></td></tr>
          </tbody>
        </table>
      </div>
    </div>
    <div class="card flex-grow-1">
      <div class="card-header"><i class="bi bi-journal-bookmark me-1"></i> Razão (débitos/créditos por conta)</div>
      <div class="card-body p-0" style="max-height:250px;overflow-y:auto">
        <table class="table table-sm table-hover mb-0" style="font-size:.82rem">
          <thead><tr><th>Conta</th><th class="text-end">Débito</th><th class="text-end">Crédito</th></tr></thead>
          <tbody>
          <?php foreach ($razao as $conta => $mov): if ($mov['debito'] == 0 && $mov['credito'] == 0) continue; ?>
            <tr><td><?= $conta ?> · <?= e_html(PLANO_CONTAS[$conta][0]) ?></td>
              <td class="text-end"><?= $mov['debito'] ? moeda($mov['debito']) : '—' ?></td>
              <td class="text-end"><?= $mov['credito'] ? moeda($mov['credito']) : '—' ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Diário -->
<div class="card">
  <div class="card-header"><i class="bi bi-journal-text me-1"></i> Diário — partidas dobradas (últimos lançamentos)</div>
  <div class="card-body p-0" style="max-height:420px;overflow-y:auto">
    <table class="table table-sm table-hover align-middle mb-0" style="font-size:.82rem">
      <thead><tr><th>Data</th><th>Histórico</th><th>D — Débito</th><th>C — Crédito</th><th class="text-end">Valor</th></tr></thead>
      <tbody>
      <?php foreach ($diario as $p): ?>
        <tr>
          <td class="text-muted" style="white-space:nowrap"><?= data_br($p['data']) ?></td>
          <td style="font-size:.8rem"><?= e_html($p['historico']) ?> <?= badge($p['tipo'], 'secondary') ?></td>
          <td style="font-size:.78rem"><?= $p['debito'] ?> · <?= e_html(PLANO_CONTAS[$p['debito']][0]) ?></td>
          <td style="font-size:.78rem"><?= $p['credito'] ?> · <?= e_html(PLANO_CONTAS[$p['credito']][0]) ?></td>
          <td class="text-end"><b><?= moeda($p['valor']) ?></b></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$diario): ?><tr><td colspan="5" class="text-muted text-center py-4">Sem lançamentos até a data.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer text-muted" style="font-size:.72rem">
    Cada movimento financeiro do fundo é traduzido em uma partida dobrada (uma conta debitada, outra creditada). Em produção, este seria o razão contábil primário no leiaute COSIF/CVM, base do balancete enviado à CVM.
  </div>
</div>
<?php endif; ?>
<?php page_end();
