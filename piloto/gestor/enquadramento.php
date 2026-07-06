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

$st = $pdo->prepare('SELECT * FROM enquadramento_regras WHERE fundo_id = ?');
$st->execute([$fid]);
$regras = $st->fetchAll();

$st = $pdo->prepare('SELECT * FROM enquadramento_eventos WHERE fundo_id = ? ORDER BY data_evento DESC');
$st->execute([$fid]);
$eventos = $st->fetchAll();

[$enqOk, ] = situacao_enquadramento($pdo, $fundo);
$ehMinimo = fn($t) => $t === 'min_rf';   // regras de mínimo: barra "quanto tenho do mínimo"

page_start('Enquadramento', 'Enquadramento', $u,
    e_html($fundo['nome']) . ' · ' . ($enqOk ? badge('Enquadrado', 'success') : badge('Desenquadrado', 'danger')));
?>

<div class="card mb-4">
  <div class="card-header"><i class="bi bi-check-circle me-1"></i> Regras da política × posição atual (medido da carteira real)</div>
  <div class="card-body">
    <?php foreach ($regras as $r):
        [$medido, $ok] = medir_regra($pdo, $fundo, $r);
        $minimo = $ehMinimo($r['tipo_regra']);
        $usoLimite = $minimo
            ? ($r['limite'] > 0 ? min(100, $medido / (float)$r['limite'] * 100) : 0)
            : ($r['limite'] > 0 ? min(100, $medido / (float)$r['limite'] * 100) : 0);
        $cor = $ok ? ($usoLimite > 85 ? '#eab308' : '#14b8a6') : '#ef4444'; ?>
      <div class="mb-3 pb-3 border-bottom">
        <div class="d-flex justify-content-between flex-wrap" style="font-size:.9rem">
          <span><?= $ok ? '<i class="bi bi-check-circle-fill text-success me-1"></i>' : '<i class="bi bi-x-circle-fill text-danger me-1"></i>' ?>
            <?= e_html($r['descricao']) ?></span>
          <span>medido: <b><?= pct($medido, 1) ?></b> · limite: <?= $minimo ? 'mín.' : 'máx.' ?> <b><?= pct((float)$r['limite'], 1) ?></b></span>
        </div>
        <div class="limite-barra mt-2"><div style="width:<?= $usoLimite ?>%;background:<?= $cor ?>"></div></div>
      </div>
    <?php endforeach; ?>
    <?php if (!$regras): ?><p class="text-muted mb-0">Sem regras cadastradas para este fundo.</p><?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-header"><i class="bi bi-clock-history me-1"></i> Histórico de eventos de enquadramento</div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead><tr><th>Data</th><th>Regra</th><th>Causa</th><th>Situação</th><th>Prazo de reenquadramento</th></tr></thead>
      <tbody>
      <?php foreach ($eventos as $ev): ?>
        <tr>
          <td><?= data_br($ev['data_evento']) ?></td>
          <td><?= e_html($ev['regra']) ?></td>
          <td><?= badge($ev['causa'], $ev['causa'] === 'Passivo' ? 'info' : 'warning') ?>
              <span class="text-muted" style="font-size:.75rem"><?= $ev['causa'] === 'Passivo' ? '(movimento de cotistas)' : '(decisão de gestão)' ?></span></td>
          <td><?= badge_status($ev['situacao']) ?></td>
          <td><?= data_br($ev['prazo_reenquadramento']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$eventos): ?><tr><td colspan="5" class="text-muted text-center py-4">Nenhum desenquadramento registrado. 🎉</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<p class="text-muted mt-3" style="font-size:.78rem"><i class="bi bi-info-circle me-1"></i>
Desenquadramento <b>passivo</b> (ex.: resgates que mudam os percentuais) tem prazo regulamentar de reenquadramento; o <b>ativo</b> (decisão do gestor) é tratado como ocorrência de compliance. O monitoramento roda no batch diário da administradora.</p>
<?php page_end();
