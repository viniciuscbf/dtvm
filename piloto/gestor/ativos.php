<?php
// Catálogo de instrumentos & solicitação de cadastro de ativo (perfil gestor).
// Abas: ativos no catálogo (filtrável por categoria) · minhas solicitações · aguardando cadastro.
// Solicitação aceita ANEXO .pdf; há cadastro em LOTE (vários PDFs de uma vez).
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

$u = exigir_perfil('gestor', 'admin');
ensure_catalogo($pdo);

$fundo = fundo_do_usuario($pdo, $u);
if (!$fundo) die('Sem fundo vinculado.');
exigir_fundo_ativo($fundo);
$fid = (int)$fundo['id'];
exigir_permissao($pdo, $u, $fid, 'solicitar_ativos');

const TIPOS_ATIVO = ['Título Público', 'Debênture', 'CDB', 'CRI/CRA', 'Ação', 'ETF', 'Cota de Fundo'];

/** Lê e valida um upload de PDF (≤ 4 MB). Retorna ['nome'=>, 'conteudo'=>] ou null. */
function ler_pdf_upload(array $f): ?array {
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
    if (($f['size'] ?? 0) <= 0 || $f['size'] > 4 * 1024 * 1024) return null;
    $nome = (string)($f['name'] ?? '');
    if (!preg_match('/\.pdf$/i', $nome) || !is_uploaded_file($f['tmp_name'])) return null;
    $c = file_get_contents($f['tmp_name']);
    if ($c === false || strncmp($c, '%PDF', 4) !== 0) return null;   // confere a assinatura do PDF
    return ['nome' => $nome, 'conteudo' => $c];
}

