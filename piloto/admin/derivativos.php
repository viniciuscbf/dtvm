<?php
// ============================================================
// Derivativos deixaram de ser uma seção à parte. Futuros (DI1, DAP, …) agora são
// instrumentos do CATÁLOGO, boletados como qualquer ativo em "Boletar operação"
// e exibidos na "Carteira". Esta rota antiga apenas redireciona.
// ============================================================
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$u = usuario();
if ($u && ($u['perfil'] ?? '') === 'gestor') {
    header('Location: ../gestor/carteira.php');
} else {
    header('Location: carteiras.php');
}
exit;
