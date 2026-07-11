<?php
// Sessão e controle de acesso — senhas com hash bcrypt (password_verify).
// AUTH_V invalida sessões de versões antigas do piloto (que tinham login mock).
const AUTH_V = 3;
require_once __DIR__ . '/seguranca.php';
bootstrap_seguranca();   // cookie de sessão seguro, timeout por inatividade e cabeçalhos de segurança

function usuario() {
    if (($_SESSION['auth_v'] ?? 0) !== AUTH_V) return null;   // sessão antiga/inválida não vale
    return $_SESSION['usuario'] ?? null;
}

function login_usuario(PDO $pdo, string $email, string $senha, string $perfil, bool $lembrar = false): bool {
    $st = $pdo->prepare('SELECT * FROM usuarios WHERE email = ? AND perfil = ?');
    $st->execute([$email, $perfil]);
    $u = $st->fetch();
    if ($u && password_verify($senha, $u['senha'])) {
        session_regenerate_id(true);
        $_SESSION['usuario'] = $u;
        $_SESSION['auth_v'] = AUTH_V;
        if ($lembrar) {   // "continuar conectado": cookie persistente de 30 dias
            $_SESSION['lembrar'] = true;
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            if (!headers_sent()) setcookie(session_name(), session_id(), ['expires' => time() + 60 * 60 * 24 * 30,
                'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => $https]);
        }
        registrar_auditoria($pdo, 'login_ok', ['ator' => $u['nome'], 'perfil' => $perfil, 'entidade' => 'sessao', 'detalhe' => "Login bem-sucedido ($email)"]);
        return true;
    }
    registrar_auditoria($pdo, 'login_falha', ['ator' => $email !== '' ? $email : 'anônimo', 'perfil' => $perfil, 'entidade' => 'sessao', 'detalhe' => 'Tentativa de login malsucedida']);
    return false;
}

function logout_usuario(): void {
    $_SESSION = [];
    session_destroy();
}

/** Bloqueia a página se não houver login com o perfil exigido. Revalida o usuário no banco a cada página. */
function exigir_perfil(string ...$perfis): array {
    global $pdo;
    $u = usuario();
    // revalidação no banco: usuário precisa continuar existindo com o mesmo perfil
    if ($u && isset($pdo)) {
        $st = $pdo->prepare('SELECT * FROM usuarios WHERE id = ? AND perfil = ?');
        $st->execute([$u['id'], $u['perfil']]);
        $atual = $st->fetch();
        if (!$atual) { logout_usuario(); $u = null; }
        else { $_SESSION['usuario'] = $u = $atual; }
    }
    if (!$u) {
        // manda para o login do portal correto
        $login = 'gestor/login.php';
        if (count($perfis) === 1 && $perfis[0] === 'admin') $login = 'admin/login.php';
        if (count($perfis) === 1 && $perfis[0] === 'custodia') $login = 'custodia/login.php';
        header('Location: ' . base_url() . $login); exit;
    }
    if (!in_array($u['perfil'], $perfis, true)) {
        $destino = ['admin' => 'admin/index.php', 'custodia' => 'custodia/index.php'][$u['perfil']] ?? 'gestor/index.php';
        header('Location: ' . base_url() . $destino); exit;
    }
    return $u;
}

/** Prefixo relativo até a raiz do piloto (páginas ficam 1 nível abaixo). */
function base_url(): string {
    return defined('BASE_URL') ? BASE_URL : '../';
}

/** Todos os fundos em que o usuário é MEMBRO ATIVO (principal ou membro com permissões). */
function fundos_do_usuario(PDO $pdo, array $u): array {
    // Modelo atual: fundo_membros (papel/status/permissões). Membro só "vê" o fundo quando Ativo.
    try {
        $st = $pdo->prepare("SELECT f.* FROM fundo_membros m JOIN fundos f ON f.id = m.fundo_id
                             WHERE m.usuario_id = ? AND m.status = 'Ativo' ORDER BY f.pl_atual DESC");
        $st->execute([$u['id']]);
        $lista = $st->fetchAll();
        if ($lista) return $lista;
    } catch (Throwable $e) { /* tabela ainda não criada — cai no legado */ }
    // Legado: vínculo N:N antigo e 1:1
    try {
        $st = $pdo->prepare('SELECT f.* FROM usuario_fundos uf JOIN fundos f ON f.id = uf.fundo_id
                             WHERE uf.usuario_id = ? ORDER BY f.pl_atual DESC');
        $st->execute([$u['id']]);
        $lista = $st->fetchAll();
        if ($lista) return $lista;
    } catch (Throwable $e) {}
    if (!empty($u['fundo_id'])) {
        $st = $pdo->prepare('SELECT * FROM fundos WHERE id = ?');
        $st->execute([$u['fundo_id']]);
        if ($f = $st->fetch()) return [$f];
    }
    return [];
}

/** Fundo em foco. Gestor: escolhe entre os seus via ?fundo_id (fica na sessão). Admin: qualquer fundo. */
function fundo_do_usuario(PDO $pdo, array $u): ?array {
    if ($u['perfil'] === 'admin') {
        if (isset($_GET['fundo_id'])) $_SESSION['admin_fundo_id'] = (int)$_GET['fundo_id'];
        $fid = $_SESSION['admin_fundo_id'] ?? 1;
        $st = $pdo->prepare('SELECT * FROM fundos WHERE id = ?');
        $st->execute([$fid]);
        return $st->fetch() ?: null;
    }
    $meus = fundos_do_usuario($pdo, $u);
    if (!$meus) return null;
    $ids = array_map(fn($f) => (int)$f['id'], $meus);
    if (isset($_GET['fundo_id']) && in_array((int)$_GET['fundo_id'], $ids, true)) {
        $_SESSION['gestor_fundo_id'] = (int)$_GET['fundo_id'];
    }
    $fid = $_SESSION['gestor_fundo_id'] ?? $ids[0];
    if (!in_array($fid, $ids, true)) $fid = $ids[0];
    foreach ($meus as $f) if ((int)$f['id'] === $fid) return $f;
    return $meus[0];
}

/** Gestor de fundo em abertura só vê a página de status da abertura. */
function exigir_fundo_ativo(array $fundo): void {
    if ($fundo['status'] !== 'Ativo' && (usuario()['perfil'] ?? '') === 'gestor') {
        header('Location: ' . base_url() . 'gestor/abertura.php'); exit;
    }
}

// ---------------- Acesso do COTISTA (por token, sem usuário) ----------------

/** Valida token informado; grava na sessão. Retorna a linha do token ou null. */
function login_token(PDO $pdo, string $token): ?array {
    $token = strtolower(trim($token));
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $token)) return null;
    $st = $pdo->prepare("SELECT t.*, f.nome fundo_nome, f.status fundo_status FROM tokens_acesso t
                         JOIN fundos f ON f.id = t.fundo_id WHERE t.token = ? AND t.status = 'Ativo'");
    $st->execute([$token]);
    $t = $st->fetch();
    if ($t) { session_regenerate_id(true); $_SESSION['cotista_token'] = $t['token']; }
    return $t ?: null;
}

/** Exige token de cotista válido (revalida no banco — revogação vale na hora). */
function exigir_token(PDO $pdo): array {
    $tok = $_SESSION['cotista_token'] ?? '';
    if ($tok) {
        $st = $pdo->prepare("SELECT t.*, f.nome fundo_nome, f.status fundo_status FROM tokens_acesso t
                             JOIN fundos f ON f.id = t.fundo_id WHERE t.token = ? AND t.status = 'Ativo'");
        $st->execute([$tok]);
        if ($t = $st->fetch()) return $t;
    }
    unset($_SESSION['cotista_token']);
    header('Location: ' . base_url() . 'cotista/index.php?expirado=1'); exit;
}

/** Data de corte dos dados visíveis pelo nível do token. */
function data_corte_token(array $token): string {
    return match ($token['nivel']) {
        'realtime' => date('Y-m-d'),
        'delay_1m' => date('Y-m-d', strtotime('-1 month')),
        default    => date('Y-m-d', strtotime('-3 months')),
    };
}

// ================= CONTA DO COTISTA (login e-mail/senha — substitui o acesso por token) =========

/** Login da conta do cotista. Retorna a conta ou null. */
function login_conta_cotista(PDO $pdo, string $email, string $senha): ?array {
    $st = $pdo->prepare("SELECT * FROM cotista_contas WHERE email = ? AND status = 'Ativa'");
    $st->execute([strtolower(trim($email))]);
    $c = $st->fetch();
    if (!$c || !password_verify($senha, $c['senha_hash'])) return null;
    session_regenerate_id(true);
    $_SESSION['cotista_conta_id'] = (int)$c['id'];
    $pdo->prepare('UPDATE cotista_contas SET ultimo_acesso = NOW() WHERE id = ?')->execute([$c['id']]);
    return $c;
}

/** Exige conta de cotista logada (revalida no banco — bloqueio vale na hora). */
function exigir_conta_cotista(PDO $pdo): array {
    $id = (int)($_SESSION['cotista_conta_id'] ?? 0);
    if ($id) {
        $st = $pdo->prepare("SELECT * FROM cotista_contas WHERE id = ? AND status = 'Ativa'");
        $st->execute([$id]);
        if ($c = $st->fetch()) return $c;
    }
    unset($_SESSION['cotista_conta_id']);
    header('Location: ' . base_url() . 'cotista/index.php?expirado=1'); exit;
}

/** Posições da conta: vínculos (linhas de cotistas) + dados do fundo, inclusive a transparência. */
function fundos_da_conta(PDO $pdo, int $contaId): array {
    $st = $pdo->prepare("SELECT c.*, f.nome fundo_nome, f.cnpj fundo_cnpj, f.classe fundo_classe,
                                f.benchmark fundo_benchmark, f.cota_atual fundo_cota, f.transparencia
                         FROM cotistas c JOIN fundos f ON f.id = c.fundo_id
                         WHERE c.conta_id = ? AND f.status = 'Ativo'
                         ORDER BY (c.cotas * f.cota_atual) DESC");
    $st->execute([$contaId]);
    return $st->fetchAll();
}

/** Data de corte pela transparência GLOBAL do fundo ('off' → null = carteira não divulgada). */
function data_corte_transparencia(?string $t): ?string {
    return match ($t ?: 'delay_1m') {
        'realtime' => date('Y-m-d'),
        'delay_3m' => date('Y-m-d', strtotime('-3 months')),
        'off'      => null,
        default    => date('Y-m-d', strtotime('-1 month')),
    };
}

/** Rótulo amigável da transparência. */
function rotulo_transparencia(?string $t): string {
    return match ($t ?: 'delay_1m') {
        'realtime' => 'tempo real',
        'delay_3m' => 'defasagem de 3 meses',
        'off'      => 'carteira não divulgada',
        default    => 'defasagem de 1 mês',
    };
}

/** UUID v4 (36 caracteres) para tokens de acesso. */
function uuid4(): string {
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}
