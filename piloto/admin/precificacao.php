<?php
// Precificação — Marcação a Mercado (MaM) homologada + Comitê de Precificação.
// SIMULAÇÃO: numa operação real, o "preço de referência" viria de feeds ANBIMA/B3.
// Aqui o feed independente é a tabela precos_mercado (alimentada pelo simulador);
// o comitê homologa preços de ativos sem fonte líquida (CDB/ilíquidos) e de divergências.
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

$u = exigir_perfil('admin');

// DDL fora de transação (CREATE causa commit implícito no MySQL/MariaDB)
ensure_precos($pdo);

$msg = ''; $msgTipo = 'success';

// Limiar de divergência de marcação (0,5%)
const LIMIAR_DIVERGENCIA = 0.005;

$fundos = $pdo->query("SELECT * FROM fundos WHERE status='Ativo' ORDER BY pl_atual DESC")->fetchAll();
$fid = (int)($_GET['fundo_id'] ?? $_POST['fundo_id'] ?? ($fundos[0]['id'] ?? 0));
$fundoSel = null;
foreach ($fundos as $f) if ((int)$f['id'] === $fid) $fundoSel = $f;
if (!$fundoSel && $fundos) { $fundoSel = $fundos[0]; $fid = (int)$fundoSel['id']; }

$datas = datas_carteira($pdo, $fid);
$data = $_GET['data'] ?? $_POST['data'] ?? ($datas[0] ?? date('Y-m-d'));
if (!in_array($data, $datas, true) && $datas) $data = $datas[0];

