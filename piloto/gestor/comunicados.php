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

$msgOk = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['assunto'])) {
    $st = $pdo->prepare('INSERT INTO chamados (fundo_id, usuario_id, assunto, mensagem) VALUES (?,?,?,?)');
    $st->execute([$fid, $u['id'], trim($_POST['assunto']), trim($_POST['mensagem'] ?? '')]);
    $msgOk = 'Chamado aberto com sucesso — a administradora foi notificada.';
}

$st = $pdo->prepare('SELECT * FROM comunicados WHERE fundo_id = ? OR fundo_id IS NULL ORDER BY data_pub DESC');
$st->execute([$fid]);
$comunicados = $st->fetchAll();

$st = $pdo->prepare('SELECT * FROM chamados WHERE fundo_id = ? AND usuario_id = ? ORDER BY criado_em DESC');
$st->execute([$fid, $u['id']]);
$chamados = $st->fetchAll();

page_start('Comunicados & Chamados', 'Comunicados', $u, e_html($fundo['nome']));
?>

<?php if ($msgOk): ?><div class="alert alert-success py-2"><i class="bi bi-check-circle me-1"></i><?= $msgOk ?></div><?php endif; ?>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-megaphone me-1"></i> Comunicados da administradora</div>
      <div class="card-body p-0">
        <?php foreach ($comunicados as $c): ?>
          <div class="p-3 border-bottom">
            <div class="d-flex justify-content-between">
              <b style="font-size:.92rem"><?= e_html($c['titulo']) ?></b>
              <span class="text-muted" style="font-size:.8rem"><?= data_br($c['data_pub']) ?>
                <?= $c['fundo_id'] === null ? badge('geral', 'secondary') : badge('este fundo', 'info') ?></span>
            </div>
            <p class="text-muted mb-0 mt-1" style="font-size:.86rem"><?= e_html($c['mensagem']) ?></p>
          </div>
        <?php endforeach; ?>
        <?php if (!$comunicados): ?><p class="text-muted text-center py-4 mb-0">Sem comunicados.</p><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-plus-circle me-1"></i> Abrir chamado</div>
      <div class="card-body">
        <form method="post">
          <div class="mb-2">
            <label class="form-label" style="font-size:.8rem">Assunto</label>
            <input class="form-control form-control-sm" name="assunto" required
                   placeholder="Ex.: Dúvida sobre a cota de 03/07">
          </div>
          <div class="mb-3">
            <label class="form-label" style="font-size:.8rem">Mensagem</label>
            <textarea class="form-control form-control-sm" name="mensagem" rows="3" required
                      placeholder="Descreva a dúvida ou questionamento…"></textarea>
          </div>
          <button class="btn btn-sm btn-dark w-100"><i class="bi bi-send me-1"></i>Enviar para a administradora</button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><i class="bi bi-ticket-detailed me-1"></i> Meus chamados</div>
      <div class="card-body p-0">
        <?php foreach ($chamados as $ch): ?>
          <div class="p-3 border-bottom" style="font-size:.86rem">
            <div class="d-flex justify-content-between">
              <b><?= e_html($ch['assunto']) ?></b> <?= badge_status($ch['status']) ?>
            </div>
            <div class="text-muted mt-1"><?= e_html($ch['mensagem']) ?></div>
            <?php if ($ch['resposta']): ?>
              <div class="mt-2 p-2 rounded" style="background:#f0fdf4;border-left:3px solid #16a34a">
                <b style="font-size:.78rem"><i class="bi bi-reply me-1"></i><?= e_html($ch['respondido_por']) ?> (administradora):</b><br>
                <?= e_html($ch['resposta']) ?>
              </div>
            <?php endif; ?>
            <div class="text-muted mt-1" style="font-size:.72rem">aberto em <?= date('d/m/Y H:i', strtotime($ch['criado_em'])) ?></div>
          </div>
        <?php endforeach; ?>
        <?php if (!$chamados): ?><p class="text-muted text-center py-4 mb-0">Você ainda não abriu chamados.</p><?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php page_end();
