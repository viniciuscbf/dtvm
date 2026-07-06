<?php
// Criar novo fundo — para um gestor JÁ logado. O criador vira o gestor PRINCIPAL
// do fundo (todo fundo tem ≥1 principal). O fundo nasce "Em abertura" e segue o
// checklist documental da Res. CVM 175 (analisado pela administradora em Aberturas).
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

$u = exigir_perfil('gestor', 'admin');
ensure_equipe($pdo);

/** Mesmo checklist da constituição pública (espelha a Res. CVM 175). */
function checklist_novo_fundo(string $publicoAlvo): array {
    return [
        ['Gestora', 'Contrato ou estatuto social consolidado da gestora', 1],
        ['Gestora', 'Ato declaratório CVM de administrador de carteiras (Res. CVM 21)', 1],
        ['Gestora', 'Política de gestão de riscos', 1],
        ['Gestora', 'Política de PLD/FT (Res. CVM 50)', 1],
        ['Responsável', 'Documento de identidade e CPF do responsável', 1],
        ['Fundo', 'Minuta do regulamento (com anexo normativo da classe — Res. CVM 175)', 1],
        ['Fundo', 'Política de investimento da classe', 1],
        ['Fundo', 'Lâmina de informações essenciais', $publicoAlvo === 'Investidores em geral' ? 1 : 0],
        ['Fundo', 'Minuta do contrato de custódia', 1],
        ['Fundo', 'Minuta do contrato de auditoria independente', 1],
        ['Fundo', 'Modelo de termo de adesão do cotista', 1],
    ];
}

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_validar()) {
    $erro = 'Requisição inválida (proteção CSRF). Recarregue a página.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['fundo_nome'] ?? '');
    if ($nome === '') {
        $erro = 'Informe o nome do fundo.';
    } else {
        $publico = $_POST['publico'] ?? 'Investidores em geral';
        $novoFid = null;
        com_transacao($pdo, function () use ($pdo, $u, $nome, $publico, &$novoFid) {
            $st = $pdo->prepare("INSERT INTO fundos (nome, cnpj, classe, publico_alvo, condominio, status, gestora, benchmark,
                                 tipo_fundo, taxa_adm, taxa_gestao, taxa_performance, caixa_atual, pl_atual, cota_atual, data_abertura)
                                 VALUES (?,?,?,?,?, 'Em abertura', ?, ?, ?, 0.0008, ?, ?, 0, 0, 1, NULL)");
            $st->execute([
                $nome, 'em registro', $_POST['classe'] ?? 'Renda Fixa', $publico,
                $_POST['condominio'] ?? 'Aberto', trim($u['gestora'] ?? '') ?: 'Gestora', $_POST['benchmark'] ?? 'CDI',
                in_array($_POST['tipo_fundo'] ?? 'FIF', ['FIF', 'FIC', 'FIP'], true) ? $_POST['tipo_fundo'] : 'FIF',
                min(0.05, max(0, (float)str_replace(',', '.', $_POST['taxa_gestao'] ?? '0') / 100)),
                min(0.5, max(0, (float)str_replace(',', '.', $_POST['taxa_perf'] ?: '0') / 100)),
            ]);
            $novoFid = (int)$pdo->lastInsertId();
            // criador vira o gestor PRINCIPAL do fundo
            tornar_principal($pdo, $novoFid, (int)$u['id']);
            // checklist documental (entra pendente — envie os anexos na tela de abertura)
            $ins = $pdo->prepare("INSERT INTO documentos_abertura (fundo_id, categoria, nome, obrigatorio, status, arquivo) VALUES (?,?,?,?, 'Pendente', NULL)");
            foreach (checklist_novo_fundo($publico) as [$cat, $nomeDoc, $obr]) {
                $ins->execute([$novoFid, $cat, $nomeDoc, $obr]);
            }
            // etapas do processo
            $etapas = ['Cadastro', 'Documentos', 'Análise KYC/PLD', 'Registro CVM', 'CNPJ Receita', 'Conta custodiante', 'Fundo apto'];
            $insE = $pdo->prepare("INSERT INTO onboarding_etapas (fundo_id, ordem, etapa, status, data_conclusao, responsavel) VALUES (?,?,?,?,?,?)");
            foreach ($etapas as $i => $e) {
                $insE->execute([$novoFid, $i + 1, $e, $i === 0 ? 'Concluída' : ($i === 1 ? 'Em andamento' : 'Pendente'),
                                $i === 0 ? date('Y-m-d') : null, $i === 0 ? $u['nome'] : null]);
            }
        });
        registrar_auditoria($pdo, 'fundo_criado', ['entidade' => 'fundos', 'entidade_id' => $novoFid, 'fundo_id' => $novoFid,
            'detalhe' => "Fundo \"$nome\" criado por {$u['nome']} (gestor principal)"]);
        $_SESSION['gestor_fundo_id'] = $novoFid;   // foca o novo fundo
        header('Location: abertura.php?novo=1'); exit;
    }
}

