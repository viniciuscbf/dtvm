<?php
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

$u = exigir_perfil('admin');
$msg = '';
$erroSeg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_validar()) {
    $_POST = [];
    $erroSeg = 'Requisição inválida (proteção CSRF). Recarregue a página e tente novamente.';
}

// AÇÃO REAL: instruir pagamento de um repasse
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['instruir'])) {
    $pdo->prepare("UPDATE repasses SET status='Instruído' WHERE id=? AND status='Apurado'")->execute([(int)$_POST['instruir']]);
    $msg = 'Repasse instruído para pagamento.';
}

$competencias = $pdo->query("SELECT DISTINCT competencia FROM repasses ORDER BY competencia DESC")->fetchAll(PDO::FETCH_COLUMN);
$comp = $_GET['competencia'] ?? ($competencias[0] ?? '');

$st = $pdo->prepare("SELECT r.*, f.nome fundo_nome FROM repasses r JOIN fundos f ON f.id=r.fundo_id
                     WHERE r.competencia=? ORDER BY r.taxa_adm_valor DESC");
$st->execute([$comp]);
$repasses = $st->fetchAll();

$totAdm = array_sum(array_map('floatval', array_column($repasses, 'taxa_adm_valor')));
$totBanco = array_sum(array_map('floatval', array_column($repasses, 'parte_banco')));
$totNos = array_sum(array_map('floatval', array_column($repasses, 'parte_adm')));
$totGestao = array_sum(array_map('floatval', array_column($repasses, 'taxa_gestao_valor')));

// evolução mensal (todas as competências)
$evolucao = $pdo->query("SELECT competencia, SUM(taxa_adm_valor) receita, SUM(parte_banco) banco
                         FROM repasses GROUP BY competencia ORDER BY competencia")->fetchAll();

page_start('Repasses & Receita', 'Repasses', $u,
    'Apuração 0,08% a.a. com piso de R$ 100/mês · split 25% banco / 75% administradora');
?>

<?php if ($msg): ?><div class="alert alert-success py-2"><i class="bi bi-check-circle me-1"></i><?= e_html($msg) ?></div><?php endif; ?>
<?php if ($erroSeg): ?><div class="alert alert-warning py-2"><i class="bi bi-exclamation-triangle me-1"></i><?= e_html($erroSeg) ?></div><?php endif; ?>

<div class="row row-cols-2 row-cols-md-4 g-3 mb-4">
  <?= kpi('Receita de adm. (' . e_html($comp) . ')', moeda($totAdm, 0), 'bi-cash-stack', 'projeção anual: ' . moeda_compacta($totAdm * 12)) ?>
  <?= kpi('Parte do banco (25%)', '<span class="split-banco">' . moeda($totBanco, 0) . '</span>', 'bi-bank', 'anualizado: ' . moeda_compacta($totBanco * 12)) ?>
  <?= kpi('Parte da administradora (75%)', moeda($totNos, 0), 'bi-building', 'anualizado: ' . moeda_compacta($totNos * 12)) ?>
  <?= kpi('Repasse de gestão (gestores)', moeda($totGestao, 0), 'bi-arrow-right-circle', 'passa pela plataforma') ?>
</div>

<div class="row g-3 mb-4">
  <div class="col-12">
    <div class="card">
      <div class="card-header"><i class="bi bi-bar-chart me-1"></i> Evolução da receita mensal</div>
      <div class="card-body grafico-box" style="height:280px"><canvas id="graf-receita"></canvas></div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-table me-1"></i> Apuração por fundo</span>
    <form method="get">
      <select class="form-select form-select-sm" name="competencia" onchange="this.form.submit()">
        <?php foreach ($competencias as $c): ?>
          <option value="<?= e_html($c) ?>" <?= $c === $comp ? 'selected' : '' ?>><?= e_html($c) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
  <div class="card-body p-0">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Fundo</th><th class="text-end">PL médio</th><th class="text-end">Taxa adm.</th><th class="text-center">Piso?</th>
        <th class="text-end">Banco (25%)</th><th class="text-end">Adm. (75%)</th><th class="text-end">Gestão</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($repasses as $r): ?>
        <tr>
          <td><b><?= e_html($r['fundo_nome']) ?></b></td>
          <td class="text-end"><?= moeda_compacta($r['pl_medio']) ?></td>
          <td class="text-end"><b><?= moeda($r['taxa_adm_valor']) ?></b></td>
          <td class="text-center"><?= $r['piso_aplicado'] ? badge('piso R$ 100', 'warning') : '<span class="text-muted">—</span>' ?></td>
          <td class="text-end split-banco"><?= moeda($r['parte_banco']) ?></td>
          <td class="text-end"><?= moeda($r['parte_adm']) ?></td>
          <td class="text-end text-muted"><?= moeda($r['taxa_gestao_valor']) ?></td>
          <td><?= badge_status($r['status']) ?></td>
          <td class="text-end">
            <?php if ($r['status'] === 'Apurado'): ?>
              <form method="post"><?= csrf_campo() ?><input type="hidden" name="instruir" value="<?= (int)$r['id'] ?>">
                <button class="btn btn-sm btn-outline-dark"><i class="bi bi-send me-1"></i>Instruir</button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot class="table-light"><tr>
        <th>Total</th><th></th><th class="text-end"><?= moeda($totAdm) ?></th><th></th>
        <th class="text-end split-banco"><?= moeda($totBanco) ?></th>
        <th class="text-end"><?= moeda($totNos) ?></th>
        <th class="text-end"><?= moeda($totGestao) ?></th><th colspan="2"></th>
      </tr></tfoot>
    </table>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  graficoBarras('graf-receita',
    <?= json_encode(array_column($evolucao, 'competencia')) ?>,
    [{ label: 'Administradora (75%)', data: <?= json_encode(array_map(fn($e) => round((float)$e['receita'] - (float)$e['banco'], 2), $evolucao)) ?>, cor: '#14b8a6' },
     { label: 'Banco parceiro (25%)', data: <?= json_encode(array_map(fn($e) => round((float)$e['banco'], 2), $evolucao)) ?>, cor: '#c9a227' }],
    true);
});
</script>
<?php page_end();
