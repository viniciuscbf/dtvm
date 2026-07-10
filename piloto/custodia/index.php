<?php
// Painel da Mesa de Custódia — a retaguarda do banco custodiante
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

$u = exigir_perfil('custodia');

$fundos = $pdo->query("SELECT * FROM fundos WHERE status='Ativo' ORDER BY pl_atual DESC")->fetchAll();

// posição custodiada por central (a partir do snapshot mais recente de cada fundo)
$porCentral = ['SELIC' => 0.0, 'B3 Depositária' => 0.0, 'B3 Balcão' => 0.0];
$totCustodiado = 0.0;
foreach ($fundos as $f) {
    foreach (carteira($pdo, (int)$f['id']) as $a) {
        $central = $a['tipo'] === 'Título Público' ? 'SELIC'
                 : (in_array($a['tipo'], ['Debênture', 'CDB', 'CRI/CRA'], true) ? 'B3 Balcão' : 'B3 Depositária');
        $porCentral[$central] += $a['valor_mercado'];
        $totCustodiado += $a['valor_mercado'];
    }
}

$contas = $pdo->query("SELECT c.*, f.nome fundo_nome FROM contas_centrais c LEFT JOIN fundos f ON f.id=c.fundo_id
                       ORDER BY c.fundo_id IS NULL DESC, c.fundo_id, FIELD(c.central,'STR/Reservas','SELIC','B3 Depositária','B3 Balcão')")->fetchAll();

$msgs = $pdo->query("SELECT COUNT(*) t, SUM(status='Recebida') rec, SUM(status='Erro') err FROM mensagens_spb")->fetch();
$liqPend = (int)$pdo->query("SELECT COUNT(*) FROM liquidacoes WHERE status='Pendente'")->fetchColumn();
$evPend = (int)$pdo->query("SELECT COUNT(*) FROM eventos_corporativos WHERE status <> 'Liquidado'")->fetchColumn();

// divergência de posição aberta (batimento) — visão do custodiante
$divs = $pdo->query("SELECT c.*, f.nome fundo_nome FROM conciliacao c JOIN fundos f ON f.id=c.fundo_id
                     WHERE c.origem='Posição × Custodiante' AND c.situacao='Divergente'")->fetchAll();

page_start('Painel da custódia', 'Painel da custódia', $u,
    'Guarda segregada por fundo · SELIC (títulos públicos), B3 Depositária (ações) e B3 Balcão (crédito privado)');
?>

<div class="row row-cols-2 row-cols-md-3 row-cols-xl-6 g-3 mb-4">
  <?= kpi('Sob custódia', moeda_compacta($totCustodiado), 'bi-safe2', count($fundos) . ' fundos segregados') ?>
  <?= kpi('SELIC', moeda_compacta($porCentral['SELIC']), 'bi-bank', 'títulos públicos') ?>
  <?= kpi('B3 Depositária', moeda_compacta($porCentral['B3 Depositária']), 'bi-building', 'renda variável') ?>
  <?= kpi('B3 Balcão', moeda_compacta($porCentral['B3 Balcão']), 'bi-briefcase', 'crédito privado') ?>
  <?= kpi('Mensageria hoje', (string)(int)$msgs['t'], 'bi-envelope-arrow-down',
          '<span class="' . ((int)$msgs['rec'] + (int)$msgs['err'] ? 'text-warning' : 'text-success') . '">' .
          (int)$msgs['rec'] . ' a processar · ' . (int)$msgs['err'] . ' erro</span>') ?>
  <?= kpi('Fila operacional', ($liqPend + $evPend) . ' itens', 'bi-list-task', $liqPend . ' liquidações · ' . $evPend . ' eventos') ?>
</div>

<?php if ($divs): ?>
  <div class="alert alert-danger py-2" style="font-size:.86rem"><i class="bi bi-exclamation-triangle me-1"></i>
    <b>Batimento com divergência:</b>
    <?php foreach ($divs as $d): ?>
      <?= e_html($d['fundo_nome']) ?> — <?= e_html($d['detalhe']) ?> (<?= moeda($d['valor_diferenca']) ?>).
    <?php endforeach; ?>
    A administradora foi notificada via conciliação.</div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-diagram-3 me-1"></i> Contas nas infraestruturas de mercado (segregação por fundo)</div>
      <div class="card-body p-0" style="max-height:430px;overflow-y:auto">
        <table class="table table-hover mb-0" style="font-size:.83rem">
          <thead><tr><th>Titularidade</th><th>Central</th><th>Conta</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($contas as $c): ?>
            <tr class="<?= $c['fundo_id'] === null ? 'table-light' : '' ?>">
              <td><b><?= e_html($c['fundo_nome'] ?? 'Banco (conta própria)') ?></b><br>
                <span class="text-muted" style="font-size:.72rem"><?= e_html($c['titularidade']) ?></span></td>
              <td><?= badge($c['central'], $c['central'] === 'SELIC' ? 'info' : ($c['central'] === 'STR/Reservas' ? 'gold' : 'secondary')) ?></td>
              <td style="font-family:monospace;font-size:.8rem"><?= e_html($c['numero_conta']) ?></td>
              <td><?= badge_status($c['status'] === 'Ativa' ? 'Ativo' : $c['status']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="card-footer text-muted" style="font-size:.72rem">
        A segregação patrimonial é a essência da custódia: cada fundo tem conta individualizada nas centrais —
        os ativos dos fundos nunca se misturam com os do banco. A conta Reservas/STR do banco liquida o financeiro.
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-envelope-arrow-down me-1"></i> Últimas mensagens RSFN/SPB</div>
      <div class="card-body p-0">
        <?php $ult = $pdo->query("SELECT m.*, f.nome fundo_nome FROM mensagens_spb m LEFT JOIN fundos f ON f.id=m.fundo_id
                                  ORDER BY m.recebida_em DESC LIMIT 6")->fetchAll();
        foreach ($ult as $m): ?>
          <div class="p-2 px-3 border-bottom" style="font-size:.8rem">
            <div class="d-flex justify-content-between">
              <span><?= badge($m['central'], 'info') ?> <code style="font-size:.72rem"><?= e_html($m['codigo']) ?></code></span>
              <?= badge_status($m['status'] === 'Processada' ? 'OK' : ($m['status'] === 'Erro' ? 'Erro' : 'Pendente')) ?>
            </div>
            <span class="text-muted"><?= e_html(mb_substr($m['descricao'], 0, 90)) ?></span>
          </div>
        <?php endforeach; ?>
        <a class="d-block text-center py-2 link-limpo" style="font-size:.82rem" href="mensageria.php">Abrir mensageria →</a>
      </div>
    </div>
    <div class="card">
      <div class="card-body" style="font-size:.8rem">
        <b><i class="bi bi-info-circle me-1 text-primary"></i>Como este portal se encaixa</b>
        <p class="text-muted mb-0 mt-1">
          Esta é a <b>retaguarda do banco custodiante</b> (autorização da Res. CVM 32): recebe as mensagens das
          centrais, liquida as operações (entrega contra pagamento), trata proventos e gera os arquivos de posição
          que alimentam a conciliação da administradora, via mensageria RSFN.
        </p>
      </div>
    </div>
  </div>
</div>
<?php page_end();
