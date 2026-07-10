<?php
// ============================================================
// DOMÍNIO — infraestrutura compartilhada de realismo e integridade:
//  • calendário de dias úteis (feriados B3)   • transações
//  • idempotência (nonce)                       • provisão de despesas
//  • tabelas auto-criáveis: catálogo de ativos, solicitações de
//    cadastro, tickets de suporte, posição do custodiante.
// Carregado ao final de helpers.php (portanto após calcular_cota/cota_em).
// ============================================================

// ---------------- DDL portável (MariaDB ↔ MySQL) ----------------
/**
 * Executa DDL de forma portável entre MariaDB e MySQL 5.7.
 * O MySQL 5.7 NÃO aceita "ALTER TABLE ... ADD COLUMN IF NOT EXISTS" (extensão MariaDB):
 * neste caso, consulta o information_schema e roda um ALTER simples só se a coluna faltar.
 * Qualquer outro SQL é executado direto. Best-effort: nunca lança para o chamador
 * (mantém a semântica dos try/catch que envolvem as migrações ensure_*).
 */
function ddl_portavel(PDO $pdo, string $sql): void {
    try {
        if (preg_match('/^\s*ALTER\s+TABLE\s+`?(\w+)`?\s+ADD\s+COLUMN\s+IF\s+NOT\s+EXISTS\s+`?(\w+)`?/i', $sql, $m)) {
            $st = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
            $st->execute([$m[1], $m[2]]);
            if ((int) $st->fetchColumn() > 0) return;                      // coluna já existe
            $sql = preg_replace('/\bIF\s+NOT\s+EXISTS\s+/i', '', $sql, 1);  // remove p/ MySQL 5.7
        }
        $pdo->exec($sql);
    } catch (Throwable $e) {
        // migração best-effort: jamais interrompe a operação
    }
}

// ---------------- Política de senha ----------------
/** Valida senha forte: mín. 8, ao menos 1 maiúscula, 1 número e 1 caractere especial. Retorna [ok, msg]. */
function senha_valida(string $s): array {
    if (mb_strlen($s) < 8)                     return [false, 'A senha precisa ter ao menos 8 caracteres.'];
    if (!preg_match('/[A-ZÀ-Þ]/u', $s))        return [false, 'A senha precisa ter ao menos uma letra maiúscula.'];
    if (!preg_match('/\d/', $s))               return [false, 'A senha precisa ter ao menos um número.'];
    if (!preg_match('/[^\p{L}\p{N}]/u', $s))   return [false, 'A senha precisa ter ao menos um caractere especial.'];
    return [true, ''];
}
// Espelho client-side de senha_valida (atributos do input de senha).
const SENHA_PATTERN = '(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}';
const SENHA_TITLE   = 'Mínimo 8 caracteres, com ao menos uma letra maiúscula, um número e um caractere especial.';

// ---------------- Registry de classes de ativo (fonte ÚNICA da verdade) ----------------
// Antes, listas de tipos viviam espalhadas (marcacao, helpers, boletas) e adicionar uma
// classe exigia caçar todas. Aqui há UM mapa tipo→grupo; o resto pergunta a estas funções.
const GRUPOS_ATIVO = [
    // Renda fixa (juros / índice de preços / crédito)
    'Título Público' => 'RF', 'LFT' => 'RF', 'CDB' => 'RF', 'RDB' => 'RF', 'Debênture' => 'RF',
    'CRI/CRA' => 'RF', 'FIDC' => 'RF', 'LCI' => 'RF', 'LCA' => 'RF', 'Letra Financeira' => 'RF',
    'LIG' => 'RF', 'DPGE' => 'RF', 'Nota Promissória' => 'RF', 'Compromissada' => 'RF',
    // Renda variável
    'Ação' => 'RV', 'ETF' => 'RV', 'BDR' => 'RV',
    // Cotas de fundos
    'Cota de Fundo' => 'COTA',
    // Câmbio · exterior · commodities
    'Cambial' => 'CAMBIAL',
    'Exterior' => 'EXTERIOR',
    'Ouro' => 'COMMODITY', 'Commodity' => 'COMMODITY',
    // Derivativos: futuros (engine de ajuste diário) e demais marcados a mercado na carteira
    'Derivativo' => 'DERIVATIVO',
    'Opção' => 'DERIV_MTM', 'Swap' => 'DERIV_MTM', 'Termo' => 'DERIV_MTM', 'NDF' => 'DERIV_MTM',
];
function grupo_ativo(string $tipo): string { return GRUPOS_ATIVO[$tipo] ?? 'OUTRO'; }
function eh_renda_fixa(string $tipo): bool { return grupo_ativo($tipo) === 'RF'; }
function eh_renda_variavel(string $tipo): bool { return grupo_ativo($tipo) === 'RV'; }
function eh_credito_privado(string $tipo): bool {
    return in_array($tipo, ['Debênture', 'CDB', 'RDB', 'CRI/CRA', 'FIDC', 'LCI', 'LCA',
        'Letra Financeira', 'LIG', 'DPGE', 'Nota Promissória'], true);
}
/** Lista de tipos de um grupo (derivada do registry). */
function classes_do_grupo(string $g): array { return array_keys(array_filter(GRUPOS_ATIVO, fn($x) => $x === $g)); }
function classes_credito_privado(): array {
    return array_values(array_filter(array_keys(GRUPOS_ATIVO), 'eh_credito_privado'));
}

