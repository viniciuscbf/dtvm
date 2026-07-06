<?php
// FIP — Fundo de Investimento em Participações (Private Equity).
// Painel da administradora fiduciária: LPs / capital comprometido, chamadas de
// capital (drawdowns), participações em investidas marcadas por laudo (valor
// justo, CPC 46 nível 3), dividendos/JCP, desinvestimentos e distribuição com
// waterfall (retorno de capital → preferencial ~8% → carry 20%).
// PL do FIP = caixa + Σ valor justo das participações ativas (não usa ativos_carteira).
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

ensure_fip($pdo);   // DDL (commit implícito) — sempre FORA de transação

$u = exigir_perfil('admin');
$msg = ''; $msgTipo = 'success';

// ---------- Seletor de fundo ----------
$fundos = $pdo->query("SELECT * FROM fundos WHERE status='Ativo' ORDER BY pl_atual DESC")->fetchAll();
if (isset($_GET['fundo_id'])) $_SESSION['admin_fundo_id'] = (int)$_GET['fundo_id'];
$fid = (int)($_SESSION['admin_fundo_id'] ?? ($fundos[0]['id'] ?? 0));
$fundo = null;
foreach ($fundos as $f) if ((int)$f['id'] === $fid) $fundo = $f;
if (!$fundo && $fundos) { $fundo = $fundos[0]; $fid = (int)$fundo['id']; }

// Métodos de laudo aceitos (CPC 46 / valor justo nível 3)
const FIP_METODOS = ['DCF', 'Múltiplos', 'Última rodada', 'Custo'];

// Data de referência das operações (última data da carteira, senão hoje)
$data = $fundo ? (ultima_data_carteira($pdo, $fid) ?: date('Y-m-d')) : date('Y-m-d');

// ---------- Proteções de POST ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_validar()) {
    $_POST = [];
    $msg = 'Requisição inválida (proteção CSRF). Recarregue a página.'; $msgTipo = 'danger';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !nonce_valido()) {
    $_POST = [];
    $msg = 'Ação já processada — envio duplicado ignorado.'; $msgTipo = 'warning';
}

// Conversor de moeda "1.234.567,89" → float
$paraFloat = static function ($s): float {
    return (float)str_replace(['.', ','], ['', '.'], (string)$s);
};

