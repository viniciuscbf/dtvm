<?php
// Classes de cotas (Res. CVM 175) — cada CLASSE segrega ATIVOS (política e patrimônio
// próprios). Aqui a classe é criada com REGULAMENTO padronizado (mesmo motor do fundo).
// Camada de registro: a segregação contábil-patrimonial plena por classe não está no piloto.
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

$u = exigir_perfil('admin');
ensure_regulamento($pdo);
$tipos = reg_tipos();

$fundos = $pdo->query("SELECT * FROM fundos WHERE status IN ('Ativo','Em abertura') ORDER BY pl_atual DESC")->fetchAll();
if (isset($_GET['fundo_id'])) $_SESSION['admin_fundo_id'] = (int)$_GET['fundo_id'];
$fid = (int)($_SESSION['admin_fundo_id'] ?? ($fundos[0]['id'] ?? 0));
$fundo = null; foreach ($fundos as $f) if ((int)$f['id'] === $fid) $fundo = $f;
if (!$fundo && $fundos) { $fundo = $fundos[0]; $fid = (int)$fundo['id']; }

$tipo  = $_POST['reg_tipo'] ?? $_GET['tipo'] ?? '';
if ($tipo && !isset($tipos[$tipo])) $tipo = '';
$dados = $tipo ? reg_coletar($tipo) : [];
$erros = []; $previa = null; $msg = ''; $msgTipo = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_validar()) {
    $erros[] = 'Requisição inválida (CSRF).';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $fundo) {
    $acao = $_POST['acao'] ?? '';
    if ($tipo && $acao === 'preview') {
        $erros = reg_validar($tipo, $dados); $previa = reg_gerar_html($tipo, $dados);
    } elseif ($tipo && $acao === 'criar') {
        $erros = reg_validar($tipo, $dados);
        if (!$erros) {
            $t = $tipos[$tipo]; $html = reg_gerar_html($tipo, $dados);
            $st = $pdo->prepare("INSERT INTO classes (fundo_id, nome, reg_tipo, classe_cvm, status, reg_dados, reg_html)
                                 VALUES (?,?,?,?, 'Em registro', ?, ?)");
            $st->execute([$fid, $dados['nome'], $tipo, $t['classe'], json_encode($dados, JSON_UNESCAPED_UNICODE), $html]);
            registrar_auditoria($pdo, 'classe_criada', ['entidade' => 'classe', 'entidade_id' => (int)$pdo->lastInsertId(),
                'fundo_id' => $fid, 'detalhe' => "Classe \"{$dados['nome']}\" ($tipo) criada no fundo #$fid"]);
            $msg = 'Classe "' . $dados['nome'] . '" criada com regulamento. Envie ao registro na CVM.';
            $tipo = ''; $dados = [];
        } else { $previa = reg_gerar_html($tipo, $dados); }
    }
}

$classes = $pdo->prepare("SELECT * FROM classes WHERE fundo_id=? ORDER BY id DESC");
$classes->execute([$fid]); $classes = $classes->fetchAll();

page_start('Classes de cotas', 'Classes de cotas', $u,
    'Cada classe tem política e patrimônio próprios (Res. CVM 175). Regulamento padronizado pelo mesmo motor do fundo.');
?>

<?php if ($msg): ?><div class="alert alert-<?= $msgTipo ?> py-2"><i class="bi bi-info-circle me-1"></i><?= e_html($msg) ?></div><?php endif; ?>
<?php if ($erros): ?><div class="alert alert-danger py-2"><b>Ajuste:</b><ul class="mb-0 mt-1" style="font-size:.85rem"><?php foreach ($erros as $e): ?><li><?= e_html($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <form method="get"><select name="fundo_id" class="form-select form-select-sm" onchange="this.form.submit()">
    <?php foreach ($fundos as $f): ?><option value="<?= (int)$f['id'] ?>" <?= (int)$f['id'] === $fid ? 'selected' : '' ?>><?= e_html($f['nome']) ?></option><?php endforeach; ?>
  </select></form>
  <?php if ($fundo): ?><div class="text-muted" style="font-size:.82rem">Classe principal do fundo: <?= badge($fundo['classe'] ?? '—', 'info') ?>
    <?php if (!empty($fundo['reg_html'])): ?> · <a href="regulamento_ver.php?origem=fundo&id=<?= $fid ?>" target="_blank">ver regulamento</a><?php endif; ?></div><?php endif; ?>
</div>

<div class="card mb-4">
  <div class="card-header"><i class="bi bi-collection me-1"></i> Classes deste fundo</div>
  <div class="card-body p-0">
    <table class="table table-hover align-middle mb-0" style="font-size:.85rem">
      <thead><tr><th>Classe</th><th>Tipo</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <tr><td><b><?= e_html($fundo['nome'] ?? '—') ?></b> <span class="badge bg-warning-subtle text-warning-emphasis">principal</span></td>
          <td><?= e_html($fundo['classe'] ?? '—') ?></td><td><?= badge_status($fundo['status'] ?? '—') ?></td>
          <td class="text-end"><?php if (!empty($fundo['reg_html'])): ?><a class="btn btn-sm btn-outline-secondary py-0" target="_blank" href="regulamento_ver.php?origem=fundo&id=<?= $fid ?>"><i class="bi bi-file-earmark-text"></i></a><?php endif; ?></td></tr>
        <?php foreach ($classes as $c): ?>
          <tr><td><b><?= e_html($c['nome']) ?></b></td><td><?= e_html($c['classe_cvm']) ?></td><td><?= badge_status($c['status']) ?></td>
            <td class="text-end"><a class="btn btn-sm btn-outline-secondary py-0" target="_blank" href="regulamento_ver.php?origem=classe&id=<?= (int)$c['id'] ?>"><i class="bi bi-file-earmark-text"></i></a></td></tr>
        <?php endforeach; ?>
        <?php if (!$classes): ?><tr><td colspan="4" class="text-muted text-center py-3" style="font-size:.82rem">Nenhuma classe adicional — o fundo tem apenas a classe principal.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <div class="card-header"><i class="bi bi-plus-circle me-1"></i> Nova classe (regulamento padronizado)</div>
  <div class="card-body">
    <form method="get" class="mb-3" style="max-width:420px">
      <input type="hidden" name="fundo_id" value="<?= $fid ?>">
      <label class="form-label" style="font-size:.82rem">Tipo da classe</label>
      <select name="tipo" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="">Selecione…</option>
        <?php foreach ($tipos as $k => $t): ?><option value="<?= e_html($k) ?>" <?= $k === $tipo ? 'selected' : '' ?>><?= e_html($t['nome']) ?></option><?php endforeach; ?>
      </select>
    </form>
    <?php if ($tipo): ?>
      <form method="post">
        <?= csrf_campo() ?><input type="hidden" name="reg_tipo" value="<?= e_html($tipo) ?>"><input type="hidden" name="fundo_id" value="<?= $fid ?>">
        <div class="row g-3">
          <div class="col-lg-<?= $previa ? '6' : '12' ?>"><?= reg_render_form($tipo, $dados) ?>
            <div class="d-flex gap-2 mb-3">
              <button name="acao" value="preview" class="btn btn-outline-dark btn-sm"><i class="bi bi-eye me-1"></i>Pré-visualizar</button>
              <button name="acao" value="criar" class="btn btn-success btn-sm"><i class="bi bi-check-lg me-1"></i>Criar classe</button>
            </div>
          </div>
          <?php if ($previa): ?><div class="col-lg-6"><div class="card"><div class="card-header"><i class="bi bi-file-earmark-text me-1"></i> Prévia</div><div class="card-body" style="max-height:560px;overflow-y:auto"><?= $previa ?></div></div></div><?php endif; ?>
        </div>
      </form>
      <?= reg_form_js() ?>
    <?php endif; ?>
  </div>
</div>
<?php page_end();
