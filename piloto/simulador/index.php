<?php
// Simulador Master — painel de controle (god mode) da simulação.
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';        // sessão + CSRF + cabeçalhos
require_once __DIR__ . '/../includes/helpers.php';     // calcular_cota, cota_em, moeda, data_br...
require_once __DIR__ . '/../includes/simulador.php';

exigir_master();

$msg = ''; $msgTipo = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_validar()) {
    $_POST = [];
    $msg = 'Requisição inválida (proteção CSRF). Recarregue a página.'; $msgTipo = 'danger';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = sim_data_atual($pdo);
        $fid = (int)($_POST['fundo_id'] ?? 0);
        if (!empty($_POST['reset'])) {
            sim_resetar($pdo);
            $msg = 'Base resetada e recarregada a partir do seed.sql. Dia reposicionado para o fim do histórico.';
        } elseif (!empty($_POST['avancar'])) {
            $r = sim_avancar_dia($pdo);
            $msg = "Dia avançado: " . data_br($r['de']) . " → " . data_br($r['para']) . " ({$r['fundos']} fundos processados).";
        } elseif (!empty($_POST['recebimento'])) {
            $valor = (float)str_replace(',', '.', $_POST['valor'] ?? '0');
            $tipo = $_POST['tipo'] ?? 'Aplicação';
            if ($fid > 0 && $valor > 0) { sim_injetar_recebimento($pdo, $fid, $tipo, $valor, $data); $msg = "Recebimento de " . moeda($valor) . " ($tipo) creditado no caixa do fundo."; }
            else { $msg = 'Selecione um fundo e um valor válido.'; $msgTipo = 'warning'; }
        } elseif (!empty($_POST['boleta'])) {
            if ($fid > 0) { sim_gerar_boleta($pdo, $fid, $data); $msg = 'Boleta de compra criada — abra a Mesa de Custódia para aceitar e liquidar (DVP).'; }
            else { $msg = 'Selecione um fundo.'; $msgTipo = 'warning'; }
        } elseif (!empty($_POST['mensagem'])) {
            if ($fid > 0) { sim_injetar_mensagem($pdo, $fid); $msg = 'Mensagem RSFN/SPB injetada na fila da custódia.'; }
            else { $msg = 'Selecione um fundo.'; $msgTipo = 'warning'; }
        } elseif (!empty($_POST['oficio'])) {
            if ($fid > 0) { sim_criar_oficio($pdo, $fid, $data); $msg = 'Ofício da CVM criado — trate em Administradora → Regulatório.'; }
            else { $msg = 'Selecione um fundo.'; $msgTipo = 'warning'; }
        } elseif (!empty($_POST['divergencia'])) {
            if ($fid > 0) { sim_criar_divergencia($pdo, $fid, $data); $msg = 'Divergência de conciliação aberta — trate em Administradora → Conciliação.'; }
            else { $msg = 'Selecione um fundo.'; $msgTipo = 'warning'; }
        }
    } catch (Throwable $e) {
        $msg = 'Não foi possível concluir: ' . $e->getMessage(); $msgTipo = 'danger';
    }
}

$dataSim = sim_data_atual($pdo);
$stats = sim_stats($pdo);
$fundos = $pdo->query("SELECT id, nome FROM fundos WHERE status='Ativo' ORDER BY pl_atual DESC")->fetchAll();

