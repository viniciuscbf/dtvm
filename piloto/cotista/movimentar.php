<?php
// Movimentar — o cotista SOLICITA aplicação/resgate, como na vida real:
//  · APLICAÇÃO: TED ou Pix a partir de conta DE SUA TITULARIDADE (PLD — Res. CVM 50, art. 20);
//    Pix com QR dinâmico (txid) que casa o crédito com a ordem. Cartão NÃO existe em fundo;
//    espécie é vedada na prática; DOC foi extinto (jan/2024).
//  · RESGATE: pago exclusivamente na conta CADASTRADA (mesma titularidade), líquido de IOF/IR.
//  A administradora confirma o recebimento e cotiza (admin/passivo.php) — nada cotiza sozinho.
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$conta = exigir_conta_cotista($pdo);
ensure_ordens_passivo($pdo);   // DDL fora de transação

// resolve o fundo pedido (GET/POST) entre os vínculos da conta
$vinculos = fundos_da_conta($pdo, (int)$conta['id']);
$fid = (int)($_POST['fundo_id'] ?? $_GET['fundo_id'] ?? 0);
$cotista = null;
foreach ($vinculos as $v) if ((int)$v['fundo_id'] === $fid) $cotista = $v;
if (!$cotista && $vinculos) { $cotista = $vinculos[0]; $fid = (int)$cotista['fundo_id']; }

$fundo = null;
if ($cotista) {
    $st = $pdo->prepare('SELECT * FROM fundos WHERE id = ?');
    $st->execute([$fid]);
    $fundo = $st->fetch();
}

// mínimos do REGULAMENTO do fundo (0 = sem mínimo), contas bancárias e saldo disponível
$minimos = $fundo ? minimos_do_fundo($fundo) : ['aplicacao' => 0.0, 'movimentacao' => 0.0, 'saldo' => 0.0];
$contasBanc = $cotista ? contas_bancarias_da_conta($pdo, (int)$conta['id']) : [];
$cotaAtual = $fundo ? (float)($fundo['cota_atual'] ?: 1) : 1.0;
$saldoEstimado = $cotista ? (float)$cotista['cotas'] * $cotaAtual : 0.0;
$pendente = $cotista ? resgates_pendentes($pdo, (int)$cotista['id']) : 0.0;
$disponivel = max(0.0, $saldoEstimado - $pendente);

$msg = ''; $msgTipo = 'success'; $ordemNova = null;

