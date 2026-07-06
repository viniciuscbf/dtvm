<?php
require_once __DIR__ . '/../includes/auth.php';
unset($_SESSION['cotista_token']);
header('Location: ../index.php');
