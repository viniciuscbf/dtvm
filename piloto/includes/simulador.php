<?php
// ============================================================
// SIMULADOR MASTER — motor de controle da simulação (god mode).
// Reset da base, avanço do "dia hipotético" e injeção de eventos
// para exercitar os fluxos de custódia e administração fiduciária.
// Depende de helpers.php (calcular_cota, cota_em, ultima_data_carteira).
// ============================================================

const SIM_MASTER_SENHA = 'god123';   // senha do painel master (god-mode)

/** Exige sessão master; senão manda para o login do simulador. */
function exigir_master(): void {
    if (empty($_SESSION['sim_master'])) { header('Location: login.php'); exit; }
}

/** Cria a tabela de estado do simulador (se não existir) e inicializa a data. */
function ensure_sim_estado(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS sim_estado (
        id TINYINT PRIMARY KEY,
        data_atual DATE NOT NULL,
        atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $tem = (int)$pdo->query("SELECT COUNT(*) FROM sim_estado WHERE id=1")->fetchColumn();
    if (!$tem) {
        $max = $pdo->query("SELECT MAX(data_ref) FROM cotas_historico")->fetchColumn() ?: date('Y-m-d');
        $pdo->prepare("INSERT INTO sim_estado (id, data_atual) VALUES (1, ?)")->execute([$max]);
    }
}

/** Data hipotética atual do simulador. */
function sim_data_atual(PDO $pdo): string {
    ensure_sim_estado($pdo);
    return (string)$pdo->query("SELECT data_atual FROM sim_estado WHERE id=1")->fetchColumn();
}

// proximo_dia_util() vive em includes/dominio.php (agora considera feriados B3, não só fim de semana).

/** Executa um arquivo .sql instrução a instrução (robusto a CRLF e comentários). */
function sim_exec_sql_file(PDO $pdo, string $path): void {
    $sql = file_get_contents($path);
    if ($sql === false) throw new RuntimeException("Não foi possível ler $path");
    $sql = str_replace("\r\n", "\n", $sql);
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);   // remove comentários de linha
    foreach (explode(";\n", $sql) as $stmt) {
        $stmt = trim(rtrim(trim($stmt), ";"));
        if ($stmt !== '') $pdo->exec($stmt);
    }
}

/** RESET: recria o schema, recarrega o seed e refaz o backfill do passivo. */
function sim_resetar(PDO $pdo): void {
    $dir = __DIR__ . '/../sql/';
    sim_exec_sql_file($pdo, $dir . 'schema.sql');
    sim_exec_sql_file($pdo, $dir . 'seed.sql');
    // backfill do motor de passivo (as colunas existem no schema; o seed não as preenche)
    $pdo->exec("UPDATE cotistas SET custo_total = cotas WHERE custo_total IS NULL");
    $pdo->exec("UPDATE fundos SET tributacao = 'Ações' WHERE classe = 'Ações'");
    // reposiciona o dia do simulador para a última data do seed
    ensure_sim_estado($pdo);
    $max = $pdo->query("SELECT MAX(data_ref) FROM cotas_historico")->fetchColumn() ?: date('Y-m-d');
    $pdo->prepare("UPDATE sim_estado SET data_atual = ?, atualizado_em = NOW() WHERE id = 1")->execute([$max]);
}

/**
 * PASSAR DE DIA: avança para o próximo dia útil e gera, para cada fundo ativo,
 * um novo snapshot de carteira (com variação de preço), o CDI do dia, a nova cota/PL
 * e a esteira de processamento. Retorna [de, para, fundos].
 */
