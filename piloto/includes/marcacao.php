<?php
// ============================================================
// Marcação a mercado por CLASSE / INDEXADOR.
//  • Renda fixa: acrual diário pela curva do indexador (CDI %, CDI+spread, IPCA+, pré).
//    Fonte ANBIMA para públicos/debêntures; CDB e ilíquidos via COMITÊ da administradora.
//  • Cota de fundo (FIF/FIC): marcada pela cota publicada do fundo-alvo (master).
//  • Renda variável: preço de mercado.
// Carregado ao final de dominio.php (após fonte_por_tipo).
// ============================================================

const IPCA_AA_EST = 0.045;   // IPCA anual estimado para o acrual diário de papéis IPCA+
function ipca_diario(): float { return pow(1 + IPCA_AA_EST, 1.0 / 252.0); }

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
function preco_novo_do_ativo(PDO $pdo, array $a, float $cdiDia, float $ipcaDia): array {
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

    if (in_array($tipo, ['Ação', 'ETF'], true)) {
        return [round((float) $a['preco_mam'] * (1 + mt_rand(-150, 150) / 10000.0), 6), 'B3'];
    }

    if (in_array($tipo, ['Título Público', 'Debênture', 'CDB', 'CRI/CRA'], true)) {
        $cat = null;
        try { $c = $pdo->prepare("SELECT indexador, taxa FROM ativos_catalogo WHERE codigo = ? LIMIT 1"); $c->execute([$a['codigo']]); $cat = $c->fetch(); } catch (Throwable $e) {}
        $p = parse_taxa($cat['indexador'] ?? null, $cat['taxa'] ?? null);
        return [marcar_rf((float) $a['preco_mam'], $p, $cdiDia, $ipcaDia), fonte_por_tipo($tipo)];
    }

    return [round((float) $a['preco_mam'] * $cdiDia, 6), fonte_por_tipo($tipo)];
}
