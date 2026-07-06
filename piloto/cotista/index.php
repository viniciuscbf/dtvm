<?php
// Portal do Cotista — entrada apenas com o token (UUID) fornecido pelo gestor
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (login_token($pdo, $_POST['token'] ?? '')) { header('Location: painel.php'); exit; }
    $erro = 'Token inválido, revogado ou mal formatado. Confirme com o gestor do fundo.';
}
if (isset($_GET['expirado'])) $erro = 'Seu acesso foi revogado ou expirou. Solicite um novo token ao gestor.';
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Portal do Cotista · Acesso por token</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="login-bg">
  <div class="login-card" style="max-width:560px;flex-direction:column">
    <div class="login-form text-center">
      <div style="font-size:2.2rem;color:#3b82f6"><i class="bi bi-person-badge"></i></div>
      <h4 class="mt-2 mb-1">Portal do Cotista</h4>
      <p class="text-muted" style="font-size:.85rem">
        Cole o token de acesso que o gestor do seu fundo enviou.<br>Sem cadastro, sem senha.
      </p>
      <?php if ($erro): ?><div class="alert alert-danger py-2" style="font-size:.83rem"><?= $erro ?></div><?php endif; ?>
      <form method="post" class="mt-3">
        <input class="form-control text-center" name="token" required
               placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
               pattern="[0-9a-fA-F-]{36}" maxlength="36" style="font-family:monospace;letter-spacing:.5px" autofocus>
        <button class="btn w-100 mt-3" style="background:#3b82f6;color:#fff"><i class="bi bi-unlock me-1"></i>Acessar meu fundo</button>
      </form>
      <p class="text-muted mt-4 mb-0" style="font-size:.72rem">
        O token define o que você vê (carteira em tempo real ou com defasagem) e pode ser
        revogado pelo gestor a qualquer momento.<br><a href="../index.php">← voltar à página inicial</a>
      </p>
    </div>
  </div>
</div>
</body>
</html>