/** Insere uma solicitação (com anexo opcional, ligado como LOB). */
function inserir_solic(PDO $pdo, int $fid, string $sol, string $cod, string $nom, string $tip,
                       string $emi, string $idx, string $tax, ?string $ven, string $det, int $lote, ?array $anexo): int {
    $st = $pdo->prepare("INSERT INTO solicitacoes_cadastro_ativo
        (fundo_id, solicitante, codigo, nome, tipo, emissor, indexador, taxa, vencimento, detalhe, status, lote, anexo, anexo_nome)
        VALUES (:fid,:sol,:cod,:nom,:tip,:emi,:idx,:tax,:ven,:det,'Solicitado',:lot,:anx,:ann)");
    $st->bindValue(':fid', $fid, PDO::PARAM_INT);
    $st->bindValue(':sol', $sol); $st->bindValue(':cod', $cod); $st->bindValue(':nom', $nom);
    $st->bindValue(':tip', $tip); $st->bindValue(':emi', $emi); $st->bindValue(':idx', $idx);
    $st->bindValue(':tax', $tax);
    $st->bindValue(':ven', $ven, $ven === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $st->bindValue(':det', $det);
    $st->bindValue(':lot', $lote, PDO::PARAM_INT);
    $st->bindValue(':anx', $anexo['conteudo'] ?? null, $anexo ? PDO::PARAM_LOB : PDO::PARAM_NULL);
    $st->bindValue(':ann', $anexo['nome'] ?? null, $anexo ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $st->execute();
    return (int)$pdo->lastInsertId();
}

$msg = ''; $msgTipo = 'success'; $abaAtiva = 'catalogo';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_validar()) {
    $_POST = []; $msg = 'Requisição inválida (proteção CSRF). Recarregue a página.'; $msgTipo = 'danger';
}

// AÇÃO: solicitação individual (com anexo .pdf opcional)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['solicitar'])) {
    $abaAtiva = 'aguard';
    $codigo = strtoupper(trim($_POST['codigo'] ?? '')); $nome = trim($_POST['nome'] ?? '');
    $tipo = $_POST['tipo'] ?? '';
    $anexo = isset($_FILES['anexo']) ? ler_pdf_upload($_FILES['anexo']) : null;
    if ($codigo === '' || $nome === '' || !in_array($tipo, TIPOS_ATIVO, true)) {
        $msg = 'Informe ao menos código, nome e um tipo válido.'; $msgTipo = 'danger';
    } elseif (!empty($_FILES['anexo']['name']) && !$anexo) {
        $msg = 'O anexo precisa ser um PDF válido de até 4 MB.'; $msgTipo = 'danger';
    } else {
        $st = $pdo->prepare('SELECT COUNT(*) FROM ativos_catalogo WHERE codigo = ?');
        $st->execute([$codigo]);
        if ((int)$st->fetchColumn() > 0) {
            $msg = "O ativo $codigo já está no catálogo — pode boletá-lo direto."; $msgTipo = 'warning';
        } else {
            $venc = trim($_POST['vencimento'] ?? '');
            $solId = inserir_solic($pdo, $fid, $u['nome'], $codigo, $nome, $tipo,
                trim($_POST['emissor'] ?? ''), trim($_POST['indexador'] ?? ''), trim($_POST['taxa'] ?? ''),
                $venc !== '' ? $venc : null, trim($_POST['detalhe'] ?? ''), 0, $anexo);
            registrar_auditoria($pdo, 'ativo_solicitado', ['entidade' => 'solicitacoes_cadastro_ativo',
                'entidade_id' => $solId, 'fundo_id' => $fid, 'detalhe' => "Solicitação de cadastro de $codigo ($tipo)" . ($anexo ? ' com anexo' : '')]);
            $msg = "Solicitação enviada" . ($anexo ? ' com anexo' : '') . ". A administradora cadastra $codigo a partir dos dados oficiais."; $msgTipo = 'success';
        }
    }
}

// AÇÃO: cadastro em LOTE — vários PDFs, um por ativo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['lote'])) {
    $abaAtiva = 'aguard';
    $files = $_FILES['anexos'] ?? null; $n = 0; $ign = 0;
    if ($files && is_array($files['name'] ?? null)) {
        for ($i = 0; $i < count($files['name']); $i++) {
            $pdf = ler_pdf_upload(['name' => $files['name'][$i], 'tmp_name' => $files['tmp_name'][$i],
                                   'size' => $files['size'][$i], 'error' => $files['error'][$i]]);
            if (!$pdf) { $ign++; continue; }
            $base = preg_replace('/\.pdf$/i', '', $pdf['nome']);
            $codigo = strtoupper(substr(trim(preg_replace('/[^A-Za-z0-9 .\-]+/', ' ', $base)), 0, 40)) ?: 'ATIVO';
            $sid = inserir_solic($pdo, $fid, $u['nome'], $codigo, $base, 'A classificar', '', '', '', null, 'Cadastro em lote — a administradora classifica e completa.', 1, $pdf);
            registrar_auditoria($pdo, 'ativo_solicitado_lote', ['entidade' => 'solicitacoes_cadastro_ativo', 'entidade_id' => $sid, 'fundo_id' => $fid, 'detalhe' => "Lote: {$pdf['nome']}"]);
            $n++;
        }
    }
    $msg = $n ? "$n arquivo(s) enviado(s) para cadastro em lote." . ($ign ? " $ign ignorado(s) (aceito só .pdf até 4 MB)." : '')
              : 'Nenhum PDF válido enviado (aceito só .pdf até 4 MB).';
    $msgTipo = $n ? 'success' : 'danger';
}

// ---- Catálogo (filtro por categoria + busca) ----
$fTipo = $_GET['tipo'] ?? ''; $busca = trim($_GET['q'] ?? '');
$sql = 'SELECT * FROM ativos_catalogo WHERE 1=1'; $args = [];
if (in_array($fTipo, TIPOS_ATIVO, true)) { $sql .= ' AND tipo = ?'; $args[] = $fTipo; }
if ($busca !== '') { $sql .= ' AND (codigo LIKE ? OR nome LIKE ?)'; $args[] = "%$busca%"; $args[] = "%$busca%"; }
$sql .= ' ORDER BY tipo, codigo';
$st = $pdo->prepare($sql); $st->execute($args); $catalogo = $st->fetchAll();
$totalCatalogo = (int)$pdo->query('SELECT COUNT(*) FROM ativos_catalogo')->fetchColumn();

