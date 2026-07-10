<?php
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

$u = exigir_perfil('admin');

$fundos = $pdo->query("SELECT * FROM fundos ORDER BY pl_atual DESC")->fetchAll();
$ativos = array_filter($fundos, fn($f) => $f['status'] === 'Ativo');
$plTotal = array_sum(array_map('floatval', array_column($ativos, 'pl_atual')));

// receita do mês corrente (apuração pela regra 0,08% a.a. com piso R$ 100)
$receitaMes = 0.0;
foreach ($ativos as $f) { [$v, ] = apurar_taxa_mensal((float)$f['pl_atual'], (float)$f['taxa_adm']); $receitaMes += $v; }
$parteBanco = $receitaMes * 0.25;

// status do batch de hoje (última data processada)
$dataBatch = $pdo->query('SELECT MAX(data_ref) FROM processamento')->fetchColumn();
$batch = [];
if ($dataBatch) {
    $st = $pdo->prepare("SELECT fundo_id,
            SUM(status='OK') oks, SUM(status='Erro') erros, SUM(status='Pendente') pendentes,
            SUM(status='Rodando') rodando, COUNT(*) total
            FROM processamento WHERE data_ref = ? GROUP BY fundo_id");
    $st->execute([$dataBatch]);
    foreach ($st->fetchAll() as $r) $batch[$r['fundo_id']] = $r;
}
$fechados = count(array_filter($batch, fn($b) => (int)$b['oks'] === (int)$b['total']));
$comErro = count(array_filter($batch, fn($b) => (int)$b['erros'] > 0));

// cotas de D-1 aguardando o gestor / rejeitadas
$aguardandoGestor = (int)$pdo->query("SELECT COUNT(DISTINCT fundo_id) FROM fechamentos WHERE status='Aguardando aprovação'")->fetchColumn();
$cotasRejeitadas = (int)$pdo->query("SELECT COUNT(*) FROM fechamentos fe WHERE fe.status='Rejeitada'
    AND fe.versao = (SELECT MAX(versao) FROM fechamentos x WHERE x.fundo_id=fe.fundo_id AND x.data_ref=fe.data_ref)")->fetchColumn();

// pendências: divergências abertas + alertas abertos + chamados sem resposta
$divergencias = $pdo->query("SELECT fundo_id, COUNT(*) c FROM conciliacao WHERE situacao='Divergente' GROUP BY fundo_id")->fetchAll(PDO::FETCH_KEY_PAIR);
$alertasAbertos = $pdo->query("SELECT fundo_id, COUNT(*) c FROM alertas_fraude WHERE status IN ('Aberto','Em revisão') GROUP BY fundo_id")->fetchAll(PDO::FETCH_KEY_PAIR);
$chamadosAbertos = (int)$pdo->query("SELECT COUNT(*) FROM chamados WHERE status='Aberto'")->fetchColumn();
$totAlertas = array_sum($alertasAbertos);
$totDiv = array_sum($divergencias);

// variação diária de cada fundo
function var_dia(PDO $pdo, int $fid): ?float {
    $st = $pdo->prepare('SELECT valor_cota FROM cotas_historico WHERE fundo_id = ? ORDER BY data_ref DESC LIMIT 2');
    $st->execute([$fid]);
    $r = $st->fetchAll();
    return count($r) === 2 ? ((float)$r[0]['valor_cota'] / (float)$r[1]['valor_cota'] - 1) * 100 : null;
}

// feed de atividade (últimos tratamentos)
$feed = $pdo->query("
    (SELECT criado_em dt, CONCAT('Comentário em fundo #', fundo_id, ': ', LEFT(texto, 60)) txt, 'bi-chat-left-text' icone FROM comentarios ORDER BY criado_em DESC LIMIT 3)
    UNION ALL
    (SELECT tratado_em dt, CONCAT('Alerta ', regra, ' — ', tipo, ' marcado como ', status) txt, 'bi-shield-check' icone FROM alertas_fraude WHERE tratado_em IS NOT NULL ORDER BY tratado_em DESC LIMIT 3)
    UNION ALL
    (SELECT resolvido_em dt, CONCAT('Divergência resolvida: ', LEFT(detalhe, 60)) txt, 'bi-check2-square' icone FROM conciliacao WHERE resolvido_em IS NOT NULL ORDER BY resolvido_em DESC LIMIT 3)
    ORDER BY dt DESC LIMIT 6")->fetchAll();

page_start('Painel geral', 'Painel geral', $u, 'Todos os fundos sob administração');
?>

<div class="row row-cols-2 row-cols-md-3 row-cols-xl-6 g-3 mb-4">
  <?= kpi('Fundos', count($ativos) . ' ativos', 'bi-folder2', (count($fundos) - count($ativos)) . ' em abertura') ?>
  <?= kpi('PL sob administração', moeda_compacta($plTotal), 'bi-safe') ?>
  <?= kpi('Receita do mês (adm.)', moeda($receitaMes, 0), 'bi-cash-stack',
          '<span class="split-banco">banco: ' . moeda($parteBanco, 0) . '</span> · adm.: ' . moeda($receitaMes - $parteBanco, 0)) ?>
  <?= kpi('Batch de hoje', $fechados . '/' . count($batch) . ' fechados', 'bi-cpu',
          $comErro ? '<span class="text-danger">' . $comErro . ' com erro</span>' : 'sem erros') ?>
  <?= kpi('Cotas D-1', $aguardandoGestor . ' com o gestor', 'bi-clipboard-check',
          $cotasRejeitadas ? '<span class="text-danger">' . $cotasRejeitadas . ' rejeitada(s) p/ corrigir</span>' : 'nenhuma rejeição') ?>
  <?= kpi('Pendências', (string)($totDiv + $totAlertas + $chamadosAbertos), 'bi-exclamation-diamond',
          $totDiv . ' diverg. · ' . $totAlertas . ' alertas IA · ' . $chamadosAbertos . ' chamados') ?>
</div>

<div class="row g-3">
  <div class="col-xl-8">
    <div class="card">
      <div class="card-header"><i class="bi bi-heart-pulse me-1"></i> Mapa de saúde dos fundos</div>
      <div class="card-body p-0">
        <table class="table table-hover mb-0">
          <thead><tr><th></th><th>Fundo</th><th>Classe</th><th class="text-end">PL</th>
            <th class="text-end">Cota (dia)</th><th>Batch</th><th>Pendências</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($fundos as $f):
              $fid = (int)$f['id'];
              $b = $batch[$fid] ?? null;
              $nDiv = (int)($divergencias[$fid] ?? 0);
              $nAl = (int)($alertasAbertos[$fid] ?? 0);
              $temErro = $b && (int)$b['erros'] > 0;
              $temAtencao = $nDiv + $nAl > 0 || ($b && (int)$b['pendentes'] > 0);
              $sem = $f['status'] !== 'Ativo' ? 'sem-amarelo' : ($temErro ? 'sem-vermelho' : ($temAtencao ? 'sem-amarelo' : 'sem-verde'));
              $vd = $f['status'] === 'Ativo' ? var_dia($pdo, $fid) : null; ?>
            <tr>
              <td><span class="semaforo <?= $sem ?>"></span></td>
              <td><b><?= e_html($f['nome']) ?></b><br><span class="text-muted" style="font-size:.75rem"><?= e_html($f['cnpj']) ?></span></td>
              <td><?= badge($f['classe'], 'secondary') ?> <?= $f['status'] !== 'Ativo' ? badge_status($f['status']) : '' ?></td>
              <td class="text-end"><?= moeda_compacta($f['pl_atual']) ?></td>
              <td class="text-end <?= pct_color($vd) ?>"><?= pct($vd, 3) ?></td>
              <td><?php if ($b): ?>
                    <?= (int)$b['oks'] ?>/<?= (int)$b['total'] ?> <?= $temErro ? badge('erro', 'danger') : ((int)$b['oks'] === (int)$b['total'] ? badge('OK', 'success') : badge('rodando', 'warning')) ?>
                  <?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
              <td>
                <?= $nDiv ? '<a class="link-limpo" href="conciliacao.php">' . badge($nDiv . ' diverg.', 'danger') . '</a> ' : '' ?>
                <?= $nAl ? '<a class="link-limpo" href="fraude.php">' . badge($nAl . ' IA', 'warning') . '</a>' : '' ?>
                <?= (!$nDiv && !$nAl) ? '<span class="text-muted" style="font-size:.8rem">—</span>' : '' ?>
              </td>
              <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>gestor/index.php?fundo_id=<?= $fid ?>" title="Ver como o gestor vê"><i class="bi bi-eye"></i></a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-xl-4">
    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-cpu me-1"></i> Batch diário — <?= data_br($dataBatch) ?></div>
      <div class="card-body">
        <?php $pctBatch = count($batch) ? round($fechados / count($batch) * 100) : 0; ?>
        <div class="d-flex justify-content-between" style="font-size:.85rem">
          <span>Fundos com cota fechada</span><b><?= $fechados ?>/<?= count($batch) ?></b>
        </div>
        <div class="progress my-2" style="height:10px">
          <div class="progress-bar bg-success" style="width:<?= $pctBatch ?>%"></div>
        </div>
        <div style="font-size:.82rem" class="text-muted">
          <?= $comErro ? '<i class="bi bi-x-circle text-danger me-1"></i>' . $comErro . ' fundo(s) com erro — ' : '' ?>
          <a href="processamento.php">abrir processamento →</a>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><i class="bi bi-clock-history me-1"></i> Atividade recente</div>
      <div class="card-body p-0">
        <?php foreach ($feed as $ev): ?>
          <div class="p-2 px-3 border-bottom" style="font-size:.8rem">
            <i class="bi <?= $ev['icone'] ?> text-muted me-1"></i> <?= e_html($ev['txt']) ?>
            <div class="text-muted" style="font-size:.7rem"><?= $ev['dt'] ? date('d/m H:i', strtotime($ev['dt'])) : '' ?></div>
          </div>
        <?php endforeach; ?>
        <?php if (!$feed): ?><p class="text-muted text-center py-3 mb-0" style="font-size:.85rem">Sem atividade registrada.</p><?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php page_end();
