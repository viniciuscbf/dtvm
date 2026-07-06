<?php
// ============================================================
// Helpers de formatação e cálculo financeiro (usados em tudo)
// ============================================================

function moeda($v, int $dec = 2): string {
    return 'R$ ' . number_format((float)$v, $dec, ',', '.');
}

function moeda_compacta($v): string {
    $v = (float)$v;
    $abs = abs($v);
    if ($abs >= 1e9) return 'R$ ' . number_format($v / 1e9, 2, ',', '.') . ' bi';
    if ($abs >= 1e6) return 'R$ ' . number_format($v / 1e6, 1, ',', '.') . ' mi';
    if ($abs >= 1e3) return 'R$ ' . number_format($v / 1e3, 0, ',', '.') . ' mil';
    return moeda($v);
}

function pct($v, int $dec = 2): string {
    if ($v === null) return '—';
    return number_format((float)$v, $dec, ',', '.') . '%';
}

function pct_color($v): string {
    if ($v === null) return '';
    return $v >= 0 ? 'text-success' : 'text-danger';
}

function e_html($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function badge(string $texto, string $cor): string {
    return '<span class="badge badge-soft-' . $cor . '">' . e_html($texto) . '</span>';
}

function badge_status(string $s): string {
    $map = [
        'OK' => 'success', 'Ativo' => 'success', 'Conciliado' => 'success', 'Concluída' => 'success',
        'Pago' => 'success', 'Aprovado' => 'success', 'Respondido' => 'success', 'Reenquadrado' => 'success',
        'Resolvido' => 'success', 'Encerrado' => 'secondary', 'Falso positivo' => 'secondary',
        'Pendente' => 'warning', 'Em andamento' => 'warning', 'Em abertura' => 'warning',
        'Em análise' => 'warning', 'Em revisão' => 'warning', 'Apurado' => 'warning', 'Rodando' => 'info',
        'Instruído' => 'info', 'Aberto' => 'info', 'Em aberto' => 'warning',
        'Erro' => 'danger', 'Divergente' => 'danger', 'Escalado' => 'danger',
        'Alta' => 'danger', 'Média' => 'warning', 'Baixa' => 'secondary',
        'Timing' => 'info', 'Suspeita' => 'danger',
    ];
    return badge($s, $map[$s] ?? 'secondary');
}

function data_br($d): string {
    if (!$d) return '—';
    return date('d/m/Y', strtotime($d));
}

// ---------------- Séries e rentabilidade ----------------

function ultima_data_cota(PDO $pdo, int $fid): ?string {
    $st = $pdo->prepare('SELECT MAX(data_ref) FROM cotas_historico WHERE fundo_id = ?');
    $st->execute([$fid]);
    return $st->fetchColumn() ?: null;
}

function serie_cota(PDO $pdo, int $fid, ?string $desde = null): array {
    if ($desde) {
        $st = $pdo->prepare('SELECT data_ref, valor_cota, pl FROM cotas_historico WHERE fundo_id = ? AND data_ref >= ? ORDER BY data_ref');
        $st->execute([$fid, $desde]);
    } else {
        $st = $pdo->prepare('SELECT data_ref, valor_cota, pl FROM cotas_historico WHERE fundo_id = ? ORDER BY data_ref');
        $st->execute([$fid]);
    }
    return $st->fetchAll();
}

/** Cota vigente na data (última <= data). */
function cota_em(PDO $pdo, int $fid, string $data): ?float {
    $st = $pdo->prepare('SELECT valor_cota FROM cotas_historico WHERE fundo_id = ? AND data_ref <= ? ORDER BY data_ref DESC LIMIT 1');
    $st->execute([$fid, $data]);
    $v = $st->fetchColumn();
    return $v !== false ? (float)$v : null;
}

/** Rentabilidade % entre a cota vigente em $de e a cota em $ate (padrão: última). */
function rent_desde(PDO $pdo, int $fid, string $de, ?string $ate = null): ?float {
    $fim = $ate ?: ultima_data_cota($pdo, $fid);
    if (!$fim) return null;
    $c0 = cota_em($pdo, $fid, $de);
    $c1 = cota_em($pdo, $fid, $fim);
    if (!$c0 || !$c1) return null;
    return ($c1 / $c0 - 1) * 100;
}

/** Fator CDI acumulado entre datas (exclusivo em $de, inclusivo em $ate) → % */
function cdi_desde(PDO $pdo, string $de, string $ate): ?float {
    $st = $pdo->prepare('SELECT fator_diario FROM cdi_historico WHERE data_ref > ? AND data_ref <= ? ORDER BY data_ref');
    $st->execute([$de, $ate]);
    $f = 1.0;
    $n = 0;
    foreach ($st->fetchAll() as $r) { $f *= (float)$r['fator_diario']; $n++; }
    return $n ? ($f - 1) * 100 : null;
}

/** Datas-base dos períodos padrão a partir da última data de cota (ou de uma data retroativa). */
function datas_periodos(PDO $pdo, int $fid, ?string $ate = null): array {
    if ($ate) {
        $st = $pdo->prepare('SELECT MAX(data_ref) FROM cotas_historico WHERE fundo_id = ? AND data_ref <= ?');
        $st->execute([$fid, $ate]);
        $fim = $st->fetchColumn() ?: null;
    } else {
        $fim = ultima_data_cota($pdo, $fid);
    }
    if (!$fim) return [];
    $st = $pdo->prepare('SELECT MIN(data_ref) FROM cotas_historico WHERE fundo_id = ?');
    $st->execute([$fid]);
    $inicio = $st->fetchColumn();
    // dia = penúltima data de cota
    $st = $pdo->prepare('SELECT data_ref FROM cotas_historico WHERE fundo_id = ? AND data_ref < ? ORDER BY data_ref DESC LIMIT 1');
    $st->execute([$fid, $fim]);
    $ontem = $st->fetchColumn() ?: $inicio;
    return [
        'Dia'          => $ontem,
        'Mês'          => date('Y-m-t', strtotime($fim . ' -1 month')),
        'Ano'          => date('Y-12-31', strtotime($fim . ' -1 year')),
        '12 meses'     => date('Y-m-d', strtotime($fim . ' -12 months')),
        'Desde início' => $inicio,
        '_fim'         => $fim,
    ];
}

/** Tabela pronta: período → [fundo %, cdi %, %CDI]. $ate permite visão retroativa. */
function tabela_performance(PDO $pdo, int $fid, ?string $ate = null): array {
    $datas = datas_periodos($pdo, $fid, $ate);
    if (!$datas) return [];
    $fim = $datas['_fim']; unset($datas['_fim']);
    $out = [];
    foreach ($datas as $rotulo => $de) {
        $rf = rent_desde($pdo, $fid, $de, $fim);
        $rc = cdi_desde($pdo, $de, $fim);
        $out[$rotulo] = [
            'fundo' => $rf,
            'cdi'   => $rc,
            'pct_cdi' => ($rf !== null && $rc !== null && abs($rc) > 1e-9) ? $rf / $rc * 100 : null,
        ];
    }
    return $out;
}

// ---------------- Carteira e enquadramento ----------------

/** Última data de snapshot de carteira do fundo. */
function ultima_data_carteira(PDO $pdo, int $fid): ?string {
    $st = $pdo->prepare('SELECT MAX(data_ref) FROM ativos_carteira WHERE fundo_id = ?');
    $st->execute([$fid]);
    return $st->fetchColumn() ?: null;
}

/** Datas com snapshot de carteira (mais recente primeiro) — para os seletores retroativos. */
function datas_carteira(PDO $pdo, int $fid): array {
    $st = $pdo->prepare('SELECT DISTINCT data_ref FROM ativos_carteira WHERE fundo_id = ? ORDER BY data_ref DESC');
    $st->execute([$fid]);
    return array_column($st->fetchAll(), 'data_ref');
}

/** Carteira do fundo em uma data (padrão: snapshot mais recente). */
function carteira(PDO $pdo, int $fid, ?string $data = null): array {
    $data = $data ?: ultima_data_carteira($pdo, $fid);
    if (!$data) return [];
    $st = $pdo->prepare('SELECT * FROM ativos_carteira WHERE fundo_id = ? AND data_ref = ? ORDER BY quantidade * preco_mam DESC');
    $st->execute([$fid, $data]);
    $rows = $st->fetchAll();
    foreach ($rows as &$r) {
        $r['valor_mercado'] = $r['quantidade'] * $r['preco_mam'];
        $r['custo'] = $r['quantidade'] * $r['preco_medio'];
        $r['resultado'] = $r['valor_mercado'] - $r['custo'];
    }
    return $rows;
}

const CLASSES_RF = ['Título Público', 'Debênture', 'CDB', 'LFT', 'CRI/CRA'];
const CLASSES_CREDITO = ['Debênture', 'CDB', 'CRI/CRA'];

/** Mede uma regra de enquadramento contra a carteira. Retorna [medido%, ok]. */
function medir_regra(PDO $pdo, array $fundo, array $regra): array {
    $ativos = carteira($pdo, (int)$fundo['id']);
    $pl = (float)$fundo['pl_atual'];
    if ($pl <= 0) return [0.0, true];
    $soma = function (callable $filtro) use ($ativos): float {
        $s = 0.0;
        foreach ($ativos as $a) if ($filtro($a)) $s += $a['valor_mercado'];
        return $s;
    };
    $limite = (float)$regra['limite'];
    switch ($regra['tipo_regra']) {
        case 'min_rf':
            $v = $soma(fn($a) => in_array($a['tipo'], CLASSES_RF, true)) / $pl * 100;
            return [$v, $v >= $limite];
        case 'max_acoes':
            $v = $soma(fn($a) => $a['tipo'] === 'Ação') / $pl * 100;
            return [$v, $v <= $limite];
        case 'max_credito_privado':
            $v = $soma(fn($a) => in_array($a['tipo'], CLASSES_CREDITO, true)) / $pl * 100;
            return [$v, $v <= $limite];
        case 'max_ativo_unico':
            $max = 0.0;
            foreach ($ativos as $a) $max = max($max, $a['valor_mercado']);
            $v = $max / $pl * 100;
            return [$v, $v <= $limite];
        case 'max_caixa':
            $v = (float)$fundo['caixa_atual'] / $pl * 100;
            return [$v, $v <= $limite];
    }
    return [0.0, true];
}

/** O fundo está enquadrado? (todas as regras ok e sem evento em aberto) */
function situacao_enquadramento(PDO $pdo, array $fundo): array {
    $st = $pdo->prepare('SELECT * FROM enquadramento_regras WHERE fundo_id = ?');
    $st->execute([$fundo['id']]);
    $violadas = [];
    foreach ($st->fetchAll() as $regra) {
        [, $ok] = medir_regra($pdo, $fundo, $regra);
        if (!$ok) $violadas[] = $regra['descricao'];
    }
    return [count($violadas) === 0, $violadas];
}

// ---------------- Receita (regra comercial do plano) ----------------

/** Taxa de administração mensal: 0,08% a.a. sobre o PL, piso R$ 100/mês. */
function apurar_taxa_mensal(float $pl, float $taxa_aa = 0.0008): array {
    $percentual = $pl * $taxa_aa / 12;
    $valor = max(100.0, $percentual);
    return [$valor, $percentual < 100.0];   // [valor, piso aplicado?]
}

// ---------------- Fechamento de cota e liberação de dados ----------------

/** Última versão do fechamento de uma data. */
function fechamento(PDO $pdo, int $fid, string $data): ?array {
    $st = $pdo->prepare('SELECT * FROM fechamentos WHERE fundo_id = ? AND data_ref = ? ORDER BY versao DESC LIMIT 1');
    $st->execute([$fid, $data]);
    $f = $st->fetch();
    return $f ?: null;
}

/**
 * Os dados do fundo naquela data estão disponíveis para relatório/download?
 * Regra (dinâmica real): assim que a administradora PROCESSOU o dia — bateu carteira e
 * calculou a cota (existe fechamento) — carteira e relatórios ficam disponíveis. Enquanto
 * a cota não for aprovada pelo gestor, o material sai marcado como PRÉVIA; depois, oficial.
 * Só não há download quando o dia ainda não foi processado.
 */
function data_liberada(PDO $pdo, int $fid, string $data, string $perfil): bool {
    if (in_array($perfil, ['admin', 'custodia'], true)) return true;
    return fechamento($pdo, $fid, $data) !== null;
}

/** Rótulo do status do dia para carimbar relatórios: 'PRÉVIA' ou 'OFICIAL'. */
function selo_dia(PDO $pdo, int $fid, string $data): string {
    $f = fechamento($pdo, $fid, $data);
    return $f && in_array($f['status'], ['Aprovada', 'Republicada'], true) ? 'OFICIAL' : 'PRÉVIA';
}

/** Total de cotas emitidas do fundo. */
function total_cotas(PDO $pdo, int $fid): float {
    $st = $pdo->prepare('SELECT COALESCE(SUM(cotas),0) FROM cotistas WHERE fundo_id = ?');
    $st->execute([$fid]);
    return (float)$st->fetchColumn();
}

/**
 * Recalcula a cota de uma data a partir do snapshot da carteira + caixa:
 * cota = (Σ quantidade × preço MaM + caixa) / cotas emitidas.
 * Retorna [cota, pl] ou null se não houver snapshot/cotas.
 */
function calcular_cota(PDO $pdo, array $fundo, string $data): ?array {
    $ativos = carteira($pdo, (int)$fundo['id'], $data);
    $totCotas = total_cotas($pdo, (int)$fundo['id']);
    if (!$ativos || $totCotas <= 0) return null;
    $pl = array_sum(array_column($ativos, 'valor_mercado')) + (float)$fundo['caixa_atual'];
    return [$pl / $totCotas, $pl];
}
