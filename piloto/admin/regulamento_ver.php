<?php
// Visualizador do regulamento/suplemento gerado (fundo / classe / subclasse).
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
ensure_regulamento($pdo);

$u = exigir_perfil('admin', 'gestor');
$origem = $_GET['origem'] ?? 'fundo';
$id = (int)($_GET['id'] ?? 0);

// resolve o HTML e o fundo_id (para checar acesso do gestor)
$html = null; $fundoId = 0; $titulo = 'Regulamento';
if ($origem === 'subclasse') {
    $st = $pdo->prepare("SELECT s.reg_html, s.fundo_id, s.nome FROM subclasses s WHERE s.id=?");
    $st->execute([$id]); $r = $st->fetch();
    if ($r) { $html = $r['reg_html']; $fundoId = (int)$r['fundo_id']; $titulo = 'Suplemento — ' . $r['nome']; }
} elseif ($origem === 'classe') {
    $st = $pdo->prepare("SELECT reg_html, fundo_id, nome FROM classes WHERE id=?");
    $st->execute([$id]); $r = $st->fetch();
    if ($r) { $html = $r['reg_html']; $fundoId = (int)$r['fundo_id']; $titulo = 'Regulamento — ' . $r['nome']; }
} else {
    $st = $pdo->prepare("SELECT reg_html, id, nome FROM fundos WHERE id=?");
    $st->execute([$id]); $r = $st->fetch();
    if ($r) { $html = $r['reg_html']; $fundoId = (int)$r['id']; $titulo = 'Regulamento — ' . $r['nome']; }
}

// acesso: admin vê tudo; gestor só os fundos em que é membro ativo
if ($u['perfil'] === 'gestor') {
    $ids = array_map(fn($f) => (int)$f['id'], fundos_do_usuario($pdo, $u));
    if (!in_array($fundoId, $ids, true)) { http_response_code(403); exit('Sem acesso a este regulamento.'); }
}
if (!$html) { $html = '<p class="text-muted">Este item não tem regulamento gerado (criado antes do gerador padronizado).</p>'; }
?><!DOCTYPE html>
<html lang="pt-BR"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e_html($titulo) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>@media print{.noprint{display:none}} body{background:#fff} .doc{max-width:800px;margin:20px auto;padding:0 16px}</style>
</head><body>
<div class="doc">
  <div class="noprint d-flex justify-content-between align-items-center mb-3">
    <a href="javascript:history.back()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
    <button onclick="window.print()" class="btn btn-sm btn-dark"><i class="bi bi-printer me-1"></i>Imprimir / PDF</button>
  </div>
  <?= $html ?>
</div>
</body></html>
