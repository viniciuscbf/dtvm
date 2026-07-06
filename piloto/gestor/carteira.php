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

// seletor retroativo de data (snapshots diários da carteira)
$datas = datas_carteira($pdo, $fid);
$data = $_GET['data'] ?? ($datas[0] ?? null);
if (!in_array($data, $datas, true) && $datas) $data = $datas[0];

$ativos = carteira($pdo, $fid, $data);
$filtro = $_GET['tipo'] ?? '';
$tipos = array_values(array_unique(array_column($ativos, 'tipo')));
if ($filtro) $ativos = array_values(array_filter($ativos, fn($a) => $a['tipo'] === $filtro));

$totVm = array_sum(array_column($ativos, 'valor_mercado'));
$totCusto = array_sum(array_column($ativos, 'custo'));
$pl = (float)$fundo['pl_atual'];

$fech = fechamento($pdo, $fid, $data);
$liberada = data_liberada($pdo, $fid, $data, $u['perfil']);
$statusDia = $fech ? $fech['status'] : 'Em processamento';

page_start('Carteira', 'Carteira', $u, e_html($fundo['nome']) . ' · posição de ' . data_br($data));
?>

<div class="d-flex gap-2 align-items-center flex-wrap mb-3">
  <form method="get" class="d-flex gap-2 align-items-center">
    <label class="text-muted" style="font-size:.8rem"><i class="bi bi-calendar3 me-1"></i>Posição em</label>
    <select name="data" class="form-select form-select-sm" style="max-width:180px" onchange="this.form.submit()">
      <?php foreach ($datas as $d): ?>
        <option value="<?= $d ?>" <?= $d === $data ? 'selected' : '' ?>><?= data_br($d) ?><?= $d === $datas[0] ? ' (D-1)' : '' ?></option>
      <?php endforeach; ?>
    </select>
    <?php if ($filtro): ?><input type="hidden" name="tipo" value="<?= e_html($filtro) ?>"><?php endif; ?>
  </form>
  <span style="font-size:.8rem">Cota do dia: <?= badge_status($statusDia) ?></span>
  <?php if ($liberada): ?>
    <?= selo_dia($pdo, $fid, $data) === 'OFICIAL'
        ? badge('OFICIAL — cota aprovada', 'success')
        : badge('PRÉVIA — processada, aguardando sua aprovação da cota', 'warning') ?>
  <?php else: ?>
    <?= badge('dia ainda não processado — sem carteira calculada', 'secondary') ?>
  <?php endif; ?>
</div>

<?php if ($liberada && selo_dia($pdo, $fid, $data) !== 'OFICIAL'): ?>
  <div class="alert alert-warning py-2" style="font-size:.85rem"><i class="bi bi-search me-1"></i>
    A administradora já bateu e calculou <?= data_br($data) ?> — use esta posição (e os downloads) para <b>conferir</b>
    antes de aprovar ou rejeitar a cota na aba <a href="cotas.php">Aprovação de cota</a>. Os arquivos saem carimbados como PRÉVIA.</div>
<?php elseif (!$liberada): ?>
  <div class="alert alert-secondary py-2" style="font-size:.85rem"><i class="bi bi-hourglass-split me-1"></i>
    Este dia ainda não foi processado pela administradora — não há carteira batida nem cota calculada para baixar.</div>
<?php endif; ?>

<div class="row row-cols-2 row-cols-md-4 g-3 mb-4">
  <?= kpi('Valor de mercado', moeda_compacta($totVm), 'bi-collection') ?>
  <?= kpi('Custo de aquisição', moeda_compacta($totCusto), 'bi-receipt') ?>
  <?= kpi('Resultado não realizado', '<span class="' . pct_color($totVm - $totCusto) . '">' . moeda_compacta($totVm - $totCusto) . '</span>',
          'bi-graph-up', $totCusto > 0 ? pct(($totVm / $totCusto - 1) * 100) : '—') ?>
  <?= kpi('Caixa + PL', moeda_compacta($fundo['caixa_atual']) . ' · ' . moeda_compacta($pl), 'bi-safe') ?>
