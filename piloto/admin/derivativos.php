<?php
// ============================================================
// DERIVATIVOS — DI1 (futuro de DI) e DAP (futuro de cupom de IPCA / juro real).
// Painel de posições com AJUSTE DIÁRIO: não há desembolso do principal; há
// margem de garantia e marcação a mercado liquidada em caixa todo dia útil,
// que roda automaticamente no "passar de dia" (ajuste_diario_derivativos).
// Infra em includes/derivativos.php (carregada via helpers→dominio→layout).
// ============================================================
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

ensure_derivativos($pdo);   // DDL (commit implícito) — sempre FORA de transação

$u = exigir_perfil('admin', 'gestor');
$msg = ''; $msgTipo = 'success';

// ---------- Seleção de fundo (por GET, guardada na sessão) ----------
$fundo = fundo_do_usuario($pdo, $u);
$fid = $fundo ? (int)$fundo['id'] : 0;
if ($u['perfil'] === 'gestor' && $fid) exigir_permissao($pdo, $u, $fid, 'operar_derivativos');

// Lista de fundos para o seletor (admin: todos; gestor: os seus).
if ($u['perfil'] === 'admin') {
    $fundos = $pdo->query("SELECT * FROM fundos WHERE status='Ativo' ORDER BY pl_atual DESC")->fetchAll();
} else {
    $fundos = fundos_do_usuario($pdo, $u);
}

$data = $fundo ? (ultima_data_carteira($pdo, $fid) ?: date('Y-m-d')) : date('Y-m-d');

// ---------- Domínios ----------
const DERIV_INSTRUMENTOS = ['DI1', 'DAP'];

// ---------- Proteções de POST ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_validar()) {
    $_POST = [];
    $msg = 'Requisição inválida (proteção CSRF). Recarregue a página.'; $msgTipo = 'danger';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !nonce_valido()) {
    $_POST = [];
    $msg = 'Ação já processada — envio duplicado ignorado.'; $msgTipo = 'warning';
}

// ---------- Ações ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $fundo) {
    try {
        if (!empty($_POST['abrir'])) {
            $instr     = in_array($_POST['instrumento'] ?? '', DERIV_INSTRUMENTOS, true) ? $_POST['instrumento'] : DERIV_INSTRUMENTOS[0];
            $venc      = trim($_POST['vencimento'] ?? '');
            $contratos = (int)($_POST['contratos'] ?? 0);
            $compradoPu = ($_POST['direcao'] ?? 'Comprado') === 'Comprado';
            // taxa informada em % a.a. — convertida para fração (11 -> 0,11).
            $taxa      = (float)str_replace(',', '.', $_POST['taxa'] ?? '0') / 100;
            $margem    = (float)str_replace(['.', ','], ['', '.'], $_POST['margem'] ?? '0');

            if ($venc === '' || $venc <= $data) {
                $msg = 'Informe um vencimento futuro (posterior à data de referência).'; $msgTipo = 'warning';
            } elseif ($contratos <= 0) {
                $msg = 'Informe um número de contratos maior que zero.'; $msgTipo = 'warning';
            } elseif ($taxa <= 0) {
                $msg = 'Informe uma taxa a.a. válida (%).'; $msgTipo = 'warning';
            } else {
                $id = abrir_derivativo($pdo, $fid, $instr, $venc, $contratos, $compradoPu, $taxa, $margem, $data);
                registrar_auditoria($pdo, 'derivativo_aberto', [
                    'entidade' => 'derivativo', 'entidade_id' => $id, 'fundo_id' => $fid,
                    'detalhe' => "Abertura $instr venc " . data_br($venc) . " — $contratos contrato(s) " .
                                 ($compradoPu ? 'Comprado' : 'Vendido') . ' em PU a ' .
                                 number_format($taxa * 100, 2, ',', '.') . '% a.a., margem ' . moeda($margem),
                ]);
                $msg = "Posição $instr aberta (#$id): $contratos contrato(s) " .
                       ($compradoPu ? 'Comprado' : 'Vendido') . ' em PU a ' .
                       number_format($taxa * 100, 2, ',', '.') . '% a.a. — margem ' . moeda($margem) .
                       '. O ajuste diário roda no passar de dia.';
            }
        }
    } catch (Throwable $e) {
        $msg = 'Não foi possível concluir: ' . $e->getMessage(); $msgTipo = 'danger';
    }
}

