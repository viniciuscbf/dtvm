<?php
// Conexão PDO com o MySQL do XAMPP
date_default_timezone_set('America/Sao_Paulo');   // fuso fixo (evita virada de dia errada)
$host = 'localhost';
$db   = 'administradora';
$user = 'root';
$pass = '';           // senha padrão do XAMPP é vazia
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die('<div style="font-family:sans-serif;padding:40px">
        <h2>Erro de conexão com o banco</h2>
        <p>Verifique se o MySQL do XAMPP está rodando e se o banco <b>administradora</b> foi criado
        (importe <code>sql/schema.sql</code> e <code>sql/seed.sql</code> no phpMyAdmin).</p>
        <pre>' . htmlspecialchars($e->getMessage()) . '</pre></div>');
}
