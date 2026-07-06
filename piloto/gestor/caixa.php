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
exigir_permissao($pdo, $u, $fid, 'ver_caixa');

// visão retroativa: posição de caixa em qualquer data com snapshot
$datas = datas_carteira($pdo, $fid);
$data = $_GET['data'] ?? ($datas[0] ?? date('Y-m-d'));
if (!in_array($data, $datas, true) && $datas) $data = $datas[0];
$ehAtual = $data === ($datas[0] ?? $data);

// saldo na data = caixa atual - movimentos posteriores à data
$st = $pdo->prepare('SELECT COALESCE(SUM(valor),0) FROM movimentacoes WHERE fundo_id = ? AND data_ref > ?');
$st->execute([$fid, $data]);
$saldoNaData = (float)$fundo['caixa_atual'] - (float)$st->fetchColumn();

$filtro = $_GET['tipo'] ?? '';
$sql = 'SELECT * FROM movimentacoes WHERE fundo_id = ? AND data_ref <= ?' . ($filtro ? ' AND tipo = ?' : '') . ' ORDER BY data_ref DESC, id DESC LIMIT 60';
$st = $pdo->prepare($sql);
$st->execute($filtro ? [$fid, $data, $filtro] : [$fid, $data]);
$movs = $st->fetchAll();

$st = $pdo->prepare('SELECT DISTINCT tipo FROM movimentacoes WHERE fundo_id = ? ORDER BY tipo');
$st->execute([$fid]);
$tipos = array_column($st->fetchAll(), 'tipo');

