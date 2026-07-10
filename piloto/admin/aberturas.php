<?php
// Aberturas de fundos — análise documental, avanço de etapas e lançamento do fundo
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

$u = exigir_perfil('admin');
$msg = ''; $msgTipo = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_validar()) {
    $_POST = [];
    $msg = 'Requisição inválida (proteção CSRF). Recarregue a página e tente novamente.'; $msgTipo = 'danger';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // aprovar / rejeitar documento
    if (!empty($_POST['doc_id'])) {
        $acao = $_POST['acao'] ?? '';
        if ($acao === 'aprovar') {
            $pdo->prepare("UPDATE documentos_abertura SET status='Aprovado', motivo=NULL, atualizado_em=NOW() WHERE id=?")
                ->execute([(int)$_POST['doc_id']]);
            $msg = 'Documento aprovado.';
        } elseif ($acao === 'rejeitar' && trim($_POST['motivo'] ?? '') !== '') {
            $pdo->prepare("UPDATE documentos_abertura SET status='Rejeitado', motivo=?, atualizado_em=NOW() WHERE id=?")
                ->execute([trim($_POST['motivo']), (int)$_POST['doc_id']]);
            $msg = 'Documento rejeitado — o gestor foi notificado do motivo.'; $msgTipo = 'warning';
        } else { $msg = 'Para rejeitar, informe o motivo.'; $msgTipo = 'danger'; }
    }
    // concluir etapa atual
    elseif (!empty($_POST['concluir_etapa'])) {
        $st = $pdo->prepare('SELECT * FROM onboarding_etapas WHERE id = ?');
        $st->execute([(int)$_POST['concluir_etapa']]);
        if ($e = $st->fetch()) {
            $pdo->prepare("UPDATE onboarding_etapas SET status='Concluída', data_conclusao=CURDATE(), responsavel=? WHERE id=?")
                ->execute([$u['nome'], $e['id']]);
            $pdo->prepare("UPDATE onboarding_etapas SET status='Em andamento' WHERE fundo_id=? AND ordem=? AND status='Pendente'")
                ->execute([$e['fundo_id'], $e['ordem'] + 1]);
            $msg = 'Etapa "' . $e['etapa'] . '" concluída.';
        }
    }
    // lançar o fundo (todos os checks OK)
    elseif (!empty($_POST['lancar_fundo'])) {
        $fid = (int)$_POST['lancar_fundo'];
        $st = $pdo->prepare("SELECT COUNT(*) FROM documentos_abertura WHERE fundo_id=? AND obrigatorio=1 AND status<>'Aprovado'");
        $st->execute([$fid]);
        $docsPend = (int)$st->fetchColumn();
        $st = $pdo->prepare("SELECT COUNT(*) FROM onboarding_etapas WHERE fundo_id=? AND etapa<>'Fundo apto' AND status<>'Concluída'");
        $st->execute([$fid]);
        $etapasPend = (int)$st->fetchColumn();
        if ($docsPend === 0 && $etapasPend === 0) {
            $pdo->prepare("UPDATE fundos SET status='Ativo', cnpj=IF(cnpj='em registro', CONCAT('4', LPAD(id,1,'0'), '.', LPAD(100+id,3,'0'), '.', LPAD(900+id,3,'0'), '/0001-', LPAD(10+id,2,'0')), cnpj), data_abertura=CURDATE() WHERE id=?")->execute([$fid]);
            $pdo->prepare("UPDATE onboarding_etapas SET status='Concluída', data_conclusao=CURDATE(), responsavel=? WHERE fundo_id=? AND etapa='Fundo apto'")
                ->execute([$u['nome'], $fid]);
            $msg = 'Fundo lançado! Já aparece como Ativo no painel e o gestor ganha o portal completo.';
        } else {
            $msg = "Não é possível lançar: $docsPend documento(s) obrigatório(s) sem aprovação e $etapasPend etapa(s) pendente(s)."; $msgTipo = 'danger';
        }
    }
}

// todos os fundos com processo de abertura registrado + os demais para visão geral de status
$fundos = $pdo->query("SELECT * FROM fundos ORDER BY FIELD(status,'Em abertura','Ativo','Fechado','Encerrado'), id DESC")->fetchAll();
$emAbertura = array_filter($fundos, fn($f) => $f['status'] === 'Em abertura');

