<?php
// Boletagem de operações — o gestor informa o que comprou/vendeu; a custódia aceita e liquida (DVP)
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

$u = exigir_perfil('gestor', 'admin');
$fundo = fundo_do_usuario($pdo, $u);
if (!$fundo) die('Sem fundo vinculado.');
exigir_fundo_ativo($fundo);
$fid = (int)$fundo['id'];

$msg = ''; $msgTipo = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['boletar'])) {
    $qtd = (float)str_replace(['.', ','], ['', '.'], $_POST['quantidade'] ?? '0');
    $preco = (float)str_replace(['.', ','], ['', '.'], $_POST['preco'] ?? '0');
    $codigo = strtoupper(trim($_POST['ativo_codigo'] ?? ''));
    $tipo = $_POST['tipo_ativo'] ?? 'CDB';
    $operacao = $_POST['operacao'] === 'Venda' ? 'Venda' : 'Compra';
    if ($qtd <= 0 || $preco <= 0 || $codigo === '') {
        $msg = 'Informe ativo, quantidade e preço válidos.'; $msgTipo = 'danger';
    } else {
        // venda: precisa ter posição suficiente no snapshot mais recente
        if ($operacao === 'Venda') {
            $st = $pdo->prepare('SELECT quantidade FROM ativos_carteira WHERE fundo_id = ? AND codigo = ? AND data_ref = ?');
            $st->execute([$fid, $codigo, ultima_data_carteira($pdo, $fid)]);
            $posAtual = (float)($st->fetchColumn() ?: 0);
            if ($posAtual < $qtd) {
                $msg = "Venda maior que a posição atual de $codigo (" . number_format($posAtual, 0, ',', '.') . ' un.).';
                $msgTipo = 'danger';
            }
        }
        if ($msgTipo !== 'danger') {
            $pdo->prepare("INSERT INTO boletas (fundo_id, data_operacao, operacao, ativo_codigo, tipo_ativo, quantidade, preco, valor, contraparte, criado_por)
                           VALUES (?,?,?,?,?,?,?,?,?,?)")
                ->execute([$fid, $_POST['data_operacao'] ?: date('Y-m-d'), $operacao, $codigo, $tipo,
                           $qtd, $preco, $qtd * $preco, trim($_POST['contraparte'] ?? ''), $u['nome']]);
            $msg = "Boleta de $operacao de $codigo enviada à mesa de custódia — ela valida, liquida (DVP) e a posição entra na carteira.";
        }
    }
}

$st = $pdo->prepare('SELECT * FROM boletas WHERE fundo_id = ? ORDER BY criado_em DESC LIMIT 30');
$st->execute([$fid]);
$boletas = $st->fetchAll();

// posição atual (para o gestor conferir antes de vender)
$posicao = carteira($pdo, $fid);

page_start('Boletar operação', 'Boletar operação', $u,
    e_html($fundo['nome']) . ' · a boleta segue para a custódia, que liquida DVP e reflete na carteira e no caixa');
?>

<?php if ($msg): ?><div class="alert alert-<?= $msgTipo ?> py-2"><i class="bi bi-info-circle me-1"></i><?= e_html($msg) ?></div><?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-lg-5">
    <div class="card">
      <div class="card-header"><i class="bi bi-receipt-cutoff me-1"></i> Nova boleta</div>
      <div class="card-body">
        <form method="post">
          <input type="hidden" name="boletar" value="1">
          <div class="row g-2">
            <div class="col-6"><label class="form-label" style="font-size:.78rem">Operação</label>
              <select class="form-select form-select-sm" name="operacao"><option>Compra</option><option>Venda</option></select></div>
            <div class="col-6"><label class="form-label" style="font-size:.78rem">Data da operação</label>
              <input type="date" class="form-control form-control-sm" name="data_operacao" value="<?= date('Y-m-d') ?>"></div>
            <div class="col-6"><label class="form-label" style="font-size:.78rem">Código do ativo *</label>
              <input class="form-control form-control-sm" name="ativo_codigo" required placeholder="Ex.: CDB BCO MÉTRICA, PETR4"></div>
            <div class="col-6"><label class="form-label" style="font-size:.78rem">Tipo</label>
              <select class="form-select form-select-sm" name="tipo_ativo">
                <option>CDB</option><option>Debênture</option><option>Título Público</option>
                <option>CRI/CRA</option><option>Ação</option><option>Cota de Fundo</option>
              </select></div>
            <div class="col-6"><label class="form-label" style="font-size:.78rem">Quantidade *</label>
              <input class="form-control form-control-sm" name="quantidade" required placeholder="500"></div>
            <div class="col-6"><label class="form-label" style="font-size:.78rem">Preço unitário (R$) *</label>
              <input class="form-control form-control-sm" name="preco" required placeholder="1.120,50"></div>
            <div class="col-12"><label class="form-label" style="font-size:.78rem">Contraparte</label>
              <input class="form-control form-control-sm" name="contraparte" placeholder="Ex.: XP CTVM, Banco Métrica (emissor)"></div>
          </div>
          <button class="btn btn-dark btn-sm w-100 mt-3"><i class="bi bi-send me-1"></i>Enviar boleta à custódia</button>
        </form>
        <p class="text-muted mb-0 mt-2" style="font-size:.72rem">
          Ciclo real reproduzido: boleta → aceite da mesa de custódia (instrução de liquidação D+1/D+2) →
          liquidação DVP (caixa sai/entra) → ativo entra na carteira pelo preço médio → cota do dia reflete a posição.</p>
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
          <td><?= badge($b['status'], ['Enviada' => 'warning', 'Aceita' => 'info', 'Liquidada' => 'success', 'Rejeitada' => 'danger'][$b['status']]) ?>
            <?= $b['status'] === 'Rejeitada' && $b['motivo'] ? '<br><span class="text-danger" style="font-size:.72rem">' . e_html($b['motivo']) . '</span>' : '' ?>
            <?= $b['status'] === 'Enviada' ? '<br><span class="text-muted" style="font-size:.7rem">aguardando aceite da custódia</span>' : '' ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$boletas): ?><tr><td colspan="6" class="text-muted text-center py-4">Nenhuma boleta enviada ainda.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php page_end();
