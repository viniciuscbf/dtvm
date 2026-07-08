<?php
// =============================================================
// MOTOR DE REGULAMENTOS — criação de fundo / classe / subclasse dirigida por schema.
// Cada TIPO tem: metadados, seções de campos (com campos condicionais), regras de
// NOME (sufixos obrigatórios por classe/estratégia), validação e um TEMPLATE de
// regulamento (blocos/artigos com placeholders {{campo}} e condição opcional).
// Rigor jurídico: baseado na Res. CVM 175 (Parte Geral + Anexo I FIF / Anexo IV FIP)
// e nas diretrizes de nomenclatura ANBIMA. Os TEXTOS-BASE são minutas e devem passar
// por revisão jurídica antes de protocolo — o motor garante consistência, não parecer legal.
// =============================================================

mb_internal_encoding('UTF-8');   // consistência de acentos entre CLI e SAPI web

/** Helper para montar um campo do formulário. */
function reg_campo(string $chave, string $rotulo, string $tipo, array $o = []): array {
    return array_merge(['chave' => $chave, 'rotulo' => $rotulo, 'tipo' => $tipo, 'obrigatorio' => false], $o);
}

/** Tipos de fundo/classe que PERMITIMOS abrir (FIDC e FII ficam de fora deste piloto). */
function reg_tipos(): array {
    return [
        'FIF_RF' => [
            'nome' => 'FIF · Classe Renda Fixa', 'classe' => 'Renda Fixa', 'anexo' => 'Res. CVM 175 — Anexo Normativo I (FIF)',
            'tributacao' => 'Longo Prazo', 'sufixo_classe' => 'Renda Fixa',
            'publico' => ['Investidores em geral', 'Investidores qualificados', 'Investidores profissionais'],
            'resumo' => 'Mín. 80% do PL em ativos de renda fixa (juros/índice de preços). Benchmark típico: CDI, IPCA+ ou prefixado.',
        ],
        'FIF_ACOES' => [
            'nome' => 'FIF · Classe Ações', 'classe' => 'Ações', 'anexo' => 'Res. CVM 175 — Anexo Normativo I (FIF)',
            'tributacao' => 'Ações', 'sufixo_classe' => 'Ações',
            'publico' => ['Investidores em geral', 'Investidores qualificados', 'Investidores profissionais'],
            'resumo' => 'Mín. 67% do PL em ações e ativos de renda variável. Benchmark típico: Ibovespa/IBrX.',
        ],
        'FIF_MULTI' => [
            'nome' => 'FIF · Classe Multimercado', 'classe' => 'Multimercado', 'anexo' => 'Res. CVM 175 — Anexo Normativo I (FIF)',
            'tributacao' => 'Longo Prazo', 'sufixo_classe' => 'Multimercado',
            'publico' => ['Investidores em geral', 'Investidores qualificados', 'Investidores profissionais'],
            'resumo' => 'Sem compromisso de concentração; várias classes de ativos e estratégias. Benchmark típico: CDI.',
        ],
        'FIF_CAMBIAL' => [
            'nome' => 'FIF · Classe Cambial', 'classe' => 'Cambial', 'anexo' => 'Res. CVM 175 — Anexo Normativo I (FIF)',
            'tributacao' => 'Longo Prazo', 'sufixo_classe' => 'Cambial',
            'publico' => ['Investidores em geral', 'Investidores qualificados', 'Investidores profissionais'],
            'resumo' => 'Mín. 80% do PL exposto a moeda estrangeira / variação cambial.',
        ],
        'FIP' => [
            'nome' => 'Fundo de Investimento em Participações (FIP)', 'classe' => 'FIP', 'anexo' => 'Res. CVM 175 — Anexo Normativo IV (FIP)',
            'tributacao' => 'FIP', 'sufixo_classe' => 'Participações',
            'publico' => ['Investidores qualificados', 'Investidores profissionais'],
            'resumo' => 'Private equity: mín. 90% em ações/deb. conversíveis/bônus/cotas, com participação no processo decisório das investidas. Condomínio fechado.',
        ],
    ];
}

// ---------------- SEÇÕES COMPARTILHADAS (a maioria das classes FIF) ----------------

