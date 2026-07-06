<?php
// Simulador Master — porta de entrada (senha própria, independente dos portais).
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';        // sessão + CSRF + cabeçalhos de segurança
require_once __DIR__ . '/../includes/simulador.php';

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validar()) {
        $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
    } elseif (($_POST['senha'] ?? '') === SIM_MASTER_SENHA) {
        $_SESSION['sim_master'] = true;
        header('Location: index.php'); exit;
    } else {
        $erro = 'Senha incorreta.';
    }
}
if (!empty($_SESSION['sim_master'])) { header('Location: index.php'); exit; }
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Simulador Master</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
  body { background:#0b1220; color:#e2e8f0; min-height:100vh; display:flex; align-items:center; justify-content:center; font-family:system-ui,sans-serif; }
  .master-card { background:#111c30; border:1px solid #1e2f4a; border-radius:16px; padding:38px; max-width:400px; width:100%; box-shadow:0 20px 60px rgba(0,0,0,.5); }
  .master-card h3 { font-weight:700; } .form-control { background:#0b1220; border-color:#26364f; color:#e2e8f0; }
  .form-control:focus { background:#0b1220; color:#fff; border-color:#3b82f6; box-shadow:none; }
</style>
</head>
<body>
  <div class="master-card">
    <div style="font-size:2.4rem;color:#f59e0b"><i class="bi bi-joystick"></i></div>
    <h3 class="mt-2 mb-1">Simulador Master</h3>
    <p class="text-secondary" style="font-size:.85rem">Painel de controle da simulação — reset, avanço de dia e injeção de eventos.</p>
    <?php if ($erro): ?><div class="alert alert-danger py-2" style="font-size:.85rem"><?= e_html($erro) ?></div><?php endif; ?>
    <form method="post">
      <?= csrf_campo() ?>
      <label class="form-label" style="font-size:.8rem">Senha master</label>
      <input type="password" name="senha" class="form-control mb-3" required autofocus>
      <button class="btn w-100" style="background:#f59e0b;color:#111;font-weight:600">Entrar no painel</button>
    </form>
    <p class="text-secondary mt-3 mb-0" style="font-size:.72rem">Demo: senha <code>master123</code> (troque em produção)</p>
    <a href="../index.php" style="color:#64748b;font-size:.72rem">← voltar à plataforma</a>
  </div>
</body>
</html>
