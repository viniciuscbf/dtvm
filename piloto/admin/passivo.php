<?php
// Passivo do cotista & Tributação — aplicações, resgates (IR/IOF) e come-cotas (Lei 14.754).
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/passivo.php';

$u = exigir_perfil('admin');
$msg = ''; $msgTipo = 'success';

$fundos = $pdo->query("SELECT * FROM fundos WHERE status='Ativo' ORDER BY pl_atual DESC")->fetchAll();
if (isset($_GET['fundo_id'])) $_SESSION['admin_fundo_id'] = (int)$_GET['fundo_id'];
$fid = (int)($_SESSION['admin_fundo_id'] ?? ($fundos[0]['id'] ?? 0));
$fundo = null;
foreach ($fundos as $f) if ((int)$f['id'] === $fid) $fundo = $f;
if (!$fundo && $fundos) { $fundo = $fundos[0]; $fid = (int)$fundo['id']; }

$dataRef = $fundo ? (ultima_data_cota($pdo, $fid) ?: date('Y-m-d')) : date('Y-m-d');
$cotaRef = $fundo ? (cota_em($pdo, $fid, $dataRef) ?: (float)$fundo['cota_atual']) : 1.0;
if (!$cotaRef || $cotaRef <= 0) $cotaRef = 1.0;

