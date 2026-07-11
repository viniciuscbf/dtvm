<?php
// Início do cotista logado: posição CONSOLIDADA em todos os fundos vinculados à conta.
// Como nas plataformas reais: a posição própria (cotas × última cota publicada) é sempre
// visível; a transparência global do fundo (gestor/acessos.php) regula só a CARTEIRA.
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$conta = exigir_conta_cotista($pdo);
$vinculos = fundos_da_conta($pdo, (int)$conta['id']);

$totalValor = 0.0; $totalCusto = 0.0; $posicoes = [];
foreach ($vinculos as $v) {
    $valor = (float)$v['cotas'] * (float)($v['fundo_cota'] ?: 1);
    $custo = $v['custo_total'] !== null ? (float)$v['custo_total'] : (float)$v['cotas'];  // PM 1,00 quando NULL
    $totalValor += $valor; $totalCusto += $custo;
    $posicoes[] = $v + ['valor' => $valor, 'custo' => $custo,
                        'rent' => $custo > 0 ? ($valor / $custo - 1) * 100 : null];
}
$rentTotal = $totalCusto > 0 ? ($totalValor / $totalCusto - 1) * 100 : null;
$idsCotista = array_map(fn($v) => (int)$v['id'], $vinculos);
$idsFundo = array_map(fn($v) => (int)$v['fundo_id'], $vinculos);