// ---- Minhas solicitações ----
$st = $pdo->prepare('SELECT id, fundo_id, solicitante, codigo, nome, tipo, emissor, indexador, taxa, vencimento, detalhe, status, motivo, criado_em, decidido_por, decidido_em, lote, anexo_nome, (anexo IS NOT NULL) AS tem_anexo FROM solicitacoes_cadastro_ativo WHERE solicitante = ? ORDER BY criado_em DESC');
$st->execute([$u['nome']]);
$minhas = $st->fetchAll();
$pendentes = array_values(array_filter($minhas, fn($s) => $s['status'] === 'Solicitado'));

page_start('Catálogo de ativos', 'Catálogo de ativos', $u,
    e_html($fundo['nome']) . ' · instrumentos disponíveis para boletar e solicitação de novos');

/** Linha de tabela de uma solicitação. */
function linha_solic(array $s): string {
    $anexo = $s['tem_anexo'] ? ' <a href="ativo_anexo.php?id=' . (int)$s['id'] . '" target="_blank" title="Ver anexo (PDF)"><i class="bi bi-paperclip"></i></a>' : '';
    $ret = $s['status'] === 'Aprovado' ? '<i class="bi bi-check-circle text-success me-1"></i>Cadastrado — já pode boletar.'
         : ($s['status'] === 'Rejeitado' ? '<i class="bi bi-x-circle text-danger me-1"></i>' . e_html($s['motivo'] ?: 'Sem motivo informado.')
         : 'Aguardando análise da administradora.');
    if ($s['decidido_por']) $ret .= '<br><span style="font-size:.75rem">— ' . e_html($s['decidido_por']) . ($s['decidido_em'] ? ', ' . date('d/m/Y H:i', strtotime($s['decidido_em'])) : '') . '</span>';
    return '<tr><td><b>' . e_html($s['codigo']) . '</b>' . $anexo . ($s['lote'] ? ' <span class="badge bg-info-subtle text-dark border" style="font-size:.62rem">lote</span>' : '') . '</td>'
        . '<td style="font-size:.83rem">' . e_html($s['nome']) . '</td>'
        . '<td>' . badge($s['tipo'], 'secondary') . '</td>'
        . '<td style="font-size:.82rem">' . data_br($s['criado_em']) . '</td>'
        . '<td>' . badge_status($s['status']) . '</td>'
        . '<td style="font-size:.82rem" class="text-muted">' . $ret . '</td></tr>';
}
?>

<?php if ($msg): ?><div class="alert alert-<?= e_html($msgTipo) ?> py-2"><i class="bi bi-info-circle me-1"></i><?= e_html($msg) ?></div><?php endif; ?>

<div class="row row-cols-1 row-cols-md-3 g-3 mb-3">
  <?= kpi('Ativos no catálogo', (string)$totalCatalogo, 'bi-collection') ?>
  <?= kpi('Minhas solicitações', (string)count($minhas), 'bi-inbox') ?>
  <?= kpi('Aguardando cadastro', '<span class="' . (count($pendentes) ? 'text-warning' : '') . '">' . count($pendentes) . '</span>', 'bi-hourglass-split') ?>
</div>

<ul class="nav nav-tabs mb-3" id="catTabs">
  <li class="nav-item"><button type="button" class="nav-link active" data-tab="catalogo"><i class="bi bi-collection me-1"></i>Ativos no catálogo</button></li>
  <li class="nav-item"><button type="button" class="nav-link" data-tab="solic"><i class="bi bi-inbox me-1"></i>Minhas solicitações <span class="badge bg-secondary"><?= count($minhas) ?></span></button></li>
  <li class="nav-item"><button type="button" class="nav-link" data-tab="aguard"><i class="bi bi-hourglass-split me-1"></i>Aguardando cadastro <span class="badge bg-warning text-dark"><?= count($pendentes) ?></span></button></li>
