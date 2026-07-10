<?php
// Base de instrumentos (catálogo) & fila de solicitações de cadastro (perfil admin).
// A administradora mantém a base: aprova/rejeita os pedidos dos gestores (cadastrando
// o instrumento a partir de dados B3/Cetip/ANBIMA) e pode cadastrar ativos direto.
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

$u = exigir_perfil('admin');
ensure_catalogo($pdo);
ensure_fund_types($pdo);   // garante ativos_catalogo.fundo_alvo_id (vínculo FIC → cota do master)

$msg = ''; $msgTipo = 'success';

// Tipos aceitos no catálogo (mesma lista do enumerador do domínio).
const TIPOS_ATIVO = ['Título Público', 'Debênture', 'CDB', 'CRI/CRA', 'Ação', 'ETF', 'Cota de Fundo'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_validar()) {
    $_POST = [];
    $msg = 'Requisição inválida (proteção CSRF). Recarregue a página e tente novamente.'; $msgTipo = 'danger';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // APROVAR solicitação: cadastra no catálogo (sem duplicar código) e marca como Aprovada
    if (!empty($_POST['aprovar'])) {
        $st = $pdo->prepare("SELECT id, fundo_id, codigo, nome, tipo, emissor, indexador, taxa, vencimento FROM solicitacoes_cadastro_ativo WHERE id = ? AND status = 'Solicitado'");
        $st->execute([(int)$_POST['aprovar']]);
        if ($s = $st->fetch()) {
            // A administradora pode completar/corrigir na aprovação (essencial p/ pedidos em lote sem categoria).
            $codigo  = strtoupper(trim($_POST['codigo'] ?? '')) ?: $s['codigo'];
            $nome    = trim($_POST['nome'] ?? '') ?: $s['nome'];
            $tipo    = in_array($_POST['tipo'] ?? '', TIPOS_ATIVO, true) ? $_POST['tipo'] : $s['tipo'];
            $emissor = trim($_POST['emissor'] ?? '') ?: $s['emissor'];
            $index   = trim($_POST['indexador'] ?? '') ?: $s['indexador'];
            $taxa    = trim($_POST['taxa'] ?? '') ?: $s['taxa'];
            $venc    = trim($_POST['vencimento'] ?? '') ?: ($s['vencimento'] ?: '');
            if (!in_array($tipo, TIPOS_ATIVO, true)) {
                $msg = "Classifique a categoria de {$s['codigo']} antes de aprovar (pedido em lote sem categoria)."; $msgTipo = 'danger';
            } else {
                com_transacao($pdo, function () use ($pdo, $s, $u, $codigo, $nome, $tipo, $emissor, $index, $taxa, $venc) {
                    $ins = $pdo->prepare(
                        "INSERT INTO ativos_catalogo (codigo, nome, tipo, emissor, indexador, taxa, vencimento, status)
                         VALUES (?,?,?,?,?,?,?, 'Ativo')
                         ON DUPLICATE KEY UPDATE nome=VALUES(nome), emissor=VALUES(emissor),
                            indexador=VALUES(indexador), taxa=VALUES(taxa), vencimento=VALUES(vencimento)"
                    );
                    $ins->execute([$codigo, $nome, $tipo, $emissor, $index, $taxa, $venc !== '' ? $venc : null]);
                    $pdo->prepare("UPDATE solicitacoes_cadastro_ativo
                                     SET status='Aprovado', motivo=NULL, codigo=?, nome=?, tipo=?, decidido_por=?, decidido_em=NOW() WHERE id=?")
                        ->execute([$codigo, $nome, $tipo, $u['nome'], $s['id']]);
                });
                registrar_auditoria($pdo, 'ativo_aprovado', [
                    'entidade' => 'ativos_catalogo', 'entidade_id' => $codigo, 'fundo_id' => $s['fundo_id'] ? (int)$s['fundo_id'] : null,
                    'detalhe' => "Solicitação #{$s['id']} aprovada — $codigo cadastrado no catálogo",
                ]);
                $msg = "Ativo $codigo cadastrado no catálogo. O gestor já pode boletá-lo.";
            }
        } else { $msg = 'Solicitação não encontrada ou já decidida.'; $msgTipo = 'warning'; }
    }
    // REJEITAR solicitação: exige motivo
    elseif (!empty($_POST['rejeitar'])) {
        $motivo = trim($_POST['motivo'] ?? '');
        if ($motivo === '') { $msg = 'Para rejeitar, informe o motivo.'; $msgTipo = 'danger'; }
        else {
            $st = $pdo->prepare("UPDATE solicitacoes_cadastro_ativo
                                   SET status='Rejeitado', motivo=?, decidido_por=?, decidido_em=NOW()
                                 WHERE id=? AND status='Solicitado'");
            $st->execute([$motivo, $u['nome'], (int)$_POST['rejeitar']]);
            if ($st->rowCount() > 0) {
                registrar_auditoria($pdo, 'ativo_rejeitado', [
                    'entidade' => 'solicitacoes_cadastro_ativo', 'entidade_id' => (int)$_POST['rejeitar'],
                    'detalhe' => "Solicitação #{$_POST['rejeitar']} rejeitada: $motivo",
                ]);
                $msg = 'Solicitação rejeitada — o gestor foi notificado do motivo.'; $msgTipo = 'warning';
            } else { $msg = 'Solicitação não encontrada ou já decidida.'; $msgTipo = 'warning'; }
        }
    }
    // CADASTRO MANUAL direto no catálogo
    elseif (!empty($_POST['cadastrar'])) {
        $codigo   = strtoupper(trim($_POST['codigo'] ?? ''));
        $nome     = trim($_POST['nome'] ?? '');
        $tipo     = $_POST['tipo'] ?? '';
        $emissor  = trim($_POST['emissor'] ?? '');
        $cnpj     = trim($_POST['cnpj_emissor'] ?? '');
        $index    = trim($_POST['indexador'] ?? '');
        $taxa     = trim($_POST['taxa'] ?? '');
        $venc     = trim($_POST['vencimento'] ?? '');
        $fonte    = trim($_POST['fonte_preco'] ?? 'ANBIMA');
        // Fundo-alvo só faz sentido para "Cota de Fundo" (vínculo FIC → marca pela cota do master).
        $alvo     = ($tipo === 'Cota de Fundo' && !empty($_POST['fundo_alvo_id'])) ? (int)$_POST['fundo_alvo_id'] : null;

        if ($codigo === '' || $nome === '' || !in_array($tipo, TIPOS_ATIVO, true)) {
            $msg = 'Informe ao menos código, nome e um tipo válido.'; $msgTipo = 'danger';
        } elseif ($tipo === 'Cota de Fundo' && !$alvo) {
            $msg = 'Para "Cota de Fundo", selecione o fundo-alvo (master) que ela replica.'; $msgTipo = 'danger';
        } else {
            $st = $pdo->prepare('SELECT COUNT(*) FROM ativos_catalogo WHERE codigo = ?');
            $st->execute([$codigo]);
            if ((int)$st->fetchColumn() > 0) {
                $msg = "Já existe um ativo com o código $codigo no catálogo."; $msgTipo = 'warning';
            } else {
                $ins = $pdo->prepare(
                    "INSERT INTO ativos_catalogo (codigo, nome, tipo, emissor, cnpj_emissor, indexador, taxa, vencimento, fonte_preco, fundo_alvo_id, status)
                     VALUES (?,?,?,?,?,?,?,?,?,?, 'Ativo')"
                );
                $ins->execute([$codigo, $nome, $tipo, $emissor, $cnpj ?: null, $index, $taxa,
                               $venc !== '' ? $venc : null, $fonte ?: 'ANBIMA', $alvo]);
                registrar_auditoria($pdo, 'ativo_cadastrado', [
                    'entidade' => 'ativos_catalogo', 'entidade_id' => $codigo,
                    'detalhe' => "Ativo $codigo ($tipo) cadastrado manualmente no catálogo",
                ]);
                $msg = "Ativo $codigo cadastrado no catálogo.";
            }
        }
    }
}

// ---- Fila de solicitações pendentes ----
$fila = $pdo->query("SELECT s.id, s.fundo_id, s.solicitante, s.codigo, s.nome, s.tipo, s.emissor, s.indexador, s.taxa, s.vencimento, s.detalhe, s.lote, s.anexo_nome, (s.anexo IS NOT NULL) AS tem_anexo, f.nome fundo_nome
                     FROM solicitacoes_cadastro_ativo s LEFT JOIN fundos f ON f.id = s.fundo_id
                     WHERE s.status='Solicitado' ORDER BY s.criado_em")->fetchAll();

// ---- Catálogo (com filtro por tipo) ----
$fTipo = $_GET['tipo'] ?? '';
$sql = 'SELECT * FROM ativos_catalogo';
$args = [];
if (in_array($fTipo, TIPOS_ATIVO, true)) { $sql .= ' WHERE tipo = ?'; $args[] = $fTipo; }
$sql .= ' ORDER BY tipo, codigo';
$st = $pdo->prepare($sql);
$st->execute($args);
$catalogo = $st->fetchAll();
$totalCatalogo = (int)$pdo->query('SELECT COUNT(*) FROM ativos_catalogo')->fetchColumn();
// Fundos elegíveis como alvo (master) de uma "Cota de Fundo" (vínculo FIC).
$fundosAlvo = $pdo->query("SELECT id, nome FROM fundos WHERE status IN ('Ativo','Em abertura') ORDER BY nome")->fetchAll();

page_start('Base de instrumentos', 'Base de instrumentos', $u,
    'Catálogo mantido pela administradora e fila de solicitações de cadastro dos gestores');
?>

<?php if ($msg): ?>
  <div class="alert alert-<?= e_html($msgTipo) ?> py-2"><i class="bi bi-info-circle me-1"></i><?= e_html($msg) ?></div>
<?php endif; ?>

<div class="row row-cols-1 row-cols-md-2 g-3 mb-4">
  <?= kpi('Ativos no catálogo', (string)$totalCatalogo, 'bi-collection') ?>
  <?= kpi('Solicitações pendentes', '<span class="' . (count($fila) ? 'text-warning' : '') . '">' . count($fila) . '</span>', 'bi-hourglass-split') ?>
</div>

<div class="card mb-4 <?= $fila ? 'border-warning' : '' ?>">
  <div class="card-header <?= $fila ? 'text-warning' : '' ?>">
    <i class="bi bi-inbox me-1"></i> Fila de solicitações de cadastro — pedidos dos gestores
  </div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0 align-middle">
      <thead><tr><th>Código / Nome</th><th>Tipo</th><th>Fundo / Solicitante</th><th>Indexador · Taxa · Venc.</th><th>Detalhe</th><th style="min-width:280px"></th></tr></thead>
      <tbody>
      <?php foreach ($fila as $s): ?>
        <tr>
          <td><b><?= e_html($s['codigo']) ?></b><?php if ($s['lote']): ?> <span class="badge bg-info-subtle text-dark border" style="font-size:.6rem">lote</span><?php endif; ?>
            <br><span class="text-muted" style="font-size:.78rem"><?= e_html($s['nome']) ?></span>
            <?php if ($s['tem_anexo']): ?><br><a href="../gestor/ativo_anexo.php?id=<?= (int)$s['id'] ?>" target="_blank" style="font-size:.75rem"><i class="bi bi-paperclip"></i> <?= e_html($s['anexo_nome'] ?: 'anexo.pdf') ?></a><?php endif; ?></td>
          <td><?= $s['tipo'] === 'A classificar' ? '<span class="badge bg-warning text-dark">a classificar</span>' : badge($s['tipo'], 'secondary') ?></td>
          <td style="font-size:.8rem"><?= e_html($s['fundo_nome'] ?: '—') ?><br><span class="text-muted"><?= e_html($s['solicitante']) ?></span></td>
          <td style="font-size:.8rem"><?= e_html($s['indexador'] ?: '—') ?> · <?= e_html($s['taxa'] ?: '—') ?> · <?= $s['vencimento'] ? data_br($s['vencimento']) : '—' ?></td>
          <td class="text-muted" style="font-size:.8rem"><?= e_html($s['detalhe'] ?: '—') ?></td>
          <td>
            <form method="post" class="mb-1">
              <?= csrf_campo() ?><input type="hidden" name="aprovar" value="<?= (int)$s['id'] ?>">
              <div class="row g-1 mb-1">
                <div class="col-5"><input class="form-control form-control-sm" name="codigo" value="<?= e_html($s['codigo']) ?>" title="código"></div>
                <div class="col-7"><select class="form-select form-select-sm" name="tipo" title="categoria"<?= $s['tipo'] === 'A classificar' ? ' required' : '' ?>>
                  <option value="">categoria…</option>
                  <?php foreach (TIPOS_ATIVO as $t): ?><option value="<?= e_html($t) ?>" <?= $t === $s['tipo'] ? 'selected' : '' ?>><?= e_html($t) ?></option><?php endforeach; ?>
                </select></div>
                <div class="col-12"><input class="form-control form-control-sm" name="nome" value="<?= e_html($s['nome']) ?>" title="nome"></div>
              </div>
              <button class="btn btn-sm btn-success w-100"><i class="bi bi-check-lg me-1"></i>Aprovar e cadastrar</button>
            </form>
            <form method="post" class="d-flex gap-1">
              <?= csrf_campo() ?><input type="hidden" name="rejeitar" value="<?= (int)$s['id'] ?>">
              <input class="form-control form-control-sm" name="motivo" placeholder="Motivo da rejeição…" required>
              <button class="btn btn-sm btn-outline-danger" title="Rejeitar"><i class="bi bi-x-lg"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$fila): ?>
        <tr><td colspan="6" class="text-muted text-center py-4">Nenhuma solicitação pendente.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="bi bi-collection me-1"></i> Catálogo de instrumentos</span>
        <form method="get">
          <select class="form-select form-select-sm" name="tipo" onchange="this.form.submit()">
            <option value="">Todos os tipos</option>
            <?php foreach (TIPOS_ATIVO as $t): ?>
              <option value="<?= e_html($t) ?>" <?= $t === $fTipo ? 'selected' : '' ?>><?= e_html($t) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
      <div class="card-body p-0" style="max-height:560px;overflow-y:auto">
        <table class="table table-sm table-hover mb-0 align-middle">
          <thead><tr><th>Código</th><th>Nome</th><th>Tipo</th><th>Emissor</th><th>Indexador</th><th>Taxa</th><th class="text-end">Vencimento</th><th>Fonte</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($catalogo as $a): ?>
            <tr>
              <td><b><?= e_html($a['codigo']) ?></b></td>
              <td style="font-size:.82rem"><?= e_html($a['nome']) ?></td>
              <td><?= badge($a['tipo'], 'secondary') ?></td>
              <td style="font-size:.8rem"><?= e_html($a['emissor'] ?: '—') ?></td>
              <td style="font-size:.8rem"><?= e_html($a['indexador'] ?: '—') ?></td>
              <td style="font-size:.8rem"><?= e_html($a['taxa'] ?: '—') ?></td>
              <td class="text-end" style="font-size:.8rem"><?= $a['vencimento'] ? data_br($a['vencimento']) : '—' ?></td>
              <td><?= badge($a['fonte_preco'] ?: 'ANBIMA', $a['fonte_preco'] === 'Comitê' ? 'warning' : 'info') ?></td>
              <td><?= badge_status($a['status']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$catalogo): ?>
            <tr><td colspan="9" class="text-muted text-center py-4">Nenhum ativo nesse filtro.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-plus-circle me-1"></i> Cadastrar ativo direto no catálogo</div>
      <div class="card-body">
        <form method="post" class="row g-2">
          <?= csrf_campo() ?>
          <input type="hidden" name="cadastrar" value="1">
          <div class="col-12">
            <label class="form-label mb-1" style="font-size:.8rem">Código *</label>
            <input class="form-control form-control-sm" name="codigo" required placeholder="ex.: NTN-B 2050">
          </div>
          <div class="col-12">
            <label class="form-label mb-1" style="font-size:.8rem">Nome *</label>
            <input class="form-control form-control-sm" name="nome" required placeholder="ex.: Tesouro IPCA+ 2050">
          </div>
          <div class="col-12">
            <label class="form-label mb-1" style="font-size:.8rem">Tipo *</label>
            <select class="form-select form-select-sm" name="tipo" id="tipoSel" required
                    onchange="document.getElementById('alvoWrap').style.display = this.value==='Cota de Fundo' ? 'block' : 'none';">
              <option value="">Selecione…</option>
              <?php foreach (TIPOS_ATIVO as $t): ?>
                <option value="<?= e_html($t) ?>"><?= e_html($t) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12" id="alvoWrap" style="display:none">
            <label class="form-label mb-1" style="font-size:.8rem">Fundo-alvo (master) *
              <i class="bi bi-info-circle" title="A cota é marcada diariamente pela cota publicada deste fundo (vínculo FIC)."></i></label>
            <select class="form-select form-select-sm" name="fundo_alvo_id">
              <option value="">Selecione o fundo replicado…</option>
              <?php foreach ($fundosAlvo as $fa): ?>
                <option value="<?= (int)$fa['id'] ?>"><?= e_html($fa['nome']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label mb-1" style="font-size:.8rem">Emissor</label>
            <input class="form-control form-control-sm" name="emissor" placeholder="ex.: Tesouro Nacional">
          </div>
          <div class="col-12">
            <label class="form-label mb-1" style="font-size:.8rem">CNPJ do emissor</label>
            <input class="form-control form-control-sm" name="cnpj_emissor" placeholder="00.000.000/0001-00">
          </div>
          <div class="col-6">
            <label class="form-label mb-1" style="font-size:.8rem">Indexador</label>
            <input class="form-control form-control-sm" name="indexador" placeholder="IPCA / CDI…">
          </div>
          <div class="col-6">
            <label class="form-label mb-1" style="font-size:.8rem">Taxa</label>
            <input class="form-control form-control-sm" name="taxa" placeholder="IPCA+6% …">
          </div>
          <div class="col-6">
            <label class="form-label mb-1" style="font-size:.8rem">Vencimento</label>
            <input type="date" class="form-control form-control-sm" name="vencimento">
          </div>
          <div class="col-6">
            <label class="form-label mb-1" style="font-size:.8rem">Fonte de preço</label>
            <select class="form-select form-select-sm" name="fonte_preco">
              <option value="ANBIMA">ANBIMA</option>
              <option value="B3">B3</option>
              <option value="Comitê">Comitê</option>
            </select>
          </div>
          <div class="col-12">
            <button class="btn btn-sm btn-primary w-100"><i class="bi bi-plus-lg me-1"></i> Cadastrar no catálogo</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php page_end();
