<?php
// Chamados de cotistas (canal cotista ↔ gestor) — lado do gestor.
// Requer permissão 'ver_chamados_cotista' para ver; 'responder_chamados_cotista'
// para responder / encerrar / reabrir. O principal tem ambas.
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

$u = exigir_perfil('gestor', 'admin');
ensure_tickets($pdo);

$fundo = fundo_do_usuario($pdo, $u);
if (!$fundo) { header('Location: equipe.php'); exit; }
$fid = (int)$fundo['id'];
exigir_permissao($pdo, $u, $fid, 'ver_chamados_cotista');
$podeResponder = pode($pdo, $u, $fid, 'responder_chamados_cotista');

$meusFundos = fundos_do_usuario($pdo, $u);
$idsFundos  = array_map(fn($f) => (int)$f['id'], $meusFundos);
$STATUS = ['Aberto', 'Em andamento', 'Respondido', 'Encerrado'];

$msgOk = ''; $msgErr = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validar()) { $_POST = []; $msgErr = 'Sessão expirada. Tente novamente.'; }
    elseif (!$podeResponder) { $msgErr = 'Você tem acesso somente de leitura a estes chamados.'; }
    else {
        $tid = (int)($_POST['ticket_id'] ?? 0);
        $st = $pdo->prepare("SELECT * FROM tickets WHERE id=? AND canal='cotista_gestor'");
        $st->execute([$tid]);
        $t = $st->fetch();
        if (!$t || !in_array((int)$t['fundo_id'], $idsFundos, true)) {
            $msgErr = 'Chamado não encontrado.';
        } elseif (($_POST['acao'] ?? '') === 'responder') {
            $mensagem = trim($_POST['mensagem'] ?? '');
            if ($mensagem === '') { $msgErr = 'Escreva uma mensagem.'; }
            elseif ($t['status'] === 'Encerrado') { $msgErr = 'Chamado encerrado — reabra para responder.'; }
            else {
                com_transacao($pdo, function () use ($pdo, $tid, $u, $mensagem) {
                    $pdo->prepare("INSERT INTO ticket_mensagens (ticket_id, autor, perfil, mensagem) VALUES (?,?, 'gestor', ?)")
                        ->execute([$tid, $u['nome'], $mensagem]);
                    $pdo->prepare("UPDATE tickets SET status='Respondido', atualizado_em=NOW() WHERE id=?")->execute([$tid]);
                });
                registrar_auditoria($pdo, 'chamado_cotista_respondido', ['entidade' => 'ticket', 'entidade_id' => $tid, 'fundo_id' => (int)$t['fundo_id'],
                    'detalhe' => 'Gestor respondeu chamado de cotista #' . $tid]);
                $msgOk = 'Resposta enviada ao cotista.';
            }
            $_GET['id'] = $tid;
        } elseif (($_POST['acao'] ?? '') === 'encerrar') {
            $pdo->prepare("UPDATE tickets SET status='Encerrado', atualizado_em=NOW() WHERE id=?")->execute([$tid]);
            registrar_auditoria($pdo, 'chamado_cotista_encerrado', ['entidade' => 'ticket', 'entidade_id' => $tid, 'fundo_id' => (int)$t['fundo_id'], 'detalhe' => 'Chamado de cotista #' . $tid . ' encerrado']);
            $msgOk = 'Chamado encerrado.'; $_GET['id'] = $tid;
        } elseif (($_POST['acao'] ?? '') === 'reabrir') {
            $pdo->prepare("UPDATE tickets SET status='Aberto', atualizado_em=NOW() WHERE id=?")->execute([$tid]);
            registrar_auditoria($pdo, 'chamado_cotista_reaberto', ['entidade' => 'ticket', 'entidade_id' => $tid, 'fundo_id' => (int)$t['fundo_id'], 'detalhe' => 'Chamado de cotista #' . $tid . ' reaberto']);
            $msgOk = 'Chamado reaberto.'; $_GET['id'] = $tid;
        }
    }
}

