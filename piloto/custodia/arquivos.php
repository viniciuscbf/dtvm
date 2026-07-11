<?php
// Arquivos & Extratos — o custodiante gera os arquivos diários que alimentam a administradora
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

$u = exigir_perfil('custodia');

$fundos = $pdo->query("SELECT * FROM fundos WHERE status='Ativo' ORDER BY pl_atual DESC")->fetchAll();
$fid = (int)($_GET['fundo_id'] ?? ($fundos[0]['id'] ?? 0));
$fundoSel = null;
foreach ($fundos as $f) if ((int)$f['id'] === $fid) $fundoSel = $f;
if (!$fundoSel && $fundos) { $fundoSel = $fundos[0]; $fid = (int)$fundoSel['id']; }

$datas = datas_carteira($pdo, $fid);
$data = $_GET['data'] ?? ($datas[0] ?? null);
if ($datas && !in_array($data, $datas, true)) $data = $datas[0];

// ---------- downloads reais ----------
if (isset($_GET['baixar'])) {
    header('Content-Type: text/csv; charset=utf-8');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    if ($_GET['baixar'] === 'posicao') {
        header("Content-Disposition: attachment; filename=\"posicao_custodia_f{$fid}_" . str_replace('-', '', $data) . '.csv"');
        fputcsv($out, ['Data', 'Fundo', 'CNPJ', 'Central', 'Ativo', 'Tipo', 'Quantidade', 'Preco Referencia', 'Valor'], ';');
        // FONTE INDEPENDENTE: a posição exportada é a do CUSTODIANTE (posicao_custodiante),
        // não a carteira contábil da administradora — senão a conciliação seria circular.
        $stp = $pdo->prepare("SELECT MAX(data_ref) FROM posicao_custodiante WHERE fundo_id = ? AND data_ref <= ?");
        $stp->execute([$fid, $data]);
        $dataPos = $stp->fetchColumn();
        // preço de referência por código (só para valorizar o arquivo; a quantidade é a custodiada)
        $refPreco = [];
        foreach (carteira($pdo, $fid, $data) as $a) {
            $refPreco[$a['codigo']] = (float)($a['preco_referencia'] ?: $a['preco_mam']);
        }
        if ($dataPos) {
            $stp = $pdo->prepare("SELECT * FROM posicao_custodiante WHERE fundo_id = ? AND data_ref = ? ORDER BY codigo");
            $stp->execute([$fid, $dataPos]);
            foreach ($stp->fetchAll() as $a) {
                $pu = $refPreco[$a['codigo']] ?? 0.0;
                fputcsv($out, [$dataPos, $fundoSel['nome'], $fundoSel['cnpj'], $a['central'], $a['codigo'], $a['tipo'],
                    number_format((float)$a['quantidade'], 2, ',', ''),
                    number_format($pu, 4, ',', ''),
                    number_format((float)$a['quantidade'] * $pu, 2, ',', '')], ';');
            }
        } else {
            // fallback (base antiga sem snapshot do custodiante): exporta a carteira com aviso explícito
            foreach (carteira($pdo, $fid, $data) as $a) {
                $central = $a['tipo'] === 'Título Público' ? 'SELIC'
                         : (in_array($a['tipo'], ['Debênture', 'CDB', 'CRI/CRA'], true) ? 'B3 Balcao' : 'B3 Depositaria');
                fputcsv($out, [$data, $fundoSel['nome'], $fundoSel['cnpj'], $central . ' (SEM SNAPSHOT DO CUSTODIANTE - fallback carteira)', $a['codigo'], $a['tipo'],
                    number_format((float)$a['quantidade'], 2, ',', ''),
                    number_format((float)($a['preco_referencia'] ?: $a['preco_mam']), 4, ',', ''),
                    number_format($a['valor_mercado'], 2, ',', '')], ';');
            }
        }
    } else {
        header("Content-Disposition: attachment; filename=\"extrato_conta_f{$fid}_" . str_replace('-', '', $data) . '.csv"');
        fputcsv($out, ['Data', 'Fundo', 'Tipo', 'Descricao', 'Valor'], ';');
        $st = $pdo->prepare('SELECT * FROM movimentacoes WHERE fundo_id = ? AND data_ref <= ? ORDER BY data_ref DESC LIMIT 100');
        $st->execute([$fid, $data]);
        foreach ($st->fetchAll() as $m) {
            fputcsv($out, [$m['data_ref'], $fundoSel['nome'], $m['tipo'], $m['descricao'],
                number_format((float)$m['valor'], 2, ',', '')], ';');
        }
    }
    fclose($out);
    exit;
}

