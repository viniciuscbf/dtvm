<?php
// Portal do Cotista — CONTA com e-mail e senha (substitui o acesso por token).
// O cotista pode se cadastrar sozinho; as posições aparecem quando a administradora/gestor
// vincula o CPF (ou já nascem vinculadas quando a conta é criada pelo gestor).
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

ensure_contas_cotista($pdo);   // DDL fora de transação

$erro = ''; $ok = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['entrar'])) {
    if (login_conta_cotista($pdo, $_POST['email'] ?? '', $_POST['senha'] ?? '')) {
        header('Location: home.php'); exit;
    }
    $erro = 'E-mail ou senha inválidos, ou conta bloqueada.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cadastrar'])) {
    $nome = trim($_POST['nome'] ?? '');
    $doc = trim($_POST['documento'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $s1 = $_POST['senha'] ?? ''; $s2 = $_POST['senha2'] ?? '';
    [$senhaOk, $senhaMsg] = senha_valida($s1);
    if ($nome === '' || $doc === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Preencha nome, CPF e um e-mail válido.';
    } elseif (!$senhaOk) {
        $erro = 'Senha fraca: ' . $senhaMsg;
    } elseif ($s1 !== $s2) {
        $erro = 'As senhas não conferem.';
    } elseif (empty($_POST['termo'])) {
        $erro = 'É preciso aceitar o termo de uso e a política de privacidade (LGPD).';
    } else {
        $st = $pdo->prepare('SELECT id FROM cotista_contas WHERE email = ?');
        $st->execute([$email]);
        if ($st->fetch()) {
            $erro = 'Já existe uma conta com este e-mail — use "Entrar".';
        } else {
            $pdo->prepare('INSERT INTO cotista_contas (nome, documento, email, senha_hash) VALUES (?,?,?,?)')
                ->execute([$nome, $doc, $email, password_hash($s1, PASSWORD_BCRYPT)]);
            registrar_auditoria($pdo, 'conta_cotista_autocadastro', ['entidade' => 'cotista_conta',
                'entidade_id' => (int)$pdo->lastInsertId(), 'detalhe' => "Autocadastro de $nome ($email)"]);
            $ok = 'Cadastro criado! Entre com seu e-mail e senha. Suas posições aparecem assim que a ' .
                  'administradora vincular o seu CPF — se você já é cotista, avise o gestor do seu fundo.';
        }
    }
}
if (isset($_GET['expirado'])) $erro = 'Sua sessão expirou ou a conta foi bloqueada. Entre novamente.';
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Portal do Cotista · Entrar</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="login-bg">
  <div class="login-card" style="max-width:560px;flex-direction:column">
    <div class="login-form">
      <div class="text-center">
        <div style="font-size:2.2rem;color:#3b82f6"><i class="bi bi-person-badge"></i></div>
        <h4 class="mt-2 mb-1">Portal do Cotista</h4>
        <p class="text-muted" style="font-size:.85rem">Acompanhe todos os fundos em que você investe, movimente e fale com o gestor.</p>
      </div>
      <?php if ($erro): ?><div class="alert alert-danger py-2" style="font-size:.83rem"><?= e_html($erro) ?></div><?php endif; ?>
      <?php if ($ok): ?><div class="alert alert-success py-2" style="font-size:.83rem"><?= e_html($ok) ?></div><?php endif; ?>

      <ul class="nav nav-tabs mb-3" style="font-size:.85rem">
        <li class="nav-item"><a class="nav-link active" data-alvo="aba-entrar" href="#">Entrar</a></li>
        <li class="nav-item"><a class="nav-link" data-alvo="aba-cadastro" href="#">Criar cadastro</a></li>
      </ul>

      <form method="post" id="aba-entrar">
        <input type="hidden" name="entrar" value="1">
        <label class="form-label" style="font-size:.78rem">E-mail</label>
        <input class="form-control form-control-sm mb-2" type="email" name="email" required autofocus>
        <label class="form-label" style="font-size:.78rem">Senha</label>
        <input class="form-control form-control-sm mb-3" type="password" name="senha" required>
        <button class="btn w-100" style="background:#3b82f6;color:#fff"><i class="bi bi-unlock me-1"></i>Entrar</button>
        <p class="text-muted mt-2 mb-0" style="font-size:.72rem">Demo: <code>ricardo.alves@email.com.br</code> · senha <code>Cotista@123</code></p>
      </form>

      <form method="post" id="aba-cadastro" style="display:none">
        <input type="hidden" name="cadastrar" value="1">
        <label class="form-label" style="font-size:.78rem">Nome completo</label>
        <input class="form-control form-control-sm mb-2" name="nome" required>
        <div class="row g-2">
          <div class="col-6"><label class="form-label" style="font-size:.78rem">CPF</label>
            <input class="form-control form-control-sm mb-2" name="documento" placeholder="000.000.000-00" required></div>
          <div class="col-6"><label class="form-label" style="font-size:.78rem">E-mail</label>
            <input class="form-control form-control-sm mb-2" type="email" name="email" required></div>
        </div>
        <div class="row g-2">
          <div class="col-6"><label class="form-label" style="font-size:.78rem">Senha</label>
            <input class="form-control form-control-sm mb-1" type="password" name="senha" required></div>
          <div class="col-6"><label class="form-label" style="font-size:.78rem">Confirmar senha</label>
            <input class="form-control form-control-sm mb-1" type="password" name="senha2" required></div>
        </div>
        <p class="text-muted mb-2" style="font-size:.68rem">Mínimo 8 caracteres, com maiúscula, número e caractere especial.</p>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" name="termo" id="termo" value="1">
          <label class="form-check-label" for="termo" style="font-size:.74rem">
            Li e aceito o termo de uso e a política de privacidade (LGPD).</label>
        </div>
        <button class="btn btn-dark w-100"><i class="bi bi-person-plus me-1"></i>Criar cadastro</button>
        <p class="text-muted mt-2 mb-0" style="font-size:.7rem">
          Suas posições aparecem quando a administradora vincular o seu CPF ao cadastro — se você já
          é cotista, avise o gestor do fundo. O cadastro completo (suitability, conta bancária) é
          finalizado no onboarding com a administradora.</p>
      </form>

      <p class="text-muted mt-4 mb-0 text-center" style="font-size:.72rem"><a href="../index.php">← voltar à página inicial</a></p>
    </div>
  </div>
</div>
<script>
document.querySelectorAll('[data-alvo]').forEach(function (a) {
  a.addEventListener('click', function (e) {
    e.preventDefault();
    document.querySelectorAll('[data-alvo]').forEach(function (x) { x.classList.remove('active'); });
    a.classList.add('active');
    document.getElementById('aba-entrar').style.display = a.dataset.alvo === 'aba-entrar' ? 'block' : 'none';
    document.getElementById('aba-cadastro').style.display = a.dataset.alvo === 'aba-cadastro' ? 'block' : 'none';
  });
});
</script>
</body>
</html>
