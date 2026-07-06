<?php
// Central de relatórios — GET fundo_id, tipo (carteira|caixa|cotistas|cota_serie), data, formato (csv|json|pdf)
// Disponível para qualquer dia PROCESSADO (cota calculada). Antes da aprovação do gestor sai como PRÉVIA.
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/pdf.php';

$u = usuario();
if (!$u) { http_response_code(401); exit('não autenticado'); }
$fid = (int)($_GET['fundo_id'] ?? 0);

// gestor só acessa fundos da própria conta (pode haver vários: FIC/master, subclasses)
if ($u['perfil'] === 'gestor') {
    $ids = array_map(fn($f) => (int)$f['id'], fundos_do_usuario($pdo, $u));
    if (!in_array($fid, $ids, true)) { http_response_code(403); exit('sem acesso a este fundo'); }
}

$st = $pdo->prepare('SELECT * FROM fundos WHERE id = ?');
$st->execute([$fid]);
$fundo = $st->fetch();
if (!$fundo) { http_response_code(404); exit('fundo não encontrado'); }

$data = $_GET['data'] ?? ultima_data_carteira($pdo, $fid);
$tipo = $_GET['tipo'] ?? 'carteira';
$formato = $_GET['formato'] ?? 'csv';

if ($tipo !== 'cota_serie' && !data_liberada($pdo, $fid, $data, $u['perfil'])) {
    http_response_code(423);
    exit('O dia ' . date('d/m/Y', strtotime($data)) . ' ainda não foi processado pela administradora — sem carteira/cota calculada, não há relatório a gerar.');
}
$selo = selo_dia($pdo, $fid, $data);
$slug = fn($t) => $t . '_' . preg_replace('/\W+/', '_', mb_strtolower($fundo['nome'])) . '_' . str_replace('-', '', $data);

function saida_csv(string $arquivo, array $cab, array $linhas): void {
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"$arquivo.csv\"");
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $cab, ';');
    foreach ($linhas as $l) fputcsv($out, $l, ';');
    fclose($out);
    exit;
}
function saida_json(string $arquivo, array $payload): void {
    header('Content-Type: application/json; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"$arquivo.json\"");
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
$meta = ['fundo' => $fundo['nome'], 'cnpj' => $fundo['cnpj'], 'data_posicao' => $data,
         'situacao' => $selo, 'gerado_em' => date('c'), 'gerado_por' => $u['nome']];

switch ($tipo) {
    case 'carteira':
        $ativos = carteira($pdo, $fid, $data);
        if ($formato === 'pdf') {
            $bin = pdf_carteira($fundo, $ativos, $data, $u['nome'] . " · $selo");
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $slug('carteira') . '.pdf"');
            header('Content-Length: ' . strlen($bin));
            echo $bin; exit;
        }
        if ($formato === 'json') {
            saida_json($slug('carteira'), $meta + ['ativos' => array_map(fn($a) => [
                'codigo' => $a['codigo'], 'tipo' => $a['tipo'], 'quantidade' => (float)$a['quantidade'],
                'preco_medio' => (float)$a['preco_medio'], 'preco_mam' => (float)$a['preco_mam'],
                'fonte_preco' => $a['fonte_preco'], 'valor_mercado' => round($a['valor_mercado'], 2),
                'resultado_nao_realizado' => round($a['resultado'], 2)], $ativos)]);
        }
        saida_csv($slug('carteira'),
            ['Situacao', 'Data', 'Codigo', 'Tipo', 'Quantidade', 'Preco Medio', 'Preco MaM', 'Fonte', 'Valor Mercado', 'Resultado'],
            array_map(fn($a) => [$selo, $data, $a['codigo'], $a['tipo'],
                number_format((float)$a['quantidade'], 2, ',', ''),
                number_format((float)$a['preco_medio'], 4, ',', ''),
                number_format((float)$a['preco_mam'], 4, ',', ''),
                $a['fonte_preco'],
                number_format($a['valor_mercado'], 2, ',', ''),
                number_format($a['resultado'], 2, ',', '')], $ativos));

    case 'caixa':
        $st = $pdo->prepare('SELECT * FROM movimentacoes WHERE fundo_id = ? AND data_ref <= ? ORDER BY data_ref DESC, id DESC LIMIT 200');
        $st->execute([$fid, $data]);
        $movs = $st->fetchAll();
        if ($formato === 'json') saida_json($slug('fluxo_caixa'), $meta + ['movimentacoes' => $movs]);
        saida_csv($slug('fluxo_caixa'),
            ['Situacao', 'Data', 'Tipo', 'Descricao', 'Valor'],
            array_map(fn($m) => [$selo, $m['data_ref'], $m['tipo'], $m['descricao'],
                number_format((float)$m['valor'], 2, ',', '')], $movs));

    case 'cotistas':
        $st = $pdo->prepare('SELECT * FROM cotistas WHERE fundo_id = ? ORDER BY cotas DESC');
        $st->execute([$fid]);
        $cts = $st->fetchAll();
        $totCotas = array_sum(array_map('floatval', array_column($cts, 'cotas')));
        $cota = cota_em($pdo, $fid, $data) ?? (float)$fundo['cota_atual'];
        if ($formato === 'json') {
            saida_json($slug('cotistas'), $meta + ['cotistas' => array_map(fn($c) => [
                'nome' => $c['nome'], 'documento' => $c['documento'], 'tipo' => $c['tipo_pessoa'],
                'cotas' => (float)$c['cotas'], 'posicao' => round((float)$c['cotas'] * $cota, 2),
                'participacao_pct' => $totCotas > 0 ? round((float)$c['cotas'] / $totCotas * 100, 4) : 0], $cts)]);
        }
        saida_csv($slug('cotistas'),
            ['Situacao', 'Data', 'Cotista', 'Documento', 'PF/PJ', 'Cotas', 'Posicao (R$)', 'Participacao %'],
            array_map(fn($c) => [$selo, $data, $c['nome'], $c['documento'], $c['tipo_pessoa'],
                number_format((float)$c['cotas'], 4, ',', ''),
                number_format((float)$c['cotas'] * $cota, 2, ',', ''),
                number_format($totCotas > 0 ? (float)$c['cotas'] / $totCotas * 100 : 0, 4, ',', '')], $cts));

    case 'cota_serie':
        $serie = array_values(array_filter(serie_cota($pdo, $fid), fn($p) => $p['data_ref'] <= $data));
        if ($formato === 'json') saida_json($slug('serie_cota'), $meta + ['serie' => $serie]);
        saida_csv($slug('serie_cota'),
            ['Data', 'Cota', 'PL'],
            array_map(fn($p) => [$p['data_ref'],
                number_format((float)$p['valor_cota'], 8, ',', ''),
                number_format((float)$p['pl'], 2, ',', '')], $serie));
}
http_response_code(400);
exit('tipo de relatório inválido');