// ---------- Ações ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $fundo) {
    try {
        if (!empty($_POST['converter_fip'])) {
            $pdo->prepare("UPDATE fundos SET tipo_fundo='FIP' WHERE id=?")->execute([$fid]);
            $fundo['tipo_fundo'] = 'FIP';
            registrar_auditoria($pdo, 'fip_converter', [
                'entidade' => 'fundo', 'entidade_id' => $fid, 'fundo_id' => $fid,
                'detalhe' => 'Fundo convertido para tipo FIP (Private Equity)',
            ]);
            $msg = 'Fundo convertido para FIP.';

        } elseif (!empty($_POST['comprometer'])) {
            $nome = trim($_POST['nome'] ?? '');
            $doc  = trim($_POST['documento'] ?? '');
            $comp = $paraFloat($_POST['comprometido'] ?? '0');
            if ($nome === '' || $comp <= 0) {
                $msg = 'Informe o nome do LP e o capital comprometido.'; $msgTipo = 'warning';
            } else {
                $lpId = fip_comprometer($pdo, $fid, $nome, $doc, $comp);
                registrar_auditoria($pdo, 'fip_comprometer', [
                    'entidade' => 'fip_lp', 'entidade_id' => $lpId, 'fundo_id' => $fid,
                    'detalhe' => "LP \"$nome\" comprometeu " . moeda($comp),
                ]);
                $msg = 'Compromisso do LP "' . $nome . '" registrado.';
            }

        } elseif (!empty($_POST['emitir_chamada'])) {
            $perc  = (float)str_replace(',', '.', $_POST['percentual'] ?? '0');
            $prazo = $_POST['prazo'] ?? '';
            if ($perc <= 0) {
                $msg = 'Informe um percentual de chamada maior que zero.'; $msgTipo = 'warning';
            } elseif ($prazo === '') {
                $msg = 'Informe o prazo (data-limite) da chamada.'; $msgTipo = 'warning';
            } else {
                $chId = fip_emitir_chamada($pdo, $fid, $perc, $data, $prazo);
                registrar_auditoria($pdo, 'fip_emitir_chamada', [
                    'entidade' => 'fip_chamada', 'entidade_id' => $chId, 'fundo_id' => $fid,
                    'detalhe' => 'Chamada de capital de ' . number_format($perc, 4, ',', '.') . '% (prazo ' . data_br($prazo) . ')',
                ]);
                $msg = 'Chamada de capital emitida.';
            }

        } elseif (!empty($_POST['integralizar'])) {
            $clId = (int)($_POST['chamada_lp_id'] ?? 0);
            fip_integralizar($pdo, $clId, $data);
            registrar_auditoria($pdo, 'fip_integralizar', [
                'entidade' => 'fip_chamada_lp', 'entidade_id' => $clId, 'fundo_id' => $fid,
                'detalhe' => 'Parcela de chamada integralizada (entrada de caixa / emissão de cotas)',
            ]);
            $msg = 'Parcela integralizada.';

        } elseif (!empty($_POST['inadimplir'])) {
            $clId = (int)($_POST['chamada_lp_id'] ?? 0);
            fip_inadimplir($pdo, $clId);
            registrar_auditoria($pdo, 'fip_inadimplir', [
                'entidade' => 'fip_chamada_lp', 'entidade_id' => $clId, 'fundo_id' => $fid,
                'detalhe' => 'Parcela de chamada marcada como inadimplente',
            ]);
            $msg = 'Parcela marcada como inadimplente.';

        } elseif (!empty($_POST['investir'])) {
            $empresa = trim($_POST['empresa'] ?? '');
            $setor   = trim($_POST['setor'] ?? '');
            $perc    = (float)str_replace(',', '.', $_POST['participacao'] ?? '0');
            $custo   = $paraFloat($_POST['custo'] ?? '0');
            if ($empresa === '' || $custo <= 0) {
                $msg = 'Informe a empresa e o custo de aquisição.'; $msgTipo = 'warning';
            } else {
                $pId = fip_investir($pdo, $fid, $empresa, $setor, $perc, $custo, $data);
                registrar_auditoria($pdo, 'fip_investir', [
                    'entidade' => 'fip_participacao', 'entidade_id' => $pId, 'fundo_id' => $fid,
                    'detalhe' => "Investimento em $empresa ($setor) — " . number_format($perc, 4, ',', '.') . '% por ' . moeda($custo),
                ]);
                $msg = 'Participação em "' . $empresa . '" registrada.';
            }

        } elseif (!empty($_POST['reavaliar'])) {
            $pId    = (int)($_POST['participacao_id'] ?? 0);
            $novo   = $paraFloat($_POST['novo_valor'] ?? '0');
            $metodo = in_array($_POST['metodo'] ?? '', FIP_METODOS, true) ? $_POST['metodo'] : 'Custo';
            $laudo  = trim($_POST['laudo'] ?? '');
            if ($novo <= 0) {
                $msg = 'Informe o novo valor justo do laudo.'; $msgTipo = 'warning';
            } else {
                fip_reavaliar($pdo, $pId, $novo, $metodo, $laudo, $data);
                registrar_auditoria($pdo, 'fip_reavaliar', [
                    'entidade' => 'fip_participacao', 'entidade_id' => $pId, 'fundo_id' => $fid,
                    'detalhe' => "Reavaliação (laudo, $metodo) — valor justo " . moeda($novo),
                ]);
                $msg = 'Participação reavaliada por laudo.';
            }

        } elseif (!empty($_POST['dividendo'])) {
            $pId   = (int)($_POST['participacao_id'] ?? 0);
            $valor = $paraFloat($_POST['valor'] ?? '0');
            if ($valor <= 0) {
                $msg = 'Informe o valor do dividendo/JCP.'; $msgTipo = 'warning';
            } else {
                fip_dividendo($pdo, $pId, $valor, $data);
                registrar_auditoria($pdo, 'fip_dividendo', [
                    'entidade' => 'fip_participacao', 'entidade_id' => $pId, 'fundo_id' => $fid,
                    'detalhe' => 'Dividendo/JCP recebido de investida — ' . moeda($valor),
                ]);
                $msg = 'Dividendo/JCP registrado (entrada de caixa).';
            }

        } elseif (!empty($_POST['vender'])) {
            $pId   = (int)($_POST['participacao_id'] ?? 0);
            $valor = $paraFloat($_POST['valor_venda'] ?? '0');
            if ($valor <= 0) {
                $msg = 'Informe o valor da venda.'; $msgTipo = 'warning';
            } else {
                $r = fip_vender($pdo, $pId, $valor, $data);
                registrar_auditoria($pdo, 'fip_vender', [
                    'entidade' => 'fip_participacao', 'entidade_id' => $pId, 'fundo_id' => $fid,
                    'detalhe' => "Desinvestimento de {$r['empresa']} por " . moeda($valor) . ' (ganho ' . moeda($r['ganho']) . ')',
                ]);
                $msg = 'Desinvestimento de "' . $r['empresa'] . '" — ganho realizado ' . moeda($r['ganho']) . '.';
            }

        } elseif (!empty($_POST['distribuir'])) {
            $valor = $paraFloat($_POST['valor_distribuir'] ?? '0');
            if ($valor <= 0) {
                $msg = 'Informe o valor a distribuir.'; $msgTipo = 'warning';
            } else {
                $r = fip_distribuir($pdo, $fid, $valor, $data);
                registrar_auditoria($pdo, 'fip_distribuir', [
                    'entidade' => 'fundo', 'entidade_id' => $fid, 'fundo_id' => $fid,
                    'detalhe' => 'Distribuição ' . moeda($valor) . ' — capital ' . moeda($r['retornoCapital']) .
                                 ' / LPs ' . moeda($r['retornoLps']) . ' / carry ' . moeda($r['carry']),
                ]);
                $msg = 'Distribuição realizada — retorno de capital ' . moeda($r['retornoCapital']) .
                       ', LPs ' . moeda($r['retornoLps']) . ', carry ' . moeda($r['carry']) . '.';
            }
        }
    } catch (Throwable $e) {
        $msg = 'Não foi possível concluir: ' . $e->getMessage(); $msgTipo = 'danger';
    }
}

