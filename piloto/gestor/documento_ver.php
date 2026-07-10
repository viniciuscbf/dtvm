<?php
// Visualização / download de uma minuta de documento do fundo (guardada em documentos_abertura.conteudo).
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';   // puxa templates_docs.php via dominio

$u = exigir_perfil('gestor', 'admin');
$doc = documento_para_usuario($pdo, $u, (int)($_GET['id'] ?? 0));
if (!$doc || $doc['conteudo'] === null) { http_response_code(404); die('Documento não encontrado.'); }

// download como arquivo
if (isset($_GET['dl'])) {
    $fn = preg_replace('/[^A-Za-z0-9._-]+/', '_', $doc['arquivo'] ?: ('documento_' . $doc['id'] . '.html'));
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fn . '"');
    echo "<!doctype html><html lang=pt-BR><head><meta charset=utf-8><title>" . e_html($doc['nome'])
       . "</title></head><body style=\"font-family:Georgia,serif;max-width:820px;margin:24px auto;padding:0 16px\">"
       . $doc['conteudo'] . "</body></html>";
    exit;
}
$voltar = ($u['perfil'] === 'admin') ? 'aberturas.php' : 'abertura.php';
?><!doctype html>
<html lang="pt-BR"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e_html($doc['nome']) ?> · <?= e_html($doc['fundo_nome']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="../assets/css/style.css" rel="stylesheet">
<style>
  .doc-wrap{max-width:840px;margin:0 auto;background:#fff;padding:34px 44px;border:1px solid var(--borda);border-radius:12px;font-family:Georgia,'Times New Roman',serif;color:#1e293b;line-height:1.5}
  .doc-wrap h2{font-size:1.3rem;margin-bottom:.3rem} .doc-wrap h3{font-size:1.02rem;margin-top:20px;color:#334155}
  .doc-wrap li{margin:4px 0} .doc-wrap hr{border-color:#e2e8f0}
  .doc-rodape{margin-top:26px;font-size:.8rem;color:#64748b;border-top:1px solid #e2e8f0;padding-top:12px}
  .doc-nota{font-size:.82rem;color:#64748b;background:#f8fafc;border-radius:8px;padding:8px 12px}
  @media print{.noprint{display:none} .doc-wrap{border:0;padding:0}}
</style>
</head><body style="background:var(--bg)">
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3 noprint" style="max-width:840px;margin:0 auto">
    <a href="<?= $voltar ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
    <div class="d-flex gap-2">
      <a href="?id=<?= (int)$doc['id'] ?>&dl=1" class="btn btn-sm btn-outline-primary"><i class="bi bi-download me-1"></i>Baixar</a>
      <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer me-1"></i>Imprimir</button>
    </div>
  </div>
  <div class="doc-wrap"><?= $doc['conteudo'] ?></div>
</div>
</body></html>
