<?php
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

$u = exigir_perfil('gestor', 'admin');
ensure_tickets($pdo);

$fundo = fundo_do_usuario($pdo, $u);
if (!$fundo) die('Sem fundo vinculado.');
$fid = (int)$fundo['id'];

// Fundos aos quais o gestor tem acesso (para restringir a leitura de tickets).
$meusFundos = fundos_do_usuario($pdo, $u);
$idsFundos  = array_map(fn($f) => (int)$f['id'], $meusFundos);

$msgOk  = '';
$msgErr = '';

// ---------------- Handlers (POST) ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validar()) {
        $_POST = [];
        $msgErr = 'Sessão expirada ou requisição inválida. Tente novamente.';
    } elseif (($_POST['acao'] ?? '') === 'abrir') {
        // Abrir novo chamado vinculado ao fundo em foco.
        $tema      = trim($_POST['tema'] ?? '');
        $assunto   = trim($_POST['assunto'] ?? '');
        $prioridade = in_array($_POST['prioridade'] ?? '', ['Baixa', 'Média', 'Alta'], true) ? $_POST['prioridade'] : 'Média';
        $mensagem  = trim($_POST['mensagem'] ?? '');

        if (!in_array($tema, TEMAS_TICKET, true)) $tema = 'Outros';
        if ($assunto === '' || $mensagem === '') {
            $msgErr = 'Preencha o assunto e a mensagem inicial do chamado.';
        } else {
            com_transacao($pdo, function () use ($pdo, $fid, $u, $tema, $assunto, $prioridade, $mensagem, &$novoId) {
                $st = $pdo->prepare("INSERT INTO tickets (fundo_id, aberto_por, perfil_abertura, tema, assunto, prioridade, status)
                                     VALUES (?,?,?,?,?,?, 'Aberto')");
                $st->execute([$fid, $u['nome'], $u['perfil'], $tema, $assunto, $prioridade]);
                $novoId = (int)$pdo->lastInsertId();
                $pdo->prepare("INSERT INTO ticket_mensagens (ticket_id, autor, perfil, mensagem) VALUES (?,?,?,?)")
                    ->execute([$novoId, $u['nome'], $u['perfil'], $mensagem]);
            });
            registrar_auditoria($pdo, 'ticket_aberto', [
                'entidade' => 'ticket', 'entidade_id' => $novoId, 'fundo_id' => $fid,
                'detalhe' => 'Chamado aberto: [' . $tema . '] ' . $assunto,
            ]);
            $msgOk = 'Chamado aberto — a administradora foi notificada. Protocolo #' . $novoId . '.';
        }
    } elseif (($_POST['acao'] ?? '') === 'responder') {
        // Responder um chamado existente do(s) fundo(s) do gestor.
        $tid      = (int)($_POST['ticket_id'] ?? 0);
        $mensagem = trim($_POST['mensagem'] ?? '');
        $st = $pdo->prepare("SELECT * FROM tickets WHERE id = ?");
        $st->execute([$tid]);
        $tk = $st->fetch();
        if (!$tk || !in_array((int)$tk['fundo_id'], $idsFundos, true)) {
            $msgErr = 'Chamado não encontrado.';
        } elseif ($mensagem === '') {
            $msgErr = 'Escreva uma mensagem para enviar.';
        } elseif ($tk['status'] === 'Encerrado') {
            $msgErr = 'Este chamado está encerrado e não aceita novas mensagens.';
        } else {
            com_transacao($pdo, function () use ($pdo, $tid, $u, $mensagem, $tk) {
                $pdo->prepare("INSERT INTO ticket_mensagens (ticket_id, autor, perfil, mensagem) VALUES (?,?,?,?)")
                    ->execute([$tid, $u['nome'], $u['perfil'], $mensagem]);
                // Se estava 'Respondido' pela administradora, volta a 'Aberto' (aguardando análise).
                $novoStatus = $tk['status'] === 'Respondido' ? 'Aberto' : $tk['status'];
                $pdo->prepare("UPDATE tickets SET atualizado_em = NOW(), status = ? WHERE id = ?")
                    ->execute([$novoStatus, $tid]);
            });
            registrar_auditoria($pdo, 'ticket_respondido', [
                'entidade' => 'ticket', 'entidade_id' => $tid, 'fundo_id' => (int)$tk['fundo_id'],
                'detalhe' => 'Resposta do gestor no chamado #' . $tid,
            ]);
            $msgOk = 'Mensagem enviada.';
        }
        $_GET['id'] = $tid; // permanece na thread
    }
}