// ---------------- Calendário de dias úteis ----------------
// Feriados nacionais + B3 calculados ALGORITMICAMENTE para qualquer ano — fixos + móveis
// derivados da Páscoa (Carnaval, Sexta-feira Santa, Corpus Christi). Não expira em 2027.
function feriados_do_ano(int $ano): array {
    // fixos: Confraternização, Tiradentes, Trabalho, Independência, N.Sra Aparecida,
    //        Finados, Proclamação, Consciência Negra (nacional desde 2024), Natal.
    $fixos = ["$ano-01-01", "$ano-04-21", "$ano-05-01", "$ano-09-07", "$ano-10-12",
              "$ano-11-02", "$ano-11-15", "$ano-11-20", "$ano-12-25"];
    // Domingo de Páscoa (algoritmo de Meeus/Gauss)
    $a = $ano % 19; $b = intdiv($ano, 100); $c = $ano % 100; $d = intdiv($b, 4); $e = $b % 4;
    $f = intdiv($b + 8, 25); $g = intdiv($b - $f + 1, 3); $h = (19 * $a + $b - $d - $g + 15) % 30;
    $i = intdiv($c, 4); $k = $c % 4; $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7; $m = intdiv($a + 11 * $h + 22 * $l, 451);
    $mes = intdiv($h + $l - 7 * $m + 114, 31); $dia = (($h + $l - 7 * $m + 114) % 31) + 1;
    $pascoa = new DateTime(sprintf('%04d-%02d-%02d', $ano, $mes, $dia));
    $desl = function (int $off) use ($pascoa) {
        $x = clone $pascoa; $x->modify(($off >= 0 ? '+' : '') . $off . ' day'); return $x->format('Y-m-d');
    };
    // Carnaval (segunda e terça), Sexta-feira Santa, Corpus Christi
    return array_merge($fixos, [$desl(-48), $desl(-47), $desl(-2), $desl(60)]);
}

function eh_feriado(string $data): bool {
    static $cache = [];
    $ano = (int) substr($data, 0, 4);
    if (!isset($cache[$ano])) $cache[$ano] = array_flip(feriados_do_ano($ano));
    return isset($cache[$ano][substr($data, 0, 10)]);
}

function eh_dia_util(string $data): bool {
    $n = (int)(new DateTime($data))->format('N');   // 6=sáb, 7=dom
    return $n < 6 && !eh_feriado($data);
}

/** Próximo dia útil após $data (pula fim de semana E feriados). */
function proximo_dia_util(string $data): string {
    $d = new DateTime($data);
    do { $d->modify('+1 day'); } while (!eh_dia_util($d->format('Y-m-d')));
    return $d->format('Y-m-d');
}

/** Soma N dias úteis a uma data (para prazos regulatórios). */
function soma_dias_uteis(string $data, int $n): string {
    $d = $data;
    for ($i = 0; $i < $n; $i++) $d = proximo_dia_util($d);
    return $d;
}

