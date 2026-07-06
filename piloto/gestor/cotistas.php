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

$st = $pdo->prepare('SELECT * FROM cotistas WHERE fundo_id = ? ORDER BY cotas DESC');
$st->execute([$fid]);
$cotistas = $st->fetchAll();
$totCotas = array_sum(array_map('floatval', array_column($cotistas, 'cotas')));
$valorCota = (float)$fundo['cota_atual'];

$top5 = array_slice($cotistas, 0, 5);
$concTop5 = $totCotas > 0 ? array_sum(array_map('floatval', array_column($top5, 'cotas'))) / $totCotas * 100 : 0;

$st = $pdo->prepare('SELECT m.*, c.nome AS cotista_nome FROM mov_cotistas m JOIN cotistas c ON c.id = m.cotista_id
                     WHERE m.fundo_id = ? ORDER BY m.data_ref DESC LIMIT 25');
$st->execute([$fid]);
$movs = $st->fetchAll();

$ticketMedio = count($cotistas) ? ($totCotas * $valorCota) / count($cotistas) : 0;

page_start('Cotistas', 'Cotistas', $u, e_html($fundo['nome']) . ' · visão do passivo liberada ao gestor');
?>

<div class="row row-cols-2 row-cols-md-4 g-3 mb-4">
  <?= kpi('Cotistas', (string)count($cotistas), 'bi-people') ?>
  <?= kpi('Cotas emitidas', number_format($totCotas, 0, ',', '.'), 'bi-collection') ?>
  <?= kpi('Ticket médio', moeda_compacta($ticketMedio), 'bi-cash') ?>
  <?= kpi('Concentração top 5', pct($concTop5, 1), 'bi-diagram-3',
        $concTop5 > 40 ? '<span class="text-warning"><i class="bi bi-exclamation-triangle"></i> risco de liquidez</span>' : 'saudável') ?>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-diagram-3 me-1"></i> Maiores cotistas</div>
      <div class="card-body">
        <?php foreach ($top5 as $c):
            $p = $totCotas > 0 ? (float)$c['cotas'] / $totCotas * 100 : 0; ?>
          <div class="mb-3">
            <div class="d-flex justify-content-between" style="font-size:.85rem">
              <span><?= e_html($c['nome']) ?> <span class="text-muted">(<?= $c['tipo_pessoa'] ?>)</span></span>
              <b><?= pct($p, 1) ?></b>
            </div>
            <div class="limite-barra mt-1"><div style="width:<?= min(100, $p) ?>%;background:<?= $p > 25 ? '#eab308' : '#14b8a6' ?>"></div></div>
          </div>
        <?php endforeach; ?>
        <p class="text-muted mb-0" style="font-size:.75rem">Concentração acima de 25% por cotista ou 40% no top 5 acende alerta de risco de liquidez.</p>
      </div>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-list-ul me-1"></i> Lista de cotistas</div>
      <div class="card-body p-0" style="max-height:400px;overflow-y:auto">
        <table class="table table-hover mb-0">
          <thead><tr><th>Nome</th><th>Documento</th><th class="text-end">Cotas</th><th class="text-end">Posição</th><th class="text-end">%</th><th>Desde</th></tr></thead>
          <tbody>
          <?php foreach ($cotistas as $c): ?>
            <tr>
              <td><?= e_html($c['nome']) ?></td>
              <td class="text-muted"><?= e_html($c['documento']) ?></td>
              <td class="text-end"><?= number_format((float)$c['cotas'], 2, ',', '.') ?></td>
              <td class="text-end"><?= moeda((float)$c['cotas'] * $valorCota) ?></td>
              <td class="text-end"><?= pct($totCotas > 0 ? (float)$c['cotas'] / $totCotas * 100 : 0, 1) ?></td>
              <td><?= data_br($c['data_entrada']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header"><i class="bi bi-arrow-left-right me-1"></i> Movimentação de cotas (aplicações e resgates)</div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead><tr><th>Data</th><th>Cotista</th><th>Tipo</th><th class="text-end">Valor</th><th class="text-end">Cotas</th><th>Cotização</th><th>Liquidação</th></tr></thead>
      <tbody>
      <?php foreach ($movs as $m): ?>
        <tr>
          <td><?= data_br($m['data_ref']) ?></td>
          <td><?= e_html($m['cotista_nome']) ?></td>
          <td><?= badge($m['tipo'], $m['tipo'] === 'Aplicação' ? 'success' : 'warning') ?></td>
          <td class="text-end"><?= moeda($m['valor']) ?></td>
          <td class="text-end"><?= number_format((float)$m['cotas'], 2, ',', '.') ?></td>
          <td><?= data_br($m['data_cotizacao']) ?></td>
          <td><?= data_br($m['data_liquidacao']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php page_end();
