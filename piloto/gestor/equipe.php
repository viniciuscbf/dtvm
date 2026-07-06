<?php
// Equipe do fundo — o gestor principal convida contas (por account_id), define
// permissões por checkbox, transfere a gestão principal e remove membros.
// Qualquer gestor vê aqui o seu account_id e os convites recebidos (aceitar/recusar).
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

$u = exigir_perfil('gestor', 'admin');
ensure_equipe($pdo);

$meus  = fundos_do_usuario($pdo, $u);
$fundo = fundo_do_usuario($pdo, $u);          // fundo em foco (pode ser null se sem fundo)
$fid   = $fundo ? (int)$fundo['id'] : 0;
$souPrincipal = $fid ? eh_principal($pdo, $u, $fid) : false;

$msg = ''; $msgTipo = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_validar()) {
    $_POST = [];
    $msg = 'Requisição inválida (proteção CSRF). Recarregue a página.'; $msgTipo = 'danger';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    // --- responder convite (qualquer gestor, independe do fundo em foco) ---
    if ($acao === 'convite') {
        [$ok, $m] = responder_convite($pdo, (int)($_POST['membro_id'] ?? 0), (int)$u['id'], !empty($_POST['aceitar']));
        $msg = $m; $msgTipo = $ok ? 'success' : 'warning';
        // se aceitou, foca o fundo recém-aceito
        if ($ok && !empty($_POST['aceitar'])) { $_SESSION['gestor_fundo_id'] = (int)($_POST['fundo_id'] ?? 0); }
        registrar_auditoria($pdo, 'convite_respondido', ['entidade' => 'fundo_membros', 'entidade_id' => (int)($_POST['membro_id'] ?? 0),
            'detalhe' => $m]);
    }
    // --- ações do principal sobre o fundo em foco ---
    elseif ($fid && $souPrincipal && $acao === 'convidar') {
        [$ok, $m] = convidar_membro($pdo, $fid, $_POST['account_id'] ?? '', (int)$u['id']);
        $msg = $m; $msgTipo = $ok ? 'success' : 'warning';
        if ($ok) registrar_auditoria($pdo, 'membro_convidado', ['entidade' => 'fundo_membros', 'fundo_id' => $fid, 'detalhe' => $m]);
    } elseif ($fid && $souPrincipal && $acao === 'permissoes') {
        $perms = array_keys($_POST['perm'] ?? []);   // checkboxes marcados
        [$ok, $m] = definir_permissoes($pdo, $fid, (int)($_POST['membro_id'] ?? 0), $perms);
        $msg = $m; $msgTipo = $ok ? 'success' : 'warning';
        if ($ok) registrar_auditoria($pdo, 'permissoes_alteradas', ['entidade' => 'fundo_membros', 'entidade_id' => (int)($_POST['membro_id'] ?? 0),
            'fundo_id' => $fid, 'detalhe' => 'Permissões: ' . implode(', ', $perms)]);
    } elseif ($fid && $souPrincipal && $acao === 'transferir') {
        [$ok, $m] = transferir_principal($pdo, $fid, (int)($_POST['usuario_id'] ?? 0), (int)$u['id']);
        $msg = $m; $msgTipo = $ok ? 'success' : 'warning';
        if ($ok) { $souPrincipal = false; registrar_auditoria($pdo, 'gestao_transferida', ['entidade' => 'fundo_membros', 'fundo_id' => $fid, 'detalhe' => $m]); }
    } elseif ($fid && $souPrincipal && $acao === 'remover') {
        [$ok, $m] = remover_membro($pdo, $fid, (int)($_POST['membro_id'] ?? 0));
        $msg = $m; $msgTipo = $ok ? 'success' : 'warning';
        if ($ok) registrar_auditoria($pdo, 'membro_removido', ['entidade' => 'fundo_membros', 'entidade_id' => (int)($_POST['membro_id'] ?? 0), 'fundo_id' => $fid, 'detalhe' => $m]);
    } else {
        $msg = 'Ação não permitida para o seu papel neste fundo.'; $msgTipo = 'warning';
    }
    // recarrega estado após a ação
    $meus  = fundos_do_usuario($pdo, $u);
    $fundo = $fid ? (function() use ($pdo,$u,$fid){ foreach (fundos_do_usuario($pdo,$u) as $f) if((int)$f['id']===$fid) return $f; return fundo_do_usuario($pdo,$u); })() : fundo_do_usuario($pdo, $u);
    $fid   = $fundo ? (int)$fundo['id'] : 0;
    $souPrincipal = $fid ? eh_principal($pdo, $u, $fid) : false;
}

$meuAccount = $pdo->query("SELECT account_id FROM usuarios WHERE id=" . (int)$u['id'])->fetchColumn();
$convites   = convites_do_usuario($pdo, (int)$u['id']);
$membros    = $fid ? membros_do_fundo($pdo, $fid) : [];
$catalogo   = permissoes_fundo();
$minhaMemb  = $fid ? membership($pdo, (int)$u['id'], $fid) : null;

