<?php
// Meus dados — perfil da conta, vínculos (suitability/KYC por fundo), conta bancária
// cadastrada (destino do resgate / origem esperada da aplicação) e troca de senha.
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$conta = exigir_conta_cotista($pdo);
$vinculos = fundos_da_conta($pdo, (int)$conta['id']);

$msg = ''; $msgTipo = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_validar()) {
    $_POST = []; $msg = 'Requisição inválida (proteção CSRF). Recarregue a página.'; $msgTipo = 'danger';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['salvar_banco'])) {
    $banco = trim($_POST['banco'] ?? ''); $ag = trim($_POST['agencia'] ?? '');
    $cc = trim($_POST['conta'] ?? ''); $pix = trim($_POST['pix_chave'] ?? '');
    if ($banco === '' || $cc === '') {
        $msg = 'Informe ao menos o banco e a conta.'; $msgTipo = 'warning';
    } elseif (!$vinculos) {
        $msg = 'Sua conta ainda não tem posições vinculadas.'; $msgTipo = 'warning';
    } else {
        // grava em TODOS os vínculos da conta (uma conta bancária por CPF, como na prática)
        $pdo->prepare('UPDATE cotistas SET banco=?, agencia=?, conta=?, pix_chave=? WHERE conta_id=?')
            ->execute([$banco, $ag, $cc, $pix, (int)$conta['id']]);
        registrar_auditoria($pdo, 'cotista_conta_bancaria_alterada', ['entidade' => 'cotista_conta',
            'entidade_id' => (int)$conta['id'],
            'detalhe' => "Conta bancária alterada pelo portal: $banco ag. $ag c/c $cc" . ($pix ? " · Pix $pix" : '')]);
        $msg = 'Conta bancária atualizada. Na prática, a alteração passa por validação da administradora ' .
               '(a titularidade é conferida no primeiro resgate).';
        $vinculos = fundos_da_conta($pdo, (int)$conta['id']);   // recarrega
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['trocar_senha'])) {
    $atual = $_POST['senha_atual'] ?? ''; $s1 = $_POST['senha_nova'] ?? ''; $s2 = $_POST['senha_nova2'] ?? '';
    [$senhaOk, $senhaMsg] = senha_valida($s1);
    if (!password_verify($atual, $conta['senha_hash'])) {
        $msg = 'Senha atual incorreta.'; $msgTipo = 'danger';
    } elseif (!$senhaOk) {
        $msg = 'Senha nova fraca: ' . $senhaMsg; $msgTipo = 'warning';
    } elseif ($s1 !== $s2) {
        $msg = 'A confirmação não confere com a senha nova.'; $msgTipo = 'warning';
    } else {
        $pdo->prepare('UPDATE cotista_contas SET senha_hash=? WHERE id=?')
            ->execute([password_hash($s1, PASSWORD_BCRYPT), (int)$conta['id']]);
        registrar_auditoria($pdo, 'cotista_senha_alterada', ['entidade' => 'cotista_conta',
            'entidade_id' => (int)$conta['id'], 'detalhe' => 'Senha alterada pelo próprio cotista no portal']);
        $msg = 'Senha alterada com sucesso.';
    }
}

// dados bancários atuais (iguais em todos os vínculos; pega do primeiro)
$bk = $vinculos[0] ?? null;
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Meus dados · Portal do Cotista</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="../assets/css/style.css" rel="stylesheet">
<style>body{background:var(--bg)}</style>
</head>
<body>
<nav style="background:var(--navy)" class="py-2 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
  <div class="d-flex align-items-center gap-2 text-white">
    <img src="../assets/favicon.png" alt="Argus" style="height:26px;width:26px;object-fit:contain">
    <b style="font-size:.85rem;letter-spacing:1px">PORTAL DO COTISTA</b>
  </div>
  <div class="d-flex align-items-center gap-2 flex-wrap">
    <a class="btn btn-sm btn-outline-light" href="home.php" style="font-size:.75rem"><i class="bi bi-house me-1"></i>Início</a>
    <a class="btn btn-sm btn-outline-light" href="movimentar.php" style="font-size:.75rem"><i class="bi bi-arrow-left-right me-1"></i>Movimentar</a>
    <a class="btn btn-sm btn-outline-light" href="tickets.php" style="font-size:.75rem"><i class="bi bi-chat-dots me-1"></i>Dúvidas</a>
    <a class="btn btn-sm btn-outline-light" href="sair.php" style="font-size:.75rem">Sair</a>
  </div>
</nav>

