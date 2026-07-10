<?php
// Redefinir senha via chave temporária (link do "e-mail" simulado).
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
ensure_equipe($pdo);

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$erro = ''; $ok = false; $reset = validar_reset_senha($pdo, $token);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validar()) {
        $erro = 'Requisição inválida (proteção CSRF). Recarregue a página.';
    } else {
        [$sucesso, $msg] = redefinir_senha($pdo, $token, $_POST['senha'] ?? '');
        if ($sucesso) { $ok = true; } else { $erro = $msg; $reset = validar_reset_senha($pdo, $token); }
    }
}
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Redefinir senha · Portal do Gestor</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="login-bg">
  <div class="login-card" style="max-width:480px">
    <div class="login-form" style="width:100%">
      <h4 class="mb-1"><i class="bi bi-shield-lock me-1"></i> Redefinir senha</h4>
      <?php if ($ok): ?>
        <div class="alert alert-success py-2" style="font-size:.85rem"><i class="bi bi-check-circle me-1"></i>Senha redefinida com sucesso!</div>
        <a class="btn w-100" style="background:#14b8a6;color:#fff" href="login.php">Ir para o login</a>
      <?php elseif (!$reset): ?>
        <div class="alert alert-danger py-2" style="font-size:.85rem"><i class="bi bi-x-circle me-1"></i>
          Link inválido ou expirado. Peça um novo em <a href="recuperar.php">Recuperar senha</a>.</div>
      <?php else: ?>
        <p class="text-muted" style="font-size:.85rem">Conta: <b><?= e_html($reset['email']) ?></b>. Escolha uma nova senha (mín. 8, com maiúscula, número e símbolo).</p>
        <?php if ($erro): ?><div class="alert alert-danger py-2" style="font-size:.82rem"><?= e_html($erro) ?></div><?php endif; ?>
        <form method="post">
          <?= csrf_campo() ?>
          <input type="hidden" name="token" value="<?= e_html($token) ?>">
          <div class="mb-3">
            <label class="form-label" style="font-size:.8rem">Nova senha</label>
            <input type="password" name="senha" class="form-control" required minlength="8" pattern="<?= SENHA_PATTERN ?>" title="<?= SENHA_TITLE ?>" autofocus>
          </div>
          <button class="btn w-100" style="background:#14b8a6;color:#fff">Salvar nova senha</button>
        </form>
        <div class="text-center mt-3"><a href="login.php" style="font-size:.8rem;color:#0d9488">← voltar ao login</a></div>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
