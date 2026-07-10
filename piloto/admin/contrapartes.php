<?php
// Cadastro/habilitação de CONTRAPARTES (perfil admin = papel de compliance/risco da administradora).
// A mesa do gestor só pode boletar balcão contra contraparte APROVADA. Aqui a administradora
// cadastra, faz o KYC/PLD (Res. CVM 50), define o limite de crédito e aprova/suspende.
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

$u = exigir_perfil('admin');
ensure_contrapartes($pdo);

$msg = ''; $msgTipo = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_validar()) {
    $_POST = []; $msg = 'Requisição inválida (proteção CSRF). Recarregue a página.'; $msgTipo = 'danger';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $msgTipo !== 'danger') {
    // aprovar / suspender / reativar
    if (!empty($_POST['acao']) && !empty($_POST['id'])) {
        $id = (int) $_POST['id'];
        if ($_POST['acao'] === 'aprovar') {
            $pdo->prepare("UPDATE contrapartes SET status='Aprovada', aprovada_por=?, aprovada_em=NOW() WHERE id=?")
                ->execute([$u['nome'], $id]);
            $msg = 'Contraparte aprovada — a mesa já pode operar balcão contra ela.';
        } elseif ($_POST['acao'] === 'suspender') {
            $pdo->prepare("UPDATE contrapartes SET status='Suspensa' WHERE id=?")->execute([$id]);
            $msg = 'Contraparte suspensa — novas boletas de balcão contra ela ficam bloqueadas.'; $msgTipo = 'warning';
        } elseif ($_POST['acao'] === 'reativar') {
            $pdo->prepare("UPDATE contrapartes SET status='Aprovada', aprovada_por=?, aprovada_em=NOW() WHERE id=?")
                ->execute([$u['nome'], $id]);
            $msg = 'Contraparte reativada.';
        } elseif ($_POST['acao'] === 'limite') {
            $lim = (float) str_replace(['.', ','], ['', '.'], $_POST['limite_credito'] ?? '0');
            $pdo->prepare("UPDATE contrapartes SET limite_credito=? WHERE id=?")->execute([$lim, $id]);
            $msg = 'Limite de crédito atualizado.';
        }
    }
    // novo cadastro
    elseif (!empty($_POST['cadastrar'])) {
        $razao = trim($_POST['razao_social'] ?? '');
        $papeis = array_intersect(array_keys(cp_papeis()), (array) ($_POST['papeis'] ?? []));
        if ($razao === '' || !$papeis) {
            $msg = 'Informe a razão social e ao menos um papel.'; $msgTipo = 'danger';
        } else {
            $lim = (float) str_replace(['.', ','], ['', '.'], $_POST['limite_credito'] ?? '0');
            $pdo->prepare("INSERT INTO contrapartes
                (razao_social, nome_fantasia, cnpj, tipo_instituicao, papeis, camara, rating, limite_credito,
                 contrato_mestre, beneficiario_final, kyc_status, status, observacao)
                VALUES (?,?,?,?,?,?,?,?,?,?,?, 'Pendente', ?)")
                ->execute([
                    $razao, trim($_POST['nome_fantasia'] ?? ''), trim($_POST['cnpj'] ?? ''),
                    $_POST['tipo_instituicao'] ?? 'Banco', implode(',', $papeis), $_POST['camara'] ?? 'B3_BALCAO',
                    trim($_POST['rating'] ?? ''), $lim, $_POST['contrato_mestre'] ?? 'Nenhum',
                    trim($_POST['beneficiario_final'] ?? ''), $_POST['kyc_status'] ?? 'Pendente',
                    trim($_POST['observacao'] ?? ''),
                ]);
            $msg = 'Contraparte cadastrada em análise. Conclua o KYC e aprove para liberar a mesa.';
        }
    }
}

$lista = $pdo->query("SELECT * FROM contrapartes ORDER BY FIELD(status,'Pendente','Aprovada','Suspensa'), razao_social")->fetchAll();
$camaras = ['B3_CCP' => 'Câmara B3 (CCP)', 'B3_BALCAO' => 'B3 Balcão (CETIP)', 'SELIC' => 'SELIC', 'bilateral' => 'Bilateral'];

page_start('Contrapartes', 'Contrapartes', $u,
    'Conheça sua contraparte · KYC/PLD (Res. CVM 50), limite de crédito e aprovação antes de a mesa operar balcão');
?>

<?php if ($msg): ?><div class="alert alert-<?= $msgTipo ?> py-2"><i class="bi bi-info-circle me-1"></i><?= e_html($msg) ?></div><?php endif; ?>

<div class="alert alert-secondary py-2" style="font-size:.82rem"><i class="bi bi-shield-check me-1"></i>
  <b>Bolsa</b> (ação, ETF, futuro) não tem contraparte bilateral: a Câmara B3 é a contraparte central (CCP) e o que se registra é a <b>corretora executora</b>.
  <b>Balcão</b> (CDB, debênture, CRI/CRA, swap) tem contraparte nominal — precisa de KYC, limite e aprovação aqui.</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header"><i class="bi bi-plus-circle me-1"></i> Cadastrar contraparte</div>
      <div class="card-body">
        <form method="post">
          <?= csrf_campo() ?>
          <input type="hidden" name="cadastrar" value="1">
          <div class="mb-2"><label class="form-label" style="font-size:.78rem">Razão social *</label>
            <input class="form-control form-control-sm" name="razao_social" required placeholder="Banco Exemplo S.A."></div>
          <div class="row g-2">
            <div class="col-7 mb-2"><label class="form-label" style="font-size:.78rem">Nome fantasia</label>
              <input class="form-control form-control-sm" name="nome_fantasia" placeholder="Banco Exemplo"></div>
            <div class="col-5 mb-2"><label class="form-label" style="font-size:.78rem">CNPJ</label>
              <input class="form-control form-control-sm" name="cnpj" placeholder="00.000.000/0001-00"></div>
          </div>
          <div class="row g-2">
            <div class="col-6 mb-2"><label class="form-label" style="font-size:.78rem">Tipo</label>
              <select class="form-select form-select-sm" name="tipo_instituicao">
                <?php foreach (['Banco', 'Corretora', 'Distribuidora', 'Gestora', 'Emissor', 'Tesouraria'] as $t): ?>
                  <option><?= $t ?></option><?php endforeach; ?>
              </select></div>
            <div class="col-6 mb-2"><label class="form-label" style="font-size:.78rem">Câmara</label>
              <select class="form-select form-select-sm" name="camara">
                <?php foreach ($camaras as $k => $v): ?><option value="<?= $k ?>"><?= e_html($v) ?></option><?php endforeach; ?>
              </select></div>
          </div>
          <label class="form-label" style="font-size:.78rem">Papéis *</label>
          <div class="border rounded p-2 mb-2" style="font-size:.78rem">
            <?php foreach (cp_papeis() as $k => $v): ?>
              <div class="form-check"><input class="form-check-input" type="checkbox" name="papeis[]" value="<?= $k ?>" id="p_<?= $k ?>">
                <label class="form-check-label" for="p_<?= $k ?>"><?= e_html($v) ?></label></div>
            <?php endforeach; ?>
          </div>
          <div class="row g-2">
            <div class="col-4 mb-2"><label class="form-label" style="font-size:.78rem">Rating</label>
              <input class="form-control form-control-sm" name="rating" placeholder="AAA"></div>
            <div class="col-8 mb-2"><label class="form-label" style="font-size:.78rem">Limite de crédito (R$)</label>
              <input class="form-control form-control-sm" name="limite_credito" placeholder="5.000.000,00"></div>
          </div>
          <div class="row g-2">
            <div class="col-6 mb-2"><label class="form-label" style="font-size:.78rem">Contrato-mestre</label>
              <select class="form-select form-select-sm" name="contrato_mestre"><option>Nenhum</option><option>CGD</option><option>ISDA</option></select></div>
            <div class="col-6 mb-2"><label class="form-label" style="font-size:.78rem">KYC</label>
              <select class="form-select form-select-sm" name="kyc_status"><option>Pendente</option><option>Concluído</option></select></div>
          </div>
          <div class="mb-2"><label class="form-label" style="font-size:.78rem">Beneficiário final (UBO)</label>
            <input class="form-control form-control-sm" name="beneficiario_final" placeholder="Sócio(s) controlador(es)"></div>
          <div class="mb-2"><label class="form-label" style="font-size:.78rem">Observação</label>
            <input class="form-control form-control-sm" name="observacao" placeholder="Notas de risco/compliance"></div>
          <button class="btn btn-dark btn-sm w-100"><i class="bi bi-save me-1"></i>Cadastrar (entra em análise)</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card">
      <div class="card-header"><i class="bi bi-people me-1"></i> Contrapartes cadastradas</div>
      <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0" style="font-size:.82rem">
          <thead><tr><th>Contraparte</th><th>Papéis · câmara</th><th class="text-end">Exposição / limite</th><th>KYC</th><th>Status</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($lista as $c):
              $exp = cp_exposicao($pdo, (int) $c['id']);
              $lim = (float) $c['limite_credito'];
              $estourado = $lim > 0 && $exp > $lim; ?>
            <tr>
              <td><b><?= e_html($c['nome_fantasia'] ?: $c['razao_social']) ?></b>
                <?php if ($c['rating']): ?><span class="badge bg-light text-dark border ms-1"><?= e_html($c['rating']) ?></span><?php endif; ?>
                <br><span class="text-muted" style="font-size:.72rem"><?= e_html($c['cnpj'] ?: '—') ?> · <?= e_html($c['tipo_instituicao']) ?>
                <?= $c['contrato_mestre'] && $c['contrato_mestre'] !== 'Nenhum' ? '· ' . e_html($c['contrato_mestre']) : '' ?></span></td>
              <td style="font-size:.74rem">
                <?php foreach (explode(',', (string) $c['papeis']) as $p): if ($p === '') continue; ?>
                  <span class="badge bg-secondary-subtle text-dark border mb-1"><?= e_html(cp_papel_label($p)) ?></span><br>
                <?php endforeach; ?>
                <span class="text-muted"><?= e_html($camaras[$c['camara']] ?? $c['camara']) ?></span></td>
              <td class="text-end">
                <?php if ($lim > 0): ?>
                  <span class="<?= $estourado ? 'text-danger fw-bold' : '' ?>"><?= moeda($exp) ?></span>
                  <br><span class="text-muted" style="font-size:.72rem">de <?= moeda($lim) ?></span>
                <?php else: ?><span class="text-muted">—</span><?php endif; ?>
              </td>
              <td><?= $c['kyc_status'] === 'Concluído'
                    ? '<span class="badge bg-success-subtle text-success border">Concluído</span>'
                    : '<span class="badge bg-warning-subtle text-warning border">Pendente</span>' ?>
                <?php if ($c['kyc_validade']): ?><br><span class="text-muted" style="font-size:.68rem">val. <?= data_br($c['kyc_validade']) ?></span><?php endif; ?></td>
              <td><?= badge_status($c['status'] === 'Aprovada' ? 'Aprovado' : ($c['status'] === 'Suspensa' ? 'Erro' : 'Pendente')) ?>
                <span class="d-none"><?= e_html($c['status']) ?></span></td>
              <td class="text-end">
                <form method="post" class="d-inline"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                  <?php if ($c['status'] !== 'Aprovada'): ?>
                    <button name="acao" value="aprovar" class="btn btn-sm btn-outline-success" title="Aprovar"><i class="bi bi-check-lg"></i></button>
                  <?php endif; ?>
                  <?php if ($c['status'] === 'Aprovada'): ?>
                    <button name="acao" value="suspender" class="btn btn-sm btn-outline-danger" title="Suspender"><i class="bi bi-pause"></i></button>
                  <?php elseif ($c['status'] === 'Suspensa'): ?>
                    <button name="acao" value="reativar" class="btn btn-sm btn-outline-secondary" title="Reativar"><i class="bi bi-arrow-counterclockwise"></i></button>
                  <?php endif; ?>
                </form>
              </td>
            </tr>
            <?php if ($c['observacao']): ?><tr><td colspan="6" class="text-muted pt-0" style="font-size:.72rem;border-top:0"><i class="bi bi-info-circle me-1"></i><?= e_html($c['observacao']) ?></td></tr><?php endif; ?>
          <?php endforeach; ?>
          <?php if (!$lista): ?><tr><td colspan="6" class="text-muted text-center py-4">Nenhuma contraparte cadastrada.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php page_end();