// ---------- Dados ----------
$lps = $chamadas = $chamadaLps = $participacoes = $distribuicoes = [];
$totComprometido = $totIntegralizado = $valorJusto = 0.0;
$investidasAtivas = 0;

if ($fundo) {
    $st = $pdo->prepare("SELECT * FROM fip_lps WHERE fundo_id=? ORDER BY id DESC");
    $st->execute([$fid]);
    $lps = $st->fetchAll();
    foreach ($lps as $lp) {
        $totComprometido  += (float)$lp['capital_comprometido'];
        $totIntegralizado += (float)$lp['capital_integralizado'];
    }

    $st = $pdo->prepare("SELECT * FROM fip_chamadas WHERE fundo_id=? ORDER BY id DESC");
    $st->execute([$fid]);
    $chamadas = $st->fetchAll();

    // parcelas por chamada, com nome do LP
    $st = $pdo->prepare("SELECT cl.*, l.nome AS lp_nome
                         FROM fip_chamada_lp cl JOIN fip_lps l ON l.id = cl.lp_id
                         WHERE cl.fundo_id=? ORDER BY cl.chamada_id DESC, cl.id ASC");
    $st->execute([$fid]);
    foreach ($st->fetchAll() as $cl) {
        $chamadaLps[(int)$cl['chamada_id']][] = $cl;
    }

    $st = $pdo->prepare("SELECT * FROM fip_participacoes WHERE fundo_id=? ORDER BY (status='Vendida'), id DESC");
    $st->execute([$fid]);
    $participacoes = $st->fetchAll();
    foreach ($participacoes as $p) {
        if (($p['status'] ?? '') === 'Ativa') $investidasAtivas++;
    }

    $st = $pdo->prepare("SELECT * FROM fip_distribuicoes WHERE fundo_id=? ORDER BY id DESC");
    $st->execute([$fid]);
    $distribuicoes = $st->fetchAll();

    $valorJusto = valor_participacoes_fip($pdo, $fid);
}
$pctChamado = $totComprometido > 0 ? ($totIntegralizado / $totComprometido * 100) : 0.0;
$tipoAtual  = $fundo['tipo_fundo'] ?? 'FIF';
$ehFip      = ($tipoAtual === 'FIP');

page_start('FIP / Private Equity', 'FIP / Private Equity', $u,
    'Fundo de Investimento em Participações — LPs, chamadas de capital, laudos e waterfall');
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
  <?php if ($fundo): ?>
  <div style="font-size:.82rem" class="text-muted">
    Tipo do fundo: <?= badge($tipoAtual, $ehFip ? 'success' : 'secondary') ?> ·
    Data de referência: <?= data_br($data) ?>
  </div>
  <?php endif; ?>
</div>

<?php if (!$fundo): ?>
  <div class="alert alert-warning py-2">Nenhum fundo ativo disponível.</div>
<?php else: ?>

<!-- 1. Configuração -->
<div class="card mb-4">
  <div class="card-header"><i class="bi bi-gear me-1"></i> Configuração do fundo</div>
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div style="font-size:.85rem">
        Tipo atual: <?= badge($tipoAtual, $ehFip ? 'success' : 'secondary') ?>
        <?php if (!$ehFip): ?>
          <span class="text-muted">— converta o fundo para operar como Private Equity.</span>
        <?php else: ?>
          <span class="text-muted">— o fundo já opera como FIP.</span>
        <?php endif; ?>
      </div>
      <?php if (!$ehFip): ?>
      <form method="post" onsubmit="return confirm('Converter este fundo para o tipo FIP?')">
        <?= csrf_campo() ?><?= nonce_campo() ?>
        <button name="converter_fip" value="1" class="btn btn-sm btn-dark"><i class="bi bi-arrow-repeat me-1"></i>Converter fundo em FIP</button>
      </form>
      <?php endif; ?>
    </div>
    <div class="alert alert-info py-2 mb-0 mt-3" style="font-size:.82rem">
      <b>PL do FIP</b> = caixa do fundo + Σ <b>valor justo</b> das participações ativas. O FIP <b>não</b> usa a carteira
      de ativos líquidos (<code>ativos_carteira</code>); o valor das investidas vem do <b>laudo</b> registrado aqui.
    </div>
  </div>
</div>

<!-- 2. KPIs -->
<div class="row row-cols-2 row-cols-md-3 row-cols-xl-5 g-3 mb-4">
  <?= kpi('Capital comprometido', moeda_compacta($totComprometido), 'bi-hand-thumbs-up', 'total dos LPs') ?>
  <?= kpi('Capital integralizado', moeda_compacta($totIntegralizado), 'bi-cash-coin', 'já aportado') ?>
  <?= kpi('% chamado', number_format($pctChamado, 1, ',', '.') . '%', 'bi-percent', 'integralizado / comprometido') ?>
  <?= kpi('Valor justo participações', moeda_compacta($valorJusto), 'bi-buildings', 'CPC 46 nível 3') ?>
  <?= kpi('Investidas ativas', (string)$investidasAtivas, 'bi-diagram-3', 'participações vivas') ?>
</div>

<div class="row g-3 mb-4">
  <!-- 3. Limited Partners -->
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-people me-1"></i> Novo Limited Partner (compromisso)</div>
      <div class="card-body">
        <form method="post">
          <?= csrf_campo() ?><?= nonce_campo() ?>
          <label class="form-label" style="font-size:.8rem">Nome do LP</label>
          <input name="nome" class="form-control form-control-sm mb-2" placeholder="Ex.: Fundo de Pensão XYZ" required>
          <label class="form-label" style="font-size:.8rem">Documento (CNPJ/CPF)</label>
          <input name="documento" class="form-control form-control-sm mb-2" placeholder="00.000.000/0001-00">
          <label class="form-label" style="font-size:.8rem">Capital comprometido (R$)</label>
          <input name="comprometido" class="form-control form-control-sm mb-3" placeholder="10.000.000,00" required>
          <button name="comprometer" value="1" class="btn btn-sm btn-dark w-100"><i class="bi bi-check-lg me-1"></i>Registrar compromisso</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-people me-1"></i> Limited Partners</div>
      <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0" style="font-size:.83rem">
          <thead><tr>
            <th>LP</th><th>Documento</th><th class="text-end">Comprometido</th>
            <th class="text-end">Integralizado</th><th class="text-end">% integr.</th><th class="text-center">Status</th>
          </tr></thead>
          <tbody>
          <?php foreach ($lps as $lp):
            $c = (float)$lp['capital_comprometido']; $i = (float)$lp['capital_integralizado'];
            $pc = $c > 0 ? $i / $c * 100 : 0; ?>
            <tr>
              <td><b><?= e_html($lp['nome']) ?></b></td>
              <td><?= e_html($lp['documento'] ?: '—') ?></td>
              <td class="text-end"><?= moeda($c) ?></td>
              <td class="text-end"><?= moeda($i) ?></td>
              <td class="text-end"><?= number_format($pc, 1, ',', '.') ?>%</td>
              <td class="text-center"><?= badge_status($lp['status'] ?? 'Ativo') ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$lps): ?>
            <tr><td colspan="6" class="text-muted text-center py-4">Nenhum LP cadastrado — registre o primeiro compromisso ao lado.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- 4. Chamadas de capital -->
