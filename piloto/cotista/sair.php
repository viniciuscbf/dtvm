<?php
require_once __DIR__ . '/../includes/auth.php';
unset($_SESSION['cotista_token'], $_SESSION['cotista_conta_id']);
header('Location: ../index.php');
