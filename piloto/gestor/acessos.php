<?php
// Acessos de cotistas — modelo de CONTA (e-mail/senha) + transparência GLOBAL do fundo.
//  · A transparência da carteira é POLÍTICA DO FUNDO (vale p/ todos os cotistas): tempo real,
//    defasada 1m/3m ou não divulgada — o gestor define aqui (substitui o nível por token).
//  · Cada cotista pode ter uma CONTA de acesso (cotista_contas) vinculada às suas posições
//    (cotistas.conta_id) — a mesma conta enxerga todos os fundos em que investe.
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

$u = exigir_perfil('gestor', 'admin');
$fundo = fundo_do_usuario($pdo, $u);
if (!$fundo) die('Sem fundo vinculado.');
exigir_fundo_ativo($fundo);
$fid = (int)$fundo['id'];
exigir_permissao($pdo, $u, $fid, 'gerir_cotistas');
ensure_contas_cotista($pdo);   // DDL fora de transação

$msg = ''; $msgTipo = 'success'; $senhaProvisoria = null; $emailNovo = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_validar()) {
    $_POST = []; $msg = 'Requisição inválida (proteção CSRF). Recarregue a página.'; $msgTipo = 'danger';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['acao'] ?? '') === 'transparencia') {
        $t = in_array($_POST['transparencia'] ?? '', ['realtime', 'delay_1m', 'delay_3m', 'off'], true) ? $_POST['transparencia'] : 'delay_1m';
        $pdo->prepare('UPDATE fundos SET transparencia = ? WHERE id = ?')->execute([$t, $fid]);
        $fundo['transparencia'] = $t;
        registrar_auditoria($pdo, 'transparencia_alterada', ['entidade' => 'fundo', 'entidade_id' => $fid, 'fundo_id' => $fid,
            'detalhe' => 'Transparência do portal do cotista: ' . rotulo_transparencia($t)]);
        $msg = 'Política de transparência atualizada: ' . rotulo_transparencia($t) . ' — vale para todos os cotistas do fundo.';
    } elseif (($_POST['acao'] ?? '') === 'criar_acesso') {
        $cid = (int)($_POST['cotista_id'] ?? 0);
        $email = strtolower(trim($_POST['email'] ?? ''));
        $st = $pdo->prepare('SELECT * FROM cotistas WHERE id = ? AND fundo_id = ?');
        $st->execute([$cid, $fid]);
        $cot = $st->fetch();
        if (!$cot || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msg = 'Selecione um cotista e informe um e-mail válido.'; $msgTipo = 'warning';
        } else {
            // conta já existe com este e-mail? então só VINCULA (mesma pessoa, outro fundo)
            $st = $pdo->prepare('SELECT * FROM cotista_contas WHERE email = ?');
            $st->execute([$email]);
            if ($cc = $st->fetch()) {
                $pdo->prepare('UPDATE cotistas SET conta_id = ? WHERE id = ?')->execute([(int)$cc['id'], $cid]);
                registrar_auditoria($pdo, 'conta_cotista_vinculada', ['entidade' => 'cotista', 'entidade_id' => $cid, 'fundo_id' => $fid,
                    'detalhe' => "Posição de {$cot['nome']} vinculada à conta existente $email"]);
                $msg = "Posição vinculada à conta existente de $email — o cotista já enxerga este fundo no portal.";
            } else {
                // cria a conta com senha PROVISÓRIA forte, exibida UMA única vez
                $senhaProvisoria = 'Arg' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4)) . '@' . random_int(10, 99);
                $emailNovo = $email;
                $pdo->prepare('INSERT INTO cotista_contas (nome, documento, email, senha_hash) VALUES (?,?,?,?)')
                    ->execute([$cot['nome'], $cot['documento'] ?: '—', $email, password_hash($senhaProvisoria, PASSWORD_BCRYPT)]);
                $novaConta = (int)$pdo->lastInsertId();
                $pdo->prepare('UPDATE cotistas SET conta_id = ? WHERE id = ?')->execute([$novaConta, $cid]);
                registrar_auditoria($pdo, 'conta_cotista_criada', ['entidade' => 'cotista', 'entidade_id' => $cid, 'fundo_id' => $fid,
                    'detalhe' => "Conta de acesso criada p/ {$cot['nome']} ($email)"]);
                $msg = 'Conta criada e vinculada — envie a senha provisória ao cotista com segurança (exibida uma única vez).';
            }
        }
    } elseif (!empty($_POST['bloquear_conta'])) {
        $pdo->prepare("UPDATE cotista_contas SET status = 'Bloqueada' WHERE id = ?")->execute([(int)$_POST['bloquear_conta']]);
        registrar_auditoria($pdo, 'conta_cotista_bloqueada', ['entidade' => 'cotista_conta', 'entidade_id' => (int)$_POST['bloquear_conta'], 'fundo_id' => $fid]);
        $msg = 'Conta bloqueada — o acesso cai imediatamente (todas as sessões são revalidadas no banco).'; $msgTipo = 'warning';
    } elseif (!empty($_POST['reativar_conta'])) {
        $pdo->prepare("UPDATE cotista_contas SET status = 'Ativa' WHERE id = ?")->execute([(int)$_POST['reativar_conta']]);
        $msg = 'Conta reativada.';
    } elseif (!empty($_POST['desvincular'])) {
        $pdo->prepare('UPDATE cotistas SET conta_id = NULL WHERE id = ? AND fundo_id = ?')->execute([(int)$_POST['desvincular'], $fid]);
        $msg = 'Vínculo removido — o cotista deixa de ver ESTE fundo no portal (a conta continua existindo).'; $msgTipo = 'secondary';
    }
}