<div class="row g-3 mb-4">
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-megaphone me-1"></i> Nova chamada de capital</div>
      <div class="card-body">
        <?php if (!$lps): ?>
          <div class="alert alert-warning py-2 mb-0" style="font-size:.82rem">Cadastre ao menos um LP antes de emitir chamadas.</div>
        <?php else: ?>
        <form method="post">
          <?= csrf_campo() ?><?= nonce_campo() ?>
          <label class="form-label" style="font-size:.8rem">Percentual do comprometido (%)</label>
          <input name="percentual" class="form-control form-control-sm mb-2" placeholder="25" required>
          <label class="form-label" style="font-size:.8rem">Prazo (data-limite)</label>
          <input type="date" name="prazo" class="form-control form-control-sm mb-3" required>
          <button name="emitir_chamada" value="1" class="btn btn-sm btn-dark w-100"><i class="bi bi-send me-1"></i>Emitir chamada (pro-rata)</button>
        </form>
        <div class="text-muted mt-2" style="font-size:.72rem">A chamada é rateada <b>pro-rata</b> do capital comprometido de cada LP.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-megaphone me-1"></i> Chamadas emitidas</div>
      <div class="card-body p-0">
        <?php if (!$chamadas): ?>
          <div class="text-muted text-center py-4" style="font-size:.83rem">Nenhuma chamada emitida.</div>
        <?php else: ?>
          <?php foreach ($chamadas as $ch): ?>
            <div class="px-3 py-2 border-bottom">
              <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div style="font-size:.83rem">
                  <b><?= number_format((float)$ch['percentual'], 4, ',', '.') ?>%</b> ·
                  total <?= moeda($ch['valor_total']) ?> ·
                  chamada <?= data_br($ch['data_chamada']) ?> · prazo <?= data_br($ch['prazo']) ?>
                </div>
                <span><?= badge_status($ch['status'] ?? 'Aberta') ?></span>
              </div>
              <table class="table table-sm align-middle mb-0 mt-2" style="font-size:.8rem">
                <thead><tr><th>LP</th><th class="text-end">Valor</th><th class="text-center">Status</th><th class="text-end"></th></tr></thead>
                <tbody>
                <?php foreach (($chamadaLps[(int)$ch['id']] ?? []) as $cl): ?>
                  <tr>
                    <td><?= e_html($cl['lp_nome']) ?></td>
                    <td class="text-end"><?= moeda($cl['valor']) ?></td>
                    <td class="text-center"><?= badge_status($cl['status'] ?? 'Pendente') ?></td>
                    <td class="text-end">
                      <?php if (($cl['status'] ?? '') === 'Pendente'): ?>
                        <form method="post" class="d-inline">
                          <?= csrf_campo() ?><?= nonce_campo() ?>
                          <input type="hidden" name="chamada_lp_id" value="<?= (int)$cl['id'] ?>">
                          <button name="integralizar" value="1" class="btn btn-sm btn-outline-success py-0"><i class="bi bi-cash me-1"></i>Integralizar</button>
                        </form>
                        <form method="post" class="d-inline" onsubmit="return confirm('Marcar esta parcela como inadimplente?')">
                          <?= csrf_campo() ?><?= nonce_campo() ?>
                          <input type="hidden" name="chamada_lp_id" value="<?= (int)$cl['id'] ?>">
                          <button name="inadimplir" value="1" class="btn btn-sm btn-outline-danger py-0"><i class="bi bi-x-circle me-1"></i>Inadimplente</button>
                        </form>
                      <?php else: ?>
                        <span class="text-muted"><?= $cl['pago_em'] ? 'pago em ' . data_br($cl['pago_em']) : '—' ?></span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php if (empty($chamadaLps[(int)$ch['id']])): ?>
                  <tr><td colspan="4" class="text-muted text-center py-2">Sem parcelas.</td></tr>
                <?php endif; ?>
                </tbody>
              </table>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- 5. Participações (investidas) -->
