<?php
// Cadastro e habilitação de CONTRAPARTES — o "conheça sua contraparte" que precede a boleta.
// Distinção central (confirmada em B3/CVM):
//  · BOLSA (ação, ETF, futuro listado): NÃO há contraparte bilateral. A Câmara B3 se interpõe
//    como Contraparte Central Garantidora (CCP) e liquida em D+2; o que se registra de nominal é
//    a CORRETORA EXECUTORA. O comprador não sabe quem vendeu.
//  · BALCÃO (CDB, debênture, CRI/CRA, compromissada, swap OTC): a contraparte é NOMINAL e bilateral.
//    Precisa de KYC/PLD com beneficiário final (Res. CVM 50), análise de crédito, LIMITE de crédito
//    e contrato-mestre (ISDA/CGD) — e é APROVADA pela administradora antes de a mesa poder operar.

function ensure_contrapartes(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS contrapartes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        razao_social VARCHAR(150) NOT NULL,
        nome_fantasia VARCHAR(120),
        cnpj VARCHAR(20),
        tipo_instituicao VARCHAR(30),                  -- Banco, Corretora, Distribuidora, Gestora, Emissor, Tesouraria
        papeis VARCHAR(200),                           -- CSV: corretora_executora,emissor,dealer_balcao,banco_liquidante,contraparte_derivativo
        camara VARCHAR(20) DEFAULT 'B3_BALCAO',        -- B3_CCP, B3_BALCAO, SELIC, bilateral
        rating VARCHAR(12),
        limite_credito DECIMAL(18,2) DEFAULT 0,        -- teto de exposição bilateral (balcão)
        contrato_mestre VARCHAR(20) DEFAULT 'Nenhum',  -- ISDA, CGD, Nenhum
        conta_liquidacao VARCHAR(40),
        beneficiario_final VARCHAR(200),               -- UBO exigido pela Res. CVM 50
        kyc_status VARCHAR(20) DEFAULT 'Pendente',     -- Pendente, Concluído
        kyc_validade DATE NULL,
        status VARCHAR(20) DEFAULT 'Pendente',         -- Pendente, Aprovada, Suspensa
        aprovada_por VARCHAR(100),
        aprovada_em DATETIME NULL,
        observacao VARCHAR(300),
        ativo TINYINT DEFAULT 1,
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_cp_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // a boleta passa a referenciar o cadastro por FK; o texto 'contraparte' vira apenas snapshot p/ a custódia
    ddl_portavel($pdo, "ALTER TABLE boletas ADD COLUMN IF NOT EXISTS contraparte_id INT NULL");
    ddl_portavel($pdo, "ALTER TABLE boletas ADD COLUMN IF NOT EXISTS corretora_executora_id INT NULL");

    // Semente de referência (como ensure_catalogo): habilita a mesa a operar já no 1º acesso.
    if ((int) $pdo->query("SELECT COUNT(*) FROM contrapartes")->fetchColumn() === 0) {
        $ref = [
            // razao_social, nome_fantasia, cnpj, tipo, papeis, camara, rating, limite, contrato, conta, UBO, kyc, kyc_val, status, aprov_por, aprov_em, obs
            ['XP Investimentos CCTVM S.A.', 'XP CCTVM', '02.332.886/0001-04', 'Corretora', 'corretora_executora', 'B3_CCP', 'AAA', 0, 'Nenhum', 'B3-1001', 'XP Inc. (capital aberto)', 'Concluído', '2027-01-31', 'Aprovada', 'Equipe Administradora', '2026-05-10 10:00:00', 'Corretora executora — bolsa liquida via Câmara B3 (CCP).'],
            ['Banco BTG Pactual S.A.', 'BTG Pactual', '30.306.294/0001-45', 'Banco', 'corretora_executora,emissor,dealer_balcao,contraparte_derivativo', 'B3_BALCAO', 'AAA', 8000000, 'CGD', 'B3-2044', 'André Esteves e acionistas (capital aberto)', 'Concluído', '2027-01-31', 'Aprovada', 'Equipe Administradora', '2026-05-10 10:05:00', 'Executora e emissora/dealer de balcão; contrato-mestre CGD assinado.'],
            ['Itaú Unibanco S.A.', 'Itaú', '60.701.190/0001-04', 'Banco', 'emissor,contraparte_derivativo,banco_liquidante', 'B3_BALCAO', 'AAA', 10000000, 'CGD', 'B3-2055', 'Itaúsa / família Villela e Setúbal (capital aberto)', 'Concluído', '2027-03-31', 'Aprovada', 'Equipe Administradora', '2026-05-11 09:00:00', 'Emissor de CDB e contraparte de swap.'],
            ['Banco Bradesco S.A.', 'Bradesco', '60.746.948/0001-12', 'Banco', 'emissor,banco_liquidante', 'B3_BALCAO', 'AAA', 6000000, 'Nenhum', 'B3-2061', 'Fundação Bradesco (capital aberto)', 'Concluído', '2027-03-31', 'Aprovada', 'Equipe Administradora', '2026-05-11 09:10:00', 'Emissor de CDB.'],
            ['Vale S.A.', 'Vale', '33.592.510/0001-54', 'Emissor', 'emissor', 'B3_BALCAO', 'AA+', 3000000, 'Nenhum', 'B3-3010', 'Capital pulverizado (corporation)', 'Concluído', '2027-06-30', 'Aprovada', 'Equipe Administradora', '2026-05-12 14:00:00', 'Emissora de debêntures.'],
            ['Banco Métrica S.A.', 'Banco Métrica', '11.222.333/0001-81', 'Banco', 'emissor,dealer_balcao', 'B3_BALCAO', 'A', 1500000, 'Nenhum', 'B3-3099', 'Grupo Métrica (fechado)', 'Concluído', '2027-02-28', 'Aprovada', 'Equipe Administradora', '2026-05-13 11:00:00', 'Emissor de CDB de fundo pequeno — limite reduzido.'],
            ['XYZ Securitizadora S.A.', 'XYZ Securit.', '12.345.678/0001-90', 'Emissor', 'emissor', 'B3_BALCAO', 'BBB', 500000, 'Nenhum', null, 'Carlos Mendes (a confirmar UBO)', 'Pendente', null, 'Pendente', null, null, 'KYC em análise — possível vínculo societário com gestora (checar parte relacionada).'],
        ];
        $ins = $pdo->prepare("INSERT INTO contrapartes
            (razao_social, nome_fantasia, cnpj, tipo_instituicao, papeis, camara, rating, limite_credito,
             contrato_mestre, conta_liquidacao, beneficiario_final, kyc_status, kyc_validade, status,
             aprovada_por, aprovada_em, observacao)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        foreach ($ref as $r) $ins->execute($r);
    }
}

// papéis possíveis de uma contraparte (um mesmo CNPJ pode acumular vários)
function cp_papeis(): array
{
    return [
        'corretora_executora'    => 'Corretora executora (bolsa)',
        'emissor'                => 'Emissor (CDB, debênture, CRI/CRA)',
        'dealer_balcao'          => 'Dealer de balcão / RF privada',
        'contraparte_derivativo' => 'Contraparte de derivativo (OTC)',
        'banco_liquidante'       => 'Banco liquidante',
    ];
}
function cp_papel_label(string $p): string
{
    return cp_papeis()[$p] ?? $p;
}

// negociação em bolsa (contraparte central B3) vs. balcão (contraparte bilateral nominal)
function cp_eh_bolsa(string $tipo): bool
{
    return in_array($tipo, ['Ação', 'ETF', 'Derivativo'], true);
}

// câmara / local de liquidação por tipo de ativo
function cp_camara_por_tipo(string $tipo): string
{
    if ($tipo === 'Título Público') return 'SELIC';
    if (cp_eh_bolsa($tipo)) return 'Câmara B3 (CCP)';
    return 'B3 Balcão (CETIP)';   // CDB, Debênture, CRI/CRA, Cota de Fundo
}

// contrapartes aprovadas, opcionalmente filtradas por papel
function contrapartes_aprovadas(PDO $pdo, ?string $papel = null): array
{
    $rows = $pdo->query("SELECT * FROM contrapartes WHERE status='Aprovada' AND ativo=1 ORDER BY razao_social")->fetchAll();
    if ($papel === null) return $rows;
    return array_values(array_filter($rows, fn($r) => in_array($papel, explode(',', (string) $r['papeis']), true)));
}

// exposição atual contra a contraparte (compras de balcão não rejeitadas), para conferir o limite
function cp_exposicao(PDO $pdo, int $cid): float
{
    $st = $pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM boletas WHERE contraparte_id = ? AND operacao='Compra' AND status <> 'Rejeitada'");
    $st->execute([$cid]);
    return (float) $st->fetchColumn();
}

// uma contraparte por id (ou null)
function contraparte_por_id(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare("SELECT * FROM contrapartes WHERE id = ?");
    $st->execute([$id]);
    return $st->fetch() ?: null;
}