$st = $pdo->prepare("SELECT c.*, cc.email conta_email, cc.status conta_status, cc.ultimo_acesso
                     FROM cotistas c LEFT JOIN cotista_contas cc ON cc.id = c.conta_id
                     WHERE c.fundo_id = ? ORDER BY c.cotas DESC");
$st->execute([$fid]);
$cotistas = $st->fetchAll();
$comConta = count(array_filter($cotistas, fn($c) => !empty($c['conta_email'])));

$transAtual = $fundo['transparencia'] ?? 'delay_1m';

page_start('Acessos de cotistas', 'Acessos de cotistas', $u,
    e_html($fundo['nome']) . ' · contas com e-mail e senha; a transparência da carteira é política do fundo');
?>

<?php if ($msg): ?><div class="alert alert-<?= $msgTipo ?> py-2"><i class="bi bi-info-circle me-1"></i><?= e_html($msg) ?></div><?php endif; ?>
<?php if ($senhaProvisoria): ?>
  <div class="alert alert-success">
    <b><i class="bi bi-key me-1"></i>Conta criada para <?= e_html($emailNovo) ?> — senha provisória (mostrada UMA vez):</b>
    <div class="d-flex gap-2 align-items-center mt-2">
      <code id="senha-prov" style="font-size:1.05rem"><?= e_html($senhaProvisoria) ?></code>
      <button class="btn btn-sm btn-outline-dark" onclick="navigator.clipboard.writeText(document.getElementById('senha-prov').textContent);this.textContent='copiado!'">
        <i class="bi bi-clipboard"></i> copiar</button>
    </div>
    <div class="text-muted mt-1" style="font-size:.78rem">Peça ao cotista para trocá-la no primeiro acesso (Meus dados → Alterar senha).</div>
  </div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-eye me-1"></i> Transparência da carteira — política do fundo</div>
      <div class="card-body">
        <form method="post">
          <?= csrf_campo() ?><input type="hidden" name="acao" value="transparencia">
          <?php foreach ([
              'realtime' => ['Tempo real', 'carteira aberta — expõe a estratégia (front-running); usar com parcimônia'],
              'delay_1m' => ['Defasagem de 1 mês', 'equilíbrio comum entre transparência e proteção da estratégia'],
              'delay_3m' => ['Defasagem de 3 meses', 'padrão de mercado — espelha o prazo do CDA público da CVM'],
              'off'      => ['Não divulgar a carteira', 'o cotista vê a própria posição e a rentabilidade, mas não a composição'],
          ] as $val => [$rot, $desc]): ?>
            <label class="d-block border rounded p-2 mb-2" style="cursor:pointer;<?= $transAtual === $val ? 'border-color:var(--navy);background:#f6f4ff' : '' ?>">
              <input type="radio" name="transparencia" value="<?= $val ?>" <?= $transAtual === $val ? 'checked' : '' ?>>
              <b style="font-size:.85rem"><?= $rot ?></b><br>
              <span class="text-muted" style="font-size:.74rem"><?= $desc ?></span>
            </label>
          <?php endforeach; ?>
          <button class="btn btn-dark btn-sm w-100"><i class="bi bi-check-lg me-1"></i>Aplicar a todos os cotistas do fundo</button>
        </form>
        <p class="text-muted mb-0 mt-2" style="font-size:.72rem">
          A política é <b>global por fundo</b> (não há mais nível por link individual). O cotista sempre vê a
          <b>própria posição</b>; a política controla a visão da <b>carteira do fundo</b>.</p>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-person-badge me-1"></i> Contas de acesso (<?= $comConta ?>/<?= count($cotistas) ?> cotistas com acesso)</span>
      </div>
      <div class="card-body p-0" style="max-height:430px;overflow-y:auto">
        <table class="table table-hover align-middle mb-0" style="font-size:.82rem">
          <thead><tr><th>Cotista</th><th>Conta de acesso</th><th>Último acesso</th><th style="min-width:210px"></th></tr></thead>
          <tbody>
          <?php foreach ($cotistas as $c): ?>
            <tr>
              <td><b><?= e_html($c['nome']) ?></b><br>
                <span class="text-muted" style="font-size:.72rem"><?= e_html($c['documento']) ?> · <?= number_format((float)$c['cotas'], 0, ',', '.') ?> cotas</span></td>
              <td style="font-size:.78rem">
                <?php if ($c['conta_email']): ?>
                  <?= e_html($c['conta_email']) ?><br>
                  <?= badge($c['conta_status'], $c['conta_status'] === 'Ativa' ? 'success' : 'danger') ?>
                <?php else: ?><span class="text-muted">sem acesso ao portal</span><?php endif; ?>
              </td>
              <td style="font-size:.74rem" class="text-muted"><?= $c['ultimo_acesso'] ? date('d/m H:i', strtotime($c['ultimo_acesso'])) : '—' ?></td>
              <td class="text-end">
                <?php if (!$c['conta_email']): ?>
                  <form method="post" class="d-flex gap-1">
                    <?= csrf_campo() ?><input type="hidden" name="acao" value="criar_acesso">
                    <input type="hidden" name="cotista_id" value="<?= (int)$c['id'] ?>">
                    <input class="form-control form-control-sm" name="email" placeholder="e-mail do cotista" style="font-size:.74rem" required>
                    <button class="btn btn-sm btn-dark" title="Cria a conta (ou vincula se o e-mail já tiver conta em outro fundo)"><i class="bi bi-person-plus"></i></button>
                  </form>
                <?php else: ?>
                  <?php if ($c['conta_status'] === 'Ativa'): ?>
                    <form method="post" class="d-inline" onsubmit="return confirm('Bloquear a conta? O acesso cai na hora, em todos os fundos.')">
                      <?= csrf_campo() ?><input type="hidden" name="bloquear_conta" value="<?= (int)$c['conta_id'] ?>">
                      <button class="btn btn-sm btn-outline-danger" title="Bloquear conta"><i class="bi bi-lock"></i></button>
                    </form>
                  <?php else: ?>
                    <form method="post" class="d-inline">
                      <?= csrf_campo() ?><input type="hidden" name="reativar_conta" value="<?= (int)$c['conta_id'] ?>">
                      <button class="btn btn-sm btn-outline-success" title="Reativar conta"><i class="bi bi-unlock"></i></button>
                    </form>
                  <?php endif; ?>
                  <form method="post" class="d-inline" onsubmit="return confirm('Remover o vínculo deste fundo? A conta continua valendo nos demais.')">
                    <?= csrf_campo() ?><input type="hidden" name="desvincular" value="<?= (int)$c['id'] ?>">
                    <button class="btn btn-sm btn-outline-secondary" title="Desvincular deste fundo"><i class="bi bi-link-45deg"></i></button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$cotistas): ?><tr><td colspan="4" class="text-muted text-center py-4">Nenhum cotista neste fundo.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="card-footer text-muted" style="font-size:.72rem">
        Uma conta é <b>uma pessoa</b>: se o mesmo e-mail já tem conta (por outro fundo), o vínculo é adicionado e o
        cotista passa a ver os dois fundos na mesma visão consolidada. O cotista também pode se cadastrar sozinho no
        portal — a posição aparece quando você (ou a administradora) vincular o CPF.
      </div>
    </div>
  </div>
</div>

<p class="text-muted" style="font-size:.75rem">
  <i class="bi bi-info-circle me-1"></i>O que o cotista vê no portal: posição consolidada em todos os fundos que
  investe, evolução × benchmark e composição por classe <b>na data de corte da política acima</b>, movimentações
  (aplicar/resgatar), documentos e comunicados. Ele não vê outros cotistas, caixa ou operações.
  <b>Os antigos links por token foram substituídos por contas</b> — tokens ativos deixaram de dar acesso.</p>
<?php page_end();
