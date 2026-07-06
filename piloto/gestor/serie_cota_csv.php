<?php
// Download da série histórica da cota em CSV
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$u = usuario();
if (!$u) { header('Location: ../index.php'); exit; }
$fundo = fundo_do_usuario($pdo, $u);
if (!$fundo) die('Sem fundo vinculado.');

$serie = serie_cota($pdo, (int)$fundo['id']);
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="serie_cota_' . date('Ymd') . '.csv"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['Data', 'Cota', 'PL'], ';');
foreach ($serie as $p) {
    fputcsv($out, [$p['data_ref'],
        number_format((float)$p['valor_cota'], 8, ',', ''),
        number_format((float)$p['pl'], 2, ',', '')], ';');
}
fclose($out);
