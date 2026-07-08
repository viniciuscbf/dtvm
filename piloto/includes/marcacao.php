<?php
// ============================================================
// Marcação a mercado por CLASSE / INDEXADOR.
//  • Renda fixa: acrual diário pela curva do indexador (CDI %, CDI+spread, IPCA+, pré).
//    Fonte ANBIMA para públicos/debêntures; CDB e ilíquidos via COMITÊ da administradora.
//  • Cota de fundo (FIF/FIC): marcada pela cota publicada do fundo-alvo (master).
//  • Renda variável: preço de mercado.
// Carregado ao final de dominio.php (após fonte_por_tipo).
// ============================================================

const IPCA_AA_EST = 0.040;   // fallback do IPCA anual (quando não há data de referência)

// ---------------- Curva de juros e parâmetros de mercado (estrutura a termo) ----------------
// Substitui os "chutes" fixos e o random walk: preços de RF/derivativos derivam de uma
// curva ancorada na meta Selic vigente; RV usa um fator de mercado sistêmico + choque
// idiossincrático DETERMINÍSTICOS (reprodutíveis e correlacionados), não ruído branco.

/** Meta Selic (a.a., fração) vigente na data — trajetória em degraus (reuniões do Copom). */
function selic_meta_em(string $data): float {
    static $traj = [
        '2024-01-01' => 0.1175, '2024-06-19' => 0.1050, '2024-09-18' => 0.1075,
        '2025-01-29' => 0.1325, '2025-06-18' => 0.1500, '2026-01-28' => 0.1275,
        '2026-09-16' => 0.1150, '2027-01-27' => 0.1050,
    ];
    $m = 0.105;
    foreach ($traj as $d => $v) { if ($data >= $d) $m = $v; }
    return $m;
}

/** Fator diário do CDI na data (derivado da meta Selic vigente, base 252 d.u.). */
function cdi_fator_dia(string $data): float { return pow(1 + selic_meta_em($data), 1.0 / 252.0); }

/** IPCA (a.a., fração) vigente na data — série de referência (12m). */
function ipca_aa_em(string $data): float {
    static $serie = ['2024-01-01' => 0.045, '2025-01-01' => 0.048, '2026-01-01' => 0.041, '2027-01-01' => 0.038];
    $v = 0.040;
    foreach ($serie as $d => $x) { if ($data >= $d) $v = $x; }
    return $v;
}

/** Fator diário do IPCA (base 252). Usa a série por data; sem data, cai no fallback. */
function ipca_diario(?string $data = null): float {
    $aa = $data ? ipca_aa_em($data) : IPCA_AA_EST;
    return pow(1 + $aa, 1.0 / 252.0);
}

/** Curva pré a.a. (fração) por prazo em dias úteis: Selic + prêmio a termo que satura. */
function curva_pre_aa(string $data, int $du): float {
    $anos = max(0.0, $du / 252.0);
    return selic_meta_em($data) + 0.014 * (1 - exp(-$anos / 2.5));   // ~+1,4% no vértice longo
}

/** Curva de juro REAL a.a. (para DAP): pré da curva menos IPCA esperado (Fisher aprox.). */
function curva_real_aa(string $data, int $du): float {
    return max(0.005, curva_pre_aa($data, $du) - ipca_aa_em($data));
}

/** Fator de mercado do dia: choque SISTÊMICO compartilhado por toda a bolsa. Determinístico. */
function fator_mercado_dia(string $data): float {
    return ((crc32('MKT|' . $data) % 10000) / 10000.0 - 0.5) * 0.020;   // ±1,0% sistêmico
}

/** Choque IDIOSSINCRÁTICO de um ativo no dia (menor, específico). Determinístico. */
function choque_ativo(string $codigo, string $data): float {
    return ((crc32($codigo . '|' . $data) % 10000) / 10000.0 - 0.5) * 0.016;   // ±0,8%
}

/** Fator diário da variação cambial USD/BRL: tendência leve de alta + choque determinístico. */
function fx_fator_dia(string $data): float {
    return pow(1.02, 1.0 / 252.0) * (1 + ((crc32('FX|' . $data) % 10000) / 10000.0 - 0.5) * 0.018);   // ±0,9%/dia
}

/** Fator diário de uma commodity/ouro (choque determinístico, volatilidade um pouco maior). */
function commodity_fator_dia(string $codigo, string $data): float {
    return 1 + ((crc32($codigo . '|COM|' . $data) % 10000) / 10000.0 - 0.5) * 0.022;   // ±1,1%/dia
}

/** Interpreta a taxa textual de um papel de RF em um modo de marcação. */
function parse_taxa(?string $indexador, ?string $taxa): array {
    $s = strtolower(trim((string) $taxa));
    $s = str_replace([',', ' ', '%'], ['.', '', ''], $s);
    if (str_contains($s, 'cdi') && preg_match('/cdi\+(\d+(?:\.\d+)?)/', $s, $m)) return ['modo' => 'cdi_spread', 'valor' => (float) $m[1]];
    if (preg_match('/ipca\+(\d+(?:\.\d+)?)/', $s, $m)) return ['modo' => 'ipca_spread', 'valor' => (float) $m[1]];
    if (str_contains($s, 'cdi') && preg_match('/(\d+(?:\.\d+)?)(?:do)?cdi/', $s, $m)) return ['modo' => 'pct_cdi', 'valor' => (float) $m[1] / 100.0];
    if (preg_match('/(\d+(?:\.\d+)?)a\.?a/', $s, $m)) return ['modo' => 'pre', 'valor' => (float) $m[1]];
    $ix = strtolower((string) $indexador);
    if (str_contains($ix, 'ipca')) return ['modo' => 'ipca_spread', 'valor' => 5.0];
    if (str_contains($ix, 'pre')) return ['modo' => 'pre', 'valor' => 10.0];
    return ['modo' => 'pct_cdi', 'valor' => 1.0];   // Selic/CDI: 100% CDI
}