function dados_abertura(PDO $pdo, int $fid): array {
    $st = $pdo->prepare('SELECT * FROM onboarding_etapas WHERE fundo_id = ? ORDER BY ordem');
    $st->execute([$fid]);
    $etapas = $st->fetchAll();
    $st = $pdo->prepare("SELECT * FROM documentos_abertura WHERE fundo_id = ? ORDER BY FIELD(categoria,'Gestora','Responsável','Fundo'), id");
    $st->execute([$fid]);
    return [$etapas, $st->fetchAll()];
}

page_start('Aberturas de fundos', 'Aberturas de fundos', $u,
    'Análise documental e checks de lançamento — o que falta para cada fundo entrar em operação');
?>

<?php if ($msg): ?><div class="alert alert-<?= $msgTipo ?> py-2"><i class="bi bi-info-circle me-1"></i><?= e_html($msg) ?></div><?php endif; ?>

<div class="card mb-4">
  <div class="card-header"><i class="bi bi-folder2 me-1"></i> Status de todos os fundos</div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0" style="font-size:.86rem">
      <thead><tr><th>Fundo</th><th>Gestora</th><th>Status</th><th>Situação da abertura</th></tr></thead>
      <tbody>
      <?php foreach ($fundos as $f):
          $resumo = '—';
          if ($f['status'] === 'Em abertura') {
              [$ets, $dcs] = dados_abertura($pdo, (int)$f['id']);
              $atual = null; foreach ($ets as $e) if ($e['status'] !== 'Concluída') { $atual = $e; break; }
              $pend = count(array_filter($dcs, fn($d) => $d['obrigatorio'] && $d['status'] !== 'Aprovado'));
              $resumo = ($atual ? 'etapa: <b>' . e_html($atual['etapa']) . '</b>' : 'etapas concluídas') .
                        ' · ' . ($pend ? "<span class='text-danger'>$pend doc(s) sem aprovação</span>" : "<span class='text-success'>docs OK</span>");
          } ?>
        <tr>
          <td><b><?= e_html($f['nome']) ?></b></td>
          <td style="font-size:.8rem"><?= e_html($f['gestora']) ?></td>
          <td><?= badge_status($f['status']) ?></td>
          <td style="font-size:.8rem"><?= $resumo ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php foreach ($emAbertura as $f):
    [$etapas, $docs] = dados_abertura($pdo, (int)$f['id']);
    $docsPend = array_filter($docs, fn($d) => $d['obrigatorio'] && $d['status'] !== 'Aprovado');
    $etapaAtual = null; foreach ($etapas as $e) if ($e['status'] !== 'Concluída' && $e['etapa'] !== 'Fundo apto') { $etapaAtual = $e; break; }
    $prontoParaLancar = !$docsPend && !$etapaAtual; ?>
  <div class="card mb-4 <?= $prontoParaLancar ? 'border-success' : 'border-warning' ?>">
    <div class="card-header d-flex justify-content-between align-items-center" style="background:<?= $prontoParaLancar ? '#f0fdf4' : '#fffbeb' ?>">
      <span><i class="bi bi-rocket-takeoff me-1"></i> <b><?= e_html($f['nome']) ?></b>
        <span class="text-muted" style="font-size:.78rem">· <?= e_html($f['gestora']) ?> · <?= e_html($f['classe']) ?> · <?= e_html($f['publico_alvo']) ?></span></span>
      <form method="post" onsubmit="return confirm('Lançar o fundo <?= e_html($f['nome']) ?>? Ele passa a Ativo e o gestor ganha acesso completo.')">
        <?= csrf_campo() ?>
        <input type="hidden" name="lancar_fundo" value="<?= (int)$f['id'] ?>">
        <button class="btn btn-sm <?= $prontoParaLancar ? 'btn-success' : 'btn-outline-secondary' ?>" <?= $prontoParaLancar ? '' : 'disabled title="Há pendências no checklist"' ?>>
          <i class="bi bi-flag me-1"></i>Lançar fundo</button>
      </form>
    </div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-lg-4">
          <div class="text-muted mb-2" style="font-size:.76rem;font-weight:700">ETAPAS OPERACIONAIS</div>
          <?php foreach ($etapas as $e): ?>
            <div class="d-flex justify-content-between align-items-center border-bottom py-1" style="font-size:.83rem">
              <span><?= (int)$e['ordem'] ?>. <?= e_html($e['etapa']) ?></span>
              <span class="d-flex gap-1 align-items-center">
                <?= badge_status($e['status']) ?>
                <?php if ($e['status'] === 'Em andamento' && $e['etapa'] !== 'Fundo apto'): ?>
                  <form method="post"><?= csrf_campo() ?><input type="hidden" name="concluir_etapa" value="<?= (int)$e['id'] ?>">
                    <button class="btn btn-sm btn-outline-success py-0" title="Marcar como concluída"><i class="bi bi-check-lg"></i></button></form>
                <?php endif; ?>
              </span>
            </div>
          <?php endforeach; ?>
          <div class="alert <?= $prontoParaLancar ? 'alert-success' : 'alert-warning' ?> py-2 mt-3 mb-0" style="font-size:.78rem">
            <?php if ($prontoParaLancar): ?>
              <i class="bi bi-check-circle me-1"></i><b>Pronto para lançar</b> — todos os checks aprovados.
            <?php else: ?>
              <i class="bi bi-exclamation-triangle me-1"></i><b>Falta:</b>
              <?= $etapaAtual ? 'etapa "' . e_html($etapaAtual['etapa']) . '"' : '' ?>
              <?= $etapaAtual && $docsPend ? ' + ' : '' ?>
              <?= $docsPend ? count($docsPend) . ' documento(s) obrigatório(s) aprovado(s)' : '' ?>
            <?php endif; ?>
          </div>
        </div>
        <div class="col-lg-8">
          <div class="text-muted mb-2" style="font-size:.76rem;font-weight:700">ANÁLISE DOCUMENTAL</div>
          <div style="max-height:340px;overflow-y:auto">
            <table class="table table-sm align-middle mb-0" style="font-size:.8rem">
              <tbody>
              <?php foreach ($docs as $d): ?>
                <tr class="<?= $d['status'] === 'Rejeitado' ? 'table-danger' : '' ?>">
                  <td style="width:42%"><?= e_html($d['nome']) ?> <?= $d['obrigatorio'] ? '<span class="text-danger">*</span>' : '' ?>
                    <?php if ($d['arquivo']): ?><br><span class="text-muted" style="font-size:.7rem"><i class="bi bi-paperclip"></i> <?= e_html($d['arquivo']) ?></span><?php endif; ?>
                    <?php if (!empty($d['conteudo'])): ?><br><a href="../gestor/documento_ver.php?id=<?= (int)$d['id'] ?>" style="font-size:.72rem"><i class="bi bi-file-earmark-text me-1"></i>ver minuta gerada</a><?php endif; ?></td>
                  <td><?= badge($d['categoria'], 'secondary') ?></td>
                  <td><?= badge_status($d['status']) ?></td>
                  <td>
                    <?php if (in_array($d['status'], ['Recebido', 'Rejeitado', 'Aprovado'], true)): ?>
                      <form method="post" class="d-flex gap-1">
                        <?= csrf_campo() ?>
                        <input type="hidden" name="doc_id" value="<?= (int)$d['id'] ?>">
                        <?php if ($d['status'] !== 'Aprovado'): ?>
                          <button class="btn btn-sm btn-outline-success py-0" name="acao" value="aprovar" <?= $d['status'] === 'Pendente' ? 'disabled' : '' ?>><i class="bi bi-check-lg"></i></button>
                        <?php endif; ?>
                        <?php if ($d['status'] === 'Recebido'): ?>
                          <input class="form-control form-control-sm" name="motivo" placeholder="motivo p/ rejeitar" style="max-width:200px">
                          <button class="btn btn-sm btn-outline-danger py-0" name="acao" value="rejeitar"><i class="bi bi-x-lg"></i></button>
                        <?php endif; ?>
                      </form>
                    <?php else: ?>
                      <span class="text-muted" style="font-size:.74rem">aguardando envio do gestor</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
<?php endforeach; ?>
<?php if (!$emAbertura): ?>
  <div class="alert alert-secondary">Nenhum fundo em processo de abertura no momento.
    Novos pedidos chegam pelo cadastro público do <a href="../gestor/cadastro.php">Portal do Gestor</a>.</div>
<?php endif; ?>
<?php page_end();
