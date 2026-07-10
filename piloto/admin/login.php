<?php
// Área restrita da administradora — login da equipe
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (login_usuario($pdo, trim($_POST['email'] ?? ''), $_POST['senha'] ?? '', 'admin', !empty($_POST['lembrar']))) {
        header('Location: index.php'); exit;
    }
    $erro = 'Credenciais inválidas.';
}
if (usuario()) { header('Location: ' . (usuario()['perfil'] === 'admin' ? 'index.php' : '../gestor/index.php')); exit; }
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Argus DTVM · Área restrita</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="login-bg">
  <div class="login-card" style="max-width:760px">
    <div class="login-lado">
      <div style="font-size:2rem;color:#8b7ad0"><i class="bi bi-shield-lock"></i></div>
      <h2 class="mt-2">Área restrita da equipe</h2>
      <ul>
        <li>Processamento diário e fechamento de cota</li>
        <li>Lançamentos, ajustes e reprocessamento (inclusive retroativo)</li>
        <li>Aprovação documental das aberturas de fundos</li>
        <li>Conciliação, IA de fraude e repasses</li>
      </ul>
      <div class="mt-auto" style="font-size:.72rem;color:#64748b">
        <a href="../index.php" style="color:#94a3b8">← voltar à página inicial</a>
      </div>
    </div>
    <div class="login-form">
      <h4 class="mb-1">Entrar</h4>
      <p class="text-muted" style="font-size:.85rem">Acesso exclusivo de colaboradores.</p>
      <?php if ($erro): ?><div class="alert alert-danger py-2" style="font-size:.85rem"><?= $erro ?></div><?php endif; ?>
      <form method="post">
        <div class="mb-2">
          <label class="form-label" style="font-size:.8rem">E-mail corporativo</label>
          <input type="email" name="email" class="form-control" required autofocus>
        </div>
        <div class="mb-3">
          <label class="form-label" style="font-size:.8rem">Senha</label>
          <input type="password" name="senha" class="form-control" required>
        </div>
        <div class="mb-3 form-check">
          <input type="checkbox" class="form-check-input" name="lembrar" id="lembrar" value="1">
          <label class="form-check-label" for="lembrar" style="font-size:.82rem">Continuar conectado neste dispositivo</label>
        </div>
        <button class="btn btn-dark w-100">Entrar</button>
      </form>
      <p class="text-muted mt-3 mb-0" style="font-size:.7rem">Demo: admin@administradora.com.br — senha <code>demo123</code></p>
    </div>
  </div>
</div>
</body>
</html>