page_start('Equipe do fundo', 'Equipe do fundo', $u,
    $fundo ? e_html($fundo['nome']) . ' · gestão de acessos, permissões e transferência da gestão principal'
           : 'Gerencie sua conta, convites e a equipe dos seus fundos');
?>

<?php if ($msg): ?><div class="alert alert-<?= e_html($msgTipo) ?> py-2"><i class="bi bi-info-circle me-1"></i><?= e_html($msg) ?></div><?php endif; ?>

<!-- Minha conta (account_id para receber convites) -->
<div class="card mb-4">
  <div class="card-header"><i class="bi bi-person-badge me-1"></i> Minha conta</div>
  <div class="card-body d-flex flex-wrap gap-4 align-items-center">
    <div>
      <div class="text-muted" style="font-size:.75rem;font-weight:700">SEU ACCOUNT ID</div>
      <div class="d-flex align-items-center gap-2">
        <code style="font-size:1.1rem;background:#0f172a;color:#e2e8f0;padding:4px 10px;border-radius:6px"><?= e_html($meuAccount) ?></code>
        <button class="btn btn-sm btn-outline-secondary" onclick="navigator.clipboard&&navigator.clipboard.writeText('<?= e_html($meuAccount) ?>')" title="Copiar"><i class="bi bi-clipboard"></i></button>
      </div>
      <div class="text-muted" style="font-size:.75rem">Compartilhe com um gestor principal para ser convidado a um fundo.</div>
    </div>
    <div class="vr d-none d-md-block"></div>
    <div>
      <div class="text-muted" style="font-size:.75rem;font-weight:700">FUNDOS EM QUE PARTICIPO</div>
      <div style="font-size:.9rem"><b><?= count($meus) ?></b> fundo(s)
        <?php if (!$meus): ?><span class="text-muted">— você ainda não participa de nenhum. Aceite um convite ou <a href="<?= BASE_URL ?>gestor/novo_fundo.php">crie o seu primeiro fundo</a>.</span><?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Convites recebidos -->
<?php if ($convites): ?>
<div class="card mb-4 border-warning">
  <div class="card-header text-warning"><i class="bi bi-envelope-paper me-1"></i> Convites para participar de um fundo (<?= count($convites) ?>)</div>
  <div class="card-body p-0">
    <table class="table table-hover align-middle mb-0" style="font-size:.86rem">
      <thead><tr><th>Fundo</th><th>Gestora</th><th>Convidado por</th><th class="text-end">Responder</th></tr></thead>
      <tbody>
      <?php foreach ($convites as $c): ?>
        <tr>
          <td><b><?= e_html($c['fundo_nome']) ?></b></td>
          <td style="font-size:.82rem"><?= e_html($c['gestora'] ?: '—') ?></td>
          <td style="font-size:.82rem"><?= e_html($c['convidante'] ?: '—') ?></td>
          <td class="text-end">
            <form method="post" class="d-inline">
              <?= csrf_campo() ?>
              <input type="hidden" name="acao" value="convite">
              <input type="hidden" name="membro_id" value="<?= (int)$c['id'] ?>">
              <input type="hidden" name="fundo_id" value="<?= (int)$c['fundo_id'] ?>">
              <button name="aceitar" value="1" class="btn btn-sm btn-success"><i class="bi bi-check-lg me-1"></i>Aceitar</button>
              <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer text-muted" style="font-size:.72rem">Ao aceitar, você entra <b>sem permissões</b> — o gestor principal libera cada acesso.</div>
</div>
<?php endif; ?>

<?php if (!$fundo): ?>
  <div class="alert alert-secondary">Selecione ou aceite um fundo para gerenciar a equipe. Você também pode
    <a href="<?= BASE_URL ?>gestor/novo_fundo.php">criar um novo fundo</a> — você será o gestor principal dele.</div>
<?php else: ?>

