<?php
// Simulador Master — encerra a sessão do painel (não afeta os logins dos portais).
require_once __DIR__ . '/../includes/auth.php';
unset($_SESSION['sim_master']);
header('Location: login.php');