function reg_secao_identificacao(array $t): array {
    $fip = ($t['classe'] ?? '') === 'FIP';
    $campos = [
        reg_campo('nome', 'Nome do fundo/classe', 'texto', ['obrigatorio' => true,
            'ajuda' => 'Deve refletir a classe e as estratégias. O sistema valida os sufixos obrigatórios (inclui "Responsabilidade Limitada" quando aplicável).',
            'placeholder' => $fip ? 'Ex.: Aurora Fundo de Investimento em Participações Multiestratégia'
                                  : 'Ex.: Aurora Fundo de Investimento Financeiro ' . $t['sufixo_classe']]),
        reg_campo('publico_alvo', 'Público-alvo', 'select', ['obrigatorio' => true, 'opcoes' => $t['publico']]),
        reg_campo('responsabilidade', 'Responsabilidade dos cotistas', 'select', ['obrigatorio' => true,
            'opcoes' => ['Limitada', 'Ilimitada'], 'default' => 'Limitada',
            'ajuda' => 'Res. CVM 175 / art. 1.368-D, I do Código Civil. "Limitada": exige o sufixo "Responsabilidade Limitada" no nome e regime de insolvência. "Ilimitada": cotista responde por PL negativo (termo de ciência).']),
        reg_campo('condominio', 'Condomínio', 'select', ['obrigatorio' => true,
            'opcoes' => $fip ? ['Fechado'] : ['Aberto', 'Fechado'],
            'ajuda' => 'Aberto: aplicações/resgates a qualquer tempo. Fechado: cotas resgatadas só na amortização/encerramento.']),
    ];
    if (!$fip) {
        $campos[] = reg_campo('estrutura_cotas', 'Estrutura da carteira', 'select', ['obrigatorio' => true,
            'opcoes' => ['Carteira própria de ativos', 'Classe de Investimento em Cotas (fund-of-funds, mín. 95% em cotas)'],
            'ajuda' => 'A 2ª opção (CIC / master-feeder) investe ≥95% em cotas de outras classes e usa "em Cotas" no nome.']);
    }
    $campos[] = reg_campo('duracao', 'Prazo de duração', 'select', ['obrigatorio' => true, 'opcoes' => ['Indeterminado', 'Determinado']]);
    $campos[] = reg_campo('data_encerramento', 'Data de encerramento', 'data', ['se' => ['duracao' => 'Determinado']]);
    $campos[] = reg_campo('exercicio_social', 'Mês de encerramento do exercício social', 'select', ['opcoes' =>
        ['Dezembro', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro'], 'default' => 'Dezembro']);
    $campos[] = reg_campo('tributacao', 'Regime de tributação', 'select', ['obrigatorio' => true,
        'opcoes' => ['Longo Prazo', 'Curto Prazo', 'Ações', 'FIP'], 'default' => $t['tributacao'],
        'ajuda' => 'Define come-cotas/IR. Decorre da classe e do prazo médio da carteira.']);
    return ['titulo' => '1 · Identificação', 'campos' => $campos];
}

function reg_secao_prestadores(): array {
    return ['titulo' => '2 · Prestadores de serviço', 'campos' => [
        reg_campo('administrador', 'Administrador fiduciário', 'texto', ['obrigatorio' => true, 'default' => 'Administradora S.A.']),
        reg_campo('gestor', 'Gestor da carteira', 'texto', ['obrigatorio' => true]),
        reg_campo('custodiante', 'Custodiante', 'texto', ['obrigatorio' => true, 'default' => 'Banco Custodiante S.A.']),
        reg_campo('auditor', 'Auditor independente', 'texto', ['obrigatorio' => true]),
    ]];
}

function reg_secao_taxas(bool $fip = false): array {
    return ['titulo' => '3 · Taxas e remuneração', 'campos' => array_filter([
        reg_campo('taxa_adm', $fip ? 'Taxa de administração (% a.a. s/ capital comprometido)' : 'Taxa de administração global (% a.a.)', 'percent',
            ['obrigatorio' => true, 'min' => 0, 'max' => 5,
             'ajuda' => 'Apropriada por dia útil (base 252) sobre o PL, paga mensalmente até o 5º dia útil. Na 175 costuma ser a "Taxa de Administração Global" (Ofício-Circular 3/2024 CVM/SIN).']),
        reg_campo('taxa_adm_min_mensal', 'Remuneração mínima mensal (R$)', 'moeda', ['min' => 0, 'default' => 0,
            'ajuda' => 'Piso de remuneração em R$/mês (comum em fundos pequenos), normalmente corrigido por IGP-M/IPCA.']),
        $fip ? reg_campo('base_calculo', 'Base de cálculo das taxas', 'select', ['obrigatorio' => true,
            'opcoes' => ['Capital comprometido / integralizado', 'Patrimônio líquido', 'O maior entre PL e capital comprometido'],
            'default' => 'Capital comprometido / integralizado',
            'ajuda' => 'Varia entre os regulamentos de FIP e afeta a receita. "O maior entre PL e capital comprometido" é comum.']) : null,
        $fip ? reg_campo('taxa_gestao_fip', 'Taxa de gestão (% a.a.)', 'percent', ['min' => 0, 'max' => 5, 'default' => 1.5]) : null,
        reg_campo('taxa_custodia_max', 'Taxa máxima de custódia (% a.a.)', 'percent', ['min' => 0, 'max' => 1, 'default' => 0.03]),
        reg_campo('tem_performance', $fip ? 'Cobra taxa de performance / carried interest?' : 'Cobra taxa de performance?', 'checkbox'),
        reg_campo('taxa_performance', $fip ? 'Carried interest (%)' : 'Taxa de performance (%)', 'percent', ['se' => ['tem_performance' => true], 'min' => 0, 'max' => 40, 'default' => $fip ? 20 : 20]),
        reg_campo('benchmark_perf', $fip ? 'Retorno preferencial / hurdle' : 'Índice de referência da performance', 'texto', ['se' => ['tem_performance' => true],
            'placeholder' => $fip ? 'Ex.: capital integralizado atualizado pelo CDI' : 'Ex.: 100% do CDI / Ibovespa / IPCA + 4%']),
        reg_campo('metodo_perf', 'Método de apuração', 'select', ['se' => ['tem_performance' => true],
            'opcoes' => ['Linha d’água (high-water mark)', 'Método do passivo (individual por aplicação)', 'Sobre o que exceder o benchmark no período']]),
        reg_campo('periodo_perf', 'Período de apuração', 'select', ['se' => ['tem_performance' => true],
            'opcoes' => ['Semestral', 'Anual']]),
        $fip ? null : reg_campo('taxa_ingresso', 'Taxa de ingresso (%)', 'percent', ['min' => 0, 'max' => 10, 'default' => 0]),
        $fip ? null : reg_campo('taxa_saida', 'Taxa de saída (%)', 'percent', ['min' => 0, 'max' => 10, 'default' => 0]),
    ])];
}

function reg_secao_passivo(bool $fip = false): array {
    if ($fip) {
        return ['titulo' => '5 · Passivo, capital e chamadas', 'campos' => [
            reg_campo('capital_comprometido', 'Capital comprometido total (R$)', 'moeda', ['obrigatorio' => true,
                'ajuda' => 'Somatório dos compromissos de investimento dos cotistas (integralizados via chamadas de capital).']),
            reg_campo('compromisso_minimo', 'Compromisso mínimo por investidor (R$)', 'moeda', ['obrigatorio' => true]),
            reg_campo('prazo_chamada', 'Prazo de atendimento à chamada de capital (dias)', 'numero', ['obrigatorio' => true, 'default' => 10, 'min' => 1]),
            reg_campo('periodo_investimento', 'Período de investimento', 'texto', ['obrigatorio' => true, 'placeholder' => 'Ex.: 5 anos a partir do 1º fechamento']),
            reg_campo('periodo_desinvestimento', 'Período de desinvestimento', 'texto', ['obrigatorio' => true, 'placeholder' => 'Ex.: 5 anos, prorrogável por 2']),
        ]];
    }
    return ['titulo' => '5 · Passivo (cotização e liquidez)', 'campos' => [
        reg_campo('aplicacao_minima', 'Aplicação inicial mínima (R$)', 'moeda', ['obrigatorio' => true, 'default' => 0]),
        reg_campo('movimentacao_minima', 'Movimentação mínima (R$)', 'moeda', ['default' => 0]),
        reg_campo('saldo_minimo', 'Saldo mínimo de permanência (R$)', 'moeda', ['default' => 0]),
        reg_campo('prazo_cotizacao_aplic', 'Cotização da aplicação', 'select', ['obrigatorio' => true, 'opcoes' => ['D+0', 'D+1'], 'default' => 'D+0']),
        reg_campo('prazo_cotizacao_resgate', 'Cotização do resgate', 'select', ['obrigatorio' => true, 'opcoes' => ['D+0', 'D+1', 'D+2', 'D+4', 'D+30', 'D+60'], 'default' => 'D+1',
            'ajuda' => 'Dia em que a cota do resgate é apurada.']),
        reg_campo('prazo_liquidacao_resgate', 'Liquidação financeira do resgate', 'select', ['obrigatorio' => true, 'opcoes' => ['D+0', 'D+1', 'D+2', 'D+3', 'D+5'], 'default' => 'D+1',
            'ajuda' => 'Dia do pagamento ao cotista, contado da cotização.']),
        reg_campo('horario_corte', 'Horário de corte', 'texto', ['default' => '14:00']),
        reg_campo('tem_carencia', 'Possui carência para resgate?', 'checkbox'),
        reg_campo('carencia_dias', 'Carência (dias)', 'numero', ['se' => ['tem_carencia' => true], 'min' => 1]),
    ]];
}

function reg_secao_riscos(array $extra = []): array {
    $base = ['Risco de mercado', 'Risco de crédito', 'Risco de liquidez', 'Risco de concentração', 'Risco de contraparte', 'Risco operacional', 'Risco de patrimônio líquido negativo'];
    return ['titulo' => '6 · Fatores de risco', 'campos' => [
        reg_campo('fatores_risco', 'Fatores de risco aplicáveis', 'checkboxes', ['opcoes' => array_merge($base, $extra),
            'ajuda' => 'Marque os riscos que o regulamento deve descrever para este fundo.']),
    ]];
}

// ---------------- SCHEMA POR TIPO ----------------

function reg_schema(string $tipo): array {
    $tipos = reg_tipos();
    $t = $tipos[$tipo] ?? null;
    if (!$t) return [];

    // Seção de política de investimento varia por classe.
    if ($tipo === 'FIF_RF') {
        $politica = ['titulo' => '4 · Política de investimento', 'campos' => [
            reg_campo('objetivo', 'Objetivo do fundo', 'textarea', ['obrigatorio' => true,
                'placeholder' => 'Ex.: superar a variação do CDI investindo em títulos públicos e privados de renda fixa.']),
            reg_campo('indexador', 'Indexador / benchmark', 'select', ['obrigatorio' => true, 'opcoes' => ['CDI', 'IPCA+', 'Prefixado', 'IMA-B', 'IMA-B 5']]),
            reg_campo('pct_min_classe', 'Mínimo em renda fixa (% do PL)', 'percent', ['obrigatorio' => true, 'default' => 80, 'min' => 80, 'max' => 100,
                'ajuda' => 'A classe Renda Fixa exige no mínimo 80% do PL em ativos de renda fixa (Anexo I).']),
            reg_campo('usa_derivativos', 'Uso de derivativos', 'select', ['obrigatorio' => true, 'opcoes' => ['Não utiliza', 'Somente para proteção (hedge)', 'Inclusive para posicionamento/alavancagem']]),
            reg_campo('credito_privado', 'Investe majoritariamente em crédito privado (>50%)?', 'checkbox',
                ['ajuda' => 'Se sim, exige a designação "Crédito Privado" no nome e termo de ciência de risco do cotista.']),
            reg_campo('pct_credito_privado', 'Limite de crédito privado (% do PL)', 'percent', ['se' => ['credito_privado' => true], 'min' => 50, 'max' => 100, 'default' => 100]),
            reg_campo('invest_exterior', 'Limite de investimento no exterior (% do PL)', 'percent', ['min' => 0, 'max' => 100, 'default' => 0]),
            reg_campo('qualificacao', 'Qualificações da classe (Anexo I, Seção VII)', 'checkboxes', ['opcoes' => ['Curto Prazo', 'Referenciada', 'Simples', 'Dívida Externa'],
                'ajuda' => 'Curto Prazo (art. 52): prazo médio da carteira curto, títulos de baixo risco. Referenciada (art. 53): ≥95% acompanhando um índice/indicador. Simples (art. 54): ≥95% em títulos públicos/baixo risco de crédito, sem derivativos salvo hedge, sem crédito privado. Dívida Externa (art. 55): aplica no exterior em títulos da dívida da União.']),
        ]];
    } elseif ($tipo === 'FIF_ACOES') {
        $politica = ['titulo' => '4 · Política de investimento', 'campos' => [
            reg_campo('objetivo', 'Objetivo do fundo', 'textarea', ['obrigatorio' => true, 'placeholder' => 'Ex.: superar o Ibovespa no longo prazo via seleção de ações.']),
            reg_campo('indexador', 'Índice de referência', 'select', ['obrigatorio' => true, 'opcoes' => ['Ibovespa', 'IBrX-100', 'IBrX-50', 'SMLL (Small Caps)', 'IDIV']]),
            reg_campo('pct_min_classe', 'Mínimo em ações/RV (% do PL)', 'percent', ['obrigatorio' => true, 'default' => 67, 'min' => 67, 'max' => 100,
                'ajuda' => 'A classe Ações exige no mínimo 67% do PL em ações e ativos de renda variável (Anexo I).']),
            reg_campo('usa_derivativos', 'Uso de derivativos', 'select', ['obrigatorio' => true, 'opcoes' => ['Não utiliza', 'Somente para proteção (hedge)', 'Inclusive para posicionamento/alavancagem']]),
            reg_campo('invest_exterior', 'Limite de investimento no exterior (% do PL)', 'percent', ['min' => 0, 'max' => 100, 'default' => 0]),
            reg_campo('bdr', 'Investe em BDRs?', 'checkbox'),
        ]];
    } elseif ($tipo === 'FIF_MULTI') {
        $politica = ['titulo' => '4 · Política de investimento', 'campos' => [
            reg_campo('objetivo', 'Objetivo / estratégia', 'textarea', ['obrigatorio' => true, 'placeholder' => 'Ex.: retorno absoluto combinando juros, moedas, ações e crédito.']),
            reg_campo('indexador', 'Benchmark', 'select', ['obrigatorio' => true, 'opcoes' => ['CDI', 'IPCA+', 'IFMM']]),
            reg_campo('usa_derivativos', 'Uso de derivativos', 'select', ['obrigatorio' => true, 'opcoes' => ['Somente para proteção (hedge)', 'Inclusive para posicionamento/alavancagem']]),
            reg_campo('alavancagem', 'Admite alavancagem?', 'checkbox'),
            reg_campo('limite_alavancagem', 'Limite de exposição (múltiplo do PL)', 'numero', ['se' => ['alavancagem' => true], 'min' => 1, 'default' => 2]),
            reg_campo('credito_privado', 'Investe majoritariamente em crédito privado (>50%)?', 'checkbox'),
            reg_campo('pct_credito_privado', 'Limite de crédito privado (% do PL)', 'percent', ['se' => ['credito_privado' => true], 'min' => 50, 'max' => 100, 'default' => 100]),
            reg_campo('invest_exterior', 'Limite de investimento no exterior (% do PL)', 'percent', ['min' => 0, 'max' => 100, 'default' => 0]),
        ]];
    } elseif ($tipo === 'FIF_CAMBIAL') {
        $politica = ['titulo' => '4 · Política de investimento', 'campos' => [
            reg_campo('objetivo', 'Objetivo do fundo', 'textarea', ['obrigatorio' => true,
                'placeholder' => 'Ex.: manter o patrimônio investido em ativos, diretos ou sintetizados via derivativos, atrelados à variação cambial.']),
            reg_campo('moeda', 'Moeda de referência', 'select', ['obrigatorio' => true,
                'opcoes' => ['Dólar dos EUA (USD)', 'Euro (EUR)', 'Cesta de moedas (diversas, sem concentração)']]),
            reg_campo('pct_min_classe', 'Mínimo exposto a câmbio (% do PL)', 'percent', ['obrigatorio' => true, 'default' => 80, 'min' => 80, 'max' => 100,
                'ajuda' => 'A classe Cambial exige no mínimo 80% do PL exposto à variação de moeda estrangeira (Anexo I). Atenção: a redação da composição é de câmbio, não de renda fixa.']),
            reg_campo('usa_derivativos', 'Uso de derivativos', 'select', ['obrigatorio' => true,
                'opcoes' => ['Inclusive para posicionamento/alavancagem', 'Somente para proteção (hedge)'],
                'ajuda' => 'Em fundos cambiais o derivativo costuma ser o instrumento central (uso amplo), não apenas hedge.']),
            reg_campo('invest_exterior', 'Ativos no exterior (% do PL)', 'percent', ['min' => 0, 'max' => 100, 'default' => 10]),
        ]];
    } elseif ($tipo === 'FIP') {
        $politica = ['titulo' => '4 · Política de investimento (FIP)', 'campos' => [
            reg_campo('objetivo', 'Tese de investimento', 'textarea', ['obrigatorio' => true, 'placeholder' => 'Ex.: participações relevantes em companhias de médio porte do setor X.']),
            reg_campo('categoria', 'Categoria do FIP (Anexo IV, art. 13)', 'select', ['obrigatorio' => true,
                'opcoes' => ['Multiestratégia', 'Capital Semente', 'Empresas Emergentes', 'Infraestrutura', 'Produção Econômica Intensiva em PD&I'],
                'ajuda' => 'Capital Semente: investida com receita ≤ R$ 20 mi (art. 14). Empresas Emergentes: receita ≤ R$ 400 mi (art. 15). Infra/PD&I (art. 16): projetos no país, mín. 5 cotistas ≤ 40% cada. Multiestratégia: residual (art. 17).']),
            reg_campo('enquadramento_min', 'Enquadramento mínimo em ativos elegíveis (% do PL)', 'percent', ['obrigatorio' => true, 'default' => 90, 'min' => 90, 'max' => 100,
                'ajuda' => 'Res. CVM 175, Anexo IV, art. 11: ≥ 90% do PL nos ativos elegíveis do art. 5º (ações, deb. conversíveis, bônus, cotas). Dívida não conversível ≤ 33% do capital subscrito (art. 11, §1º).']),
            reg_campo('participacao_decisoria', 'Confirma participação no processo decisório das investidas', 'checkbox',
                ['obrigatorio' => true, 'ajuda' => 'Requisito do FIP (Anexo IV, art. 5º, §1º e art. 6º): influência efetiva via bloco de controle, acordo de acionistas ou indicação ao conselho.']),
            reg_campo('entidade_investimento', 'Qualifica-se como "entidade de investimento" (Lei 14.754/2023)', 'checkbox',
                ['default' => true, 'ajuda' => 'Se sim: sem come-cotas, IR de 15% só na distribuição/resgate.']),
            reg_campo('tem_comite_investimento', 'Possui comitê de investimento', 'checkbox'),
            reg_campo('tem_comite_avaliacao', 'Possui comitê de avaliação (valor justo)', 'checkbox'),
        ]];
    } else {
        $politica = ['titulo' => '4 · Política de investimento', 'campos' => []];
    }

    $fip = $tipo === 'FIP';
    $riscosExtra = $fip ? ['Risco de iliquidez de participações', 'Risco de avaliação (valor justo nível 3)']
                        : ($tipo === 'FIF_CAMBIAL' ? ['Risco cambial'] : ['Risco de derivativos']);

    $secoes = [
        reg_secao_identificacao($t),
        reg_secao_prestadores(),
        reg_secao_taxas($fip),
        $politica,
        reg_secao_passivo($fip),
        reg_secao_riscos($riscosExtra),
    ];
    if ($fip) {
        // waterfall
        $secoes[] = ['titulo' => '7 · Distribuição de resultados (waterfall)', 'campos' => [
            reg_campo('hurdle', 'Retorno preferencial / hurdle (% a.a.)', 'percent', ['default' => 8, 'min' => 0, 'max' => 30]),
            reg_campo('carry', 'Carried interest ao gestor (%)', 'percent', ['default' => 20, 'min' => 0, 'max' => 40]),
            reg_campo('catch_up', 'Cláusula de catch-up para o gestor', 'checkbox'),
        ]];
    }
    return $secoes;
}

// ---------------- NOMENCLATURA ----------------

/** Sufixos/termos que o NOME deve conter, dado o tipo e as respostas. */
function reg_nome_tokens(string $tipo, array $d): array {
    $tipos = reg_tipos();
    $t = $tipos[$tipo] ?? [];
    $tokens = [];
    $tokens[] = $t['sufixo_classe'] ?? '';                                   // classe (Renda Fixa/Ações/...)
    // crédito privado > 50% (designação obrigatória + termo de ciência de risco)
    if (!empty($d['credito_privado'])) $tokens[] = 'Crédito Privado';
    // qualificações de RF — Anexo I, Seção VII (Tipificação): Curto Prazo (art. 52),
    // Referenciada (art. 53), Simples (art. 54), Dívida Externa (art. 55).
    // NOTA: "Investimento no Exterior" NÃO é sufixo de FIF na Res. CVM 175 (foi aposentado).
    foreach ((array)($d['qualificacao'] ?? []) as $q) $tokens[] = $q;
    // FIP: "Fundo de Investimento em Participações" + tipo/categoria (Anexo IV, arts. 3º e 13).
    // O tipo aparece SEMPRE no nome (inclusive "Multiestratégia"), como nos regulamentos reais.
    if ($tipo === 'FIP') {
        $tokens[] = 'Participações';
        $cat = $d['categoria'] ?? '';
        if ($cat) $tokens[] = preg_replace('/\s*\(.*\)/', '', $cat);
    }
    // Responsabilidade limitada → sufixo obrigatório no nome (Res. CVM 175 / art. 1.368-D, I, CC).
    if (($d['responsabilidade'] ?? 'Limitada') === 'Limitada') $tokens[] = 'Responsabilidade Limitada';
    return array_values(array_filter(array_unique($tokens)));
}

/** Verifica se o nome contém todos os tokens obrigatórios; retorna os que faltam.
 *  Encoding UTF-8 explícito (o SAPI web nem sempre define mb_internal_encoding). */
function reg_nome_faltantes(string $nome, array $tokens): array {
    $n = mb_strtolower($nome, 'UTF-8');
    $faltam = [];
    foreach ($tokens as $tk) {
        if ($tk === '') continue;
        if (mb_strpos($n, mb_strtolower($tk, 'UTF-8'), 0, 'UTF-8') === false) $faltam[] = $tk;
    }
    return $faltam;
}

/** Sugestão de nome padronizado a partir de um "nome de fantasia". */
function reg_sugerir_nome(string $tipo, array $d): string {
    $fip = $tipo === 'FIP';
    // nome de fantasia = parte antes da forma jurídica (remove tokens conhecidos p/ não duplicar)
    $fantasia = trim($d['nome_fantasia'] ?? preg_replace('/\b(Fundo de Investimento|Financeiro|Participações|Responsabilidade Limitada).*/iu', '', $d['nome'] ?? '')) ?: 'Novo';
    $cic = !$fip && str_starts_with($d['estrutura_cotas'] ?? '', 'Classe de Investimento em Cotas');
    $base = $fip ? 'Fundo de Investimento em' : ('Fundo de Investimento Financeiro' . ($cic ? ' em Cotas de Fundos de Investimento' : ''));
    // remove "Participações" dos tokens quando FIP (já entra via base) e reordena resp. limitada por último
    $tokens = reg_nome_tokens($tipo, $d);
    $partes = array_filter([$fantasia, $base, implode(' ', $tokens)]);
    return trim(preg_replace('/\s+/u', ' ', implode(' ', $partes)));
}

// ---------------- VALIDAÇÃO ----------------

/** Um campo condicional só "vale" se a condição bater nas respostas. */
function reg_campo_ativo(array $campo, array $d): bool {
    if (empty($campo['se'])) return true;
    foreach ($campo['se'] as $k => $v) {
        $val = $d[$k] ?? null;
        if ($v === true) { if (empty($val)) return false; }
        elseif ((string)$val !== (string)$v) return false;
    }
    return true;
}

/** Valida as respostas contra o schema + regras de nome. Retorna lista de erros. */
function reg_validar(string $tipo, array $d): array {
    $erros = [];
    foreach (reg_schema($tipo) as $sec) {
        foreach ($sec['campos'] as $c) {
            if (!reg_campo_ativo($c, $d)) continue;
            $v = $d[$c['chave']] ?? '';
            $vazio = ($v === '' || $v === null || $v === [] || ($c['tipo'] === 'checkbox' && !$v));
            if (!empty($c['obrigatorio']) && $vazio) {
                $erros[] = $c['tipo'] === 'checkbox' ? 'Confirme: ' . $c['rotulo'] : 'Preencha: ' . $c['rotulo'];
                continue;
            }
            if (in_array($c['tipo'], ['percent', 'numero', 'moeda'], true) && $v !== '' && $v !== null) {
                $num = (float)str_replace(['.', ','], ['', '.'], (string)$v);
                if (isset($c['min']) && $num < $c['min']) $erros[] = $c['rotulo'] . ': mínimo ' . $c['min'];
                if (isset($c['max']) && $num > $c['max']) $erros[] = $c['rotulo'] . ': máximo ' . $c['max'];
            }
        }
    }
    // coerência: performance exige benchmark
    if (!empty($d['tem_performance']) && trim($d['benchmark_perf'] ?? '') === '')
        $erros[] = 'Informe o índice de referência da taxa de performance.';
    // nome: sufixos obrigatórios
    $faltam = reg_nome_faltantes($d['nome'] ?? '', reg_nome_tokens($tipo, $d));
    if ($faltam) $erros[] = 'O nome deve conter: ' . implode(', ', $faltam) . '. (sugestão: "' . reg_sugerir_nome($tipo, $d) . '")';
    return $erros;
}

// ---------------- RENDER DO FORMULÁRIO ----------------

/** Renderiza um campo como controle Bootstrap (com atributo data-se para condicional via JS). */
function reg_render_campo(array $c, array $d = []): string {
    $ch = e_html($c['chave']);
    $val = $d[$c['chave']] ?? ($c['default'] ?? '');
    $req = !empty($c['obrigatorio']) ? 'required' : '';
    $se = !empty($c['se']) ? " data-se='" . e_html(json_encode($c['se'])) . "'" : '';
    $ajuda = !empty($c['ajuda']) ? '<div class="form-text" style="font-size:.75rem">' . e_html($c['ajuda']) . '</div>' : '';
    $ph = !empty($c['placeholder']) ? 'placeholder="' . e_html($c['placeholder']) . '"' : '';
    $rot = e_html($c['rotulo']) . (!empty($c['obrigatorio']) ? ' <span class="text-danger">*</span>' : '');
    $wrapIni = "<div class=\"mb-3 reg-campo\"$se data-chave=\"$ch\">";
    $wrapFim = "$ajuda</div>";

    switch ($c['tipo']) {
        case 'checkbox':
            $ck = !empty($val) ? 'checked' : '';
            return "<div class=\"mb-2 reg-campo\"$se data-chave=\"$ch\"><div class=\"form-check\">
                <input class=\"form-check-input\" type=\"checkbox\" name=\"$ch\" id=\"f_$ch\" value=\"1\" $ck>
                <label class=\"form-check-label\" for=\"f_$ch\" style=\"font-size:.86rem\">$rot</label></div>$ajuda</div>";
        case 'checkboxes':
            $sel = (array)$val; $itens = '';
            foreach ($c['opcoes'] as $op) {
                $ck = in_array($op, $sel, true) ? 'checked' : '';
                $id = 'f_' . $ch . '_' . md5($op);
                $itens .= "<div class=\"form-check\"><input class=\"form-check-input\" type=\"checkbox\" name=\"{$ch}[]\" id=\"$id\" value=\"" . e_html($op) . "\" $ck>
                    <label class=\"form-check-label\" for=\"$id\" style=\"font-size:.84rem\">" . e_html($op) . "</label></div>";
            }
            return "$wrapIni<label class=\"form-label\" style=\"font-size:.82rem\">$rot</label>$itens$wrapFim";
        case 'select':
            $ops = '';
            foreach ($c['opcoes'] as $op) {
                $k = is_int(array_key_first($c['opcoes'])) ? $op : $op;
                $s = ((string)$val === (string)$op) ? 'selected' : '';
                $ops .= "<option value=\"" . e_html($op) . "\" $s>" . e_html($op) . "</option>";
            }
            $pre = empty($val) && empty($c['default']) ? '<option value="">Selecione…</option>' : '';
            return "$wrapIni<label class=\"form-label\" style=\"font-size:.82rem\">$rot</label>
                <select class=\"form-select form-select-sm\" name=\"$ch\" $req>$pre$ops</select>$wrapFim";
        case 'textarea':
            return "$wrapIni<label class=\"form-label\" style=\"font-size:.82rem\">$rot</label>
                <textarea class=\"form-control form-control-sm\" name=\"$ch\" rows=\"2\" $req $ph>" . e_html((string)$val) . "</textarea>$wrapFim";
        case 'percent':
            return "$wrapIni<label class=\"form-label\" style=\"font-size:.82rem\">$rot</label>
                <div class=\"input-group input-group-sm\"><input type=\"text\" class=\"form-control\" name=\"$ch\" value=\"" . e_html((string)$val) . "\" $req $ph>
                <span class=\"input-group-text\">%</span></div>$wrapFim";
        case 'moeda':
            return "$wrapIni<label class=\"form-label\" style=\"font-size:.82rem\">$rot</label>
                <div class=\"input-group input-group-sm\"><span class=\"input-group-text\">R$</span>
                <input type=\"text\" class=\"form-control\" name=\"$ch\" value=\"" . e_html((string)$val) . "\" $req $ph></div>$wrapFim";
        case 'data':
            return "$wrapIni<label class=\"form-label\" style=\"font-size:.82rem\">$rot</label>
                <input type=\"date\" class=\"form-control form-control-sm\" name=\"$ch\" value=\"" . e_html((string)$val) . "\" $req>$wrapFim";
        default: // texto / numero
            $tp = $c['tipo'] === 'numero' ? 'number' : 'text';
            return "$wrapIni<label class=\"form-label\" style=\"font-size:.82rem\">$rot</label>
                <input type=\"$tp\" class=\"form-control form-control-sm\" name=\"$ch\" value=\"" . e_html((string)$val) . "\" $req $ph>$wrapFim";
    }
}

/** Coleta as respostas do $_POST conforme o schema do tipo. */
function reg_coletar(string $tipo): array {
    $d = [];
    foreach (reg_schema($tipo) as $sec) {
        foreach ($sec['campos'] as $c) {
            $ch = $c['chave'];
            if ($c['tipo'] === 'checkbox')       $d[$ch] = !empty($_POST[$ch]);
            elseif ($c['tipo'] === 'checkboxes') $d[$ch] = array_values((array)($_POST[$ch] ?? []));
            else                                  $d[$ch] = trim((string)($_POST[$ch] ?? ''));
        }
    }
    return $d;
}

/** Renderiza o formulário completo (seções + campos) de um tipo. */
function reg_render_form(string $tipo, array $d = []): string {
    $h = '';
    foreach (reg_schema($tipo) as $sec) {
        $h .= '<div class="card mb-3"><div class="card-header" style="font-size:.9rem"><b>' . e_html($sec['titulo']) . '</b></div><div class="card-body">';
        foreach ($sec['campos'] as $c) $h .= reg_render_campo($c, $d);
        $h .= '</div></div>';
    }
    return $h;
}

/** JS que mostra/esconde campos condicionais (data-se) conforme as respostas. */
function reg_form_js(): string {
    return <<<'JS'
<script>
(function(){
  function val(name){
    var el=document.querySelector('[name="'+name+'"]');
    if(!el) return '';
    if(el.type==='checkbox') return el.checked;
    return el.value;
  }
  function apply(){
    document.querySelectorAll('.reg-campo[data-se]').forEach(function(w){
      var cond=JSON.parse(w.getAttribute('data-se')); var ok=true;
      for(var k in cond){ var v=val(k); if(cond[k]===true){ if(!v) ok=false; } else if(String(v)!==String(cond[k])) ok=false; }
      w.style.display = ok ? '' : 'none';
      w.querySelectorAll('input,select,textarea').forEach(function(i){ i.disabled=!ok; });
    });
  }
  document.addEventListener('change', apply);
  document.addEventListener('DOMContentLoaded', apply);
  apply();
})();
</script>
JS;
}

// ---------------- GERAÇÃO DO REGULAMENTO (documento) ----------------

/** Formata um valor para o texto do regulamento. */
function reg_fmt(array $d, string $k, string $kind = 'texto'): string {
    $v = $d[$k] ?? '';
    if ($v === '' || $v === null) return '—';
    return match ($kind) {
        'percent' => number_format((float)str_replace(',', '.', (string)$v), 2, ',', '.') . '%',
        'moeda'   => 'R$ ' . number_format((float)str_replace(['.', ','], ['', '.'], (string)$v), 2, ',', '.'),
        'lista'   => implode('; ', (array)$v),
        default   => (string)$v,
    };
}

/**
 * Gera o REGULAMENTO em HTML (artigos numerados) a partir das respostas.
 * Retorna o HTML pronto para preview e para o PDF.
 */
function reg_gerar_html(string $tipo, array $d): string {
    $tipos = reg_tipos();
    $t = $tipos[$tipo] ?? [];
    $nome = e_html($d['nome'] ?? 'Fundo');
    $art = 0;
    $A = function (string $titulo, string $corpo) use (&$art) {
        $art++;
        return "<h4 style='font-size:1rem;margin:14px 0 4px'>Art. {$art}º — " . e_html($titulo) . "</h4><p style='margin:0 0 8px;text-align:justify'>$corpo</p>";
    };
    $h = "<div class='regulamento'>";
    $h .= "<h2 style='text-align:center;margin-bottom:2px'>REGULAMENTO</h2>";
    $h .= "<p style='text-align:center;margin-top:0'><b>$nome</b><br><span style='font-size:.85rem;color:#555'>"
        . e_html($t['nome'] ?? '') . ' · ' . e_html($t['anexo'] ?? '') . "</span></p>";

    $h .= "<h3 style='font-size:1.05rem'>REGULAMENTO (Parte Geral) — Capítulo I — Do Fundo</h3>";
    $cic = !empty($d['estrutura_cotas']) && str_starts_with((string)$d['estrutura_cotas'], 'Classe de Investimento em Cotas');
    $h .= $A('Denominação, classe e forma', "O fundo denomina-se <b>$nome</b>, constituído sob a forma de condomínio <b>"
        . reg_fmt($d, 'condominio') . "</b>" . ($cic ? ', estruturado como <b>classe de investimento em cotas</b> (fundo de fundos)' : '')
        . ", da classe <b>" . e_html($t['classe'] ?? '') . "</b>, com prazo de duração <b>" . reg_fmt($d, 'duracao') . "</b>"
        . (!empty($d['data_encerramento']) ? ', encerrando-se em ' . reg_fmt($d, 'data_encerramento') : '')
        . ", exercício social encerrado em <b>" . reg_fmt($d, 'exercicio_social') . "</b>, regido por este regulamento e pela Resolução CVM 175.");
    $h .= $A('Público-alvo', "Destina-se a <b>" . reg_fmt($d, 'publico_alvo') . "</b>.");
    $h .= $A('Regime de tributação', "Adota o regime de tributação <b>" . reg_fmt($d, 'tributacao') . "</b>, aplicando-se as regras de imposto de renda e, quando cabível, come-cotas correspondentes.");

    $h .= "<h3 style='font-size:1.05rem'>Capítulo II — Dos Prestadores de Serviços Essenciais</h3>";
    $h .= $A('Prestadores e esferas de responsabilidade', "Administrador fiduciário: <b>" . reg_fmt($d, 'administrador') . "</b>. Gestor da carteira: <b>"
        . reg_fmt($d, 'gestor') . "</b>. Custodiante: <b>" . reg_fmt($d, 'custodiante') . "</b>. Auditor independente: <b>"
        . reg_fmt($d, 'auditor') . "</b>. Cada prestador atua na sua esfera de responsabilidade, <b>não havendo solidariedade</b> entre administrador e gestor (Res. CVM 175, art. 81).");
    $h .= $A('Encargos e rateio de despesas', "Constituem encargos do fundo as taxas e despesas admitidas pela Res. CVM 175, apropriadas e rateadas na forma deste regulamento.");

    $h .= "<h3 style='font-size:1.05rem'>ANEXO — Da Classe — Capítulo III — Da Política de Investimento</h3>";
    $h .= $A('Objetivo', reg_fmt($d, 'objetivo') . " Tal objetivo não constitui promessa ou garantia de rentabilidade, representando meta a ser perseguida pelo gestor.");
    if ($tipo === 'FIP') {
        $h .= $A('Categoria e enquadramento', "FIP da categoria <b>" . reg_fmt($d, 'categoria') . "</b>. Manterá, no mínimo, <b>"
            . reg_fmt($d, 'enquadramento_min', 'percent') . "</b> do patrimônio líquido em ações, debêntures conversíveis, bônus de subscrição e cotas, "
            . "com participação no processo decisório das companhias investidas."
            . (!empty($d['entidade_investimento']) ? " Qualifica-se como <b>entidade de investimento</b> (Lei 14.754/2023)." : ""));
        if (!empty($d['tem_comite_investimento']) || !empty($d['tem_comite_avaliacao'])) {
            $com = [];
            if (!empty($d['tem_comite_investimento'])) $com[] = 'comitê de investimento';
            if (!empty($d['tem_comite_avaliacao'])) $com[] = 'comitê de avaliação (valor justo)';
            $h .= $A('Comitês', "O fundo contará com " . implode(' e ', $com) . ", cujas competências constam de anexo a este regulamento.");
        }
    } else {
        $lim = $d['pct_min_classe'] ?? null;
        $txtLim = $lim ? "Manterá, no mínimo, <b>" . reg_fmt($d, 'pct_min_classe', 'percent') . "</b> do PL em ativos compatíveis com a classe " . e_html($t['classe'] ?? '') . ". " : '';
        $idx = isset($d['indexador']) ? "Índice de referência: <b>" . reg_fmt($d, 'indexador') . "</b>. " : '';
        $moe = isset($d['moeda']) ? "Moeda de referência: <b>" . reg_fmt($d, 'moeda') . "</b>. " : '';
        $der = "Derivativos: <b>" . reg_fmt($d, 'usa_derivativos') . "</b>. ";
        $ext = (isset($d['invest_exterior']) && (float)$d['invest_exterior'] > 0) ? "Investimento no exterior: até <b>" . reg_fmt($d, 'invest_exterior', 'percent') . "</b> do PL. " : '';
        $cp = !empty($d['credito_privado']) ? "A classe aplica <b>mais de 50% do PL em crédito privado</b> (até " . reg_fmt($d, 'pct_credito_privado', 'percent') . "), sujeitando-se a <b>risco de perda substancial</b>; o cotista firma <b>termo de ciência de risco</b>. " : '';
        $cicT = $cic ? "Por ser <b>classe de investimento em cotas</b>, aplica no mínimo <b>95% do PL em cotas</b> de outras classes/fundos, mantendo até 5% em caixa/liquidez. " : '';
        $h .= $A('Composição e limites', $cicT . $txtLim . $idx . $moe . $der . $ext . $cp);
        if (!empty($d['qualificacao'])) $h .= $A('Qualificações da classe', 'A classe observa as regras de: <b>' . reg_fmt($d, 'qualificacao', 'lista') . '</b>.');
        if (!empty($d['alavancagem'])) $h .= $A('Alavancagem', 'Admite alavancagem, limitada à exposição de <b>' . reg_fmt($d, 'limite_alavancagem') . 'x</b> o patrimônio líquido.');
    }

    $h .= "<h3 style='font-size:1.05rem'>ANEXO — Da Classe — Capítulo IV — Da Remuneração</h3>";
    $fip2 = $tipo === 'FIP';
    $piso = ((float)str_replace(['.', ','], ['', '.'], (string)($d['taxa_adm_min_mensal'] ?? 0)) > 0)
        ? ", observada a remuneração mínima mensal de <b>" . reg_fmt($d, 'taxa_adm_min_mensal', 'moeda') . "</b> (corrigida por índice de preços)" : '';
    $bcFip = $d['base_calculo'] ?? 'Capital comprometido / integralizado';
    $baseFipTxt = stripos($bcFip, 'maior') !== false ? "o maior entre o patrimônio líquido e o capital comprometido"
                 : (stripos($bcFip, 'Patrim') !== false ? "o patrimônio líquido"
                 : "o capital comprometido (no período de investimento) e o capital integralizado (no desinvestimento)");
    $baseTaxa = $fip2 ? " sobre $baseFipTxt"
                      : " sobre o patrimônio líquido, apropriada por dia útil (base 252) e paga mensalmente até o 5º dia útil do mês subsequente";
    $gestaoFip = ($fip2 && isset($d['taxa_gestao_fip'])) ? " Taxa de gestão de <b>" . reg_fmt($d, 'taxa_gestao_fip', 'percent') . " a.a.</b>." : '';
    $cust = isset($d['taxa_custodia_max']) ? " Taxa máxima de custódia de <b>" . reg_fmt($d, 'taxa_custodia_max', 'percent') . " a.a.</b>." : '';
    $perf = !empty($d['tem_performance'])
        ? " " . ($fip2 ? 'Carried interest' : 'Taxa de performance') . " de <b>" . reg_fmt($d, 'taxa_performance', 'percent') . "</b> sobre o que exceder <b>"
          . reg_fmt($d, 'benchmark_perf') . "</b>, apurada <b>" . reg_fmt($d, 'periodo_perf') . "</b> pelo método <b>" . reg_fmt($d, 'metodo_perf')
          . "</b>; é <b>vedada</b> a cobrança quando o valor da cota de apuração for inferior à cota base (linha d’água)."
        : " Não há cobrança de taxa de performance.";
    $ingsai = !$fip2 ? " Taxa de ingresso: <b>" . reg_fmt($d, 'taxa_ingresso', 'percent') . "</b>; taxa de saída: <b>" . reg_fmt($d, 'taxa_saida', 'percent') . "</b>." : '';
    $h .= $A('Remuneração', "Taxa de administração" . ($fip2 ? '' : ' global') . " de <b>" . reg_fmt($d, 'taxa_adm', 'percent') . " ao ano</b>$baseTaxa$piso.$gestaoFip$cust$perf$ingsai");

    $h .= "<h3 style='font-size:1.05rem'>ANEXO — Da Classe — Capítulo V — Da Responsabilidade dos Cotistas e do Passivo</h3>";
    $limitada = ($d['responsabilidade'] ?? 'Limitada') === 'Limitada';
    $h .= $A('Responsabilidade dos cotistas e regime de insolvência', $limitada
        ? "A responsabilidade dos cotistas é <b>limitada ao valor por eles subscrito</b>, nos termos do art. 1.368-D, I, do Código Civil e da Res. CVM 175. Constatado patrimônio líquido negativo, a classe poderá sujeitar-se ao <b>regime de insolvência</b>, sem que os cotistas respondam além do valor subscrito."
        : "A responsabilidade dos cotistas <b>não é limitada</b> ao valor por eles detido: respondem por eventual <b>patrimônio líquido negativo</b> da classe e firmam, na aquisição das cotas, <b>Termo de Ciência e Assunção de Responsabilidade Ilimitada</b>.");
    if ($tipo === 'FIP') {
        $h .= $A('Capital comprometido e chamadas de capital', "Capital comprometido total de <b>" . reg_fmt($d, 'capital_comprometido', 'moeda')
            . "</b>, compromisso mínimo por investidor de <b>" . reg_fmt($d, 'compromisso_minimo', 'moeda') . "</b>. As integralizações ocorrem por "
            . "<b>chamadas de capital</b>, atendidas em até <b>" . reg_fmt($d, 'prazo_chamada') . " dias úteis</b> da notificação.");
        $h .= $A('Inadimplência do cotista', "O cotista inadimplente dispõe de prazo de cura; persistindo a mora, ficam <b>suspensos</b> seus direitos de voto, de transferência e de recebimento de amortizações, incidindo encargos (atualização pelo indexador, multa e juros de mora), podendo o administrador compensar valores com as amortizações devidas (Res. CVM 175, Anexo IV, art. 23).");
        $h .= $A('Períodos de investimento e desinvestimento', "Período de investimento: <b>" . reg_fmt($d, 'periodo_investimento') . "</b>. Período de desinvestimento: <b>" . reg_fmt($d, 'periodo_desinvestimento') . "</b>.");
        $h .= $A('Enquadramento e governança das investidas', "Manterá, no mínimo, <b>" . reg_fmt($d, 'enquadramento_min', 'percent')
            . "</b> do PL em ativos elegíveis (Anexo IV, art. 5º), com <b>participação no processo decisório</b> das companhias investidas (art. 6º); dívida não conversível ≤ 33% do capital subscrito."
            . (!empty($d['entidade_investimento']) ? " Qualifica-se como <b>entidade de investimento</b> (Lei 14.754/2023): sem come-cotas; IR de 15% na distribuição/amortização." : ""));
        if (!empty($d['tem_comite_investimento']) || !empty($d['tem_comite_avaliacao'])) {
            $com = []; if (!empty($d['tem_comite_investimento'])) $com[] = 'comitê de investimento'; if (!empty($d['tem_comite_avaliacao'])) $com[] = 'comitê de avaliação (valor justo)';
            $h .= $A('Comitês', "O fundo contará com " . implode(' e ', $com) . ", cujas competências constam de anexo a este regulamento.");
        }
        $h .= $A('Distribuição de resultados (waterfall)', "As distribuições observam a ordem: (i) <b>devolução do capital</b> integralizado; (ii) <b>retorno preferencial</b> aos cotistas ("
            . reg_fmt($d, 'hurdle', 'percent') . " a.a. / hurdle); (iii) devolução do capital usado para pagar a taxa de gestão; "
            . (!empty($d['catch_up']) ? "(iv) <b>catch-up</b> da gestora; (v) " : "(iv) ") . "rateio do excedente, com <b>carried interest de "
            . reg_fmt($d, 'carry', 'percent') . "</b> à gestora.");
    } else {
        $car = !empty($d['tem_carencia']) ? " Há carência de <b>" . reg_fmt($d, 'carencia_dias') . " dias</b> para resgate." : '';
        $h .= $A('Aplicação, resgate e liquidação', "Aplicação inicial mínima de <b>" . reg_fmt($d, 'aplicacao_minima', 'moeda') . "</b>; saldo mínimo de <b>"
            . reg_fmt($d, 'saldo_minimo', 'moeda') . "</b>. Cotização da aplicação em <b>" . reg_fmt($d, 'prazo_cotizacao_aplic') . "</b>; do resgate em <b>"
            . reg_fmt($d, 'prazo_cotizacao_resgate') . "</b>, com liquidação financeira em <b>" . reg_fmt($d, 'prazo_liquidacao_resgate')
            . "</b>. Horário de corte: <b>" . reg_fmt($d, 'horario_corte') . "</b>. É devida ao cotista <b>multa de 0,5% ao dia</b> sobre o valor do resgate em caso de atraso no pagamento, a cargo de quem der causa." . $car);
    }

    $h .= "<h3 style='font-size:1.05rem'>ANEXO — Da Classe — Capítulo VI — Dos Fatores de Risco</h3>";
    $riscoLista = !empty($d['fatores_risco']) ? 'Entre outros, aplicam-se os riscos de <b>' . reg_fmt($d, 'fatores_risco', 'lista') . '</b>. ' : '';
    $riscoCP = !empty($d['credito_privado']) ? 'Por aplicar mais de 50% em crédito privado, há risco de perda substancial do patrimônio. ' : '';
    $h .= $A('Fatores de risco', $riscoLista . $riscoCP
        . "As aplicações <b>não contam com garantia</b> do administrador, do gestor, de qualquer mecanismo de seguro ou do <b>Fundo Garantidor de Créditos – FGC</b>, podendo o cotista sofrer perdas, inclusive a totalidade do capital investido.");

    $h .= "<h3 style='font-size:1.05rem'>Disposições Gerais</h3>";
    $h .= $A('Assembleia, divulgação e voto', "As assembleias (geral e especial), a divulgação de cota e informes, o serviço de atendimento ao cotista (SAC) e a política de exercício de direito de voto observam a Resolução CVM 175 e os procedimentos do administrador.");
    $h .= "<p style='font-size:.72rem;color:#888;margin-top:14px'><i>Minuta gerada automaticamente pelo padrão da plataforma. "
        . "Sujeita a revisão jurídica e ao registro/protocolo na CVM antes de entrar em vigor.</i></p>";
    $h .= "</div>";
    return $h;
}

/**
 * Gera o SUPLEMENTO da subclasse (a subclasse segrega o PASSIVO da classe — Res. CVM 175:
 * varia público-alvo, aplicação mínima, taxas e prazos; NÃO altera a política de investimento).
 * $s = linha da subclasse (nome, publico_alvo, aplicacao_minima, taxa_adm, taxa_performance,
 * prazo_cotizacao, prazo_liquidacao, condominio, data_vigencia, regulamento_anexo).
 */
function reg_suplemento_subclasse_html(array $s, string $fundoNome = ''): string {
    $nome = e_html($s['nome'] ?? 'Subclasse');
    $pct = fn($v) => number_format((float)$v * 100, 4, ',', '.') . '%';
    $moe = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');
    $h = "<div class='regulamento'>";
    $h .= "<h2 style='text-align:center;margin-bottom:2px'>SUPLEMENTO DE SUBCLASSE</h2>";
    $h .= "<p style='text-align:center;margin-top:0'><b>$nome</b>"
        . ($fundoNome ? "<br><span style='font-size:.85rem;color:#555'>Subclasse de cotas de " . e_html($fundoNome) . "</span>" : '') . "</p>";
    $h .= "<p style='text-align:justify'>Este suplemento integra o regulamento da classe e disciplina, nos termos da "
        . "Resolução CVM 175, exclusivamente as características do <b>passivo</b> desta subclasse — sem alterar a política "
        . "de investimento, a carteira ou os limites de risco da classe, que lhe são comuns.</p>";
    $h .= "<h4 style='font-size:1rem;margin:12px 0 4px'>Art. 1º — Público-alvo</h4><p>Destina-se a <b>" . e_html($s['publico_alvo'] ?? '—') . "</b>.</p>";
    $h .= "<h4 style='font-size:1rem;margin:12px 0 4px'>Art. 2º — Aplicação e permanência</h4><p>Aplicação inicial mínima de <b>"
        . $moe($s['aplicacao_minima'] ?? 0) . "</b>. Condomínio <b>" . e_html($s['condominio'] ?? '—') . "</b>.</p>";
    $h .= "<h4 style='font-size:1rem;margin:12px 0 4px'>Art. 3º — Taxas</h4><p>Taxa de administração de <b>" . $pct($s['taxa_adm'] ?? 0)
        . " ao ano</b>" . ((float)($s['taxa_performance'] ?? 0) > 0 ? " e taxa de performance de <b>" . $pct($s['taxa_performance']) . "</b>" : '') . ".</p>";
    $h .= "<h4 style='font-size:1rem;margin:12px 0 4px'>Art. 4º — Cotização e liquidação</h4><p>Cotização do resgate em <b>"
        . e_html($s['prazo_cotizacao'] ?? '—') . "</b> e liquidação financeira em <b>" . e_html($s['prazo_liquidacao'] ?? '—') . "</b>.</p>";
    if (!empty($s['data_vigencia']))
        $h .= "<h4 style='font-size:1rem;margin:12px 0 4px'>Art. 5º — Vigência</h4><p>Início de vigência pretendido em <b>"
            . date('d/m/Y', strtotime($s['data_vigencia'])) . "</b>, após protocolo e registro na CVM.</p>";
    $h .= "<p style='font-size:.72rem;color:#888;margin-top:14px'><i>Suplemento gerado pelo padrão da plataforma; a subclasse "
        . "compartilha a carteira e a cota da sua classe. Sujeito a revisão jurídica e registro na CVM.</i></p></div>";
    return $h;
}

/** Colunas/tabelas para persistir regulamento em fundos/classes/subclasses. */
function ensure_regulamento(PDO $pdo): void {
    foreach ([
        "ALTER TABLE fundos ADD COLUMN IF NOT EXISTS reg_tipo VARCHAR(20) NULL",
        "ALTER TABLE fundos ADD COLUMN IF NOT EXISTS reg_dados MEDIUMTEXT NULL",
        "ALTER TABLE fundos ADD COLUMN IF NOT EXISTS reg_html MEDIUMTEXT NULL",
    ] as $sql) { try { $pdo->exec($sql); } catch (Throwable $e) {} }
    // classes (camada de registro; segregação contábil plena não está no piloto)
    $pdo->exec("CREATE TABLE IF NOT EXISTS classes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fundo_id INT NOT NULL,
        nome VARCHAR(150) NOT NULL,
        reg_tipo VARCHAR(20) NULL,
        classe_cvm VARCHAR(40) NULL,
        cnpj VARCHAR(20) NULL,
        status VARCHAR(20) DEFAULT 'Em registro',
        reg_dados MEDIUMTEXT NULL,
        reg_html MEDIUMTEXT NULL,
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_cl_fundo (fundo_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    foreach ([
        "ALTER TABLE subclasses ADD COLUMN IF NOT EXISTS reg_dados MEDIUMTEXT NULL",
        "ALTER TABLE subclasses ADD COLUMN IF NOT EXISTS reg_html MEDIUMTEXT NULL",
    ] as $sql) { try { $pdo->exec($sql); } catch (Throwable $e) {} }
}