// ---------- Dados ----------
$posicoes = [];
if ($fundo) {
    $st = $pdo->prepare("SELECT * FROM derivativos WHERE fundo_id = ? AND status = 'Aberta' ORDER BY vencimento, id");
    $st->execute([$fid]);
    $posicoes = $st->fetchAll();
}

$ajustes = [];
$somaAjustes = 0.0;
if ($fundo) {
    $st = $pdo->prepare("SELECT da.*, d.instrumento, d.vencimento
                         FROM derivativos_ajustes da LEFT JOIN derivativos d ON d.id = da.deriv_id
                         WHERE da.fundo_id = ? ORDER BY da.data_ref DESC, da.id DESC LIMIT 60");
    $st->execute([$fid]);
    $ajustes = $st->fetchAll();

    $somaAjustes = (float)$pdo->query("SELECT COALESCE(SUM(ajuste),0) FROM derivativos_ajustes WHERE fundo_id = " . (int)$fid)->fetchColumn();
}

// KPIs das posições abertas.
$totMargem = 0.0; $totNocional = 0.0;
foreach ($posicoes as $p) {
    $totMargem   += (float)$p['margem'];
    $totNocional += (int)$p['contratos'] * (float)$p['pu_atual'];
}

page_start('Derivativos', 'Derivativos', $u,
    'DI1 / DAP com ajuste diário — margem de garantia e marcação a mercado liquidada em caixa no passar de dia');
?>

<?php if ($msg): ?><div class="alert alert-<?= e_html($msgTipo) ?> py-2"><i class="bi bi-info-circle me-1"></i><?= e_html($msg) ?></div><?php endif; ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <form method="get">
    <select name="fundo_id" class="form-select form-select-sm" onchange="this.form.submit()">
      <?php foreach ($fundos as $f): ?>
        <option value="<?= (int)$f['id'] ?>" <?= (int)$f['id'] === $fid ? 'selected' : '' ?>><?= e_html($f['nome']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <div style="font-size:.82rem" class="text-muted">
    Data de referência: <b><?= data_br($data) ?></b>
  </div>
</div>

<?php if (!$fundo): ?>
  <div class="alert alert-warning py-2"><i class="bi bi-exclamation-triangle me-1"></i>Nenhum fundo disponível para o seu usuário.</div>
<?php else: ?>

<div class="row row-cols-2 row-cols-md-4 g-3 mb-4">
  <?= kpi('Posições abertas', (string)count($posicoes), 'bi-graph-up-arrow') ?>
  <?= kpi('Margem depositada', moeda_compacta($totMargem), 'bi-shield-lock', 'garantia no clearing') ?>
  <?= kpi('Exposição nocional', moeda_compacta($totNocional), 'bi-cash-stack', 'contratos × PU') ?>
  <?= kpi('Ajustes acumulados', '<span class="' . ($somaAjustes >= 0 ? 'text-success' : 'text-danger') . '">' . moeda_compacta($somaAjustes) . '</span>', 'bi-arrow-left-right', 'soma dos ajustes em caixa') ?>
</div>

<!-- 1. Explicação -->
<div class="card mb-4">
  <div class="card-header"><i class="bi bi-book me-1"></i> Como funcionam DI1 e DAP</div>
  <div class="card-body" style="font-size:.86rem">
    <div class="row g-3">
      <div class="col-md-6">
        <h6 class="mb-1"><?= badge('DI1', 'info') ?> Futuro de DI</h6>
        <p class="text-muted mb-0">
          Negocia a taxa de juro nominal (CDI) até um vencimento. O preço unitário é
          <code>PU = 100.000 / (1 + i)<sup>(du/252)</sup></code>, onde <i>i</i> é a taxa a.a. e
          <i>du</i> os dias úteis até o vencimento (base 252). Cada ponto do DI1 vale <b>R$ 1,00</b>.
          <b>Comprado em PU</b> = aposta que os juros caem (PU sobe); <b>Vendido em PU</b> = aposta que sobem.
        </p>
      </div>
      <div class="col-md-6">
        <h6 class="mb-1"><?= badge('DAP', 'info') ?> Futuro de cupom de IPCA (juro real)</h6>
        <p class="text-muted mb-0">
          Negocia o juro real (cupom sobre o IPCA). Aqui usa a <b>mesma mecânica de PU simplificada</b>
          do DI1 — o ponto real (R$ 0,00025 × índice do IPCA) é aproximado. O essencial, o <b>ajuste diário
          em caixa</b>, é fiel.
        </p>
      </div>
    </div>
    <hr class="my-3">
    <div class="alert alert-info mb-0 py-2">
      <i class="bi bi-cash-coin me-1"></i>
      <b>Não há desembolso do principal.</b> Ao abrir a posição deposita-se apenas uma <b>margem de garantia</b>
      no clearing. Todo dia útil ocorre o <b>ajuste diário</b> (marcação a mercado): a variação do PU é
      creditada ou debitada em caixa. Neste piloto o ajuste roda automaticamente no <b>"passar de dia"</b>.
    </div>
  </div>
</div>

<!-- 2. Abrir posição -->
<div class="card mb-4">
  <div class="card-header"><i class="bi bi-plus-circle me-1"></i> Abrir posição</div>
  <div class="card-body">
    <form method="post" class="row g-2 align-items-end">
      <?= csrf_campo() ?><?= nonce_campo() ?>
      <input type="hidden" name="abrir" value="1">
      <div class="col-6 col-md-2">
        <label class="form-label mb-1" style="font-size:.8rem">Instrumento</label>
        <select name="instrumento" class="form-select form-select-sm">
          <?php foreach (DERIV_INSTRUMENTOS as $i): ?>
            <option value="<?= e_html($i) ?>"><?= e_html($i) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label mb-1" style="font-size:.8rem">Vencimento</label>
        <input type="date" name="vencimento" class="form-control form-control-sm" required>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label mb-1" style="font-size:.8rem">Nº de contratos</label>
        <input name="contratos" type="number" min="1" step="1" class="form-control form-control-sm" placeholder="100" required>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label mb-1" style="font-size:.8rem">Direção</label>
        <select name="direcao" class="form-select form-select-sm">
          <option value="Comprado">Comprado em PU (juros caem)</option>
          <option value="Vendido">Vendido em PU (juros sobem)</option>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label mb-1" style="font-size:.8rem">Taxa a.a. (%)</label>
        <input name="taxa" class="form-control form-control-sm" placeholder="11,00" required>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label mb-1" style="font-size:.8rem">Margem (R$)</label>
        <input name="margem" class="form-control form-control-sm" placeholder="50.000,00">
      </div>
      <div class="col-12">
        <button class="btn btn-sm btn-dark"><i class="bi bi-check-lg me-1"></i>Abrir posição</button>
        <span class="text-muted ms-2" style="font-size:.75rem">
          A taxa em % é convertida para decimal (11% → 0,11); o PU é calculado a partir dos dias úteis até o vencimento.
        </span>
      </div>
    </form>
  </div>
</div>

<!-- 3. Posições abertas -->
<div class="card mb-4">
  <div class="card-header"><i class="bi bi-list-columns me-1"></i> Posições abertas</div>
  <div class="card-body p-0">
    <table class="table table-sm table-hover align-middle mb-0" style="font-size:.83rem">
      <thead><tr>
        <th>Instrumento</th><th>Vencimento</th><th class="text-end">Contratos</th><th class="text-center">Direção</th>
        <th class="text-end">Taxa operação</th><th class="text-end">Taxa atual</th><th class="text-end">PU atual</th>
        <th class="text-end">Margem</th><th class="text-end">Exposição nocional</th>
      </tr></thead>
      <tbody>
      <?php foreach ($posicoes as $p):
          $comprado = (int)$p['comprado_pu'] === 1;
          $nocional = (int)$p['contratos'] * (float)$p['pu_atual']; ?>
        <tr>
          <td><?= badge($p['instrumento'], 'info') ?></td>
          <td class="text-muted" style="white-space:nowrap"><?= data_br($p['vencimento']) ?></td>
          <td class="text-end"><?= number_format((int)$p['contratos'], 0, ',', '.') ?></td>
          <td class="text-center"><?= $comprado ? badge('Comprado em PU', 'success') : badge('Vendido em PU', 'warning') ?></td>
          <td class="text-end"><?= number_format((float)$p['taxa_operacao'] * 100, 2, ',', '.') ?>%</td>
          <td class="text-end"><?= number_format((float)$p['taxa_atual'] * 100, 2, ',', '.') ?>%</td>
          <td class="text-end"><?= number_format((float)$p['pu_atual'], 6, ',', '.') ?></td>
          <td class="text-end"><?= moeda($p['margem']) ?></td>
          <td class="text-end"><b><?= moeda($nocional) ?></b></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$posicoes): ?><tr><td colspan="9" class="text-muted text-center py-4">Nenhuma posição aberta — abra uma acima.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer text-muted" style="font-size:.72rem">
    A taxa atual oscila a cada dia útil; o PU e o ajuste diário são recalculados no passar de dia. Posições vencidas são encerradas automaticamente.
  </div>
</div>

<!-- 4. Ajustes diários recentes -->
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-arrow-left-right me-1"></i> Ajustes diários recentes</span>
    <span style="font-size:.82rem">Soma dos ajustes:
      <b class="<?= $somaAjustes >= 0 ? 'text-success' : 'text-danger' ?>"><?= moeda($somaAjustes) ?></b>
    </span>
  </div>
  <div class="card-body p-0">
    <table class="table table-sm table-hover align-middle mb-0" style="font-size:.82rem">
      <thead><tr>
        <th>Data</th><th>Derivativo</th><th class="text-end">PU anterior</th><th class="text-end">PU novo</th><th class="text-end">Ajuste (R$)</th>
      </tr></thead>
      <tbody>
      <?php foreach ($ajustes as $a):
          $aj = (float)$a['ajuste'];
          $deriv = trim(($a['instrumento'] ?? '—') . ' ' . ($a['vencimento'] ? 'venc ' . data_br($a['vencimento']) : '')); ?>
        <tr>
          <td class="text-muted" style="white-space:nowrap"><?= data_br($a['data_ref']) ?></td>
          <td><?= $a['instrumento'] ? badge($a['instrumento'], 'info') . ' <span class="text-muted">' . ($a['vencimento'] ? 'venc ' . data_br($a['vencimento']) : '') . '</span>' : e_html($deriv) ?></td>
          <td class="text-end"><?= number_format((float)$a['pu_ant'], 6, ',', '.') ?></td>
          <td class="text-end"><?= number_format((float)$a['pu_novo'], 6, ',', '.') ?></td>
          <td class="text-end <?= $aj >= 0 ? 'text-success' : 'text-danger' ?>"><b><?= moeda($aj) ?></b></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$ajustes): ?><tr><td colspan="5" class="text-muted text-center py-4">Nenhum ajuste ainda — ocorre no passar de dia com posições abertas.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php endif; ?>
<?php page_end();