// ---------------- Thread (?id=) ----------------
$ticketAberto = null;
$thread = [];
if (!empty($_GET['id'])) {
    $st = $pdo->prepare("SELECT t.*, f.nome fundo_nome FROM tickets t
                         LEFT JOIN fundos f ON f.id = t.fundo_id WHERE t.id = ?");
    $st->execute([(int)$_GET['id']]);
    $cand = $st->fetch();
    if ($cand && in_array((int)$cand['fundo_id'], $idsFundos, true)) {
        $ticketAberto = $cand;
        $st = $pdo->prepare("SELECT * FROM ticket_mensagens WHERE ticket_id = ? ORDER BY criado_em, id");
        $st->execute([(int)$cand['id']]);
        $thread = $st->fetchAll();
    }
}

// ---------------- Lista de chamados do(s) fundo(s) ----------------
$in = implode(',', array_fill(0, count($idsFundos), '?'));
$st = $pdo->prepare("SELECT t.*, f.nome fundo_nome,
                       (SELECT COUNT(*) FROM ticket_mensagens m WHERE m.ticket_id = t.id) n_msg
                     FROM tickets t LEFT JOIN fundos f ON f.id = t.fundo_id
                     WHERE t.fundo_id IN ($in)
                     ORDER BY FIELD(t.status,'Respondido','Aberto','Em andamento','Encerrado'), t.atualizado_em DESC");
$st->execute($idsFundos);
$tickets = $st->fetchAll();

$abertos     = count(array_filter($tickets, fn($t) => in_array($t['status'], ['Aberto', 'Em andamento'], true)));
$respondidos = count(array_filter($tickets, fn($t) => $t['status'] === 'Respondido'));

page_start('Suporte', 'Suporte', $u, 'Abra chamados à administradora e acompanhe as respostas — ' . e_html($fundo['nome']));
?>

<?php if ($msgOk): ?><div class="alert alert-success py-2"><i class="bi bi-check-circle me-1"></i><?= e_html($msgOk) ?></div><?php endif; ?>
<?php if ($msgErr): ?><div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle me-1"></i><?= e_html($msgErr) ?></div><?php endif; ?>

<div class="row row-cols-3 g-3 mb-4">
  <?= kpi('Meus chamados', (string)count($tickets), 'bi-life-preserver') ?>
  <?= kpi('Em aberto', (string)$abertos, 'bi-hourglass-split') ?>
  <?= kpi('Respondidos', '<span class="' . ($respondidos ? 'text-success' : '') . '">' . $respondidos . '</span>', 'bi-chat-left-dots') ?>
</div>

<div class="row g-3">
  <!-- Coluna esquerda: abrir + lista -->
  <div class="col-lg-5">
    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-plus-circle me-1"></i> Abrir novo chamado</div>
      <div class="card-body">
        <form method="post">
          <?= csrf_campo() ?>
          <input type="hidden" name="acao" value="abrir">
          <div class="mb-2">
            <label class="form-label mb-1" style="font-size:.82rem">Tema</label>
            <select name="tema" class="form-select form-select-sm" required>
              <?php foreach (TEMAS_TICKET as $t): ?>
                <option value="<?= e_html($t) ?>"><?= e_html($t) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label mb-1" style="font-size:.82rem">Assunto</label>
            <input name="assunto" class="form-control form-control-sm" maxlength="200" placeholder="Resumo do chamado" required>
          </div>
          <div class="mb-2">
            <label class="form-label mb-1" style="font-size:.82rem">Prioridade</label>
            <select name="prioridade" class="form-select form-select-sm">
              <option>Baixa</option><option selected>Média</option><option>Alta</option>
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label mb-1" style="font-size:.82rem">Mensagem inicial</label>
            <textarea name="mensagem" class="form-control form-control-sm" rows="3" placeholder="Descreva a solicitação…" required></textarea>
          </div>
          <button class="btn btn-sm btn-primary w-100"><i class="bi bi-send me-1"></i> Abrir chamado</button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><i class="bi bi-list-ul me-1"></i> Meus chamados</div>
      <div class="card-body p-0" style="max-height:520px;overflow-y:auto">
        <?php if (!$tickets): ?>
          <p class="text-muted text-center py-4 mb-0" style="font-size:.85rem">Nenhum chamado aberto ainda.</p>
        <?php endif; ?>
        <?php foreach ($tickets as $t): ?>
          <a href="?id=<?= (int)$t['id'] ?>" class="d-block p-2 px-3 border-bottom text-decoration-none text-body
             <?= $ticketAberto && (int)$ticketAberto['id'] === (int)$t['id'] ? 'bg-light' : '' ?>">
            <div class="d-flex justify-content-between align-items-start">
              <b style="font-size:.86rem">#<?= (int)$t['id'] ?> · <?= e_html($t['assunto']) ?></b>
              <?= badge_status($t['status']) ?>
            </div>
            <div style="font-size:.76rem" class="text-muted mt-1">
              <?= badge($t['tema'], 'secondary') ?> <?= badge_status($t['prioridade']) ?>
              · <?= (int)$t['n_msg'] ?> msg · atualizado <?= data_br($t['atualizado_em']) ?>
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
            <p class="mt-2 mb-0" style="font-size:.9rem">Selecione um chamado para ver a conversa.</p>
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

          <div style="max-height:360px;overflow-y:auto" class="mb-3">
            <?php foreach ($thread as $m):
                $euMesmo = $m['perfil'] !== 'admin'; ?>
              <div class="mb-2 p-2 rounded <?= $euMesmo ? 'bg-light' : 'border' ?>"
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

          <?php if ($ticketAberto['status'] === 'Encerrado'): ?>
            <div class="alert alert-secondary py-2 mb-0" style="font-size:.85rem">
              <i class="bi bi-lock me-1"></i> Chamado encerrado.
            </div>
          <?php else: ?>
            <form method="post">
              <?= csrf_campo() ?>
              <input type="hidden" name="acao" value="responder">
              <input type="hidden" name="ticket_id" value="<?= (int)$ticketAberto['id'] ?>">
              <div class="input-group">
                <textarea name="mensagem" class="form-control form-control-sm" rows="2" placeholder="Escreva sua resposta…" required></textarea>
                <button class="btn btn-sm btn-primary"><i class="bi bi-send"></i></button>
              </div>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php page_end();
