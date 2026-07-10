<?php
// Gera um MODELO (minuta em branco) de um documento do fundo para download, a partir dos
// dados já digitados no formulário de constituição. Público (o cadastro é público); sem DB.
require_once __DIR__ . '/../includes/templates_docs.php';

$ger = tpl_gerador_por_nome($_GET['doc'] ?? '');
if (!$ger) { http_response_code(404); header('Content-Type: text/plain; charset=utf-8'); die('Modelo não disponível para este documento.'); }

// fundo "de rascunho" montado com o que o usuário já preencheu (placeholders no que faltar)
$f = [
    'nome'             => trim($_GET['nome_fundo'] ?? '') ?: '[Nome do fundo]',
    'cnpj'             => '[CNPJ em registro]',
    'classe'           => trim($_GET['classe'] ?? '') ?: 'Renda Fixa',
    'publico_alvo'     => trim($_GET['publico'] ?? '') ?: 'Investidores em geral',
    'condominio'       => trim($_GET['condominio'] ?? '') ?: 'Aberto',
    'gestora'          => trim($_GET['gestora'] ?? '') ?: '[Gestora]',
    'benchmark'        => trim($_GET['benchmark'] ?? '') ?: 'CDI',
    'taxa_adm'         => 0.0008,
    'taxa_gestao'      => (float)str_replace(',', '.', $_GET['taxa_gestao'] ?? '0') / 100,
    'taxa_performance' => (float)str_replace(',', '.', $_GET['taxa_perf'] ?? '0') / 100,
];
$html = $ger($f);
$slug = 'modelo_' . preg_replace('/[^a-z0-9]+/i', '_', mb_strtolower($_GET['doc'] ?? 'documento'));

// ?ver=1 abre no navegador (pré-visualização); sem isso, baixa em .docx (editável no Word)
if (!isset($_GET['ver'])) {
    enviar_documento_download($html, $slug);
    exit;
}
$titulo = htmlspecialchars($_GET['doc'] ?? 'documento', ENT_QUOTES, 'UTF-8');
header('Content-Type: text/html; charset=utf-8');
echo "<!doctype html><html lang=\"pt-BR\"><head><meta charset=\"utf-8\"><title>$titulo</title>";
echo "<style>body{font-family:Georgia,'Times New Roman',serif;max-width:840px;margin:24px auto;padding:0 20px;color:#1e293b;line-height:1.5}"
   . "h2{font-size:1.3rem}h3{font-size:1.02rem;margin-top:18px}hr{border-color:#e2e8f0}"
   . "em{color:#b7791f;font-weight:600}"
   . ".doc-rodape{margin-top:24px;font-size:.8rem;color:#b7791f;border-top:1px solid #e2e8f0;padding-top:10px}"
   . ".doc-nota{font-size:.82rem;color:#b7791f;background:#fffbeb;border-radius:8px;padding:8px 12px}</style></head><body>";
echo tpl_realcar_campos($html);
echo "</body></html>";
