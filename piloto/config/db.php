<?php
// ============================================================================
// Conexão PDO com o MySQL/MariaDB.
//
// Produção: crie config/env.php (FORA do versionamento) definindo as
// constantes ARGUS_* — ver config/env.sample.php. Se env.php não existir,
// cai nos padrões de DESENVOLVIMENTO (XAMPP: localhost/root/senha vazia),
// para o piloto continuar rodando localmente sem configuração.
// ============================================================================
date_default_timezone_set('America/Sao_Paulo');   // fuso fixo (evita virada de dia errada)

// Carrega segredos de produção, se presentes (não versionado).
$envFile = __DIR__ . '/env.php';
if (is_file($envFile)) { require $envFile; }

// Resolve cada parâmetro: constante (env.php) > variável de ambiente > padrão dev.
$cfg = function (string $const, string $envVar, string $default): string {
    if (defined($const))                     return (string) constant($const);
    $v = getenv($envVar);
    return ($v !== false && $v !== '') ? $v : $default;
};

$host    = $cfg('ARGUS_DB_HOST', 'ARGUS_DB_HOST', 'localhost');
$db      = $cfg('ARGUS_DB_NAME', 'ARGUS_DB_NAME', 'administradora');
$user    = $cfg('ARGUS_DB_USER', 'ARGUS_DB_USER', 'root');
$pass    = $cfg('ARGUS_DB_PASS', 'ARGUS_DB_PASS', '');
$charset = 'utf8mb4';
$isProd  = $cfg('ARGUS_ENV', 'ARGUS_ENV', 'dev') === 'prod';

// Em produção, não exibir erros na tela (vaza informação); logar no servidor.
if ($isProd) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    if ($isProd) {
        // Não revelar detalhes ao visitante em produção.
        error_log('[Argus] Falha de conexão com o banco: ' . $e->getMessage());
        http_response_code(503);
        header('Retry-After: 120');
        die('<!doctype html><meta charset="utf-8">
            <div style="font-family:system-ui,sans-serif;padding:48px;max-width:520px;margin:auto;text-align:center">
              <h2>Serviço temporariamente indisponível</h2>
              <p style="color:#555">Estamos com uma instabilidade momentânea. Tente novamente em instantes.</p>
            </div>');
    }
    // Desenvolvimento: mostrar o detalhe ajuda a diagnosticar.
    die('<div style="font-family:sans-serif;padding:40px">
        <h2>Erro de conexão com o banco</h2>
        <p>Verifique se o MySQL do XAMPP está rodando e se o banco <b>' . htmlspecialchars($db) . '</b> foi criado
        (importe <code>sql/schema.sql</code> e <code>sql/seed.sql</code> no phpMyAdmin).</p>
        <pre>' . htmlspecialchars($e->getMessage()) . '</pre></div>');
}
