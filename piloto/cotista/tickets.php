<?php
// Portal do Cotista — Central de dúvidas (chamados cotista ↔ gestor).
// O cotista entra por token (sem cadastro). Abre chamados, acompanha respostas
// do gestor e pode encerrar (resolvido) ou reabrir. Tudo gravado em SQL.
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
ensure_tickets($pdo);

$token = exigir_token($pdo);
$fid = (int)$token['fundo_id'];
$tk  = $token['token'];
$autor = $token['descricao'] ?: 'Cotista';
$fundoNome = $token['fundo_nome'] ?? '';

const TEMAS_COTISTA = ['Dúvida sobre rentabilidade', 'Aplicação / resgate', 'Documentos / informe de IR',
                       'Tributação (come-cotas / IR)', 'Acesso ao portal', 'Outros'];

$msgOk = ''; $msgErr = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validar()) {
        $_POST = []; $msgErr = 'Sessão expirada. Recarregue a página.';
    } elseif (($_POST['acao'] ?? '') === 'abrir') {
        $tema = in_array($_POST['tema'] ?? '', TEMAS_COTISTA, true) ? $_POST['tema'] : 'Outros';
        $assunto = trim($_POST['assunto'] ?? '');
        $mensagem = trim($_POST['mensagem'] ?? '');
        if ($assunto === '' || $mensagem === '') {
            $msgErr = 'Preencha o assunto e a sua dúvida.';
        } else {
            $novoId = null;
            com_transacao($pdo, function () use ($pdo, $fid, $autor, $tk, $tema, $assunto, $mensagem, &$novoId) {
                $pdo->prepare("INSERT INTO tickets (fundo_id, aberto_por, perfil_abertura, tema, assunto, prioridade, status, canal, token_cotista)
                               VALUES (?,?, 'cotista', ?,?, 'Média', 'Aberto', 'cotista_gestor', ?)")
                    ->execute([$fid, $autor, $tema, $assunto, $tk]);
                $novoId = (int)$pdo->lastInsertId();
                $pdo->prepare("INSERT INTO ticket_mensagens (ticket_id, autor, perfil, mensagem) VALUES (?,?, 'cotista', ?)")
                    ->execute([$novoId, $autor, $mensagem]);
            });
            $msgOk = 'Dúvida enviada ao gestor. Protocolo #' . $novoId . '.';
            $_GET['id'] = $novoId;
        }
    } else {
        // ações sobre um chamado do próprio cotista (validado por token)
        $tid = (int)($_POST['ticket_id'] ?? 0);
        $st = $pdo->prepare("SELECT * FROM tickets WHERE id=? AND canal='cotista_gestor' AND token_cotista=?");
        $st->execute([$tid, $tk]);
        $t = $st->fetch();
        if (!$t) {
            $msgErr = 'Chamado não encontrado.';
        } elseif (($_POST['acao'] ?? '') === 'responder') {
            $mensagem = trim($_POST['mensagem'] ?? '');
            if ($mensagem === '') { $msgErr = 'Escreva uma mensagem.'; }
            elseif ($t['status'] === 'Encerrado') { $msgErr = 'Chamado encerrado — reabra para enviar mensagens.'; }
            else {
                com_transacao($pdo, function () use ($pdo, $tid, $autor, $mensagem) {
                    $pdo->prepare("INSERT INTO ticket_mensagens (ticket_id, autor, perfil, mensagem) VALUES (?,?, 'cotista', ?)")
                        ->execute([$tid, $autor, $mensagem]);
                    $pdo->prepare("UPDATE tickets SET status='Aberto', atualizado_em=NOW() WHERE id=?")->execute([$tid]);
                });
                $msgOk = 'Mensagem enviada.';
            }
            $_GET['id'] = $tid;
        } elseif (($_POST['acao'] ?? '') === 'encerrar') {
            $pdo->prepare("UPDATE tickets SET status='Encerrado', atualizado_em=NOW() WHERE id=?")->execute([$tid]);
            $msgOk = 'Chamado encerrado. Você pode reabri-lo quando quiser.';
            $_GET['id'] = $tid;
        } elseif (($_POST['acao'] ?? '') === 'reabrir') {
            $pdo->prepare("UPDATE tickets SET status='Aberto', atualizado_em=NOW() WHERE id=?")->execute([$tid]);
            $msgOk = 'Chamado reaberto.';
            $_GET['id'] = $tid;
        }
    }
}

