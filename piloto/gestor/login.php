<?php
// Portal do Gestor — login (e-mail + senha, hash bcrypt no banco)
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (login_usuario($pdo, trim($_POST['email'] ?? ''), $_POST['senha'] ?? '', 'gestor')) {
        // fundo em abertura? o gestor cai direto no status do processo
        $u = usuario();
        $st = $pdo->prepare('SELECT status FROM fundos WHERE id = ?');
        $st->execute([$u['fundo_id']]);
        $statusFundo = $st->fetchColumn();
        header('Location: ' . ($statusFundo === 'Ativo' ? 'index.php' : 'abertura.php'));
        exit;
    }
    $erro = 'E-mail ou senha inválidos.';
}
if (usuario()) { header('Location: ' . (usuario()['perfil'] === 'admin' ? '../admin/index.php' : 'index.php')); exit; }
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Portal do Gestor · Acesso</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="login-bg">
  <div class="login-card" style="max-width:820px">
    <div class="login-lado">
      <div style="font-size:2rem;color:#2dd4bf"><i class="bi bi-briefcase"></i></div>
      <h2 class="mt-2">Portal do Gestor</h2>
      <ul>
        <li>Aprove ou rejeite a cota do dia anterior (D-1)</li>
        <li>Carteira, caixa e relatórios — inclusive retroativos</li>
        <li>Gere e revogue os acessos dos seus cotistas</li>
        <li>Acompanhe a abertura do seu fundo etapa a etapa</li>
      </ul>
      <div class="mt-auto" style="font-size:.72rem;color:#64748b">
        <a href="../index.php" style="color:#94a3b8">← voltar à página inicial</a>
      </div>
    </div>
    <div class="login-form">
      <h4 class="mb-1">Entrar</h4>
      <p class="text-muted" style="font-size:.85rem">Acesso exclusivo de gestoras cadastradas.</p>
      <?php if ($erro): ?><div class="alert alert-danger py-2" style="font-size:.85rem"><?= $erro ?></div><?php endif; ?>
      <form method="post">
        <div class="mb-2">
          <label class="form-label" style="font-size:.8rem">E-mail</label>
          <input type="email" name="email" class="form-control" required autofocus>
        </div>
        <div class="mb-3">
          <label class="form-label" style="font-size:.8rem">Senha</label>
          <input type="password" name="senha" class="form-control" required>
        </div>
        <button class="btn w-100" style="background:#14b8a6;color:#fff">Entrar no portal</button>
      </form>
      <hr>
      <p style="font-size:.82rem" class="mb-1">Ainda não é cliente?</p>
      <a class="btn btn-outline-dark btn-sm w-100" href="cadastro.php"><i class="bi bi-rocket-takeoff me-1"></i>Constituir um novo fundo</a>
      <p class="text-muted mt-3 mb-0" style="font-size:.7rem">
        Demo: gestor@auroracapital.com.br · gestor@horizonteinvest.com.br · gestor@novafronteira.com.br (fundo em abertura) — senha <code>demo123</code>
      </p>
    </div>
  </div>
</div>
</body>
</html>