/** Aplica o fator diário de marcação ao PU conforme o modo do papel. */
function marcar_rf(float $pu, array $p, float $cdiDia, float $ipcaDia): float {
    switch ($p['modo']) {
        case 'cdi_spread':  $f = $cdiDia * pow(1 + $p['valor'] / 100.0, 1.0 / 252.0); break;   // CDI + spread a.a.
        case 'ipca_spread': $f = $ipcaDia * pow(1 + $p['valor'] / 100.0, 1.0 / 252.0); break;  // IPCA (VNA) + cupom real
        case 'pre':         $f = pow(1 + $p['valor'] / 100.0, 1.0 / 252.0); break;              // prefixado
        case 'pct_cdi':
        default:            $f = 1 + ($cdiDia - 1) * $p['valor']; break;                        // % do CDI
    }
    return round($pu * $f, 6);
}

/**
 * Novo preço de um ativo no avanço de dia → [preco, fonte].
 * RF pela curva do indexador; Cota de Fundo pela cota do master; RV a mercado.
 */
function preco_novo_do_ativo(PDO $pdo, array $a, float $cdiDia, float $ipcaDia, string $data): array {
    $tipo = $a['tipo'];

    if ($tipo === 'Cota de Fundo') {
        $alvo = 0;
        try {
            $c = $pdo->prepare("SELECT fundo_alvo_id FROM ativos_catalogo WHERE codigo = ? LIMIT 1");
            $c->execute([$a['codigo']]);
            $alvo = (int) ($c->fetchColumn() ?: 0);
        } catch (Throwable $e) {}
        if (!$alvo) {
            try { $m = $pdo->prepare("SELECT master_id FROM fundos WHERE id = ?"); $m->execute([$a['fundo_id']]); $alvo = (int) ($m->fetchColumn() ?: 0); } catch (Throwable $e) {}
        }
        if ($alvo) {
            $q = $pdo->prepare("SELECT valor_cota FROM cotas_historico WHERE fundo_id = ? ORDER BY data_ref DESC LIMIT 1");
            $q->execute([$alvo]);
            $vc = (float) ($q->fetchColumn() ?: 0);
            if ($vc > 0) return [round($vc, 6), 'Cota do master'];
        }
        return [round((float) $a['preco_mam'] * $cdiDia, 6), 'ANBIMA'];   // fallback
    }

    if (grupo_ativo($tipo) === 'CAMBIAL') {
        return [round((float) $a['preco_mam'] * fx_fator_dia($data), 6), 'B3 Câmbio'];
    }
    if ($tipo === 'BDR') {
        // BDR = ação estrangeira × câmbio: varia com o mercado externo E com o dólar
        $var = fator_mercado_dia($data) + choque_ativo($a['codigo'], $data);
        return [round((float) $a['preco_mam'] * (1 + $var) * fx_fator_dia($data), 6), 'B3'];
    }
    if (grupo_ativo($tipo) === 'EXTERIOR') {
        $var = fator_mercado_dia($data) * 0.6;   // mercado externo (proxy) + câmbio
        return [round((float) $a['preco_mam'] * (1 + $var) * fx_fator_dia($data), 6), 'Custódia exterior'];
    }
    if (grupo_ativo($tipo) === 'COMMODITY') {
        return [round((float) $a['preco_mam'] * commodity_fator_dia($a['codigo'], $data), 6), 'B3'];
    }
    if (grupo_ativo($tipo) === 'DERIV_MTM') {
        // opção/swap/termo/NDF marcados a mercado pelo fator do ativo-objeto (câmbio ou índice)
        $ix = '';
        try { $c = $pdo->prepare("SELECT indexador FROM ativos_catalogo WHERE codigo = ? LIMIT 1"); $c->execute([$a['codigo']]); $ix = strtoupper((string) $c->fetchColumn()); } catch (Throwable $e) {}
        $base = $ix === 'USD' ? (fx_fator_dia($data) - 1) : fator_mercado_dia($data);
        $sens = $tipo === 'Opção' ? 2.0 : 1.0;   // opção tem alavancagem/convexidade (simplificado)
        return [round((float) $a['preco_mam'] * (1 + $base * $sens), 6), 'B3'];
    }

    if (eh_renda_variavel($tipo)) {
        // fator de mercado (sistêmico, igual p/ todos os papéis no dia) + choque idiossincrático:
        // gera correlação e estrutura, não ruído branco. Determinístico → reprodutível.
        $beta = $tipo === 'ETF' ? 1.0 : 1.1;
        $var  = $beta * fator_mercado_dia($data) + choque_ativo($a['codigo'], $data);
        return [round((float) $a['preco_mam'] * (1 + $var), 6), 'B3'];
    }

    if (eh_renda_fixa($tipo)) {
        $cat = null;
        try { $c = $pdo->prepare("SELECT indexador, taxa FROM ativos_catalogo WHERE codigo = ? LIMIT 1"); $c->execute([$a['codigo']]); $cat = $c->fetch(); } catch (Throwable $e) {}
        $p = parse_taxa($cat['indexador'] ?? null, $cat['taxa'] ?? null);
        return [marcar_rf((float) $a['preco_mam'], $p, $cdiDia, $ipcaDia), fonte_por_tipo($tipo)];
    }

    return [round((float) $a['preco_mam'] * $cdiDia, 6), fonte_por_tipo($tipo)];
}