if ($cotista && $_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_validar()) {
    $_POST = []; $msg = 'Requisição inválida (proteção CSRF). Recarregue a página.'; $msgTipo = 'danger';
}
if ($cotista && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $cid = (int)$cotista['id'];
    if (!empty($_POST['aplicar'])) {
        $valor = (float)str_replace(['.', ','], ['', '.'], $_POST['valor'] ?? '0');
        $metodo = ($_POST['metodo'] ?? 'Pix') === 'TED' ? 'TED' : 'Pix';
        // 1ª aplicação usa a aplicação inicial mínima do regulamento; as demais, a movimentação mínima
        $minAplic = (float)$cotista['cotas'] > 0 ? $minimos['movimentacao'] : $minimos['aplicacao'];
        if ($valor <= 0) {
            $msg = 'Informe um valor de aplicação válido.'; $msgTipo = 'warning';
        } elseif ($valor < $minAplic) {
            $msg = 'O regulamento deste fundo fixa mínimo de ' . moeda($minAplic) . ' para esta operação.'; $msgTipo = 'warning';
        } else {
            $r = ordem_criar_aplicacao($pdo, $fid, $cid, $valor, $metodo);
            $ordemNova = $r['id'];
            registrar_auditoria($pdo, 'ordem_aplicacao_criada', ['entidade' => 'ordem_passivo', 'entidade_id' => $r['id'],
                'fundo_id' => $fid, 'detalhe' => "Aplicação de " . moeda($valor) . " via {$r['metodo']} solicitada pelo portal"]);
            $msg = 'Ordem criada — siga as instruções de pagamento abaixo. A cotização acontece após a confirmação do recebimento.';
        }
    } elseif (!empty($_POST['simular_pgto'])) {
        // DEMO: simula a chegada da TED/Pix na conta do fundo (na vida real, vem do extrato bancário).
        $oid = (int)$_POST['simular_pgto'];
        $doc = !empty($_POST['terceiro']) ? '***.999.999-**' : ($cotista['documento'] ?: '***.000.000-**');
        $pdo->prepare("UPDATE ordens_passivo SET status='Recebida', pagador_doc=? WHERE id=? AND cotista_id=? AND status='Aguardando pagamento'")
            ->execute([$doc, $oid, $cid]);
        $msg = !empty($_POST['terceiro'])
            ? 'Pagamento simulado com CPF DIVERGENTE — a administradora vai barrar e devolver (regra de titularidade).'
            : 'Pagamento simulado — a ordem aguarda a confirmação da administradora para cotizar.';
        $msgTipo = !empty($_POST['terceiro']) ? 'warning' : 'success';
    } elseif (!empty($_POST['resgatar'])) {
        $valor = (float)str_replace(['.', ','], ['', '.'], $_POST['valor_resgate'] ?? '0');
        // conta de destino escolhida (entre as cadastradas — todas da mesma titularidade)
        $ccb = null;
        foreach ($contasBanc as $cb) if ((int)$cb['id'] === (int)($_POST['conta_banc_id'] ?? 0)) $ccb = $cb;
        if (!$ccb && $contasBanc) $ccb = $contasBanc[0];   // default: principal
        $resgateTotal = abs($valor - $disponivel) < 0.005;
        $saldoRestante = $disponivel - $valor;
        if (!$ccb) {
            $msg = 'Cadastre uma conta bancária de sua titularidade em "Meus dados" antes de resgatar.'; $msgTipo = 'warning';
        } elseif ($valor <= 0) {
            $msg = 'Informe um valor de resgate válido.'; $msgTipo = 'warning';
        } elseif ($valor > $disponivel + 0.005) {
            $msg = 'Resgate maior que o saldo disponível (' . moeda($disponivel) . ')' .
                   ($pendente > 0 ? ' — ' . moeda($pendente) . ' já estão bloqueados em resgates pendentes.' : '.');
            $msgTipo = 'warning';
        } elseif (!$resgateTotal && $valor < $minimos['movimentacao']) {
            $msg = 'O regulamento fixa movimentação mínima de ' . moeda($minimos['movimentacao']) . ' (exceto resgate total).'; $msgTipo = 'warning';
        } elseif (!$resgateTotal && $minimos['saldo'] > 0 && $saldoRestante < $minimos['saldo']) {
            $msg = 'Este resgate deixaria o saldo abaixo do mínimo de permanência do regulamento (' . moeda($minimos['saldo']) .
                   ') — reduza o valor ou solicite o resgate total (' . moeda($disponivel) . ').'; $msgTipo = 'warning';
        } else {
            $destino = $ccb['banco'] . ' · ag. ' . $ccb['agencia'] . ' · c/c ' . $ccb['conta'];
            $oid = ordem_criar_resgate($pdo, $fid, (int)$cotista['id'], $valor, $destino);
            registrar_auditoria($pdo, 'ordem_resgate_criada', ['entidade' => 'ordem_passivo', 'entidade_id' => $oid,
                'fundo_id' => $fid, 'detalhe' => 'Resgate de ' . moeda($valor) . " solicitado pelo portal → $destino"]);
            $msg = 'Resgate solicitado — o valor foi bloqueado do seu saldo disponível e será pago líquido de IR/IOF em ' . $destino . '.';
            $pendente = resgates_pendentes($pdo, (int)$cotista['id']);
            $disponivel = max(0.0, $saldoEstimado - $pendente);
        }
    } elseif (!empty($_POST['cancelar'])) {
        $pdo->prepare("UPDATE ordens_passivo SET status='Cancelada' WHERE id=? AND cotista_id=? AND status IN ('Aguardando pagamento','Solicitado')")
            ->execute([(int)$_POST['cancelar'], $cid]);
        $msg = 'Ordem cancelada.'; $msgTipo = 'secondary';
        // cancelamento de resgate libera o valor bloqueado
        $pendente = resgates_pendentes($pdo, $cid);
        $disponivel = max(0.0, $saldoEstimado - $pendente);
    }
}

