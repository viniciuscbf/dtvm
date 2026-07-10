<?php
// Status da abertura do fundo — o gestor de fundo em abertura cai aqui direto após o login
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

$u = exigir_perfil('gestor', 'admin');
$fundo = fundo_do_usuario($pdo, $u);
if (!$fundo) die('Sem fundo vinculado.');
$fid = (int)$fundo['id'];

if ($fundo['status'] === 'Ativo') { header('Location: index.php'); exit; }

ensure_documentos_conteudo($pdo);   // garante a coluna de conteúdo das minutas (fora de transação)
$st = $pdo->prepare('SELECT * FROM onboarding_etapas WHERE fundo_id = ? ORDER BY ordem');
$st->execute([$fid]);
$etapas = $st->fetchAll();

$st = $pdo->prepare("SELECT * FROM documentos_abertura WHERE fundo_id = ? ORDER BY FIELD(categoria,'Gestora','Responsável','Fundo'), id");
$st->execute([$fid]);
$docs = $st->fetchAll();

// garante que as minutas dos documentos do fundo (categoria "Fundo") existam e estejam guardadas
if (array_filter($docs, fn($d) => $d['categoria'] === 'Fundo' && empty($d['conteudo']))) {
    gerar_e_salvar_templates($pdo, $fid);
    $st->execute([$fid]);
    $docs = $st->fetchAll();
}

// reenvio de documento pendente/rejeitado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['reenviar_doc'])) {
    $arq = $_FILES['arquivo']['name'] ?? '';
    if ($arq) {
        $pdo->prepare("UPDATE documentos_abertura SET status='Recebido', arquivo=?, motivo=NULL, atualizado_em=NOW() WHERE id=? AND fundo_id=?")
            ->execute([$arq, (int)$_POST['reenviar_doc'], $fid]);
        header('Location: abertura.php?enviado=1'); exit;
    }
}

$pendentes = array_filter($docs, fn($d) => $d['obrigatorio'] && !in_array($d['status'], ['Aprovado'], true));
$etapaAtual = null;
foreach ($etapas as $e) if ($e['status'] !== 'Concluída') { $etapaAtual = $e; break; }

page_start('Abertura do fundo', 'Visão geral', $u,
    e_html($fundo['nome']) . ' · ' . badge_status($fundo['status']) . ' · o portal completo é liberado quando o fundo entra em operação');
?>

<?php if (isset($_GET['novo'])): ?>
  <div class="alert alert-success"><i class="bi bi-check-circle me-1"></i>
    <b>Solicitação recebida!</b> Seu acesso foi criado. Acompanhe abaixo cada etapa e o status de análise dos documentos.</div>
<?php elseif (isset($_GET['enviado'])): ?>
  <div class="alert alert-success py-2"><i class="bi bi-check-circle me-1"></i>Documento reenviado — entrou na fila de análise da administradora.</div>
<?php endif; ?>