// ---------------- AÇÕES ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_validar()) { $_POST = []; $msg = 'Requisição inválida (proteção CSRF). Recarregue a página.'; $msgTipo = 'danger'; }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['homologar'])) {
    // O comitê homologa um preço para um código na data: grava/atualiza o feed
    // precos_mercado (fonte='Comitê') e, opcionalmente, alinha o preco_mam da
    // carteira do fundo/data selecionado ao preço homologado.
    $codigo = trim((string)($_POST['codigo'] ?? ''));
    $preco  = (float)str_replace(',', '.', (string)($_POST['preco_homologado'] ?? '0'));
    $justificativa = trim((string)($_POST['justificativa'] ?? ''));
    $alinhar = !empty($_POST['alinhar_mam']);

    if ($codigo === '' || $preco <= 0) {
        $msg = 'Informe um código válido e um preço homologado maior que zero.'; $msgTipo = 'danger';
    } else {
        com_transacao($pdo, function () use ($pdo, $fid, $data, $codigo, $preco, $justificativa, $alinhar, $u) {
            // 1) homologa no feed independente (fonte Comitê)
            $pdo->prepare("INSERT INTO precos_mercado (codigo, data_ref, preco, fonte) VALUES (?,?,?,'Comitê')
                           ON DUPLICATE KEY UPDATE preco=VALUES(preco), fonte='Comitê'")
                ->execute([$codigo, $data, $preco]);

            // 2) atualiza a referência de mercado do ativo na carteira do fundo/data
            $pdo->prepare("UPDATE ativos_carteira SET preco_referencia = ?, fonte_preco = 'Comitê'
                           WHERE fundo_id = ? AND codigo = ? AND data_ref = ?")
                ->execute([$preco, $fid, $codigo, $data]);

            // 3) opcionalmente alinha a marcação (preco_mam) ao preço homologado
            if ($alinhar) {
                $pdo->prepare("UPDATE ativos_carteira SET preco_mam = ?
                               WHERE fundo_id = ? AND codigo = ? AND data_ref = ?")
                    ->execute([$preco, $fid, $codigo, $data]);
            }

            // 4) ata/log do comitê (etapa 'Precificação')
            $ata = "Comitê homologou {$codigo} @ " . number_format($preco, 6, ',', '.') .
                   " em " . data_br($data) . ($alinhar ? ' (preço MaM alinhado)' : ' (referência)') .
                   ($justificativa !== '' ? " — {$justificativa}" : '') . ' · por ' . $u['nome'];
            $pdo->prepare("INSERT INTO log_processamento (fundo_id, data_ref, etapa, nivel, mensagem) VALUES (?,?,?,?,?)")
                ->execute([$fid, $data, 'Precificação', 'INFO', $ata]);

            // 5) auditoria
            registrar_auditoria($pdo, 'preco_homologado', [
                'entidade' => 'precos_mercado', 'entidade_id' => $codigo, 'fundo_id' => $fid,
                'detalhe' => $ata,
            ]);
        });
        $msg = "Preço de {$codigo} homologado pelo Comitê em " . data_br($data) . " (fonte Comitê)." .
               ($alinhar ? ' Preço MaM alinhado ao homologado.' : ' Referência de mercado atualizada.');
        $msgTipo = 'warning';
    }
}

// ---------------- DADOS DA PÁGINA ----------------
$ativos = carteira($pdo, $fid, $data);

// Divergências MaM × referência
$divergentes = [];
foreach ($ativos as &$a) {
    $mam = (float)$a['preco_mam'];
    $ref = (float)$a['preco_referencia'];
    $a['div_pct'] = ($ref > 0) ? ($mam - $ref) / $ref : 0.0;
    $a['divergente'] = ($ref > 0 && abs($a['div_pct']) > LIMIAR_DIVERGENCIA);
    if ($a['divergente']) $divergentes[] = $a['codigo'];
}
unset($a);

// Feed de preços do dia (fonte independente)
$stFeed = $pdo->prepare("SELECT codigo, fonte, preco FROM precos_mercado WHERE data_ref = ? ORDER BY fonte, codigo");
$stFeed->execute([$data]);
$feed = $stFeed->fetchAll();

// Ata do comitê (log de Precificação) — mais recentes
$stAta = $pdo->prepare("SELECT * FROM log_processamento WHERE etapa='Precificação' AND fundo_id=? ORDER BY id DESC LIMIT 15");
$stAta->execute([$fid]);
$ataLog = $stAta->fetchAll();

// KPIs
$qtdComite = 0; foreach ($ativos as $a) if (($a['fonte_preco'] ?? '') === 'Comitê') $qtdComite++;
$qtdDiverg = count($divergentes);

page_start('Precificação', 'Precificação', $u,
    'Marcação a Mercado homologada · cascata ANBIMA → B3 → Comitê de Precificação · feed independente vs. preço marcado');
?>

<?php if ($msg): ?><div class="alert alert-<?= $msgTipo ?> py-2"><i class="bi bi-info-circle me-1"></i><?= e_html($msg) ?></div><?php endif; ?>

<div class="alert alert-secondary py-2" style="font-size:.8rem">
  <i class="bi bi-info-circle me-1"></i>
  <b>Simulação de MaM homologada.</b> As fontes de referência (ANBIMA/B3) são reproduzidas por um feed interno
  (<code>precos_mercado</code>). O preço de referência vem dos feeds ANBIMA/B3 e das corretoras.
</div>

<div class="card mb-3">
  <div class="card-body py-2">
    <form method="get" class="d-flex gap-2 align-items-center flex-wrap">
      <i class="bi bi-funnel text-muted"></i>
      <select name="fundo_id" class="form-select form-select-sm" style="max-width:320px" onchange="this.form.submit()">
        <?php foreach ($fundos as $f): ?>
          <option value="<?= (int)$f['id'] ?>" <?= (int)$f['id'] === $fid ? 'selected' : '' ?>><?= e_html($f['nome']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="data" class="form-select form-select-sm" style="max-width:170px" onchange="this.form.submit()">
        <?php foreach ($datas as $d): ?>
          <option value="<?= $d ?>" <?= $d === $data ? 'selected' : '' ?>><?= data_br($d) ?><?= $d === ($datas[0] ?? '') ? ' (D-1)' : '' ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (!$datas): ?><span class="text-muted" style="font-size:.78rem">Sem snapshot de carteira para este fundo.</span><?php endif; ?>
    </form>
  </div>
</div>

<div class="row g-3 mb-3">
  <?= kpi('Ativos na carteira', number_format(count($ativos), 0, ',', '.'), 'bi-collection', data_br($data)) ?>
  <?= kpi('Divergências de marcação', number_format($qtdDiverg, 0, ',', '.'), 'bi-exclamation-triangle',
          'MaM × ref. > ' . number_format(LIMIAR_DIVERGENCIA * 100, 1, ',', '.') . '%') ?>
  <?= kpi('Precificados pelo Comitê', number_format($qtdComite, 0, ',', '.'), 'bi-people', 'CDB e ilíquidos') ?>
  <?= kpi('Feed do dia', number_format(count($feed), 0, ',', '.'), 'bi-broadcast', 'preços em ' . data_br($data)) ?>
</div>

<!-- Metodologia (cascata) -->
<div class="card mb-3 border-primary">
  <div class="card-header"><i class="bi bi-diagram-3 me-1"></i> Metodologia de precificação — cascata de fontes</div>
  <div class="card-body">
    <p class="mb-2" style="font-size:.86rem">
      A marcação a mercado segue uma <b>cascata de fontes</b>: usa-se sempre a fonte primária mais líquida e
      independente disponível para cada classe de ativo. Só se recorre ao Comitê quando não há fonte pública.
    </p>
    <div class="d-flex align-items-stretch gap-2 flex-wrap" style="font-size:.82rem">
      <div class="card flex-grow-1" style="min-width:200px">
        <div class="card-body py-2">
          <div><?= badge('1 · ANBIMA', 'primary') ?></div>
          <div class="mt-1"><b>Títulos públicos, debêntures, CRI/CRA.</b></div>
          <div class="text-muted">Preços de referência ANBIMA (curvas e mercado secundário de crédito).</div>
        </div>
      </div>
      <div class="d-flex align-items-center text-muted"><i class="bi bi-arrow-right"></i></div>
      <div class="card flex-grow-1" style="min-width:200px">
        <div class="card-body py-2">
          <div><?= badge('2 · B3', 'info') ?></div>
          <div class="mt-1"><b>Ações, ETF, cotas de fundo.</b></div>
          <div class="text-muted">Fechamento/preço médio da B3 para renda variável e cotas negociadas.</div>
        </div>
      </div>
      <div class="d-flex align-items-center text-muted"><i class="bi bi-arrow-right"></i></div>
      <div class="card flex-grow-1" style="min-width:200px">
        <div class="card-body py-2">
          <div><?= badge('3 · Comitê', 'warning') ?></div>
          <div class="mt-1"><b>CDB e ilíquidos sem fonte pública.</b></div>
          <div class="text-muted">Comitê de Precificação homologa o preço (marcação na curva / modelo), com ata.</div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <!-- Divergências MaM × referência -->
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-shield-exclamation me-1"></i>
        Divergências MaM × referência — <?= data_br($data) ?>
        <span class="text-muted" style="font-size:.75rem">(limiar <?= number_format(LIMIAR_DIVERGENCIA * 100, 1, ',', '.') ?>%)</span></div>
      <div class="card-body p-0" style="max-height:460px;overflow-y:auto">
        <table class="table table-hover align-middle mb-0" style="font-size:.82rem">
          <thead><tr>
            <th>Ativo</th><th>Fonte</th>
            <th class="text-end">MaM (marcado)</th><th class="text-end">Referência (feed)</th>
            <th class="text-end">Δ %</th><th>Situação</th>
          </tr></thead>
          <tbody>
          <?php foreach ($ativos as $a): ?>
            <tr class="<?= $a['divergente'] ? 'table-warning' : '' ?>">
              <td><b><?= e_html($a['codigo']) ?></b> <?= badge($a['tipo'], 'secondary') ?></td>
              <td><?= badge(e_html($a['fonte_preco'] ?? '—'), ($a['fonte_preco'] ?? '') === 'Comitê' ? 'warning' : 'light') ?></td>
              <td class="text-end"><?= number_format((float)$a['preco_mam'], 4, ',', '.') ?></td>
              <td class="text-end"><?= (float)$a['preco_referencia'] > 0 ? number_format((float)$a['preco_referencia'], 4, ',', '.') : '<span class="text-muted">—</span>' ?></td>
              <td class="text-end <?= $a['divergente'] ? 'fw-bold' : 'text-muted' ?>">
                <?= ((float)$a['preco_referencia'] > 0) ? number_format($a['div_pct'] * 100, 3, ',', '.') . '%' : '—' ?>
              </td>
              <td>
                <?php if ($a['divergente']): ?>
                  <?= badge('Divergência de marcação', 'danger') ?>
                <?php elseif ((float)$a['preco_referencia'] <= 0): ?>
                  <?= badge('Sem referência', 'secondary') ?>
                <?php else: ?>
                  <?= badge('Aderente', 'success') ?>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$ativos): ?><tr><td colspan="6" class="text-muted text-center py-3">Sem ativos na carteira para esta data.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php if ($qtdDiverg): ?>
        <div class="card-footer py-2 text-danger" style="font-size:.78rem">
          <i class="bi bi-exclamation-triangle me-1"></i>
          <b><?= $qtdDiverg ?></b> ativo(s) com marcação divergente da fonte — cenário de risco. Encaminhe ao Comitê para homologação.
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Comitê de Precificação -->
  <div class="col-lg-5">
    <div class="card h-100 border-warning">
      <div class="card-header"><i class="bi bi-people me-1"></i> Comitê de Precificação — homologar preço</div>
      <div class="card-body">
        <p class="text-muted mb-2" style="font-size:.78rem">
          Homologa o preço de um ativo sem fonte líquida (fonte <b>Comitê</b>) ou de uma divergência.
          Grava no feed <code>precos_mercado</code> (fonte Comitê) e, se marcado, alinha o <b>preço MaM</b> da carteira.
        </p>
        <form method="post">
          <?= csrf_campo() ?><input type="hidden" name="homologar" value="1">
          <input type="hidden" name="fundo_id" value="<?= $fid ?>"><input type="hidden" name="data" value="<?= e_html($data) ?>">
          <div class="mb-2">
            <label class="form-label mb-1" style="font-size:.78rem">Ativo</label>
            <select name="codigo" class="form-select form-select-sm" required>
              <option value="">— selecione —</option>
              <?php foreach ($ativos as $a):
                    $rot = $a['codigo'] . ($a['divergente'] ? '  ⚠ divergência' : (($a['fonte_preco'] ?? '') === 'Comitê' ? '  · Comitê' : '')); ?>
                <option value="<?= e_html($a['codigo']) ?>"><?= e_html($rot) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label mb-1" style="font-size:.78rem">Preço homologado</label>
            <input name="preco_homologado" class="form-control form-control-sm" placeholder="ex.: 1.234,560000" required>
          </div>
          <div class="mb-2">
            <input name="justificativa" class="form-control form-control-sm" placeholder="Justificativa / metodologia (marcação na curva, spread, etc.)">
          </div>
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="alinhar_mam" value="1" id="alinhar" checked>
            <label class="form-check-label" for="alinhar" style="font-size:.8rem">Alinhar preço MaM (marcado) ao homologado</label>
          </div>
          <button class="btn btn-warning btn-sm w-100" <?= $ativos ? '' : 'disabled' ?>>
            <i class="bi bi-check2-square me-1"></i>Homologar preço</button>
        </form>
      </div>
      <div class="card-footer py-2 text-muted" style="font-size:.72rem">
        <i class="bi bi-journal-text me-1"></i> Toda homologação gera ata (log) e trilha de auditoria.
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <!-- Feed do dia -->
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-broadcast me-1"></i> Feed de preços — <?= data_br($data) ?> (fonte independente)</div>
      <div class="card-body p-0" style="max-height:340px;overflow-y:auto">
        <table class="table table-sm table-hover mb-0" style="font-size:.8rem">
          <thead><tr><th>Código</th><th>Fonte</th><th class="text-end">Preço</th></tr></thead>
          <tbody>
          <?php foreach ($feed as $p): ?>
            <tr>
              <td><b><?= e_html($p['codigo']) ?></b></td>
              <td><?= badge(e_html($p['fonte']), $p['fonte'] === 'Comitê' ? 'warning' : ($p['fonte'] === 'B3' ? 'info' : 'primary')) ?></td>
              <td class="text-end"><?= number_format((float)$p['preco'], 6, ',', '.') ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$feed): ?><tr><td colspan="3" class="text-muted text-center py-3">Sem preços no feed para esta data.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Ata do Comitê -->
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-journal-check me-1"></i> Ata do Comitê de Precificação (log)</div>
      <div class="card-body p-0" style="max-height:340px;overflow-y:auto">
        <table class="table table-sm table-hover mb-0" style="font-size:.78rem">
          <thead><tr><th>Quando</th><th>Data ref.</th><th>Registro</th></tr></thead>
          <tbody>
          <?php foreach ($ataLog as $l): ?>
            <tr>
              <td class="text-muted text-nowrap"><?= isset($l['criado_em']) ? date('d/m H:i', strtotime($l['criado_em'])) : '—' ?></td>
              <td class="text-nowrap"><?= data_br($l['data_ref']) ?></td>
              <td class="text-muted"><?= e_html($l['mensagem']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$ataLog): ?><tr><td colspan="3" class="text-muted text-center py-3">Nenhuma homologação registrada para este fundo.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php page_end();
