<?php
// GET fundo_id, periodo (1m|3m|6m|12m|ini) → JSON {labels, fundo[], cdi[]} base 100
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$u = usuario();
if (!$u) { http_response_code(401); exit(json_encode(['erro' => 'não autenticado'])); }

$fid = (int)($_GET['fundo_id'] ?? 0);
if ($u['perfil'] !== 'admin' && (int)$u['fundo_id'] !== $fid) {
    http_response_code(403); exit(json_encode(['erro' => 'sem acesso a este fundo']));
}

$fim = ultima_data_cota($pdo, $fid);
if (!$fim) { exit(json_encode(['labels' => [], 'fundo' => [], 'cdi' => []])); }

$mapa = ['1m' => '-1 month', '3m' => '-3 months', '6m' => '-6 months', '12m' => '-12 months'];
$periodo = $_GET['periodo'] ?? '12m';
$desde = isset($mapa[$periodo]) ? date('Y-m-d', strtotime($fim . ' ' . $mapa[$periodo])) : null;

$serie = serie_cota($pdo, $fid, $desde);
if (!$serie) { exit(json_encode(['labels' => [], 'fundo' => [], 'cdi' => []])); }

// CDI no mesmo intervalo
$st = $pdo->prepare('SELECT data_ref, fator_diario FROM cdi_historico WHERE data_ref >= ? AND data_ref <= ? ORDER BY data_ref');
$st->execute([$serie[0]['data_ref'], $fim]);
$cdiPorData = [];
foreach ($st->fetchAll() as $r) $cdiPorData[$r['data_ref']] = (float)$r['fator_diario'];

$labels = []; $fundo = []; $cdi = [];
$base = (float)$serie[0]['valor_cota'];
$fatorCdi = 1.0;
$primeiro = true;
foreach ($serie as $p) {
    if (!$primeiro && isset($cdiPorData[$p['data_ref']])) $fatorCdi *= $cdiPorData[$p['data_ref']];
    $primeiro = false;
    $labels[] = date('d/m/y', strtotime($p['data_ref']));
    $fundo[] = round((float)$p['valor_cota'] / $base * 100, 3);
    $cdi[]   = round($fatorCdi * 100, 3);
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['labels' => $labels, 'fundo' => $fundo, 'cdi' => $cdi]);