// ---------- Exportação do informe de rendimentos (CSV) ----------
if ($fundo && ($_GET['export'] ?? '') === 'informe') {
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"informe_rendimentos_f{$fid}_" . date('Y') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Cotista', 'Documento', 'Tipo', 'Competencia', 'Data', 'Base (rendimento)', 'Aliquota %', 'Tributo'], ';');
    $st = $pdo->prepare("SELECT ef.*, c.nome, c.documento FROM eventos_fiscais ef LEFT JOIN cotistas c ON c.id=ef.cotista_id
                         WHERE ef.fundo_id = ? ORDER BY ef.data_ref, ef.id");
    $st->execute([$fid]);
    foreach ($st->fetchAll() as $e) {
        fputcsv($out, [$e['nome'] ?? '—', $e['documento'] ?? '', $e['tipo'], $e['competencia'], $e['data_ref'],
            number_format((float)$e['base_calculo'], 2, ',', ''), number_format((float)$e['aliquota'], 2, ',', ''),
            number_format((float)$e['valor_tributo'], 2, ',', '')], ';');
    }
    fclose($out);
    exit;
}

// ---------- Ações ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_validar()) {
    $_POST = [];
    $msg = 'Requisição inválida (proteção CSRF). Recarregue a página.'; $msgTipo = 'danger';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !nonce_valido()) {
    $_POST = [];
    $msg = 'Ação já processada — envio duplicado ignorado.'; $msgTipo = 'warning';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $fundo) {
    try {
        if (!empty($_POST['aplicar'])) {
            $valor = (float)str_replace(',', '.', $_POST['valor'] ?? '0');
            $cotistaId = (int)($_POST['cotista_id'] ?? 0);
            if ($cotistaId === 0 && trim($_POST['novo_nome'] ?? '') !== '') {
                $pdo->prepare('INSERT INTO cotistas (fundo_id, nome, documento, tipo_pessoa, cotas, custo_total, data_entrada)
                               VALUES (?,?,?,?,0,0,?)')
                    ->execute([$fid, trim($_POST['novo_nome']), trim($_POST['novo_doc'] ?? ''),
                               ($_POST['novo_tipo'] ?? 'PF'), $dataRef]);
                $cotistaId = (int)$pdo->lastInsertId();
            }
            if ($cotistaId > 0 && $valor > 0) {
                $r = passivo_aplicar($pdo, $fid, $cotistaId, $valor, $dataRef);
                registrar_auditoria($pdo, 'aplicacao_cotista', ['entidade' => 'cotista', 'entidade_id' => $cotistaId, 'fundo_id' => $fid,
                    'detalhe' => 'Aplicação de ' . moeda($valor) . ' (' . number_format($r['cotas'], 6, ',', '.') . ' cotas)']);
                $msg = 'Aplicação de ' . moeda($valor) . ' cotizada a ' . number_format($r['cota'], 8, ',', '.') .
                       ' — ' . number_format($r['cotas'], 6, ',', '.') . ' cotas emitidas.';
            } else {
                $msg = 'Informe um cotista (ou novo nome) e um valor válido.'; $msgTipo = 'warning';
            }
        } elseif (!empty($_POST['resgatar'])) {
            $cotistaId = (int)($_POST['cotista_id'] ?? 0);
            $valor = (float)str_replace(',', '.', $_POST['valor'] ?? '0');
            $r = passivo_resgatar($pdo, $fid, $cotistaId, $valor, $dataRef);
            registrar_auditoria($pdo, 'resgate_cotista', ['entidade' => 'cotista', 'entidade_id' => $cotistaId, 'fundo_id' => $fid,
                'detalhe' => 'Resgate bruto ' . moeda($r['bruto']) . ' · IR ' . moeda($r['ir']) . ' · IOF ' . moeda($r['iof'])]);
            $msg = 'Resgate bruto ' . moeda($r['bruto']) . ' — IR ' . moeda($r['ir']) . ' (' . number_format($r['aliq'], 1, ',', '.') . '%)' .
                   ($r['iof'] > 0 ? ' + IOF ' . moeda($r['iof']) : '') . ' → líquido ' . moeda($r['liquido']) . ' (' . $r['dias'] . ' dias).';
        } elseif (!empty($_POST['come_cotas'])) {
            $comp = trim($_POST['competencia'] ?? substr($dataRef, 0, 7));
            $r = passivo_come_cotas($pdo, $fid, $comp, $dataRef);
            if (!$r['aplicavel']) { $msg = 'Este fundo (ações/isento) não está sujeito ao come-cotas.'; $msgTipo = 'warning'; }
            elseif (!empty($r['repetido'])) { $msg = "Come-cotas da competência $comp já foi apurado para este fundo."; $msgTipo = 'warning'; }
            elseif ($r['n'] === 0) { $msg = 'Nenhum cotista com rendimento tributável na competência.'; $msgTipo = 'warning'; }
            else {
                registrar_auditoria($pdo, 'come_cotas', ['entidade' => 'fundo', 'entidade_id' => $fid, 'fundo_id' => $fid,
                    'detalhe' => "Come-cotas $comp: " . moeda($r['total']) . " de {$r['n']} cotista(s) a " . $r['aliquota'] . '%']);
                $msg = "Come-cotas $comp apurado: " . moeda($r['total']) . " recolhidos de {$r['n']} cotista(s) à alíquota de " .
                       number_format($r['aliquota'], 0, ',', '.') . '%.';
            }
        }
        // recarrega o fundo após a ação (caixa/PL mudaram)
        foreach ($pdo->query("SELECT * FROM fundos WHERE id=$fid")->fetchAll() as $ff) $fundo = $ff;
        $cotaRef = cota_em($pdo, $fid, $dataRef) ?: (float)$fundo['cota_atual'];
        if (!$cotaRef || $cotaRef <= 0) $cotaRef = 1.0;
    } catch (Throwable $e) {
        $msg = 'Não foi possível concluir: ' . $e->getMessage(); $msgTipo = 'danger';
    }
}

// ---------- Dados ----------
$cotistas = [];
if ($fundo) {
    $st = $pdo->prepare('SELECT * FROM cotistas WHERE fundo_id = ? ORDER BY cotas DESC');
    $st->execute([$fid]);
    $cotistas = $st->fetchAll();
}
$eventos = [];
if ($fundo) {
    $st = $pdo->prepare("SELECT ef.*, c.nome cotista_nome FROM eventos_fiscais ef LEFT JOIN cotistas c ON c.id=ef.cotista_id
                         WHERE ef.fundo_id = ? ORDER BY ef.data_ref DESC, ef.id DESC LIMIT 60");
    $st->execute([$fid]);
    $eventos = $st->fetchAll();
}
$totalTributo = 0.0;
foreach ($eventos as $e) $totalTributo += (float)$e['valor_tributo'];
$totalCotas = 0.0;
foreach ($cotistas as $c) $totalCotas += (float)$c['cotas'];

$tribFundo = $fundo['tributacao'] ?? 'Longo Prazo';
if (($fundo['classe'] ?? '') === 'Ações') $tribFundo = 'Ações';
$aliqCC = aliquota_come_cotas($tribFundo);

page_start('Passivo & Tributação', 'Passivo & Tributação', $u,
    'Aplicações, resgates (IR/IOF) e come-cotas — o administrador como responsável tributário (Lei 14.754/2023)');
?>

<?php if ($msg): ?><div class="alert alert-<?= $msgTipo ?> py-2"><i class="bi bi-info-circle me-1"></i><?= e_html($msg) ?></div><?php endif; ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <form method="get">
    <select name="fundo_id" class="form-select form-select-sm" onchange="this.form.submit()">
      <?php foreach ($fundos as $f): ?>
        <option value="<?= (int)$f['id'] ?>" <?= (int)$f['id'] === $fid ? 'selected' : '' ?>><?= e_html($f['nome']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <div style="font-size:.82rem" class="text-muted">
    Cota de referência (<?= data_br($dataRef) ?>): <b><?= number_format($cotaRef, 8, ',', '.') ?></b> ·
    Tributação: <?= badge($tribFundo, $tribFundo === 'Ações' ? 'info' : 'secondary') ?>
    <?= $aliqCC !== null ? '· come-cotas ' . number_format($aliqCC, 0, ',', '.') . '%' : '· sem come-cotas' ?>
  </div>
</div>

<div class="row row-cols-2 row-cols-md-4 g-3 mb-4">
  <?= kpi('Cotistas', (string)count($cotistas), 'bi-people') ?>
  <?= kpi('Cotas emitidas', number_format($totalCotas, 2, ',', '.'), 'bi-pie-chart') ?>
  <?= kpi('Patrimônio dos cotistas', moeda_compacta($totalCotas * $cotaRef), 'bi-cash-stack') ?>
  <?= kpi('Tributo apurado', moeda_compacta($totalTributo), 'bi-receipt', 'IR + IOF + come-cotas') ?>
</div>

<div class="row g-3 mb-4">
  <!-- Aplicação -->
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-plus-circle me-1"></i> Nova aplicação</div>
      <div class="card-body">
        <form method="post">
          <?= csrf_campo() ?><?= nonce_campo() ?>
          <label class="form-label" style="font-size:.8rem">Cotista</label>
          <select name="cotista_id" class="form-select form-select-sm mb-2" onchange="document.getElementById('novo-cot').style.display=this.value==='0'?'block':'none'">
            <?php foreach ($cotistas as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= e_html($c['nome']) ?></option>
            <?php endforeach; ?>
            <option value="0">➕ Novo cotista…</option>
          </select>
          <div id="novo-cot" style="display:<?= $cotistas ? 'none' : 'block' ?>">
            <input name="novo_nome" class="form-control form-control-sm mb-1" placeholder="Nome do novo cotista">
            <div class="d-flex gap-1 mb-2">
              <input name="novo_doc" class="form-control form-control-sm" placeholder="CPF/CNPJ">
              <select name="novo_tipo" class="form-select form-select-sm" style="max-width:80px"><option>PF</option><option>PJ</option></select>
            </div>
          </div>
          <label class="form-label" style="font-size:.8rem">Valor a aplicar (R$)</label>
          <input name="valor" class="form-control form-control-sm mb-2" placeholder="10000,00" required>
          <button name="aplicar" value="1" class="btn btn-sm btn-dark w-100"><i class="bi bi-check-lg me-1"></i>Aplicar (cotizar)</button>
        </form>
      </div>
    </div>
  </div>
  <!-- Resgate -->
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-dash-circle me-1"></i> Resgate (com IR/IOF)</div>
      <div class="card-body">
        <form method="post" onsubmit="return confirm('Confirmar o resgate? IR e IOF serão retidos conforme o prazo.')">
          <?= csrf_campo() ?><?= nonce_campo() ?>
          <label class="form-label" style="font-size:.8rem">Cotista</label>
          <select name="cotista_id" class="form-select form-select-sm mb-2" required>
            <?php foreach ($cotistas as $c): if ((float)$c['cotas'] <= 0) continue; ?>
              <option value="<?= (int)$c['id'] ?>"><?= e_html($c['nome']) ?> — <?= number_format((float)$c['cotas'] * $cotaRef, 2, ',', '.') ?></option>
            <?php endforeach; ?>
          </select>
          <label class="form-label" style="font-size:.8rem">Valor bruto a resgatar (R$)</label>
          <input name="valor" class="form-control form-control-sm mb-2" placeholder="5000,00" required>
          <button name="resgatar" value="1" class="btn btn-sm btn-outline-danger w-100"><i class="bi bi-box-arrow-up me-1"></i>Resgatar</button>
        </form>
        <p class="text-muted mb-0 mt-2" style="font-size:.7rem">IR regressivo 22,5%→15% conforme o prazo; IOF se &lt; 30 dias.</p>
      </div>
    </div>
  </div>
  <!-- Come-cotas -->
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-scissors me-1"></i> Come-cotas (mai/nov)</div>
      <div class="card-body">
        <?php if ($aliqCC === null): ?>
          <div class="alert alert-info py-2" style="font-size:.8rem">Fundo de <b>ações/isento</b>: não há come-cotas. O IR incide apenas no resgate.</div>
        <?php else: ?>
          <form method="post" onsubmit="return confirm('Apurar o come-cotas? As cotas dos cotistas serão reduzidas pelo tributo.')">
            <?= csrf_campo() ?><?= nonce_campo() ?>
            <label class="form-label" style="font-size:.8rem">Competência</label>
            <select name="competencia" class="form-select form-select-sm mb-2">
              <?php $ano = (int)substr($dataRef, 0, 4); foreach (["$ano-05", "$ano-11", ($ano - 1) . '-11', ($ano - 1) . '-05'] as $cmp): ?>
                <option value="<?= $cmp ?>"><?= $cmp ?></option>
              <?php endforeach; ?>
            </select>
            <p class="text-muted" style="font-size:.75rem">Alíquota do fundo: <b><?= number_format($aliqCC, 0, ',', '.') ?>%</b> sobre o rendimento; base sofre <i>step-up</i> após a apuração.</p>
            <button name="come_cotas" value="1" class="btn btn-sm btn-warning w-100"><i class="bi bi-scissors me-1"></i>Apurar come-cotas</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Cotistas -->
<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-people me-1"></i> Cotistas e posições</span>
    <a class="btn btn-sm btn-outline-secondary" href="?export=informe"><i class="bi bi-download me-1"></i>Informe de rendimentos (CSV)</a>
  </div>
  <div class="card-body p-0">
    <table class="table table-hover align-middle mb-0" style="font-size:.83rem">
      <thead><tr><th>Cotista</th><th class="text-end">Cotas</th><th class="text-end">PM</th>
        <th class="text-end">Valor de mercado</th><th class="text-end">Rendimento</th><th class="text-center">Prazo</th></tr></thead>
      <tbody>
      <?php foreach ($cotistas as $c):
          $pm = pm_cotista($c);
          $vm = (float)$c['cotas'] * $cotaRef;
          $custo = $c['custo_total'] !== null ? (float)$c['custo_total'] : (float)$c['cotas'];
          $rend = $vm - $custo;
          $dias = dias_entre($c['data_entrada'] ?? null, $dataRef); ?>
        <tr>
          <td><b><?= e_html($c['nome']) ?></b> <span class="text-muted" style="font-size:.72rem"><?= e_html($c['tipo_pessoa']) ?></span></td>
          <td class="text-end"><?= number_format((float)$c['cotas'], 6, ',', '.') ?></td>
          <td class="text-end"><?= number_format($pm, 6, ',', '.') ?></td>
          <td class="text-end"><b><?= moeda($vm) ?></b></td>
          <td class="text-end <?= $rend >= 0 ? 'text-success' : 'text-danger' ?>"><?= moeda($rend) ?></td>
          <td class="text-center"><?= $dias ?> d</td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$cotistas): ?><tr><td colspan="6" class="text-muted text-center py-4">Sem cotistas — faça uma aplicação para começar.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer text-muted" style="font-size:.72rem">
    O rendimento é apurado pela valorização da cota vs. o custo médio (PM); o motor fiscal
    provisiona o IR na cota e apura come-cotas em maio/novembro. Valores e alíquotas conforme a Lei 14.754/2023.
  </div>
</div>

<!-- Eventos fiscais -->
<div class="card">
  <div class="card-header"><i class="bi bi-receipt me-1"></i> Eventos fiscais (histórico)</div>
  <div class="card-body p-0">
    <table class="table table-sm table-hover align-middle mb-0" style="font-size:.82rem">
      <thead><tr><th>Data</th><th>Tipo</th><th>Cotista</th><th>Competência</th>
        <th class="text-end">Base</th><th class="text-end">Alíq.</th><th class="text-end">Tributo</th></tr></thead>
      <tbody>
      <?php foreach ($eventos as $e): ?>
        <tr>
          <td class="text-muted" style="white-space:nowrap"><?= data_br($e['data_ref']) ?></td>
          <td><?= badge($e['tipo'], $e['tipo'] === 'Come-cotas' ? 'warning' : ($e['tipo'] === 'IOF Resgate' ? 'info' : 'secondary')) ?></td>
          <td style="font-size:.8rem"><?= e_html($e['cotista_nome'] ?? '—') ?></td>
          <td><?= e_html($e['competencia']) ?></td>
          <td class="text-end"><?= moeda($e['base_calculo']) ?></td>
          <td class="text-end"><?= number_format((float)$e['aliquota'], 1, ',', '.') ?>%</td>
          <td class="text-end"><b><?= moeda($e['valor_tributo']) ?></b></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$eventos): ?><tr><td colspan="7" class="text-muted text-center py-4">Nenhum tributo apurado ainda.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php page_end();