// entradas/saídas por mês (6 meses até a data)
$st = $pdo->prepare("SELECT DATE_FORMAT(data_ref,'%Y-%m') mes,
        SUM(CASE WHEN valor >= 0 THEN valor ELSE 0 END) entradas,
        SUM(CASE WHEN valor < 0 THEN -valor ELSE 0 END) saidas
        FROM movimentacoes WHERE fundo_id = ? AND data_ref <= ? GROUP BY mes ORDER BY mes DESC LIMIT 6");
$st->execute([$fid, $data]);
$porMes = array_reverse($st->fetchAll());

// previsão 60 dias (apenas na visão do dia mais recente)
$previsoes = []; $temNegativo = false;
if ($ehAtual) {
    $st = $pdo->prepare('SELECT * FROM previsao_caixa WHERE fundo_id = ? AND data_prevista >= CURDATE() ORDER BY data_prevista LIMIT 40');
    $st->execute([$fid]);
    $previsoes = $st->fetchAll();
    $saldoProj = (float)$fundo['caixa_atual'];
    foreach ($previsoes as &$p) {
        $saldoProj += (float)$p['valor'];
        $p['saldo_projetado'] = $saldoProj;
        if ($saldoProj < 0) $temNegativo = true;
    }
    unset($p);
}

$liberada = data_liberada($pdo, $fid, $data, $u['perfil']);

page_start('Caixa & Fluxo', 'Caixa & Fluxo', $u, e_html($fundo['nome']) . ' · posição de ' . data_br($data));
?>

<div class="d-flex gap-2 align-items-center flex-wrap mb-3">
  <form method="get" class="d-flex gap-2 align-items-center">
    <label class="text-muted" style="font-size:.8rem"><i class="bi bi-calendar3 me-1"></i>Posição em</label>
    <select name="data" class="form-select form-select-sm" style="max-width:180px" onchange="this.form.submit()">
      <?php foreach ($datas as $d): ?>
        <option value="<?= $d ?>" <?= $d === $data ? 'selected' : '' ?>><?= data_br($d) ?><?= $d === $datas[0] ? ' (D-1)' : '' ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <?php if ($liberada): ?>
    <?= selo_dia($pdo, $fid, $data) === 'OFICIAL' ? badge('OFICIAL', 'success') : badge('PRÉVIA — aguardando sua aprovação da cota', 'warning') ?>
  <?php else: ?>
    <?= badge('dia ainda não processado', 'secondary') ?>
  <?php endif; ?>
</div>

<div class="row row-cols-2 row-cols-md-3 g-3 mb-4">
  <?= kpi('Saldo de caixa em ' . data_br($data), moeda($saldoNaData), 'bi-wallet2') ?>
  <?= kpi('% do PL em caixa', pct((float)$fundo['pl_atual'] > 0 ? $saldoNaData / (float)$fundo['pl_atual'] * 100 : 0), 'bi-percent') ?>
  <?php if ($ehAtual): ?>
    <?= kpi('Eventos previstos (60d)', (string)count($previsoes), 'bi-calendar-week',
            $temNegativo ? '<span class="text-danger"><i class="bi bi-exclamation-triangle"></i> saldo projetado negativo</span>' : 'liquidez confortável') ?>
  <?php else: ?>
    <?= kpi('Visão retroativa', data_br($data), 'bi-clock-history', 'previsão disponível apenas em D-1') ?>
  <?php endif; ?>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-bar-chart me-1"></i> Entradas × saídas por mês (até <?= data_br($data) ?>)</div>
      <div class="card-body grafico-box" style="height:300px"><canvas id="graf-fluxo"></canvas></div>
    </div>
  </div>
  <div class="col-lg-6">
    <?php if ($ehAtual): ?>
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between">
        <span><i class="bi bi-calendar-week me-1"></i> Previsão de caixa (próximos 60 dias)</span>
        <?= $temNegativo ? badge('atenção à liquidez', 'danger') : badge('OK', 'success') ?>
      </div>
      <div class="card-body p-0" style="max-height:300px;overflow-y:auto">
        <table class="table table-sm mb-0">
          <thead><tr><th>Data</th><th>Evento</th><th class="text-end">Valor</th><th class="text-end">Saldo projetado</th></tr></thead>
          <tbody>
          <?php foreach ($previsoes as $p): ?>
            <tr class="<?= $p['saldo_projetado'] < 0 ? 'table-danger' : '' ?>">
              <td><?= data_br($p['data_prevista']) ?></td>
              <td><?= badge($p['tipo'], (float)$p['valor'] >= 0 ? 'success' : 'warning') ?>
                  <span class="text-muted" style="font-size:.78rem"><?= e_html($p['descricao']) ?></span></td>
              <td class="text-end <?= pct_color((float)$p['valor']) ?>"><?= moeda($p['valor']) ?></td>
              <td class="text-end"><b><?= moeda($p['saldo_projetado']) ?></b></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php else: ?>
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-clock-history me-1"></i> Visão retroativa</div>
      <div class="card-body text-muted" style="font-size:.85rem">
        Você está vendo o caixa como ele estava em <b><?= data_br($data) ?></b> — extrato e fluxos até essa data.
        A previsão de caixa (eventos futuros) só faz sentido na posição mais recente.
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-arrow-left-right me-1"></i> Extrato de movimentações até <?= data_br($data) ?></span>
    <form method="get">
      <input type="hidden" name="data" value="<?= e_html($data) ?>">
      <select name="tipo" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="">Todos os tipos</option>
        <?php foreach ($tipos as $t): ?>
          <option value="<?= e_html($t) ?>" <?= $t === $filtro ? 'selected' : '' ?>><?= e_html($t) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead><tr><th>Data</th><th>Tipo</th><th>Descrição</th><th class="text-end">Valor</th></tr></thead>
      <tbody>
      <?php foreach ($movs as $m): ?>
        <tr>
          <td><?= data_br($m['data_ref']) ?></td>
          <td><?= badge($m['tipo'], (float)$m['valor'] >= 0 ? 'success' : 'warning') ?></td>
          <td class="text-muted"><?= e_html($m['descricao']) ?></td>
          <td class="text-end <?= pct_color((float)$m['valor']) ?>"><b><?= moeda($m['valor']) ?></b></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  graficoBarras('graf-fluxo',
    <?= json_encode(array_column($porMes, 'mes')) ?>,
    [{ label: 'Entradas', data: <?= json_encode(array_map('floatval', array_column($porMes, 'entradas'))) ?>, cor: '#14b8a6' },
     { label: 'Saídas',   data: <?= json_encode(array_map('floatval', array_column($porMes, 'saidas'))) ?>, cor: '#ef4444' }]);
});
</script>
<?php page_end();
