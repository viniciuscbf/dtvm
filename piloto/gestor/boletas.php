<?php
// Boletagem de operações — o gestor boleta a partir do CATÁLOGO de ativos, com
// enquadramento PRÉ-TRADE (art. 89) antes do envio; a custódia aceita e liquida (DVP).
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

$u = exigir_perfil('gestor', 'admin');
$fundo = fundo_do_usuario($pdo, $u);
if (!$fundo) die('Sem fundo vinculado.');
exigir_fundo_ativo($fundo);
$fid = (int)$fundo['id'];
exigir_permissao($pdo, $u, $fid, 'boletar');
ensure_catalogo($pdo);

$msg = ''; $msgTipo = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_validar()) {
    $_POST = []; $msg = 'Requisição inválida (proteção CSRF). Recarregue a página.'; $msgTipo = 'danger';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['boletar']) && !nonce_valido()) {
    $_POST = []; $msg = 'Boleta já enviada — envio duplicado ignorado.'; $msgTipo = 'warning';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['boletar'])) {
    $qtd = (float)str_replace(['.', ','], ['', '.'], $_POST['quantidade'] ?? '0');
    $preco = (float)str_replace(['.', ','], ['', '.'], $_POST['preco'] ?? '0');
    $codigo = strtoupper(trim($_POST['ativo_codigo'] ?? ''));
    $operacao = ($_POST['operacao'] ?? 'Compra') === 'Venda' ? 'Venda' : 'Compra';

    // 1) o ativo PRECISA estar no catálogo (não se boleta ativo fora da lista)
    $st = $pdo->prepare("SELECT * FROM ativos_catalogo WHERE codigo = ? AND status = 'Ativo'");
    $st->execute([$codigo]);
    $cat = $st->fetch();

    if ($qtd <= 0 || $preco <= 0 || $codigo === '') {
        $msg = 'Informe ativo, quantidade e preço válidos.'; $msgTipo = 'danger';
    } elseif (!$cat) {
        $msg = "O ativo \"$codigo\" não está no catálogo. Solicite o cadastro em Catálogo de ativos antes de boletar.";
        $msgTipo = 'danger';
    } else {
        $tipo = $cat['tipo'];

        if ($tipo === 'Derivativo') {
            // Futuro: boletado como qualquer instrumento, mas ABRE POSIÇÃO (sem DVP do principal).
            // "quantidade" = nº de contratos; "preço" = taxa a.a. % (DI1/DAP) OU preço do contrato
            // (DOL/IND) conforme a mecânica; Compra = comprado / Venda = vendido; venc vem do contrato.
            ensure_derivativos($pdo);
            [$mec, $ponto, $fator] = deriv_perfil($codigo);
            $contratos  = (int) $qtd;
            $compradoPu = $operacao === 'Compra';
            $venc       = $cat['vencimento'];
            $dataRef    = ultima_data_carteira($pdo, $fid) ?: date('Y-m-d');
            if ($contratos <= 0) {
                $msg = 'Informe o nº de contratos (no campo Quantidade).'; $msgTipo = 'danger';
            } elseif (!$venc || $venc <= $dataRef) {
                $msg = 'Este contrato já venceu — escolha um vencimento futuro.'; $msgTipo = 'danger';
            } elseif ($preco <= 0) {
                $msg = $mec === 'PU' ? 'Informe a taxa a.a. (%) no campo de preço.' : 'Informe o preço do contrato.'; $msgTipo = 'danger';
            } else {
                $anosVenc = max(0.0, (strtotime($venc) - strtotime($dataRef)) / (365 * 86400));
                $margem   = round($contratos * (150 + 120 * $anosVenc), 2);   // margem cresce com o prazo
                $did = abrir_derivativo($pdo, $fid, $codigo, $venc, $contratos, $compradoPu, $preco, $margem, $dataRef);
                $refTxt = $mec === 'PU' ? ('em PU a ' . number_format($preco, 2, ',', '.') . '% a.a.')
                                        : ('ao preço ' . number_format($preco, 2, ',', '.'));
                $pdo->prepare("INSERT INTO boletas (fundo_id, data_operacao, operacao, ativo_codigo, tipo_ativo, quantidade, preco, valor, contraparte, status, criado_por)
                               VALUES (?,?,?,?,?,?,?,?,?, 'Liquidada', ?)")
                    ->execute([$fid, ($_POST['data_operacao'] ?? '') ?: $dataRef, $operacao, $codigo, 'Derivativo',
                               $contratos, $preco, $margem, trim($_POST['contraparte'] ?? ''), $u['nome']]);
                registrar_auditoria($pdo, 'derivativo_aberto', ['entidade' => 'derivativo', 'entidade_id' => $did, 'fundo_id' => $fid,
                    'detalhe' => "Boleta $codigo — $contratos contrato(s) " . ($compradoPu ? 'comprado' : 'vendido') . " $refTxt"]);
                $msg = "Posição em $codigo aberta: $contratos contrato(s) " . ($compradoPu ? 'comprado' : 'vendido') .
                       " $refTxt — margem " . moeda($margem) . '. Aparece na carteira; o ajuste diário roda no passar de dia.';
            }
        } else {
            $valor = $qtd * $preco;

            // 2) venda: precisa ter posição suficiente
            if ($operacao === 'Venda') {
                $st = $pdo->prepare('SELECT quantidade FROM ativos_carteira WHERE fundo_id = ? AND codigo = ? AND data_ref = ?');
                $st->execute([$fid, $codigo, ultima_data_carteira($pdo, $fid)]);
                $posAtual = (float)($st->fetchColumn() ?: 0);
                if ($posAtual < $qtd) {
                    $msg = "Venda maior que a posição atual de $codigo (" . number_format($posAtual, 0, ',', '.') . ' un.).';
                    $msgTipo = 'danger';
                }
            }

            // 3) enquadramento PRÉ-TRADE (barra a boleta se violar o mandato)
            if ($msgTipo !== 'danger') {
                $pt = checar_pre_trade($pdo, $fundo, $tipo, $operacao, $valor);
                if (!$pt['ok']) {
                    $msg = 'Boleta barrada no pré-trade (enquadramento): ' . implode(' · ', $pt['violacoes']);
                    $msgTipo = 'danger';
                }
            }

            if ($msgTipo !== 'danger') {
                com_transacao($pdo, function () use ($pdo, $fid, $operacao, $codigo, $tipo, $qtd, $preco, $valor, $u) {
                    $pdo->prepare("INSERT INTO boletas (fundo_id, data_operacao, operacao, ativo_codigo, tipo_ativo, quantidade, preco, valor, contraparte, criado_por)
                                   VALUES (?,?,?,?,?,?,?,?,?,?)")
                        ->execute([$fid, ($_POST['data_operacao'] ?? '') ?: date('Y-m-d'), $operacao, $codigo, $tipo,
                                   $qtd, $preco, $valor, trim($_POST['contraparte'] ?? ''), $u['nome']]);
                });
                registrar_auditoria($pdo, 'boleta_enviada', ['entidade' => 'boleta', 'fundo_id' => $fid,
                    'detalhe' => "$operacao de $codigo — " . moeda($valor) . ' (pré-trade OK)']);
                $msg = "Boleta de $operacao de $codigo enviada à mesa de custódia (pré-trade OK) — ela valida, liquida (DVP) e a posição entra na carteira.";
            }
        }
    }
}

$catalogo = $pdo->query("SELECT * FROM ativos_catalogo WHERE status='Ativo' ORDER BY tipo, codigo")->fetchAll();

$st = $pdo->prepare('SELECT * FROM boletas WHERE fundo_id = ? ORDER BY criado_em DESC LIMIT 30');
$st->execute([$fid]);
$boletas = $st->fetchAll();
$posicao = carteira($pdo, $fid);

page_start('Boletar operação', 'Boletar operação', $u,
    e_html($fundo['nome']) . ' · boleta a partir do catálogo, com enquadramento pré-trade, segue para a custódia (DVP)');
?>

<?php if ($msg): ?><div class="alert alert-<?= $msgTipo ?> py-2"><i class="bi bi-info-circle me-1"></i><?= e_html($msg) ?></div><?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-lg-5">
    <div class="card">
      <div class="card-header"><i class="bi bi-receipt-cutoff me-1"></i> Nova boleta</div>
      <div class="card-body">
        <form method="post">
          <?= csrf_campo() ?><?= nonce_campo() ?>
          <input type="hidden" name="boletar" value="1">
          <div class="row g-2">
            <div class="col-6"><label class="form-label" style="font-size:.78rem">Operação</label>
              <select class="form-select form-select-sm" name="operacao"><option>Compra</option><option>Venda</option></select></div>
            <div class="col-6"><label class="form-label" style="font-size:.78rem">Data da operação</label>
              <input type="date" class="form-control form-control-sm" name="data_operacao" value="<?= date('Y-m-d') ?>"></div>
            <div class="col-12"><label class="form-label" style="font-size:.78rem">Ativo (do catálogo) *</label>
              <select class="form-select form-select-sm" name="ativo_codigo" required>
                <option value="">— selecione um instrumento cadastrado —</option>
                <?php $tipoAtual = ''; foreach ($catalogo as $c):
                    if ($c['tipo'] !== $tipoAtual) { if ($tipoAtual !== '') echo '</optgroup>'; echo '<optgroup label="' . e_html($c['tipo']) . '">'; $tipoAtual = $c['tipo']; } ?>
                  <option value="<?= e_html($c['codigo']) ?>" data-tipo="<?= e_html($c['tipo']) ?>" data-venc="<?= e_html($c['vencimento']) ?>" data-mec="<?= $c['tipo'] === 'Derivativo' ? e_html(deriv_perfil($c['codigo'])[0]) : '' ?>"><?= e_html($c['codigo'] . ' · ' . $c['nome']) ?></option>
                <?php endforeach; if ($tipoAtual !== '') echo '</optgroup>'; ?>
              </select>
              <span class="text-muted" style="font-size:.7rem">Não achou o ativo? <a href="ativos.php">Solicite o cadastro no catálogo</a> — não se boleta ativo fora da lista.</span></div>
            <div class="col-6"><label class="form-label" id="lbl-qtd" style="font-size:.78rem">Quantidade *</label>
              <input class="form-control form-control-sm" name="quantidade" required placeholder="500"></div>
            <div class="col-6"><label class="form-label" id="lbl-preco" style="font-size:.78rem">Preço unitário (R$) *</label>
              <input class="form-control form-control-sm" name="preco" required placeholder="1.120,50"></div>
            <div class="col-12" id="deriv-hint" style="display:none">
              <div class="alert alert-info py-1 px-2 mb-0" style="font-size:.72rem"><i class="bi bi-info-circle me-1"></i>
                Futuro: <b>Compra</b> = comprado em PU (aposta que os juros caem); <b>Venda</b> = vendido. Sem desembolso do
                principal — deposita-se margem de garantia e há ajuste diário em caixa. O vencimento vem do contrato.</div>
            </div>
            <div class="col-12"><label class="form-label" style="font-size:.78rem">Contraparte</label>
              <input class="form-control form-control-sm" name="contraparte" placeholder="Ex.: XP CTVM, Banco Métrica (emissor)"></div>
          </div>
          <button class="btn btn-dark btn-sm w-100 mt-3"><i class="bi bi-send me-1"></i>Validar (pré-trade) e enviar à custódia</button>
        </form>
        <p class="text-muted mb-0 mt-2" style="font-size:.72rem">
          Ciclo real: seleção no catálogo → <b>enquadramento pré-trade</b> (art. 89) → boleta → aceite da mesa de custódia
          (instrução D+1/D+2) → liquidação DVP → ativo entra na carteira pelo preço médio → a cota do dia reflete.</p>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-pie-chart me-1"></i> Posição atual (para conferência)</div>
      <div class="card-body p-0" style="max-height:380px;overflow-y:auto">
        <table class="table table-sm table-hover mb-0" style="font-size:.8rem">
          <thead><tr><th>Ativo</th><th>Tipo</th><th class="text-end">Quantidade</th><th class="text-end">Preço médio</th><th class="text-end">Valor</th></tr></thead>
          <tbody>
          <?php foreach ($posicao as $a): ?>
            <tr>
              <td><b><?= e_html($a['codigo']) ?></b></td>
              <td><?= badge($a['tipo'], 'secondary') ?></td>
              <td class="text-end"><?= number_format((float)$a['quantidade'], 0, ',', '.') ?></td>
              <td class="text-end"><?= number_format((float)$a['preco_medio'], 4, ',', '.') ?></td>
              <td class="text-end"><?= moeda($a['valor_mercado']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header"><i class="bi bi-list-check me-1"></i> Minhas boletas</div>
  <div class="card-body p-0">
    <table class="table table-hover align-middle mb-0" style="font-size:.84rem">
      <thead><tr><th>Enviada</th><th>Operação</th><th class="text-end">Qtde × Preço</th><th class="text-end">Financeiro</th>
        <th>Contraparte</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($boletas as $b): ?>
        <tr>
          <td class="text-muted" style="font-size:.78rem"><?= date('d/m H:i', strtotime($b['criado_em'])) ?><br>
            <span style="font-size:.7rem">op. <?= data_br($b['data_operacao']) ?></span></td>
          <td><?= badge($b['operacao'], $b['operacao'] === 'Compra' ? 'warning' : 'success') ?>
            <b><?= e_html($b['ativo_codigo']) ?></b> <span class="text-muted" style="font-size:.72rem">(<?= e_html($b['tipo_ativo']) ?>)</span></td>
          <td class="text-end"><?= number_format((float)$b['quantidade'], 0, ',', '.') ?> × <?= number_format((float)$b['preco'], 4, ',', '.') ?></td>
          <td class="text-end"><b><?= moeda($b['valor']) ?></b></td>
          <td style="font-size:.8rem"><?= e_html($b['contraparte']) ?></td>
          <td><?= badge($b['status'], ['Enviada' => 'warning', 'Aceita' => 'info', 'Liquidada' => 'success', 'Rejeitada' => 'danger'][$b['status']] ?? 'secondary') ?>
            <?= $b['status'] === 'Rejeitada' && $b['motivo'] ? '<br><span class="text-danger" style="font-size:.72rem">' . e_html($b['motivo']) . '</span>' : '' ?>
            <?= $b['status'] === 'Enviada' ? '<br><span class="text-muted" style="font-size:.7rem">aguardando aceite da custódia</span>' : '' ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$boletas): ?><tr><td colspan="6" class="text-muted text-center py-4">Nenhuma boleta enviada ainda.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<script>
(function () {
  var sel = document.querySelector('select[name="ativo_codigo"]');
  var lq = document.getElementById('lbl-qtd'), lp = document.getElementById('lbl-preco'), hint = document.getElementById('deriv-hint');
  if (!sel || !lq || !lp || !hint) return;
  function upd() {
    var o = sel.options[sel.selectedIndex];
    var deriv = o && o.getAttribute('data-tipo') === 'Derivativo';
    lq.textContent = deriv ? 'Nº de contratos *' : 'Quantidade *';
    lp.textContent = deriv ? (o.getAttribute('data-mec') === 'PRECO' ? 'Preço do contrato *' : 'Taxa a.a. (%) *') : 'Preço unitário (R$) *';
    hint.style.display = deriv ? 'block' : 'none';
  }
  sel.addEventListener('change', upd); upd();
})();
</script>
<?php page_end();