</div>

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="bi bi-table me-1"></i> Ativos em <?= data_br($data) ?></span>
    <div class="d-flex gap-2 flex-wrap">
      <form method="get" class="d-flex gap-2">
        <input type="hidden" name="data" value="<?= e_html($data) ?>">
        <select name="tipo" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">Todos os tipos</option>
          <?php foreach ($tipos as $t): ?>
            <option value="<?= e_html($t) ?>" <?= $t === $filtro ? 'selected' : '' ?>><?= e_html($t) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <?php if ($liberada): ?>
        <a class="btn btn-sm btn-success" href="<?= BASE_URL ?>api/carteira_export.php?fundo_id=<?= $fid ?>&data=<?= e_html($data) ?>&formato=csv">
          <i class="bi bi-filetype-csv me-1"></i>CSV</a>
        <a class="btn btn-sm btn-outline-success" href="<?= BASE_URL ?>api/carteira_export.php?fundo_id=<?= $fid ?>&data=<?= e_html($data) ?>&formato=json">
          <i class="bi bi-filetype-json me-1"></i>JSON</a>
        <a class="btn btn-sm btn-danger" href="<?= BASE_URL ?>api/carteira_export.php?fundo_id=<?= $fid ?>&data=<?= e_html($data) ?>&formato=pdf">
          <i class="bi bi-filetype-pdf me-1"></i>PDF por classe</a>
      <?php else: ?>
        <button class="btn btn-sm btn-outline-secondary" disabled title="Dia ainda não processado pela administradora">
          <i class="bi bi-hourglass-split me-1"></i>CSV · JSON · PDF</button>
      <?php endif; ?>
    </div>
  </div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead><tr>
        <th>Código</th><th>Tipo</th><th class="text-end">Quantidade</th>
        <th class="text-end">Preço médio</th><th class="text-end">Preço MaM</th><th>Fonte</th>
        <th class="text-end">Valor de mercado</th><th class="text-end">% PL</th><th class="text-end">Resultado</th>
      </tr></thead>
      <tbody>
      <?php foreach ($ativos as $a):
          $pctPl = $pl > 0 ? $a['valor_mercado'] / $pl * 100 : 0;
          $resPct = $a['custo'] > 0 ? ($a['valor_mercado'] / $a['custo'] - 1) * 100 : 0; ?>
        <tr>
          <td><b><?= e_html($a['codigo']) ?></b></td>
          <td><?= badge($a['tipo'], 'secondary') ?></td>
          <td class="text-end"><?= number_format((float)$a['quantidade'], 0, ',', '.') ?></td>
          <td class="text-end"><?= number_format((float)$a['preco_medio'], 4, ',', '.') ?></td>
          <td class="text-end"><?= number_format((float)$a['preco_mam'], 4, ',', '.') ?></td>
          <td><?= $a['fonte_preco'] === 'Comitê' ? badge('Comitê', 'warning') : badge($a['fonte_preco'], 'info') ?></td>
          <td class="text-end"><?= moeda($a['valor_mercado']) ?></td>
          <td class="text-end"><?= pct($pctPl) ?></td>
          <td class="text-end <?= pct_color($a['resultado']) ?>"><?= moeda($a['resultado']) ?> <small>(<?= pct($resPct) ?>)</small></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot class="table-light"><tr>
        <th colspan="6">Total (<?= count($ativos) ?> ativos)</th>
        <th class="text-end"><?= moeda($totVm) ?></th>
        <th class="text-end"><?= pct($pl > 0 ? $totVm / $pl * 100 : 0) ?></th>
        <th class="text-end <?= pct_color($totVm - $totCusto) ?>"><?= moeda($totVm - $totCusto) ?></th>
      </tr></tfoot>
    </table>
  </div>
</div>
<p class="text-muted mt-3" style="font-size:.78rem"><i class="bi bi-search me-1"></i>
Encontrou um erro na posição (preço, quantidade, evento não capturado)? <b>Rejeite a cota do dia</b> na aba
<a href="cotas.php">Aprovação de cota</a> descrevendo a divergência — a controladoria corrige, reprocessa e reenvia a prévia.</p>
<?php page_end();
