<?php
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

$u = exigir_perfil('admin');

$fundos = $pdo->query("SELECT * FROM fundos WHERE status='Ativo' ORDER BY pl_atual DESC")->fetchAll();
$fid = (int)($_GET['fundo_id'] ?? ($fundos[0]['id'] ?? 0));
$fundoSel = null;
foreach ($fundos as $f) if ((int)$f['id'] === $fid) $fundoSel = $f;
if (!$fundoSel && $fundos) { $fundoSel = $fundos[0]; $fid = (int)$fundoSel['id']; }

// exposição agregada por classe e por ativo (snapshot mais recente de cada fundo)
$agg = $pdo->query("SELECT a.codigo, a.tipo, SUM(a.quantidade * a.preco_mam) vm, COUNT(DISTINCT a.fundo_id) n_fundos
                    FROM ativos_carteira a
                    JOIN fundos f ON f.id = a.fundo_id AND f.status='Ativo'
                    JOIN (SELECT fundo_id, MAX(data_ref) md FROM ativos_carteira GROUP BY fundo_id) ult
                      ON ult.fundo_id = a.fundo_id AND ult.md = a.data_ref
                    GROUP BY a.codigo, a.tipo ORDER BY vm DESC")->fetchAll();
$aggClasse = [];
foreach ($agg as $a) $aggClasse[$a['tipo']] = ($aggClasse[$a['tipo']] ?? 0) + (float)$a['vm'];
$totalExposicao = array_sum($aggClasse);

// fila do comitê de precificação (ativos sem fonte primária, no snapshot mais recente)
$comite = $pdo->query("SELECT a.*, f.nome fundo_nome FROM ativos_carteira a
                       JOIN fundos f ON f.id = a.fundo_id
                       JOIN (SELECT fundo_id, MAX(data_ref) md FROM ativos_carteira GROUP BY fundo_id) ult
                         ON ult.fundo_id = a.fundo_id AND ult.md = a.data_ref
                       WHERE a.fonte_preco = 'Comitê' ORDER BY a.quantidade * a.preco_mam DESC")->fetchAll();

// visão retroativa da carteira do fundo selecionado
$datasSel = $fundoSel ? datas_carteira($pdo, $fid) : [];
$dataSel = $_GET['data'] ?? ($datasSel[0] ?? null);
if ($datasSel && !in_array($dataSel, $datasSel, true)) $dataSel = $datasSel[0];
$ativosSel = $fundoSel ? carteira($pdo, $fid, $dataSel) : [];
$plSel = $fundoSel ? (float)$fundoSel['pl_atual'] : 0;

page_start('Carteiras & Ativos', 'Carteiras', $u, 'Exposição consolidada da administradora e carteira por fundo');
?>

<div class="row g-3 mb-4">
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-pie-chart me-1"></i> Exposição agregada por classe</div>
      <div class="card-body grafico-box" style="height:280px"><canvas id="graf-agg"></canvas></div>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-collection me-1"></i> Maiores exposições consolidadas (todos os fundos)</div>
      <div class="card-body p-0" style="max-height:280px;overflow-y:auto">
        <table class="table table-sm table-hover mb-0">
          <thead><tr><th>Ativo</th><th>Tipo</th><th class="text-end">Exposição total</th><th class="text-end">% da administradora</th><th class="text-center">Em quantos fundos</th></tr></thead>
          <tbody>
          <?php foreach (array_slice($agg, 0, 12) as $a): ?>
            <tr>
              <td><b><?= e_html($a['codigo']) ?></b></td>
              <td><?= badge($a['tipo'], 'secondary') ?></td>
              <td class="text-end"><?= moeda_compacta($a['vm']) ?></td>
              <td class="text-end"><?= pct($totalExposicao > 0 ? (float)$a['vm'] / $totalExposicao * 100 : 0, 1) ?></td>
              <td class="text-center"><?= (int)$a['n_fundos'] ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-briefcase me-1"></i> Carteira por fundo
      <span class="text-muted" style="font-size:.75rem">· posição de <?= $dataSel ? data_br($dataSel) : '—' ?></span></span>
    <form method="get" class="d-flex gap-2">
      <select class="form-select form-select-sm" name="fundo_id" onchange="this.form.submit()">
        <?php foreach ($fundos as $f): ?>
          <option value="<?= (int)$f['id'] ?>" <?= (int)$f['id'] === $fid ? 'selected' : '' ?>><?= e_html($f['nome']) ?></option>
        <?php endforeach; ?>
      </select>
      <select class="form-select form-select-sm" name="data" onchange="this.form.submit()">
        <?php foreach ($datasSel as $d): ?>
          <option value="<?= $d ?>" <?= $d === $dataSel ? 'selected' : '' ?>><?= data_br($d) ?></option>
        <?php endforeach; ?>
      </select>
      <a class="btn btn-sm btn-outline-danger" title="Baixar PDF por classe"
         href="<?= BASE_URL ?>api/carteira_export.php?fundo_id=<?= $fid ?>&data=<?= e_html($dataSel) ?>&formato=pdf"><i class="bi bi-filetype-pdf"></i></a>
      <a class="btn btn-sm btn-outline-success" title="Baixar CSV"
         href="<?= BASE_URL ?>api/carteira_export.php?fundo_id=<?= $fid ?>&data=<?= e_html($dataSel) ?>&formato=csv"><i class="bi bi-filetype-csv"></i></a>
    </form>
  </div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead><tr><th>Ativo</th><th>Tipo</th><th class="text-end">Quantidade</th><th class="text-end">Preço MaM</th>
        <th class="text-end">Referência</th><th>Fonte</th><th class="text-end">Valor</th><th class="text-end">% PL</th></tr></thead>
      <tbody>
      <?php foreach ($ativosSel as $a):
          $desvio = $a['preco_referencia'] > 0 ? ((float)$a['preco_mam'] / (float)$a['preco_referencia'] - 1) * 100 : null; ?>
        <tr class="<?= $desvio !== null && abs($desvio) > 5 ? 'table-warning' : '' ?>">
          <td><b><?= e_html($a['codigo']) ?></b></td>
          <td><?= badge($a['tipo'], 'secondary') ?></td>
          <td class="text-end"><?= number_format((float)$a['quantidade'], 0, ',', '.') ?></td>
          <td class="text-end"><?= number_format((float)$a['preco_mam'], 4, ',', '.') ?>
            <?php if ($desvio !== null && abs($desvio) > 5): ?>
              <br><span class="text-danger" style="font-size:.72rem"><i class="bi bi-exclamation-triangle"></i> <?= pct($desvio, 1) ?> vs ref.</span>
            <?php endif; ?></td>
          <td class="text-end text-muted"><?= $a['preco_referencia'] ? number_format((float)$a['preco_referencia'], 4, ',', '.') : '—' ?></td>
          <td><?= badge($a['fonte_preco'], $a['fonte_preco'] === 'Comitê' ? 'warning' : 'info') ?></td>
          <td class="text-end"><?= moeda($a['valor_mercado']) ?></td>
          <td class="text-end"><?= pct($plSel > 0 ? $a['valor_mercado'] / $plSel * 100 : 0, 1) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <div class="card-header"><i class="bi bi-people me-1"></i> Fila do comitê de precificação (ativos sem fonte primária)</div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead><tr><th>Ativo</th><th>Fundo</th><th class="text-end">Preço em uso</th><th class="text-end">Posição</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($comite as $a): ?>
        <tr>
          <td><b><?= e_html($a['codigo']) ?></b> <?= badge($a['tipo'], 'secondary') ?></td>
          <td><?= e_html($a['fundo_nome']) ?></td>
          <td class="text-end"><?= number_format((float)$a['preco_mam'], 4, ',', '.') ?></td>
          <td class="text-end"><?= moeda((float)$a['quantidade'] * (float)$a['preco_mam']) ?></td>
          <td><?= badge('validação do comitê', 'warning') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$comite): ?><tr><td colspan="5" class="text-muted text-center py-4">Nenhum ativo aguardando o comitê.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  graficoRosca('graf-agg',
    <?= json_encode(array_keys($aggClasse)) ?>,
    <?= json_encode(array_map(fn($v) => round($v, 2), array_values($aggClasse))) ?>);
});
</script>
<?php page_end();