// lista dos chamados deste cotista (por token)
$st = $pdo->prepare("SELECT t.*, (SELECT COUNT(*) FROM ticket_mensagens m WHERE m.ticket_id=t.id) n_msg
                     FROM tickets t WHERE t.canal='cotista_gestor' AND t.token_cotista=?
                     ORDER BY FIELD(t.status,'Respondido','Aberto','Em andamento','Encerrado'), t.atualizado_em DESC");
$st->execute([$tk]);
$tickets = $st->fetchAll();

$ticketAberto = null; $thread = [];
if (!empty($_GET['id'])) {
    $st = $pdo->prepare("SELECT * FROM tickets WHERE id=? AND canal='cotista_gestor' AND token_cotista=?");
    $st->execute([(int)$_GET['id'], $tk]);
    $ticketAberto = $st->fetch() ?: null;
    if ($ticketAberto) {
        $st = $pdo->prepare("SELECT * FROM ticket_mensagens WHERE ticket_id=? ORDER BY criado_em, id");
        $st->execute([(int)$ticketAberto['id']]);
        $thread = $st->fetchAll();
    }
}
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dúvidas · Portal do Cotista</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="../assets/css/style.css" rel="stylesheet">
<style>body{background:var(--bg)}</style>
</head>
<body>
<nav style="background:var(--navy)" class="py-2 px-4 d-flex justify-content-between align-items-center">
  <div class="d-flex align-items-center gap-2 text-white">
    <i class="bi bi-bank2" style="color:#c9a227"></i>
    <b style="font-size:.85rem;letter-spacing:1px">PORTAL DO COTISTA</b>
  </div>
  <div class="d-flex align-items-center gap-3">
    <a class="btn btn-sm btn-outline-light" href="painel.php" style="font-size:.75rem"><i class="bi bi-graph-up me-1"></i>Meu fundo</a>
    <a class="btn btn-sm btn-outline-light" href="sair.php" style="font-size:.75rem">Sair</a>
  </div>
</nav>

<div class="container py-4" style="max-width:1050px">
  <div class="mb-3">
    <h4 class="mb-0"><i class="bi bi-chat-dots me-2"></i>Central de dúvidas</h4>
    <span class="text-muted" style="font-size:.82rem">Envie dúvidas ao gestor do fundo <b><?= e_html($fundoNome) ?></b> e acompanhe as respostas.</span>
  </div>

  <?php if ($msgOk): ?><div class="alert alert-success py-2"><i class="bi bi-check-circle me-1"></i><?= e_html($msgOk) ?></div><?php endif; ?>
  <?php if ($msgErr): ?><div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle me-1"></i><?= e_html($msgErr) ?></div><?php endif; ?>

  <div class="row g-3">
    <div class="col-lg-5">
      <div class="card mb-3">
        <div class="card-header"><i class="bi bi-plus-circle me-1"></i> Nova dúvida</div>
        <div class="card-body">
          <form method="post">
            <?= csrf_campo() ?>
            <input type="hidden" name="acao" value="abrir">
            <div class="mb-2">
              <label class="form-label mb-1" style="font-size:.82rem">Tema</label>
              <select name="tema" class="form-select form-select-sm">
                <?php foreach (TEMAS_COTISTA as $t): ?><option value="<?= e_html($t) ?>"><?= e_html($t) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="mb-2">
              <label class="form-label mb-1" style="font-size:.82rem">Assunto</label>
              <input name="assunto" class="form-control form-control-sm" maxlength="200" placeholder="Resumo da dúvida" required>
            </div>
            <div class="mb-2">
              <label class="form-label mb-1" style="font-size:.82rem">Sua dúvida</label>
              <textarea name="mensagem" class="form-control form-control-sm" rows="3" placeholder="Escreva sua dúvida…" required></textarea>
            </div>
            <button class="btn btn-sm btn-primary w-100"><i class="bi bi-send me-1"></i> Enviar dúvida</button>
          </form>
        </div>
      </div>
      <div class="card">
        <div class="card-header"><i class="bi bi-list-ul me-1"></i> Minhas dúvidas <span class="text-muted">(<?= count($tickets) ?>)</span></div>
        <div class="card-body p-0" style="max-height:460px;overflow-y:auto">
          <?php if (!$tickets): ?><p class="text-muted text-center py-4 mb-0" style="font-size:.85rem">Nenhuma dúvida enviada ainda.</p><?php endif; ?>
          <?php foreach ($tickets as $t): ?>
            <a href="?id=<?= (int)$t['id'] ?>" class="d-block p-2 px-3 border-bottom text-decoration-none text-body <?= $ticketAberto && (int)$ticketAberto['id'] === (int)$t['id'] ? 'bg-light' : '' ?>">
              <div class="d-flex justify-content-between align-items-start">
                <b style="font-size:.86rem">#<?= (int)$t['id'] ?> · <?= e_html($t['assunto']) ?></b>
                <?= badge_status($t['status']) ?>
              </div>
              <div style="font-size:.76rem" class="text-muted mt-1">
                <?= badge($t['tema'], 'secondary') ?> · <?= (int)$t['n_msg'] ?> msg · <?= data_br($t['atualizado_em']) ?>
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
            <p class="mt-2 mb-0" style="font-size:.9rem">Selecione uma dúvida ou abra uma nova.</p></div>
        </div></div>
      <?php else: ?>
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-ticket-detailed me-1"></i> #<?= (int)$ticketAberto['id'] ?> · <?= e_html($ticketAberto['assunto']) ?></span>
            <?= badge_status($ticketAberto['status']) ?>
          </div>
          <div class="card-body">
            <div class="mb-3 text-muted" style="font-size:.82rem">
              <?= badge($ticketAberto['tema'], 'secondary') ?> · aberto em <?= data_br($ticketAberto['criado_em']) ?>
            </div>
            <div style="max-height:340px;overflow-y:auto" class="mb-3">
              <?php foreach ($thread as $m): $euCot = $m['perfil'] === 'cotista'; ?>
                <div class="mb-2 p-2 rounded <?= $euCot ? 'bg-light' : 'border' ?>" style="<?= $euCot ? '' : 'background:#f0fdf4' ?>">
                  <div class="d-flex justify-content-between" style="font-size:.76rem">
                    <b><?= e_html($m['autor']) ?> <?= $euCot ? badge('Você', 'secondary') : badge('Gestor', 'success') ?></b>
                    <span class="text-muted"><?= date('d/m/Y H:i', strtotime($m['criado_em'])) ?></span>
                  </div>
                  <div class="mt-1" style="font-size:.88rem;white-space:pre-wrap"><?= e_html($m['mensagem']) ?></div>
                </div>
              <?php endforeach; ?>
            </div>
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
                  <textarea name="mensagem" class="form-control form-control-sm" rows="2" placeholder="Escreva uma mensagem…" required></textarea>
                  <button class="btn btn-sm btn-primary"><i class="bi bi-send"></i></button>
                </div>
              </form>
              <form method="post" onsubmit="return confirm('Encerrar este chamado?')">
                <?= csrf_campo() ?><input type="hidden" name="acao" value="encerrar"><input type="hidden" name="ticket_id" value="<?= (int)$ticketAberto['id'] ?>">
                <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-check2-circle me-1"></i>Marcar como resolvido</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
