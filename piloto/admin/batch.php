<?php
// Processamento em lote (fechamento) — geral e por fundo, com isolamento por fundo,
// erro explicado e CONSOLIDAÇÃO de erros iguais (fundos agrupados por causa).
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

$u = exigir_perfil('admin');
ensure_batch($pdo);

// data de referência do fechamento = último dia com cota publicada
$dataProc = $pdo->query("SELECT MAX(data_ref) FROM cotas_historico")->fetchColumn() ?: date('Y-m-d');

$msg = ''; $msgTipo = 'success'; $relatorio = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_validar()) {
    $_POST = []; $msg = 'Requisição inválida (proteção CSRF).'; $msgTipo = 'danger';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    $novoRun = 'R' . date('YmdHis') . substr(bin2hex(random_bytes(2)), 0, 3);
    if ($acao === 'geral') {
        $demo = !empty($_POST['demo']);
        $relatorio = processar_fundos($pdo, $dataProc, null, $novoRun, $u['nome'], $demo);
        registrar_auditoria($pdo, 'batch_geral', ['entidade' => 'processamento_batch', 'detalhe' =>
            "Batch geral {$relatorio['run_id']}: {$relatorio['ok']} ok, {$relatorio['erro']} erro" . ($demo ? ' (demo)' : '')]);
        $msg = "Batch geral concluído: {$relatorio['ok']} OK, {$relatorio['erro']} com erro (de {$relatorio['total']} fundos).";
        if ($relatorio['erro']) $msgTipo = 'warning';
    } elseif ($acao === 'fundo') {
        $fid = (int)($_POST['fundo_id'] ?? 0);
        $run = ultimo_run_batch($pdo) ?: $novoRun;
        $relatorio = processar_fundos($pdo, $dataProc, [$fid], $run, $u['nome']);
        $msg = "Fundo #$fid reprocessado na execução $run.";
        $relatorio = carregar_relatorio_batch($pdo, $run);   // mostra o run completo atualizado
    } elseif ($acao === 'grupo') {
        $codigo = $_POST['codigo'] ?? '';
        $run = ultimo_run_batch($pdo);
        if ($run) {
            $st = $pdo->prepare("SELECT fundo_id FROM processamento_batch WHERE run_id=? AND erro_codigo=?");
            $st->execute([$run, $codigo]);
            $ids = array_map('intval', array_column($st->fetchAll(), 'fundo_id'));
            if ($ids) {
                processar_fundos($pdo, $dataProc, $ids, $run, $u['nome']);
                $msg = 'Reprocessados ' . count($ids) . ' fundo(s) do grupo de erro "' . $codigo . '".';
            }
            $relatorio = carregar_relatorio_batch($pdo, $run);
        }
    }
}

// Ao abrir a página (ou após ação sem relatório), carrega a última execução.
if (!$relatorio) {
    $run = ultimo_run_batch($pdo);
    if ($run) $relatorio = carregar_relatorio_batch($pdo, $run);
}
$fundos = $pdo->query("SELECT id, nome FROM fundos WHERE status IN ('Ativo','Em abertura') ORDER BY pl_atual DESC")->fetchAll();

page_start('Processamento em lote', 'Processamento em lote', $u,
    'Fechamento de ' . data_br($dataProc) . ' · cada fundo é processado isolado; erros iguais são consolidados');
?>

<?php if ($msg): ?><div class="alert alert-<?= e_html($msgTipo) ?> py-2"><i class="bi bi-info-circle me-1"></i><?= e_html($msg) ?></div><?php endif; ?>

<!-- Controles -->
<div class="row g-3 mb-4">
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-play-circle me-1"></i> Processamento geral</div>
      <div class="card-body">
        <p class="text-muted mb-2" style="font-size:.85rem">Roda o fechamento de <b><?= data_br($dataProc) ?></b> para
          <b>todos</b> os fundos. Cada fundo é isolado: se um falha, os demais seguem e publicam.</p>
        <form method="post" class="d-flex flex-wrap gap-2 align-items-center">
          <?= csrf_campo() ?><input type="hidden" name="acao" value="geral">
          <button class="btn btn-dark btn-sm"><i class="bi bi-cpu me-1"></i>Processar todos os fundos</button>
          <div class="form-check ms-2">
            <input class="form-check-input" type="checkbox" name="demo" value="1" id="demo">
            <label class="form-check-label" for="demo" style="font-size:.8rem">incluir cenário de falhas (demonstração)</label>
          </div>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-bullseye me-1"></i> Processar um fundo</div>
      <div class="card-body">
        <form method="post" class="d-flex gap-2">
          <?= csrf_campo() ?><input type="hidden" name="acao" value="fundo">
          <select name="fundo_id" class="form-select form-select-sm" required>
            <option value="">Selecione…</option>
            <?php foreach ($fundos as $f): ?><option value="<?= (int)$f['id'] ?>"><?= e_html($f['nome']) ?></option><?php endforeach; ?>
          </select>
          <button class="btn btn-outline-dark btn-sm text-nowrap"><i class="bi bi-arrow-clockwise me-1"></i>Processar</button>
        </form>
        <p class="text-muted mb-0 mt-2" style="font-size:.78rem">Reprocessa um fundo específico na execução atual (idempotente — sobrescreve o resultado dele).</p>
      </div>
    </div>
  </div>
