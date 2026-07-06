<?php
// Constituição de novo fundo — cadastro real (grava gestora, fundo, checklist documental CVM 175)
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

/** Checklist documental exigido na abertura (espelha as exigências reais da Res. CVM 175 e da prática de mercado). */
function checklist_documentos(string $publicoAlvo): array {
    return [
        // categoria, nome, obrigatório?
        ['Gestora', 'Contrato ou estatuto social consolidado da gestora', 1],
        ['Gestora', 'Ato declaratório CVM de administrador de carteiras (Res. CVM 21)', 1],
        ['Gestora', 'Formulário de referência atualizado (Res. CVM 21, Anexo E)', 1],
        ['Gestora', 'Política de gestão de riscos', 1],
        ['Gestora', 'Política de PLD/FT (Res. CVM 50)', 1],
        ['Gestora', 'Certidões negativas de débitos (RFB/PGFN)', 1],
        ['Responsável', 'Documento de identidade e CPF do responsável', 1],
        ['Responsável', 'Comprovante de endereço do responsável', 1],
        ['Fundo', 'Minuta do regulamento (com anexo normativo da classe — Res. CVM 175)', 1],
        ['Fundo', 'Política de investimento da classe', 1],
        ['Fundo', 'Lâmina de informações essenciais', $publicoAlvo === 'Investidores em geral' ? 1 : 0],
        ['Fundo', 'Minuta do contrato de custódia', 1],
        ['Fundo', 'Minuta do contrato de auditoria independente', 1],
        ['Fundo', 'Contrato de distribuição', 0],
        ['Fundo', 'Modelo de termo de adesão do cotista', 1],
    ];
}

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $nomeFundo = trim($_POST['fundo_nome'] ?? '');
    if (strlen($senha) < 6) {
        $erro = 'A senha precisa ter ao menos 6 caracteres.';
    } elseif (!$email || !$nomeFundo) {
        $erro = 'Preencha os campos obrigatórios.';
    } else {
        $st = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE email = ?');
        $st->execute([$email]);
        if ($st->fetchColumn() > 0) {
            $erro = 'Já existe um usuário com este e-mail.';
        } else {
            $publico = $_POST['publico'] ?? 'Investidores em geral';
            $pdo->beginTransaction();
            // fundo em abertura
            $st = $pdo->prepare("INSERT INTO fundos (nome, cnpj, classe, publico_alvo, condominio, status, gestora, benchmark,
                                 taxa_adm, taxa_gestao, taxa_performance, caixa_atual, pl_atual, cota_atual, data_abertura)
                                 VALUES (?,?,?,?,?,'Em abertura',?,?,0.0008,?,?,0,0,1,NULL)");
            $st->execute([
                $nomeFundo, 'em registro', $_POST['classe'] ?? 'Renda Fixa', $publico,
                $_POST['condominio'] ?? 'Aberto', trim($_POST['gestora_nome'] ?? ''), $_POST['benchmark'] ?? 'CDI',
                min(0.05, max(0, (float)str_replace(',', '.', $_POST['taxa_gestao'] ?? '0') / 100)),
                min(0.5, max(0, (float)str_replace(',', '.', $_POST['taxa_perf'] ?: '0') / 100)),
            ]);
            $fid = (int)$pdo->lastInsertId();
            // usuário gestor (senha com hash bcrypt)
            $st = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, perfil, fundo_id, gestora, telefone, kyc_status)
                                 VALUES (?,?,?,'gestor',?,?,?,'Pendente')");
            $st->execute([trim($_POST['resp_nome'] ?? ''), $email, password_hash($senha, PASSWORD_DEFAULT),
                          $fid, trim($_POST['gestora_nome'] ?? ''), trim($_POST['telefone'] ?? '')]);
            // checklist documental — arquivo anexado vira 'Recebido', sem anexo fica 'Pendente'
            $ins = $pdo->prepare("INSERT INTO documentos_abertura (fundo_id, categoria, nome, obrigatorio, status, arquivo) VALUES (?,?,?,?,?,?)");
            foreach (checklist_documentos($publico) as $i => [$cat, $nome, $obr]) {
                $arq = $_FILES['doc_' . $i]['name'] ?? '';
                $ins->execute([$fid, $cat, $nome, $obr, $arq ? 'Recebido' : 'Pendente', $arq ?: null]);
            }
            // etapas do processo de abertura
            $etapas = ['Cadastro', 'Documentos', 'Análise KYC/PLD', 'Registro CVM', 'CNPJ Receita', 'Conta custodiante', 'Fundo apto'];
            $ins = $pdo->prepare("INSERT INTO onboarding_etapas (fundo_id, ordem, etapa, status, data_conclusao, responsavel) VALUES (?,?,?,?,?,?)");
            foreach ($etapas as $i => $e) {
                $ins->execute([$fid, $i + 1, $e, $i === 0 ? 'Concluída' : ($i === 1 ? 'Em andamento' : 'Pendente'),
                               $i === 0 ? date('Y-m-d') : null, $i === 0 ? 'Plataforma' : ($i === 1 ? 'Gestora' : null)]);
            }
            $pdo->commit();
            // login automático e direto para o acompanhamento
            login_usuario($pdo, $email, $senha, 'gestor');
            header('Location: abertura.php?novo=1'); exit;
        }
    }
}
$docs = checklist_documentos($_POST['publico'] ?? 'Investidores em geral');
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Constituir novo fundo · Portal do Gestor</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="../assets/css/style.css" rel="stylesheet">
<style>body{background:var(--bg)} .form-label{font-size:.8rem} .secao{font-size:.78rem;letter-spacing:1px;color:#64748b;text-transform:uppercase;margin:22px 0 10px;font-weight:700}</style>
</head>
<body>
<div class="container py-4" style="max-width:980px">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex align-items-center gap-2">
      <i class="bi bi-bank2" style="color:#c9a227;font-size:1.3rem"></i>
      <b style="letter-spacing:1px">ADMINISTRADORA</b>
      <span class="text-muted" style="font-size:.8rem">· constituição de fundo</span>
    </div>
    <a href="login.php" class="btn btn-sm btn-outline-secondary">Já tenho acesso →</a>
  </div>

  <div class="card">
    <div class="card-header"><i class="bi bi-rocket-takeoff me-1"></i> Solicitação de constituição de fundo
      <span class="text-muted float-end" style="font-size:.75rem">os documentos abaixo espelham as exigências da Res. CVM 175</span>
    </div>
    <div class="card-body">
      <?php if ($erro): ?><div class="alert alert-danger py-2" style="font-size:.85rem"><?= e_html($erro) ?></div><?php endif; ?>
      <form method="post" enctype="multipart/form-data">
        <div class="secao">1 · Gestora</div>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Razão social da gestora *</label>
            <input class="form-control form-control-sm" name="gestora_nome" required value="<?= e_html($_POST['gestora_nome'] ?? '') ?>"></div>
          <div class="col-md-3"><label class="form-label">CNPJ da gestora *</label>
            <input class="form-control form-control-sm" name="gestora_cnpj" required placeholder="00.000.000/0001-00"></div>
          <div class="col-md-3"><label class="form-label">Registro CVM (gestora) *</label>
            <input class="form-control form-control-sm" name="gestora_cvm" required placeholder="Ato declaratório nº"></div>
        </div>

        <div class="secao">2 · Responsável e acesso</div>
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label">Nome completo *</label>
            <input class="form-control form-control-sm" name="resp_nome" required value="<?= e_html($_POST['resp_nome'] ?? '') ?>"></div>
          <div class="col-md-3"><label class="form-label">CPF *</label>
            <input class="form-control form-control-sm" name="resp_cpf" required></div>
          <div class="col-md-3"><label class="form-label">E-mail (será o login) *</label>
            <input type="email" class="form-control form-control-sm" name="email" required value="<?= e_html($_POST['email'] ?? '') ?>"></div>
          <div class="col-md-2"><label class="form-label">Telefone</label>
            <input class="form-control form-control-sm" name="telefone"></div>
          <div class="col-md-4"><label class="form-label">Senha de acesso * <span class="text-muted">(mín. 6, guardada com hash bcrypt)</span></label>
            <input type="password" class="form-control form-control-sm" name="senha" required minlength="6"></div>
        </div>

        <div class="secao">3 · O fundo</div>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Nome do fundo *</label>
            <input class="form-control form-control-sm" name="fundo_nome" required placeholder="Ex.: Cedro RF Crédito Privado FI"></div>
          <div class="col-md-3"><label class="form-label">Classe (CVM 175)</label>
            <select class="form-select form-select-sm" name="classe"><option>Renda Fixa</option><option>Ações</option><option>Multimercado</option><option>Cambial</option></select></div>
          <div class="col-md-3"><label class="form-label">Benchmark</label>
            <select class="form-select form-select-sm" name="benchmark"><option>CDI</option><option>IPCA+</option><option>Ibovespa</option></select></div>
          <div class="col-md-3"><label class="form-label">Público-alvo</label>
            <select class="form-select form-select-sm" name="publico"><option>Investidores em geral</option><option>Qualificados</option><option>Profissionais</option></select></div>
          <div class="col-md-3"><label class="form-label">Condomínio</label>
            <select class="form-select form-select-sm" name="condominio"><option>Aberto</option><option>Fechado</option></select></div>
          <div class="col-md-3"><label class="form-label">Taxa de gestão (% a.a.) *</label>
            <input class="form-control form-control-sm" name="taxa_gestao" required placeholder="definida por você no regulamento"></div>
          <div class="col-md-3"><label class="form-label">Taxa de performance (%)</label>
            <input class="form-control form-control-sm" name="taxa_perf" placeholder="opcional — ex.: 20 s/ CDI"></div>
        </div>
        <p class="text-muted mt-2 mb-0" style="font-size:.75rem">
          <b>Gestão e performance são escolhas suas</b>, pactuadas no regulamento do fundo — a plataforma não as padroniza.
          A única taxa fixa da plataforma é a de <b>administração: 0,08% a.a. com piso de R$ 100/mês</b>.</p>

        <div class="secao">4 · Documentação exigida</div>
        <p class="text-muted" style="font-size:.8rem">Anexe o que já tiver — o que faltar entra como pendência e pode ser enviado depois.
          A administradora analisa <b>documento a documento</b>; o fundo só é lançado com o checklist aprovado.</p>
        <?php $cat = ''; foreach ($docs as $i => [$c, $nome, $obr]): ?>
          <?php if ($c !== $cat): $cat = $c; ?><div class="text-muted mt-3 mb-1" style="font-size:.74rem;font-weight:700"><?= e_html($c) ?></div><?php endif; ?>
          <div class="d-flex justify-content-between align-items-center border rounded p-2 mb-1" style="font-size:.84rem">
            <span><i class="bi bi-file-earmark-text me-2 text-muted"></i><?= e_html($nome) ?>
              <?= $obr ? '<span class="text-danger">*</span>' : '<span class="text-muted" style="font-size:.72rem">(opcional)</span>' ?></span>
            <input type="file" name="doc_<?= $i ?>" class="form-control form-control-sm" style="max-width:300px">
          </div>
        <?php endforeach; ?>

        <div class="form-check mt-3" style="font-size:.82rem">
          <input class="form-check-input" type="checkbox" required id="decl">
          <label class="form-check-label" for="decl">Declaro que as informações são verdadeiras e que a gestora está apta a exercer a gestão profissional de recursos (Res. CVM 21).</label>
        </div>
        <button class="btn btn-success mt-3 px-4"><i class="bi bi-send me-1"></i>Enviar solicitação e criar acesso</button>
      </form>
    </div>
  </div>
  <p class="text-muted mt-3" style="font-size:.72rem">Piloto: os arquivos não são armazenados — apenas o nome entra no checklist. Em produção, upload seguro com verificação e trilha de auditoria.</p>
</div>
</body>
</html>
