<?php
// Painel do cotista (por fundo): evolução vs benchmark + composição da carteira.
// A conta enxerga qualquer fundo em que tenha vínculo; a CARTEIRA respeita a
// transparência GLOBAL do fundo (realtime / defasagem / não divulgada) definida pelo gestor.
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$conta = exigir_conta_cotista($pdo);
$vinculos = fundos_da_conta($pdo, (int)$conta['id']);
if (!$vinculos) { header('Location: home.php'); exit; }

// resolve o fundo pedido (GET) entre os vínculos da conta — default: o de maior posição
$fid = (int)($_GET['fundo_id'] ?? 0);
$vinculo = null;
foreach ($vinculos as $v) if ((int)$v['fundo_id'] === $fid) $vinculo = $v;
if (!$vinculo) { $vinculo = $vinculos[0]; $fid = (int)$vinculo['fundo_id']; }

$st = $pdo->prepare('SELECT * FROM fundos WHERE id = ?');
$st->execute([$fid]);
$fundo = $st->fetch();

$transp = $vinculo['transparencia'] ?: 'delay_1m';
$corte = data_corte_transparencia($transp);        // null = carteira não divulgada
$nivelRotulo = rotulo_transparencia($transp);

// posição própria: SEMPRE visível (é dele), pela última cota publicada
$posValor = (float)$vinculo['cotas'] * (float)($fundo['cota_atual'] ?: 1);
$posCusto = $vinculo['custo_total'] !== null ? (float)$vinculo['custo_total'] : (float)$vinculo['cotas'];
$posRent = $posCusto > 0 ? ($posValor / $posCusto - 1) * 100 : null;

// série da cota até o corte (apenas cotas publicadas) — só quando a carteira é divulgada
$serie = [];
if ($corte !== null) {
    $st = $pdo->prepare('SELECT data_ref, valor_cota FROM cotas_historico WHERE fundo_id = ? AND data_ref <= ? ORDER BY data_ref');
    $st->execute([$fid, $corte]);
    $serie = $st->fetchAll();
}

$labels = []; $fundoBase = []; $cdiBase = [];
if ($serie) {
    $st = $pdo->prepare('SELECT data_ref, fator_diario FROM cdi_historico WHERE data_ref >= ? AND data_ref <= ? ORDER BY data_ref');
    $st->execute([$serie[0]['data_ref'], $corte]);
    $cdiPorData = [];
    foreach ($st->fetchAll() as $r) $cdiPorData[$r['data_ref']] = (float)$r['fator_diario'];
    $base = (float)$serie[0]['valor_cota']; $f = 1.0; $primeiro = true;
    foreach ($serie as $p) {
        if (!$primeiro && isset($cdiPorData[$p['data_ref']])) $f *= $cdiPorData[$p['data_ref']];
        $primeiro = false;
        $labels[] = date('d/m/y', strtotime($p['data_ref']));
        $fundoBase[] = round((float)$p['valor_cota'] / $base * 100, 3);
        $cdiBase[] = round($f * 100, 3);
    }
}
$ultimaData = $serie ? end($serie)['data_ref'] : null;
$ultimaCota = $serie ? (float)end($serie)['valor_cota'] : null;

// rentabilidades até o corte
function rent_ate(array $serie, string $de): ?float {
    $c0 = null; $c1 = (float)end($serie)['valor_cota'];
    foreach ($serie as $p) if ($p['data_ref'] <= $de) $c0 = (float)$p['valor_cota'];
    return $c0 ? ($c1 / $c0 - 1) * 100 : null;
}
$rentMes = $serie ? rent_ate($serie, date('Y-m-d', strtotime($ultimaData . ' -1 month'))) : null;
$rentAno = $serie ? rent_ate($serie, date('Y-12-31', strtotime($ultimaData . ' -1 year'))) : null;
$rentIni = $serie ? ((float)end($serie)['valor_cota'] / (float)$serie[0]['valor_cota'] - 1) * 100 : null;

// composição por classe no snapshot mais recente <= corte (agregado, sem ativos individuais)
$porClasse = []; $dataCart = null;
if ($corte !== null) {
    $st = $pdo->prepare('SELECT MAX(data_ref) FROM ativos_carteira WHERE fundo_id = ? AND data_ref <= ?');
    $st->execute([$fid, $corte]);
    $dataCart = $st->fetchColumn();
    if ($dataCart) {
        foreach (carteira($pdo, $fid, $dataCart) as $a)
            $porClasse[$a['tipo']] = ($porClasse[$a['tipo']] ?? 0) + $a['valor_mercado'];
    }
}
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e_html($fundo['nome']) ?> · Portal do Cotista</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="../assets/css/style.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<style>body{background:var(--bg)}</style>
</head>
<body>
<nav style="background:var(--navy)" class="py-2 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
  <div class="d-flex align-items-center gap-2 text-white">
    <img src="../assets/favicon.png" alt="Argus" style="height:26px;width:26px;object-fit:contain">
    <b style="font-size:.85rem;letter-spacing:1px">PORTAL DO COTISTA</b>
  </div>
  <div class="d-flex align-items-center gap-2 flex-wrap">
    <a class="btn btn-sm btn-outline-light" href="home.php" style="font-size:.75rem"><i class="bi bi-house me-1"></i>Início</a>
    <a class="btn btn-sm btn-outline-light" href="movimentar.php?fundo_id=<?= $fid ?>" style="font-size:.75rem"><i class="bi bi-arrow-left-right me-1"></i>Movimentar</a>
    <a class="btn btn-sm btn-outline-light" href="tickets.php" style="font-size:.75rem"><i class="bi bi-chat-dots me-1"></i>Dúvidas</a>
    <a class="btn btn-sm btn-outline-light" href="dados.php" style="font-size:.75rem"><i class="bi bi-person-gear me-1"></i>Meus dados</a>
    <a class="btn btn-sm btn-outline-light" href="sair.php" style="font-size:.75rem">Sair</a>
  </div>