$ordens = [];
if ($cotista) {
    $st = $pdo->prepare("SELECT * FROM ordens_passivo WHERE cotista_id = ? ORDER BY criado_em DESC LIMIT 20");
    $st->execute([(int)$cotista['id']]);
    $ordens = $st->fetchAll();
}
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $fundo ? e_html($fundo['nome']) . ' · ' : '' ?>Movimentar · Portal do Cotista</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="../assets/css/style.css" rel="stylesheet">
<style>body{background:var(--bg)}</style>
</head>
<body>
<nav style="background:var(--navy)" class="py-2 px-4 d-flex justify-content-between align-items-center">
  <div class="d-flex align-items-center gap-2 text-white">
    <img src="../assets/favicon.png" alt="Argus" style="height:26px;width:26px;object-fit:contain">
    <b style="font-size:.85rem;letter-spacing:1px">PORTAL DO COTISTA</b>
  </div>
  <div class="d-flex align-items-center gap-2 flex-wrap">
    <a class="btn btn-sm btn-outline-light" href="home.php" style="font-size:.75rem"><i class="bi bi-house me-1"></i>Início</a>
    <a class="btn btn-sm btn-outline-light" href="painel.php?fundo_id=<?= (int)$fid ?>" style="font-size:.75rem"><i class="bi bi-graph-up me-1"></i>Painel</a>
    <a class="btn btn-sm btn-outline-light" href="tickets.php" style="font-size:.75rem"><i class="bi bi-chat-dots me-1"></i>Dúvidas</a>
    <a class="btn btn-sm btn-outline-light" href="dados.php" style="font-size:.75rem"><i class="bi bi-person-gear me-1"></i>Meus dados</a>
    <a class="btn btn-sm btn-outline-light" href="sair.php" style="font-size:.75rem">Sair</a>
  </div>
</nav>