</div>

<?php if (!$relatorio): ?>
  <div class="alert alert-secondary">Nenhuma execução ainda. Clique em <b>Processar todos os fundos</b> para rodar o fechamento.</div>
<?php else: ?>

<!-- KPIs da execução -->
<div class="row row-cols-2 row-cols-md-4 g-3 mb-4">
  <?= kpi('Fundos processados', (string)$relatorio['total'], 'bi-collection', 'execução ' . e_html($relatorio['run_id'])) ?>
  <?= kpi('Publicados (OK)', '<span class="text-success">' . $relatorio['ok'] . '</span>', 'bi-check-circle') ?>
  <?= kpi('Com erro', '<span class="' . ($relatorio['erro'] ? 'text-danger' : '') . '">' . $relatorio['erro'] . '</span>', 'bi-x-octagon') ?>
  <?= kpi('Data do fechamento', data_br($relatorio['data']), 'bi-calendar-check') ?>
</div>

<!-- Consolidação de erros -->
<?php if ($relatorio['consolidado']): ?>
<div class="card mb-4 border-danger">
  <div class="card-header text-danger"><i class="bi bi-exclamation-octagon me-1"></i>
    Erros consolidados <span class="text-muted">— fundos agrupados pela mesma causa</span></div>
  <div class="card-body">
    <?php foreach ($relatorio['consolidado'] as $g): ?>
      <div class="border rounded p-3 mb-3" style="background:#fff7f7">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
          <div>
            <span class="badge bg-danger-subtle text-danger-emphasis border border-danger-subtle"><?= e_html($g['codigo']) ?></span>
            <b class="ms-1"><?= e_html($g['titulo']) ?></b>
            <span class="badge bg-secondary ms-1"><?= count($g['fundos']) ?> fundo(s)</span>
            <div class="text-muted mt-1" style="font-size:.82rem"><?= e_html($g['explicacao']) ?></div>
            <div class="mt-1" style="font-size:.8rem"><i class="bi bi-lightbulb me-1 text-warning"></i><b>Como resolver:</b> <?= e_html($g['resolver']) ?></div>
          </div>
          <form method="post">
            <?= csrf_campo() ?><input type="hidden" name="acao" value="grupo"><input type="hidden" name="codigo" value="<?= e_html($g['codigo']) ?>">
            <button class="btn btn-sm btn-outline-danger text-nowrap"><i class="bi bi-arrow-repeat me-1"></i>Reprocessar grupo</button>
          </form>
        </div>
        <div class="mt-2 d-flex flex-wrap gap-1">
          <?php foreach ($g['fundos'] as $ff): ?>
            <span class="badge bg-light text-dark border" style="font-weight:500">
              <?= e_html($ff['nome']) ?><?php if ($ff['detalhe']): ?> <span class="text-muted">· <?= e_html($ff['detalhe']) ?></span><?php endif; ?>
            </span>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php else: ?>
  <div class="alert alert-success py-2 mb-4"><i class="bi bi-check-circle me-1"></i> Nenhum erro nesta execução — todos os fundos fecharam a cota.</div>
<?php endif; ?>

<!-- Resultado por fundo -->
<div class="card">
  <div class="card-header"><i class="bi bi-list-check me-1"></i> Resultado por fundo</div>
  <div class="card-body p-0">
    <table class="table table-hover align-middle mb-0" style="font-size:.85rem">
      <thead><tr><th>Fundo</th><th class="text-center">Status</th><th class="text-end">Cota</th><th class="text-end">PL</th><th>Erro</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($relatorio['fundos'] as $r): ?>
        <tr>
          <td><b><?= e_html($r['nome']) ?></b></td>
          <td class="text-center"><?= $r['status'] === 'ok' ? badge('publicado', 'success') : badge('erro', 'danger') ?></td>
          <td class="text-end"><?= $r['cota'] !== null ? number_format((float)$r['cota'], 8, ',', '.') : '—' ?></td>
          <td class="text-end"><?= $r['pl'] !== null ? moeda_compacta($r['pl']) : '—' ?></td>
          <td style="font-size:.8rem">
            <?php if ($r['codigo']): ?>
              <span class="text-danger"><?= e_html($r['codigo']) ?></span>
              <?php if ($r['detalhe']): ?><span class="text-muted">· <?= e_html($r['detalhe']) ?></span><?php endif; ?>
            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
          </td>
          <td class="text-end">
            <?php if ($r['status'] === 'erro'): ?>
              <form method="post"><?= csrf_campo() ?><input type="hidden" name="acao" value="fundo"><input type="hidden" name="fundo_id" value="<?= (int)$r['id'] ?>">
                <button class="btn btn-sm btn-outline-secondary py-0"><i class="bi bi-arrow-clockwise"></i></button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer text-muted" style="font-size:.74rem">
    Isolamento por fundo: a falha de um não impede os demais de publicar. Reprocessar é idempotente (sobrescreve o resultado do fundo na execução).
    A cota só "publica" quando passa nas travas (cotas emitidas, carteira, PL &gt; 0 e variação dentro do limite).
  </div>
</div>
<?php endif; ?>
<?php page_end();
