<?php
// =============================================================
// Motor de processamento em lote (fechamento) — resiliente e por fundo.
// Cada fundo é processado de forma ISOLADA (falha de um não derruba os outros),
// com validação (trava de publicação) e ERRO EXPLICADO por código. Erros iguais
// em fundos diferentes são consolidados na visão. Resultados gravados em SQL.
// =============================================================

/** Catálogo de erros: código => [título, explicação, como resolver]. */
function batch_erros_catalogo(): array {
    return [
        'SEM_COTAS'       => ['Fundo sem cotas emitidas na data',
            'Não há cotas/cotistas para a data de referência.',
            'Fundo pode estar em abertura ou sem a primeira integralização — confira o passivo.'],
        'SEM_CARTEIRA'    => ['Sem posição de carteira para precificar',
            'Não existe snapshot de posição/preços do fundo na data.',
            'O batch de posição/preços não rodou para este fundo — rode a marcação e reprocesse.'],
        'PL_NAO_POSITIVO' => ['PL não-positivo',
            'O patrimônio líquido calculado ficou ≤ 0.',
            'Provável erro de marcação, caixa negativo ou resgate acima do PL — revise lançamentos.'],
        'COTA_SALTO'      => ['Variação de cota fora do intervalo de sanidade',
            'A cota variou além do limite aceitável frente ao dia anterior.',
            'Possível erro de preço/marcação — publicação BLOQUEADA para conferência manual.'],
        'BALANCETE'       => ['Balancete não fecha (Ativo ≠ Passivo + PL)',
            'A identidade contábil não bate na data.',
            'Há lançamento faltando ou inconsistência entre carteira, caixa e passivo.'],
        'EXCECAO'         => ['Erro inesperado no processamento',
            'Uma exceção foi capturada durante o cálculo do fundo.',
            'Veja o detalhe técnico; o fundo foi isolado e não afetou os demais.'],
    ];
}