</ul>

<!-- ABA: ativos no catálogo -->
<div data-pane="catalogo">
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
      <span><i class="bi bi-collection me-1"></i> Instrumentos no catálogo</span>
      <form method="get" class="d-flex gap-2 align-items-center">
        <label class="text-muted" style="font-size:.78rem">Categoria:</label>
        <select class="form-select form-select-sm" name="tipo" onchange="this.form.submit()" style="max-width:170px">
          <option value="">Todas</option>
          <?php foreach (TIPOS_ATIVO as $t): ?>
            <option value="<?= e_html($t) ?>" <?= $t === $fTipo ? 'selected' : '' ?>><?= e_html($t) ?></option>
          <?php endforeach; ?>
        </select>
        <input class="form-control form-control-sm" name="q" value="<?= e_html($busca) ?>" placeholder="código ou nome…" style="max-width:190px">
        <button class="btn btn-sm btn-outline-secondary" title="Buscar"><i class="bi bi-search"></i></button>
      </form>
    </div>
    <div class="card-body p-0" style="max-height:560px;overflow-y:auto">
      <table class="table table-sm table-hover mb-0 align-middle">
        <thead><tr><th>Código</th><th>Nome</th><th>Categoria</th><th>Emissor</th><th>Indexador</th><th>Taxa</th><th class="text-end">Vencimento</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($catalogo as $a): ?>
          <tr>
            <td><b><?= e_html($a['codigo']) ?></b></td>
            <td style="font-size:.83rem"><?= e_html($a['nome']) ?></td>
            <td><?= badge($a['tipo'], 'secondary') ?></td>
            <td style="font-size:.82rem"><?= e_html($a['emissor'] ?: '—') ?></td>
            <td style="font-size:.82rem"><?= e_html($a['indexador'] ?: '—') ?></td>
            <td style="font-size:.82rem"><?= e_html($a['taxa'] ?: '—') ?></td>
            <td class="text-end" style="font-size:.82rem"><?= $a['vencimento'] ? data_br($a['vencimento']) : '—' ?></td>
            <td><?= badge_status($a['status']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$catalogo): ?><tr><td colspan="8" class="text-muted text-center py-4">Nenhum ativo com esse filtro. Solicite o cadastro na aba "Aguardando cadastro".</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ABA: minhas solicitações -->
<div data-pane="solic" style="display:none">
  <div class="card">
    <div class="card-header"><i class="bi bi-inbox me-1"></i> Minhas solicitações de cadastro</div>
    <div class="card-body p-0">
      <table class="table table-hover mb-0 align-middle">
        <thead><tr><th>Código</th><th>Nome</th><th>Categoria</th><th>Enviada em</th><th>Status</th><th>Retorno da administradora</th></tr></thead>
        <tbody>
        <?php foreach ($minhas as $s) echo linha_solic($s); ?>
        <?php if (!$minhas): ?><tr><td colspan="6" class="text-muted text-center py-4">Você ainda não solicitou nenhum cadastro.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ABA: aguardando cadastro (formulários + pendentes) -->
