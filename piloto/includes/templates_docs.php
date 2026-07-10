<?php
// ============================================================
// TEMPLATES DE DOCUMENTOS DO FUNDO (minutas em conformidade — Res. CVM 175)
// Gera o conteúdo das minutas dos documentos da categoria "Fundo" a partir
// dos dados do próprio fundo, e grava em documentos_abertura.conteudo.
// Base legal lida do texto consolidado da Res. CVM 175 (Parte Geral + Anexo I):
//  regulamento arts. 48-49 + Anexo I 15-16; termo de adesão art. 29 (máx. 5.000
//  caracteres, 5 fatores de risco); política de investimento Anexo I art. 16;
//  lâmina (Suplemento B) art. 14 — só varejo; não-solidariedade art. 5º + CC 1.368-D
//  (Lei 14.754); taxas base 252; multa 0,5%/dia (art. 40, V).
// São MINUTAS — exigem revisão jurídica (dito no rodapé de cada documento).
// ============================================================

/** % a partir de fração (0.0008 -> "0,08%"). */
function tpl_pct($frac): string { return number_format(((float)$frac) * 100, 2, ',', '.') . '%'; }
function tpl_esc($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
/** Realça os campos a preencher [entre colchetes] como guia (âmbar, via <em>) — devem ser
 *  substituídos/removidos no documento final, junto com as observações. */
function tpl_realcar_campos(string $html): string {
    return preg_replace('/\[[^\[\]\r\n]{1,90}\]/u', '<em>$0</em>', $html);
}
/** Rodapé padrão das minutas. */
function tpl_rodape(): string {
    return '<p class="doc-rodape"><em>Minuta gerada automaticamente a partir dos dados do fundo, para agilizar a constituição. '
         . 'Não substitui a revisão de advogado de mercado de capitais nem o registro na CVM (Res. 175, arts. 8º–11). '
         . 'Os campos entre [colchetes] devem ser completados.</em></p>';
}
/** Fatores de risco típicos por categoria (para o termo de adesão e a lâmina). */
function tpl_riscos_categoria(string $classe): array {
    $c = mb_strtolower($classe);
    if (str_contains($c, 'ações') || str_contains($c, 'acoes'))
        return ['Risco de mercado (variação de preço das ações)', 'Risco de concentração', 'Risco de liquidez', 'Risco de mercado externo, se aplicável', 'Risco de contraparte'];
    if (str_contains($c, 'multi'))
        return ['Risco de mercado (juros, câmbio, ações)', 'Risco de derivativos e alavancagem', 'Risco de crédito', 'Risco de liquidez', 'Risco de contraparte'];
    if (str_contains($c, 'cambial'))
        return ['Risco de variação cambial', 'Risco de mercado externo', 'Risco de liquidez', 'Risco de contraparte', 'Risco de crédito'];
    return ['Risco de crédito (emissor/contraparte)', 'Risco de mercado (variação da taxa de juros)', 'Risco de liquidez', 'Risco de concentração', 'Risco de mercado externo, se aplicável'];
}

/** Cabeçalho comum dos documentos. */
function tpl_cabecalho(array $f, string $titulo): string {
    $nome = tpl_esc($f['nome']);
    $cnpj = tpl_esc($f['cnpj'] ?: '[CNPJ em registro]');
    return "<h2>$titulo</h2>\n<p><b>Fundo:</b> $nome &nbsp;·&nbsp; <b>CNPJ:</b> $cnpj &nbsp;·&nbsp; <b>Classe/Categoria:</b> "
         . tpl_esc($f['classe']) . " &nbsp;·&nbsp; <b>Público-alvo:</b> " . tpl_esc($f['publico_alvo']) . "</p>\n<hr>\n";
}

// ---------------------------------------------------------------- REGULAMENTO (minuta)
function tpl_regulamento(array $f): string {
    $resp = ($f['publico_alvo'] === 'Investidores em geral')
        ? 'limitada ao valor por ele subscrito' : 'limitada ao valor por ele subscrito';
    $h  = tpl_cabecalho($f, 'REGULAMENTO (MINUTA) — ' . tpl_esc($f['nome']));
    $h .= "<h3>Parte Geral (art. 48, §1º, Res. CVM 175)</h3><ol>";
    $h .= "<li><b>Prestadores essenciais.</b> Administrador fiduciário: [Instituição administradora], registrada na CVM (Res. CVM 21). Gestor de recursos: "
        . tpl_esc($f['gestora']) . " (Ato Declaratório CVM [nº]). Custodiante, escriturador e auditor independente identificados no Anexo da Classe.</li>";
    $h .= "<li><b>Responsabilidade dos prestadores.</b> Cada prestador responde, na sua esfera de atuação, por seus próprios atos e omissões (nos termos da Res. CVM 175), sem solidariedade automática, salvo dolo ou má-fé (Código Civil, art. 1.368-D, II — Lei 14.754/2023).</li>";
    $h .= "<li><b>Classes e despesas.</b> O fundo possui [uma/mais de uma] classe de cotas. As despesas comuns, se houver, são rateadas de forma verificável e sem transferência indevida de riqueza (art. 48, §1º, III e IV).</li>";
    $h .= "<li><b>Prazo de duração:</b> indeterminado.</li>";
    $h .= "<li><b>Taxa de administração:</b> " . tpl_pct($f['taxa_adm']) . " ao ano sobre o patrimônio líquido (base 252 dias úteis), com piso de R$ 100,00/mês. "
        . "<b>Taxa de gestão:</b> " . tpl_pct($f['taxa_gestao']) . " ao ano (base 252).</li>";
    $h .= "<li><b>Exercício social:</b> encerra em 31 de dezembro.</li>";
    $h .= "</ol>";
    $h .= "<h3>Anexo Descritivo da Classe (art. 48, §2º; Anexo I, arts. 15–16)</h3><ol>";
    $h .= "<li><b>Público-alvo:</b> " . tpl_esc($f['publico_alvo']) . ".</li>";
    $h .= "<li><b>Responsabilidade dos cotistas:</b> $resp (art. 18; sufixo \"Responsabilidade Limitada\" na denominação quando aplicável).</li>";
    $h .= "<li><b>Regime:</b> " . tpl_esc($f['condominio']) . ".</li>";
    $h .= "<li><b>Categoria:</b> " . tpl_esc($f['classe']) . ", com política de investimento aderente (detalhada em documento próprio e integrante deste anexo).</li>";
    $h .= "<li><b>Aplicação e resgate</b> (classe aberta): cotização e pagamento conforme o Anexo; o não pagamento do resgate no prazo sujeita o fundo à multa de <b>0,5% ao dia</b> em favor do cotista (art. 40, V), salvo hipóteses de barreira/fechamento.</li>";
    $h .= "<li><b>Barreiras a resgate / gestão de liquidez</b> e <b>procedimentos de liquidação da classe</b> (inclusive PL negativo — arts. 122–125) conforme parâmetros do Anexo.</li>";
    $h .= "<li><b>Taxa máxima de custódia:</b> [X]% ao ano do PL (base 252) — no arranjo com a administradora, absorvida (custo zero ao fundo). <b>Taxa de performance:</b> "
        . ((float)$f['taxa_performance'] > 0 ? tpl_pct($f['taxa_performance']) . " sobre o que exceder " . tpl_esc($f['benchmark']) . ", com linha d'água" : "não há") . ".</li>";
    $h .= "</ol>";
    return $h . tpl_rodape();
}

// ---------------------------------------------------------------- POLÍTICA DE INVESTIMENTO
function tpl_politica_investimento(array $f): string {
    $h  = tpl_cabecalho($f, 'POLÍTICA DE INVESTIMENTO DA CLASSE (MINUTA)');
    $h .= "<p>Parte integrante do anexo da classe (Res. CVM 175, Anexo I, art. 16). Limites mínimos obrigatórios:</p><ol>";
    $h .= "<li><b>Objetivo:</b> buscar retorno compatível com o benchmark <b>" . tpl_esc($f['benchmark']) . "</b>, na categoria " . tpl_esc($f['classe']) . ".</li>";
    $h .= "<li><b>% máximo em ativos de emissão do gestor ou de seu grupo econômico:</b> [__]% (art. 16, I).</li>";
    $h .= "<li><b>% máximo em cotas de fundos do gestor ou de partes relacionadas:</b> [__]% (art. 16, II).</li>";
    $h .= "<li><b>% máximo em ativos de um mesmo emissor:</b> [__]% (art. 16, III), observados os limites da categoria.</li>";
    $h .= "<li><b>% máximo em ativos no exterior:</b> [__]% (art. 16, IV).</li>";
    $h .= "<li><b>Exposição a risco de capital (margem bruta):</b> " . ((float)$f['taxa_performance'] > 0 || str_contains(mb_strtolower($f['classe']), 'multi') ? "permitida, limitada a [__]% do PL" : "não permitida") . " (art. 16, V).</li>";
    $h .= "<li><b>Derivativos:</b> [somente para proteção (hedge) / também para posicionamento], conforme a categoria.</li>";
    $h .= "</ol>";
    return $h . tpl_rodape();
}

// ---------------------------------------------------------------- TERMO DE ADESÃO E CIÊNCIA DE RISCO
function tpl_termo_adesao(array $f): string {
    $riscos = tpl_riscos_categoria($f['classe']);
    $h  = tpl_cabecalho($f, 'TERMO DE ADESÃO E CIÊNCIA DE RISCO');
    $h .= "<p>Nos termos do art. 29 da Res. CVM 175, o cotista, ao ingressar no fundo acima, DECLARA que:</p><ol>";
    $h .= "<li>teve acesso ao inteiro teor do <b>regulamento</b>, do <b>anexo da classe</b> e, se for o caso, do apêndice da subclasse investida;</li>";
    $h .= "<li>tem ciência dos <b>fatores de risco</b> a que a classe está exposta, com destaque para os cinco principais:<ul>";
    foreach (array_slice($riscos, 0, 5) as $r) $h .= "<li>" . tpl_esc($r) . "</li>";
    $h .= "</ul></li>";
    $h .= "<li>tem ciência de que <b>não há qualquer garantia contra eventuais perdas patrimoniais</b> que possam ser incorridas pela classe;</li>";
    $h .= "<li>tem ciência de que o <b>registro na CVM não implica</b>, por parte da autarquia, garantia de veracidade das informações nem julgamento sobre a qualidade do fundo, de seus prestadores ou das cotas;</li>";
    $h .= "<li>tem ciência de que a rentabilidade passada não representa garantia de resultados futuros.</li>";
    $h .= "</ol>";
    $h .= "<p>_____________________________________ , [data]. &nbsp;&nbsp; Cotista: ____________________________ &nbsp; CPF/CNPJ: ____________________</p>";
    $h .= "<p class=\"doc-nota\">Observação de conformidade: o documento definitivo deve respeitar o limite de <b>5.000 caracteres</b> (art. 29, §1º) e a linguagem do art. 47, §1º. Classes de responsabilidade ilimitada exigem, adicionalmente, o Termo do Suplemento A da Res. 175.</p>";
    return $h . tpl_rodape();
}

// ---------------------------------------------------------------- LÂMINA DE INFORMAÇÕES BÁSICAS (só varejo)
function tpl_lamina(array $f): string {
    $riscos = tpl_riscos_categoria($f['classe']);
    $h  = "<h2>LÂMINA DE INFORMAÇÕES ESSENCIAIS SOBRE " . tpl_esc(mb_strtoupper($f['nome'])) . "</h2>";
    $h .= "<p><em>Lâmina de informações básicas — Res. CVM 175, Anexo I, art. 14 e Suplemento B. Compare esta classe com outras da mesma categoria.</em></p>";
    $h .= "<p><b>Mês/ano de referência:</b> [mm/aaaa] · <b>CNPJ:</b> " . tpl_esc($f['cnpj'] ?: '[em registro]')
        . " · <b>Administrador:</b> [Instituição] · <b>Gestor:</b> " . tpl_esc($f['gestora']) . "</p>";
    $h .= "<h3>Público-alvo</h3><p>" . tpl_esc($f['publico_alvo']) . ".</p>";
    $h .= "<h3>Objetivo</h3><p>Buscar retorno compatível com " . tpl_esc($f['benchmark']) . ", na categoria " . tpl_esc($f['classe']) . ".</p>";
    $h .= "<h3>Política de investimento (resumo) e limites</h3><ul>"
        . "<li>Aplicação no exterior: até [__]%</li><li>Crédito privado: até [__]%</li>"
        . "<li>Concentração em um mesmo fundo: até [__]%</li><li>Derivativos apenas para proteção: [Sim/Não]</li>"
        . "<li>Alavancagem/margem: [Sim/Não]</li></ul>";
    $h .= "<h3>Condições de investimento</h3><ul>"
        . "<li>Taxa de administração: <b>" . tpl_pct($f['taxa_adm']) . " a.a.</b> (piso R$ 100/mês)</li>"
        . "<li>Taxa de gestão: " . tpl_pct($f['taxa_gestao']) . " a.a.</li>"
        . "<li>Taxa de performance: " . ((float)$f['taxa_performance'] > 0 ? tpl_pct($f['taxa_performance']) . " s/ " . tpl_esc($f['benchmark']) : "não há") . "</li>"
        . "<li>Taxa de entrada/saída: [não há]</li>"
        . "<li>Taxa total de despesas (estimada): [__]% a.a.</li>"
        . "<li>Aplicação inicial mínima: R$ [__] · Resgate: cotização [D+_] / pagamento [D+_]</li></ul>";
    $h .= "<h3>Riscos</h3><p>Escala de risco (1 a 5, atribuída pelo gestor): [_]. Principais riscos:</p><ul>";
    foreach (array_slice($riscos, 0, 5) as $r) $h .= "<li>" . tpl_esc($r) . "</li>";
    $h .= "</ul>";
    $h .= "<h3>Composição da carteira e rentabilidade</h3><p>PL: R$ [__] · 5 principais espécies de ativos: [__]. "
        . "Histórico de rentabilidade dos últimos 5 anos vs. " . tpl_esc($f['benchmark']) . ": [preencher] (ou simulação de desempenho, para estruturados).</p>";
    return $h . tpl_rodape();
}

// ---------------------------------------------------------------- MINUTA DE CONTRATO DE CUSTÓDIA
function tpl_contrato_custodia(array $f): string {
    $h  = tpl_cabecalho($f, 'CONTRATO DE PRESTAÇÃO DE SERVIÇOS DE CUSTÓDIA QUALIFICADA (MINUTA)');
    $h .= "<p><b>Partes:</b> [Custodiante], autorizado pela CVM (Res. CVM 32), e o fundo acima, representado por seu administrador.</p><ol>";
    $h .= "<li><b>Objeto:</b> guarda dos ativos financeiros da classe, liquidação de operações e controladoria de ativos, de forma independente e coordenada com o administrador.</li>";
    $h .= "<li><b>Segregação:</b> as atividades de custódia e controladoria são totalmente segregadas da gestão de recursos (Res. CVM 21, art. 30).</li>";
    $h .= "<li><b>Remuneração:</b> taxa máxima de custódia de [X]% a.a. do PL (base 252). <b>No arranjo com a administradora, a custódia é absorvida — custo zero para o fundo.</b></li>";
    $h .= "<li><b>Conciliação e reporte:</b> posição diária conciliada com o administrador; comunicação de divergências.</li>";
    $h .= "<li><b>Vigência e rescisão:</b> [prazo], com aviso prévio de [30] dias.</li>";
    $h .= "</ol>";
    return $h . tpl_rodape();
}

// ---------------------------------------------------------------- MINUTA DE CONTRATO DE AUDITORIA
function tpl_contrato_auditoria(array $f): string {
    $h  = tpl_cabecalho($f, 'CONTRATO DE AUDITORIA INDEPENDENTE (MINUTA)');
    $h .= "<p><b>Partes:</b> [Auditor independente registrado na CVM] e o fundo acima, por seu administrador.</p><ol>";
    $h .= "<li><b>Objeto:</b> auditoria das demonstrações contábeis da classe, emitindo parecer <b>ao menos uma vez por ano</b> ao fim do exercício social (Res. CVM 175, art. 69).</li>";
    $h .= "<li><b>Independência:</b> o auditor declara independência e ausência de dependência econômica que a comprometa; rotatividade conforme as normas do CFC/CVM.</li>";
    $h .= "<li><b>Prazo de entrega:</b> parecer em até 90 dias do encerramento do exercício, para envio à CVM.</li>";
    $h .= "<li><b>Honorários:</b> R$ [__] por exercício, encargo do fundo (art. 117).</li>";
    $h .= "</ol>";
    return $h . tpl_rodape();
}

// ---------------------------------------------------------------- MINUTA DE CONTRATO DE DISTRIBUIÇÃO
function tpl_contrato_distribuicao(array $f): string {
    $h  = tpl_cabecalho($f, 'CONTRATO DE DISTRIBUIÇÃO DE COTAS (MINUTA)');
    $h .= "<p><b>Partes:</b> [Distribuidor autorizado / intermediário] e o fundo acima, por seu administrador.</p><ol>";
    $h .= "<li><b>Objeto:</b> distribuição das cotas da classe pelo distribuidor autorizado. Assessores de investimento atuam apenas como <b>prepostos</b> do intermediário (Res. CVM 178), nunca como gestores.</li>";
    $h .= "<li><b>Taxa máxima de distribuição:</b> [__]% a.a., observado o teto do regulamento.</li>";
    $h .= "<li><b>Suitability, KYC e PLD/FT:</b> obrigações do distribuidor (Res. CVM 50), com identificação do beneficiário final.</li>";
    $h .= "</ol>";
    return $h . tpl_rodape();
}

// ================================================================
// MODELOS DA GESTORA (administrador de carteiras — Res. CVM 21/50)
// Diferente dos documentos do fundo: são da GESTORA (não do fundo),
// então vêm como ESQUELETO genérico (campos [entre colchetes]), usando
// só o nome da gestora. Não são auto-salvos por fundo — só download-modelo.
// ================================================================

/** Rodapé dos modelos da gestora. */
function tpl_rodape_gestora(): string {
    return '<p class="doc-rodape"><em>Modelo de referência da GESTORA (administrador de carteiras — Res. CVM 21/2021). '
         . 'Cada gestora é responsável pelo conteúdo do seu próprio documento: adapte à sua estrutura e submeta a revisão '
         . 'jurídica/compliance. Os campos entre [colchetes] devem ser completados.</em></p>';
}
/** Cabeçalho dos modelos da gestora. */
function tpl_cabecalho_gestora(array $f, string $titulo): string {
    $g = tpl_esc($f['gestora'] ?: '[Gestora]');
    return "<h2>$titulo</h2>\n<p><b>Gestora (administrador de carteiras):</b> $g &nbsp;·&nbsp; <b>CNPJ:</b> [CNPJ da gestora] "
         . "&nbsp;·&nbsp; <b>Ato Declaratório CVM:</b> [nº] &nbsp;·&nbsp; <b>Diretor responsável:</b> [nome]</p>\n<hr>\n";
}

// ---------------------------------------------------------------- FORMULÁRIO DE REFERÊNCIA (Res. CVM 21, Anexo E)
function tpl_formulario_referencia(array $f): string {
    $g = tpl_esc($f['gestora'] ?: '[Gestora]');
    $h  = tpl_cabecalho_gestora($f, 'FORMULÁRIO DE REFERÊNCIA — ADMINISTRADOR DE CARTEIRAS (MINUTA)');
    $h .= "<p><em>Estrutura conforme o <b>Anexo E</b> da Res. CVM 21/2021 (administrador de carteiras — pessoa jurídica). "
        . "Data-base: posições de <b>30 de dezembro</b> de [aaaa]. Atualizar em até 7 meses do encerramento do exercício e sempre que houver alteração relevante. "
        . "Subitens marcados como facultativos para o gestor de recursos (ex.: demonstrações financeiras, tesouraria/escrituração, risco de liquidez do fiduciário) podem ser assinalados 'não aplicável'.</em></p><ol>";
    $h .= "<li><b>Identificação dos responsáveis pelo conteúdo.</b> Diretor responsável pela gestão de recursos: [nome]. "
        . "Diretor responsável por controles internos/compliance, PLD/FT e gestão de riscos: [nome]. <b>Declaração assinada</b> de que reviram o formulário e de que ele é retrato verdadeiro, preciso e completo das informações.</li>";
    $h .= "<li><b>Histórico da empresa.</b> Constituição de $g em [data]; principais fatos dos últimos 5 anos [descrever].</li>";
    $h .= "<li><b>Recursos humanos.</b> Sócios: [__]; empregados: [__]; terceirizados: [__]. Qualificação da equipe-chave: [descrever].</li>";
    $h .= "<li><b>Auditores.</b> Auditor independente contratado: [nome/registro CVM] ou [não aplicável].</li>";
    $h .= "<li><b>Resiliência financeira.</b> Patrimônio líquido da gestora em [data]: R$ [__]; avaliação da capacidade de manter a atividade [descrever].</li>";
    $h .= "<li><b>Escopo das atividades.</b> Gestão discricionária de recursos de terceiros (fundos e/ou carteiras administradas); tipos de veículos e classes de ativos: [descrever]. Atividades terceirizadas: [administração fiduciária, custódia, controladoria, escrituração — se terceirizadas].</li>";
    $h .= "<li><b>Grupo econômico.</b> Controladores, controladas, coligadas e partes relacionadas: [organograma/descrição] ou [não há].</li>";
    $h .= "<li><b>Estrutura operacional e administrativa.</b> Áreas de gestão, risco, compliance/PLD e back office, com <b>segregação</b> das funções; sistemas utilizados: [descrever]. Fluxo de decisão de investimento e de enquadramento pré/pós-trade: [descrever]. (Item mais detalhado do Anexo E — subdivide-se em estrutura administrativa, comitês, diretores, gestão de recursos, controles internos, gestão de riscos e distribuição.)</li>";
    $h .= "<li><b>Remuneração da empresa.</b> Formas de remuneração: taxa de gestão e, quando houver, taxa de performance; política de <i>rebate</i>/conflitos: [descrever].</li>";
    $h .= "<li><b>Regras, procedimentos e controles internos.</b> Código de ética e conduta; política de gestão de riscos; política de PLD/FT (Res. CVM 50); rateio e alocação equitativa de ordens; política de negociação de valores mobiliários por sócios e empregados; tratamento de conflitos de interesse e operações com partes relacionadas.</li>";
    $h .= "<li><b>Contingências.</b> Processos judiciais, administrativos ou arbitrais relevantes envolvendo a gestora, seus controladores e diretores: [listar] ou [não há].</li>";
    $h .= "<li><b>Declarações adicionais do diretor responsável pela administração.</b> Declara, quanto aos últimos 5 anos: (a) não estar inabilitado/suspenso nem ter sofrido punição em processo administrativo da CVM/BCB/SUSEP/PREVIC; (b) não ter condenação por crime falimentar, prevaricação, suborno, concussão, peculato, lavagem de dinheiro, contra a economia popular, a ordem econômica, as relações de consumo, a fé pública, a propriedade pública ou o Sistema Financeiro Nacional, nem pena que vede o acesso a cargos públicos; (c) não estar impedido de administrar bens; (d) não estar incluído em cadastro de proteção ao crédito; (e) não constar de relação de comitentes inadimplentes de mercado organizado; (f) não ter títulos levados a protesto.</li>";
    $h .= "</ol>";
    return $h . tpl_rodape_gestora();
}

// ---------------------------------------------------------------- POLÍTICA DE GESTÃO DE RISCOS
function tpl_politica_riscos(array $f): string {
    $h  = tpl_cabecalho_gestora($f, 'POLÍTICA DE GESTÃO DE RISCOS (MINUTA)');
    $h .= "<p><em>Exigida do administrador de carteiras (Res. CVM 21) e das Regras ANBIMA de Administração de Recursos de Terceiros. Aprovada pela diretoria.</em></p><ol>";
    $h .= "<li><b>Objetivo e abrangência.</b> Estrutura de identificação, avaliação, monitoramento e controle dos riscos das carteiras e fundos sob gestão.</li>";
    $h .= "<li><b>Governança e independência.</b> A gestão de riscos é conduzida por diretor responsável (art. 4º, Res. 21) com <b>independência</b>: reporta-se diretamente à alta administração/sócios e tem <b>prerrogativa de veto (sem direito a voto) nos comitês de investimento</b>, sem subordinação à mesa de gestão.</li>";
    $h .= "<li><b>Riscos monitorados.</b> Mercado; liquidez (ativo e passivo/resgates); crédito e <b>contraparte</b>; concentração (emissor, setor, contraparte); operacional; derivativos e alavancagem.</li>";
    $h .= "<li><b>Metodologias e métricas.</b> Value at Risk (VaR) [paramétrico/histórico], testes de estresse, volatilidade/drawdown, análise de liquidez (desmontagem × resgate), exposição/alavancagem e limites por mandato/classe. Parâmetros: [descrever].</li>";
    $h .= "<li><b>Escala de risco (1 a 5).</b> O gestor classifica a escala de risco de cada classe (1 = menor, 5 = maior) segundo critérios (juros, índices de preços, câmbio, bolsa, crédito, liquidez, commodities) e a tabela por categoria ANBIMA: [preencher tabela].</li>";
    $h .= "<li><b>Limites e enquadramento.</b> Limites por fundo/classe (mandato, regulamento e norma) verificados <b>pré-trade</b> (barra a operação que violaria o mandato) e <b>pós-trade</b> (diariamente).</li>";
    $h .= "<li><b>Contrapartes.</b> Só se opera balcão contra contraparte previamente habilitada (KYC, análise de crédito e <b>limite de exposição</b>), monitorando a exposição contra o limite.</li>";
    $h .= "<li><b>Desenquadramento.</b> <b>Ativo</b> (por ação do gestor): plano de ação comunicado à CVM se persistir por <b>10 dias úteis</b> consecutivos. <b>Passivo</b> (por fatores de mercado): explicações à CVM se persistir por <b>15 dias úteis</b> consecutivos.</li>";
    $h .= "<li><b>Gerenciamento de risco de liquidez (GRL).</b> Para classes abertas: critérios do lado do ativo (liquidez/haircut) e do passivo (segmentação de cotistas, concentração, <b>Matriz de Probabilidade de Resgates ANBIMA</b>), vértices (1, 2, 3, 4, 5, 21, 42, 63 dias úteis) e teste de estresse ao menos mensal. [Não aplicável a fundos fechados/exclusivos.]</li>";
    $h .= "<li><b>Reporte.</b> Relatórios de risco com periodicidade [diária/mensal] à diretoria e ao administrador fiduciário.</li>";
    $h .= "<li><b>Revisão.</b> Esta política é revista, no mínimo, <b>a cada 2 anos</b> (ou antes, por alteração regulatória); os parâmetros de liquidez, anualmente.</li>";
    $h .= "</ol>";
    return $h . tpl_rodape_gestora();
}

// ---------------------------------------------------------------- POLÍTICA DE PLD/FT (Res. CVM 50)
function tpl_politica_pld(array $f): string {
    $h  = tpl_cabecalho_gestora($f, 'POLÍTICA DE PLD/FT/FPADM — PREVENÇÃO À LAVAGEM DE DINHEIRO, AO FINANCIAMENTO DO TERRORISMO E DA PROLIFERAÇÃO DE ARMAS DE DESTRUIÇÃO EM MASSA (MINUTA)');
    $h .= "<p><em>Base legal: Res. CVM 50/2021; Lei 9.613/1998; Lei 13.810/2019 (sanções do CSNU); Guia ANBIMA de PLD. Aprovada pela diretoria; revisão mínima a cada 2 anos.</em></p><ol>";
    $h .= "<li><b>Objetivo e governança.</b> Prevenir o uso da gestora para lavagem de dinheiro, financiamento do terrorismo e da proliferação de armas de destruição em massa. Diretor estatutário responsável perante a CVM (art. 8º): [nome], com acesso amplo e irrestrito; substituição comunicada à CVM.</li>";
    $h .= "<li><b>Abordagem Baseada em Risco (ABR).</b> Classificação em risco baixo/médio/alto de seis frentes: serviços, produtos, canais de distribuição, clientes, prestadores relevantes e agentes/ambientes de negociação e registro.</li>";
    $h .= "<li><b>Avaliação Interna de Risco / Relatório Anual (arts. 20-23).</b> Relatório anual à alta administração <b>até o último dia útil de abril</b>, com a segmentação de risco, ameaças/vulnerabilidades, <b>tabela do nº de operações atípicas detectadas, de análises e de comunicações ao COAF</b>, medidas de mitigação e indicadores de efetividade.</li>";
    $h .= "<li><b>Conheça seu Cliente (KYC) e beneficiário final.</b> Identificação e qualificação do cliente e do <b>beneficiário final (UBO)</b> — pessoa natural com <b>influência significativa (titular de 25%+ do capital)</b> ou controle de fato —, origem dos recursos e compatibilidade patrimonial; atenção reforçada a PEP. Dispensa de verificação de UBO nas hipóteses da norma (companhia aberta; fundos não exclusivos com gestor discricionário; instituições financeiras; seguradoras/previdência/RPPS; certos investidores não residentes).</li>";
    $h .= "<li><b>Conheça seu parceiro, prestador e funcionário.</b> Diligência e classificação ABR dos prestadores relevantes, com <b>cláusula contratual de observância da Res. CVM 50</b> e uso do <b>Questionário de Due Diligence (QDD) ANBIMA</b>; termo de compromisso e monitoramento de conduta dos colaboradores.</li>";
    $h .= "<li><b>Monitoramento e seleção de operações.</b> Sinais de alerta: atipicidade de valor/frequência, incompatibilidade patrimonial, fracionamento, partes relacionadas, jurisdições de risco.</li>";
    $h .= "<li><b>Análise e comunicação ao COAF.</b> Alertas analisados em até [45] dias; a comunicação ao COAF é feita em <b>até 24 horas contadas da conclusão da análise</b> que objetivamente permita fazê-lo. <b>Declaração negativa</b> anual (não ocorrência) até o último dia útil de abril.</li>";
    $h .= "<li><b>Financiamento do terrorismo e sanções do CSNU.</b> Cumprimento imediato das resoluções sancionatórias do Conselho de Segurança da ONU (Lei 13.810/2019): <b>indisponibilidade imediata de ativos, sem aviso prévio</b>, com comunicação ao MJSP, à CVM e ao COAF.</li>";
    $h .= "<li><b>Registros.</b> Cadastros e registros mantidos por, no mínimo, <b>5 anos</b> — contados do encerramento do relacionamento (cadastro) e da data do evento (operações/comunicações), prorrogáveis por determinação da CVM.</li>";
    $h .= "<li><b>Treinamento e revisão.</b> Capacitação periódica dos colaboradores; revisão desta política ao menos a cada 2 anos.</li>";
    $h .= "</ol>";
    return $h . tpl_rodape_gestora();
}

// ---------------------------------------------------------------- CONTRATO SOCIAL DA GESTORA (modelo)
function tpl_contrato_social(array $f): string {
    $g = tpl_esc($f['gestora'] ?: '[Gestora]');
    $h  = tpl_cabecalho_gestora($f, 'CONTRATO SOCIAL — SOCIEDADE GESTORA DE RECURSOS (MODELO)');
    $h .= "<p><em>Modelo de contrato social de sociedade empresária limitada cujo objeto é a gestão de recursos de terceiros. "
        . "É a peça que CONSTITUI a gestora — deve ser registrada na Junta Comercial e é pré-requisito ao pedido de autorização como "
        . "administrador de carteiras (Res. CVM 21). O ato declaratório da CVM não se preenche aqui: é emitido pela CVM ao final do processo.</em></p><ol>";
    $h .= "<li><b>Denominação e tipo.</b> A sociedade gira sob a denominação <b>$g Ltda.</b>, sociedade empresária limitada regida por este contrato e pela legislação aplicável.</li>";
    $h .= "<li><b>Sede.</b> [endereço completo, cidade/UF, CEP], podendo abrir filiais mediante alteração contratual.</li>";
    $h .= "<li><b>Objeto social.</b> Exercício <b>exclusivo</b> da <b>administração de carteiras de valores mobiliários, na categoria gestor de recursos</b>, nos termos da "
        . "<b>Resolução CVM 21/2021</b>, exclusivamente sobre títulos e valores mobiliários <b>de titularidade de terceiros</b>. A sociedade não exercerá atividade privativa de "
        . "instituição financeira, nem de administração fiduciária ou custódia, sem as respectivas autorizações. (CNAE 6630-4/00 — administração de fundos por contrato ou comissão; não admite MEI.)</li>";
    $h .= "<li><b>Prazo.</b> Indeterminado, iniciando as atividades reguladas somente após a autorização da CVM.</li>";
    $h .= "<li><b>Capital social.</b> R$ [__], dividido em [__] quotas de R$ 1,00 cada, subscritas e integralizadas, assim distribuídas: [Sócio 1] — [__]%; [Sócio 2] — [__]%.</li>";
    $h .= "<li><b>Responsabilidade dos sócios.</b> Restrita ao valor das quotas, respondendo todos solidariamente pela integralização do capital (Código Civil, art. 1.052).</li>";
    $h .= "<li><b>Administração e responsáveis perante a CVM.</b> A sociedade é administrada por [administrador(es)], com poderes de representação. Ficam designados: "
        . "<b>diretor responsável pela gestão de recursos</b> [nome] (pessoa natural credenciada na CVM); <b>diretor responsável por controles internos e compliance</b> [nome]; "
        . "<b>diretor responsável pela gestão de riscos</b> [nome]; e, se houver distribuição de cotas próprias, <b>diretor responsável pela distribuição</b> [nome]. "
        . "As funções podem se acumular, <b>vedado</b> ao diretor de gestão acumular risco ou compliance (segregação — Res. CVM 21 e 50).</li>";
    $h .= "<li><b>Segregação de atividades (<i>chinese wall</i>).</b> As atividades de gestão, risco e compliance observam segregação física e de informação, e a administração respeita as restrições e vedações dos normativos da CVM aplicáveis.</li>";
    $h .= "<li><b>Cessão de quotas.</b> A cessão a terceiros depende de anuência de sócios que representem [__]% do capital, assegurado o direito de preferência.</li>";
    $h .= "<li><b>Exercício social e resultados.</b> Encerra em 31 de dezembro; lucros ou prejuízos apurados em balanço e atribuídos na proporção das quotas.</li>";
    $h .= "<li><b>Deliberações.</b> Tomadas por sócios que representem a maioria do capital, salvo quórum legal diverso.</li>";
    $h .= "<li><b>Retirada, exclusão e falecimento de sócio:</b> apuração de haveres com base em balanço especial na data do evento.</li>";
    $h .= "<li><b>Declaração dos administradores.</b> Não estão impedidos por lei especial, condenação criminal ou outro impedimento de exercer a administração (Código Civil, art. 1.011, §1º).</li>";
    $h .= "<li><b>Foro.</b> Comarca de [cidade/UF] para dirimir questões deste contrato.</li>";
    $h .= "</ol>";
    $h .= "<p>[Cidade/UF], [data]. &nbsp;&nbsp; Sócios: ____________________________ &nbsp;&nbsp; ____________________________</p>";
    return $h . tpl_rodape_gestora();
}

// ---------------------------------------------------------------- CÓDIGO DE ÉTICA E CONDUTA
function tpl_codigo_etica(array $f): string {
    $h  = tpl_cabecalho_gestora($f, 'CÓDIGO DE ÉTICA E CONDUTA (MINUTA)');
    $h .= "<p><em>Documento obrigatório do administrador de carteiras (Res. CVM 21, arts. 16-II e 18). Aprovado pela diretoria; revisão anual.</em></p><ol>";
    $h .= "<li><b>Base legal.</b> Res. CVM 21, 50 (PLD/FT) e 175; Lei 9.613/1998 (lavagem); Lei 12.846/2013 (anticorrupção); Códigos ANBIMA (Ética e de Administração e Gestão de Recursos de Terceiros).</li>";
    $h .= "<li><b>Abrangência.</b> Aplica-se a todos os <b>Colaboradores</b> — sócios, administradores, empregados, estagiários e prestadores relevantes.</li>";
    $h .= "<li><b>Dever fiduciário.</b> Atuar com boa-fé, diligência e lealdade, fazendo prevalecer sempre o <b>interesse do cliente</b> sobre o interesse próprio.</li>";
    $h .= "<li><b>Informação privilegiada e confidencialidade.</b> Vedação ao uso de informação relevante não pública (<i>insider trading</i>); sigilo sobre carteiras e clientes. O detalhamento operacional mora na Política de Investimentos Pessoais e no Manual de Controles Internos.</li>";
    $h .= "<li><b>Conflitos de interesse e operações entre fundos ligados.</b> Identificação, comunicação e tratamento; vedação a operações que prejudiquem o cliente em benefício da gestora ou de pessoa ligada.</li>";
    $h .= "<li><b>Segregação de funções (<i>chinese wall</i>).</b> Barreiras de informação entre gestão, risco e compliance; independência das áreas de controle.</li>";
    $h .= "<li><b>Presentes, brindes e <i>soft dollar</i>.</b> Vedação a vantagens que possam influenciar decisões; brindes institucionais limitados a R$ [750]. Benefícios de corretoras (soft dollar) revertem em favor dos fundos.</li>";
    $h .= "<li><b>PLD/FT e anticorrupção.</b> Dever de prevenir e comunicar indícios de lavagem, financiamento do terrorismo e corrupção, na forma da Política de PLD/FT e da legislação anticorrupção.</li>";
    $h .= "<li><b>Comunicação externa, imprensa e redes sociais.</b> Manifestação pública em nome da gestora depende de autorização; vedado divulgar posições ou informações de clientes.</li>";
    $h .= "<li><b>Ambiente de trabalho.</b> Respeito, não discriminação e vedação a assédio de qualquer natureza.</li>";
    $h .= "<li><b>Canal de denúncias e sanções.</b> Meio para reporte (inclusive anônimo) de violações e medidas disciplinares.</li>";
    $h .= "<li><b>Vigência, revisão e adesão.</b> Vigente a partir da aprovação; revisão anual. <b>Anexo — Termo de Recebimento e Compromisso</b> assinado por cada Colaborador.</li>";
    $h .= "</ol>";
    return $h . tpl_rodape_gestora();
}

// ---------------------------------------------------------------- POLÍTICA DE RATEIO E DIVISÃO DE ORDENS
function tpl_politica_rateio(array $f): string {
    $h  = tpl_cabecalho_gestora($f, 'POLÍTICA DE RATEIO E DIVISÃO DE ORDENS (MINUTA)');
    $h .= "<p><em>Obrigatória para o gestor de recursos (Res. CVM 21, art. 16-VII; dispensada apenas do administrador fiduciário puro). Aprovada pela diretoria; revisão anual.</em></p><ol>";
    $h .= "<li><b>Objetivo.</b> Assegurar alocação <b>justa, equitativa e verificável</b> das ordens entre os fundos e carteiras, sem transferência indevida de riqueza.</li>";
    $h .= "<li><b>Critério de alocação.</b> Proporcional ao <b>patrimônio líquido/mandato</b> de cada carteira e aos limites do respectivo regulamento; em ordens agregadas (<i>bunching</i>) executadas a vários preços, aplica-se o <b>preço médio</b> a todas as carteiras.</li>";
    $h .= "<li><b>Tipos de ordem.</b> Ordens de <b>enquadramento</b> (aplicação/resgate de caixa) são alocadas apenas ao veículo movimentado; ordens de <b>estratégia</b> (discricionárias) são rateadas entre os veículos de mesma estratégia.</li>";
    $h .= "<li><b>Validação prévia de risco.</b> A área de Risco valida a ordem contra os limites do fundo <b>antes</b> da execução.</li>";
    $h .= "<li><b>Melhor execução (<i>best execution</i>).</b> Busca do melhor resultado global para o cliente (custo, preço, liquidez e probabilidade de execução), não apenas o menor custo.</li>";
    $h .= "<li><b>Vedação a <i>cherry-picking</i>.</b> Proibido direcionar as melhores execuções a carteiras específicas (inclusive de sócios/ligados); em execução parcial, o rateio pró-rata aplica-se também ao lote executado.</li>";
    $h .= "<li><b>Exceções.</b> Qualquer desvio é justificado e aprovado em conjunto por <b>Gestão e Compliance</b>.</li>";
    $h .= "<li><b>Registro e guarda.</b> Alocações e divergências registradas em sistema e guardadas por, no mínimo, <b>5 anos</b> (Res. CVM 21, art. 34).</li>";
    $h .= "<li><b>Monitoramento e revisão.</b> Compliance monitora a aderência; revisão anual.</li>";
    $h .= "</ol>";
    return $h . tpl_rodape_gestora();
}

// ---------------------------------------------------------------- POLÍTICA DE INVESTIMENTOS PESSOAIS
function tpl_politica_invest_pessoais(array $f): string {
    $h  = tpl_cabecalho_gestora($f, 'POLÍTICA DE INVESTIMENTOS PESSOAIS (MINUTA)');
    $h .= "<p><em>Política de negociação de valores mobiliários (Res. CVM 21, art. 16-V). Aprovada pela diretoria; revisão anual.</em></p><ol>";
    $h .= "<li><b>Pessoas vinculadas.</b> Sócios, diretores, empregados e demais pessoas ligadas na forma da <b>Res. CVM 35, art. 2º, XII</b> (inclusive cônjuge/companheiro, filhos menores e pessoas jurídicas controladas), além da <b>própria gestora</b>.</li>";
    $h .= "<li><b>Regime recomendado.</b> Renda variável apenas por meio de <b>fundos</b> (próprios ou de terceiros), vedada a compra direta de ações/derivativos e o <i>day trade</i> — o que simplifica o controle na gestora pequena. [Ajuste conforme a estrutura.]</li>";
    $h .= "<li><b>Vedações.</b> Usar informação confidencial, operar por interposta pessoa, operar junto com o cliente e <b>antecipar-se às ordens dos fundos (<i>front-running</i>)</b>.</li>";
    $h .= "<li><b>Lista Restrita e Lista Privilegiada.</b> Ativos com informação relevante ou posição dos fundos entram em lista de <b>bloqueio</b> para operações pessoais.</li>";
    $h .= "<li><b>Pré-aprovação (<i>pre-clearance</i>).</b> Operações pessoais sujeitas a autorização prévia do Compliance quando fora do regime de fundos.</li>";
    $h .= "<li><b>Período de bloqueio.</b> Janela de restrição (ex.: <b>10 dias</b>) após aporte em fundo próprio e em torno de operações relevantes. Posições pré-existentes ao ingresso podem ser mantidas; novas aquisições seguem esta política.</li>";
    $h .= "<li><b>Declaração e comprovação.</b> Declaração periódica dos investimentos pessoais e entrega de <b>extratos das corretoras</b> ao Compliance; posse de informação confidencial comunicada em até <b>1 dia útil</b>. <b>Anexo — Declaração de Investimentos Pessoais</b> (ativo, emissor, quantidade, valor, data).</li>";
    $h .= "<li><b>Monitoramento e sanções.</b> Compliance verifica a aderência; violações sujeitam a medidas disciplinares.</li>";
    $h .= "<li><b>Revisão.</b> Anual.</li>";
    $h .= "</ol>";
    return $h . tpl_rodape_gestora();
}

// ---------------------------------------------------------------- PLANO DE CONTINUIDADE DE NEGÓCIOS
function tpl_plano_continuidade(array $f): string {
    $h  = tpl_cabecalho_gestora($f, 'PLANO DE CONTINUIDADE DE NEGÓCIOS (MINUTA)');
    $h .= "<p><em>Decorre dos controles internos da Res. CVM 21 (arts. 22-24) e é <b>exigido formalmente pelas Regras ANBIMA</b> (a Res. 21 não o nomeia expressamente). Considera o Guia ANBIMA de Cibersegurança e a LGPD. Aprovado pela diretoria; revisão mínima a cada 24 meses.</em></p><ol>";
    $h .= "<li><b>Objetivo e abrangência.</b> Garantir a continuidade das funções críticas de gestão, risco e compliance em contingências, inclusive quanto a prestadores relevantes.</li>";
    $h .= "<li><b>Equipe de contingência.</b> Papéis nomeados e gatilho de acionamento — coordenação: <b>Diretor de Compliance</b> [nome]; suplentes [nomes]; quem decide acionar e quem comunica: [definir].</li>";
    $h .= "<li><b>Cenários cobertos.</b> Falha de sistemas/dados e cibersegurança; indisponibilidade da sede; perda de pessoa-chave; falha de fornecedor crítico de TI.</li>";
    $h .= "<li><b>Backup e recuperação.</b> Backup <b>diário</b> (RPO ≈ 24h) e retomada das funções críticas <b>no mesmo dia útil</b> (RTO ≈ mesmo dia). [Calibrar à infraestrutura real — não prometer métricas inexequíveis.]</li>";
    $h .= "<li><b>Contingência operacional.</b> Trabalho remoto (<i>home office</i>) e sistemas em nuvem como estratégia primária.</li>";
    $h .= "<li><b>Sucessão de funções-chave.</b> Substitutos designados para gestão, risco e compliance.</li>";
    $h .= "<li><b>Comunicação a terceiros.</b> Protocolo de comunicação, em contingência, a clientes, administrador fiduciário, custodiante e corretoras.</li>";
    $h .= "<li><b>Testes e treinamento.</b> Teste <b>anual</b> do plano com registro em relatório; treinamento e divulgação aos colaboradores.</li>";
    $h .= "<li><b>Análise de Impacto no Negócio (BIA) — Anexo.</b> Matriz por evento: probabilidade × impacto × tempo tolerável × ação mitigatória.</li>";
    $h .= "<li><b>Revisão e guarda.</b> Revisão a cada 24 meses; registros guardados por 5 anos.</li>";
    $h .= "</ol>";
    return $h . tpl_rodape_gestora();
}

/**
 * Mapa nome-do-checklist → função geradora. Documentos do FUNDO (Res. 175) têm minuta
 * preenchida com os dados do fundo; documentos da GESTORA (Formulário de Referência e
 * políticas, Res. 21/50) têm modelo/esqueleto genérico. Os demais (estatuto, ato
 * declaratório, certidões, identidade) são anexos externos (upload) — retornam null.
 */
function tpl_gerador_por_nome(string $nome): ?callable {
    $n = mb_strtolower($nome);
    if (str_contains($n, 'regulamento'))       return 'tpl_regulamento';
    if ((str_contains($n, 'política de invest') || str_contains($n, 'politica de invest')) && !str_contains($n, 'pessoa')) return 'tpl_politica_investimento';
    if (str_contains($n, 'termo de adesão') || str_contains($n, 'termo de adesao')) return 'tpl_termo_adesao';
    if (str_contains($n, 'lâmina') || str_contains($n, 'lamina')) return 'tpl_lamina';
    if (str_contains($n, 'custódia') || str_contains($n, 'custodia')) return 'tpl_contrato_custodia';
    if (str_contains($n, 'auditoria'))          return 'tpl_contrato_auditoria';
    if (str_contains($n, 'distribuição') || str_contains($n, 'distribuicao')) return 'tpl_contrato_distribuicao';
    // documentos da gestora (modelo/esqueleto):
    if (str_contains($n, 'formulário de referência') || str_contains($n, 'formulario de referencia')) return 'tpl_formulario_referencia';
    if (str_contains($n, 'gestão de risco') || str_contains($n, 'gestao de risco')) return 'tpl_politica_riscos';
    if (str_contains($n, 'pld'))                return 'tpl_politica_pld';
    if (str_contains($n, 'estatuto') || str_contains($n, 'social consolidado') || str_contains($n, 'contrato social')) return 'tpl_contrato_social';
    if (str_contains($n, 'ética') || str_contains($n, 'etica') || str_contains($n, 'conduta')) return 'tpl_codigo_etica';
    if (str_contains($n, 'rateio') || str_contains($n, 'divisão de ordens') || str_contains($n, 'divisao de ordens')) return 'tpl_politica_rateio';
    if (str_contains($n, 'investimentos pessoais') || str_contains($n, 'investimento pessoal')) return 'tpl_politica_invest_pessoais';
    if (str_contains($n, 'continuidade'))      return 'tpl_plano_continuidade';
    return null;
}

/**
 * Gera e grava as minutas dos documentos do fundo em documentos_abertura.conteudo.
 * Percorre os documentos já criados (categoria 'Fundo'); para os que têm gerador,
 * preenche o conteúdo e marca status 'Recebido' (minuta disponível). Idempotente.
 */
/** Carrega um documento se o usuário pode vê-lo (admin: qualquer; gestor: só dos seus fundos). */
function documento_para_usuario(PDO $pdo, array $u, int $id): ?array {
    $st = $pdo->prepare("SELECT d.*, f.nome AS fundo_nome FROM documentos_abertura d JOIN fundos f ON f.id=d.fundo_id WHERE d.id=?");
    $st->execute([$id]);
    $d = $st->fetch();
    if (!$d) return null;
    if (($u['perfil'] ?? '') === 'admin') return $d;
    $fids = array_map(fn($x) => (int)$x['id'], fundos_do_usuario($pdo, $u));
    return in_array((int)$d['fundo_id'], $fids, true) ? $d : null;
}

// ================================================================
// CONVERSÃO HTML → .docx (Word), sem biblioteca externa.
// Um .docx é um ZIP com XML (OOXML). Convertemos a minuta (h2/h3/p/hr/ol/ul/li/b/em)
// em WordprocessingML para que o usuário edite no Word/LibreOffice. Requer ext-zip e
// ext-dom (presentes no XAMPP e na Locaweb); se faltarem, retorna null (fallback .doc).
// ================================================================

function docx_esc(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8'); }

/** Um run de texto com formatação (b/i/sz em meio-pontos). */
function docx_run(array $r): string {
    $rpr = '';
    if (!empty($r['b'])) $rpr .= '<w:b/>';
    if (!empty($r['i']) || !empty($r['note'])) $rpr .= '<w:i/>';
    if (!empty($r['note'])) $rpr .= '<w:color w:val="B7791F"/>';   // notas/OBS ao gestor em âmbar
    $sz = $r['sz'] ?? 22;
    $rpr .= '<w:sz w:val="' . $sz . '"/><w:szCs w:val="' . $sz . '"/>';
    return '<w:r><w:rPr>' . $rpr . '</w:rPr><w:t xml:space="preserve">' . docx_esc($r['t']) . '</w:t></w:r>';
}

/** Um parágrafo com runs e opções (spacing/indent/borda inferior p/ <hr>). */
function docx_par(array $runs, array $o = []): string {
    $ppr = '<w:spacing w:before="' . ($o['before'] ?? 40) . '" w:after="' . ($o['after'] ?? 80) . '"/>';
    if (!empty($o['ind']))    $ppr .= '<w:ind w:left="' . $o['ind'] . '"/>';
    if (!empty($o['border'])) $ppr .= '<w:pBdr><w:bottom w:val="single" w:sz="6" w:space="1" w:color="999999"/></w:pBdr>';
    $r = '';
    foreach ($runs as $run) $r .= docx_run($run);
    return '<w:p><w:pPr>' . $ppr . '</w:pPr>' . $r . '</w:p>';
}

/** Coleta runs inline (texto + b/strong/em/i), ignorando listas aninhadas. */
function docx_inline_runs(DOMNode $node, array $inh = []): array {
    $runs = [];
    foreach ($node->childNodes as $ch) {
        if ($ch->nodeType === XML_TEXT_NODE) {
            $t = preg_replace('/\s+/', ' ', $ch->nodeValue);
            if (trim($t) !== '') $runs[] = $inh + ['t' => $t];
        } elseif ($ch->nodeType === XML_ELEMENT_NODE) {
            $tag = strtolower($ch->nodeName);
            if ($tag === 'ul' || $tag === 'ol') continue;   // bloco, tratado à parte
            $ni = $inh;
            if ($tag === 'b' || $tag === 'strong') $ni['b'] = true;
            if ($tag === 'em')                     $ni['note'] = true;   // nota/OBS ao gestor
            if ($tag === 'i')                      $ni['i'] = true;       // termo estrangeiro inline
            $runs = array_merge($runs, docx_inline_runs($ch, $ni));
        }
    }
    return $runs;
}

/** Emite os parágrafos de uma lista (numerada/marcadores), com aninhamento. */
function docx_list(DOMElement $list, bool $ordered, int $level, array &$paras): void {
    $i = 0;
    foreach ($list->childNodes as $li) {
        if ($li->nodeType !== XML_ELEMENT_NODE || strtolower($li->nodeName) !== 'li') continue;
        $i++;
        $prefix = $ordered ? ($i . '. ') : '• ';
        $runs = array_merge([['t' => $prefix, 'sz' => 22]], docx_inline_runs($li, ['sz' => 22]));
        $paras[] = docx_par($runs, ['ind' => 360 + $level * 360, 'after' => 60]);
        foreach ($li->childNodes as $c) {
            if ($c->nodeType === XML_ELEMENT_NODE && in_array(strtolower($c->nodeName), ['ul', 'ol'], true)) {
                docx_list($c, strtolower($c->nodeName) === 'ol', $level + 1, $paras);
            }
        }
    }
}

/** Monta um ZIP (entradas "store", sem compressão) em memória — não depende de ext-zip. */
function docx_zip_store(array $files): string {
    $local = ''; $central = ''; $offset = 0;
    $dosTime = 0;                                   // 00:00
    $dosDate = ((2025 - 1980) << 9) | (1 << 5) | 1; // 2025-01-01 (fixo, determinístico)
    foreach ($files as $name => $data) {
        $crc = crc32($data); $len = strlen($data); $nlen = strlen($name);
        $local .= "PK\x03\x04" . pack('v', 20) . pack('v', 0) . pack('v', 0)
                . pack('v', $dosTime) . pack('v', $dosDate)
                . pack('V', $crc) . pack('V', $len) . pack('V', $len)
                . pack('v', $nlen) . pack('v', 0) . $name . $data;
        $central .= "PK\x01\x02" . pack('v', 20) . pack('v', 20) . pack('v', 0) . pack('v', 0)
                 . pack('v', $dosTime) . pack('v', $dosDate)
                 . pack('V', $crc) . pack('V', $len) . pack('V', $len)
                 . pack('v', $nlen) . pack('v', 0) . pack('v', 0) . pack('v', 0)
                 . pack('v', 0) . pack('V', 0) . pack('V', $offset) . $name;
        $offset += 30 + $nlen + $len;
    }
    $n = count($files);
    $end = "PK\x05\x06" . pack('v', 0) . pack('v', 0) . pack('v', $n) . pack('v', $n)
         . pack('V', strlen($central)) . pack('V', strlen($local)) . pack('v', 0);
    return $local . $central . $end;
}

/** Converte a minuta (fragmento HTML) em bytes de um arquivo .docx, ou null se indisponível. */
function docx_from_html(string $html): ?string {
    if (!class_exists('DOMDocument')) return null;
    $html = tpl_realcar_campos($html);   // campos [entre colchetes] em âmbar (guia)
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>', LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();
    $root = $dom->getElementsByTagName('div')->item(0);
    $paras = [];
    if ($root) {
        foreach ($root->childNodes as $node) {
            if ($node->nodeType !== XML_ELEMENT_NODE) continue;
            $tag = strtolower($node->nodeName);
            if ($tag === 'h2')      $paras[] = docx_par(docx_inline_runs($node, ['b' => true, 'sz' => 32]), ['before' => 120, 'after' => 160]);
            elseif ($tag === 'h3')  $paras[] = docx_par(docx_inline_runs($node, ['b' => true, 'sz' => 26]), ['before' => 160, 'after' => 80]);
            elseif ($tag === 'hr')  $paras[] = docx_par([], ['border' => true, 'after' => 120]);
            elseif ($tag === 'p') {
                $cls   = $node->getAttribute('class');
                $small = (strpos($cls, 'doc-rodape') !== false || strpos($cls, 'doc-nota') !== false);
                $inh   = $small ? ['note' => true, 'sz' => 18] : ['sz' => 22];
                $paras[] = docx_par(docx_inline_runs($node, $inh), ['after' => 120]);
            } elseif ($tag === 'ol' || $tag === 'ul') {
                docx_list($node, $tag === 'ol', 0, $paras);
            }
        }
    }
    $documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
        . implode('', $paras)
        . '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134"/></w:sectPr>'
        . '</w:body></w:document>';
    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
        . '</Types>';
    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
        . '</Relationships>';
    return docx_zip_store([
        '[Content_Types].xml' => $contentTypes,
        '_rels/.rels'         => $rels,
        'word/document.xml'   => $documentXml,
    ]);
}

/** Envia a minuta como download .docx (com fallback .doc/HTML se ext-zip/dom faltarem). */
function enviar_documento_download(string $html, string $slug): void {
    $docx = docx_from_html($html);
    if ($docx !== null) {
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . $slug . '.docx"');
        echo $docx;
        return;
    }
    // fallback: HTML que o Word abre e converte para .docx
    header('Content-Type: application/msword; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $slug . '.doc"');
    echo '<html lang="pt-BR"><head><meta charset="utf-8"></head><body style="font-family:Georgia,serif">' . $html . '</body></html>';
}

function gerar_e_salvar_templates(PDO $pdo, int $fid): int {
    $st = $pdo->prepare("SELECT * FROM fundos WHERE id=?");
    $st->execute([$fid]);
    $f = $st->fetch();
    if (!$f) return 0;
    $docs = $pdo->prepare("SELECT id, nome FROM documentos_abertura WHERE fundo_id=? AND categoria='Fundo'");
    $docs->execute([$fid]);
    $upd = $pdo->prepare("UPDATE documentos_abertura SET conteudo=?, status='Recebido', arquivo=COALESCE(arquivo, ?) WHERE id=?");
    $n = 0;
    foreach ($docs->fetchAll() as $d) {
        $ger = tpl_gerador_por_nome($d['nome']);
        if (!$ger) continue;
        $conteudo = $ger($f);
        $arq = 'minuta_' . preg_replace('/[^a-z0-9]+/i', '_', mb_strtolower($d['nome'])) . '.html';
        $upd->execute([$conteudo, $arq, (int)$d['id']]);
        $n++;
    }
    return $n;
}