/** Tabela de resultados do batch (um registro por fundo por execução). */
function ensure_batch(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS processamento_batch (
        id INT AUTO_INCREMENT PRIMARY KEY,
        run_id VARCHAR(20) NOT NULL,
        data_ref DATE NOT NULL,
        fundo_id INT NOT NULL,
        status VARCHAR(10) NOT NULL,           -- ok / erro
        erro_codigo VARCHAR(40) NULL,
        erro_detalhe VARCHAR(255) NULL,
        cota DECIMAL(18,8) NULL,
        pl DECIMAL(18,2) NULL,
        criado_por VARCHAR(120),
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_run_fundo (run_id, fundo_id),
        INDEX idx_run (run_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

const BATCH_COTA_SALTO_LIMITE = 0.15;   // 15% de variação diária dispara a trava (real: 5–15% por classe)

/**
 * Processa um conjunto de fundos para uma data, de forma isolada por fundo.
 * $fundoIds = null → todos os fundos ativos/em abertura. Idempotente por (run_id, fundo).
 * $demo = true injeta um CENÁRIO DE FALHAS de demonstração (rotula os erros como tal).
 * Retorna o relatório estruturado (com consolidação de erros).
 */
function processar_fundos(PDO $pdo, string $data, ?array $fundoIds, string $runId, string $ator, bool $demo = false): array {
    ensure_batch($pdo);
    $cat = batch_erros_catalogo();

    $sql = "SELECT * FROM fundos WHERE status IN ('Ativo','Em abertura')";
    $args = [];
    if ($fundoIds) {
        $in = implode(',', array_fill(0, count($fundoIds), '?'));
        $sql .= " AND id IN ($in)";
        $args = array_map('intval', $fundoIds);
    }
    $sql .= " ORDER BY pl_atual DESC";
    $st = $pdo->prepare($sql); $st->execute($args);
    $fundos = $st->fetchAll();

    $ins = $pdo->prepare("INSERT INTO processamento_batch (run_id, data_ref, fundo_id, status, erro_codigo, erro_detalhe, cota, pl, criado_por)
                          VALUES (?,?,?,?,?,?,?,?,?)
                          ON DUPLICATE KEY UPDATE status=VALUES(status), erro_codigo=VALUES(erro_codigo),
                            erro_detalhe=VALUES(erro_detalhe), cota=VALUES(cota), pl=VALUES(pl), criado_em=NOW()");

    $resultados = [];
    $i = 0;
    foreach ($fundos as $f) {
        $i++;
        $fid = (int)$f['id'];
        $status = 'ok'; $codigo = null; $detalhe = null; $cota = null; $pl = null;

        // ---- cenário de demonstração (rotulado) para popular a visão de erros ----
        $codigoDemo = null;
        if ($demo) {
            if ($i % 4 === 1) $codigoDemo = 'COTA_SALTO';
            elseif ($i % 4 === 2) $codigoDemo = 'SEM_CARTEIRA';
        }

        if ($codigoDemo) {
            $status = 'erro'; $codigo = $codigoDemo; $detalhe = 'cenário de demonstração';
        } else {
            // ---- processamento real, ISOLADO por fundo ----
            try {
                $tot = total_cotas_na_data($pdo, $fid, $data);
                if ($tot <= 0) {
                    $status = 'erro'; $codigo = 'SEM_COTAS';
                } else {
                    $calc = calcular_cota($pdo, $f, $data);
                    if ($calc === null) {
                        $status = 'erro'; $codigo = 'SEM_CARTEIRA';
                    } else {
                        [$cotaCalc, $plCalc] = $calc;
                        if ($plCalc <= 0) {
                            $status = 'erro'; $codigo = 'PL_NAO_POSITIVO';
                            $detalhe = 'PL = ' . number_format($plCalc, 2, ',', '.');
                        } else {
                            // trava de sanidade da cota vs. dia anterior
                            $stA = $pdo->prepare("SELECT valor_cota FROM cotas_historico WHERE fundo_id=? AND data_ref < ? ORDER BY data_ref DESC LIMIT 1");
                            $stA->execute([$fid, $data]);
                            $cotaAnt = $stA->fetchColumn();
                            if ($cotaAnt && abs($cotaCalc / (float)$cotaAnt - 1) > BATCH_COTA_SALTO_LIMITE) {
                                $status = 'erro'; $codigo = 'COTA_SALTO';
                                $detalhe = 'Δ ' . number_format(($cotaCalc / (float)$cotaAnt - 1) * 100, 1, ',', '.') . '%';
                            } else {
                                $cota = $cotaCalc; $pl = $plCalc;
                            }
                        }
                    }
                }
            } catch (Throwable $e) {
                $status = 'erro'; $codigo = 'EXCECAO'; $detalhe = substr($e->getMessage(), 0, 200);
            }
        }

        $ins->execute([$runId, $data, $fid, $status, $codigo, $detalhe, $cota, $pl, $ator]);
        $resultados[] = ['id' => $fid, 'nome' => $f['nome'], 'status' => $status,
                         'codigo' => $codigo, 'detalhe' => $detalhe, 'cota' => $cota, 'pl' => $pl];
    }

    return montar_relatorio_batch($runId, $data, $resultados, $cat);
}

/** Monta o relatório: totais + consolidação de erros (agrupa fundos com o MESMO código). */
function montar_relatorio_batch(string $runId, string $data, array $resultados, array $cat): array {
    $ok = 0; $erro = 0; $consolidado = [];
    foreach ($resultados as $r) {
        if ($r['status'] === 'ok') { $ok++; continue; }
        $erro++;
        $cod = $r['codigo'] ?: 'EXCECAO';
        if (!isset($consolidado[$cod])) {
            [$titulo, $explica, $resolve] = $cat[$cod] ?? [$cod, '', ''];
            $consolidado[$cod] = ['codigo' => $cod, 'titulo' => $titulo, 'explicacao' => $explica,
                                  'resolver' => $resolve, 'fundos' => []];
        }
        $consolidado[$cod]['fundos'][] = ['id' => $r['id'], 'nome' => $r['nome'], 'detalhe' => $r['detalhe']];
    }
    // ordena grupos por quantidade de fundos afetados (maior primeiro)
    uasort($consolidado, fn($a, $b) => count($b['fundos']) <=> count($a['fundos']));
    return ['run_id' => $runId, 'data' => $data, 'total' => count($resultados),
            'ok' => $ok, 'erro' => $erro, 'fundos' => $resultados, 'consolidado' => $consolidado];
}

/** Recarrega o relatório de uma execução já gravada (para exibir ao abrir a página). */
function carregar_relatorio_batch(PDO $pdo, string $runId): ?array {
    ensure_batch($pdo);
    $st = $pdo->prepare("SELECT b.*, f.nome FROM processamento_batch b JOIN fundos f ON f.id=b.fundo_id
                         WHERE b.run_id=? ORDER BY (b.status='erro') DESC, f.pl_atual DESC");
    $st->execute([$runId]);
    $rows = $st->fetchAll();
    if (!$rows) return null;
    $data = $rows[0]['data_ref'];
    $resultados = array_map(fn($r) => ['id' => (int)$r['fundo_id'], 'nome' => $r['nome'], 'status' => $r['status'],
        'codigo' => $r['erro_codigo'], 'detalhe' => $r['erro_detalhe'], 'cota' => $r['cota'], 'pl' => $r['pl']], $rows);
    return montar_relatorio_batch($runId, $data, $resultados, batch_erros_catalogo());
}

/** Última execução gravada (run_id) ou null. */
function ultimo_run_batch(PDO $pdo): ?string {
    ensure_batch($pdo);
    $v = $pdo->query("SELECT run_id FROM processamento_batch ORDER BY criado_em DESC, id DESC LIMIT 1")->fetchColumn();
    return $v ?: null;
}
