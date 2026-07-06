<?php
// Aprovação de cota (D-1): o gestor valida a prévia calculada pela administradora
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

$u = exigir_perfil('gestor', 'admin');
$fundo = fundo_do_usuario($pdo, $u);
if (!$fundo) die('Sem fundo vinculado.');
exigir_fundo_ativo($fundo);
$fid = (int)$fundo['id'];
$msg = ''; $msgTipo = 'success';

/** Publica a cota aprovada em cotas_historico, propagando para frente se for retroativa. */
function publicar_cota(PDO $pdo, int $fid, string $data, float $cota, float $pl): void {
    $st = $pdo->prepare('SELECT valor_cota FROM cotas_historico WHERE fundo_id = ? AND data_ref = ?');
    $st->execute([$fid, $data]);
    $antiga = $st->fetchColumn();
    if ($antiga !== false) {
        $pdo->prepare('UPDATE cotas_historico SET valor_cota = ?, pl = ? WHERE fundo_id = ? AND data_ref = ?')
            ->execute([$cota, $pl, $fid, $data]);
        // reprocessamento retroativo: propaga o fator para os dias seguintes e marca-os como republicados
        if ((float)$antiga > 0 && abs($cota / (float)$antiga - 1) > 1e-9) {
            $fator = $cota / (float)$antiga;
            $pdo->prepare('UPDATE cotas_historico SET valor_cota = valor_cota * ?, pl = pl * ? WHERE fundo_id = ? AND data_ref > ?')
                ->execute([$fator, $fator, $fid, $data]);
            $pdo->prepare("UPDATE fechamentos SET status='Republicada' WHERE fundo_id = ? AND data_ref > ? AND status IN ('Aprovada','Republicada')")
                ->execute([$fid, $data]);
        }
    } else {
        $pdo->prepare('INSERT INTO cotas_historico (fundo_id, data_ref, valor_cota, pl) VALUES (?,?,?,?)')
            ->execute([$fid, $data, $cota, $pl]);
    }
    // atualiza o espelho no cadastro do fundo com a última cota publicada
    $st = $pdo->prepare('SELECT valor_cota, pl FROM cotas_historico WHERE fundo_id = ? ORDER BY data_ref DESC LIMIT 1');
    $st->execute([$fid]);
    if ($ult = $st->fetch()) {
        $pdo->prepare('UPDATE fundos SET cota_atual = ?, pl_atual = ? WHERE id = ?')
            ->execute([$ult['valor_cota'], $ult['pl'], $fid]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['fechamento_id'])) {
    $st = $pdo->prepare("SELECT * FROM fechamentos WHERE id = ? AND fundo_id = ? AND status = 'Aguardando aprovação'");
    $st->execute([(int)$_POST['fechamento_id'], $fid]);
    if ($f = $st->fetch()) {
        if (($_POST['acao'] ?? '') === 'aprovar') {
            $pdo->prepare("UPDATE fechamentos SET status='Aprovada', decidido_por=?, decidido_em=NOW() WHERE id=?")
                ->execute([$u['nome'], $f['id']]);
            publicar_cota($pdo, $fid, $f['data_ref'], (float)$f['valor_cota'], (float)$f['pl']);
            $msg = 'Cota de ' . data_br($f['data_ref']) . ' aprovada e publicada — os relatórios do dia passam de PRÉVIA a OFICIAL.';
        } elseif (($_POST['acao'] ?? '') === 'rejeitar' && trim($_POST['motivo'] ?? '') !== '') {
            $pdo->prepare("UPDATE fechamentos SET status='Rejeitada', decidido_por=?, decidido_em=NOW(), motivo=? WHERE id=?")
                ->execute([$u['nome'], trim($_POST['motivo']), $f['id']]);
            $msg = 'Cota de ' . data_br($f['data_ref']) . ' rejeitada — a controladoria foi notificada para corrigir e reprocessar.';
            $msgTipo = 'warning';
        } else {
            $msg = 'Para rejeitar, informe o motivo da divergência.'; $msgTipo = 'danger';
        }
    }
}

// fechamentos (última versão por data)
$st = $pdo->prepare("SELECT f1.* FROM fechamentos f1
                     JOIN (SELECT data_ref, MAX(versao) mv FROM fechamentos WHERE fundo_id = ? GROUP BY data_ref) f2
                       ON f2.data_ref = f1.data_ref AND f2.mv = f1.versao
                     WHERE f1.fundo_id = ? ORDER BY f1.data_ref DESC LIMIT 30");
$st->execute([$fid, $fid]);
$fechamentos = $st->fetchAll();

$pendentes = array_filter($fechamentos, fn($f) => $f['status'] === 'Aguardando aprovação');

// versões da data pendente (para mostrar o histórico de reprocessamento)
function versoes(PDO $pdo, int $fid, string $data): array {
    $st = $pdo->prepare('SELECT * FROM fechamentos WHERE fundo_id = ? AND data_ref = ? ORDER BY versao');
    $st->execute([$fid, $data]);
    return $st->fetchAll();
}

page_start('Aprovação de cota', 'Aprovação de cota', $u,
    e_html($fundo['nome']) . ' · batimento diário: a administradora fecha a cota de D-1 e o gestor valida antes da publicação');
?>

<?php if ($msg): ?><div class="alert alert-<?= $msgTipo ?> py-2"><i class="bi bi-info-circle me-1"></i><?= e_html($msg) ?></div><?php endif; ?>

<?php if ($pendentes): foreach ($pendentes as $f):
    $st = $pdo->prepare('SELECT valor_cota FROM cotas_historico WHERE fundo_id = ? AND data_ref < ? ORDER BY data_ref DESC LIMIT 1');
    $st->execute([$fid, $f['data_ref']]);
    $cotaAnterior = (float)($st->fetchColumn() ?: 0);
    $vs = versoes($pdo, $fid, $f['data_ref']); ?>
  <div class="card mb-4 border-warning">
    <div class="card-header" style="background:#fffbeb">
      <i class="bi bi-clipboard-check me-1"></i> Prévia de cota aguardando sua aprovação — <b><?= data_br($f['data_ref']) ?></b>
      <?= count($vs) > 1 ? badge('versão ' . $f['versao'] . ' (reprocessada)', 'info') : '' ?>
    </div>
    <div class="card-body">
      <div class="row g-3 mb-3">
        <?= kpi('Cota calculada', number_format((float)$f['valor_cota'], 8, ',', '.'), 'bi-graph-up',
                $cotaAnterior > 0 ? '<span class="' . pct_color(((float)$f['valor_cota'] / $cotaAnterior - 1) * 100) . '">' .
                pct(((float)$f['valor_cota'] / $cotaAnterior - 1) * 100, 4) . ' vs D-2</span>' : '') ?>
        <?= kpi('PL apurado', moeda($f['pl']), 'bi-safe') ?>
        <?= kpi('Calculada em', $f['calculada_em'] ? date('d/m/Y H:i', strtotime($f['calculada_em'])) : '—', 'bi-clock') ?>
        <?= kpi('Conferência', '<a href="carteira.php?data=' . e_html($f['data_ref']) . '" class="link-limpo">abrir carteira do dia →</a>', 'bi-search') ?>
      </div>
      <?php if (count($vs) > 1): ?>
        <div class="alert alert-secondary py-2" style="font-size:.82rem">
          <b>Histórico de versões desta data:</b>
          <?php foreach ($vs as $v): ?>
            <div>v<?= $v['versao'] ?> · cota <?= number_format((float)$v['valor_cota'], 8, ',', '.') ?> · <?= badge_status($v['status']) ?>
              <?= $v['motivo'] ? '<span class="text-muted">— ' . e_html($v['motivo']) . '</span>' : '' ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <div class="d-flex gap-2 flex-wrap align-items-start">
        <form method="post">
          <input type="hidden" name="fechamento_id" value="<?= (int)$f['id'] ?>">
          <button class="btn btn-success" name="acao" value="aprovar"
                  onclick="return confirm('Aprovar a cota de <?= data_br($f['data_ref']) ?>? Ela será publicada aos cotistas.')">
            <i class="bi bi-check-lg me-1"></i>Aprovar cota</button>
        </form>
        <form method="post" class="d-flex gap-2 flex-grow-1" style="max-width:560px">
          <input type="hidden" name="fechamento_id" value="<?= (int)$f['id'] ?>">
          <input class="form-control form-control-sm" name="motivo" placeholder="Motivo da rejeição (ex.: preço da DEB VALE29 divergente do nosso controle)…">
          <button class="btn btn-outline-danger" name="acao" value="rejeitar"><i class="bi bi-x-lg me-1"></i>Rejeitar</button>
        </form>
      </div>
    </div>
  </div>
<?php endforeach; else: ?>
  <div class="alert alert-success py-2"><i class="bi bi-check-circle me-1"></i>Nenhuma prévia pendente — todas as cotas estão validadas.</div>
<?php endif; ?>

<div class="card">
  <div class="card-header"><i class="bi bi-clock-history me-1"></i> Histórico de fechamentos (30 dias)</div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0" style="font-size:.86rem">
      <thead><tr><th>Data</th><th class="text-end">Cota</th><th class="text-end">PL</th><th class="text-center">Versão</th>
        <th>Status</th><th>Decisão</th><th class="text-center">Relatórios</th></tr></thead>
      <tbody>
      <?php foreach ($fechamentos as $f): ?>
        <tr>
          <td><b><?= data_br($f['data_ref']) ?></b></td>
          <td class="text-end"><?= number_format((float)$f['valor_cota'], 8, ',', '.') ?></td>
          <td class="text-end"><?= moeda_compacta($f['pl']) ?></td>
          <td class="text-center"><?= (int)$f['versao'] ?></td>
          <td><?= badge_status($f['status']) ?>
            <?= $f['motivo'] && $f['status'] === 'Rejeitada' ? '<br><span class="text-muted" style="font-size:.72rem">' . e_html($f['motivo']) . '</span>' : '' ?></td>
          <td class="text-muted" style="font-size:.78rem">
            <?= $f['decidido_por'] ? e_html($f['decidido_por']) . '<br>' . ($f['decidido_em'] ? date('d/m H:i', strtotime($f['decidido_em'])) : '') : '—' ?></td>
          <td class="text-center"><?= in_array($f['status'], ['Aprovada', 'Republicada'], true)
              ? badge('OFICIAL', 'success')
              : badge('PRÉVIA', 'warning') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<p class="text-muted mt-3" style="font-size:.78rem"><i class="bi bi-info-circle me-1"></i>
Fluxo real reproduzido no piloto: a controladoria precifica, bate a carteira e fecha a prévia de D-1 → você
<b>confere</b> (a carteira e os relatórios do dia já ficam disponíveis como PRÉVIA justamente para isso) e aprova
(publica — vira OFICIAL) ou rejeita apontando a divergência → a controladoria corrige via lançamentos,
<b>reprocessa</b> (nova versão) e reenvia.</p>
<?php page_end();
