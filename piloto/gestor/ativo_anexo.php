<?php
// Streaming do anexo (.pdf) de uma solicitação de cadastro de ativo.
// Gestor vê o próprio anexo; administradora vê qualquer um.
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';   // ensure_* via dominio
ensure_catalogo($pdo);

$u = exigir_perfil('gestor', 'admin');
$id = (int) ($_GET['id'] ?? 0);
$st = $pdo->prepare("SELECT anexo, anexo_nome, solicitante FROM solicitacoes_cadastro_ativo WHERE id = ?");
$st->execute([$id]);
$row = $st->fetch();

if (!$row || $row['anexo'] === null || ($u['perfil'] !== 'admin' && $row['solicitante'] !== $u['nome'])) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    die('Anexo não encontrado.');
}
$fn = preg_replace('/[^A-Za-z0-9._-]+/', '_', $row['anexo_nome'] ?: ('anexo_' . $id . '.pdf'));
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $fn . '"');
header('Content-Length: ' . strlen($row['anexo']));
echo $row['anexo'];
