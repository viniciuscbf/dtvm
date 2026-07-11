<?php
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

$u = exigir_perfil('gestor', 'admin');
$fundo = fundo_do_usuario($pdo, $u);
if (!$fundo) { header('Location: equipe.php'); exit; }   // conta sem fundo: convites / criar 1º fundo
exigir_fundo_ativo($fundo);
$fid = (int)$fundo['id'];

$perf = tabela_performance($pdo, $fid);
$rentDia = $perf['Dia']['fundo'] ?? null;
$rentMes = $perf['Mês']['fundo'] ?? null;
$rentAno = $perf['Ano']['fundo'] ?? null;
$pctCdiAno = $perf['Ano']['pct_cdi'] ?? null;

$st = $pdo->prepare('SELECT COUNT(*) c, COALESCE(SUM(cotas),0) tot FROM cotistas WHERE fundo_id = ?');
$st->execute([$fid]);
$cot = $st->fetch();

// composição por classe (snapshot mais recente)
$porClasse = [];
foreach (carteira($pdo, $fid) as $a) {
    $porClasse[$a['tipo']] = ($porClasse[$a['tipo']] ?? 0) + $a['valor_mercado'];
}
if ((float)$fundo['caixa_atual'] > 0) $porClasse['Caixa'] = (float)$fundo['caixa_atual'];

// prévia de cota pendente de aprovação?
$st = $pdo->prepare("SELECT COUNT(*) FROM fechamentos WHERE fundo_id = ? AND status = 'Aguardando aprovação'");
$st->execute([$fid]);
$previasPendentes = (int)$st->fetchColumn();

// cotistas com conta de acesso ao portal (vinculada e ativa)
$tokensAtivos = 0;
try {
    $st = $pdo->prepare("SELECT COUNT(*) FROM cotistas c JOIN cotista_contas cc ON cc.id = c.conta_id
                         WHERE c.fundo_id = ? AND cc.status = 'Ativa'");
    $st->execute([$fid]);
    $tokensAtivos = (int)$st->fetchColumn();
} catch (Throwable $t) { /* tabela lazy pode não existir ainda */ }

[$enqOk, $violadas] = situacao_enquadramento($pdo, $fundo);
$st = $pdo->prepare('SELECT * FROM comunicados WHERE fundo_id = ? OR fundo_id IS NULL ORDER BY data_pub DESC LIMIT 3');
$st->execute([$fid]);
$avisos = $st->fetchAll();

$st = $pdo->prepare('SELECT * FROM movimentacoes WHERE fundo_id = ? ORDER BY data_ref DESC, id DESC LIMIT 5');
$st->execute([$fid]);
$ultMov = $st->fetchAll();

page_start('Visão geral', 'Visão geral', $u,
    e_html($fundo['nome']) . ' · CNPJ ' . e_html($fundo['cnpj']) . ' · ' . badge($fundo['classe'], 'info') . ' ' . badge_status($fundo['status']));
?>

<?php if ($previasPendentes): ?>
  <div class="alert alert-warning d-flex justify-content-between align-items-center py-2">
    <span style="font-size:.9rem"><i class="bi bi-clipboard-check me-1"></i>
      <b><?= $previasPendentes ?> prévia(s) de cota</b> aguardando sua validação — a cota só é publicada depois que você aprovar.</span>
    <a class="btn btn-sm btn-dark" href="cotas.php">Revisar e aprovar →</a>
  </div>
<?php endif; ?>

<div class="row row-cols-2 row-cols-md-3 row-cols-xl-6 g-3 mb-4">
  <?= kpi('Patrimônio líquido', moeda_compacta($fundo['pl_atual']), 'bi-safe') ?>
  <?= kpi('Cota publicada', number_format((float)$fundo['cota_atual'], 6, ',', '.'),
          'bi-graph-up', '<span class="' . pct_color($rentDia) . '">' . pct($rentDia) . ' no dia</span>') ?>
  <?= kpi('No mês', '<span class="' . pct_color($rentMes) . '">' . pct($rentMes) . '</span>', 'bi-calendar-month') ?>
  <?= kpi('No ano', '<span class="' . pct_color($rentAno) . '">' . pct($rentAno) . '</span>', 'bi-calendar3',
          pct($pctCdiAno, 0) . ' do CDI') ?>
  <?= kpi('Cotistas', (string)$cot['c'], 'bi-people', $tokensAtivos . ' acesso(s) ativo(s)') ?>
  <?= kpi('Caixa', moeda_compacta($fundo['caixa_atual']), 'bi-wallet2') ?>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-graph-up-arrow me-1"></i> Cota vs CDI (base 100) — apenas cotas publicadas</span>
        <div class="btn-group btn-group-sm" role="group">
          <?php foreach (['1m' => '1M', '3m' => '3M', '6m' => '6M', '12m' => '12M', 'ini' => 'Início'] as $k => $r): ?>
            <button class="btn btn-outline-secondary <?= $k === '12m' ? 'active' : '' ?>" data-periodo="<?= $k ?>"><?= $r ?></button>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="card-body grafico-box" style="height:330px"><canvas id="graf-cota"></canvas></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-pie-chart me-1"></i> Composição da carteira</div>
      <div class="card-body grafico-box" style="height:330px"><canvas id="graf-composicao"></canvas></div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-bell me-1"></i> Alertas e avisos</div>
      <div class="card-body">
        <?php if ($enqOk): ?>
          <div class="alert alert-success py-2 mb-2" style="font-size:.85rem"><i class="bi bi-check-circle me-1"></i> Fundo <b>enquadrado</b> na política de investimento.</div>
        <?php else: ?>
          <div class="alert alert-danger py-2 mb-2" style="font-size:.85rem"><i class="bi bi-exclamation-triangle me-1"></i>
            <b>Desenquadrado:</b> <?= e_html(implode('; ', $violadas)) ?> — <a href="enquadramento.php">ver detalhes</a></div>
        <?php endif; ?>
        <?php foreach ($avisos as $av): ?>
          <div class="border-bottom py-2" style="font-size:.85rem">
            <b><?= e_html($av['titulo']) ?></b>
            <span class="text-muted float-end"><?= data_br($av['data_pub']) ?></span><br>
            <span class="text-muted"><?= e_html(mb_substr($av['mensagem'], 0, 120)) ?>…</span>
          </div>
        <?php endforeach; ?>
        <a class="d-inline-block mt-2 link-limpo" style="font-size:.85rem" href="comunicados.php">Ver todos os comunicados →</a>
      </div>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-arrow-left-right me-1"></i> Últimas movimentações de caixa</div>
      <div class="card-body p-0">
        <table class="table mb-0">
          <thead><tr><th>Data</th><th>Tipo</th><th>Descrição</th><th class="text-end">Valor</th></tr></thead>
          <tbody>
          <?php foreach ($ultMov as $m): ?>
            <tr>
              <td><?= data_br($m['data_ref']) ?></td>
              <td><?= badge($m['tipo'], (float)$m['valor'] >= 0 ? 'success' : 'warning') ?></td>
              <td class="text-muted"><?= e_html($m['descricao']) ?></td>
              <td class="text-end <?= pct_color((float)$m['valor']) ?>"><?= moeda($m['valor']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  iniciarGraficoCota('graf-cota', '<?= BASE_URL ?>', <?= $fid ?>);
  graficoRosca('graf-composicao',
    <?= json_encode(array_keys($porClasse)) ?>,
    <?= json_encode(array_map(fn($v) => round($v, 2), array_values($porClasse))) ?>);
});
</script>
<?php page_end();
