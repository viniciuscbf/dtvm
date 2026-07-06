<?php
// Assembleias de cotistas — o gestor solicita (ex.: alteração de regulamento) e acompanha
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

$u = exigir_perfil('gestor', 'admin');
$fundo = fundo_do_usuario($pdo, $u);
if (!$fundo) die('Sem fundo vinculado.');
exigir_fundo_ativo($fundo);
$fid = (int)$fundo['id'];

$msg = '';
$erroSeg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_validar()) {
    $_POST = [];
    $erroSeg = 'Requisição inválida (proteção CSRF). Recarregue a página e tente novamente.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['pauta'])) {
    $pdo->prepare("INSERT INTO assembleias (fundo_id, tipo, pauta, origem, status, criado_por)
                   VALUES (?, 'AGE', ?, 'Solicitação do gestor', 'Solicitada', ?)")
        ->execute([$fid, trim($_POST['pauta']), $u['nome']]);
    $msg = 'Solicitação registrada — a administradora vai analisar e convocar a assembleia.';
}

$st = $pdo->prepare('SELECT * FROM assembleias WHERE fundo_id = ? ORDER BY criado_em DESC');
$st->execute([$fid]);
$assembleias = $st->fetchAll();

page_start('Assembleias de cotistas', 'Assembleias', $u,
    e_html($fundo['nome']) . ' · alterações de regulamento, taxas e prestadores passam por deliberação dos cotistas');
?>

<?php if ($msg): ?><div class="alert alert-success py-2"><i class="bi bi-check-circle me-1"></i><?= e_html($msg) ?></div><?php endif; ?>
<?php if ($erroSeg): ?><div class="alert alert-warning py-2"><i class="bi bi-exclamation-triangle me-1"></i><?= e_html($erroSeg) ?></div><?php endif; ?>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header"><i class="bi bi-plus-circle me-1"></i> Solicitar assembleia (AGE)</div>
      <div class="card-body">
        <form method="post">
          <?= csrf_campo() ?>
          <label class="form-label" style="font-size:.8rem">Pauta da deliberação</label>
          <select class="form-select form-select-sm mb-2" onchange="if(this.value)document.getElementById('pauta').value=this.value">
            <option value="">Modelos de pauta…</option>
            <option>Alteração do regulamento — política de investimento</option>
            <option>Alteração da taxa de gestão / performance</option>
            <option>Substituição do auditor independente</option>
            <option>Criação de subclasse de cotas</option>
            <option>Alteração do público-alvo</option>
            <option>Incorporação / transformação do fundo</option>
          </select>
          <textarea class="form-control form-control-sm" id="pauta" name="pauta" rows="3" required
                    placeholder="Descreva a alteração pretendida (ex.: incluir CRI/CRA no limite de crédito privado até 40% do PL)"></textarea>
          <button class="btn btn-sm btn-dark w-100 mt-3"><i class="bi bi-send me-1"></i>Enviar à administradora</button>
        </form>
        <div class="alert alert-light border mt-3 mb-0" style="font-size:.74rem">
          <i class="bi bi-info-circle me-1 text-primary"></i>Pela Res. CVM 175, mudanças de regulamento, taxas e
          prestadores essenciais dependem de <b>assembleia de cotistas</b> (pode ser 100% eletrônica). A administradora
          convoca com antecedência, conduz a votação e registra a ata.
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card">
      <div class="card-header"><i class="bi bi-megaphone me-1"></i> Assembleias do fundo</div>
      <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0" style="font-size:.85rem">
          <thead><tr><th>Pauta</th><th>Origem</th><th>Convocação / Realização</th><th>Status</th><th>Resultado</th></tr></thead>
          <tbody>
          <?php foreach ($assembleias as $a): ?>
            <tr>
              <td style="font-size:.84rem"><?= badge($a['tipo'], 'secondary') ?> <?= e_html($a['pauta']) ?></td>
              <td style="font-size:.78rem"><?= e_html($a['origem']) ?></td>
              <td style="font-size:.78rem">
                <?= $a['data_convocacao'] ? data_br($a['data_convocacao']) : '—' ?> /
                <?= $a['data_realizacao'] ? data_br($a['data_realizacao']) : '—' ?>
                <br><span class="text-muted" style="font-size:.7rem"><?= e_html($a['modo']) ?></span></td>
              <td><?= badge($a['status'], ['Solicitada' => 'warning', 'Convocada' => 'info', 'Realizada' => 'success', 'Cancelada' => 'secondary'][$a['status']]) ?></td>
              <td style="font-size:.8rem"><?= e_html($a['resultado'] ?? '—') ?>
                <?= $a['quorum'] ? '<br><span class="text-muted" style="font-size:.7rem">quórum: ' . e_html($a['quorum']) . '</span>' : '' ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$assembleias): ?><tr><td colspan="5" class="text-muted text-center py-4">Nenhuma assembleia registrada para este fundo.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php page_end();