<div class="row g-3 mb-4">
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-building-add me-1"></i> Nova participação (investir)</div>
      <div class="card-body">
        <form method="post">
          <?= csrf_campo() ?><?= nonce_campo() ?>
          <label class="form-label" style="font-size:.8rem">Empresa</label>
          <input name="empresa" class="form-control form-control-sm mb-2" placeholder="Ex.: TechCo S.A." required>
          <label class="form-label" style="font-size:.8rem">Setor</label>
          <input name="setor" class="form-control form-control-sm mb-2" placeholder="Ex.: Tecnologia">
          <label class="form-label" style="font-size:.8rem">% de participação</label>
          <input name="participacao" class="form-control form-control-sm mb-2" placeholder="30">
          <label class="form-label" style="font-size:.8rem">Custo de aquisição (R$)</label>
          <input name="custo" class="form-control form-control-sm mb-3" placeholder="5.000.000,00" required>
          <button name="investir" value="1" class="btn btn-sm btn-dark w-100"><i class="bi bi-check-lg me-1"></i>Registrar investimento</button>
        </form>
        <div class="text-muted mt-2" style="font-size:.72rem">Sai caixa; o valor justo inicial é o próprio custo (método "Custo").</div>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-buildings me-1"></i> Participações (investidas)</div>
      <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0" style="font-size:.83rem">
          <thead><tr>
            <th>Empresa</th><th>Setor</th><th class="text-end">%</th><th class="text-end">Custo</th>
            <th class="text-end">Valor justo</th><th>Método</th><th class="text-center">Status</th><th></th>
          </tr></thead>
          <tbody>
          <?php foreach ($participacoes as $p):
            $ativa = (($p['status'] ?? '') === 'Ativa'); ?>
            <tr class="<?= $ativa ? '' : 'text-muted' ?>">
              <td><b><?= e_html($p['empresa']) ?></b></td>
              <td><?= e_html($p['setor'] ?: '—') ?></td>
              <td class="text-end"><?= number_format((float)$p['percentual'], 2, ',', '.') ?>%</td>
              <td class="text-end"><?= moeda($p['custo_aquisicao']) ?></td>
              <td class="text-end"><?= $ativa ? moeda($p['valor_justo']) : moeda($p['valor_venda']) ?></td>
              <td><?= e_html($p['metodo'] ?? '—') ?></td>
              <td class="text-center"><?= badge_status($p['status'] ?? 'Ativa') ?></td>
              <td class="text-end">
                <?php if ($ativa): ?>
                  <button class="btn btn-sm btn-outline-secondary py-0" type="button"
                          data-bs-toggle="collapse" data-bs-target="#acoes-<?= (int)$p['id'] ?>">
                    <i class="bi bi-three-dots"></i>
                  </button>
                <?php endif; ?>
              </td>
            </tr>
            <?php if ($ativa): ?>
            <tr class="collapse" id="acoes-<?= (int)$p['id'] ?>">
              <td colspan="8" class="bg-light">
                <div class="row g-3 py-2">
                  <!-- Reavaliar -->
                  <div class="col-md-5">
                    <div class="fw-bold mb-1" style="font-size:.8rem"><i class="bi bi-clipboard-data me-1"></i>Reavaliar (laudo)</div>
                    <form method="post">
                      <?= csrf_campo() ?><?= nonce_campo() ?>
                      <input type="hidden" name="participacao_id" value="<?= (int)$p['id'] ?>">
                      <input name="novo_valor" class="form-control form-control-sm mb-2" placeholder="Novo valor justo (R$)" required>
                      <select name="metodo" class="form-select form-select-sm mb-2">
                        <?php foreach (FIP_METODOS as $m): ?>
                          <option value="<?= e_html($m) ?>" <?= ($p['metodo'] ?? '') === $m ? 'selected' : '' ?>><?= e_html($m) ?></option>
                        <?php endforeach; ?>
                      </select>
                      <textarea name="laudo" class="form-control form-control-sm mb-2" rows="2" placeholder="Texto do laudo (empresa avaliadora, premissas)"></textarea>
                      <button name="reavaliar" value="1" class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-check-lg me-1"></i>Registrar laudo</button>
                    </form>
                  </div>
                  <!-- Dividendo -->
                  <div class="col-md-3">
                    <div class="fw-bold mb-1" style="font-size:.8rem"><i class="bi bi-cash-stack me-1"></i>Dividendo/JCP</div>
                    <form method="post">
                      <?= csrf_campo() ?><?= nonce_campo() ?>
                      <input type="hidden" name="participacao_id" value="<?= (int)$p['id'] ?>">
                      <input name="valor" class="form-control form-control-sm mb-2" placeholder="Valor (R$)" required>
                      <button name="dividendo" value="1" class="btn btn-sm btn-outline-success w-100"><i class="bi bi-check-lg me-1"></i>Registrar</button>
                    </form>
                  </div>
                  <!-- Vender -->
                  <div class="col-md-4">
                    <div class="fw-bold mb-1" style="font-size:.8rem"><i class="bi bi-box-arrow-up me-1"></i>Vender (desinvestir)</div>
                    <form method="post" onsubmit="return confirm('Confirmar desinvestimento desta participação?')">
                      <?= csrf_campo() ?><?= nonce_campo() ?>
                      <input type="hidden" name="participacao_id" value="<?= (int)$p['id'] ?>">
                      <input name="valor_venda" class="form-control form-control-sm mb-2" placeholder="Valor de venda (R$)" required>
                      <button name="vender" value="1" class="btn btn-sm btn-outline-danger w-100"><i class="bi bi-check-lg me-1"></i>Vender</button>
                    </form>
                  </div>
                </div>
              </td>
            </tr>
            <?php endif; ?>
          <?php endforeach; ?>
          <?php if (!$participacoes): ?>
            <tr><td colspan="8" class="text-muted text-center py-4">Nenhuma participação — registre o primeiro investimento ao lado.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="card-footer text-muted" style="font-size:.72rem">
        Valor justo por <b>laudo</b> (CPC 46, <b>nível 3</b>): revisão em até 12 meses por empresa independente. Métodos: DCF, múltiplos, última rodada ou custo.
      </div>
    </div>
  </div>