page_start('Arquivos & Extratos', 'Arquivos & Extratos', $u,
    'Os arquivos diários que o custodiante envia à administradora — a matéria-prima da conciliação');
?>

<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="bi bi-file-earmark-zip me-1"></i> Geração de arquivos por fundo e data</span>
    <form method="get" class="d-flex gap-2">
      <select class="form-select form-select-sm" name="fundo_id" onchange="this.form.submit()">
        <?php foreach ($fundos as $f): ?>
          <option value="<?= (int)$f['id'] ?>" <?= (int)$f['id'] === $fid ? 'selected' : '' ?>><?= e_html($f['nome']) ?></option>
        <?php endforeach; ?>
      </select>
      <select class="form-select form-select-sm" name="data" onchange="this.form.submit()">
        <?php foreach ($datas as $d): ?>
          <option value="<?= $d ?>" <?= $d === $data ? 'selected' : '' ?>><?= data_br($d) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-6">
        <div class="border rounded p-3 h-100">
          <b style="font-size:.9rem"><i class="bi bi-filetype-csv me-1 text-success"></i>Arquivo de posição custodiada</b>
          <p class="text-muted mb-2 mt-1" style="font-size:.8rem">
            Posição por ativo com central de guarda e preço de referência — é este arquivo que a administradora
            usa no batimento "Posição × Custodiante" do batch diário.</p>
          <a class="btn btn-sm btn-success" href="?fundo_id=<?= $fid ?>&data=<?= e_html($data) ?>&baixar=posicao">
            <i class="bi bi-download me-1"></i>Gerar posição de <?= data_br($data) ?></a>
        </div>
      </div>
      <div class="col-md-6">
        <div class="border rounded p-3 h-100">
          <b style="font-size:.9rem"><i class="bi bi-filetype-csv me-1 text-primary"></i>Extrato da conta do fundo</b>
          <p class="text-muted mb-2 mt-1" style="font-size:.8rem">
            Lançamentos financeiros (liquidações, proventos, taxas) — alimenta o batimento "Caixa × Extrato".</p>
          <a class="btn btn-sm btn-primary" href="?fundo_id=<?= $fid ?>&data=<?= e_html($data) ?>&baixar=extrato">
            <i class="bi bi-download me-1"></i>Gerar extrato até <?= data_br($data) ?></a>
        </div>
      </div>
    </div>
  </div>
  <div class="card-footer text-muted" style="font-size:.72rem">
    No produto final estes arquivos trafegam automaticamente (SFTP/API) no fechamento do dia, e a administradora
    concilia sem intervenção humana. O download manual aqui demonstra o conteúdo e o papel de cada arquivo.
  </div>
</div>

<div class="alert alert-light border" style="font-size:.82rem">
  <b><i class="bi bi-diagram-3 me-1 text-primary"></i>O ciclo completo entre as duas pontas:</b>
  custodiante processa mensageria e liquida (D+1/D+2) → gera <b>posição + extrato</b> no fechamento →
  administradora importa, <b>concilia</b> (posição × custodiante, caixa × extrato), precifica e fecha a prévia da cota →
  gestor aprova → administradora publica, envia o <b>informe diário à CVM</b> e libera os downloads.
</div>
<?php page_end();