function sim_avancar_dia(PDO $pdo): array {
    $atual = sim_data_atual($pdo);
    $prox  = proximo_dia_util($atual);
    $cdiDia = cdi_fator_dia($prox);   // fator diário derivado da meta Selic vigente (curva, não fixo)

    // CDI do dia
    $chk = $pdo->prepare("SELECT COUNT(*) FROM cdi_historico WHERE data_ref = ?");
    $chk->execute([$prox]);
    if (!(int)$chk->fetchColumn()) {
        $pdo->prepare("INSERT INTO cdi_historico (data_ref, fator_diario) VALUES (?, ?)")
            ->execute([$prox, round($cdiDia, 10)]);
    }

    // DDL FORA da transação (CREATE/ALTER causam commit implícito no MySQL/MariaDB)
    ensure_dominio($pdo);   // todas as tabelas/colunas (DDL) fora da transação
    $ipcaDia = ipca_diario($prox);
    return com_transacao($pdo, function () use ($pdo, $atual, $prox, $cdiDia, $ipcaDia) {
        $fundos = $pdo->query("SELECT * FROM fundos WHERE status='Ativo'")->fetchAll();
        $n = 0; $divs = 0;
        foreach ($fundos as $f) {
            $fid = (int)$f['id'];
            // ===== PIPELINE DIÁRIO por fundo — cada passo é uma função isolada, testável e
            //       substituível; adicionar/reordenar um passo é uma linha aqui (não editar blobão). =====
            $ult = ultima_data_carteira($pdo, $fid);
            if ($ult && $ult !== $prox) proc_marcar_carteira($pdo, $fid, $ult, $prox, $cdiDia, $ipcaDia);              // 1) preços/posição
            $f['provisao_despesas'] = (float)($f['provisao_despesas'] ?? 0) + provisionar_despesas_dia($pdo, $f, $prox); // 2) provisão de despesas
            proc_zeragem_over($pdo, $f, $prox, $cdiDia);               // 2b) zeragem do caixa (compromissada over, SEL1054/1056)
            ajuste_diario_derivativos($pdo, $fid, $prox);              // 3) ajuste diário de derivativos (no caixa)
            liquidar_passivos_vencidos($pdo, $fid, $prox);            // 4) liquida passivos vencidos (cotistas/tributos)
            proc_persistir_cota($pdo, $f, $prox);                     // 5) recalcula e publica a cota/PL
            $divs += sim_gerar_posicao_e_conciliar($pdo, $fid, $prox); // 6) posição do custodiante + conciliação
            proc_esteira($pdo, $fid, $prox);                          // 7) esteira de processamento
            $n++;
        }
        $pdo->prepare("UPDATE sim_estado SET data_atual = ?, atualizado_em = NOW() WHERE id = 1")->execute([$prox]);
        return ['de' => $atual, 'para' => $prox, 'fundos' => $n, 'divergencias' => $divs];
    });
}

/** Gera a posição do custodiante (fonte INDEPENDENTE) e concilia contra a carteira da administradora. Retorna nº de divergências. */
function sim_gerar_posicao_e_conciliar(PDO $pdo, int $fid, string $data): int {
    $pdo->prepare("DELETE FROM posicao_custodiante WHERE fundo_id=? AND data_ref=?")->execute([$fid, $data]);
    $pdo->prepare("DELETE FROM conciliacao WHERE fundo_id=? AND data_ref=? AND origem='Posição × Custodiante'")->execute([$fid, $data]);
    $st = $pdo->prepare("SELECT * FROM ativos_carteira WHERE fundo_id=? AND data_ref=?");
    $st->execute([$fid, $data]);
    $divs = 0;
    foreach ($st->fetchAll() as $a) {
        $qtdCart = (float)$a['quantidade'];
        // divergência de timing (custodiante ainda não refletiu a liquidação): DETERMINÍSTICA,
        // ~8% dos ativos por dia, para a conciliação ser reprodutível (não sorteia a cada rodada).
        $seed = crc32($fid . '|' . $a['codigo'] . '|' . $data);
        $qtdCust = ($seed % 100) < 8 ? round($qtdCart * (0.85 + ($seed % 13) / 100.0), 6) : $qtdCart;
        $pdo->prepare("INSERT INTO posicao_custodiante (fundo_id, data_ref, codigo, tipo, quantidade, central) VALUES (?,?,?,?,?,?)")
            ->execute([$fid, $data, $a['codigo'], $a['tipo'], $qtdCust, central_do_ativo($a['tipo'])]);
        if (abs($qtdCart - $qtdCust) > 0.0001) {
            $diff = abs(($qtdCart - $qtdCust) * (float)$a['preco_mam']);
            $pdo->prepare("INSERT INTO conciliacao (fundo_id, data_ref, origem, situacao, classificacao, detalhe, valor_diferenca) VALUES (?,?,?,?,?,?,?)")
                ->execute([$fid, $data, 'Posição × Custodiante', 'Divergente', 'Timing',
                    "{$a['codigo']}: carteira " . number_format($qtdCart, 0, ',', '.') . " × custodiante " . number_format($qtdCust, 0, ',', '.'), $diff]);
            $divs++;
        }
    }
    return $divs;
}

/** Passo 2b — ZERAGEM DO CAIXA: o saldo livre rende compromissada over lastreada em LFT,
 *  como um custodiante real faz (contratação = SEL1054; retorno na abertura = SEL1056).
 *  Sem isso o caixa ficaria parado sem render — irreal para qualquer fundo. */
