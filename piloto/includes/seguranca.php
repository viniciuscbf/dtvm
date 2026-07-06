<?php
// ============================================================
// Endurecimento de segurança — sessão, cabeçalhos, CSRF e trilha
// de auditoria. Suporta a comprovação de estrutura da Res. CVM 32
// (art. 5º, I; art. 13, V; Anexo A, art. 1º, III "c"/"e").
// ============================================================

/**
 * Bootstrap de segurança chamado uma vez por requisição (via auth.php),
 * antes de qualquer saída: configura o cookie de sessão, inicia a sessão,
 * aplica timeout por inatividade e envia os cabeçalhos de segurança.
 */
function bootstrap_seguranca(): void {
    if (session_status() === PHP_SESSION_NONE) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,       // cookie inacessível a JavaScript
            'samesite' => 'Lax',      // mitiga CSRF cross-site
            'secure'   => $https,     // só trafega em HTTPS quando disponível
        ]);
        session_start();
    }
    // Timeout por inatividade (30 min): limpa a sessão e rotaciona o id.
    $limite = 1800;
    $agora  = time();
    if (isset($_SESSION['ultima_atividade']) && ($agora - $_SESSION['ultima_atividade']) > $limite) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['ultima_atividade'] = $agora;

    headers_seguranca();
}

/** Cabeçalhos de segurança HTTP. Compatíveis com os CDNs usados pelo piloto. */
function headers_seguranca(): void {
    if (headers_sent()) return;
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    // CSP: libera apenas 'self' + jsDelivr (Bootstrap/Chart.js) e imagens data:.
    header(
        "Content-Security-Policy: default-src 'self'; " .
        "script-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; " .
        "style-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; " .
        "font-src 'self' https://cdn.jsdelivr.net; " .
        "img-src 'self' data:; " .
        "frame-ancestors 'none'"
    );
}

// ---------------- CSRF ----------------

/** Token CSRF da sessão (gerado sob demanda). */
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** Campo oculto pronto para inserir em qualquer <form method="post">. */
function csrf_campo(): string {
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/** Valida o token enviado no POST (comparação em tempo constante). */
function csrf_validar(): bool {
    $t = $_POST['csrf'] ?? '';
    return is_string($t) && $t !== '' && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}

// ---------------- Trilha de auditoria (append-only) ----------------

/**
 * Registra uma ação na trilha de auditoria. NUNCA derruba a operação:
 * se a tabela `auditoria` ainda não existir (migração não aplicada) ou
 * ocorrer qualquer erro, ignora silenciosamente.
 *
 * @param string $acao  ex.: 'login_ok','login_falha','logout','boleta_aceita',
 *                      'liquidacao_dvp','evento_creditado','mensagem_processada'
 * @param array  $opts  ator, perfil, entidade, entidade_id, fundo_id, detalhe
 */
function registrar_auditoria(PDO $pdo, string $acao, array $opts = []): void {
    try {
        $u = $_SESSION['usuario'] ?? null;
        $st = $pdo->prepare(
            'INSERT INTO auditoria (ator, perfil, acao, entidade, entidade_id, fundo_id, detalhe, ip, user_agent)
             VALUES (?,?,?,?,?,?,?,?,?)'
        );
        $st->execute([
            $opts['ator']     ?? ($u['nome'] ?? $u['email'] ?? 'anônimo'),
            $opts['perfil']   ?? ($u['perfil'] ?? null),
            $acao,
            $opts['entidade'] ?? null,
            isset($opts['entidade_id']) ? (string) $opts['entidade_id'] : null,
            isset($opts['fundo_id']) ? (int) $opts['fundo_id'] : null,
            $opts['detalhe']  ?? null,
            substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);
    } catch (Throwable $e) {
        // trilha de auditoria é best-effort: jamais interrompe a operação
    }
}