</nav>

<div class="container py-4" style="max-width:1050px">
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
      <?php if (count($vinculos) > 1): ?>
        <form method="get" class="mb-1">
          <select name="fundo_id" class="form-select form-select-sm d-inline-block" style="max-width:420px;font-weight:600" onchange="this.form.submit()">
            <?php foreach ($vinculos as $v): ?>
              <option value="<?= (int)$v['fundo_id'] ?>" <?= (int)$v['fundo_id'] === $fid ? 'selected' : '' ?>><?= e_html($v['fundo_nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      <?php else: ?>
        <h4 class="mb-0"><?= e_html($fundo['nome']) ?></h4>
      <?php endif; ?>
      <span class="text-muted" style="font-size:.82rem">CNPJ <?= e_html($fundo['cnpj']) ?> · <?= e_html($fundo['classe']) ?> · benchmark <?= e_html($fundo['benchmark']) ?></span>
    </div>
    <span class="badge badge-soft-<?= $corte === null ? 'secondary' : 'info' ?>" style="font-size:.75rem">
      <i class="bi bi-clock-history me-1"></i><?= $corte === null ? 'carteira não divulgada'
        : 'dados até ' . ($ultimaData ? data_br($ultimaData) : '—') . ' (' . $nivelRotulo . ')' ?>
    </span>
  </div>

  <!-- posição própria: sempre visível -->
  <div class="row row-cols-2 row-cols-md-4 g-3 mb-4">
    <?= kpi_simples('Minha posição', moeda($posValor)) ?>
    <?= kpi_simples('Minhas cotas', number_format((float)$vinculo['cotas'], 2, ',', '.')) ?>
    <?= kpi_simples('Valor aplicado', moeda($posCusto)) ?>
    <?= kpi_simples('Meu resultado', '<span class="' . pct_color($posRent) . '">' . pct($posRent) . '</span>') ?>
  </div>

  <?php if ($corte === null): ?>
    <div class="card">
      <div class="card-body text-center py-5">
        <i class="bi bi-eye-slash text-muted" style="font-size:2rem"></i>
        <h6 class="mt-2 mb-1">Carteira não divulgada</h6>
        <p class="text-muted mb-0" style="font-size:.85rem;max-width:560px;margin:0 auto">
          O gestor deste fundo optou por não divulgar a composição da carteira e a série histórica no portal
          (permitido pela regulamentação — a CVM admite defasagem de até 90 dias ou divulgação restrita).
          Sua posição pessoal acima continua sempre visível, e os informes oficiais seguem sendo enviados.
          Dúvidas? <a href="tickets.php">Fale com o gestor</a>.</p>
      </div>
    </div>
  <?php else: ?>

  <div class="row row-cols-2 row-cols-md-4 g-3 mb-4">
    <?= kpi_simples('Cota', $ultimaCota ? number_format($ultimaCota, 6, ',', '.') : '—') ?>
    <?= kpi_simples('No mês', '<span class="' . pct_color($rentMes) . '">' . pct($rentMes) . '</span>') ?>
    <?= kpi_simples('No ano', '<span class="' . pct_color($rentAno) . '">' . pct($rentAno) . '</span>') ?>
    <?= kpi_simples('Desde o início', '<span class="' . pct_color($rentIni) . '">' . pct($rentIni) . '</span>') ?>
  </div>

  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card h-100">
        <div class="card-header"><i class="bi bi-graph-up-arrow me-1"></i> Evolução do fundo × <?= e_html($fundo['benchmark']) ?> (base 100)</div>
        <div class="card-body grafico-box" style="height:340px"><canvas id="graf"></canvas></div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card h-100">
        <div class="card-header"><i class="bi bi-pie-chart me-1"></i> Composição da carteira
          <span class="text-muted" style="font-size:.7rem">· <?= $dataCart ? data_br($dataCart) : '—' ?></span></div>
        <div class="card-body grafico-box" style="height:340px"><canvas id="graf-classe"></canvas></div>
      </div>
    </div>
  </div>

  <?php endif; ?>

  <p class="text-muted mt-3" style="font-size:.74rem"><i class="bi bi-info-circle me-1"></i>
    A divulgação da carteira segue a política de transparência definida pelo gestor para TODOS os cotistas
    do fundo (<?= $nivelRotulo ?>). As cotas exibidas são as oficialmente publicadas (aprovadas pelo gestor e
    processadas pela administradora). Rentabilidade passada não garante resultados futuros.</p>
</div>

<script src="../assets/js/app.js"></script>
<?php if ($corte !== null): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
  graficoLinha('graf', <?= json_encode($labels) ?>, [
    { label: 'Fundo', data: <?= json_encode($fundoBase) ?>, cor: '#14b8a6', fill: true },
    { label: '<?= e_html($fundo['benchmark']) ?>', data: <?= json_encode($cdiBase) ?>, cor: '#94a3b8', borderDash: [6,4] }
  ]);
  graficoRosca('graf-classe', <?= json_encode(array_keys($porClasse)) ?>,
    <?= json_encode(array_map(fn($v) => round($v, 2), array_values($porClasse))) ?>);
});
</script>
<?php endif; ?>
</body>
</html>
<?php
// mini-helper local (não usa o layout com sidebar)
function kpi_simples(string $rotulo, string $valor): string {
    return '<div class="col"><div class="kpi-card"><div>
        <div class="kpi-rotulo">' . e_html($rotulo) . '</div>
        <div class="kpi-valor">' . $valor . '</div></div></div></div>';
}