<!-- Membros do fundo em foco -->
<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-people me-1"></i> Membros de <b><?= e_html($fundo['nome']) ?></b></span>
    <?php if (!$souPrincipal && $minhaMemb): ?>
      <span class="badge bg-secondary">Você é membro — permissões definidas pelo principal</span>
    <?php endif; ?>
  </div>
  <div class="card-body p-0">
    <table class="table table-hover align-middle mb-0" style="font-size:.85rem">
      <thead><tr><th>Membro</th><th>Papel</th><th>Status</th><th>Permissões</th><th class="text-end"></th></tr></thead>
      <tbody>
      <?php foreach ($membros as $m):
          $perms = json_decode($m['permissoes'] ?? '[]', true) ?: [];
          $ehPrinc = $m['papel'] === 'principal'; ?>
        <tr>
          <td><b><?= e_html($m['nome']) ?></b><br>
            <span class="text-muted" style="font-size:.75rem"><?= e_html($m['email']) ?> · <code><?= e_html($m['account_id']) ?></code></span></td>
          <td><?= $ehPrinc ? badge('Principal', 'gold') : badge('Membro', 'secondary') ?></td>
          <td><?= badge_status($m['status']) ?></td>
          <td style="font-size:.8rem">
            <?php if ($ehPrinc): ?>
              <span class="text-muted">todas as permissões</span>
            <?php elseif ($m['status'] !== 'Ativo'): ?>
              <span class="text-muted">—</span>
            <?php elseif (!$perms): ?>
              <span class="text-danger">nenhuma</span>
            <?php else: ?>
              <?= count($perms) ?> de <?= count($catalogo) ?>
            <?php endif; ?>
          </td>
          <td class="text-end">
            <?php if ($souPrincipal && !$ehPrinc && $m['status'] === 'Ativo'): ?>
              <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#perm<?= (int)$m['id'] ?>">
                <i class="bi bi-sliders me-1"></i>Permissões</button>
              <form method="post" class="d-inline" onsubmit="return confirm('Transferir a GESTÃO PRINCIPAL para <?= e_html($m['nome']) ?>? Você passa a membro.')">
                <?= csrf_campo() ?><input type="hidden" name="acao" value="transferir"><input type="hidden" name="usuario_id" value="<?= (int)$m['usuario_id'] ?>">
                <button class="btn btn-sm btn-outline-warning" title="Tornar gestor principal"><i class="bi bi-arrow-left-right"></i></button>
              </form>
              <form method="post" class="d-inline" onsubmit="return confirm('Remover <?= e_html($m['nome']) ?> do fundo?')">
                <?= csrf_campo() ?><input type="hidden" name="acao" value="remover"><input type="hidden" name="membro_id" value="<?= (int)$m['id'] ?>">
                <button class="btn btn-sm btn-outline-danger" title="Remover"><i class="bi bi-person-x"></i></button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php if ($souPrincipal && !$ehPrinc && $m['status'] === 'Ativo'): ?>
        <tr class="collapse" id="perm<?= (int)$m['id'] ?>">
          <td colspan="5" style="background:#f8fafc">
            <form method="post" class="row g-2">
              <?= csrf_campo() ?>
              <input type="hidden" name="acao" value="permissoes">
              <input type="hidden" name="membro_id" value="<?= (int)$m['id'] ?>">
              <div class="col-12"><div class="text-muted mb-1" style="font-size:.74rem;font-weight:700">
                MARQUE AS PERMISSÕES DE <?= e_html(mb_strtoupper($m['nome'])) ?> (visualizações e ações)</div></div>
              <?php foreach ($catalogo as $chave => [$rot, $tipo]): ?>
                <div class="col-md-6 col-lg-4">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="perm[<?= e_html($chave) ?>]" value="1"
                           id="p<?= (int)$m['id'] ?>_<?= e_html($chave) ?>" <?= in_array($chave, $perms, true) ? 'checked' : '' ?>>
                    <label class="form-check-label" style="font-size:.82rem" for="p<?= (int)$m['id'] ?>_<?= e_html($chave) ?>">
                      <?= e_html($rot) ?> <span class="badge bg-<?= $tipo === 'acao' ? 'primary' : 'info' ?>-subtle text-<?= $tipo === 'acao' ? 'primary' : 'info' ?>-emphasis" style="font-size:.62rem"><?= $tipo === 'acao' ? 'ação' : 'visão' ?></span>
                    </label>
                  </div>
                </div>
              <?php endforeach; ?>
              <div class="col-12"><button class="btn btn-sm btn-dark mt-1"><i class="bi bi-save me-1"></i>Salvar permissões</button></div>
            </form>
          </td>
        </tr>
        <?php endif; ?>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Convidar (só principal) -->
<?php if ($souPrincipal): ?>
<div class="card mb-2">
  <div class="card-header"><i class="bi bi-person-plus me-1"></i> Convidar uma conta para este fundo</div>
  <div class="card-body">
    <form method="post" class="row g-2 align-items-end" style="max-width:560px">
      <?= csrf_campo() ?>
      <input type="hidden" name="acao" value="convidar">
      <div class="col-sm-8">
        <label class="form-label mb-1" style="font-size:.8rem">Account ID do convidado</label>
        <input name="account_id" class="form-control form-control-sm" placeholder="G-XXXXXXXX" required
               style="text-transform:uppercase">
      </div>
      <div class="col-sm-4">
        <button class="btn btn-sm btn-dark w-100"><i class="bi bi-send me-1"></i>Enviar convite</button>
      </div>
    </form>
    <p class="text-muted mb-0 mt-2" style="font-size:.78rem">
      A pessoa precisa ter uma <b>conta de gestor</b>. Ela recebe o convite na própria tela de Equipe e decide aceitar.
      Ao aceitar, entra <b>sem permissões</b> — você libera cada acesso por checkbox acima.</p>
  </div>
</div>
<?php elseif ($minhaMemb): ?>
<div class="alert alert-light border" style="font-size:.83rem">
  <i class="bi bi-info-circle me-1"></i> Você participa deste fundo como <b>membro</b>. A gestão de convites, permissões e
  transferência é exclusiva do <b>gestor principal</b>.
</div>
<?php endif; ?>

<?php endif; /* fundo */ ?>
<?php page_end();