page_start('Criar fundo', 'Criar fundo', $u, 'Você será o gestor principal — o fundo nasce "Em abertura" e segue o checklist da Res. CVM 175');
?>

<?php if ($erro): ?><div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle me-1"></i><?= e_html($erro) ?></div><?php endif; ?>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header"><i class="bi bi-rocket-takeoff me-1"></i> Dados do novo fundo</div>
      <div class="card-body">
        <form method="post" class="row g-3">
          <?= csrf_campo() ?>
          <div class="col-md-8">
            <label class="form-label" style="font-size:.8rem">Nome do fundo *</label>
            <input class="form-control form-control-sm" name="fundo_nome" required placeholder="Ex.: Cedro RF Crédito Privado FI">
          </div>
          <div class="col-md-4">
            <label class="form-label" style="font-size:.8rem">Tipo</label>
            <select class="form-select form-select-sm" name="tipo_fundo">
              <option value="FIF">FIF (carteira própria)</option>
              <option value="FIC">FIC (compra cotas de fundos)</option>
              <option value="FIP">FIP (private equity)</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label" style="font-size:.8rem">Classe (CVM 175)</label>
            <select class="form-select form-select-sm" name="classe"><option>Renda Fixa</option><option>Ações</option><option>Multimercado</option><option>Cambial</option></select>
          </div>
          <div class="col-md-4">
            <label class="form-label" style="font-size:.8rem">Benchmark</label>
            <select class="form-select form-select-sm" name="benchmark"><option>CDI</option><option>IPCA+</option><option>Ibovespa</option></select>
          </div>
          <div class="col-md-4">
            <label class="form-label" style="font-size:.8rem">Público-alvo</label>
            <select class="form-select form-select-sm" name="publico"><option>Investidores em geral</option><option>Qualificados</option><option>Profissionais</option></select>
          </div>
          <div class="col-md-4">
            <label class="form-label" style="font-size:.8rem">Condomínio</label>
            <select class="form-select form-select-sm" name="condominio"><option>Aberto</option><option>Fechado</option></select>
          </div>
          <div class="col-md-4">
            <label class="form-label" style="font-size:.8rem">Taxa de gestão (% a.a.) *</label>
            <input class="form-control form-control-sm" name="taxa_gestao" required placeholder="0,70">
          </div>
          <div class="col-md-4">
            <label class="form-label" style="font-size:.8rem">Taxa de performance (%)</label>
            <input class="form-control form-control-sm" name="taxa_perf" placeholder="20 (opcional)">
          </div>
          <div class="col-12">
            <button class="btn btn-success btn-sm px-4"><i class="bi bi-check-lg me-1"></i>Criar fundo e virar gestor principal</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-info-circle me-1"></i> O que acontece</div>
      <div class="card-body">
        <ul style="font-size:.85rem" class="mb-2">
          <li>Você vira o <b>gestor principal</b> do fundo (todo fundo tem ≥1 principal).</li>
          <li>O fundo nasce <b>Em abertura</b> e recebe o <b>checklist documental</b> da Res. CVM 175.</li>
          <li>Você envia os documentos na tela de <b>abertura</b>; a administradora analisa e <b>lança</b> o fundo.</li>
          <li>Depois de principal, você pode <b>convidar</b> colegas em <a href="equipe.php">Equipe do fundo</a> e liberar permissões.</li>
        </ul>
        <div class="alert alert-secondary py-2 mb-0" style="font-size:.78rem">
          As taxas de gestão/performance são suas (regulamento). A taxa de administração é a da plataforma: 0,08% a.a. (piso R$ 100/mês).
        </div>
      </div>
    </div>
  </div>
</div>
<?php page_end();