<?php $minutas = array_filter($docs, fn($d) => !empty($d['conteudo'])); ?>
<?php if ($minutas): ?>
<div class="card mb-3 border-success">
  <div class="card-header"><i class="bi bi-file-earmark-text me-1"></i> Minutas dos documentos do fundo — geradas pela plataforma
    <span class="text-muted float-end" style="font-size:.74rem">preenchidas com os dados do seu fundo · Res. CVM 175</span></div>
  <div class="card-body">
    <p class="text-muted" style="font-size:.82rem;margin-bottom:.7rem">Estas minutas já vêm prontas para consulta e download. Revise com seu advogado antes do registro na CVM.</p>
    <div class="row g-2">
      <?php foreach ($minutas as $d): ?>
        <div class="col-md-6">
          <div class="d-flex justify-content-between align-items-center border rounded p-2" style="font-size:.85rem">
            <span class="text-truncate me-2"><i class="bi bi-file-earmark-text me-2 text-muted"></i><?= e_html($d['nome']) ?></span>
            <span class="d-flex gap-1 flex-shrink-0">
              <a href="documento_ver.php?id=<?= (int)$d['id'] ?>" class="btn btn-sm btn-outline-primary" title="Ver"><i class="bi bi-eye"></i></a>
              <a href="documento_ver.php?id=<?= (int)$d['id'] ?>&dl=1" class="btn btn-sm btn-outline-secondary" title="Baixar"><i class="bi bi-download"></i></a>
            </span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card">
      <div class="card-header"><i class="bi bi-list-check me-1"></i> Etapas do processo</div>
      <div class="card-body">
        <ul class="stepper">
          <?php foreach ($etapas as $et):
              $cls = $et['status'] === 'Concluída' ? 'step-ok' : ($et['status'] === 'Em andamento' ? 'step-atual' : ''); ?>
            <li class="<?= $cls ?>">
              <div class="step-dot"><?= $et['status'] === 'Concluída' ? '<i class="bi bi-check"></i>' : (int)$et['ordem'] ?></div>
              <div>
                <div class="step-titulo"><?= e_html($et['etapa']) ?> <?= badge_status($et['status']) ?></div>
                <div class="step-meta">
                  <?= $et['data_conclusao'] ? 'concluída em ' . data_br($et['data_conclusao']) : ($et['status'] === 'Em andamento' ? 'em andamento' : 'aguardando') ?>
                  <?= $et['responsavel'] ? ' · resp.: ' . e_html($et['responsavel']) : '' ?>
                </div>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>

    <div class="card mt-3 <?= $pendentes ? 'border-warning' : 'border-success' ?>">
      <div class="card-body" style="font-size:.86rem">
        <?php if ($pendentes): ?>
          <b><i class="bi bi-exclamation-triangle text-warning me-1"></i>O que falta para o lançamento:</b>
          <ul class="mb-0 mt-2">
            <?php if ($etapaAtual): ?><li>Etapa atual: <b><?= e_html($etapaAtual['etapa']) ?></b></li><?php endif; ?>
            <?php foreach ($pendentes as $p): ?>
              <li><?= e_html($p['nome']) ?> — <?= badge_status($p['status']) ?>
                <?= $p['status'] === 'Rejeitado' && $p['motivo'] ? '<span class="text-danger" style="font-size:.78rem"> (' . e_html($p['motivo']) . ')</span>' : '' ?></li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <b><i class="bi bi-check-circle text-success me-1"></i>Checklist documental completo.</b>
          O lançamento depende apenas da conclusão das etapas operacionais pela administradora.
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card">
      <div class="card-header"><i class="bi bi-folder-check me-1"></i> Análise documental (documento a documento)</div>
      <div class="card-body p-0">
        <table class="table align-middle mb-0" style="font-size:.85rem">
          <thead><tr><th>Documento</th><th>Categoria</th><th>Status</th><th style="min-width:230px">Ação</th></tr></thead>
          <tbody>
          <?php foreach ($docs as $d): ?>
            <tr class="<?= $d['status'] === 'Rejeitado' ? 'table-danger' : '' ?>">
              <td><?= e_html($d['nome']) ?> <?= $d['obrigatorio'] ? '<span class="text-danger">*</span>' : '' ?>
                <?php if ($d['arquivo']): ?><br><span class="text-muted" style="font-size:.72rem"><i class="bi bi-paperclip"></i> <?= e_html($d['arquivo']) ?></span><?php endif; ?>
                <?php if (!empty($d['conteudo'])): ?><br><a href="documento_ver.php?id=<?= (int)$d['id'] ?>" style="font-size:.74rem"><i class="bi bi-file-earmark-text me-1"></i>ver minuta gerada</a><?php endif; ?>
                <?php if ($d['status'] === 'Rejeitado' && $d['motivo']): ?><br><span class="text-danger" style="font-size:.75rem"><i class="bi bi-x-circle"></i> <?= e_html($d['motivo']) ?></span><?php endif; ?>
              </td>
              <td><?= badge($d['categoria'], 'secondary') ?></td>
              <td><?= badge_status($d['status']) ?></td>
              <td>
                <?php if (in_array($d['status'], ['Pendente', 'Rejeitado'], true)): ?>
                  <form method="post" enctype="multipart/form-data" class="d-flex gap-1">
                    <input type="hidden" name="reenviar_doc" value="<?= (int)$d['id'] ?>">
                    <input type="file" name="arquivo" class="form-control form-control-sm" required>
                    <button class="btn btn-sm btn-dark"><i class="bi bi-upload"></i></button>
                  </form>
                <?php elseif ($d['status'] === 'Recebido'): ?>
                  <span class="text-muted" style="font-size:.78rem"><i class="bi bi-hourglass-split me-1"></i>em análise pela administradora</span>
                <?php else: ?>
                  <span class="text-success" style="font-size:.78rem"><i class="bi bi-check-lg me-1"></i>aprovado</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <p class="text-muted mt-2" style="font-size:.75rem"><i class="bi bi-info-circle me-1"></i>
      Enquanto o fundo estiver em abertura, as demais abas do portal ficam bloqueadas — este é o único painel disponível, exatamente como o processo real.</p>
  </div>
</div>
<?php page_end();