// comunicados: dos meus fundos + gerais
$comunicados = [];
if ($idsFundo) {
    $in = implode(',', $idsFundo);
    $comunicados = $pdo->query("SELECT c.*, f.nome fundo_nome FROM comunicados c
                                LEFT JOIN fundos f ON f.id = c.fundo_id
                                WHERE c.fundo_id IS NULL OR c.fundo_id IN ($in)
                                ORDER BY c.data_pub DESC, c.id DESC LIMIT 6")->fetchAll();
}

// eventos fiscais (come-cotas / IR / IOF) das minhas posições — extrato fiscal resumido
$fiscais = []; $ordens = [];
if ($idsCotista) {
    $in = implode(',', $idsCotista);
    try {
        $fiscais = $pdo->query("SELECT e.*, f.nome fundo_nome FROM eventos_fiscais e
                                JOIN fundos f ON f.id = e.fundo_id
                                WHERE e.cotista_id IN ($in) ORDER BY e.data_ref DESC, e.id DESC LIMIT 6")->fetchAll();
    } catch (Throwable $t) { /* tabela lazy pode não existir ainda */ }
    try {
        $ordens = $pdo->query("SELECT o.*, f.nome fundo_nome FROM ordens_passivo o
                               JOIN fundos f ON f.id = o.fundo_id
                               WHERE o.cotista_id IN ($in) ORDER BY o.criado_em DESC LIMIT 6")->fetchAll();
    } catch (Throwable $t) { }
}
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Minha posição · Portal do Cotista</title>
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
    <span class="text-secondary" style="font-size:.75rem"><i class="bi bi-person-circle me-1"></i><?= e_html($conta['nome']) ?></span>
    <a class="btn btn-sm btn-outline-light" href="movimentar.php" style="font-size:.75rem"><i class="bi bi-arrow-left-right me-1"></i>Movimentar</a>
    <a class="btn btn-sm btn-outline-light" href="tickets.php" style="font-size:.75rem"><i class="bi bi-chat-dots me-1"></i>Dúvidas</a>
    <a class="btn btn-sm btn-outline-light" href="dados.php" style="font-size:.75rem"><i class="bi bi-person-gear me-1"></i>Meus dados</a>
    <a class="btn btn-sm btn-outline-light" href="sair.php" style="font-size:.75rem">Sair</a>
  </div>
</nav>

<div class="container py-4" style="max-width:1050px">
  <div class="mb-3">
    <h4 class="mb-0">Olá, <?= e_html(explode(' ', trim($conta['nome']))[0]) ?></h4>
    <span class="text-muted" style="font-size:.82rem">Sua posição consolidada em <?= count($posicoes) ?> fundo<?= count($posicoes) === 1 ? '' : 's' ?>, pela última cota publicada.</span>
  </div>

  <?php if (!$posicoes): ?>
    <div class="alert alert-info" style="font-size:.9rem"><i class="bi bi-hourglass-split me-1"></i>
      Sua conta ainda não tem posições vinculadas. Se você já é cotista de algum fundo administrado por nós,
      avise o gestor — a administradora vincula o seu CPF e as posições aparecem aqui automaticamente.</div>
  <?php else: ?>

  <div class="row row-cols-2 row-cols-md-4 g-3 mb-4">
    <div class="col"><div class="kpi-card"><div>
      <div class="kpi-rotulo">Patrimônio total</div>
      <div class="kpi-valor"><?= moeda($totalValor) ?></div></div></div></div>
    <div class="col"><div class="kpi-card"><div>
      <div class="kpi-rotulo">Valor aplicado</div>
      <div class="kpi-valor"><?= moeda($totalCusto) ?></div></div></div></div>
    <div class="col"><div class="kpi-card"><div>
      <div class="kpi-rotulo">Resultado</div>
      <div class="kpi-valor <?= pct_color($rentTotal) ?>"><?= moeda($totalValor - $totalCusto) ?></div></div></div></div>
    <div class="col"><div class="kpi-card"><div>
      <div class="kpi-rotulo">Rentabilidade</div>
      <div class="kpi-valor <?= pct_color($rentTotal) ?>"><?= pct($rentTotal) ?></div></div></div></div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-lg-8">
      <div class="card h-100">
        <div class="card-header"><i class="bi bi-wallet2 me-1"></i> Meus fundos</div>
        <div class="card-body p-0">
          <table class="table table-hover align-middle mb-0" style="font-size:.83rem">
            <thead><tr><th>Fundo</th><th class="text-end">Cotas</th><th class="text-end">Valor</th>
              <th class="text-end">Rent.</th><th>Carteira</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($posicoes as $p): ?>
              <tr>
                <td><b><?= e_html($p['fundo_nome']) ?></b><br>
                  <span class="text-muted" style="font-size:.72rem"><?= e_html($p['fundo_classe']) ?> · cota <?= number_format((float)$p['fundo_cota'], 6, ',', '.') ?></span></td>
                <td class="text-end"><?= number_format((float)$p['cotas'], 2, ',', '.') ?></td>
                <td class="text-end"><b><?= moeda($p['valor']) ?></b></td>
                <td class="text-end <?= pct_color($p['rent']) ?>"><?= pct($p['rent']) ?></td>
                <td><?= badge(rotulo_transparencia($p['transparencia']), $p['transparencia'] === 'off' ? 'secondary' : ($p['transparencia'] === 'realtime' ? 'success' : 'info')) ?></td>
                <td class="text-end" style="min-width:150px">
                  <a class="btn btn-sm btn-outline-dark" style="font-size:.72rem" href="painel.php?fundo_id=<?= (int)$p['fundo_id'] ?>"><i class="bi bi-graph-up me-1"></i>Painel</a>
                  <a class="btn btn-sm btn-outline-secondary" style="font-size:.72rem" href="movimentar.php?fundo_id=<?= (int)$p['fundo_id'] ?>"><i class="bi bi-arrow-left-right"></i></a>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="card-footer text-muted" style="font-size:.7rem">
          A posição é sempre a sua (cotas × última cota publicada). O selo "carteira" indica o que o gestor divulga
          da composição do fundo — tempo real, com defasagem, ou não divulgada (política global definida pelo gestor).
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card h-100">
        <div class="card-header"><i class="bi bi-pie-chart me-1"></i> Alocação por fundo</div>
        <div class="card-body grafico-box" style="height:300px"><canvas id="graf-aloc"></canvas></div>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-4">
      <div class="card h-100">
        <div class="card-header"><i class="bi bi-clock-history me-1"></i> Últimas movimentações</div>
        <div class="card-body p-0" style="font-size:.78rem">
          <?php if (!$ordens): ?><p class="text-muted text-center py-4 mb-0">Nenhuma movimentação.</p><?php endif; ?>
          <?php foreach ($ordens as $o): ?>
            <div class="p-2 px-3 border-bottom">
              <div class="d-flex justify-content-between">
                <b><?= e_html($o['tipo']) ?> · <?= moeda($o['valor']) ?></b>
                <?= badge($o['status'], ['Cotizada' => 'success', 'Pago' => 'success', 'Recebida' => 'info',
                    'Devolvida' => 'danger', 'Cancelada' => 'secondary'][$o['status']] ?? 'warning') ?>
              </div>
              <span class="text-muted" style="font-size:.7rem"><?= e_html($o['fundo_nome']) ?> · <?= date('d/m/Y', strtotime($o['criado_em'])) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card h-100">
        <div class="card-header"><i class="bi bi-receipt me-1"></i> Eventos fiscais</div>
        <div class="card-body p-0" style="font-size:.78rem">
          <?php if (!$fiscais): ?><p class="text-muted text-center py-4 mb-0">Nenhum evento (come-cotas/IR) ainda.</p><?php endif; ?>
          <?php foreach ($fiscais as $e): ?>
            <div class="p-2 px-3 border-bottom">
              <div class="d-flex justify-content-between">
                <b><?= e_html($e['tipo']) ?></b><span class="text-danger"><?= moeda($e['valor_tributo']) ?></span>
              </div>
              <span class="text-muted" style="font-size:.7rem"><?= e_html($e['fundo_nome']) ?> · <?= data_br($e['data_ref']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card h-100">
        <div class="card-header"><i class="bi bi-megaphone me-1"></i> Comunicados</div>
        <div class="card-body p-0" style="font-size:.78rem">
          <?php if (!$comunicados): ?><p class="text-muted text-center py-4 mb-0">Nenhum comunicado.</p><?php endif; ?>
          <?php foreach ($comunicados as $c): ?>
            <div class="p-2 px-3 border-bottom">
              <b><?= e_html($c['titulo']) ?></b><br>
              <span class="text-muted" style="font-size:.7rem"><?= $c['fundo_nome'] ? e_html($c['fundo_nome']) : 'Geral' ?> · <?= data_br($c['data_pub']) ?></span>
              <?= $c['mensagem'] ? '<div class="text-muted mt-1" style="font-size:.72rem">' . e_html(mb_strimwidth($c['mensagem'], 0, 140, '…', 'UTF-8')) . '</div>' : '' ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <?php endif; ?>

  <p class="text-muted mt-3 mb-0" style="font-size:.72rem"><i class="bi bi-info-circle me-1"></i>
    As cotas exibidas são as oficialmente publicadas (aprovadas pelo gestor e processadas pela administradora).
    Rentabilidade passada não garante resultados futuros. No piloto, os valores são simulados.</p>
</div>

<script src="../assets/js/app.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  <?php if ($posicoes): ?>
  graficoRosca('graf-aloc', <?= json_encode(array_map(fn($p) => $p['fundo_nome'], $posicoes)) ?>,
    <?= json_encode(array_map(fn($p) => round($p['valor'], 2), $posicoes)) ?>);
  <?php endif; ?>
});
</script>
</body>
</html>
