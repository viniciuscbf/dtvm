<?php
// =============================================================
// Equipe do fundo — account_id, membros, permissões granulares,
// convites (aceite/recusa), transferência de gestão principal e
// recuperação de senha (simulada). Um fundo tem SEMPRE ≥1 principal.
// =============================================================

/** Catálogo de permissões concedíveis a um membro (o principal tem todas implicitamente). */
function permissoes_fundo(): array {
    return [
        // chave              => [rótulo, tipo]  (view = leitura; acao = escreve/opera)
        'ver_carteira'        => ['Ver carteira & posições', 'view'],
        'ver_caixa'           => ['Ver caixa & fluxo',        'view'],
        'ver_cotistas'        => ['Ver cotistas',             'view'],
        'ver_performance'     => ['Ver performance & relatórios', 'view'],
        'boletar'             => ['Boletar operações (compra/venda)', 'acao'],
        'aprovar_cota'        => ['Aprovar cota do dia',      'acao'],
        'operar_derivativos'  => ['Operar derivativos',       'acao'],
        'solicitar_ativos'    => ['Solicitar cadastro de ativos', 'acao'],
        'gerir_cotistas'      => ['Gerir acessos de cotistas (tokens)', 'acao'],
        'comunicar'           => ['Comunicados & assembleias', 'acao'],
        'ver_chamados_cotista'      => ['Ver chamados de cotistas', 'view'],
        'responder_chamados_cotista'=> ['Responder chamados de cotistas', 'acao'],
    ];
}

/** Permissão exigida por item de menu do gestor (null = todo membro ativo vê). */
function permissao_de_menu(): array {
    return [
        'Visão geral'          => null,
        'Aprovação de cota'    => 'aprovar_cota',
        'Boletar operação'     => 'boletar',
        'Catálogo de ativos'   => 'solicitar_ativos',
        'Derivativos'          => 'operar_derivativos',
        'Carteira'             => 'ver_carteira',
        'Caixa & Fluxo'        => 'ver_caixa',
        'Cotistas'             => 'ver_cotistas',
        'Acessos de cotistas'  => 'gerir_cotistas',
        'Performance'          => 'ver_performance',
        'Relatórios'           => 'ver_performance',
        'Enquadramento'        => 'ver_carteira',
        'Assembleias'          => 'comunicar',
        'Comunicados'          => 'comunicar',
        'Chamados de cotistas' => 'ver_chamados_cotista',
        'Equipe do fundo'      => '__principal__',   // só o principal
        'Suporte'              => null,
    ];
}

/** Gera um account_id único e legível (ex.: G-1A2B3C4D). */
function gerar_account_id(PDO $pdo): string {
    do {
        $cand = 'G-' . strtoupper(bin2hex(random_bytes(4)));
        $st = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE account_id = ?');
        $st->execute([$cand]);
    } while ((int)$st->fetchColumn() > 0);
    return $cand;
}

