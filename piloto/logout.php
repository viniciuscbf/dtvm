<?php
require_once __DIR__ . '/includes/auth.php';
logout_usuario();
header('Location: index.php');