<div data-pane="aguard" style="display:none">
  <div class="row g-3">
    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header"><i class="bi bi-plus-circle me-1"></i> Solicitar um ativo</div>
        <div class="card-body">
          <form method="post" enctype="multipart/form-data" class="row g-2">
            <?= csrf_campo() ?><input type="hidden" name="solicitar" value="1">
            <div class="col-6"><label class="form-label mb-1" style="font-size:.78rem">Código *</label>
              <input class="form-control form-control-sm" name="codigo" required placeholder="DEB CEMIG32"></div>
            <div class="col-6"><label class="form-label mb-1" style="font-size:.78rem">Categoria *</label>
              <select class="form-select form-select-sm" name="tipo" required><option value="">Selecione…</option>
                <?php foreach (TIPOS_ATIVO as $t): ?><option value="<?= e_html($t) ?>"><?= e_html($t) ?></option><?php endforeach; ?></select></div>
            <div class="col-12"><label class="form-label mb-1" style="font-size:.78rem">Nome *</label>
              <input class="form-control form-control-sm" name="nome" required placeholder="Debênture Cemig 2032"></div>
            <div class="col-6"><label class="form-label mb-1" style="font-size:.78rem">Emissor</label>
              <input class="form-control form-control-sm" name="emissor" placeholder="Cemig"></div>
            <div class="col-6"><label class="form-label mb-1" style="font-size:.78rem">Vencimento</label>
              <input type="date" class="form-control form-control-sm" name="vencimento"></div>
            <div class="col-6"><label class="form-label mb-1" style="font-size:.78rem">Indexador</label>
              <input class="form-control form-control-sm" name="indexador" placeholder="IPCA / CDI"></div>
            <div class="col-6"><label class="form-label mb-1" style="font-size:.78rem">Taxa</label>
              <input class="form-control form-control-sm" name="taxa" placeholder="IPCA+6%"></div>
            <div class="col-12"><label class="form-label mb-1" style="font-size:.78rem">Anexo (PDF) — opcional</label>
              <input type="file" class="form-control form-control-sm" name="anexo" accept="application/pdf,.pdf">
              <span class="text-muted" style="font-size:.7rem">Lâmina/termo/prospecto do ativo. Até 4 MB.</span></div>
            <div class="col-12"><label class="form-label mb-1" style="font-size:.78rem">Detalhe / observação</label>
              <textarea class="form-control form-control-sm" name="detalhe" rows="2" placeholder="ISIN, série, motivo…"></textarea></div>
            <div class="col-12"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-send me-1"></i> Enviar solicitação</button></div>
          </form>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card">
        <div class="card-header"><i class="bi bi-files me-1"></i> Cadastro em lote (só os PDFs)</div>
        <div class="card-body">
          <p class="text-muted" style="font-size:.83rem">Envie <b>vários PDFs de uma vez</b> — um por ativo. O código e o nome vêm do nome do arquivo; a <b>administradora classifica e completa os dados</b> no cadastro. Ideal quando você tem só as lâminas/termos.</p>
          <form method="post" enctype="multipart/form-data">
            <?= csrf_campo() ?><input type="hidden" name="lote" value="1">
            <input type="file" class="form-control form-control-sm mb-2" name="anexos[]" accept="application/pdf,.pdf" multiple required>
            <button class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-upload me-1"></i> Enviar lote de PDFs</button>
          </form>
        </div>
      </div>
      <div class="card mt-3">
        <div class="card-header"><i class="bi bi-hourglass-split me-1"></i> Aguardando cadastro (<?= count($pendentes) ?>)</div>
        <div class="card-body p-0">
          <table class="table table-sm table-hover mb-0 align-middle">
            <thead><tr><th>Código</th><th>Nome</th><th>Categoria</th><th>Enviada</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($pendentes as $s) echo linha_solic($s); ?>
            <?php if (!$pendentes): ?><tr><td colspan="6" class="text-muted text-center py-3">Nada aguardando cadastro.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  var tabs = [].slice.call(document.querySelectorAll('#catTabs [data-tab]'));
  function show(t){
    tabs.forEach(function(b){ b.classList.toggle('active', b.dataset.tab===t); });
    document.querySelectorAll('[data-pane]').forEach(function(p){ p.style.display=(p.dataset.pane===t)?'':'none'; });
  }
  tabs.forEach(function(b){ b.addEventListener('click', function(){ show(b.dataset.tab); }); });
  show(<?= json_encode($abaAtiva) ?>);
})();
</script>
<?php page_end();