$in = implode(',', array_fill(0, count($idsFundos), '?'));
$st = $pdo->prepare("SELECT t.*, f.nome fundo_nome, (SELECT COUNT(*) FROM ticket_mensagens m WHERE m.ticket_id=t.id) n_msg
                     FROM tickets t LEFT JOIN fundos f ON f.id=t.fundo_id
                     WHERE t.canal='cotista_gestor' AND t.fundo_id IN ($in)
                     ORDER BY FIELD(t.status,'Aberto','Em andamento','Respondido','Encerrado'), t.atualizado_em DESC");
$st->execute($idsFundos);
$tickets = $st->fetchAll();
$kAbertos = count(array_filter($tickets, fn($t) => in_array($t['status'], ['Aberto', 'Em andamento'], true)));

$ticketAberto = null; $thread = [];
if (!empty($_GET['id'])) {
    $st = $pdo->prepare("SELECT t.*, f.nome fundo_nome FROM tickets t LEFT JOIN fundos f ON f.id=t.fundo_id
                         WHERE t.id=? AND t.canal='cotista_gestor'");
    $st->execute([(int)$_GET['id']]);
    $cand = $st->fetch();
    if ($cand && in_array((int)$cand['fundo_id'], $idsFundos, true)) {
        $ticketAberto = $cand;
        $st = $pdo->prepare("SELECT * FROM ticket_mensagens WHERE ticket_id=? ORDER BY criado_em, id");
        $st->execute([(int)$cand['id']]);
        $thread = $st->fetchAll();
    }
}

page_start('Chamados de cotistas', 'Chamados de cotistas', $u,
    e_html($fundo['nome']) . ($podeResponder ? ' · responda as dúvidas dos cotistas' : ' · acesso somente leitura'));
?>

<?php if ($msgOk): ?><div class="alert alert-success py-2"><i class="bi bi-check-circle me-1"></i><?= e_html($msgOk) ?></div><?php endif; ?>
<?php if ($msgErr): ?><div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle me-1"></i><?= e_html($msgErr) ?></div><?php endif; ?>

<?php if (!$podeResponder): ?>
  <div class="alert alert-light border" style="font-size:.83rem"><i class="bi bi-eye me-1"></i>
    Você tem permissão de <b>ver</b> os chamados, mas não de responder. Peça ao gestor principal a permissão
    <b>“Responder chamados de cotistas”</b>.</div>
<?php endif; ?>

<div class="row row-cols-2 g-3 mb-4">
  <?= kpi('Chamados de cotistas', (string)count($tickets), 'bi-chat-dots') ?>
  <?= kpi('Em aberto', '<span class="' . ($kAbertos ? 'text-warning' : '') . '">' . $kAbertos . '</span>', 'bi-hourglass-split') ?>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card">
      <div class="card-header"><i class="bi bi-list-ul me-1"></i> Chamados <span class="text-muted">(<?= count($tickets) ?>)</span></div>
      <div class="card-body p-0" style="max-height:560px;overflow-y:auto">
        <?php if (!$tickets): ?><p class="text-muted text-center py-4 mb-0" style="font-size:.85rem">Nenhum chamado de cotista ainda.</p><?php endif; ?>
        <?php foreach ($tickets as $t): ?>
          <a href="?id=<?= (int)$t['id'] ?>" class="d-block p-2 px-3 border-bottom text-decoration-none text-body <?= $ticketAberto && (int)$ticketAberto['id'] === (int)$t['id'] ? 'bg-light' : '' ?>">
            <div class="d-flex justify-content-between align-items-start">
              <b style="font-size:.86rem">#<?= (int)$t['id'] ?> · <?= e_html($t['assunto']) ?></b>
              <?= badge_status($t['status']) ?>
            </div>
            <div style="font-size:.76rem" class="text-muted mt-1">
              <?= badge($t['tema'], 'secondary') ?> · <?= e_html($t['aberto_por']) ?> · <?= e_html($t['fundo_nome'] ?? '—') ?>
              · <?= (int)$t['n_msg'] ?> msg · <?= data_br($t['atualizado_em']) ?>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <?php if (!$ticketAberto): ?>
      <div class="card h-100"><div class="card-body d-flex align-items-center justify-content-center text-muted" style="min-height:300px">
        <div class="text-center"><i class="bi bi-chat-square-text" style="font-size:2rem"></i>
          <p class="mt-2 mb-0" style="font-size:.9rem">Selecione um chamado.</p></div></div></div>
    <?php else: ?>
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="bi bi-ticket-detailed me-1"></i> #<?= (int)$ticketAberto['id'] ?> · <?= e_html($ticketAberto['assunto']) ?></span>
          <?= badge_status($ticketAberto['status']) ?>
        </div>
        <div class="card-body">
          <div class="mb-3 text-muted" style="font-size:.82rem">
            <?= badge($ticketAberto['tema'], 'secondary') ?> · cotista <b><?= e_html($ticketAberto['aberto_por']) ?></b>
            · <?= e_html($ticketAberto['fundo_nome'] ?? '—') ?> · aberto em <?= data_br($ticketAberto['criado_em']) ?>
          </div>
          <div style="max-height:340px;overflow-y:auto" class="mb-3">
            <?php foreach ($thread as $m): $ehGestor = $m['perfil'] === 'gestor'; ?>
              <div class="mb-2 p-2 rounded <?= $ehGestor ? '' : 'bg-light' ?>" style="<?= $ehGestor ? 'background:#f0fdf4' : '' ?>">
                <div class="d-flex justify-content-between" style="font-size:.76rem">
                  <b><?= e_html($m['autor']) ?> <?= $ehGestor ? badge('Gestor', 'success') : badge('Cotista', 'secondary') ?></b>
                  <span class="text-muted"><?= date('d/m/Y H:i', strtotime($m['criado_em'])) ?></span>
                </div>
                <div class="mt-1" style="font-size:.88rem;white-space:pre-wrap"><?= e_html($m['mensagem']) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
          <?php if ($podeResponder): ?>
            <?php if ($ticketAberto['status'] === 'Encerrado'): ?>
              <div class="d-flex justify-content-between align-items-center">
                <span class="text-muted" style="font-size:.85rem"><i class="bi bi-lock me-1"></i>Chamado encerrado.</span>
                <form method="post"><?= csrf_campo() ?><input type="hidden" name="acao" value="reabrir"><input type="hidden" name="ticket_id" value="<?= (int)$ticketAberto['id'] ?>">
                  <button class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-clockwise me-1"></i>Reabrir</button></form>
              </div>
            <?php else: ?>
              <form method="post" class="mb-2">
                <?= csrf_campo() ?><input type="hidden" name="acao" value="responder"><input type="hidden" name="ticket_id" value="<?= (int)$ticketAberto['id'] ?>">
                <div class="input-group">
                  <textarea name="mensagem" class="form-control form-control-sm" rows="2" placeholder="Responder ao cotista…" required></textarea>
                  <button class="btn btn-sm btn-primary"><i class="bi bi-send"></i></button>
                </div>
              </form>
              <form method="post" onsubmit="return confirm('Encerrar este chamado?')">
                <?= csrf_campo() ?><input type="hidden" name="acao" value="encerrar"><input type="hidden" name="ticket_id" value="<?= (int)$ticketAberto['id'] ?>">
                <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-check2-circle me-1"></i>Encerrar</button>
              </form>
            <?php endif; ?>
          <?php else: ?>
            <div class="alert alert-light border py-2 mb-0" style="font-size:.82rem"><i class="bi bi-eye me-1"></i>Somente leitura.</div>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php page_end();
