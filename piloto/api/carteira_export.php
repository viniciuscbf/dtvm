<?php
// GET fundo_id, formato=csv|json|pdf, data=YYYY-MM-DD (opcional; padrão = último snapshot)
// Regra de liberação (vida real): gestor só baixa datas cuja cota foi APROVADA e cujo download
// a administradora LIBEROU. Admin baixa qualquer data.
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/pdf.php';

$u = usuario();
if (!$u) { http_response_code(401); exit('não autenticado'); }
$fid = (int)($_GET['fundo_id'] ?? 0);
if ($u['perfil'] === 'gestor' && (int)$u['fundo_id'] !== $fid) { http_response_code(403); exit('sem acesso a este fundo'); }

$st = $pdo->prepare('SELECT * FROM fundos WHERE id = ?');
$st->execute([$fid]);
$fundo = $st->fetch();
if (!$fundo) { http_response_code(404); exit('fundo não encontrado'); }

$data = $_GET['data'] ?? ultima_data_carteira($pdo, $fid);
if (!$data) { http_response_code(404); exit('sem carteira'); }

if (!data_liberada($pdo, $fid, $data, $u['perfil'])) {
    http_response_code(423);
    exit('Os dados de ' . date('d/m/Y', strtotime($data)) . ' ainda não foram liberados: a cota do dia precisa ser aprovada pelo gestor e o download liberado pela administradora.');
}

$ativos = carteira($pdo, $fid, $data);
$arquivo = 'carteira_' . preg_replace('/\W+/', '_', mb_strtolower($fundo['nome'])) . '_' . str_replace('-', '', $data);
$formato = $_GET['formato'] ?? 'csv';

if ($formato === 'pdf') {
    $pdfBin = pdf_carteira($fundo, $ativos, $data, $u['nome']);
    header('Content-Type: application/pdf');
    header("Content-Disposition: attachment; filename=\"$arquivo.pdf\"");
    header('Content-Length: ' . strlen($pdfBin));
    echo $pdfBin;
    exit;
}

if ($formato === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"$arquivo.json\"");
    echo json_encode([
        'fundo' => $fundo['nome'], 'cnpj' => $fundo['cnpj'], 'data_posicao' => $data,
        'gerado_em' => date('c'), 'gerado_por' => $u['nome'],
        'ativos' => array_map(fn($a) => [
            'codigo' => $a['codigo'], 'tipo' => $a['tipo'],
            'quantidade' => (float)$a['quantidade'],
            'preco_medio' => (float)$a['preco_medio'], 'preco_mam' => (float)$a['preco_mam'],
            'fonte_preco' => $a['fonte_preco'],
            'valor_mercado' => round($a['valor_mercado'], 2),
            'resultado_nao_realizado' => round($a['resultado'], 2),
        ], $ativos),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: text/csv; charset=utf-8');
header("Content-Disposition: attachment; filename=\"$arquivo.csv\"");
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['Data Posicao', 'Codigo', 'Tipo', 'Quantidade', 'Preco Medio', 'Preco MaM', 'Fonte', 'Valor Mercado', 'Resultado'], ';');
foreach ($ativos as $a) {
    fputcsv($out, [
        $data, $a['codigo'], $a['tipo'],
        number_format((float)$a['quantidade'], 2, ',', ''),
        number_format((float)$a['preco_medio'], 4, ',', ''),
        number_format((float)$a['preco_mam'], 4, ',', ''),
        $a['fonte_preco'],
        number_format($a['valor_mercado'], 2, ',', ''),
        number_format($a['resultado'], 2, ',', ''),
    ], ';');
}
fclose($out);
