<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
if (usuario()) registrar_auditoria($pdo, 'logout', ['entidade' => 'sessao', 'detalhe' => 'Encerramento de sessão']);
logout_usuario();
header('Location: index.php');
