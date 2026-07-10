<?php
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

$u = exigir_perfil('gestor', 'admin');
$fundo = fundo_do_usuario($pdo, $u);
if (!$fundo) die('Sem fundo vinculado.');
exigir_fundo_ativo($fundo);
$fid = (int)$fundo['id'];
exigir_permissao($pdo, $u, $fid, 'ver_performance');

// relatório retroativo: performance "como estava" em qualquer data de fechamento
$datas = datas_carteira($pdo, $fid);
$ate = $_GET['ate'] ?? ($datas[0] ?? null);
if (!in_array($ate, $datas, true) && $datas) $ate = $datas[0];
$ehAtual = $ate === ($datas[0] ?? $ate);

$perf = tabela_performance($pdo, $fid, $ate);
$serie = array_values(array_filter(serie_cota($pdo, $fid), fn($p) => $p['data_ref'] <= $ate));

// risco: vol anualizada + drawdown máximo (até a data)
$retornos = [];
for ($i = 1; $i < count($serie); $i++) {
    $retornos[] = (float)$serie[$i]['valor_cota'] / (float)$serie[$i - 1]['valor_cota'] - 1;
}
$vol = null; $ddMax = null;
if (count($retornos) > 10) {
    $media = array_sum($retornos) / count($retornos);
    $var = array_sum(array_map(fn($r) => ($r - $media) ** 2, $retornos)) / (count($retornos) - 1);
    $vol = sqrt($var) * sqrt(252) * 100;
    $pico = 0; $dd = 0;
    foreach ($serie as $p) {
        $c = (float)$p['valor_cota'];
        $pico = max($pico, $c);
        $dd = min($dd, $c / $pico - 1);
    }
    $ddMax = $dd * 100;
}

// matriz mensal até a data
$porMes = [];
foreach ($serie as $p) $porMes[substr($p['data_ref'], 0, 7)] = (float)$p['valor_cota'];
$meses = array_keys($porMes);
$matriz = [];
for ($i = 1; $i < count($meses); $i++) {
    $matriz[$meses[$i]] = ($porMes[$meses[$i]] / $porMes[$meses[$i - 1]] - 1) * 100;
}
$matriz = array_slice($matriz, -12, 12, true);

// memória de cálculo da taxa de performance
$temPerf = (float)$fundo['taxa_performance'] > 0;
$excedente = null; $valorPerf = null;
if ($temPerf && isset($perf['12 meses'])) {
    $rf = $perf['12 meses']['fundo']; $rc = $perf['12 meses']['cdi'];
    if ($rf !== null && $rc !== null && $rf > $rc) {
        $excedente = $rf - $rc;
        $valorPerf = (float)$fundo['pl_atual'] * ($excedente / 100) * (float)$fundo['taxa_performance'];
    }
}

page_start('Performance', 'Performance', $u,
    e_html($fundo['nome']) . ' · benchmark ' . e_html($fundo['benchmark']) . ' · relatório na data de ' . data_br($ate));
?>

<div class="d-flex gap-2 align-items-center flex-wrap mb-3">
  <form method="get" class="d-flex gap-2 align-items-center">
    <label class="text-muted" style="font-size:.8rem"><i class="bi bi-calendar3 me-1"></i>Relatório na data de</label>
    <select name="ate" class="form-select form-select-sm" style="max-width:180px" onchange="this.form.submit()">
      <?php foreach ($datas as $d): ?>
        <option value="<?= $d ?>" <?= $d === $ate ? 'selected' : '' ?>><?= data_br($d) ?><?= $d === $datas[0] ? ' (atual)' : '' ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <?php if (!$ehAtual): ?><?= badge('visão retroativa — números como estavam em ' . data_br($ate), 'info') ?><?php endif; ?>
</div>

