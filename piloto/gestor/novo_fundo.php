<?php
// Criar novo fundo — para um gestor logado, com REGULAMENTO PADRONIZADO por tipo.
// Escolhe o tipo → preenche o formulário dirigido por schema (com validação e regras
// de nome) → pré-visualiza o regulamento gerado → cria. O criador vira gestor principal.
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

$u = exigir_perfil('gestor', 'admin');
ensure_equipe($pdo);
ensure_regulamento($pdo);

$tipos = reg_tipos();

function checklist_novo_fundo(string $publicoAlvo): array {
    return [
        ['Gestora', 'Contrato ou estatuto social consolidado da gestora', 1],
        ['Gestora', 'Ato declaratório CVM de administrador de carteiras (Res. CVM 21)', 1],
        ['Gestora', 'Política de gestão de riscos', 1],
        ['Gestora', 'Política de PLD/FT (Res. CVM 50)', 1],
        ['Responsável', 'Documento de identidade e CPF do responsável', 1],
        ['Fundo', 'Regulamento (gerado pela plataforma — revisar juridicamente)', 1],
        ['Fundo', 'Política de investimento da classe', 1],
        ['Fundo', 'Lâmina de informações essenciais', $publicoAlvo === 'Investidores em geral' ? 1 : 0],
        ['Fundo', 'Minuta do contrato de custódia', 1],
        ['Fundo', 'Minuta do contrato de auditoria independente', 1],
        ['Fundo', 'Modelo de termo de adesão do cotista', 1],
    ];
}

$tipo   = $_POST['reg_tipo'] ?? $_GET['tipo'] ?? '';
if ($tipo && !isset($tipos[$tipo])) $tipo = '';
$acao   = $_POST['acao'] ?? '';
$dados  = $tipo ? reg_coletar($tipo) : [];
$erros  = []; $criado = null; $previaHtml = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_validar()) {
    $erros[] = 'Requisição inválida (proteção CSRF). Recarregue a página.';
} elseif ($tipo && $acao === 'preview') {
    $erros = reg_validar($tipo, $dados);
    $previaHtml = reg_gerar_html($tipo, $dados);   // mostra a prévia mesmo com pendências
} elseif ($tipo && $acao === 'criar') {
    $erros = reg_validar($tipo, $dados);
    if (!$erros) {
        $t = $tipos[$tipo];
        $html = reg_gerar_html($tipo, $dados);
        $novoFid = null;
        com_transacao($pdo, function () use ($pdo, $u, $tipo, $t, $dados, $html, &$novoFid) {
            $taxaAdm  = (float)str_replace(',', '.', $dados['taxa_adm'] ?? '0') / 100;
            $taxaPerf = !empty($dados['tem_performance']) ? (float)str_replace(',', '.', $dados['taxa_performance'] ?? '0') / 100 : 0;
            $bench    = $dados['indexador'] ?? ($t['classe'] === 'FIP' ? 'IPCA+' : 'CDI');
            $tipoF    = $t['classe'] === 'FIP' ? 'FIP' : 'FIF';
            $st = $pdo->prepare("INSERT INTO fundos (nome, cnpj, classe, publico_alvo, condominio, status, gestora, benchmark,
                                 tributacao, tipo_fundo, taxa_adm, taxa_gestao, taxa_performance, caixa_atual, pl_atual, cota_atual,
                                 data_abertura, reg_tipo, reg_dados, reg_html)
                                 VALUES (?,?,?,?,?, 'Em abertura', ?, ?, ?, ?, ?, 0, ?, 0, 0, 1, NULL, ?, ?, ?)");
            $st->execute([
                $dados['nome'], 'em registro', $t['classe'], $dados['publico_alvo'], $dados['condominio'],
                trim($u['gestora'] ?? '') ?: 'Gestora', $bench, $dados['tributacao'] ?? $t['tributacao'], $tipoF,
                min(0.05, max(0, $taxaAdm)), min(0.5, max(0, $taxaPerf)),
                $tipo, json_encode($dados, JSON_UNESCAPED_UNICODE), $html,
            ]);
            $novoFid = (int)$pdo->lastInsertId();
            tornar_principal($pdo, $novoFid, (int)$u['id']);
            $ins = $pdo->prepare("INSERT INTO documentos_abertura (fundo_id, categoria, nome, obrigatorio, status, arquivo) VALUES (?,?,?,?, ?, ?)");
            foreach (checklist_novo_fundo($dados['publico_alvo']) as [$cat, $nomeDoc, $obr]) {
                // o regulamento entra como 'Recebido' (já gerado); os demais, 'Pendente'
                $recebido = str_starts_with($nomeDoc, 'Regulamento');
                $ins->execute([$novoFid, $cat, $nomeDoc, $obr, $recebido ? 'Recebido' : 'Pendente', $recebido ? 'regulamento_gerado.pdf' : null]);
            }
            $etapas = ['Cadastro', 'Documentos', 'Análise KYC/PLD', 'Registro CVM', 'CNPJ Receita', 'Conta custodiante', 'Fundo apto'];
            $insE = $pdo->prepare("INSERT INTO onboarding_etapas (fundo_id, ordem, etapa, status, data_conclusao, responsavel) VALUES (?,?,?,?,?,?)");
            foreach ($etapas as $i => $e) {
                $insE->execute([$novoFid, $i + 1, $e, $i === 0 ? 'Concluída' : ($i === 1 ? 'Em andamento' : 'Pendente'),
                                $i === 0 ? date('Y-m-d') : null, $i === 0 ? $u['nome'] : null]);
            }
        });
        registrar_auditoria($pdo, 'fundo_criado', ['entidade' => 'fundos', 'entidade_id' => $novoFid, 'fundo_id' => $novoFid,
            'detalhe' => "Fundo \"{$dados['nome']}\" ($tipo) criado com regulamento padronizado por {$u['nome']}"]);
        $_SESSION['gestor_fundo_id'] = $novoFid;
        $criado = ['id' => $novoFid, 'nome' => $dados['nome'], 'html' => $html];
    } else {
        $previaHtml = reg_gerar_html($tipo, $dados);
    }
}

