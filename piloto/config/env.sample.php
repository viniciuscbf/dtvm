<?php
// ============================================================================
// MODELO de configuração de produção.
// No servidor: copie este arquivo para  config/env.php  e preencha os valores.
// NUNCA versione config/env.php (já está no .gitignore) — ele contém segredos.
// ============================================================================

define('ARGUS_ENV', 'prod');               // 'prod' esconde erros; 'dev' os mostra

define('ARGUS_DB_HOST', 'localhost');      // host do MySQL (às vezes 127.0.0.1)
define('ARGUS_DB_NAME', 'SEU_BANCO');      // nome do banco criado no painel do host
define('ARGUS_DB_USER', 'SEU_USUARIO');    // usuário do banco (NÃO use root)
define('ARGUS_DB_PASS', 'SUA_SENHA_FORTE');// senha do usuário do banco
