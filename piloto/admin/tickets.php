<?php
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

$u = exigir_perfil('admin');
ensure_tickets($pdo);

$msgOk  = '';
$msgErr = '';
$STATUS = ['Aberto', 'Em andamento', 'Respondido', 'Encerrado'];

// ---------------- Handlers (POST) ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validar()) {
        $_POST = [];
        $msgErr = 'Sessão expirada ou requisição inválida. Tente novamente.';
    } else {
        $acao = $_POST['acao'] ?? '';
        $tid  = (int)($_POST['ticket_id'] ?? 0);
        $st = $pdo->prepare("SELECT * FROM tickets WHERE id = ?");
        $st->execute([$tid]);
        $tk = $st->fetch();

        if (!$tk) {
            $msgErr = 'Chamado não encontrado.';
        } elseif ($acao === 'responder') {
            $mensagem = trim($_POST['mensagem'] ?? '');
            if ($mensagem === '') {
                $msgErr = 'Escreva uma mensagem para enviar.';
            } else {
                com_transacao($pdo, function () use ($pdo, $tid, $u, $mensagem) {
                    $pdo->prepare("INSERT INTO ticket_mensagens (ticket_id, autor, perfil, mensagem) VALUES (?,?,?,?)")
                        ->execute([$tid, $u['nome'], $u['perfil'], $mensagem]);
                    $pdo->prepare("UPDATE tickets SET atualizado_em = NOW(), status = 'Respondido' WHERE id = ?")
                        ->execute([$tid]);
                });
                registrar_auditoria($pdo, 'ticket_respondido', [
                    'entidade' => 'ticket', 'entidade_id' => $tid, 'fundo_id' => (int)$tk['fundo_id'],
                    'detalhe' => 'Resposta da administradora no chamado #' . $tid,
                ]);
                $msgOk = 'Resposta enviada ao gestor.';
            }
            $_GET['id'] = $tid;
        } elseif ($acao === 'status') {
            $novo = $_POST['status'] ?? '';
            if (!in_array($novo, $STATUS, true)) {
                $msgErr = 'Status inválido.';
            } else {
                $pdo->prepare("UPDATE tickets SET status = ?, atualizado_em = NOW() WHERE id = ?")->execute([$novo, $tid]);
                registrar_auditoria($pdo, 'ticket_status', [
                    'entidade' => 'ticket', 'entidade_id' => $tid, 'fundo_id' => (int)$tk['fundo_id'],
                    'detalhe' => 'Status do chamado #' . $tid . ' → ' . $novo,
                ]);
                $msgOk = 'Status atualizado para “' . $novo . '”.';
            }
            $_GET['id'] = $tid;
        } elseif ($acao === 'prioridade') {
            $prio = $_POST['prioridade'] ?? '';
            if (!in_array($prio, ['Baixa', 'Média', 'Alta'], true)) {
                $msgErr = 'Prioridade inválida.';
            } else {
                $pdo->prepare("UPDATE tickets SET prioridade = ?, atualizado_em = NOW() WHERE id = ?")->execute([$prio, $tid]);
                registrar_auditoria($pdo, 'ticket_prioridade', [
                    'entidade' => 'ticket', 'entidade_id' => $tid, 'fundo_id' => (int)$tk['fundo_id'],
                    'detalhe' => 'Prioridade do chamado #' . $tid . ' → ' . $prio,
                ]);
                $msgOk = 'Prioridade atualizada para “' . $prio . '”.';
            }
            $_GET['id'] = $tid;
        }
    }
}

// ---------------- Filtros ----------------
$fStatus = $_GET['status'] ?? '';
$fTema   = $_GET['tema'] ?? '';
$cond = [];
$args = [];
if (in_array($fStatus, $STATUS, true))     { $cond[] = 't.status = ?'; $args[] = $fStatus; }
if (in_array($fTema, TEMAS_TICKET, true))   { $cond[] = 't.tema = ?';   $args[] = $fTema; }
$where = $cond ? ('WHERE ' . implode(' AND ', $cond)) : '';

