<?php
// Processamento diário + ciclo de fechamento da cota (prévia → aprovação do gestor → liberação)
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

$u = exigir_perfil('admin');
$msg = ''; $msgTipo = 'success';

// ---------------- AÇÕES ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_validar()) {
    $_POST = [];
    $msg = 'Requisição inválida (proteção CSRF). Recarregue a página e tente novamente.'; $msgTipo = 'danger';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // reprocessar etapa do batch com erro
    if (!empty($_POST['reprocessar_etapa'])) {
        $st = $pdo->prepare('SELECT * FROM processamento WHERE id = ?');
        $st->execute([(int)$_POST['reprocessar_etapa']]);
        if ($p = $st->fetch()) {
            $pdo->prepare("UPDATE processamento SET status='OK', horario=CURTIME(), mensagem='Reprocessado manualmente com sucesso' WHERE id=?")->execute([$p['id']]);
            $pdo->prepare("UPDATE processamento SET status='OK', horario=CURTIME(), mensagem='Executado após reprocessamento'
                           WHERE fundo_id=? AND data_ref=? AND ordem > ? AND status IN ('Pendente','Erro')")
                ->execute([$p['fundo_id'], $p['data_ref'], $p['ordem']]);
            $pdo->prepare("INSERT INTO log_processamento (fundo_id, data_ref, etapa, nivel, mensagem) VALUES (?,?,?,?,?)")
                ->execute([$p['fundo_id'], $p['data_ref'], $p['etapa'], 'INFO', 'Etapa reprocessada por ' . $u['nome']]);
            $msg = 'Etapa "' . $p['etapa'] . '" reprocessada — esteira destravada.';
        }
    }
    // gerar prévia de cota para o gestor (calcula a partir do snapshot da carteira)
    elseif (!empty($_POST['gerar_previa'])) {
        [$fid, $data] = explode('|', $_POST['gerar_previa']);
        $fid = (int)$fid;
        $st = $pdo->prepare('SELECT * FROM fundos WHERE id = ?');
        $st->execute([$fid]);
        $fundoP = $st->fetch();
        $calc = $fundoP ? calcular_cota($pdo, $fundoP, $data) : null;
        if ($calc) {
            [$cota, $pl] = $calc;
            $st = $pdo->prepare('SELECT COALESCE(MAX(versao),0) FROM fechamentos WHERE fundo_id = ? AND data_ref = ?');
            $st->execute([$fid, $data]);
            $versao = (int)$st->fetchColumn() + 1;
            $pdo->prepare("INSERT INTO fechamentos (fundo_id, data_ref, versao, valor_cota, pl, status, calculada_em)
                           VALUES (?,?,?,?,?,'Aguardando aprovação',NOW())")
                ->execute([$fid, $data, $versao, $cota, $pl]);
            $pdo->prepare("INSERT INTO log_processamento (fundo_id, data_ref, etapa, nivel, mensagem) VALUES (?,?,?,?,?)")
                ->execute([$fid, $data, 'Cota', 'INFO',
                           "Prévia v$versao enviada ao gestor: cota " . number_format($cota, 8, ',', '.') . ' por ' . $u['nome']]);
            $msg = "Prévia (v$versao) calculada e enviada ao gestor para aprovação.";
        } else { $msg = 'Não foi possível calcular: sem snapshot de carteira ou sem cotas emitidas.'; $msgTipo = 'danger'; }
    }
    // reabrir fechamento (manda para lançamentos e reprocessamento)
    elseif (!empty($_POST['reabrir'])) {
        $st = $pdo->prepare('SELECT * FROM fechamentos WHERE id = ?');
        $st->execute([(int)$_POST['reabrir']]);
        if ($f = $st->fetch()) {
            $pdo->prepare("UPDATE fechamentos SET status='Reaberta', liberado_download=0 WHERE id=?")->execute([$f['id']]);
            header('Location: lancamentos.php?fundo_id=' . $f['fundo_id'] . '&data=' . $f['data_ref'] . '&reaberto=1'); exit;
        }
    }
}

// ---------------- DADOS ----------------
$dataBatch = $pdo->query('SELECT MAX(data_ref) FROM processamento')->fetchColumn();
$st = $pdo->prepare("SELECT p.*, f.nome fundo_nome FROM processamento p JOIN fundos f ON f.id = p.fundo_id
                     WHERE p.data_ref = ? ORDER BY f.pl_atual DESC, p.ordem");
$st->execute([$dataBatch]);
$porFundo = [];
foreach ($st->fetchAll() as $r) $porFundo[$r['fundo_nome']][] = $r;

// situação do fechamento D-1 por fundo (última versão)
$fundos = $pdo->query("SELECT * FROM fundos WHERE status='Ativo' ORDER BY pl_atual DESC")->fetchAll();
$fechamentosD1 = [];
foreach ($fundos as $f) $fechamentosD1[$f['id']] = fechamento($pdo, (int)$f['id'], $dataBatch);

// histórico de fechamentos do fundo selecionado
$fidSel = (int)($_GET['hist_fundo'] ?? ($fundos[0]['id'] ?? 0));
$st = $pdo->prepare("SELECT * FROM fechamentos WHERE fundo_id = ? ORDER BY data_ref DESC, versao DESC LIMIT 40");
$st->execute([$fidSel]);
$historico = $st->fetchAll();

$logs = $pdo->query("SELECT l.*, f.nome fundo_nome FROM log_processamento l JOIN fundos f ON f.id = l.fundo_id
                     ORDER BY l.criado_em DESC, l.id DESC LIMIT 15")->fetchAll();

$etapas = ['Posição', 'Preços', 'Caixa', 'Conciliação', 'Cota', 'ANBIMA'];
$icone = ['OK' => '<i class="bi bi-check-circle-fill text-success"></i>',
          'Erro' => '<i class="bi bi-x-circle-fill text-danger"></i>',
          'Pendente' => '<i class="bi bi-clock text-warning"></i>',
          'Rodando' => '<div class="spinner-border spinner-border-sm text-primary"></div>'];

page_start('Processamento & Cota', 'Processamento & Cota', $u,
    'Batch de ' . data_br($dataBatch) . ' (D-1) · a cota só é publicada após aprovação do gestor; o download, após a sua liberação');
?>

<?php if ($msg): ?><div class="alert alert-<?= $msgTipo ?> py-2"><i class="bi bi-info-circle me-1"></i><?= e_html($msg) ?></div><?php endif; ?>

<div class="card mb-4">
  <div class="card-header"><i class="bi bi-diagram-3 me-1"></i> Esteira do batch por fundo</div>
  <div class="card-body p-0">
    <table class="table mb-0 align-middle">
      <thead><tr><th>Fundo</th>
        <?php foreach ($etapas as $e): ?><th class="text-center"><?= $e ?></th><?php endforeach; ?>
        <th></th></tr></thead>
      <tbody>
      <?php foreach ($porFundo as $nome => $linhas):
          $porEtapa = []; $falha = null;
          foreach ($linhas as $l) { $porEtapa[$l['etapa']] = $l;
              if (in_array($l['status'], ['Erro', 'Pendente'], true) && !$falha) $falha = $l; } ?>
        <tr>
          <td style="min-width:210px"><b><?= e_html($nome) ?></b>
            <?php if ($falha && $falha['mensagem']): ?>
              <br><span class="text-muted" style="font-size:.74rem"><i class="bi bi-info-circle me-1"></i><?= e_html($falha['mensagem']) ?></span>
            <?php endif; ?></td>
          <?php foreach ($etapas as $e): $l = $porEtapa[$e] ?? null; ?>
            <td class="text-center"><?= $l ? $icone[$l['status']] ?? e_html($l['status']) : '<span class="text-muted">—</span>' ?></td>
          <?php endforeach; ?>
          <td class="text-end" style="min-width:130px">
            <?php if ($falha && $falha['status'] === 'Erro'): ?>
              <form method="post"><?= csrf_campo() ?><input type="hidden" name="reprocessar_etapa" value="<?= (int)$falha['id'] ?>">
                <button class="btn btn-sm btn-danger"><i class="bi bi-arrow-clockwise me-1"></i>Reprocessar</button></form>
            <?php elseif ($falha): ?><?= badge('aguardando', 'warning') ?>
            <?php else: ?><?= badge('batch OK', 'success') ?><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card mb-4 border-warning">
  <div class="card-header" style="background:#fffbeb"><i class="bi bi-clipboard-check me-1"></i>
    Fechamento de cota de <?= data_br($dataBatch) ?> — prévia, aprovação do gestor e liberação</div>
  <div class="card-body p-0">
    <table class="table align-middle mb-0" style="font-size:.86rem">
      <thead><tr><th>Fundo</th><th class="text-end">Cota (última versão)</th><th class="text-center">Versão</th>
        <th>Status</th><th>Decisão do gestor</th><th class="text-center">Relatórios</th><th style="min-width:210px"></th></tr></thead>
      <tbody>
      <?php foreach ($fundos as $f):
          $fe = $fechamentosD1[$f['id']]; ?>
        <tr>
          <td><b><?= e_html($f['nome']) ?></b></td>
          <td class="text-end"><?= $fe ? number_format((float)$fe['valor_cota'], 8, ',', '.') : '<span class="text-muted">—</span>' ?></td>
          <td class="text-center"><?= $fe ? 'v' . (int)$fe['versao'] : '—' ?></td>
          <td><?= $fe ? badge_status($fe['status']) : badge('Em processamento', 'warning') ?>
            <?= $fe && $fe['status'] === 'Rejeitada' && $fe['motivo'] ? '<br><span class="text-danger" style="font-size:.72rem">' . e_html($fe['motivo']) . '</span>' : '' ?></td>
          <td class="text-muted" style="font-size:.78rem"><?= $fe && $fe['decidido_por'] ? e_html($fe['decidido_por']) . ($fe['decidido_em'] ? ' · ' . date('d/m H:i', strtotime($fe['decidido_em'])) : '') : '—' ?></td>
          <td class="text-center">
            <?php if ($fe): ?>
              <?= in_array($fe['status'], ['Aprovada', 'Republicada'], true) ? badge('OFICIAL', 'success') : badge('PRÉVIA disponível', 'warning') ?>
            <?php else: ?><span class="text-muted" style="font-size:.75rem">sem cota calculada</span><?php endif; ?>
          </td>
          <td class="text-end">
            <?php if (!$fe || in_array($fe['status'], ['Em processamento', 'Rejeitada', 'Reaberta'], true)): ?>
              <form method="post" class="d-inline">
                <?= csrf_campo() ?>
                <input type="hidden" name="gerar_previa" value="<?= (int)$f['id'] ?>|<?= e_html($dataBatch) ?>">
                <button class="btn btn-sm btn-dark"><i class="bi bi-send me-1"></i><?= $fe ? 'Recalcular e reenviar' : 'Gerar prévia' ?></button>
              </form>
              <a class="btn btn-sm btn-outline-secondary" href="lancamentos.php?fundo_id=<?= (int)$f['id'] ?>&data=<?= e_html($dataBatch) ?>"
                 title="Corrigir carteira/caixa antes de recalcular"><i class="bi bi-pencil-square"></i></a>
            <?php elseif ($fe['status'] === 'Aguardando aprovação'): ?>
              <span class="text-muted" style="font-size:.76rem"><i class="bi bi-hourglass-split me-1"></i>com o gestor</span>
            <?php else: ?>
              <form method="post" class="d-inline" onsubmit="return confirm('Reabrir o fechamento? A cota sairá do ar até novo ciclo de aprovação.')">
                <?= csrf_campo() ?>
                <input type="hidden" name="reabrir" value="<?= (int)$fe['id'] ?>">
                <button class="btn btn-sm btn-outline-danger" title="Reabrir para correção"><i class="bi bi-arrow-counterclockwise"></i> Reabrir</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer text-muted" style="font-size:.75rem">
    Ciclo: batch precifica e concilia → administradora <b>gera a prévia</b> — a partir daí carteira e relatórios do dia
    já ficam disponíveis ao gestor (carimbados como PRÉVIA, para ele conferir) → gestor aprova (vira OFICIAL e publica)
    ou rejeita → rejeitou? corrija em <a href="lancamentos.php">Lançamentos & Ajustes</a> e recalcule (nova versão).
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clock-history me-1"></i> Histórico de fechamentos (todas as versões)</span>
        <form method="get">
          <select name="hist_fundo" class="form-select form-select-sm" onchange="this.form.submit()">
            <?php foreach ($fundos as $f): ?>
              <option value="<?= (int)$f['id'] ?>" <?= (int)$f['id'] === $fidSel ? 'selected' : '' ?>><?= e_html($f['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
      <div class="card-body p-0" style="max-height:360px;overflow-y:auto">
        <table class="table table-sm table-hover mb-0" style="font-size:.8rem">
          <thead><tr><th>Data</th><th class="text-center">v</th><th class="text-end">Cota</th><th>Status</th><th class="text-center">Relatórios</th></tr></thead>
          <tbody>
          <?php foreach ($historico as $h): ?>
            <tr>
              <td><?= data_br($h['data_ref']) ?></td>
              <td class="text-center"><?= (int)$h['versao'] ?></td>
              <td class="text-end"><?= number_format((float)$h['valor_cota'], 8, ',', '.') ?></td>
              <td><?= badge_status($h['status']) ?></td>
              <td class="text-center" style="font-size:.7rem">
                <?= in_array($h['status'], ['Aprovada', 'Republicada'], true) ? badge('OFICIAL', 'success') : badge('PRÉVIA', 'warning') ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-terminal me-1"></i> Log de execução</div>
      <div class="card-body p-0" style="max-height:360px;overflow-y:auto">
        <table class="table table-sm mb-0" style="font-size:.78rem">
          <tbody>
          <?php foreach ($logs as $l): ?>
            <tr>
              <td class="text-muted" style="white-space:nowrap"><?= date('d/m H:i', strtotime($l['criado_em'])) ?></td>
              <td><?= badge($l['nivel'], $l['nivel'] === 'ERRO' ? 'danger' : ($l['nivel'] === 'WARN' ? 'warning' : 'secondary')) ?></td>
              <td class="text-muted"><b><?= e_html($l['fundo_nome']) ?></b> · <?= e_html($l['mensagem']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php page_end();