// ---------------- Transação atômica ----------------
/** Executa $fn dentro de uma transação (aninhável). Reverte tudo se lançar. */
function com_transacao(PDO $pdo, callable $fn) {
    $externa = $pdo->inTransaction();
    if (!$externa) $pdo->beginTransaction();
    try {
        $r = $fn();
        if (!$externa) $pdo->commit();
        return $r;
    } catch (Throwable $e) {
        if (!$externa && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

// ---------------- Idempotência (anti-duplo-clique) ----------------
/** Emite um nonce de uso único e o registra na sessão. */
function nonce_campo(): string {
    $n = bin2hex(random_bytes(16));
    $_SESSION['nonces'][$n] = time();
    // limpa nonces antigos (> 2h)
    foreach (($_SESSION['nonces'] ?? []) as $k => $t) if (time() - $t > 7200) unset($_SESSION['nonces'][$k]);
    return '<input type="hidden" name="nonce" value="' . $n . '">';
}
/** Consome o nonce do POST; retorna false se ausente/já usado (duplo submit). */
function nonce_valido(): bool {
    $n = $_POST['nonce'] ?? '';
    if (is_string($n) && $n !== '' && isset($_SESSION['nonces'][$n])) { unset($_SESSION['nonces'][$n]); return true; }
    return false;
}

// ---------------- Provisão diária de despesas (accrual) ----------------
/**
 * Provisiona pro-rata dia útil a taxa de adm + gestão + custódia do fundo,
 * acumulando em fundos.provisao_despesas (reduz o PL/cota). Idempotente por dia
 * via log_processamento (etapa 'Provisão').
 */
function provisionar_despesas_dia(PDO $pdo, array $fundo, string $data): float {
    $fid = (int)$fundo['id'];   // ensure_provisao deve ser chamado FORA de transação pelo caller (DDL = commit implícito)
    // já provisionou este dia?
    $st = $pdo->prepare("SELECT COUNT(*) FROM log_processamento WHERE fundo_id=? AND data_ref=? AND etapa='Provisão'");
    $st->execute([$fid, $data]);
    if ((int)$st->fetchColumn() > 0) return 0.0;

    $pl = (float)$fundo['pl_atual'];
    $taxaCustodia = 0.0002;   // 0,02% a.a. (estimada; a de adm/gestão vêm do fundo)
    $taxaAno = (float)$fundo['taxa_adm'] + (float)$fundo['taxa_gestao'] + $taxaCustodia;
    $despesaDia = round($pl * $taxaAno / 252.0, 2);
    if ($despesaDia <= 0) return 0.0;

    $pdo->prepare("UPDATE fundos SET provisao_despesas = COALESCE(provisao_despesas,0) + ? WHERE id=?")
        ->execute([$despesaDia, $fid]);
    $pdo->prepare("INSERT INTO log_processamento (fundo_id, data_ref, etapa, nivel, mensagem) VALUES (?,?,?,?,?)")
        ->execute([$fid, $data, 'Provisão', 'INFO',
                   'Provisão de despesas do dia: ' . number_format($despesaDia, 2, ',', '.') . ' (adm+gestão+custódia pro-rata)']);
    return $despesaDia;
}

// ---------------- Tabelas auto-criáveis ----------------

function ensure_provisao(PDO $pdo): void {
    // MariaDB (XAMPP) suporta ADD COLUMN IF NOT EXISTS
    try { ddl_portavel($pdo, "ALTER TABLE fundos ADD COLUMN IF NOT EXISTS provisao_despesas DECIMAL(18,2) DEFAULT 0"); }
    catch (Throwable $e) { /* coluna já existe ou engine sem suporte — ignora */ }
}

/** Catálogo de ativos + fila de solicitações de cadastro. Semeia um catálogo inicial. */
function ensure_catalogo(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS ativos_catalogo (
        id INT AUTO_INCREMENT PRIMARY KEY,
        codigo VARCHAR(40) NOT NULL UNIQUE,
        nome VARCHAR(150) NOT NULL,
        tipo VARCHAR(40) NOT NULL,
        emissor VARCHAR(150),
        cnpj_emissor VARCHAR(20),
        indexador VARCHAR(30),
        taxa VARCHAR(40),
        vencimento DATE NULL,
        fonte_preco VARCHAR(20) DEFAULT 'ANBIMA',
        status VARCHAR(20) DEFAULT 'Ativo',
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_cat_tipo (tipo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS solicitacoes_cadastro_ativo (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fundo_id INT NULL,
        solicitante VARCHAR(120),
        codigo VARCHAR(40) NOT NULL,
        nome VARCHAR(150),
        tipo VARCHAR(40) NOT NULL,
        emissor VARCHAR(150),
        indexador VARCHAR(30),
        taxa VARCHAR(40),
        vencimento DATE NULL,
        detalhe VARCHAR(400),
        status VARCHAR(20) DEFAULT 'Solicitado',
        motivo VARCHAR(300),
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
        decidido_por VARCHAR(120), decidido_em DATETIME NULL,
        INDEX idx_sol_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // Anexo (.pdf) da solicitação e marca de cadastro em lote — DDL lazy, fora de transação.
    ddl_portavel($pdo, "ALTER TABLE solicitacoes_cadastro_ativo ADD COLUMN IF NOT EXISTS anexo LONGBLOB NULL");
    ddl_portavel($pdo, "ALTER TABLE solicitacoes_cadastro_ativo ADD COLUMN IF NOT EXISTS anexo_nome VARCHAR(200) NULL");
    ddl_portavel($pdo, "ALTER TABLE solicitacoes_cadastro_ativo ADD COLUMN IF NOT EXISTS lote TINYINT DEFAULT 0");

    if ((int)$pdo->query("SELECT COUNT(*) FROM ativos_catalogo")->fetchColumn() === 0) {
        $ativos = [
            // código, nome, tipo, emissor, indexador, taxa, vencimento, fonte
            ['LFT 2028','Tesouro Selic 2028','Título Público','Tesouro Nacional','Selic','Selic','2028-03-01','ANBIMA'],
            ['LTN 2027','Tesouro Prefixado 2027','Título Público','Tesouro Nacional','Prefixado','10,8% a.a.','2027-01-01','ANBIMA'],
            ['NTN-B 2035','Tesouro IPCA+ 2035','Título Público','Tesouro Nacional','IPCA','IPCA+6,1%','2035-05-15','ANBIMA'],
            ['NTN-B 2045','Tesouro IPCA+ 2045','Título Público','Tesouro Nacional','IPCA','IPCA+6,2%','2045-05-15','ANBIMA'],
            ['CDB BTG 26','CDB BTG Pactual 2026','CDB','Banco BTG Pactual','CDI','108% CDI','2026-11-20','Comitê'],
            ['CDB ABC 27','CDB Banco ABC 2027','CDB','Banco ABC Brasil','CDI','110% CDI','2027-06-15','Comitê'],
            ['CDB DAYCOVAL 28','CDB Daycoval 2028','CDB','Banco Daycoval','CDI','112% CDI','2028-03-10','Comitê'],
            ['CDB SIM 26','CDB Banco Parceiro 2026','CDB','Banco Parceiro','CDI','109% CDI','2026-12-31','Comitê'],
            ['DEB VALE29','Debênture Vale 2029','Debênture','Vale S.A.','IPCA','IPCA+5,5%','2029-08-15','ANBIMA'],
            ['DEB ENGIE30','Debênture Engie 2030','Debênture','Engie Brasil','CDI','CDI+1,7%','2030-04-01','ANBIMA'],
            ['DEB SABESP31','Debênture Sabesp 2031','Debênture','Sabesp','IPCA','IPCA+5,9%','2031-09-01','ANBIMA'],
            ['CRI URBE28','CRI Urbe Capital 2028','CRI/CRA','Urbe Securitizadora','IPCA','IPCA+7,0%','2028-12-15','ANBIMA'],
            ['CRA AGRO25','CRA Agronegócio 2025','CRI/CRA','Eco Securitizadora','CDI','CDI+2,1%','2025-10-20','ANBIMA'],
            ['PETR4','Petrobras PN','Ação','Petróleo Brasileiro S.A.','—','—',null,'B3'],
            ['VALE3','Vale ON','Ação','Vale S.A.','—','—',null,'B3'],
            ['ITUB4','Itaú Unibanco PN','Ação','Itaú Unibanco','—','—',null,'B3'],
            ['BBAS3','Banco do Brasil ON','Ação','Banco do Brasil','—','—',null,'B3'],
            ['BBDC4','Bradesco PN','Ação','Banco Bradesco','—','—',null,'B3'],
            ['NORD3','Nordeste Participações ON','Ação','Nordeste Part.','—','—',null,'B3'],
            ['WEGE3','WEG ON','Ação','WEG S.A.','—','—',null,'B3'],
            ['BOVA11','iShares Ibovespa ETF','ETF','BlackRock','—','—',null,'B3'],
            ['IMAB11','iShares IMA-B ETF','ETF','BlackRock','—','—',null,'B3'],
            ['FIC RF SIM','Cota FIC RF Master (sim)','Cota de Fundo','Administradora','CDI','—',null,'ANBIMA'],
            ['MASTER MM SIM','Cota Fundo Master MM (sim)','Cota de Fundo','Administradora','—','—',null,'ANBIMA'],
        ];
        $ins = $pdo->prepare("INSERT INTO ativos_catalogo (codigo,nome,tipo,emissor,indexador,taxa,vencimento,fonte_preco)
                              VALUES (?,?,?,?,?,?,?,?)");
        foreach ($ativos as $a) $ins->execute($a);
    }

    // Futuros (derivativos) como instrumentos do catálogo — boletáveis como qualquer
    // outro ativo. INSERT IGNORE é idempotente (codigo é UNIQUE). Vários vencimentos.
    $futuros = [
        // codigo, nome, tipo, emissor, indexador, taxa (referência), vencimento
        ['DI1F26', 'Futuro de DI · Jan/2026',            'Derivativo', 'B3', 'DI',   '≈ 11,00% a.a.',        '2026-01-02'],
        ['DI1F27', 'Futuro de DI · Jan/2027',            'Derivativo', 'B3', 'DI',   '≈ 11,20% a.a.',        '2027-01-04'],
        ['DI1F28', 'Futuro de DI · Jan/2028',            'Derivativo', 'B3', 'DI',   '≈ 11,40% a.a.',        '2028-01-03'],
        ['DI1F29', 'Futuro de DI · Jan/2029',            'Derivativo', 'B3', 'DI',   '≈ 11,60% a.a.',        '2029-01-02'],
        ['DI1F30', 'Futuro de DI · Jan/2030',            'Derivativo', 'B3', 'DI',   '≈ 11,75% a.a.',        '2030-01-02'],
        ['DI1F31', 'Futuro de DI · Jan/2031',            'Derivativo', 'B3', 'DI',   '≈ 11,90% a.a.',        '2031-01-02'],
        ['DAPK27', 'Futuro de cupom IPCA · Mai/2027',    'Derivativo', 'B3', 'IPCA', '≈ 6,20% a.a. (real)',  '2027-05-17'],
        ['DAPK29', 'Futuro de cupom IPCA · Mai/2029',    'Derivativo', 'B3', 'IPCA', '≈ 6,40% a.a. (real)',  '2029-05-15'],
        ['DAPK31', 'Futuro de cupom IPCA · Mai/2031',    'Derivativo', 'B3', 'IPCA', '≈ 6,55% a.a. (real)',  '2031-05-15'],
        ['DAPK33', 'Futuro de cupom IPCA · Mai/2033',    'Derivativo', 'B3', 'IPCA', '≈ 6,65% a.a. (real)',  '2033-05-15'],
    ];
    $insF = $pdo->prepare("INSERT IGNORE INTO ativos_catalogo (codigo,nome,tipo,emissor,indexador,taxa,vencimento,fonte_preco)
                           VALUES (?,?,?,?,?,?,?, 'B3')");
    foreach ($futuros as $f) $insF->execute($f);

    // Demais classes de ativo permitidas pela Res. CVM 175 (RF de crédito, cambial, exterior,
    // commodities e derivativos de câmbio/índice/opção/swap/termo). Idempotente (INSERT IGNORE).
    $novos = [
        // --- Renda fixa privada / crédito (marcadas pela curva do indexador; custódia B3 Balcão) ---
        ['FIDC MERC SR',   'FIDC Mercantil Recebíveis — Sênior',   'FIDC',             'Gestora de Crédito', 'CDI',  'CDI+2,5%',   '2029-06-30', 'ANBIMA'],
        ['FIDC MERC MEZ',  'FIDC Mercantil Recebíveis — Mezanino', 'FIDC',             'Gestora de Crédito', 'CDI',  'CDI+5,0%',   '2029-06-30', 'Comitê'],
        ['LCI ITAU 27',    'LCI Itaú 2027',                        'LCI',              'Itaú Unibanco',      'CDI',  '95% CDI',    '2027-08-15', 'Comitê'],
        ['LCA BB 27',      'LCA Banco do Brasil 2027',             'LCA',              'Banco do Brasil',    'CDI',  '96% CDI',    '2027-05-10', 'Comitê'],
        ['LF SANT 29',     'Letra Financeira Santander 2029',      'Letra Financeira', 'Santander',          'CDI',  'CDI+1,2%',   '2029-03-01', 'Comitê'],
        ['LIG BRAD 30',    'LIG Bradesco 2030',                    'LIG',              'Banco Bradesco',     'IPCA', 'IPCA+5,0%',  '2030-09-01', 'ANBIMA'],
        ['DPGE ABC 27',    'DPGE Banco ABC 2027',                  'DPGE',             'Banco ABC Brasil',   'CDI',  '118% CDI',   '2027-11-30', 'Comitê'],
        ['NP LOCALIZA 26', 'Nota Promissória Localiza 2026',       'Nota Promissória', 'Localiza',           'CDI',  'CDI+2,8%',   '2026-12-15', 'ANBIMA'],
        ['COMPROM SELIC',  'Operação Compromissada (lastro LFT)',  'Compromissada',    'Banco Parceiro',     'Selic','100% Selic', '2026-07-10', 'Comitê'],
        // --- Câmbio (marcadas pela variação do dólar; custódia B3 Câmbio) ---
        ['USD POS',        'Posição cambial em dólar (spot)',      'Cambial',          '—',                  'USD',  '—',          null,         'B3'],
        ['CUPOM CAMB 27',  'Título com cupom cambial 2027',        'Cambial',          'Tesouro/Privado',    'USD',  'Cupom+2,0%', '2027-01-04', 'ANBIMA'],
        // --- Renda variável estrangeira (BDR) e ativos no exterior ---
        ['AAPL34',         'Apple Inc. — BDR',                     'BDR',              'Apple (via B3)',     'USD',  '—',          null,         'B3'],
        ['MSFT34',         'Microsoft — BDR',                      'BDR',              'Microsoft (via B3)', 'USD',  '—',          null,         'B3'],
        ['UST 2031',       'US Treasury Note 2031',                'Exterior',         'US Treasury',        'USD',  'UST ~4,3%',  '2031-05-15', 'Custódia'],
        ['SPY EXT',        'ETF S&P 500 (exterior)',               'Exterior',         'State Street',       'USD',  '—',          null,         'Custódia'],
        // --- Ouro / commodities ---
        ['OZ1D',           'Ouro — contrato à vista (grama)',      'Ouro',             'B3',                 '—',    '—',          null,         'B3'],
        // --- Derivativos: futuros de dólar e de índice (engine de ajuste diário) ---
        ['DOLF27',         'Futuro de Dólar · Jan/2027',           'Derivativo',       'B3',                 'USD',  'ref. câmbio','2027-01-04', 'B3'],
        ['DOLF28',         'Futuro de Dólar · Jan/2028',           'Derivativo',       'B3',                 'USD',  'ref. câmbio','2028-01-03', 'B3'],
        ['INDV27',         'Futuro de Índice Bovespa · Out/2027',  'Derivativo',       'B3',                 'IBOV', 'ref. índice','2027-10-13', 'B3'],
        ['INDZ27',         'Futuro de Índice Bovespa · Dez/2027',  'Derivativo',       'B3',                 'IBOV', 'ref. índice','2027-12-15', 'B3'],
        // --- Derivativos marcados a mercado na carteira (opção, swap, termo, NDF) ---
        ['OPC PETR C',     'Opção de compra sobre PETR4',          'Opção',            'B3',                 'IBOV', 'call PETR4', '2026-12-18', 'B3'],
        ['SWP DI-DOL',     'Swap DI × Dólar',                      'Swap',             'Balcão',             'USD',  'troca indexador', null,    'Comitê'],
        ['NDF USD 27',     'NDF de Dólar (termo sem entrega)',     'NDF',              'Balcão',             'USD',  'termo cambial','2027-03-31','Comitê'],
        ['TRM PETR',       'Termo de ações PETR4',                 'Termo',            'B3',                 'IBOV', 'termo ações', '2026-09-30', 'B3'],
    ];
    $insN = $pdo->prepare("INSERT IGNORE INTO ativos_catalogo (codigo,nome,tipo,emissor,indexador,taxa,vencimento,fonte_preco)
                           VALUES (?,?,?,?,?,?,?,?)");
    foreach ($novos as $a) $insN->execute($a);
}

/** Sistema de tickets de suporte. */
function ensure_tickets(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS tickets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fundo_id INT NULL,
        aberto_por VARCHAR(120),
        perfil_abertura VARCHAR(20),
        tema VARCHAR(60) NOT NULL,
        assunto VARCHAR(200) NOT NULL,
        prioridade VARCHAR(10) DEFAULT 'Média',
        status VARCHAR(20) DEFAULT 'Aberto',
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
        atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_tk_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS ticket_mensagens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ticket_id INT NOT NULL,
        autor VARCHAR(120),
        perfil VARCHAR(20),
        mensagem TEXT NOT NULL,
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_tkm (ticket_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // Canal do chamado: 'gestor_admin' (gestor↔administradora) ou 'cotista_gestor' (cotista↔gestor).
    try { ddl_portavel($pdo, "ALTER TABLE tickets ADD COLUMN IF NOT EXISTS canal VARCHAR(20) DEFAULT 'gestor_admin'"); } catch (Throwable $e) {}
    try { ddl_portavel($pdo, "ALTER TABLE tickets ADD COLUMN IF NOT EXISTS token_cotista VARCHAR(40) NULL"); } catch (Throwable $e) {}
}

/** Temas de chamado típicos da operação de fundos. */
const TEMAS_TICKET = [
    'Cadastro de ativo', 'Boletagem / liquidação', 'Cota / precificação',
    'Conciliação / divergência', 'Aplicação / resgate de cotista', 'Tributação (come-cotas/IR)',
    'Enquadramento', 'Documento / regulatório', 'Assembleia', 'Acesso / senha',
    'Abertura / alteração de fundo', 'Outros',
];

/** Posição custodiada — fonte INDEPENDENTE da carteira da administradora (para conciliação real). */
function ensure_posicao_custodiante(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS posicao_custodiante (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fundo_id INT NOT NULL,
        data_ref DATE NOT NULL,
        codigo VARCHAR(40) NOT NULL,
        tipo VARCHAR(40),
        quantidade DECIMAL(18,6) NOT NULL,
        central VARCHAR(20),
        INDEX idx_pc (fundo_id, data_ref)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/** Central de guarda por tipo de ativo. */
function central_do_ativo(string $tipo): string {
    // Títulos públicos federais e compromissadas lastreadas em público → SELIC.
    if (in_array($tipo, ['Título Público', 'LFT', 'Compromissada'], true)) return 'SELIC';
    // Renda fixa privada / crédito (inclui FIDC, letras, NP) → B3 Balcão (ex-CETIP, registro).
    if (in_array($tipo, ['Debênture', 'CDB', 'RDB', 'CRI/CRA', 'FIDC', 'LCI', 'LCA',
        'Letra Financeira', 'LIG', 'DPGE', 'Nota Promissória'], true)) return 'B3 Balcão';
    // Renda variável e BDR → B3 Depositária (Central Depositária).
    if (in_array($tipo, ['Ação', 'ETF', 'BDR'], true)) return 'B3 Depositária';
    // Derivativos (futuros, opções, swap, termo, NDF) → registro/compensação na clearing da B3.
    if (in_array($tipo, ['Derivativo', 'Opção', 'Swap', 'Termo', 'NDF'], true)) return 'B3 Clearing';
    if ($tipo === 'Cambial')  return 'B3 Câmbio';
    if ($tipo === 'Exterior') return 'Custódia no exterior';
    if (grupo_ativo($tipo) === 'COMMODITY') return 'B3 (ouro/commodities)';
    if ($tipo === 'Cota de Fundo') return 'Escriturador do fundo';
    return 'B3 Depositária';
}

// ---------------- Versionamento por data (caixa e cotas) ----------------
/**
 * Saldo de caixa do fundo NA DATA (não o escalar de hoje): caixa atual menos os
 * movimentos posteriores à data. Corrige o cálculo retroativo da cota.
 */
function caixa_na_data(PDO $pdo, int $fid, string $data): float {
    $st = $pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM movimentacoes WHERE fundo_id = ? AND data_ref > ?");
    $st->execute([$fid, $data]);
    $posteriores = (float)$st->fetchColumn();
    $c = $pdo->prepare("SELECT caixa_atual FROM fundos WHERE id = ?");
    $c->execute([$fid]);
    return (float)$c->fetchColumn() - $posteriores;
}

/**
 * Total de cotas emitidas NA DATA. No dia corrente é o passivo vivo (SUM cotistas);
 * em data passada, deriva do snapshot publicado (pl / valor_cota daquela data),
 * refletindo as cotas que existiam antes de come-cotas/resgates posteriores.
 */
function total_cotas_na_data(PDO $pdo, int $fid, string $data): float {
    $ultima = ultima_data_cota($pdo, $fid);
    if (!$ultima || $data >= $ultima) return total_cotas($pdo, $fid);
    $st = $pdo->prepare("SELECT valor_cota, pl FROM cotas_historico WHERE fundo_id = ? AND data_ref = ? ORDER BY id DESC LIMIT 1");
    $st->execute([$fid, $data]);
    $h = $st->fetch();
    if ($h && (float)$h['valor_cota'] > 0) return (float)$h['pl'] / (float)$h['valor_cota'];
    return total_cotas($pdo, $fid);
}

// ---------------- Passivos genéricos (valores a pagar / tributos a recolher) ----------------
// Livro ÚNICO de passivos do fundo. A cota subtrai a SOMA dos passivos em aberto
// (passivos_na_data) — assim, adicionar um NOVO tipo de passivo nunca muda a fórmula da cota.
// Semântica ponto-no-tempo: um passivo pesa no PL da data D se foi incorrido (data_ref <= D) e
// ainda não estava liquidado em D (liquidado_em nulo ou > D) — casa com o desenho de caixa_na_data,
// então a cota fica ESTÁVEL no dia (redução de cotas ↔ passivo criado) e invariante à liquidação.
function ensure_passivos(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS passivos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fundo_id INT NOT NULL,
        tipo VARCHAR(40) NOT NULL,
        descricao VARCHAR(255) NULL,
        valor DECIMAL(18,2) NOT NULL,
        data_ref DATE NOT NULL,
        data_liquidacao DATE NOT NULL,
        status VARCHAR(12) DEFAULT 'Aberto',
        liquidado_em DATE NULL,
        ref_tipo VARCHAR(30) NULL, ref_id INT NULL,
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_pass (fundo_id, status),
        INDEX idx_pass_liq (fundo_id, data_liquidacao)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/** Soma dos passivos EM ABERTO do fundo na data (ponto-no-tempo). Seguro se a tabela não existir. */
function passivos_na_data(PDO $pdo, int $fid, string $data): float {
    try {
        $st = $pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM passivos
            WHERE fundo_id = ? AND data_ref <= ? AND (liquidado_em IS NULL OR liquidado_em > ?)");
        $st->execute([$fid, $data, $data]);
        return (float) $st->fetchColumn();
    } catch (Throwable $e) { return 0.0; }   // tabela ainda não criada → sem passivos
}

/** Passivos em aberto agrupados (Tributos a recolher / Valores a cotistas / Outros) na data. */
function passivos_por_grupo(PDO $pdo, int $fid, string $data): array {
    $g = ['tributos' => 0.0, 'cotistas' => 0.0, 'outros' => 0.0];
    try {
        $st = $pdo->prepare("SELECT tipo, valor FROM passivos
            WHERE fundo_id = ? AND data_ref <= ? AND (liquidado_em IS NULL OR liquidado_em > ?)");
        $st->execute([$fid, $data, $data]);
        foreach ($st->fetchAll() as $p) {
            $v = (float) $p['valor'];
            if (strpos($p['tipo'], 'Tributos') === 0)             $g['tributos'] += $v;
            elseif (strpos($p['tipo'], 'Valores a pagar') === 0)  $g['cotistas'] += $v;
            else                                                  $g['outros']   += $v;
        }
    } catch (Throwable $e) {}
    return $g;
}

/** Registra um passivo a pagar/recolher, com a data futura de liquidação.
 *  A tabela é garantida por ensure_dominio (fora de transação) — NÃO fazer DDL aqui:
 *  registrar_passivo roda DENTRO de com_transacao e um CREATE/ALTER daria commit implícito. */
function registrar_passivo(PDO $pdo, int $fid, string $tipo, float $valor, string $data, string $dataLiq, string $desc = ''): int {
    $pdo->prepare("INSERT INTO passivos (fundo_id, tipo, descricao, valor, data_ref, data_liquidacao) VALUES (?,?,?,?,?,?)")
        ->execute([$fid, $tipo, $desc, round($valor, 2), $data, $dataLiq]);
    return (int) $pdo->lastInsertId();
}

/** Liquida os passivos vencidos (data_liquidacao <= data): baixa o caixa e marca Liquidado. */
function liquidar_passivos_vencidos(PDO $pdo, int $fid, string $data): float {
    try {
        $st = $pdo->prepare("SELECT * FROM passivos WHERE fundo_id = ? AND status = 'Aberto' AND data_liquidacao <= ?");
        $st->execute([$fid, $data]);
    } catch (Throwable $e) { return 0.0; }
    $tot = 0.0;
    foreach ($st->fetchAll() as $p) {
        $v = (float) $p['valor'];
        $pdo->prepare("INSERT INTO movimentacoes (fundo_id, data_ref, tipo, descricao, valor) VALUES (?,?,?,?,?)")
            ->execute([$fid, $data, 'Liquidação passivo', $p['descricao'] ?: $p['tipo'], -$v]);
        $pdo->prepare("UPDATE fundos SET caixa_atual = caixa_atual - ? WHERE id = ?")->execute([$v, $fid]);
        $pdo->prepare("UPDATE passivos SET status = 'Liquidado', liquidado_em = ? WHERE id = ?")->execute([$data, $p['id']]);
        $tot += $v;
    }
    return $tot;
}

/** Aplica N dias úteis a partir de uma data (prazos de liquidação D+n). */
function mais_dias_uteis(string $data, int $n): string {
    for ($i = 0; $i < max(0, $n); $i++) $data = proximo_dia_util($data);
    return $data;
}

/**
 * Credita/baixa um evento corporativo CORRETAMENTE por tipo (em transação):
 *  • Amortização → devolve principal ao caixa E baixa o PU do ativo (não é receita);
 *  • Bonificação/Desdobramento → ajusta QUANTIDADE (sem caixa);
 *  • Dividendo/JCP/Cupom → rendimento creditado no caixa.
 */
function creditar_evento_corporativo(PDO $pdo, array $ev): void {
    com_transacao($pdo, function () use ($pdo, $ev) {
        $fid = (int)$ev['fundo_id'];
        $tipo = $ev['tipo'];
        $valor = (float)($ev['valor_total'] ?? 0);
        $pdo->prepare("UPDATE eventos_corporativos SET status='Liquidado', processado_em=NOW() WHERE id=?")->execute([$ev['id']]);
        $dataSnap = ultima_data_carteira($pdo, $fid);

        if ($tipo === 'Amortização') {
            $pdo->prepare("INSERT INTO movimentacoes (fundo_id,data_ref,tipo,descricao,valor) VALUES (?,?,?,?,?)")
                ->execute([$fid, date('Y-m-d'), 'Amortização', "Amortização de {$ev['ativo_codigo']} — devolução de principal", $valor]);
            $pdo->prepare("UPDATE fundos SET caixa_atual = caixa_atual + ? WHERE id=?")->execute([$valor, $fid]);
            if ($dataSnap) {   // baixa o principal reduzindo o PU do ativo (valor de mercado cai junto)
                $st = $pdo->prepare("SELECT * FROM ativos_carteira WHERE fundo_id=? AND codigo=? AND data_ref=?");
                $st->execute([$fid, $ev['ativo_codigo'], $dataSnap]);
                if (($pos = $st->fetch()) && (float)$pos['quantidade'] > 0) {
                    $reducaoPU = $valor / (float)$pos['quantidade'];
                    $novoMam = max(0.0, (float)$pos['preco_mam'] - $reducaoPU);
                    $pdo->prepare("UPDATE ativos_carteira SET preco_mam=?, preco_referencia=? WHERE id=?")->execute([$novoMam, $novoMam, $pos['id']]);
                }
            }
        } elseif (in_array($tipo, ['Bonificação', 'Desdobramento'], true)) {
            $fator = (float)($ev['valor_por_unidade'] ?? 0);   // fração adicional (ex.: 0,10 = +10%)
            if ($dataSnap && $fator > 0) {
                $st = $pdo->prepare("SELECT * FROM ativos_carteira WHERE fundo_id=? AND codigo=? AND data_ref=?");
                $st->execute([$fid, $ev['ativo_codigo'], $dataSnap]);
                if ($pos = $st->fetch()) {
                    $nova = (float)$pos['quantidade'] * (1 + $fator);
                    $novoPm = (float)$pos['preco_medio'] / (1 + $fator);
                    $novoMam = (float)$pos['preco_mam'] / (1 + $fator);
                    $pdo->prepare("UPDATE ativos_carteira SET quantidade=?, preco_medio=?, preco_mam=?, preco_referencia=? WHERE id=?")
                        ->execute([$nova, $novoPm, $novoMam, $novoMam, $pos['id']]);
                }
            }
        } else {   // Dividendo / JCP / Cupom → rendimento
            $pdo->prepare("INSERT INTO movimentacoes (fundo_id,data_ref,tipo,descricao,valor) VALUES (?,?,?,?,?)")
                ->execute([$fid, date('Y-m-d'), 'Provento', "{$tipo} de {$ev['ativo_codigo']} creditado", $valor]);
            $pdo->prepare("UPDATE fundos SET caixa_atual = caixa_atual + ? WHERE id=?")->execute([$valor, $fid]);
        }
    });
}

/**
 * Enquadramento PRÉ-TRADE (dever do gestor — Res. CVM 175, art. 89): projeta o efeito
 * da boleta na carteira e verifica os limites do fundo ANTES do envio. Retorna [ok, violacoes].
 */
function checar_pre_trade(PDO $pdo, array $fundo, string $tipo, string $operacao, float $valor): array {
    $fid = (int)$fundo['id'];
    $pl = (float)$fundo['pl_atual'];
    if ($pl <= 0) return ['ok' => true, 'violacoes' => []];
    $ativos = carteira($pdo, $fid);
    $sinal = $operacao === 'Compra' ? 1 : -1;
    $soma = function (array $classes) use ($ativos): float {
        $s = 0.0; foreach ($ativos as $a) if (in_array($a['tipo'], $classes, true)) $s += $a['valor_mercado']; return $s;
    };
    $st = $pdo->prepare('SELECT * FROM enquadramento_regras WHERE fundo_id = ?');
    $st->execute([$fid]);
    $violacoes = [];
    foreach ($st->fetchAll() as $r) {
        $lim = (float)$r['limite'];
        switch ($r['tipo_regra']) {
            case 'max_acoes':
                $proj = ($soma(['Ação']) + ($tipo === 'Ação' ? $sinal * $valor : 0)) / $pl * 100;
                if ($proj > $lim + 1e-6) $violacoes[] = "{$r['descricao']}: iria a " . number_format($proj, 1, ',', '.') . "% (máx {$lim}%)";
                break;
            case 'max_credito_privado':
                $proj = ($soma(classes_credito_privado()) + (eh_credito_privado($tipo) ? $sinal * $valor : 0)) / $pl * 100;
                if ($proj > $lim + 1e-6) $violacoes[] = "{$r['descricao']}: iria a " . number_format($proj, 1, ',', '.') . "% (máx {$lim}%)";
                break;
            case 'min_rf':
                $proj = ($soma(classes_do_grupo('RF')) + (eh_renda_fixa($tipo) ? $sinal * $valor : 0)) / $pl * 100;
                if ($proj < $lim - 1e-6) $violacoes[] = "{$r['descricao']}: cairia a " . number_format($proj, 1, ',', '.') . "% (mín {$lim}%)";
                break;
            case 'max_ativo_unico':
                if ($operacao === 'Compra' && $valor / $pl * 100 > $lim + 1e-6)
                    $violacoes[] = "{$r['descricao']}: a operação sozinha representa " . number_format($valor / $pl * 100, 1, ',', '.') . "% (máx {$lim}%)";
                break;
        }
    }
    return ['ok' => empty($violacoes), 'violacoes' => $violacoes];
}

// ---------------- MaM: feed de preços independente ----------------
/** Fonte de preço primária por tipo de ativo (cascata de precificação). */
function fonte_por_tipo(string $tipo): string {
    if (in_array($tipo, ['Título Público', 'Debênture', 'CRI/CRA'], true)) return 'ANBIMA';
    if (in_array($tipo, ['Ação', 'ETF', 'Cota de Fundo'], true)) return 'B3';
    return 'Comitê';   // CDB e ilíquidos: comitê de precificação
}

/** Feed de preços de mercado — fonte INDEPENDENTE do preço marcado pela controladoria. */
function ensure_precos(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS precos_mercado (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        codigo VARCHAR(40) NOT NULL,
        data_ref DATE NOT NULL,
        preco DECIMAL(18,6) NOT NULL,
        fonte VARCHAR(20) DEFAULT 'ANBIMA',
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_preco (codigo, data_ref),
        INDEX idx_pm (data_ref)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

// ---------------- Passivo: KYC / suitability / PLD do cotista ----------------
function ensure_kyc_cotista(PDO $pdo): void {
    foreach ([
        "ALTER TABLE cotistas ADD COLUMN IF NOT EXISTS suitability VARCHAR(20) DEFAULT NULL",     // Conservador/Moderado/Arrojado
        "ALTER TABLE cotistas ADD COLUMN IF NOT EXISTS kyc_status VARCHAR(20) DEFAULT 'Pendente'", // Pendente/Aprovado/Reprovado
        "ALTER TABLE cotistas ADD COLUMN IF NOT EXISTS pld_status VARCHAR(20) DEFAULT 'Pendente'", // Pendente/OK/Alerta
        "ALTER TABLE cotistas ADD COLUMN IF NOT EXISTS fatca_crs VARCHAR(30) DEFAULT NULL",
        "ALTER TABLE cotistas ADD COLUMN IF NOT EXISTS termo_aceite DATETIME NULL",
    ] as $sql) { try { ddl_portavel($pdo, $sql); } catch (Throwable $e) { /* coluna já existe */ } }
}

// ---------------- Classes / subclasses (Res. CVM 175) ----------------
function ensure_subclasses(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS subclasses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fundo_id INT NOT NULL,
        nome VARCHAR(120) NOT NULL,
        publico_alvo VARCHAR(60) DEFAULT 'Investidores em geral',
        aplicacao_minima DECIMAL(18,2) DEFAULT 0,
        taxa_adm DECIMAL(8,6) DEFAULT 0,
        taxa_performance DECIMAL(8,6) DEFAULT 0,
        prazo_cotizacao VARCHAR(20) DEFAULT 'D+0',
        prazo_liquidacao VARCHAR(20) DEFAULT 'D+1',
        condominio VARCHAR(20) DEFAULT 'Aberto',
        status VARCHAR(20) DEFAULT 'Ativa',
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_sub (fundo_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // Esteira formal de registro (Res. CVM 175): anexo ao regulamento, protocolo e vigência.
    foreach ([
        "ALTER TABLE subclasses ADD COLUMN IF NOT EXISTS etapa_registro VARCHAR(20) DEFAULT 'Vigente'", // Em registro/Protocolada/Vigente
        "ALTER TABLE subclasses ADD COLUMN IF NOT EXISTS regulamento_anexo VARCHAR(255) NULL",
        "ALTER TABLE subclasses ADD COLUMN IF NOT EXISTS data_vigencia DATE NULL",
        "ALTER TABLE subclasses ADD COLUMN IF NOT EXISTS protocolo_cvm VARCHAR(40) NULL",
    ] as $sql) { try { ddl_portavel($pdo, $sql); } catch (Throwable $e) {} }
}

/** Tipos de fundo (FIF/FIC/FIP), master do FIC e fundo-alvo de cota-de-fundo. */
function ensure_fund_types(PDO $pdo): void {
    foreach ([
        "ALTER TABLE fundos ADD COLUMN IF NOT EXISTS tipo_fundo VARCHAR(10) DEFAULT 'FIF'",
        "ALTER TABLE fundos ADD COLUMN IF NOT EXISTS master_id INT DEFAULT NULL",
        "ALTER TABLE ativos_catalogo ADD COLUMN IF NOT EXISTS fundo_alvo_id INT DEFAULT NULL",
    ] as $sql) { try { ddl_portavel($pdo, $sql); } catch (Throwable $e) { /* já existe */ } }
}

/**
 * MIGRATIONS do domínio (DDL idempotente). Contrato de desacoplamento:
 *  • Chame no BOOTSTRAP da página (fora de qualquer com_transacao) — CREATE/ALTER dão
 *    commit implícito no MySQL e quebrariam uma transação aberta.
 *  • Código de RUNTIME (transacional) NUNCA deve chamar ensure_*; assuma que as tabelas
 *    já existem. Para ALTER portável entre MariaDB/MySQL, use ddl_portavel().
 */
/** Coluna que guarda o conteúdo dos documentos/minutas gerados por fundo (templates). */
function ensure_documentos_conteudo(PDO $pdo): void {
    ddl_portavel($pdo, "ALTER TABLE documentos_abertura ADD COLUMN IF NOT EXISTS conteudo LONGTEXT NULL");
}

function ensure_dominio(PDO $pdo): void {
    ensure_documentos_conteudo($pdo);
    ensure_provisao($pdo);
    ensure_passivos($pdo);
    ensure_catalogo($pdo);
    ensure_tickets($pdo);
    ensure_posicao_custodiante($pdo);
    ensure_precos($pdo);
    ensure_kyc_cotista($pdo);
    ensure_subclasses($pdo);
    ensure_fund_types($pdo);
    ensure_derivativos($pdo);
    ensure_fip($pdo);
    ensure_equipe($pdo);
    ensure_batch($pdo);
    ensure_regulamento($pdo);
    ensure_contrapartes($pdo);
}

require_once __DIR__ . '/marcacao.php';     // motor de marcação por indexador (usa fonte_por_tipo, definido acima)
require_once __DIR__ . '/derivativos.php';  // DI1/DAP com ajuste diário (usa eh_dia_util, com_transacao)
require_once __DIR__ . '/fip.php';          // Private Equity: LPs, chamadas, participações, laudo, waterfall
require_once __DIR__ . '/equipe.php';       // membros do fundo, permissões, convites, transferência, reset de senha
require_once __DIR__ . '/batch.php';        // processamento em lote (fechamento) resiliente por fundo
require_once __DIR__ . '/regulamento.php';  // gerador de regulamento (fundo/classe/subclasse) dirigido por schema
require_once __DIR__ . '/passivo.php';      // passivo do cotista, come-cotas/IR/IOF, livro de passivos (Lei 14.754)
require_once __DIR__ . '/contabilidade.php';// partidas dobradas, diário/razão, balancete
require_once __DIR__ . '/templates_docs.php';// minutas dos documentos do fundo (Res. CVM 175), guardadas por fundo
require_once __DIR__ . '/contrapartes.php'; // cadastro/habilitação de contrapartes (KYC/limite) e câmara por tipo de ativo
// Grafo de carga COMPLETO: com passivo/contabilidade aqui, qualquer função de domínio fica
// disponível em toda página (via a cadeia layout→helpers→dominio) — some a classe de bug
// "Call to undefined function" por ordem de include. (simulador.php fica de fora: é o 2º site.)