$st = $pdo->prepare("SELECT t.*, f.nome fundo_nome,
                       (SELECT COUNT(*) FROM ticket_mensagens m WHERE m.ticket_id = t.id) n_msg
                     FROM tickets t LEFT JOIN fundos f ON f.id = t.fundo_id
                     $where
                     ORDER BY FIELD(t.status,'Aberto','Em andamento','Respondido','Encerrado'),
                              FIELD(t.prioridade,'Alta','Média','Baixa'), t.atualizado_em DESC");
$st->execute($args);
$tickets = $st->fetchAll();

// ---------------- KPIs (globais, sem filtro) ----------------
$kAbertos     = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'Aberto'")->fetchColumn();
$kAndamento   = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'Em andamento'")->fetchColumn();
$kRespondidos = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'Respondido'")->fetchColumn();

// ---------------- Thread (?id=) ----------------
$ticketAberto = null;
$thread = [];
if (!empty($_GET['id'])) {
    $st = $pdo->prepare("SELECT t.*, f.nome fundo_nome FROM tickets t
                         LEFT JOIN fundos f ON f.id = t.fundo_id WHERE t.id = ?");
    $st->execute([(int)$_GET['id']]);
    $ticketAberto = $st->fetch() ?: null;
    if ($ticketAberto) {
        $st = $pdo->prepare("SELECT * FROM ticket_mensagens WHERE ticket_id = ? ORDER BY criado_em, id");
        $st->execute([(int)$ticketAberto['id']]);
        $thread = $st->fetchAll();
    }
}

// Preserva filtros nos links da thread.
$qs = http_build_query(array_filter(['status' => $fStatus, 'tema' => $fTema]));

page_start('Suporte', 'Suporte', $u, 'Central de chamados — atenda, responda e acompanhe os fundos');
?>

<?php if ($msgOk): ?><div class="alert alert-success py-2"><i class="bi bi-check-circle me-1"></i><?= e_html($msgOk) ?></div><?php endif; ?>
<?php if ($msgErr): ?><div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle me-1"></i><?= e_html($msgErr) ?></div><?php endif; ?>

<div class="row row-cols-3 g-3 mb-4">
  <?= kpi('Abertos', '<span class="' . ($kAbertos ? 'text-info' : '') . '">' . $kAbertos . '</span>', 'bi-envelope-open') ?>
  <?= kpi('Em andamento', '<span class="' . ($kAndamento ? 'text-warning' : '') . '">' . $kAndamento . '</span>', 'bi-hourglass-split') ?>
  <?= kpi('Respondidos', '<span class="' . ($kRespondidos ? 'text-success' : '') . '">' . $kRespondidos . '</span>', 'bi-chat-left-dots') ?>
</div>

<div class="row g-3">
  <!-- Coluna esquerda: filtros + lista -->
  <div class="col-lg-5">
    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-funnel me-1"></i> Filtros</div>
      <div class="card-body">
        <form method="get" class="row g-2">
          <div class="col-12">
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
              <option value="">Todos os status</option>
              <?php foreach ($STATUS as $s): ?>
                <option value="<?= e_html($s) ?>" <?= $fStatus === $s ? 'selected' : '' ?>><?= e_html($s) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <select name="tema" class="form-select form-select-sm" onchange="this.form.submit()">
              <option value="">Todos os temas</option>
              <?php foreach (TEMAS_TICKET as $t): ?>
                <option value="<?= e_html($t) ?>" <?= $fTema === $t ? 'selected' : '' ?>><?= e_html($t) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php if ($fStatus || $fTema): ?>
            <div class="col-12"><a href="tickets.php" class="btn btn-sm btn-outline-secondary w-100">Limpar filtros</a></div>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><i class="bi bi-list-ul me-1"></i> Chamados <span class="text-muted">(<?= count($tickets) ?>)</span></div>
      <div class="card-body p-0" style="max-height:560px;overflow-y:auto">
        <?php if (!$tickets): ?>
          <p class="text-muted text-center py-4 mb-0" style="font-size:.85rem">Nenhum chamado para este filtro.</p>
        <?php endif; ?>
        <?php foreach ($tickets as $t):
            $link = '?id=' . (int)$t['id'] . ($qs ? '&' . $qs : ''); ?>
          <a href="<?= $link ?>" class="d-block p-2 px-3 border-bottom text-decoration-none text-body
             <?= $ticketAberto && (int)$ticketAberto['id'] === (int)$t['id'] ? 'bg-light' : '' ?>">
            <div class="d-flex justify-content-between align-items-start">
              <b style="font-size:.86rem">#<?= (int)$t['id'] ?> · <?= e_html($t['assunto']) ?></b>
              <?= badge_status($t['status']) ?>
            </div>
            <div style="font-size:.76rem" class="text-muted mt-1">
              <?= badge($t['tema'], 'secondary') ?> <?= badge_status($t['prioridade']) ?>
              · <?= e_html($t['fundo_nome'] ?? '—') ?> · <?= e_html($t['aberto_por']) ?>
              · <?= (int)$t['n_msg'] ?> msg · <?= data_br($t['atualizado_em']) ?>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Coluna direita: thread -->
  <div class="col-lg-7">
    <?php if (!$ticketAberto): ?>
      <div class="card h-100">
        <div class="card-body d-flex align-items-center justify-content-center text-muted" style="min-height:300px">
          <div class="text-center">
            <i class="bi bi-chat-square-text" style="font-size:2rem"></i>
            <p class="mt-2 mb-0" style="font-size:.9rem">Selecione um chamado para atender.</p>
          </div>
        </div>
      </div>
    <?php else: ?>
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="bi bi-ticket-detailed me-1"></i> #<?= (int)$ticketAberto['id'] ?> · <?= e_html($ticketAberto['assunto']) ?></span>
          <?= badge_status($ticketAberto['status']) ?>
        </div>
        <div class="card-body">
          <div class="mb-3 text-muted" style="font-size:.82rem">
            <?= badge($ticketAberto['tema'], 'secondary') ?>
            <?= badge_status($ticketAberto['prioridade']) ?>
            · <?= e_html($ticketAberto['fundo_nome'] ?? '—') ?>
            · aberto por <?= e_html($ticketAberto['aberto_por']) ?> em <?= data_br($ticketAberto['criado_em']) ?>
          </div>

          <!-- Controles: status + prioridade -->
          <div class="d-flex flex-wrap gap-2 mb-3">
            <form method="post" class="d-flex gap-1">
              <?= csrf_campo() ?>
              <input type="hidden" name="acao" value="status">
              <input type="hidden" name="ticket_id" value="<?= (int)$ticketAberto['id'] ?>">
              <select name="status" class="form-select form-select-sm" style="width:auto">
                <?php foreach ($STATUS as $s): ?>
                  <option value="<?= e_html($s) ?>" <?= $ticketAberto['status'] === $s ? 'selected' : '' ?>><?= e_html($s) ?></option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-sm btn-outline-secondary" title="Alterar status"><i class="bi bi-arrow-repeat"></i></button>
            </form>
            <form method="post" class="d-flex gap-1">
              <?= csrf_campo() ?>
              <input type="hidden" name="acao" value="prioridade">
              <input type="hidden" name="ticket_id" value="<?= (int)$ticketAberto['id'] ?>">
              <select name="prioridade" class="form-select form-select-sm" style="width:auto">
                <?php foreach (['Baixa', 'Média', 'Alta'] as $p): ?>
                  <option value="<?= e_html($p) ?>" <?= $ticketAberto['prioridade'] === $p ? 'selected' : '' ?>><?= e_html($p) ?></option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-sm btn-outline-secondary" title="Alterar prioridade"><i class="bi bi-flag"></i></button>
            </form>
          </div>

          <div style="max-height:340px;overflow-y:auto" class="mb-3">
            <?php foreach ($thread as $m): ?>
              <div class="mb-2 p-2 rounded <?= $m['perfil'] === 'admin' ? '' : 'bg-light' ?>"
                   style="<?= $m['perfil'] === 'admin' ? 'background:#f0f7ff' : '' ?>">
                <div class="d-flex justify-content-between" style="font-size:.76rem">
                  <b><?= e_html($m['autor']) ?>
                    <?= $m['perfil'] === 'admin' ? badge('Administradora', 'warning') : badge('Gestor', 'info') ?>
                  </b>
                  <span class="text-muted"><?= date('d/m/Y H:i', strtotime($m['criado_em'])) ?></span>
                </div>
                <div class="mt-1" style="font-size:.88rem;white-space:pre-wrap"><?= e_html($m['mensagem']) ?></div>
              </div>
            <?php endforeach; ?>
          </div>

          <form method="post">
            <?= csrf_campo() ?>
            <input type="hidden" name="acao" value="responder">
            <input type="hidden" name="ticket_id" value="<?= (int)$ticketAberto['id'] ?>">
            <div class="input-group">
              <textarea name="mensagem" class="form-control form-control-sm" rows="2" placeholder="Responder ao gestor…" required></textarea>
              <button class="btn btn-sm btn-primary"><i class="bi bi-send"></i></button>
            </div>
            <div class="form-text" style="font-size:.75rem">Ao responder, o chamado passa a “Respondido”.</div>
          </form>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php page_end();