/** DDL + backfill idempotente: account_id, fundo_membros, senha_resets, migração de usuario_fundos. */
function ensure_equipe(PDO $pdo): void {
    // 1) coluna account_id em usuarios
    foreach ([
        "ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS account_id VARCHAR(12) NULL",
    ] as $sql) { try { ddl_portavel($pdo, $sql); } catch (Throwable $e) {} }
    try { $pdo->exec("ALTER TABLE usuarios ADD UNIQUE KEY uq_account (account_id)"); } catch (Throwable $e) {}

    // 2) tabela de membros do fundo (papel + status + permissões)
    $pdo->exec("CREATE TABLE IF NOT EXISTS fundo_membros (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fundo_id INT NOT NULL,
        usuario_id INT NOT NULL,
        papel ENUM('principal','membro') NOT NULL DEFAULT 'membro',
        status ENUM('Convidado','Ativo','Recusado','Removido') NOT NULL DEFAULT 'Convidado',
        permissoes TEXT NULL,                 -- JSON: [\"boletar\",\"ver_caixa\", ...]
        convidado_por INT NULL,
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
        atualizado_em DATETIME NULL,
        UNIQUE KEY uq_membro (fundo_id, usuario_id),
        INDEX idx_fundo (fundo_id), INDEX idx_usuario (usuario_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 3) tabela de recuperação de senha
    $pdo->exec("CREATE TABLE IF NOT EXISTS senha_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        token VARCHAR(64) NOT NULL,
        expira_em DATETIME NOT NULL,
        usado_em DATETIME NULL,
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_token (token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 4) backfill: todo usuário sem account_id ganha um
    $semAcc = $pdo->query("SELECT id FROM usuarios WHERE account_id IS NULL OR account_id = ''")->fetchAll();
    foreach ($semAcc as $r) {
        $pdo->prepare("UPDATE usuarios SET account_id = ? WHERE id = ?")->execute([gerar_account_id($pdo), (int)$r['id']]);
    }

    // 5) migração: se um gestor tem vínculo antigo (usuario_fundos) mas nenhuma linha em
    //    fundo_membros, cria-o como principal/Ativo com todas as permissões.
    $todas = json_encode(array_keys(permissoes_fundo()));
    try {
        $legado = $pdo->query("SELECT uf.usuario_id, uf.fundo_id FROM usuario_fundos uf
                               LEFT JOIN fundo_membros m ON m.usuario_id = uf.usuario_id AND m.fundo_id = uf.fundo_id
                               WHERE m.id IS NULL")->fetchAll();
        foreach ($legado as $r) {
            $pdo->prepare("INSERT IGNORE INTO fundo_membros (fundo_id, usuario_id, papel, status, permissoes)
                           VALUES (?,?, 'principal','Ativo', ?)")
                ->execute([(int)$r['fundo_id'], (int)$r['usuario_id'], $todas]);
        }
    } catch (Throwable $e) { /* usuario_fundos pode não existir */ }

    // 6) gestor com fundo_id 1:1 e sem membership → principal
    try {
        $legado2 = $pdo->query("SELECT u.id usuario_id, u.fundo_id FROM usuarios u
                                LEFT JOIN fundo_membros m ON m.usuario_id = u.id AND m.fundo_id = u.fundo_id
                                WHERE u.perfil='gestor' AND u.fundo_id IS NOT NULL AND m.id IS NULL")->fetchAll();
        foreach ($legado2 as $r) {
            $pdo->prepare("INSERT IGNORE INTO fundo_membros (fundo_id, usuario_id, papel, status, permissoes)
                           VALUES (?,?, 'principal','Ativo', ?)")
                ->execute([(int)$r['fundo_id'], (int)$r['usuario_id'], $todas]);
        }
    } catch (Throwable $e) {}
}

/** Membros de um fundo (join com usuários), opcionalmente filtrando por status. */
function membros_do_fundo(PDO $pdo, int $fid, ?string $status = null): array {
    $sql = "SELECT m.*, u.nome, u.email, u.account_id, u.gestora
            FROM fundo_membros m JOIN usuarios u ON u.id = m.usuario_id
            WHERE m.fundo_id = ?";
    $args = [$fid];
    if ($status) { $sql .= " AND m.status = ?"; $args[] = $status; }
    $sql .= " ORDER BY (m.papel='principal') DESC, u.nome";
    $st = $pdo->prepare($sql); $st->execute($args);
    return $st->fetchAll();
}

/** Linha de membership (fundo, usuário) ou null. Materializa o modelo (migração de
 *  vínculos legados → fundo_membros) uma vez por request, para funcionar logo após um reset. */
function membership(PDO $pdo, int $usuarioId, int $fid): ?array {
    static $materializado = false;
    if (!$materializado) { $materializado = true; try { ensure_equipe($pdo); } catch (Throwable $e) {} }
    $st = $pdo->prepare("SELECT * FROM fundo_membros WHERE usuario_id = ? AND fundo_id = ?");
    $st->execute([$usuarioId, $fid]);
    return $st->fetch() ?: null;
}

/** Permissões efetivas do usuário no fundo (principal = todas). */
function perms_no_fundo(PDO $pdo, array $u, int $fid): array {
    if (($u['perfil'] ?? '') === 'admin') return array_keys(permissoes_fundo());
    $m = membership($pdo, (int)$u['id'], $fid);
    if (!$m || $m['status'] !== 'Ativo') return [];
    if ($m['papel'] === 'principal') return array_keys(permissoes_fundo());
    $p = json_decode($m['permissoes'] ?? '[]', true);
    return is_array($p) ? $p : [];
}

/** Usuário é o gestor principal do fundo? */
function eh_principal(PDO $pdo, array $u, int $fid): bool {
    if (($u['perfil'] ?? '') === 'admin') return true;
    $m = membership($pdo, (int)$u['id'], $fid);
    return $m && $m['status'] === 'Ativo' && $m['papel'] === 'principal';
}

/** Pode executar/ver algo no fundo? (principal sempre pode) */
function pode(PDO $pdo, array $u, int $fid, string $perm): bool {
    if (eh_principal($pdo, $u, $fid)) return true;
    return in_array($perm, perms_no_fundo($pdo, $u, $fid), true);
}

/** Bloqueia a página se o usuário não tiver a permissão no fundo (principal/admin passam). */
function exigir_permissao(PDO $pdo, array $u, int $fid, string $perm): void {
    if (($u['perfil'] ?? '') === 'admin') return;
    if (pode($pdo, $u, $fid, $perm)) return;
    $_SESSION['flash_perm'] = 'Sem permissão para essa área neste fundo — fale com o gestor principal.';
    header('Location: ' . base_url() . 'gestor/index.php'); exit;
}

/** Convites pendentes (status Convidado) para o usuário logado. */
function convites_do_usuario(PDO $pdo, int $usuarioId): array {
    $st = $pdo->prepare("SELECT m.*, f.nome fundo_nome, f.gestora, ci.nome convidante
                         FROM fundo_membros m
                         JOIN fundos f ON f.id = m.fundo_id
                         LEFT JOIN usuarios ci ON ci.id = m.convidado_por
                         WHERE m.usuario_id = ? AND m.status = 'Convidado' ORDER BY m.criado_em DESC");
    $st->execute([$usuarioId]);
    return $st->fetchAll();
}

/** Torna um usuário o principal de um fundo (usado ao criar fundo). Idempotente. */
function tornar_principal(PDO $pdo, int $fid, int $usuarioId): void {
    $todas = json_encode(array_keys(permissoes_fundo()));
    $pdo->prepare("INSERT INTO fundo_membros (fundo_id, usuario_id, papel, status, permissoes)
                   VALUES (?,?, 'principal','Ativo', ?)
                   ON DUPLICATE KEY UPDATE papel='principal', status='Ativo', permissoes=VALUES(permissoes), atualizado_em=NOW()")
        ->execute([$fid, $usuarioId, $todas]);
}

/** Principal convida uma conta (por account_id) para o fundo. Retorna [ok, msg]. */
function convidar_membro(PDO $pdo, int $fid, string $accountId, int $convidadoPor): array {
    $accountId = strtoupper(trim($accountId));
    $st = $pdo->prepare("SELECT * FROM usuarios WHERE account_id = ? AND perfil='gestor'");
    $st->execute([$accountId]);
    $alvo = $st->fetch();
    if (!$alvo) return [false, 'Nenhuma conta de gestor com esse account_id.'];
    if ((int)$alvo['id'] === $convidadoPor) return [false, 'Você já é o gestor principal deste fundo.'];
    $m = membership($pdo, (int)$alvo['id'], $fid);
    if ($m && $m['status'] === 'Ativo') return [false, "{$alvo['nome']} já é membro ativo deste fundo."];
    if ($m && $m['status'] === 'Convidado') return [false, "{$alvo['nome']} já tem um convite pendente."];
    // (re)cria o convite — membro começa SEM nenhuma permissão
    $pdo->prepare("INSERT INTO fundo_membros (fundo_id, usuario_id, papel, status, permissoes, convidado_por)
                   VALUES (?,?, 'membro','Convidado','[]', ?)
                   ON DUPLICATE KEY UPDATE papel='membro', status='Convidado', permissoes='[]',
                     convidado_por=VALUES(convidado_por), atualizado_em=NOW()")
        ->execute([$fid, (int)$alvo['id'], $convidadoPor]);
    return [true, "Convite enviado a {$alvo['nome']} ({$alvo['email']})."];
}

/** Usuário aceita/recusa um convite. Retorna [ok, msg]. */
function responder_convite(PDO $pdo, int $membroId, int $usuarioId, bool $aceitar): array {
    $st = $pdo->prepare("SELECT * FROM fundo_membros WHERE id = ? AND usuario_id = ? AND status='Convidado'");
    $st->execute([$membroId, $usuarioId]);
    $m = $st->fetch();
    if (!$m) return [false, 'Convite não encontrado ou já respondido.'];
    $novo = $aceitar ? 'Ativo' : 'Recusado';
    $pdo->prepare("UPDATE fundo_membros SET status=?, atualizado_em=NOW() WHERE id=?")->execute([$novo, $membroId]);
    return [true, $aceitar ? 'Convite aceito — você agora participa do fundo (sem permissões até o principal liberar).'
                           : 'Convite recusado.'];
}

/** Principal define as permissões de um membro. Retorna [ok, msg]. */
function definir_permissoes(PDO $pdo, int $fid, int $membroId, array $perms): array {
    $validas = array_values(array_intersect($perms, array_keys(permissoes_fundo())));
    $st = $pdo->prepare("SELECT * FROM fundo_membros WHERE id=? AND fundo_id=?");
    $st->execute([$membroId, $fid]);
    $m = $st->fetch();
    if (!$m) return [false, 'Membro não encontrado.'];
    if ($m['papel'] === 'principal') return [false, 'O gestor principal já possui todas as permissões.'];
    $pdo->prepare("UPDATE fundo_membros SET permissoes=?, atualizado_em=NOW() WHERE id=?")
        ->execute([json_encode($validas), $membroId]);
    return [true, 'Permissões atualizadas.'];
}

/** Transfere a gestão principal para outro membro ativo. Retorna [ok, msg]. */
function transferir_principal(PDO $pdo, int $fid, int $novoUsuarioId, int $atualUsuarioId): array {
    $novo = membership($pdo, $novoUsuarioId, $fid);
    if (!$novo || $novo['status'] !== 'Ativo') return [false, 'O novo principal precisa ser um membro ativo do fundo.'];
    $todas = json_encode(array_keys(permissoes_fundo()));
    com_transacao($pdo, function () use ($pdo, $fid, $novoUsuarioId, $atualUsuarioId, $todas) {
        // novo vira principal (todas as permissões); antigo vira membro (mantém todas até ajuste)
        $pdo->prepare("UPDATE fundo_membros SET papel='principal', permissoes=?, atualizado_em=NOW() WHERE fundo_id=? AND usuario_id=?")
            ->execute([$todas, $fid, $novoUsuarioId]);
        $pdo->prepare("UPDATE fundo_membros SET papel='membro', permissoes=?, atualizado_em=NOW() WHERE fundo_id=? AND usuario_id=?")
            ->execute([$todas, $fid, $atualUsuarioId]);
    });
    return [true, 'Gestão principal transferida. Você agora é membro (com todas as permissões, ajustáveis pelo novo principal).'];
}

/** Remove (desliga) um membro do fundo. Não permite remover o principal. Retorna [ok, msg]. */
function remover_membro(PDO $pdo, int $fid, int $membroId): array {
    $st = $pdo->prepare("SELECT * FROM fundo_membros WHERE id=? AND fundo_id=?");
    $st->execute([$membroId, $fid]);
    $m = $st->fetch();
    if (!$m) return [false, 'Membro não encontrado.'];
    if ($m['papel'] === 'principal') return [false, 'Não é possível remover o gestor principal — transfira a gestão antes.'];
    $pdo->prepare("DELETE FROM fundo_membros WHERE id=?")->execute([$membroId]);
    return [true, 'Membro removido do fundo.'];
}

// ---------------- Recuperação de senha (simulada) ----------------

/** Cria um token de reset para o e-mail (se existir) e devolve [token|null, usuario|null].
 *  Nunca revela se o e-mail existe (resposta é sempre "genérica" na página). */
function criar_reset_senha(PDO $pdo, string $email): array {
    $st = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND perfil='gestor'");
    $st->execute([trim($email)]);
    $u = $st->fetch();
    if (!$u) return [null, null];
    $token = bin2hex(random_bytes(16));   // 32 hex
    $pdo->prepare("INSERT INTO senha_resets (usuario_id, token, expira_em) VALUES (?,?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))")
        ->execute([(int)$u['id'], $token]);
    return [$token, $u];
}

/** Valida token de reset (não expirado, não usado). Retorna a linha (com e-mail) ou null. */
function validar_reset_senha(PDO $pdo, string $token): ?array {
    $st = $pdo->prepare("SELECT r.*, u.email, u.nome FROM senha_resets r JOIN usuarios u ON u.id = r.usuario_id
                         WHERE r.token = ? AND r.usado_em IS NULL AND r.expira_em > NOW()");
    $st->execute([trim($token)]);
    return $st->fetch() ?: null;
}

/** Efetiva a nova senha e marca o token como usado. Retorna [ok, msg]. */
function redefinir_senha(PDO $pdo, string $token, string $novaSenha): array {
    if (strlen($novaSenha) < 6) return [false, 'A senha precisa ter ao menos 6 caracteres.'];
    $r = validar_reset_senha($pdo, $token);
    if (!$r) return [false, 'Link de redefinição inválido ou expirado.'];
    com_transacao($pdo, function () use ($pdo, $r, $novaSenha) {
        $pdo->prepare("UPDATE usuarios SET senha=? WHERE id=?")
            ->execute([password_hash($novaSenha, PASSWORD_DEFAULT), (int)$r['usuario_id']]);
        $pdo->prepare("UPDATE senha_resets SET usado_em=NOW() WHERE id=?")->execute([(int)$r['id']]);
    });
    return [true, 'Senha redefinida! Você já pode entrar com a nova senha.'];
}