<div class="container py-4" style="max-width:1050px">
  <div class="mb-3">
    <h4 class="mb-0"><i class="bi bi-person-gear me-2"></i>Meus dados</h4>
    <span class="text-muted" style="font-size:.82rem">Cadastro da conta, vínculos com os fundos e segurança do acesso.</span>
  </div>

  <?php if ($msg): ?><div class="alert alert-<?= $msgTipo ?> py-2" style="font-size:.86rem"><i class="bi bi-info-circle me-1"></i><?= e_html($msg) ?></div><?php endif; ?>

  <div class="row g-3">
    <div class="col-lg-6">
      <div class="card mb-3">
        <div class="card-header"><i class="bi bi-person-vcard me-1"></i> Cadastro</div>
        <div class="card-body" style="font-size:.86rem">
          <div class="row mb-1"><div class="col-4 text-muted">Nome</div><div class="col-8"><b><?= e_html($conta['nome']) ?></b></div></div>
          <div class="row mb-1"><div class="col-4 text-muted">CPF</div><div class="col-8"><?= e_html($conta['documento']) ?></div></div>
          <div class="row mb-1"><div class="col-4 text-muted">E-mail</div><div class="col-8"><?= e_html($conta['email']) ?></div></div>
          <div class="row mb-1"><div class="col-4 text-muted">Conta desde</div><div class="col-8"><?= data_br($conta['criado_em']) ?></div></div>
          <div class="row"><div class="col-4 text-muted">Último acesso</div><div class="col-8"><?= $conta['ultimo_acesso'] ? date('d/m/Y H:i', strtotime($conta['ultimo_acesso'])) : '—' ?></div></div>
          <p class="text-muted mb-0 mt-2" style="font-size:.7rem">Alteração de nome, CPF ou e-mail passa pela
            administradora (exige revalidação cadastral) — <a href="tickets.php">abra um chamado</a>.</p>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><i class="bi bi-bank me-1"></i> Conta bancária cadastrada</div>
        <div class="card-body">
          <p class="text-muted" style="font-size:.74rem">Conta <b>da sua titularidade</b> (mesmo CPF): é o destino
            obrigatório dos resgates e a origem esperada das aplicações. Vale para todos os seus fundos.</p>
          <form method="post">
            <?= csrf_campo() ?><input type="hidden" name="salvar_banco" value="1">
            <div class="row g-2">
              <div class="col-7"><label class="form-label" style="font-size:.76rem">Banco</label>
                <input class="form-control form-control-sm" name="banco" value="<?= e_html($bk['banco'] ?? '') ?>" placeholder="Banco Exemplo S.A. (000)"></div>
              <div class="col-5"><label class="form-label" style="font-size:.76rem">Agência</label>
                <input class="form-control form-control-sm" name="agencia" value="<?= e_html($bk['agencia'] ?? '') ?>" placeholder="0001"></div>
              <div class="col-6"><label class="form-label" style="font-size:.76rem">Conta</label>
                <input class="form-control form-control-sm" name="conta" value="<?= e_html($bk['conta'] ?? '') ?>" placeholder="12345-6"></div>
              <div class="col-6"><label class="form-label" style="font-size:.76rem">Chave Pix (opcional)</label>
                <input class="form-control form-control-sm" name="pix_chave" value="<?= e_html($bk['pix_chave'] ?? '') ?>" placeholder="CPF, e-mail ou aleatória"></div>
            </div>
            <button class="btn btn-dark btn-sm w-100 mt-3" <?= $vinculos ? '' : 'disabled' ?>><i class="bi bi-check2 me-1"></i>Salvar conta bancária</button>
          </form>
          <p class="text-muted mb-0 mt-2" style="font-size:.7rem">A alteração fica sujeita à validação da administradora
            (prevenção à lavagem — o resgate nunca vai para conta de terceiro). Tudo registrado em auditoria.</p>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card mb-3">
        <div class="card-header"><i class="bi bi-diagram-3 me-1"></i> Meus vínculos</div>
        <div class="card-body p-0">
          <table class="table align-middle mb-0" style="font-size:.8rem">
            <thead><tr><th>Fundo</th><th>Suitability</th><th>KYC</th><th>Entrada</th></tr></thead>
            <tbody>
            <?php foreach ($vinculos as $v): ?>
              <tr>
                <td><b><?= e_html($v['fundo_nome']) ?></b></td>
                <td><?= $v['suitability'] ? badge($v['suitability'], 'info') : '<span class="text-muted">—</span>' ?></td>
                <td><?= badge($v['kyc_status'] ?: 'Pendente', ($v['kyc_status'] ?? '') === 'Aprovado' ? 'success' : 'warning') ?></td>
                <td class="text-muted"><?= data_br($v['data_entrada']) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$vinculos): ?><tr><td colspan="4" class="text-muted text-center py-4">Nenhum fundo vinculado ainda.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
        <div class="card-footer text-muted" style="font-size:.7rem">Suitability e KYC/PLD são conduzidos pela
          administradora no onboarding de cada fundo (Res. CVM 30 e 50).</div>
      </div>

      <div class="card">
        <div class="card-header"><i class="bi bi-shield-lock me-1"></i> Trocar senha</div>
        <div class="card-body">
          <form method="post">
            <?= csrf_campo() ?><input type="hidden" name="trocar_senha" value="1">
            <label class="form-label" style="font-size:.76rem">Senha atual</label>
            <input class="form-control form-control-sm mb-2" type="password" name="senha_atual" required>
            <div class="row g-2">
              <div class="col-6"><label class="form-label" style="font-size:.76rem">Senha nova</label>
                <input class="form-control form-control-sm" type="password" name="senha_nova" required></div>
              <div class="col-6"><label class="form-label" style="font-size:.76rem">Confirmar</label>
                <input class="form-control form-control-sm" type="password" name="senha_nova2" required></div>
            </div>
            <p class="text-muted mb-2 mt-1" style="font-size:.68rem">Mínimo 8 caracteres, com maiúscula, número e caractere especial.</p>
            <button class="btn btn-outline-dark btn-sm w-100"><i class="bi bi-key me-1"></i>Alterar senha</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
