<?php
// Trilha de auditoria — registro append-only de acessos e ações da custódia.
// Evidência da Res. CVM 32, art. 13, V (registro de acessos, erros e incidentes).
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

$u = exigir_perfil('custodia');

$acao = $_GET['acao'] ?? '';
$tabelaExiste = true;
$registros = [];
$acoes = [];
try {
    $sql = "SELECT a.*, f.nome fundo_nome FROM auditoria a LEFT JOIN fundos f ON f.id = a.fundo_id" .
           ($acao ? " WHERE a.acao = ?" : "") .
           " ORDER BY a.criado_em DESC, a.id DESC LIMIT 300";
    $st = $pdo->prepare($sql);
    $st->execute($acao ? [$acao] : []);
    $registros = $st->fetchAll();
    $acoes = $pdo->query("SELECT DISTINCT acao FROM auditoria ORDER BY acao")->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    $tabelaExiste = false;
}

/** Cor do rótulo por tipo de ação. */
function cor_acao(string $a): string {
    if ($a === 'login_falha') return 'danger';
    if (str_starts_with($a, 'login') || $a === 'logout') return 'info';
    if (str_contains($a, 'rejeit')) return 'warning';
    return 'success';
}

page_start('Trilha de auditoria', 'Trilha de auditoria', $u,
    'Registro imutável de acessos e ações da custódia — evidência do art. 13, V da Res. CVM 32');
?>

<?php if (!$tabelaExiste): ?>
  <div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle me-1"></i>
    A tabela <code>auditoria</code> ainda não existe. Aplique a migração
    <code>sql/hardening.sql</code> no banco para ativar a trilha
    (ou reimporte o <code>sql/schema.sql</code> atualizado).
  </div>
<?php else: ?>

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="bi bi-shield-lock me-1"></i> Últimos 300 eventos
      <?= badge((string) count($registros) . ' exibidos', 'info') ?></span>
    <form method="get">
      <select name="acao" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="">Todas as ações</option>
        <?php foreach ($acoes as $a): ?>
          <option value="<?= e_html($a) ?>" <?= $acao === $a ? 'selected' : '' ?>><?= e_html($a) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
  <div class="card-body p-0" style="max-height:620px;overflow-y:auto">
    <table class="table table-hover align-middle mb-0" style="font-size:.82rem">
      <thead><tr>
        <th>Data/hora</th><th>Ator</th><th>Perfil</th><th>Ação</th>
        <th>Entidade</th><th>Fundo</th><th>Detalhe</th><th>Origem</th>
      </tr></thead>
      <tbody>
      <?php foreach ($registros as $r): ?>
        <tr>
          <td class="text-muted" style="white-space:nowrap"><?= date('d/m/Y H:i:s', strtotime($r['criado_em'])) ?></td>
          <td style="font-size:.8rem"><?= e_html($r['ator']) ?></td>
          <td><?= $r['perfil'] ? badge($r['perfil'], 'secondary') : '—' ?></td>
          <td><?= badge($r['acao'], cor_acao($r['acao'])) ?></td>
          <td style="font-size:.78rem"><?= e_html($r['entidade'] ?? '—') ?><?= $r['entidade_id'] ? ' #' . e_html($r['entidade_id']) : '' ?></td>
          <td style="font-size:.78rem"><?= e_html($r['fundo_nome'] ?? '—') ?></td>
          <td class="text-muted" style="font-size:.78rem"><?= e_html($r['detalhe'] ?? '') ?></td>
          <td class="text-muted" style="font-size:.72rem;font-family:monospace"><?= e_html($r['ip'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$registros): ?>
        <tr><td colspan="8" class="text-muted text-center py-4">Sem eventos registrados ainda.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer text-muted" style="font-size:.72rem">
    A trilha é <b>append-only</b>: a aplicação apenas insere registros — nunca altera ou apaga. Cada login,
    liquidação, tratamento de evento e processamento de mensagem gera um registro com ator, horário e origem
    (IP), atendendo ao dever de registro de acessos, erros e incidentes (art. 13, V da Res. CVM 32).
  </div>
</div>

<?php endif; ?>
<?php page_end();
