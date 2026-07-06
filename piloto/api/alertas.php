<?php
// GET [status=Aberto] → JSON dos alertas de fraude (uso do painel admin)
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$u = usuario();
if (!$u || $u['perfil'] !== 'admin') { http_response_code(403); exit(json_encode(['erro' => 'restrito'])); }

$sql = 'SELECT a.*, f.nome AS fundo_nome FROM alertas_fraude a JOIN fundos f ON f.id = a.fundo_id';
$par = [];
if (!empty($_GET['status'])) { $sql .= ' WHERE a.status = ?'; $par[] = $_GET['status']; }
$sql .= " ORDER BY FIELD(a.severidade,'Alta','Média','Baixa'), a.data_ref DESC";
$st = $pdo->prepare($sql);
$st->execute($par);

header('Content-Type: application/json; charset=utf-8');
echo json_encode($st->fetchAll(), JSON_UNESCAPED_UNICODE);