function proc_zeragem_over(PDO $pdo, array &$f, string $prox, float $cdiDia): void {
    $caixa = (float)$f['caixa_atual'];
    $rend = round($caixa * ($cdiDia - 1), 2);
    if ($caixa < 1000 || $rend <= 0) return;         // saldo residual não vai a over
    $fid = (int)$f['id'];
    $pdo->prepare("INSERT INTO movimentacoes (fundo_id, data_ref, tipo, descricao, valor) VALUES (?,?,?,?,?)")
        ->execute([$fid, $prox, 'Zeragem over',
                   'Retorno da compromissada over (lastro LFT) — rendimento de 1 dia sobre o caixa livre', $rend]);
    $pdo->prepare('UPDATE fundos SET caixa_atual = caixa_atual + ? WHERE id = ?')->execute([$rend, $fid]);
    $f['caixa_atual'] = $caixa + $rend;
    $ref = 'OVER-' . str_replace('-', '', $prox);
    $pdo->prepare("INSERT INTO mensagens_spb (central, codigo, fundo_id, referencia, descricao, valor, status, recebida_em, processada_em, processada_por)
                   VALUES ('SELIC','SEL1056',?,?,?,?,'Processada',?,?,'Rotina automática')")
        ->execute([$fid, $ref, 'Retorno da compromissada over — principal + rendimento creditados na abertura (6h30–6h45)',
                   round($caixa + $rend, 2), $prox . ' 06:40:00', $prox . ' 06:40:00']);
    $pdo->prepare("INSERT INTO mensagens_spb (central, codigo, fundo_id, referencia, descricao, valor, status, recebida_em, processada_em, processada_por)
                   VALUES ('SELIC','SEL1054',?,?,?,?,'Processada',?,?,'Rotina automática')")
        ->execute([$fid, $ref, 'Operação compromissada (zeragem over) — caixa livre aplicado com lastro em LFT, retorno na próxima abertura',
                   round($caixa + $rend, 2), $prox . ' 17:55:00', $prox . ' 17:55:00']);
}

// ---------------- Passos do pipeline diário (proc_*) — cada um isolado e testável ----------------

/** Passo 1 — remarca a carteira do dia anterior para $prox (preço por classe/indexador). */
function proc_marcar_carteira(PDO $pdo, int $fid, string $ult, string $prox, float $cdiDia, float $ipcaDia): void {
    $st = $pdo->prepare("SELECT * FROM ativos_carteira WHERE fundo_id = ? AND data_ref = ?");
    $st->execute([$fid, $ult]);
    foreach ($st->fetchAll() as $a) {
        [$novo, $fonte] = preco_novo_do_ativo($pdo, $a, $cdiDia, $ipcaDia, $prox);
        // feed de mercado (fonte INDEPENDENTE) — a referência de preço nasce aqui
        $pdo->prepare("INSERT INTO precos_mercado (codigo, data_ref, preco, fonte) VALUES (?,?,?,?)
                       ON DUPLICATE KEY UPDATE preco=VALUES(preco), fonte=VALUES(fonte)")
            ->execute([$a['codigo'], $prox, $novo, $fonte]);
        $pdo->prepare("INSERT INTO ativos_carteira (fundo_id, codigo, tipo, quantidade, preco_medio, preco_mam, preco_referencia, fonte_preco, data_ref)
                       VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$fid, $a['codigo'], $a['tipo'], $a['quantidade'], $a['preco_medio'], $novo, $novo, $fonte, $prox]);
    }
}

/** Passo 5 — recalcula a cota/PL (já líquida de provisão, derivativos e passivos) e publica. */
function proc_persistir_cota(PDO $pdo, array $f, string $prox): void {
    $fid = (int) $f['id'];
    $calc = calcular_cota($pdo, $f, $prox);
    if (!$calc) return;
    [$cota, $pl] = $calc;
    $chk = $pdo->prepare("SELECT COUNT(*) FROM cotas_historico WHERE fundo_id = ? AND data_ref = ?");
    $chk->execute([$fid, $prox]);
    if ((int) $chk->fetchColumn()) $pdo->prepare("UPDATE cotas_historico SET valor_cota = ?, pl = ? WHERE fundo_id = ? AND data_ref = ?")->execute([$cota, $pl, $fid, $prox]);
    else $pdo->prepare("INSERT INTO cotas_historico (fundo_id, data_ref, valor_cota, pl) VALUES (?,?,?,?)")->execute([$fid, $prox, $cota, $pl]);
    $pdo->prepare("UPDATE fundos SET cota_atual = ?, pl_atual = ? WHERE id = ?")->execute([$cota, $pl, $fid]);
}

/** Passo 7 — registra a esteira de processamento do dia. */
function proc_esteira(PDO $pdo, int $fid, string $prox): void {
    $ordem = 1;
    foreach (['Posição', 'Preços', 'Caixa', 'Conciliação', 'Cota', 'ANBIMA'] as $etapa)
        $pdo->prepare("INSERT INTO processamento (fundo_id, data_ref, etapa, ordem, status, horario, mensagem)
                       VALUES (?,?,?,?, 'OK', CURTIME(), 'Processado pelo simulador')")->execute([$fid, $prox, $etapa, $ordem++]);
}

// ---------------- Injetores de eventos ----------------

/** Credita um recebimento no caixa do fundo (aporte/provento/vencimento). */
function sim_injetar_recebimento(PDO $pdo, int $fid, string $tipo, float $valor, string $data): void {
    $pdo->prepare("INSERT INTO movimentacoes (fundo_id, data_ref, tipo, descricao, valor) VALUES (?,?,?,?,?)")
        ->execute([$fid, $data, $tipo, "$tipo simulado pelo master", $valor]);
    $pdo->prepare("UPDATE fundos SET caixa_atual = caixa_atual + ? WHERE id = ?")->execute([$valor, $fid]);
}

/** Cria uma boleta pendente do gestor (para a mesa de custódia aceitar/liquidar). */
function sim_gerar_boleta(PDO $pdo, int $fid, string $data): void {
    // boleta plausível: compra de LTN pelo PU de mercado (não valores redondos irreais)
    $qtd = 250; $preco = 883.47; $valor = round($qtd * $preco, 2);
    $pdo->prepare("INSERT INTO boletas (fundo_id, data_operacao, operacao, ativo_codigo, tipo_ativo, quantidade, preco, valor, contraparte, status, criado_por)
                   VALUES (?,?, 'Compra', 'LTN 2027', 'Título Público', ?, ?, ?, 'Tesouraria (sim)', 'Enviada', 'Simulador Master')")
        ->execute([$fid, $data, $qtd, $preco, $valor]);
}

/** Injeta uma mensagem na fila de mensageria RSFN/SPB da custódia. */
function sim_injetar_mensagem(PDO $pdo, int $fid): void {
    $pdo->prepare("INSERT INTO mensagens_spb (central, codigo, fundo_id, referencia, descricao, valor, status, recebida_em)
                   VALUES ('SELIC','SEL1052', ?, 'SIM', 'Mensagem de posição simulada pelo master', NULL, 'Recebida', NOW())")
        ->execute([$fid]);
}

/** Cria um ofício da CVM com prazo de resposta (para o regulatório). */
function sim_criar_oficio(PDO $pdo, int $fid, string $data): void {
    $prazo = (new DateTime($data))->modify('+10 day')->format('Y-m-d');
    $pdo->prepare("INSERT INTO oficios_cvm (fundo_id, origem, numero, assunto, teor, recebido_em, prazo_resposta, status)
                   VALUES (?, 'CVM', ?, 'Solicitação de esclarecimentos (simulada)', 'Ofício gerado pelo Simulador Master para exercitar o fluxo de resposta.', ?, ?, 'Recebido')")
        ->execute([$fid, 'OF/CVM/SIM/' . mt_rand(1000, 9999), $data, $prazo]);
}

/** Cria uma divergência de conciliação aberta (para a administradora tratar). */
function sim_criar_divergencia(PDO $pdo, int $fid, string $data): void {
    $pdo->prepare("INSERT INTO conciliacao (fundo_id, data_ref, origem, situacao, classificacao, detalhe, valor_diferenca)
                   VALUES (?, ?, 'Posição × Custodiante', 'Divergente', 'Timing', 'Divergência simulada pelo master — posição do custodiante ainda não refletida', ?)")
        ->execute([$fid, $data, mt_rand(1000, 90000)]);
}

/** Métricas para o painel do simulador. */
function sim_stats(PDO $pdo): array {
    $q = fn(string $sql) => (int)$pdo->query($sql)->fetchColumn();
    return [
        'fundos'       => $q("SELECT COUNT(*) FROM fundos WHERE status='Ativo'"),
        'cotistas'     => $q("SELECT COUNT(*) FROM cotistas"),
        'boletas'      => $q("SELECT COUNT(*) FROM boletas WHERE status='Enviada'"),
        'liquidacoes'  => $q("SELECT COUNT(*) FROM liquidacoes WHERE status='Pendente'"),
        'mensagens'    => $q("SELECT COUNT(*) FROM mensagens_spb WHERE status<>'Processada'"),
        'divergencias' => $q("SELECT COUNT(*) FROM conciliacao WHERE situacao='Divergente'"),
        'oficios'      => $q("SELECT COUNT(*) FROM oficios_cvm WHERE status='Recebido'"),
    ];
}
