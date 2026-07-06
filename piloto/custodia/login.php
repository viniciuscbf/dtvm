<?php
// Mesa de Custódia do banco — login da equipe do custodiante
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (login_usuario($pdo, trim($_POST['email'] ?? ''), $_POST['senha'] ?? '', 'custodia')) {
        header('Location: index.php'); exit;
    }
    $erro = 'Credenciais inválidas.';
}
if (usuario()) {
    $dest = ['admin' => '../admin/index.php', 'custodia' => 'index.php'][usuario()['perfil']] ?? '../gestor/index.php';
    header("Location: $dest"); exit;
}
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Banco Custodiante · Mesa de Custódia</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="login-bg">
  <div class="login-card" style="max-width:780px">
    <div class="login-lado">
      <div style="font-size:2rem;color:#93c5fd"><i class="bi bi-safe2"></i></div>
      <h2 class="mt-2">Mesa de Custódia<br>do banco</h2>
      <ul>
        <li>Contas segregadas por fundo no SELIC e na B3</li>
        <li>Mensageria RSFN/SPB (SELIC, STR, B3)</li>
        <li>Instruções de movimentação e liquidação DVP</li>
        <li>Arquivos diários de posição para a administradora</li>
      </ul>
      <div class="mt-auto" style="font-size:.72rem;color:#64748b">
        <a href="../index.php" style="color:#94a3b8">← voltar à página inicial</a>
      </div>
    </div>
    <div class="login-form">
      <h4 class="mb-1">Entrar</h4>
      <p class="text-muted" style="font-size:.85rem">Acesso da retaguarda de custódia (Res. CVM 32).</p>
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
        <button class="btn w-100" style="background:#3b82f6;color:#fff">Entrar</button>
      </form>
      <p class="text-muted mt-3 mb-0" style="font-size:.7rem">Demo: custodia@bancoparceiro.com.br — senha <code>demo123</code></p>
    </div>
  </div>
</div>
</body>
</html>
