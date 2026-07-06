<?php
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

$u = exigir_perfil('admin');
$msg = '';

// AÇÕES REAIS: resolver ou escalar divergência
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['resolver'])) {
        $pdo->prepare("UPDATE conciliacao SET situacao='Resolvido', resolucao=?, resolvido_por=?, resolvido_em=NOW() WHERE id=?")
            ->execute([trim($_POST['justificativa'] ?? 'Resolvida na operação'), $u['nome'], (int)$_POST['resolver']]);
        $msg = 'Divergência marcada como resolvida — registrada na trilha de auditoria.';
    } elseif (!empty($_POST['escalar'])) {
        $st = $pdo->prepare('SELECT * FROM conciliacao WHERE id = ?');
        $st->execute([(int)$_POST['escalar']]);
        if ($c = $st->fetch()) {
            $pdo->prepare("INSERT INTO alertas_fraude (fundo_id, data_ref, regra, tipo, severidade, explicacao, evidencia, status)
                           VALUES (?,?,?,?,?,?,?, 'Aberto')")
                ->execute([$c['fundo_id'], $c['data_ref'], 'R4', 'Divergência escalada',
                           'Alta', 'Divergência de conciliação escalada pela operação: ' . $c['detalhe'],
                           'Diferença de ' . moeda($c['valor_diferenca']) . ' em ' . $c['origem'], ]);
            $pdo->prepare("UPDATE conciliacao SET classificacao='Suspeita' WHERE id=?")->execute([(int)$_POST['escalar']]);
            $msg = 'Divergência escalada ao compliance — alerta criado no painel de IA.';
        }
    }
}

$st = $pdo->query("SELECT c.*, f.nome fundo_nome FROM conciliacao c JOIN fundos f ON f.id = c.fundo_id
                   ORDER BY FIELD(c.situacao,'Divergente','Resolvido','Conciliado'), c.data_ref DESC");
$todas = $st->fetchAll();
$divergentes = array_filter($todas, fn($c) => $c['situacao'] === 'Divergente');
$resolvidas = array_filter($todas, fn($c) => $c['situacao'] === 'Resolvido');
$conciliadas = array_filter($todas, fn($c) => $c['situacao'] === 'Conciliado');

page_start('Conciliação', 'Conciliação', $u,
    'Posição × Custodiante · Operações × Gestor · Caixa × Extrato — a prova de diligência da administradora');
?>

<?php if ($msg): ?><div class="alert alert-success py-2"><i class="bi bi-check-circle me-1"></i><?= e_html($msg) ?></div><?php endif; ?>

<div class="row row-cols-3 g-3 mb-4">
  <?= kpi('Conciliadas hoje', (string)count($conciliadas), 'bi-check2-square') ?>
  <?= kpi('Divergências abertas', '<span class="' . (count($divergentes) ? 'text-danger' : '') . '">' . count($divergentes) . '</span>', 'bi-exclamation-diamond') ?>
  <?= kpi('Resolvidas (trilha)', (string)count($resolvidas), 'bi-journal-check') ?>
</div>

<?php if ($divergentes): ?>
<div class="card mb-4 border-danger">
  <div class="card-header text-danger"><i class="bi bi-exclamation-triangle me-1"></i> Divergências abertas — exigem ação</div>
  <div class="card-body p-0">
    <table class="table mb-0 align-middle">
      <thead><tr><th>Fundo</th><th>Frente</th><th>Classificação</th><th>Detalhe</th><th class="text-end">Diferença</th><th style="min-width:290px"></th></tr></thead>
      <tbody>
      <?php foreach ($divergentes as $c): ?>
        <tr>
          <td><b><?= e_html($c['fundo_nome']) ?></b><br><span class="text-muted" style="font-size:.75rem"><?= data_br($c['data_ref']) ?></span></td>
          <td style="font-size:.83rem"><?= e_html($c['origem']) ?></td>
          <td><?= badge_status($c['classificacao']) ?></td>
          <td class="text-muted" style="font-size:.83rem"><?= e_html($c['detalhe']) ?></td>
          <td class="text-end text-danger"><b><?= moeda($c['valor_diferenca']) ?></b></td>
          <td>
            <form method="post" class="d-flex gap-1">
              <input type="hidden" name="resolver" value="<?= (int)$c['id'] ?>">
              <input class="form-control form-control-sm" name="justificativa" placeholder="Justificativa…" required>
              <button class="btn btn-sm btn-success" title="Marcar como resolvida"><i class="bi bi-check-lg"></i></button>
            </form>
            <form method="post" class="mt-1">
              <input type="hidden" name="escalar" value="<?= (int)$c['id'] ?>">
              <button class="btn btn-sm btn-outline-danger w-100"><i class="bi bi-shield-exclamation me-1"></i>Escalar ao compliance</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-check2-all me-1"></i> Frentes conciliadas hoje</div>
      <div class="card-body p-0" style="max-height:340px;overflow-y:auto">
        <table class="table table-sm table-hover mb-0" style="font-size:.84rem">
          <thead><tr><th>Fundo</th><th>Frente</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($conciliadas as $c): ?>
            <tr><td><?= e_html($c['fundo_nome']) ?></td><td><?= e_html($c['origem']) ?></td><td><?= badge_status('Conciliado') ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-journal-check me-1"></i> Trilha de auditoria — divergências resolvidas</div>
      <div class="card-body p-0" style="max-height:340px;overflow-y:auto">
        <?php foreach ($resolvidas as $c): ?>
          <div class="p-2 px-3 border-bottom" style="font-size:.82rem">
            <b><?= e_html($c['fundo_nome']) ?></b> · <?= e_html($c['origem']) ?> · <?= moeda($c['valor_diferenca']) ?><br>
            <span class="text-muted"><?= e_html($c['detalhe']) ?></span><br>
            <i class="bi bi-check-circle text-success me-1"></i><?= e_html($c['resolucao']) ?>
            <span class="text-muted">— <?= e_html($c['resolvido_por']) ?>, <?= $c['resolvido_em'] ? date('d/m/Y H:i', strtotime($c['resolvido_em'])) : '' ?></span>
          </div>
        <?php endforeach; ?>
        <?php if (!$resolvidas): ?><p class="text-muted text-center py-4 mb-0" style="font-size:.85rem">Nenhuma resolução registrada ainda.</p><?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php page_end();