<div class="container py-4" style="max-width:1050px">
  <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-3">
    <div>
      <h4 class="mb-0">Aplicar e resgatar<?= $fundo ? ' · ' . e_html($fundo['nome']) : '' ?></h4>
      <span class="text-muted" style="font-size:.82rem">cota atual <?= number_format($cotaAtual, 6, ',', '.') ?> · a movimentação cotiza somente após a confirmação da administradora</span>
    </div>
    <?php if (count($vinculos) > 1): ?>
      <form method="get">
        <select name="fundo_id" class="form-select form-select-sm" style="max-width:340px" onchange="this.form.submit()">
          <?php foreach ($vinculos as $v): ?>
            <option value="<?= (int)$v['fundo_id'] ?>" <?= (int)$v['fundo_id'] === $fid ? 'selected' : '' ?>><?= e_html($v['fundo_nome']) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
    <?php endif; ?>
  </div>

  <?php if ($msg): ?><div class="alert alert-<?= $msgTipo ?> py-2" style="font-size:.86rem"><i class="bi bi-info-circle me-1"></i><?= e_html($msg) ?></div><?php endif; ?>

  <?php if (!$cotista): ?>
    <div class="alert alert-info" style="font-size:.9rem"><i class="bi bi-hourglass-split me-1"></i>
      Sua conta ainda não tem posições vinculadas — a movimentação fica disponível quando a administradora
      vincular o seu CPF a um fundo. Se você já é cotista, avise o gestor do fundo.</div>
  <?php else: ?>

  <div class="row g-3 mb-4">
    <!-- APLICAR -->
    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header"><i class="bi bi-plus-circle me-1"></i> Aplicar</div>
        <div class="card-body">
          <form method="post">
            <?php $minAplicUi = (float)$cotista['cotas'] > 0 ? $minimos['movimentacao'] : $minimos['aplicacao']; ?>
            <?= csrf_campo() ?><input type="hidden" name="aplicar" value="1"><input type="hidden" name="fundo_id" value="<?= (int)$fid ?>">
            <label class="form-label" style="font-size:.78rem">Valor (R$)<?= $minAplicUi > 0
                ? ' — mínimo do regulamento: ' . moeda($minAplicUi) : ' — este fundo não fixa aplicação mínima' ?></label>
            <input class="form-control form-control-sm mb-2" name="valor" placeholder="100.000,00" required>
            <label class="form-label" style="font-size:.78rem">Forma de pagamento</label>
            <div class="d-flex gap-3 mb-2" style="font-size:.85rem">
              <label><input type="radio" name="metodo" value="Pix" checked> Pix (QR dinâmico)</label>
              <label><input type="radio" name="metodo" value="TED"> TED</label>
            </div>
            <button class="btn btn-dark btn-sm w-100"><i class="bi bi-send me-1"></i>Gerar ordem e instruções de pagamento</button>
          </form>
          <p class="text-muted mb-0 mt-2" style="font-size:.7rem">
            O pagamento deve sair de conta bancária <b>da sua titularidade</b> (mesmo CPF) — recurso de terceiro é
            devolvido (prevenção à lavagem, Res. CVM 50). Não aceitamos cartão de crédito (não existe em fundos),
            dinheiro em espécie nem cheque; o DOC foi descontinuado pelo sistema bancário em 2024.</p>
        </div>
      </div>
    </div>

    <!-- RESGATAR -->
    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header"><i class="bi bi-dash-circle me-1"></i> Resgatar</div>
        <div class="card-body">
          <div class="mb-2" style="font-size:.82rem">
            Saldo disponível: <b><?= moeda($disponivel) ?></b>
            <span class="text-muted">(<?= number_format((float)$cotista['cotas'], 2, ',', '.') ?> cotas × cota atual<?=
              $pendente > 0 ? ' − ' . moeda($pendente) . ' bloqueados em resgates pendentes' : '' ?>)</span>
          </div>
          <form method="post">
            <?= csrf_campo() ?><input type="hidden" name="resgatar" value="1"><input type="hidden" name="fundo_id" value="<?= (int)$fid ?>">
            <label class="form-label" style="font-size:.78rem">Valor bruto (R$)<?= $minimos['movimentacao'] > 0
                ? ' — mínimo ' . moeda($minimos['movimentacao']) . ' (exceto resgate total)' : '' ?></label>
            <input class="form-control form-control-sm mb-2" name="valor_resgate" placeholder="15.000,00" required>
            <label class="form-label" style="font-size:.78rem">Conta de destino (mesma titularidade)</label>
            <?php if ($contasBanc): ?>
              <select name="conta_banc_id" class="form-select form-select-sm mb-2">
                <?php foreach ($contasBanc as $cb): ?>
                  <option value="<?= (int)$cb['id'] ?>"><?= e_html($cb['banco'] . ' · ag. ' . $cb['agencia'] . ' · c/c ' . $cb['conta']) ?><?= $cb['principal'] ? ' (principal)' : '' ?></option>
                <?php endforeach; ?>
              </select>
            <?php else: ?>
              <div class="border rounded p-2 mb-2" style="font-size:.76rem;background:var(--bs-light)">
                <span class="text-danger">nenhuma conta cadastrada — cadastre em <a href="dados.php">Meus dados</a></span>
              </div>
            <?php endif; ?>
            <button class="btn btn-outline-dark btn-sm w-100"><i class="bi bi-send me-1"></i>Solicitar resgate</button>
          </form>
          <p class="text-muted mb-0 mt-2" style="font-size:.7rem">
            O resgate é pago <b>exclusivamente</b> em conta cadastrada da sua titularidade, líquido de
            <b>IOF</b> (aplicações com menos de 30 dias) e <b>IR</b> retidos na fonte. Ao solicitar, o valor
            fica <b>bloqueado</b> do saldo disponível até o pagamento (ou cancelamento). Você pode manter
            mais de uma conta em <a href="dados.php">Meus dados</a>.</p>
        </div>
      </div>
    </div>
  </div>

  <!-- MINHAS ORDENS -->
  <div class="card">
    <div class="card-header"><i class="bi bi-list-check me-1"></i> Minhas ordens</div>
    <div class="card-body p-0">
      <table class="table table-hover align-middle mb-0" style="font-size:.82rem">
        <thead><tr><th>Criada</th><th>Tipo</th><th class="text-end">Valor</th><th>Pagamento</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($ordens as $o): ?>
          <tr>
            <td class="text-muted" style="font-size:.76rem"><?= date('d/m H:i', strtotime($o['criado_em'])) ?></td>
            <td><?= badge($o['tipo'], $o['tipo'] === 'Aplicação' ? 'success' : 'warning') ?></td>
            <td class="text-end"><b><?= moeda($o['valor']) ?></b></td>
            <td style="font-size:.76rem">
              <?php if ($o['tipo'] === 'Aplicação'): ?>
                <?= e_html($o['metodo']) ?>
                <?php if ($o['metodo'] === 'Pix' && $o['status'] === 'Aguardando pagamento'): ?>
                  <div class="border rounded p-2 mt-1" style="background:#f8f9ff;font-size:.72rem">
                    <b><i class="bi bi-qr-code me-1"></i>QR dinâmico (simulado)</b><br>
                    chave: fundo<?= (int)$fid ?>@argusdtvm.com.br<br>
                    txid: <code style="font-size:.68rem"><?= e_html($o['txid']) ?></code> · valor travado: <?= moeda($o['valor']) ?><br>
                    <span class="text-muted">o txid casa o crédito com esta ordem automaticamente</span>
                  </div>
                <?php elseif ($o['metodo'] === 'TED' && $o['status'] === 'Aguardando pagamento'): ?>
                  <div class="border rounded p-2 mt-1" style="background:#f8f9ff;font-size:.72rem">
                    <b>Dados para TED (conta do fundo no custodiante):</b><br>
                    Banco Parceiro S.A. (479) · ag. 0001 · c/c 77.10<?= (int)$fid ?>-<?= (int)$fid ?><br>
                    Favorecido: <?= e_html($fundo['nome']) ?> · CNPJ <?= e_html($fundo['cnpj']) ?><br>
                    <span class="text-muted">envie da SUA conta — TED de terceiro é devolvida</span>
                  </div>
                <?php endif; ?>
                <?= $o['pagador_doc'] ? '<br><span class="text-muted">pagador: ' . e_html($o['pagador_doc']) . '</span>' : '' ?>
              <?php else: ?>
                <?= !empty($o['conta_destino']) ? 'p/ ' . e_html($o['conta_destino'])
                    : 'p/ conta cadastrada' . (!empty($cotista['banco']) ? ' (' . e_html($cotista['banco']) . ')' : '') ?>
              <?php endif; ?>
            </td>
            <td><?= badge($o['status'], ['Aguardando pagamento' => 'warning', 'Recebida' => 'info', 'Cotizada' => 'success',
                                          'Devolvida' => 'danger', 'Solicitado' => 'warning', 'Pago' => 'success',
                                          'Cancelada' => 'secondary'][$o['status']] ?? 'secondary') ?>
              <?= $o['motivo'] ? '<br><span class="text-muted" style="font-size:.7rem">' . e_html($o['motivo']) . '</span>' : '' ?></td>
            <td class="text-end" style="min-width:170px">
              <?php if ($o['status'] === 'Aguardando pagamento'): ?>
                <form method="post" class="d-inline"><?= csrf_campo() ?>
                  <input type="hidden" name="simular_pgto" value="<?= (int)$o['id'] ?>"><input type="hidden" name="fundo_id" value="<?= (int)$fid ?>">
                  <label class="me-1" style="font-size:.68rem"><input type="checkbox" name="terceiro" value="1"> CPF divergente</label>
                  <button class="btn btn-sm btn-outline-primary" style="font-size:.72rem" title="No piloto, simula a chegada do dinheiro">
                    <i class="bi bi-cash-coin me-1"></i>Simular pagamento</button>
                </form>
              <?php endif; ?>
              <?php if (in_array($o['status'], ['Aguardando pagamento', 'Solicitado'], true)): ?>
                <form method="post" class="d-inline"><?= csrf_campo() ?>
                  <input type="hidden" name="cancelar" value="<?= (int)$o['id'] ?>"><input type="hidden" name="fundo_id" value="<?= (int)$fid ?>">
                  <button class="btn btn-sm btn-outline-secondary" style="font-size:.72rem">Cancelar</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$ordens): ?><tr><td colspan="6" class="text-muted text-center py-4">Nenhuma ordem ainda.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="card-footer text-muted" style="font-size:.7rem">
      Ciclo real: ordem → pagamento (TED/Pix de conta própria) → conciliação do extrato pela administradora →
      validação de titularidade → <b>cotização pela cota do dia</b> → posição no painel. Aplicações confirmadas após o
      corte (14h30) cotizam no dia útil seguinte. As cotas são <b>escriturais</b> — registradas pelo escriturador,
      sem certificado físico. No piloto, o botão "Simular pagamento" substitui a chegada real do dinheiro.
    </div>
  </div>

  <?php endif; ?>
</div>
</body>
</html>
