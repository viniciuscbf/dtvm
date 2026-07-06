<?php
// Mensageria RSFN/SPB — fila de mensagens das centrais (SELIC, STR, B3)
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

$u = exigir_perfil('custodia');
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['processar'])) {
    $st = $pdo->prepare("SELECT * FROM mensagens_spb WHERE id = ? AND status IN ('Recebida','Erro')");
    $st->execute([(int)$_POST['processar']]);
    if ($m = $st->fetch()) {
        $pdo->prepare("UPDATE mensagens_spb SET status='Processada', processada_em=NOW(), processada_por=? WHERE id=?")
            ->execute([$u['nome'], $m['id']]);
        if ($m['fundo_id']) {
            $pdo->prepare("INSERT INTO log_processamento (fundo_id, data_ref, etapa, nivel, mensagem) VALUES (?,?,?,?,?)")
                ->execute([$m['fundo_id'], date('Y-m-d'), 'Posição', 'INFO',
                           "Mensagem {$m['codigo']} ({$m['central']}) processada pela custódia — " . mb_substr($m['descricao'], 0, 120)]);
        }
        $msg = "Mensagem {$m['codigo']} processada.";
    }
}

$filtro = $_GET['central'] ?? '';
$sql = "SELECT m.*, f.nome fundo_nome FROM mensagens_spb m LEFT JOIN fundos f ON f.id=m.fundo_id" .
       ($filtro ? " WHERE m.central = ?" : "") .
       " ORDER BY FIELD(m.status,'Erro','Recebida','Processada'), m.recebida_em DESC";
$st = $pdo->prepare($sql);
$st->execute($filtro ? [$filtro] : []);
$mensagens = $st->fetchAll();
$pend = count(array_filter($mensagens, fn($m) => $m['status'] !== 'Processada'));

page_start('Mensageria SPB', 'Mensageria SPB', $u,
    'Tráfego RSFN com as infraestruturas: SELIC (títulos públicos), STR (financeiro) e B3 (renda variável e balcão)');
?>

<?php if ($msg): ?><div class="alert alert-success py-2"><i class="bi bi-check-circle me-1"></i><?= e_html($msg) ?></div><?php endif; ?>

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-envelope-arrow-down me-1"></i> Fila de mensagens <?= badge($pend . ' a tratar', $pend ? 'warning' : 'success') ?></span>
    <form method="get">
      <select name="central" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="">Todas as centrais</option>
        <?php foreach (['SELIC', 'STR', 'B3 Depositária', 'B3 Balcão'] as $c): ?>
          <option <?= $filtro === $c ? 'selected' : '' ?>><?= $c ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
  <div class="card-body p-0">
    <table class="table table-hover align-middle mb-0" style="font-size:.83rem">
      <thead><tr><th>Recebida</th><th>Central</th><th>Código</th><th>Fundo</th><th>Conteúdo</th>
        <th class="text-end">Valor</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($mensagens as $m): ?>
        <tr class="<?= $m['status'] === 'Erro' ? 'table-danger' : ($m['status'] === 'Recebida' ? 'table-warning' : '') ?>">
          <td class="text-muted" style="white-space:nowrap"><?= date('d/m H:i', strtotime($m['recebida_em'])) ?></td>
          <td><?= badge($m['central'], 'info') ?></td>
          <td><code style="font-size:.76rem"><?= e_html($m['codigo']) ?></code><br>
            <span class="text-muted" style="font-size:.68rem"><?= e_html($m['referencia']) ?></span></td>
          <td style="font-size:.8rem"><?= e_html($m['fundo_nome'] ?? '—') ?></td>
          <td class="text-muted" style="font-size:.8rem"><?= e_html($m['descricao']) ?></td>
          <td class="text-end"><?= $m['valor'] !== null ? moeda($m['valor']) : '—' ?></td>
          <td><?= badge_status($m['status'] === 'Processada' ? 'OK' : ($m['status'] === 'Erro' ? 'Erro' : 'Pendente')) ?>
            <?= $m['processada_por'] ? '<br><span class="text-muted" style="font-size:.68rem">' . e_html($m['processada_por']) . '</span>' : '' ?></td>
          <td class="text-end">
            <?php if ($m['status'] !== 'Processada'): ?>
              <form method="post"><input type="hidden" name="processar" value="<?= (int)$m['id'] ?>">
                <button class="btn btn-sm <?= $m['status'] === 'Erro' ? 'btn-danger' : 'btn-dark' ?>">
                  <i class="bi bi-arrow-repeat me-1"></i><?= $m['status'] === 'Erro' ? 'Reprocessar' : 'Processar' ?></button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$mensagens): ?><tr><td colspan="8" class="text-muted text-center py-4">Sem mensagens.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer text-muted" style="font-size:.72rem">
    No produto final, estas mensagens chegam pela <b>RSFN</b> (Rede do Sistema Financeiro Nacional) no padrão de
    catálogo do SPB — SEL (SELIC), STR (reservas) e a mensageria/arquivos da B3. O piloto simula o tráfego para
    demonstrar o fluxo operacional: mensagem → processamento → reflexo na posição/caixa → conciliação.
  </div>
</div>
<?php page_end();