<div class="row row-cols-2 row-cols-md-4 g-3 mb-4">
  <?= kpi('No ano', '<span class="' . pct_color($perf['Ano']['fundo'] ?? null) . '">' . pct($perf['Ano']['fundo'] ?? null) . '</span>',
          'bi-calendar3', pct($perf['Ano']['pct_cdi'] ?? null, 0) . ' do CDI') ?>
  <?= kpi('12 meses', '<span class="' . pct_color($perf['12 meses']['fundo'] ?? null) . '">' . pct($perf['12 meses']['fundo'] ?? null) . '</span>',
          'bi-calendar-range', pct($perf['12 meses']['pct_cdi'] ?? null, 0) . ' do CDI') ?>
  <?= kpi('Volatilidade (a.a.)', pct($vol), 'bi-activity') ?>
  <?= kpi('Drawdown máximo', '<span class="text-danger">' . pct($ddMax) . '</span>', 'bi-graph-down-arrow') ?>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-graph-up-arrow me-1"></i> Fundo × CDI (base 100)</span>
        <div class="btn-group btn-group-sm">
          <?php foreach (['3m' => '3M', '12m' => '12M', 'ini' => 'Início'] as $k => $r): ?>
            <button class="btn btn-outline-secondary <?= $k === '12m' ? 'active' : '' ?>" data-periodo="<?= $k ?>"><?= $r ?></button>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="card-body grafico-box" style="height:320px"><canvas id="graf-perf"></canvas></div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-table me-1"></i> Rentabilidade por período (em <?= data_br($ate) ?>)</div>
      <div class="card-body p-0">
        <table class="table mb-0">
          <thead><tr><th>Período</th><th class="text-end">Fundo</th><th class="text-end">CDI</th><th class="text-end">% CDI</th></tr></thead>
          <tbody>
          <?php foreach ($perf as $rotulo => $l): ?>
            <tr>
              <td><b><?= e_html($rotulo) ?></b></td>
              <td class="text-end <?= pct_color($l['fundo']) ?>"><?= pct($l['fundo']) ?></td>
              <td class="text-end text-muted"><?= pct($l['cdi']) ?></td>
              <td class="text-end"><b><?= pct($l['pct_cdi'], 0) ?></b></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-calendar-month me-1"></i> Rentabilidade mensal (até <?= data_br($ate) ?>)</div>
      <div class="card-body grafico-box" style="height:260px"><canvas id="graf-mensal"></canvas></div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-calculator me-1"></i> Memória de cálculo — taxa de performance</div>
      <div class="card-body" style="font-size:.88rem">
        <?php if (!$temPerf): ?>
          <p class="text-muted mb-0">Este fundo não cobra taxa de performance.</p>
        <?php elseif ($excedente === null): ?>
          <p class="mb-2">Taxa contratada: <b><?= pct((float)$fundo['taxa_performance'] * 100, 0) ?> sobre o que exceder o CDI</b>.</p>
          <div class="alert alert-secondary py-2" style="font-size:.85rem">Nos 12 meses até <?= data_br($ate) ?> o fundo não excedeu o benchmark — <b>sem performance a provisionar</b> (marca d'água respeitada).</div>
        <?php else: ?>
          <p class="mb-2">Taxa contratada: <b><?= pct((float)$fundo['taxa_performance'] * 100, 0) ?> sobre o que exceder o CDI</b>.</p>
          <table class="table table-sm">
            <tr><td>Rentabilidade 12m</td><td class="text-end"><?= pct($perf['12 meses']['fundo']) ?></td></tr>
            <tr><td>CDI 12m</td><td class="text-end"><?= pct($perf['12 meses']['cdi']) ?></td></tr>
            <tr><td>Excedente</td><td class="text-end text-success"><b><?= pct($excedente) ?></b></td></tr>
            <tr><td>PL de referência</td><td class="text-end"><?= moeda_compacta($fundo['pl_atual']) ?></td></tr>
            <tr class="table-light"><td><b>Performance provisionada</b></td><td class="text-end"><b><?= moeda($valorPerf) ?></b></td></tr>
          </table>
          <p class="text-muted mb-0" style="font-size:.75rem">O cálculo segue a metodologia definida no regulamento, com linha d'água por cotista.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  iniciarGraficoCota('graf-perf', '<?= BASE_URL ?>', <?= $fid ?>);
  graficoBarras('graf-mensal',
    <?= json_encode(array_keys($matriz)) ?>,
    [{ label: 'Rentabilidade no mês (%)',
       data: <?= json_encode(array_map(fn($v) => round($v, 2), array_values($matriz))) ?>,
       cor: '#14b8a6' }]);
});
</script>
<?php page_end();
