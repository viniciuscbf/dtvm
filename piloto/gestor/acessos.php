<?php
// Acessos de cotistas — o gestor gera e revoga tokens (UUID 36) com nível de visão
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

$u = exigir_perfil('gestor', 'admin');
$fundo = fundo_do_usuario($pdo, $u);
if (!$fundo) die('Sem fundo vinculado.');
exigir_fundo_ativo($fundo);
$fid = (int)$fundo['id'];

$novoToken = null; $msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['acao'] ?? '') === 'gerar') {
        $nivel = in_array($_POST['nivel'] ?? '', ['realtime', 'delay_1m', 'delay_3m'], true) ? $_POST['nivel'] : 'delay_3m';
        $novoToken = uuid4();
        $pdo->prepare("INSERT INTO tokens_acesso (fundo_id, token, nivel, descricao, criado_por) VALUES (?,?,?,?,?)")
            ->execute([$fid, $novoToken, $nivel, trim($_POST['descricao'] ?? ''), $u['nome']]);
    } elseif (!empty($_POST['revogar'])) {
        $pdo->prepare("UPDATE tokens_acesso SET status='Revogado', revogado_em=NOW() WHERE id=? AND fundo_id=?")
            ->execute([(int)$_POST['revogar'], $fid]);
        $msg = 'Token revogado — o acesso do cotista cai imediatamente.';
    }
}

// checkbox "mostrar revogados" — por padrão só exibe tokens ativos
$mostrarRevogados = !empty($_GET['revogados']);
$sql = 'SELECT * FROM tokens_acesso WHERE fundo_id = ?' . ($mostrarRevogados ? '' : " AND status = 'Ativo'") . ' ORDER BY status, criado_em DESC';
$st = $pdo->prepare($sql);
$st->execute([$fid]);
$tokens = $st->fetchAll();

$nivelRotulo = ['realtime' => 'Tempo real', 'delay_1m' => 'Defasagem 1 mês', 'delay_3m' => 'Defasagem 3 meses'];
$nivelCor = ['realtime' => 'danger', 'delay_1m' => 'warning', 'delay_3m' => 'success'];

page_start('Acessos de cotistas', 'Acessos de cotistas', $u,
    e_html($fundo['nome']) . ' · o cotista entra no portal apenas com o token — sem cadastro, sem senha');
?>

<?php if ($msg): ?><div class="alert alert-success py-2"><i class="bi bi-check-circle me-1"></i><?= e_html($msg) ?></div><?php endif; ?>
<?php if ($novoToken): ?>
  <div class="alert alert-success">
    <b><i class="bi bi-key me-1"></i>Token gerado — envie ao cotista:</b>
    <div class="d-flex gap-2 align-items-center mt-2">
      <code id="token-novo" style="font-size:1.05rem"><?= $novoToken ?></code>
      <button class="btn btn-sm btn-outline-dark" onclick="navigator.clipboard.writeText(document.getElementById('token-novo').textContent);this.textContent='copiado!'">
        <i class="bi bi-clipboard"></i> copiar</button>
    </div>
    <div class="text-muted mt-1" style="font-size:.78rem">O cotista acessa em <b>Portal do Cotista</b> e cola o token. Você pode revogá-lo a qualquer momento.</div>
  </div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header"><i class="bi bi-plus-circle me-1"></i> Gerar novo acesso</div>
      <div class="card-body">
        <form method="post">
          <input type="hidden" name="acao" value="gerar">
          <div class="mb-2">
            <label class="form-label" style="font-size:.8rem">Identificação (para seu controle)</label>
            <input class="form-control form-control-sm" name="descricao" placeholder="Ex.: Família Silva, Investidor institucional X" required>
          </div>
          <div class="mb-3">
            <label class="form-label" style="font-size:.8rem">Nível de visão da carteira</label>
            <select class="form-select form-select-sm" name="nivel">
              <option value="delay_3m">Defasagem de 3 meses (padrão de mercado)</option>
              <option value="delay_1m">Defasagem de 1 mês</option>
              <option value="realtime">Tempo real (carteira aberta)</option>
            </select>
          </div>
          <button class="btn btn-dark btn-sm w-100"><i class="bi bi-key me-1"></i>Gerar token UUID</button>
        </form>
        <div class="alert alert-light border mt-3 mb-0" style="font-size:.75rem">
          <i class="bi bi-shield-check me-1 text-success"></i><b>Por que defasagem?</b> Divulgar a carteira em tempo real
          expõe a estratégia do fundo (front-running). O padrão de mercado é abrir a carteira completa com atraso —
          você escolhe o nível por cotista e revoga quando quiser.
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-key me-1"></i> Tokens emitidos</span>
        <form method="get" class="form-check mb-0" style="font-size:.8rem">
          <input class="form-check-input" type="checkbox" name="revogados" value="1" id="chk-revogados"
                 <?= $mostrarRevogados ? 'checked' : '' ?> onchange="this.form.submit()">
          <label class="form-check-label" for="chk-revogados">mostrar tokens revogados</label>
        </form>
      </div>
      <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0" style="font-size:.84rem">
          <thead><tr><th>Identificação</th><th>Token</th><th>Nível</th><th>Criado</th><th>Status</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($tokens as $t): ?>
            <tr class="<?= $t['status'] === 'Revogado' ? 'text-muted' : '' ?>">
              <td><b><?= e_html($t['descricao']) ?></b></td>
              <td><code style="font-size:.76rem"><?= e_html($t['token']) ?></code></td>
              <td><?= badge($nivelRotulo[$t['nivel']], $nivelCor[$t['nivel']]) ?></td>
              <td style="font-size:.78rem"><?= date('d/m/Y', strtotime($t['criado_em'])) ?><br>
                <span class="text-muted" style="font-size:.7rem">por <?= e_html($t['criado_por']) ?></span></td>
              <td><?= badge_status($t['status'] === 'Ativo' ? 'Ativo' : 'Encerrado') ?>
                <?= $t['revogado_em'] ? '<br><span class="text-muted" style="font-size:.7rem">em ' . date('d/m/Y H:i', strtotime($t['revogado_em'])) . '</span>' : '' ?></td>
              <td class="text-end">
                <?php if ($t['status'] === 'Ativo'): ?>
                  <form method="post" onsubmit="return confirm('Revogar este acesso? O cotista perde a visão imediatamente.')">
                    <input type="hidden" name="revogar" value="<?= (int)$t['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-circle me-1"></i>Revogar</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$tokens): ?><tr><td colspan="6" class="text-muted text-center py-4">Nenhum token emitido ainda.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <p class="text-muted mt-2" style="font-size:.75rem">
      O que o cotista vê com o token: evolução do fundo contra o benchmark e composição da carteira por classe —
      na data de corte do nível escolhido. Ele não vê outros cotistas, caixa ou operações.</p>
  </div>
</div>
<?php page_end();