</div>

<!-- 6. Distribuições (waterfall) -->
<div class="row g-3 mb-4">
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-water me-1"></i> Distribuir (waterfall)</div>
      <div class="card-body">
        <form method="post" onsubmit="return confirm('Distribuir este valor aos cotistas?')">
          <?= csrf_campo() ?><?= nonce_campo() ?>
          <label class="form-label" style="font-size:.8rem">Valor a distribuir (R$)</label>
          <input name="valor_distribuir" class="form-control form-control-sm mb-3" placeholder="3.000.000,00" required>
          <button name="distribuir" value="1" class="btn btn-sm btn-dark w-100"><i class="bi bi-arrow-down-circle me-1"></i>Distribuir</button>
        </form>
        <div class="alert alert-info py-2 mb-0 mt-3" style="font-size:.78rem">
          <b>Waterfall (simplificado):</b> 1º <b>retorno de capital</b> (devolve o integralizado) → 2º <b>retorno preferencial</b>
          (hurdle ~8% a.a.) → 3º <b>carried interest</b> de 20% ao gestor sobre o excedente. Esta é a <b>1ª versão simplificada</b>.
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-water me-1"></i> Distribuições realizadas</div>
      <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0" style="font-size:.83rem">
          <thead><tr>
            <th>Data</th><th class="text-end">Total</th><th class="text-end">Retorno de capital</th>
            <th class="text-end">Retorno aos LPs</th><th class="text-end">Carry (20%)</th><th>Detalhe</th>
          </tr></thead>
          <tbody>
          <?php foreach ($distribuicoes as $d): ?>
            <tr>
              <td><?= data_br($d['data_ref']) ?></td>
              <td class="text-end"><?= moeda($d['valor_total']) ?></td>
              <td class="text-end"><?= moeda($d['retorno_capital']) ?></td>
              <td class="text-end"><?= moeda($d['retorno_preferencial']) ?></td>
              <td class="text-end"><?= moeda($d['carry']) ?></td>
              <td class="text-muted" style="font-size:.78rem"><?= e_html($d['detalhe'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$distribuicoes): ?>
            <tr><td colspan="6" class="text-muted text-center py-4">Nenhuma distribuição realizada.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Como um FIP é marcado na vida real -->
<div class="card mb-3">
  <div class="card-header"><i class="bi bi-rulers me-1"></i> Como as participações são marcadas na vida real (valor justo)</div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-lg-6">
        <h6 class="mb-1" style="font-size:.9rem"><i class="bi bi-calculator me-1"></i> Métodos de avaliação e quando cada um é usado</h6>
        <ul style="font-size:.83rem" class="mb-2">
          <li><b>Fluxo de caixa descontado (DCF)</b> — método <b>mais comum</b>, para companhias operacionais/maduras
              (projeta a geração de caixa e desconta a uma taxa de risco).</li>
          <li><b>Múltiplos de mercado</b> (EV/EBITDA de comparáveis) — usados sobretudo como
              <b>sanity check</b> do DCF e em referências de M&amp;A do setor. Para early-stage sem lucro, usa-se <b>EV/Receita</b>.</li>
          <li><b>Última rodada / aporte recente</b> da própria companhia — referência/validação de preço.</li>
          <li><b>Custo de aquisição</b> — para pré-operacionais ou quando o valor justo <b>não é mensurável de forma confiável</b>
              (medida temporária, justificada em nota — ICVM 579, art. 3º, §4º).</li>
        </ul>
        <p class="mb-0" style="font-size:.8rem" class="text-muted">
          Tudo segue a <b>hierarquia do CPC 46 / IFRS 13</b>: como não há preço em mercado ativo, a participação de FIP fica
          tipicamente no <b>Nível 3</b> (dados não observáveis — premissas do modelo).
        </p>
      </div>
      <div class="col-lg-6">
        <h6 class="mb-1" style="font-size:.9rem"><i class="bi bi-people me-1"></i> Quem faz e com que frequência (governança)</h6>
        <ul style="font-size:.83rem" class="mb-2">
          <li><b>Gestor calcula/propõe</b> o valor justo (define as premissas); o <b>administrador fiduciário valida</b> o laudo
              e as premissas — sob a Res. CVM 175 o gestor tem o protagonismo da carteira do FIP.</li>
          <li>Na prática o laudo costuma vir de <b>empresa avaliadora independente</b> (imparcialidade); a Res. 175 admite
              <b>comitê de avaliação/precificação</b> com membros independentes e critérios objetivos.</li>
          <li><b>Periodicidade:</b> não é um "trimestral" fixo na norma — remensura-se na <b>data das demonstrações contábeis</b>
              (auditadas) <b>e sempre que houver evento relevante</b> que afete materialmente o valor (ICVM 579 — modelo periódico + event-driven).</li>
          <li><b>Trava anti-conflito:</b> a remuneração de administrador/gestor <b>não</b> pode ser calculada sobre ajustes de valor
              justo de investimentos <b>ainda não vendidos</b>; DFs auditadas por auditor registrado na CVM (envio em até 90 dias).</li>
        </ul>
        <div class="alert alert-warning py-2 mb-0" style="font-size:.8rem">
          <b>No piloto:</b> o laudo é um registro simplificado (valor + método + texto) que <b>eleva o PL</b> na cota. O
          <b>cálculo do modelo</b> (DCF/múltiplos), o parecer do avaliador independente e o fluxo de aprovação do comitê
          são <b>descritos, não executados</b>.
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Tributação do FIP na vida real -->
<div class="card mb-2">
  <div class="card-header"><i class="bi bi-percent me-1"></i> Tributação do FIP na vida real (Lei 14.754/2023)</div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-lg-6">
        <h6 class="mb-1" style="font-size:.9rem"><i class="bi bi-check2-circle me-1 text-success"></i> FIP <u>entidade de investimento</u></h6>
        <ul style="font-size:.83rem" class="mb-2">
          <li><b>Sem come-cotas.</b> IR de <b>15%</b> só no <b>resgate / amortização / distribuição / alienação</b> de cotas.</li>
          <li><b>Diferimento:</b> o ganho na venda de uma participação <b>dentro</b> do fundo fica em <b>subconta</b> e só é
              tributado quando <b>realizado/distribuído</b> ao cotista — não na venda intra-fundo.</li>
          <li>IR incide sobre a diferença entre o <b>valor patrimonial da cota</b> no evento e o <b>custo de aquisição</b>.</li>
          <li><b>Requisito (Lei 14.754, art. 26 + Res. CMN 5.111/2023):</b> gestão profissional <b>discricionária</b>, com poderes
              de investir/desinvestir para gerar retorno, e estratégias definidas no regulamento.</li>
        </ul>
      </div>
      <div class="col-lg-6">
        <h6 class="mb-1" style="font-size:.9rem"><i class="bi bi-x-circle me-1 text-danger"></i> FIP <u>desenquadrado</u> (não é entidade de investimento)</h6>
        <ul style="font-size:.83rem" class="mb-2">
          <li>Passa a sofrer <b>come-cotas de 15%</b> no último dia útil de <b>maio e novembro</b> (perde o diferimento) — não vira PJ.</li>
          <li>A variação de <b>equivalência patrimonial</b> das investidas <b>não</b> entra na base do come-cotas, desde que
              evidenciada em <b>subconta</b> (perda em subconta não é dedutível).</li>
          <li><b>Enquadramento de carteira (ICVM 578):</b> mín. <b>90% do PL</b> em ativos elegíveis (art. 11) — ações,
              debêntures conversíveis, bônus de subscrição, cotas de Ltda (art. 5) — e <b>participação no processo decisório</b>
              da investida (art. 6). O limite de 90% não corre durante o prazo de investimento do compromisso.</li>
        </ul>
      </div>
    </div>
    <div class="alert alert-secondary py-2 mb-2" style="font-size:.8rem">
      <b>Investidor estrangeiro:</b> alíquota <b>zero</b> de IR (Lei 11.312/2006, art. 3º), <b>salvo</b> residente em paraíso fiscal
      / regime fiscal privilegiado (Lei 9.430/96, arts. 24 e 24-A). A antiga <b>regra dos 40%</b> das cotas foi <b>revogada</b>
      pela MP 1.137/2022. <b>IOF:</b> aplicação/resgate em FIP costuma ser IOF-título zero; ingresso de capital estrangeiro (4373) IOF-câmbio zero.
    </div>
    <div class="alert alert-warning py-2 mb-0" style="font-size:.8rem">
      <b>No piloto:</b> o motor aplica <b>15% flat</b> no evento de saída e <b>não</b> roda come-cotas para o FIP (coerente com o
      caso <b>entidade de investimento</b>). O <b>teste de enquadramento</b> (90% / influência), o controle de <b>subcontas</b> de
      diferimento e a distinção estrangeiro/paraíso fiscal são <b>descritos, não calculados</b>.
    </div>
  </div>
</div>

<!-- Nota honesta: waterfall -->
<div class="card mb-2">
  <div class="card-header"><i class="bi bi-info-circle me-1"></i> Nota honesta — waterfall</div>
  <div class="card-body">
    <p class="mb-0" style="font-size:.83rem">
      <b>Waterfall:</b> retorno de capital → preferencial (hurdle ~8% a.a.) → carry de 20% — <b>1ª versão simplificada</b>.
      O hurdle preferencial ainda <b>não</b> é apurado por <b>TIR/tempo</b> (cash-on-cash com data de cada chamada e distribuição),
      apenas separado do carry sobre o excedente.
    </p>
  </div>
</div>

<?php endif; ?>
<?php page_end();