/** <select> de fundos reutilizável. */
function select_fundos(array $fundos): string {
    $h = '<select name="fundo_id" class="form-select form-select-sm mb-2">';
    foreach ($fundos as $f) $h .= '<option value="' . (int)$f['id'] . '">' . e_html($f['nome']) . '</option>';
    return $h . '</select>';
}
$selFundos = select_fundos($fundos);
$csrf = csrf_campo();
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Simulador Master · Painel</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
  body { background:#0b1220; color:#e2e8f0; font-family:system-ui,sans-serif; }
  .card { background:#111c30; border:1px solid #1e2f4a; border-radius:14px; }
  .card-header { background:#0e1830; border-bottom:1px solid #1e2f4a; color:#cbd5e1; font-weight:600; }
  .form-control, .form-select { background:#0b1220; border-color:#26364f; color:#e2e8f0; }
  .form-control:focus, .form-select:focus { background:#0b1220; color:#fff; border-color:#3b82f6; box-shadow:none; }
  .stat { background:#0e1830; border:1px solid #1e2f4a; border-radius:12px; padding:12px 14px; }
  .stat b { font-size:1.5rem; } .topbar { border-bottom:1px solid #1e2f4a; }
  a.portal { color:#93c5fd; text-decoration:none; } a.portal:hover { text-decoration:underline; }
</style>
</head>
<body>
<div class="topbar py-3 mb-4" style="background:#0e1830">
  <div class="container d-flex justify-content-between align-items-center">
    <span style="font-weight:700;font-size:1.1rem"><i class="bi bi-joystick me-2" style="color:#f59e0b"></i>Simulador Master</span>
    <span style="font-size:.9rem">Dia hipotético: <b style="color:#f59e0b"><?= data_br($dataSim) ?></b>
      &nbsp;·&nbsp; <a href="sair.php" style="color:#94a3b8;font-size:.85rem">sair</a></span>
  </div>
</div>

<div class="container pb-5" style="max-width:1000px">

  <?php if ($msg): ?><div class="alert alert-<?= $msgTipo ?> py-2"><i class="bi bi-info-circle me-1"></i><?= e_html($msg) ?></div><?php endif; ?>

  <!-- Estado -->
  <div class="row row-cols-2 row-cols-md-4 g-2 mb-4">
    <div class="col"><div class="stat"><b><?= $stats['fundos'] ?></b><br><span class="text-secondary" style="font-size:.78rem">fundos ativos</span></div></div>
    <div class="col"><div class="stat"><b><?= $stats['cotistas'] ?></b><br><span class="text-secondary" style="font-size:.78rem">cotistas</span></div></div>
    <div class="col"><div class="stat"><b><?= $stats['boletas'] ?></b><br><span class="text-secondary" style="font-size:.78rem">boletas pendentes</span></div></div>
    <div class="col"><div class="stat"><b><?= $stats['liquidacoes'] ?></b><br><span class="text-secondary" style="font-size:.78rem">liquidações pendentes</span></div></div>
    <div class="col"><div class="stat"><b><?= $stats['mensagens'] ?></b><br><span class="text-secondary" style="font-size:.78rem">mensagens a tratar</span></div></div>
    <div class="col"><div class="stat"><b><?= $stats['divergencias'] ?></b><br><span class="text-secondary" style="font-size:.78rem">divergências abertas</span></div></div>
    <div class="col"><div class="stat"><b><?= $stats['oficios'] ?></b><br><span class="text-secondary" style="font-size:.78rem">ofícios em aberto</span></div></div>
  </div>

  <!-- Controles globais -->
  <div class="row g-3 mb-3">
    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-header"><i class="bi bi-fast-forward-circle me-1"></i> Passar de dia</div>
        <div class="card-body">
          <p class="text-secondary" style="font-size:.85rem">Avança para o próximo dia útil: gera novos preços (RV varia, RF acretem CDI), recalcula cota/PL e monta a esteira de processamento de todos os fundos.</p>
          <form method="post"><?= $csrf ?>
            <button name="avancar" value="1" class="btn w-100" style="background:#3b82f6;color:#fff;font-weight:600"><i class="bi bi-arrow-right-circle me-1"></i>Avançar 1 dia útil</button>
          </form>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card h-100" style="border-color:#7f1d1d">
        <div class="card-header" style="color:#fca5a5"><i class="bi bi-exclamation-octagon me-1"></i> Reset da base</div>
        <div class="card-body">
          <p class="text-secondary" style="font-size:.85rem">Recria o schema e recarrega o <code>seed.sql</code>. <b style="color:#fca5a5">Apaga todos os dados</b> (aplicações, resgates, tributos, avanços de dia) e volta ao estado de fábrica.</p>
          <form method="post" onsubmit="return confirm('RESET: isso APAGA todos os dados e recarrega o seed. Confirmar?') && confirm('Tem certeza mesmo? Não há como desfazer.')">
            <?= $csrf ?>
            <button name="reset" value="1" class="btn btn-danger w-100" style="font-weight:600"><i class="bi bi-arrow-counterclockwise me-1"></i>Resetar e recarregar seed</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Injetores -->
  <div class="card mb-3">
    <div class="card-header"><i class="bi bi-magic me-1"></i> Injetar eventos (exercitar os fluxos)</div>
    <div class="card-body">
      <div class="row g-3">

        <div class="col-md-6">
          <div class="p-3" style="border:1px solid #1e2f4a;border-radius:10px">
            <b style="font-size:.9rem"><i class="bi bi-cash-coin me-1 text-success"></i>Recebimento no fundo</b>
            <form method="post" class="mt-2"><?= $csrf ?><?= $selFundos ?>
              <div class="d-flex gap-2 mb-2">
                <select name="tipo" class="form-select form-select-sm"><option>Aplicação</option><option>Provento</option><option>Vencimento de título</option><option>Aporte</option></select>
                <input name="valor" class="form-control form-control-sm" placeholder="Valor R$" required>
              </div>
              <button name="recebimento" value="1" class="btn btn-sm btn-success w-100">Creditar no caixa</button>
            </form>
            <p class="text-secondary mb-0 mt-2" style="font-size:.72rem">Credita <code>caixa_atual</code> + linha em <code>movimentacoes</code>.</p>
          </div>
        </div>

        <div class="col-md-6">
          <div class="p-3" style="border:1px solid #1e2f4a;border-radius:10px">
            <b style="font-size:.9rem"><i class="bi bi-receipt-cutoff me-1 text-warning"></i>Boleta de compra (→ custódia)</b>
            <form method="post" class="mt-2"><?= $csrf ?><?= $selFundos ?>
              <button name="boleta" value="1" class="btn btn-sm btn-warning w-100">Gerar boleta pendente</button>
            </form>
            <p class="text-secondary mb-0 mt-2" style="font-size:.72rem">Cria uma compra de CDB "Enviada" para a Mesa de Custódia aceitar e liquidar (DVP).</p>
          </div>
        </div>

        <div class="col-md-6">
          <div class="p-3" style="border:1px solid #1e2f4a;border-radius:10px">
            <b style="font-size:.9rem"><i class="bi bi-envelope-arrow-down me-1 text-info"></i>Mensagem RSFN/SPB (→ custódia)</b>
            <form method="post" class="mt-2"><?= $csrf ?><?= $selFundos ?>
              <button name="mensagem" value="1" class="btn btn-sm btn-info w-100">Injetar mensagem</button>
            </form>
            <p class="text-secondary mb-0 mt-2" style="font-size:.72rem">Adiciona uma mensagem "Recebida" na Mensageria SPB.</p>
          </div>
        </div>

        <div class="col-md-6">
          <div class="p-3" style="border:1px solid #1e2f4a;border-radius:10px">
            <b style="font-size:.9rem"><i class="bi bi-shield-exclamation me-1 text-danger"></i>Divergência / Ofício (→ administradora)</b>
            <form method="post" class="mt-2 d-flex flex-column gap-2"><?= $csrf ?><?= $selFundos ?>
              <div class="d-flex gap-2">
                <button name="divergencia" value="1" class="btn btn-sm btn-outline-danger w-100">Abrir divergência</button>
                <button name="oficio" value="1" class="btn btn-sm btn-outline-light w-100">Criar ofício CVM</button>
              </div>
            </form>
            <p class="text-secondary mb-0 mt-2" style="font-size:.72rem">Exercita Conciliação e Regulatório da administradora.</p>
          </div>
        </div>

      </div>
    </div>
  </div>

  <!-- Atalhos -->
  <div class="card">
    <div class="card-header"><i class="bi bi-box-arrow-up-right me-1"></i> Abrir os portais</div>
    <div class="card-body" style="font-size:.9rem">
      <a class="portal me-3" href="../admin/index.php" target="_blank"><i class="bi bi-bank2 me-1"></i>Administradora</a>
      <a class="portal me-3" href="../custodia/index.php" target="_blank"><i class="bi bi-safe2 me-1"></i>Custódia</a>
      <a class="portal me-3" href="../gestor/index.php" target="_blank"><i class="bi bi-graph-up me-1"></i>Gestor</a>
      <a class="portal" href="../index.php" target="_blank"><i class="bi bi-house me-1"></i>Landing</a>
    </div>
  </div>

</div>
</body>
</html>