page_start('Criar fundo', 'Criar fundo', $u,
    'Regulamento padronizado por tipo (Res. CVM 175) — preencha, pré-visualize e crie. Você vira o gestor principal.');
?>

<?php if ($erros): ?>
  <div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle me-1"></i>
    <b>Ajuste antes de criar:</b><ul class="mb-0 mt-1" style="font-size:.85rem"><?php foreach ($erros as $e): ?><li><?= e_html($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<?php if ($criado): ?>
  <div class="alert alert-success"><i class="bi bi-check-circle me-1"></i>
    Fundo <b><?= e_html($criado['nome']) ?></b> criado com regulamento gerado. Você é o <b>gestor principal</b>.
    Envie os documentos restantes em <a href="abertura.php">Abertura</a> e monte a equipe em <a href="equipe.php">Equipe do fundo</a>.</div>
  <div class="card"><div class="card-header"><i class="bi bi-file-earmark-text me-1"></i> Regulamento gerado</div>
    <div class="card-body" style="max-height:520px;overflow-y:auto"><?= $criado['html'] ?></div></div>
  <a class="btn btn-dark mt-3" href="abertura.php?novo=1">Ir para a abertura do fundo →</a>

<?php elseif (!$tipo): ?>
  <!-- Passo 1: escolher o tipo -->
  <div class="row row-cols-1 row-cols-md-2 g-3">
    <?php foreach ($tipos as $k => $t): ?>
      <div class="col">
        <a href="?tipo=<?= e_html($k) ?>" class="text-decoration-none">
          <div class="card h-100 border-2" style="transition:.1s">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start">
                <h6 class="mb-1"><i class="bi bi-diagram-3 me-1 text-primary"></i><?= e_html($t['nome']) ?></h6>
                <span class="badge bg-secondary"><?= e_html($t['classe']) ?></span>
              </div>
              <p class="text-muted mb-2" style="font-size:.82rem"><?= e_html($t['resumo']) ?></p>
              <div class="text-muted" style="font-size:.74rem"><i class="bi bi-journal-text me-1"></i><?= e_html($t['anexo']) ?>
                · <?= e_html(implode(' / ', $t['publico'])) ?></div>
            </div>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>
  <p class="text-muted mt-3" style="font-size:.8rem"><i class="bi bi-info-circle me-1"></i>
    FIDC e FII não estão disponíveis neste piloto. FIP é condomínio fechado e só para investidores qualificados.</p>

<?php else: ?>
  <!-- Passo 2: formulário do tipo -->
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div><span class="badge bg-primary"><?= e_html($tipos[$tipo]['classe']) ?></span>
      <b class="ms-1"><?= e_html($tipos[$tipo]['nome']) ?></b>
      <span class="text-muted" style="font-size:.8rem">· <?= e_html($tipos[$tipo]['anexo']) ?></span></div>
    <a href="novo_fundo.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Trocar tipo</a>
  </div>
  <form method="post">
    <?= csrf_campo() ?>
    <input type="hidden" name="reg_tipo" value="<?= e_html($tipo) ?>">
    <div class="row g-3">
      <div class="col-lg-<?= $previaHtml ? '6' : '12' ?>">
        <?= reg_render_form($tipo, $dados) ?>
        <div class="d-flex gap-2 mb-4">
          <button name="acao" value="preview" class="btn btn-outline-dark"><i class="bi bi-eye me-1"></i>Pré-visualizar regulamento</button>
          <button name="acao" value="criar" class="btn btn-success"><i class="bi bi-check-lg me-1"></i>Criar fundo</button>
        </div>
      </div>
      <?php if ($previaHtml): ?>
        <div class="col-lg-6">
          <div class="card" style="position:sticky;top:12px">
            <div class="card-header"><i class="bi bi-file-earmark-text me-1"></i> Prévia do regulamento</div>
            <div class="card-body" style="max-height:620px;overflow-y:auto"><?= $previaHtml ?></div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </form>
  <?= reg_form_js() ?>
<?php endif; ?>
<?php page_end();
