<?php
// Central de Relatórios — selecione fundo (se a conta tiver vários), data, tipo e formato
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

$u = exigir_perfil('gestor', 'admin');
$fundo = fundo_do_usuario($pdo, $u);
if (!$fundo) die('Sem fundo vinculado.');
exigir_fundo_ativo($fundo);
$fid = (int)$fundo['id'];

$meusFundos = $u['perfil'] === 'gestor' ? fundos_do_usuario($pdo, $u) : [$fundo];

// datas processadas (com cota calculada) — disponíveis; demais snapshots — ainda não processados
$datas = datas_carteira($pdo, $fid);
$processadas = array_values(array_filter($datas, fn($d) => fechamento($pdo, $fid, $d) !== null));
$dataSel = $_GET['data'] ?? ($processadas[0] ?? null);
if ($dataSel && !in_array($dataSel, $processadas, true)) $dataSel = $processadas[0] ?? null;

$st = $pdo->prepare('SELECT * FROM documentos WHERE fundo_id = ? ORDER BY data_doc DESC LIMIT 8');
$st->execute([$fid]);
$docs = $st->fetchAll();

$tipos = [
    'carteira'   => ['Carteira (posição do dia)', 'bi-pie-chart', ['csv', 'json', 'pdf']],
    'caixa'      => ['Fluxo de caixa / extrato', 'bi-wallet2', ['csv', 'json']],
    'cotistas'   => ['Cotistas e participações', 'bi-people', ['csv', 'json']],
    'cota_serie' => ['Série histórica da cota', 'bi-graph-up', ['csv', 'json']],
];

page_start('Relatórios', 'Relatórios', $u,
    'Qualquer dia já processado está disponível — como PRÉVIA antes da sua aprovação da cota, como OFICIAL depois');
?>

<div class="card mb-4">
  <div class="card-header"><i class="bi bi-file-earmark-arrow-down me-1"></i> Gerar relatório</div>
  <div class="card-body">
    <div class="row g-2 align-items-end mb-3">
      <?php if (count($meusFundos) > 1): ?>
      <div class="col-md-4">
        <label class="form-label" style="font-size:.78rem">Fundo</label>
        <form method="get">
          <select name="fundo_id" class="form-select form-select-sm" onchange="this.form.submit()">
            <?php foreach ($meusFundos as $mf): ?>
              <option value="<?= (int)$mf['id'] ?>" <?= (int)$mf['id'] === $fid ? 'selected' : '' ?>><?= e_html($mf['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
      <?php endif; ?>
      <div class="col-md-3">
        <label class="form-label" style="font-size:.78rem">Data da posição</label>
        <form method="get">
          <?php if (count($meusFundos) > 1): ?><input type="hidden" name="fundo_id" value="<?= $fid ?>"><?php endif; ?>
          <select name="data" class="form-select form-select-sm" onchange="this.form.submit()">
            <?php foreach ($processadas as $d):
                $selo = selo_dia($pdo, $fid, $d); ?>
              <option value="<?= $d ?>" <?= $d === $dataSel ? 'selected' : '' ?>><?= data_br($d) ?> · <?= $selo ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
      <div class="col-md-5" style="font-size:.78rem">
        <?php if ($dataSel): ?>
          <?= selo_dia($pdo, $fid, $dataSel) === 'OFICIAL'
              ? badge('OFICIAL — cota aprovada por você', 'success')
              : badge('PRÉVIA — processada pela administradora, aguardando sua aprovação', 'warning') ?>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($dataSel): ?>
    <div class="row g-3">
      <?php foreach ($tipos as $chave => [$rotulo, $icone, $formatos]): ?>
        <div class="col-md-6 col-xl-3">
          <div class="border rounded p-3 h-100 d-flex flex-column">
            <b style="font-size:.88rem"><i class="bi <?= $icone ?> me-1 text-primary"></i><?= $rotulo ?></b>
            <div class="d-flex gap-1 mt-auto pt-3 flex-wrap">
              <?php foreach ($formatos as $fmt): ?>
                <a class="btn btn-sm <?= $fmt === 'pdf' ? 'btn-danger' : ($fmt === 'csv' ? 'btn-success' : 'btn-outline-success') ?>"
                   href="<?= BASE_URL ?>api/relatorio.php?fundo_id=<?= $fid ?>&tipo=<?= $chave ?>&data=<?= e_html($dataSel) ?>&formato=<?= $fmt ?>">
                  <i class="bi bi-download me-1"></i><?= strtoupper($fmt) ?></a>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
      <div class="col-md-6 col-xl-3">
        <div class="border rounded p-3 h-100 d-flex flex-column">
          <b style="font-size:.88rem"><i class="bi bi-graph-up-arrow me-1 text-primary"></i>Performance na data</b>
          <div class="mt-auto pt-3">
            <a class="btn btn-sm btn-outline-dark" href="performance.php?ate=<?= e_html($dataSel) ?>">
              <i class="bi bi-box-arrow-up-right me-1"></i>Abrir relatório</a>
          </div>
        </div>
      </div>
    </div>
    <?php else: ?>
      <div class="alert alert-warning py-2 mb-0" style="font-size:.85rem"><i class="bi bi-hourglass-split me-1"></i>
        Nenhum dia processado ainda — os relatórios ficam disponíveis assim que a administradora bate a carteira e calcula a cota.</div>
    <?php endif; ?>
  </div>
  <div class="card-footer text-muted" style="font-size:.74rem">
    Regra: dia <b>processado</b> (carteira batida + cota calculada) = relatórios disponíveis na hora, carimbados como
    <b>PRÉVIA</b> até você aprovar a cota (aí viram <b>OFICIAL</b>). Dias ainda não processados não têm o que baixar.
  </div>
</div>

<div class="card">
  <div class="card-header"><i class="bi bi-folder2-open me-1"></i> Documentos institucionais do fundo</div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0" style="font-size:.85rem">
      <thead><tr><th>Documento</th><th>Tipo</th><th>Versão</th><th>Data</th></tr></thead>
      <tbody>
      <?php foreach ($docs as $d): ?>
        <tr>
          <td><i class="bi bi-file-earmark me-2 text-muted"></i><?= e_html($d['nome']) ?></td>
          <td><?= badge($d['tipo'], 'secondary') ?></td>
          <td class="text-muted"><?= e_html($d['versao']) ?></td>
          <td><?= data_br($d['data_doc']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$docs): ?><tr><td colspan="4" class="text-muted text-center py-4">Sem documentos cadastrados.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php page_end();
