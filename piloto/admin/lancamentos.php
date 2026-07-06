<?php
// Lançamentos & Ajustes — a controladoria corrige carteira/caixa e reprocessa a cota (inclusive retroativa)
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

$u = exigir_perfil('admin');
$msg = ''; $msgTipo = 'success';

$fundos = $pdo->query("SELECT * FROM fundos WHERE status='Ativo' ORDER BY pl_atual DESC")->fetchAll();
$fid = (int)($_GET['fundo_id'] ?? $_POST['fundo_id'] ?? ($fundos[0]['id'] ?? 0));
$fundoSel = null;
foreach ($fundos as $f) if ((int)$f['id'] === $fid) $fundoSel = $f;
if (!$fundoSel && $fundos) { $fundoSel = $fundos[0]; $fid = (int)$fundoSel['id']; }

$datas = datas_carteira($pdo, $fid);
$data = $_GET['data'] ?? $_POST['data'] ?? ($datas[0] ?? date('Y-m-d'));
if (!in_array($data, $datas, true) && $datas) $data = $datas[0];

// ---------------- AÇÕES ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ajuste de preço ou quantidade de um ativo do snapshot da data
    if (!empty($_POST['ajustar_ativo'])) {
        $st = $pdo->prepare('SELECT * FROM ativos_carteira WHERE id = ? AND fundo_id = ?');
        $st->execute([(int)$_POST['ajustar_ativo'], $fid]);
        if ($a = $st->fetch()) {
            $campo = $_POST['campo'] === 'quantidade' ? 'quantidade' : 'preco_mam';
            $novo = (float)str_replace(',', '.', $_POST['novo_valor'] ?? '0');
            if ($novo > 0) {
                $pdo->prepare("UPDATE ativos_carteira SET $campo = ? WHERE id = ?")->execute([$novo, $a['id']]);
                $pdo->prepare("INSERT INTO lancamentos (fundo_id, data_ref, tipo, ativo_codigo, descricao, valor_antigo, valor_novo, autor)
                               VALUES (?,?,?,?,?,?,?,?)")
                    ->execute([$fid, $a['data_ref'],
                               $campo === 'preco_mam' ? 'Ajuste de preço' : 'Correção de quantidade',
                               $a['codigo'], trim($_POST['justificativa'] ?? 'Ajuste da controladoria'),
                               $a[$campo], $novo, $u['nome']]);
                $msg = ($campo === 'preco_mam' ? 'Preço' : 'Quantidade') . " de {$a['codigo']} ajustado em " . data_br($a['data_ref']) .
                       '. Recalcule a cota para gerar nova prévia ao gestor.';
                $msgTipo = 'warning';
            }
        }
    }
    // lançamento de caixa (provento, taxa, correção)
    elseif (!empty($_POST['lancar_caixa'])) {
        $valor = (float)str_replace(['.', ','], ['', '.'], $_POST['valor_caixa'] ?? '0');
        $tipo = in_array($_POST['tipo_caixa'] ?? '', ['Movimentação de caixa', 'Provento', 'Taxa/Despesa', 'Evento corporativo'], true)
              ? $_POST['tipo_caixa'] : 'Movimentação de caixa';
        if (abs($valor) > 0.009) {
            $pdo->prepare("INSERT INTO lancamentos (fundo_id, data_ref, tipo, descricao, valor_caixa, autor) VALUES (?,?,?,?,?,?)")
                ->execute([$fid, $data, $tipo, trim($_POST['descricao_caixa'] ?? ''), $valor, $u['nome']]);
            $pdo->prepare("INSERT INTO movimentacoes (fundo_id, data_ref, tipo, descricao, valor) VALUES (?,?,?,?,?)")
                ->execute([$fid, $data, $tipo === 'Provento' ? 'Provento' : ($valor >= 0 ? 'Aplicação' : 'Taxa'),
                           '[Lançamento controladoria] ' . trim($_POST['descricao_caixa'] ?? ''), $valor]);
            $pdo->prepare('UPDATE fundos SET caixa_atual = caixa_atual + ? WHERE id = ?')->execute([$valor, $fid]);
            $fundoSel['caixa_atual'] += $valor;
            $msg = 'Lançamento de caixa registrado (' . moeda($valor) . '). Recalcule a cota para refletir na prévia.';
            $msgTipo = 'warning';
        }
    }
    // recalcular cota da data → nova versão de prévia ao gestor
    elseif (!empty($_POST['recalcular'])) {
        $calc = calcular_cota($pdo, $fundoSel, $data);
        if ($calc) {
            [$cota, $pl] = $calc;
            $st = $pdo->prepare('SELECT COALESCE(MAX(versao),0) FROM fechamentos WHERE fundo_id = ? AND data_ref = ?');
            $st->execute([$fid, $data]);
            $versao = (int)$st->fetchColumn() + 1;
            // versões anteriores aprovadas ficam marcadas como republicadas na trilha
            $pdo->prepare("UPDATE fechamentos SET status='Republicada', liberado_download=0
                           WHERE fundo_id=? AND data_ref=? AND status IN ('Aprovada','Reaberta')")->execute([$fid, $data]);
            $pdo->prepare("INSERT INTO fechamentos (fundo_id, data_ref, versao, valor_cota, pl, status, calculada_em, motivo)
                           VALUES (?,?,?,?,?,'Aguardando aprovação',NOW(),?)")
                ->execute([$fid, $data, $versao, $cota, $pl, 'Reprocessamento após lançamentos da controladoria']);
            $pdo->prepare("INSERT INTO log_processamento (fundo_id, data_ref, etapa, nivel, mensagem) VALUES (?,?,?,?,?)")
                ->execute([$fid, $data, 'Cota', 'INFO', "Cota reprocessada (v$versao) por " . $u['nome'] . ' após lançamentos']);
            $ehRetro = $data !== ($datas[0] ?? $data);
            $msg = "Cota de " . data_br($data) . " reprocessada (v$versao) e enviada ao gestor." .
                   ($ehRetro ? ' Por ser retroativa, na aprovação as cotas dos dias seguintes serão republicadas em cascata.' : '');
        } else { $msg = 'Não foi possível recalcular (sem snapshot ou sem cotas emitidas).'; $msgTipo = 'danger'; }
    }
}

$ativos = carteira($pdo, $fid, $data);
$totalAtivos = array_sum(array_column($ativos, 'valor_mercado'));
$fech = fechamento($pdo, $fid, $data);
$calcAtual = calcular_cota($pdo, $fundoSel, $data);

$st = $pdo->prepare('SELECT * FROM lancamentos WHERE fundo_id = ? ORDER BY criado_em DESC LIMIT 20');
$st->execute([$fid]);
$lancs = $st->fetchAll();

page_start('Lançamentos & Ajustes', 'Lançamentos & Ajustes', $u,
    'Correção de carteira e caixa com trilha completa · o reprocessamento gera nova prévia para o gestor aprovar');
?>

<?php if (isset($_GET['reaberto'])): ?>
  <div class="alert alert-warning py-2"><i class="bi bi-arrow-counterclockwise me-1"></i>
    Fechamento reaberto. Faça os ajustes necessários e clique em <b>Recalcular cota</b> para gerar a nova prévia.</div>
<?php endif; ?>
<?php if ($msg): ?><div class="alert alert-<?= $msgTipo ?> py-2"><i class="bi bi-info-circle me-1"></i><?= e_html($msg) ?></div><?php endif; ?>

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
      <span class="text-muted" style="font-size:.78rem">
        Fechamento: <?= $fech ? badge_status($fech['status']) . ' v' . $fech['versao'] : badge('Sem prévia', 'secondary') ?>
      </span>
      <?php if ($calcAtual): ?>
        <span class="ms-auto" style="font-size:.82rem">Cota recalculada agora:
          <b><?= number_format($calcAtual[0], 8, ',', '.') ?></b> · PL <b><?= moeda_compacta($calcAtual[1]) ?></b></span>
      <?php endif; ?>
    </form>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-pencil-square me-1"></i> Carteira de <?= data_br($data) ?> — clique para ajustar preço/quantidade</div>
      <div class="card-body p-0" style="max-height:430px;overflow-y:auto">
        <table class="table table-hover align-middle mb-0" style="font-size:.84rem">
          <thead><tr><th>Ativo</th><th class="text-end">Quantidade</th><th class="text-end">Preço MaM</th>
            <th class="text-end">Valor</th><th style="min-width:330px">Ajustar</th></tr></thead>
          <tbody>
          <?php foreach ($ativos as $a): ?>
            <tr>
              <td><b><?= e_html($a['codigo']) ?></b> <?= badge($a['tipo'], 'secondary') ?>
                <br><span class="text-muted" style="font-size:.72rem">fonte: <?= e_html($a['fonte_preco']) ?><?= $a['preco_referencia'] ? ' · ref: ' . number_format((float)$a['preco_referencia'], 4, ',', '.') : '' ?></span></td>
              <td class="text-end"><?= number_format((float)$a['quantidade'], 0, ',', '.') ?></td>
              <td class="text-end"><?= number_format((float)$a['preco_mam'], 4, ',', '.') ?></td>
              <td class="text-end"><?= moeda($a['valor_mercado']) ?></td>
              <td>
                <form method="post" class="d-flex gap-1">
                  <input type="hidden" name="ajustar_ativo" value="<?= (int)$a['id'] ?>">
                  <input type="hidden" name="fundo_id" value="<?= $fid ?>"><input type="hidden" name="data" value="<?= e_html($data) ?>">
                  <select name="campo" class="form-select form-select-sm" style="max-width:110px">
                    <option value="preco_mam">preço</option><option value="quantidade">qtde</option>
                  </select>
                  <input name="novo_valor" class="form-control form-control-sm" style="max-width:110px" placeholder="novo valor" required>
                  <input name="justificativa" class="form-control form-control-sm" placeholder="justificativa" required>
                  <button class="btn btn-sm btn-outline-dark"><i class="bi bi-check-lg"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot class="table-light"><tr>
            <th colspan="3">Ativos + caixa (<?= moeda_compacta($fundoSel['caixa_atual']) ?>)</th>
            <th class="text-end"><?= moeda($totalAtivos + (float)$fundoSel['caixa_atual']) ?></th><th></th>
          </tr></tfoot>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-4 d-flex flex-column gap-3">
    <div class="card">
      <div class="card-header"><i class="bi bi-wallet2 me-1"></i> Lançar no caixa (<?= data_br($data) ?>)</div>
      <div class="card-body">
        <form method="post">
          <input type="hidden" name="lancar_caixa" value="1">
          <input type="hidden" name="fundo_id" value="<?= $fid ?>"><input type="hidden" name="data" value="<?= e_html($data) ?>">
          <div class="mb-2">
            <select name="tipo_caixa" class="form-select form-select-sm">
              <option>Provento</option><option>Taxa/Despesa</option><option>Movimentação de caixa</option><option>Evento corporativo</option>
            </select>
          </div>
          <div class="mb-2">
            <input name="valor_caixa" class="form-control form-control-sm" placeholder="Valor (negativo = saída) ex.: -12.500,00" required>
          </div>
          <div class="mb-2">
            <input name="descricao_caixa" class="form-control form-control-sm" placeholder="Descrição (ex.: cupom NTN-B não capturado)" required>
          </div>
          <button class="btn btn-sm btn-dark w-100"><i class="bi bi-plus-lg me-1"></i>Lançar</button>
        </form>
      </div>
    </div>

    <div class="card border-success flex-grow-1">
      <div class="card-body text-center d-flex flex-column justify-content-center">
        <p class="mb-2" style="font-size:.84rem">Ajustes feitos? Gere a nova prévia:</p>
        <form method="post">
          <input type="hidden" name="recalcular" value="1">
          <input type="hidden" name="fundo_id" value="<?= $fid ?>"><input type="hidden" name="data" value="<?= e_html($data) ?>">
          <button class="btn btn-success w-100"><i class="bi bi-arrow-repeat me-1"></i>Recalcular cota de <?= data_br($data) ?></button>
        </form>
        <p class="text-muted mt-2 mb-0" style="font-size:.72rem">
          Gera a versão v<?= $fech ? (int)$fech['versao'] + 1 : 1 ?> e envia ao gestor.
          <?= $data !== ($datas[0] ?? '') ? '<b>Data retroativa:</b> na aprovação, as cotas posteriores são republicadas em cascata.' : '' ?>
        </p>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header"><i class="bi bi-journal-text me-1"></i> Trilha de lançamentos do fundo</div>
  <div class="card-body p-0" style="max-height:300px;overflow-y:auto">
    <table class="table table-sm table-hover mb-0" style="font-size:.8rem">
      <thead><tr><th>Quando</th><th>Data ref.</th><th>Tipo</th><th>Ativo</th><th>Descrição</th><th class="text-end">De → Para / Valor</th><th>Autor</th></tr></thead>
      <tbody>
      <?php foreach ($lancs as $l): ?>
        <tr>
          <td class="text-muted"><?= date('d/m H:i', strtotime($l['criado_em'])) ?></td>
          <td><?= data_br($l['data_ref']) ?></td>
          <td><?= badge($l['tipo'], 'info') ?></td>
          <td><?= e_html($l['ativo_codigo'] ?? '—') ?></td>
          <td class="text-muted"><?= e_html($l['descricao']) ?></td>
          <td class="text-end">
            <?php if ($l['valor_antigo'] !== null): ?>
              <?= number_format((float)$l['valor_antigo'], 4, ',', '.') ?> → <b><?= number_format((float)$l['valor_novo'], 4, ',', '.') ?></b>
            <?php elseif ($l['valor_caixa'] !== null): ?>
              <span class="<?= pct_color((float)$l['valor_caixa']) ?>"><?= moeda($l['valor_caixa']) ?></span>
            <?php endif; ?>
          </td>
          <td class="text-muted"><?= e_html($l['autor']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$lancs): ?><tr><td colspan="7" class="text-muted text-center py-3">Nenhum lançamento registrado.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php page_end();
