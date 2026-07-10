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
    $h .= "<li><b>Responsabilidade dos prestadores.</b> Cada prestador responde, na sua esfera de atuação, por seus próprios atos e omissões (art. 81), sem solidariedade automática, salvo dolo ou má-fé (Código Civil, art. 1.368-D, II — Lei 14.754/2023).</li>";
    $h .= "<li><b>Classes e despesas.</b> O fundo possui [uma/mais de uma] classe de cotas. As despesas comuns, se houver, são rateadas de forma verificável e sem transferência indevida de riqueza (art. 48, §1º, III e IV).</li>";
    $h .= "<li><b>Prazo de duração:</b> indeterminado.</li>";
    $h .= "<li><b>Taxa de administração:</b> " . tpl_pct($f['taxa_adm']) . " ao ano sobre o patrimônio líquido (base 252 dias úteis), com piso de R$ 100,00/mês. "
        . "<b>Taxa de gestão:</b> " . tpl_pct($f['taxa_gestao']) . " ao ano (base 252).</li>";
    $h .= "<li><b>Exercício social:</b> encerra em 31 de dezembro.</li>";
    $h .= "</ol>";
    $h .= "<h3>Anexo Descritivo da Classe (art. 48, §2º; Anexo I, arts. 15–16)</h3><ol>";
    $h .= "<li><b>Público-alvo:</b> " . tpl_esc($f['publico_alvo']) . ".</li>";
    $h .= "<li><b>Responsabilidade dos cotistas:</b> $resp (art. 5º; sufixo \"Responsabilidade Limitada\" na denominação quando aplicável).</li>";
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

/**
 * Mapa nome-do-checklist → função geradora. Só a categoria "Fundo" tem minuta;
 * documentos da gestora/responsável são anexos externos (upload).
 */
function tpl_gerador_por_nome(string $nome): ?callable {
    $n = mb_strtolower($nome);
    if (str_contains($n, 'regulamento'))       return 'tpl_regulamento';
    if (str_contains($n, 'política de invest') || str_contains($n, 'politica de invest')) return 'tpl_politica_investimento';
    if (str_contains($n, 'termo de adesão') || str_contains($n, 'termo de adesao')) return 'tpl_termo_adesao';
    if (str_contains($n, 'lâmina') || str_contains($n, 'lamina')) return 'tpl_lamina';
    if (str_contains($n, 'custódia') || str_contains($n, 'custodia')) return 'tpl_contrato_custodia';
    if (str_contains($n, 'auditoria'))          return 'tpl_contrato_auditoria';
    if (str_contains($n, 'distribuição') || str_contains($n, 'distribuicao')) return 'tpl_contrato_distribuicao';
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
