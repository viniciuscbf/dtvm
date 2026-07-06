<?php
// Catálogo de instrumentos & solicitação de cadastro de ativo (perfil gestor).
// O gestor navega/pesquisa a base mantida pela administradora e, quando um
// instrumento não existe, solicita a inclusão — que a administradora cadastra
// a partir de dados B3/Cetip/ANBIMA. Só se boleta o que está no catálogo.
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

$msg = ''; $msgTipo = 'success';

// Tipos aceitos no catálogo (mesma lista do enumerador do domínio).
const TIPOS_ATIVO = ['Título Público', 'Debênture', 'CDB', 'CRI/CRA', 'Ação', 'ETF', 'Cota de Fundo'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_validar()) {
    $_POST = [];
    $msg = 'Requisição inválida (proteção CSRF). Recarregue a página e tente novamente.'; $msgTipo = 'danger';
}

// AÇÃO: solicitar cadastro de novo ativo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['solicitar'])) {
    $codigo   = strtoupper(trim($_POST['codigo'] ?? ''));
    $nome     = trim($_POST['nome'] ?? '');
    $tipo     = $_POST['tipo'] ?? '';
    $emissor  = trim($_POST['emissor'] ?? '');
    $index    = trim($_POST['indexador'] ?? '');
    $taxa     = trim($_POST['taxa'] ?? '');
    $venc     = trim($_POST['vencimento'] ?? '');
    $detalhe  = trim($_POST['detalhe'] ?? '');

    if ($codigo === '' || $nome === '' || !in_array($tipo, TIPOS_ATIVO, true)) {
        $msg = 'Informe ao menos código, nome e um tipo válido.'; $msgTipo = 'danger';
    } else {
        // já existe no catálogo?
        $st = $pdo->prepare('SELECT COUNT(*) FROM ativos_catalogo WHERE codigo = ?');
        $st->execute([$codigo]);
        if ((int)$st->fetchColumn() > 0) {
            $msg = "O ativo $codigo já está no catálogo — pode boletá-lo direto, sem solicitar cadastro."; $msgTipo = 'warning';
        } else {
            $st = $pdo->prepare(
                "INSERT INTO solicitacoes_cadastro_ativo
                   (fundo_id, solicitante, codigo, nome, tipo, emissor, indexador, taxa, vencimento, detalhe, status)
                 VALUES (?,?,?,?,?,?,?,?,?,?, 'Solicitado')"
            );
            $st->execute([$fid, $u['nome'], $codigo, $nome, $tipo, $emissor, $index, $taxa,
                          $venc !== '' ? $venc : null, $detalhe]);
            $solId = (int)$pdo->lastInsertId();
            registrar_auditoria($pdo, 'ativo_solicitado', [
                'entidade' => 'solicitacoes_cadastro_ativo', 'entidade_id' => $solId, 'fundo_id' => $fid,
                'detalhe' => "Solicitação de cadastro do ativo $codigo ($tipo) — $nome",
            ]);
            $msg = "Solicitação enviada. A administradora vai cadastrar $codigo a partir dos dados oficiais (B3/Cetip/ANBIMA)."; $msgTipo = 'success';
        }
    }
}

// ---- Catálogo: filtros ----
$fTipo = $_GET['tipo'] ?? '';
$busca = trim($_GET['q'] ?? '');
$sql = 'SELECT * FROM ativos_catalogo WHERE 1=1';
$args = [];
if (in_array($fTipo, TIPOS_ATIVO, true)) { $sql .= ' AND tipo = ?'; $args[] = $fTipo; }
if ($busca !== '') { $sql .= ' AND (codigo LIKE ? OR nome LIKE ?)'; $args[] = "%$busca%"; $args[] = "%$busca%"; }
$sql .= ' ORDER BY tipo, codigo';
$st = $pdo->prepare($sql);
$st->execute($args);
$catalogo = $st->fetchAll();
$totalCatalogo = (int)$pdo->query('SELECT COUNT(*) FROM ativos_catalogo')->fetchColumn();

// ---- Minhas solicitações (deste gestor) ----
$st = $pdo->prepare('SELECT * FROM solicitacoes_cadastro_ativo WHERE solicitante = ? ORDER BY criado_em DESC');
$st->execute([$u['nome']]);
$minhas = $st->fetchAll();
$pendentes = array_filter($minhas, fn($s) => $s['status'] === 'Solicitado');

page_start('Catálogo de ativos', 'Catálogo de ativos', $u,
    e_html($fundo['nome']) . ' · instrumentos disponíveis para boletar e solicitação de novos');
?>

<?php if ($msg): ?>
  <div class="alert alert-<?= e_html($msgTipo) ?> py-2"><i class="bi bi-info-circle me-1"></i><?= e_html($msg) ?></div>
<?php endif; ?>

<div class="alert alert-info py-2" style="font-size:.86rem">
  <i class="bi bi-lightbulb me-1"></i>
  Para boletar um ativo, ele precisa estar no <b>catálogo</b> abaixo. Se o instrumento que você quer operar
  ainda não aparece na base, <b>solicite o cadastro</b> no formulário ao lado — a administradora o cadastra a
  partir dos dados oficiais (B3/Cetip/ANBIMA).
</div>

