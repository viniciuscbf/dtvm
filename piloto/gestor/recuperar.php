<?php
// Recuperação de senha (PILOTO) — gera uma chave temporária e "envia por e-mail".
// Como não há servidor de e-mail, simulamos: ao solicitar, aparece um POPUP com o
// conteúdo do e-mail (link + chave). Em produção isso iria por e-mail de verdade.
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';   // puxa equipe.php (criar_reset_senha) via dominio
ensure_equipe($pdo);

$emailSim = null; $linkSim = null; $chaveSim = null; $aviso = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validar()) {
        $aviso = 'Requisição inválida (proteção CSRF). Recarregue a página.';
    } else {
        $email = trim($_POST['email'] ?? '');
        [$token, $u] = criar_reset_senha($pdo, $email);
        // resposta genérica (não revela se o e-mail existe)
        $aviso = 'Se houver uma conta com esse e-mail, enviamos um link de redefinição válido por 30 minutos.';
        if ($token) {   // PILOTO: exibimos o "e-mail" na tela
            $emailSim = $email;
            $chaveSim = $token;
            $linkSim  = rtrim((string)base_url(), '/') . '/gestor/redefinir.php?token=' . $token;
        }
    }
}
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Recuperar senha · Portal do Gestor</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="login-bg">
  <div class="login-card" style="max-width:520px">
    <div class="login-form" style="width:100%">
      <h4 class="mb-1"><i class="bi bi-key me-1"></i> Recuperar senha</h4>
      <p class="text-muted" style="font-size:.85rem">Informe seu e-mail. Enviaremos uma chave temporária para redefinir a senha.</p>
      <?php if ($aviso): ?><div class="alert alert-info py-2" style="font-size:.82rem"><?= e_html($aviso) ?></div><?php endif; ?>
      <form method="post">
        <?= csrf_campo() ?>
        <div class="mb-3">
          <label class="form-label" style="font-size:.8rem">E-mail da conta</label>
          <input type="email" name="email" class="form-control" required autofocus placeholder="voce@suagestora.com.br">
        </div>
        <button class="btn w-100" style="background:#14b8a6;color:#fff">Enviar link de redefinição</button>
      </form>
      <div class="text-center mt-3"><a href="login.php" style="font-size:.8rem;color:#0d9488">← voltar ao login</a></div>
    </div>
  </div>
</div>

<?php if ($linkSim): ?>
<!-- POPUP: simulação do e-mail recebido (só no piloto) -->
<div class="modal fade" id="mailSim" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header" style="background:#0f172a;color:#e2e8f0">
        <h5 class="modal-title"><i class="bi bi-envelope-paper me-2"></i>Simulando um e-mail recebido</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="border rounded p-3 mb-3" style="background:#f8fafc;font-size:.86rem">
          <div class="text-muted" style="font-size:.72rem">De: <b>naoresponda@administradora.com.br</b></div>
          <div class="text-muted" style="font-size:.72rem">Para: <b><?= e_html($emailSim) ?></b></div>
          <div class="text-muted mb-2" style="font-size:.72rem">Assunto: Redefinição de senha</div>
          <p class="mb-2">Recebemos um pedido para redefinir a sua senha. Clique no link abaixo (válido por 30 minutos):</p>
          <a href="<?= e_html($linkSim) ?>" class="d-block mb-2" style="word-break:break-all"><?= e_html($linkSim) ?></a>
          <p class="mb-1" style="font-size:.8rem">Ou use a chave temporária:</p>
          <code style="font-size:.9rem;background:#0f172a;color:#e2e8f0;padding:3px 8px;border-radius:5px;word-break:break-all"><?= e_html($chaveSim) ?></code>
        </div>
        <div class="alert alert-info py-2 mb-0" style="font-size:.76rem">
          O link de redefinição de senha aparece abaixo para você concluir a recuperação.
        </div>
      </div>
      <div class="modal-footer">
        <a href="<?= e_html($linkSim) ?>" class="btn btn-dark btn-sm"><i class="bi bi-box-arrow-in-right me-1"></i>Abrir link de redefinição</a>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>new bootstrap.Modal(document.getElementById('mailSim')).show();</script>
<?php endif; ?>
</body>
</html>