<div class="row row-cols-1 row-cols-md-3 g-3 mb-4">
  <?= kpi('Ativos no catálogo', (string)$totalCatalogo, 'bi-collection') ?>
  <?= kpi('Minhas solicitações', (string)count($minhas), 'bi-inbox') ?>
  <?= kpi('Aguardando cadastro', '<span class="' . (count($pendentes) ? 'text-warning' : '') . '">' . count($pendentes) . '</span>', 'bi-hourglass-split') ?>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="bi bi-search me-1"></i> Catálogo de instrumentos</span>
        <form method="get" class="d-flex gap-2">
          <select class="form-select form-select-sm" name="tipo" onchange="this.form.submit()">
            <option value="">Todos os tipos</option>
            <?php foreach (TIPOS_ATIVO as $t): ?>
              <option value="<?= e_html($t) ?>" <?= $t === $fTipo ? 'selected' : '' ?>><?= e_html($t) ?></option>
            <?php endforeach; ?>
          </select>
          <input class="form-control form-control-sm" name="q" value="<?= e_html($busca) ?>" placeholder="código ou nome…" style="max-width:200px">
          <button class="btn btn-sm btn-outline-secondary" title="Buscar"><i class="bi bi-search"></i></button>
        </form>
      </div>
      <div class="card-body p-0" style="max-height:520px;overflow-y:auto">
        <table class="table table-sm table-hover mb-0 align-middle">
          <thead><tr><th>Código</th><th>Nome</th><th>Tipo</th><th>Emissor</th><th>Indexador</th><th>Taxa</th><th class="text-end">Vencimento</th><th>Status</th></tr></thead>
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
          <?php if (!$catalogo): ?>
            <tr><td colspan="8" class="text-muted text-center py-4">Nenhum ativo encontrado com esse filtro. Solicite o cadastro ao lado.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-plus-circle me-1"></i> Solicitar cadastro de novo ativo</div>
      <div class="card-body">
        <form method="post" class="row g-2">
          <?= csrf_campo() ?>
          <input type="hidden" name="solicitar" value="1">
          <div class="col-12">
            <label class="form-label mb-1" style="font-size:.8rem">Código *</label>
            <input class="form-control form-control-sm" name="codigo" required placeholder="ex.: DEB CEMIG32">
          </div>
          <div class="col-12">
            <label class="form-label mb-1" style="font-size:.8rem">Nome *</label>
            <input class="form-control form-control-sm" name="nome" required placeholder="ex.: Debênture Cemig 2032">
          </div>
          <div class="col-12">
            <label class="form-label mb-1" style="font-size:.8rem">Tipo *</label>
            <select class="form-select form-select-sm" name="tipo" required>
              <option value="">Selecione…</option>
              <?php foreach (TIPOS_ATIVO as $t): ?>
                <option value="<?= e_html($t) ?>"><?= e_html($t) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label mb-1" style="font-size:.8rem">Emissor</label>
            <input class="form-control form-control-sm" name="emissor" placeholder="ex.: Cemig">
          </div>
          <div class="col-6">
            <label class="form-label mb-1" style="font-size:.8rem">Indexador</label>
            <input class="form-control form-control-sm" name="indexador" placeholder="IPCA / CDI…">
          </div>
          <div class="col-6">
            <label class="form-label mb-1" style="font-size:.8rem">Taxa</label>
            <input class="form-control form-control-sm" name="taxa" placeholder="IPCA+6% …">
          </div>
          <div class="col-12">
            <label class="form-label mb-1" style="font-size:.8rem">Vencimento</label>
            <input type="date" class="form-control form-control-sm" name="vencimento">
          </div>
          <div class="col-12">
            <label class="form-label mb-1" style="font-size:.8rem">Detalhe / observação</label>
            <textarea class="form-control form-control-sm" name="detalhe" rows="2" placeholder="ISIN, série, motivo do pedido…"></textarea>
          </div>
          <div class="col-12">
            <button class="btn btn-sm btn-primary w-100"><i class="bi bi-send me-1"></i> Enviar solicitação</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="card mt-4">
  <div class="card-header"><i class="bi bi-inbox me-1"></i> Minhas solicitações de cadastro</div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0 align-middle">
      <thead><tr><th>Código</th><th>Nome</th><th>Tipo</th><th>Enviada em</th><th>Status</th><th>Retorno da administradora</th></tr></thead>
      <tbody>
      <?php foreach ($minhas as $s): ?>
        <tr>
          <td><b><?= e_html($s['codigo']) ?></b></td>
          <td style="font-size:.83rem"><?= e_html($s['nome']) ?></td>
          <td><?= badge($s['tipo'], 'secondary') ?></td>
          <td style="font-size:.82rem"><?= data_br($s['criado_em']) ?></td>
          <td><?= badge_status($s['status']) ?></td>
          <td style="font-size:.82rem" class="text-muted">
            <?php if ($s['status'] === 'Aprovado'): ?>
              <i class="bi bi-check-circle text-success me-1"></i>Cadastrado no catálogo — já pode boletar.
            <?php elseif ($s['status'] === 'Rejeitado'): ?>
              <i class="bi bi-x-circle text-danger me-1"></i><?= e_html($s['motivo'] ?: 'Sem motivo informado.') ?>
            <?php else: ?>
              Aguardando análise da administradora.
            <?php endif; ?>
            <?php if ($s['decidido_por']): ?><br><span style="font-size:.75rem">— <?= e_html($s['decidido_por']) ?><?= $s['decidido_em'] ? ', ' . date('d/m/Y H:i', strtotime($s['decidido_em'])) : '' ?></span><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$minhas): ?>
        <tr><td colspan="6" class="text-muted text-center py-4">Você ainda não solicitou nenhum cadastro.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php page_end();
