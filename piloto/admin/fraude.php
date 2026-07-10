<?php
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

$u = exigir_perfil('admin');
$msg = '';
$erroSeg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_validar()) {
    $_POST = [];
    $erroSeg = 'Requisição inválida (proteção CSRF). Recarregue a página e tente novamente.';
}

// AÇÕES REAIS: tratar alerta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['alerta_id'])) {
    $acao = $_POST['acao'] ?? '';
    $mapa = ['revisar' => 'Em revisão', 'escalar' => 'Escalado', 'falso' => 'Falso positivo'];
    if (isset($mapa[$acao])) {
        $pdo->prepare('UPDATE alertas_fraude SET status=?, tratado_por=?, tratado_em=NOW(), justificativa=? WHERE id=?')
            ->execute([$mapa[$acao], $u['nome'], trim($_POST['justificativa'] ?? ''), (int)$_POST['alerta_id']]);
        $msg = 'Alerta atualizado para "' . $mapa[$acao] . '" — tratamento registrado na trilha.';
    }
}

$alertas = $pdo->query("SELECT a.*, f.nome fundo_nome FROM alertas_fraude a JOIN fundos f ON f.id=a.fundo_id
                        ORDER BY FIELD(a.status,'Aberto','Em revisão','Escalado','Falso positivo','Encerrado'),
                                 FIELD(a.severidade,'Alta','Média','Baixa'), a.data_ref DESC")->fetchAll();
$abertos = array_filter($alertas, fn($a) => in_array($a['status'], ['Aberto', 'Em revisão'], true));
$altas = array_filter($abertos, fn($a) => $a['severidade'] === 'Alta');

// grafo: fundo com vínculo suspeito (ou o primeiro com partes cadastradas)
$fidGrafo = $pdo->query("SELECT fundo_id FROM partes_relacionadas WHERE suspeito=1 LIMIT 1")->fetchColumn()
        ?: $pdo->query("SELECT fundo_id FROM partes_relacionadas LIMIT 1")->fetchColumn();
$nos = []; $arestas = []; $nomeFundoGrafo = '';
if ($fidGrafo) {
    $st = $pdo->prepare('SELECT nome FROM fundos WHERE id=?');
    $st->execute([$fidGrafo]);
    $nomeFundoGrafo = $st->fetchColumn();
    $st = $pdo->prepare('SELECT * FROM partes_relacionadas WHERE fundo_id=?');
    $st->execute([$fidGrafo]);
    $vinculos = $st->fetchAll();
    $mapa = [];
    $tipoNo = function (string $nome): string {
        if (stripos($nome, 'gestora') !== false || stripos($nome, 'gestão') !== false) return 'gestora';
        if (stripos($nome, 'FI') !== false && stripos($nome, 'fundo') !== false) return 'fundo';
        if (preg_match('/^[A-ZÀ-Ü][a-zà-ü]+ [A-ZÀ-Ü]/u', $nome)) return 'pessoa';
        return 'contraparte';
    };
    foreach ($vinculos as $v) {
        foreach ([$v['origem'], $v['destino']] as $n) {
            if (!isset($mapa[$n])) $mapa[$n] = ['id' => $n, 'rotulo' => $n, 'tipo' => $tipoNo($n), 'suspeito' => 0];
        }
        if ($v['suspeito']) { $mapa[$v['origem']]['suspeito'] = 1; $mapa[$v['destino']]['suspeito'] = 1; }
        $arestas[] = ['de' => $v['origem'], 'para' => $v['destino'], 'rotulo' => $v['tipo_vinculo'], 'suspeito' => (int)$v['suspeito']];
    }
    // gestora primeiro (fica no centro do grafo)
    uasort($mapa, fn($a, $b) => ($b['tipo'] === 'gestora') <=> ($a['tipo'] === 'gestora'));
    $nos = array_values($mapa);
}

// [código, categoria, nome, como dispara (cruzamento de dados + limiar), base legal / caso real]
// Limiares numéricos são parametrização interna do piloto / convenção de mercado (ANBIMA), não número fixado em lei.
$regras = [
    ['R1', 'Preço & negociação', 'Negócio a preço fora de mercado', 'Preço da boleta do gestor × marcação independente do ativo na data; desvio acima da banda (piloto: 3% p/ líquido, 10% p/ ilíquido). Flagra sobrepreço / self-dealing, sobretudo com contraparte de balcão.', 'Res. CVM 175, Anexo I (adm. verifica o preço) · dever de diligência (PAS CVM — aquisição de CCB, 2024)'],
    ['R2', 'Preço & negociação', 'Valuation de ilíquido (nível 3) sem lastro', 'Laudo/premissas do gestor para ativo nível 3 (FIP, crédito ilíquido) que destoam de transações comparáveis; a administradora contesta antes de aceitar a marcação.', 'CPC 46 / IFRS 13 (valor justo nível 3) · caso FIP LSH / More Invest — laudo inflado (PAS CVM 2024)'],
    ['R3', 'Preço & negociação', 'Preço defasado ou remarcação abrupta', 'Mesma taxa/PU repetida além de 2 dias úteis (stale price) ou remarcação abrupta (write-down) sem fato relevante que a justifique.', 'Metodologia ANBIMA (corte 20:30; repetição ≤ 2 d.u. — convenção) · caso Vorcaro / Jade'],
    ['R4', 'Partes relacionadas', 'Parte relacionada / conflito', 'CNPJ de contraparte ou emissor coincide com o quadro societário do gestor (grafo de vínculos); beneficiário final acima de 25%.', 'Res. CVM 175 (conflito de interesse) · Res. CVM 50, art. 2º, IX (25%)'],
    ['R5', 'Custódia & lastro', 'Conciliação de custódia (posição sem lastro)', 'Posição interna (boletas do gestor processadas pela controladoria) × posição confirmada pelo custodiante/depositário na conciliação diária; diferença sem lastro retém o fechamento da cota. Para ativo listado o depositário (B3/SELIC) é a fonte autoritativa — a quebra costuma ser falha de liquidação ou boleta fictícia. A versão-fraude com recebível inexistente vive em FIDC.', 'Res. CVM 32 (conciliação diária de custódia) · lastro de recebíveis em FIDC (fora do escopo do piloto — caso Silverado)'],
    ['R6', 'Enquadramento & liquidez', 'Concentração acima do limite', '% do PL por emissor acima do art. 44: 20% (inst. financeira), 10% (cia aberta / fundo / securitizadora), 5% (PF ou PJ não financeira); sem limite p/ título público federal.', 'Res. CVM 175, Anexo I, art. 44'],
    ['R7', 'Enquadramento & liquidez', 'Descasamento de liquidez (ativo × passivo)', 'Ativo líquido por janela (bucket D+0, D+1…) menor que o passivo exigível (resgates) na mesma janela; teste de estresse de liquidez.', 'Res. CVM 175 (gestão de liquidez) · Indicador de Resgate em Estresse (CVM/SIN)'],
    ['R8', 'Passivo & mercado', 'Movimentação atípica', 'Aplicação ou resgate acima de 3 desvios-padrão do padrão histórico de movimentação do fundo.', 'Res. CVM 50, art. 20 (dever de monitorar atipicidades)'],
    ['R9', 'Passivo & mercado', 'Front-running / timing de remarcação', 'Cotização na véspera de remarcação relevante (aplica antes da alta; resgata antes da baixa), sobretudo por parte relacionada.', 'Caso BB Asset — front-running (multas R$ 6,9 mi) · Res. CVM 50, art. 20'],
    ['R10', 'Passivo & mercado', 'Late trading', 'Ordem registrada após o horário de corte da cotização, cotizada no mesmo dia em vez de D+1.', 'Corte de cotização (piloto alinhado às 20:30 ANBIMA — convenção) · Res. CVM 175 (taxa de saída / anti-diluição)'],
    ['R11', 'Passivo & mercado', 'Concentração de resgates', 'Resgates acima de um % do PL em janela curta de dias úteis — risco de corrida e de venda forçada de ativo ilíquido.', 'Res. CVM 175 (gestão de liquidez) · prática de mercado'],
    ['R12', 'PLD / FT', 'Ida-e-volta e fracionamento (smurfing)', 'Aplicação seguida de resgate em curto intervalo sem racional econômico, ou fracionamento para escapar de comunicação. Gera COS ao COAF (sem valor mínimo), em 24h da conclusão da análise.', 'Res. CVM 50, art. 20, II (c/e/g) · Lei 9.613/98'],
    ['R13', 'PLD / FT', 'KYC/PLD incompleto, PEP ou beneficiário final', 'Cotista ou contraparte sem KYC/PLD aprovado, PEP não classificado, ou beneficiário final (>25%) não identificado. Estende-se ao KYP / due diligence de contrapartes.', 'Res. CVM 50 (arts. 11–16; Anexo A — PEP) · QDD ANBIMA (KYP)'],
    ['R14', 'Integridade da cota', 'Cota anômala', 'Retorno diário fora de 4 desvios-padrão da série histórica do próprio fundo — dispara verificação (com frequência é falso positivo legítimo).', 'Controladoria / MaM independente (Res. CVM 175, Anexo I)'],
];

page_start('Monitoramento de fraude · IA', 'IA · Fraude', $u,
    'Motor de vigilância que roda sobre todos os fundos, todos os dias — o que a administração tradicional não faz com esse rigor');
?>

<?php if ($msg): ?><div class="alert alert-success py-2"><i class="bi bi-check-circle me-1"></i><?= e_html($msg) ?></div><?php endif; ?>
<?php if ($erroSeg): ?><div class="alert alert-warning py-2"><i class="bi bi-exclamation-triangle me-1"></i><?= e_html($erroSeg) ?></div><?php endif; ?>

<div class="row row-cols-3 g-3 mb-4">
  <?= kpi('Alertas em aberto', (string)count($abertos), 'bi-shield-exclamation',
          count($altas) . ' de severidade alta') ?>
  <?= kpi('Fundos monitorados', (string)(int)$pdo->query("SELECT COUNT(*) FROM fundos WHERE status='Ativo'")->fetchColumn(),
          'bi-eye', 'varredura no batch diário') ?>
  <?= kpi('Regras ativas', (string)count($regras), 'bi-cpu', 'R1 a R' . count($regras) . ' — ver "Como funciona"') ?>
</div>

<ul class="nav nav-tabs mb-3" role="tablist" style="font-size:.88rem">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#aba-alertas">Alertas priorizados</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#aba-grafo">Grafo de partes relacionadas</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#aba-regras">Como funciona</button></li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="aba-alertas">
    <?php foreach ($alertas as $a): ?>
      <div class="card mb-3 <?= $a['severidade'] === 'Alta' && in_array($a['status'], ['Aberto', 'Em revisão']) ? 'border-danger' : '' ?>">
        <div class="card-body py-3">
          <div class="d-flex justify-content-between flex-wrap gap-2">
            <div>
              <?= badge($a['regra'], 'gold') ?> <?= badge_status($a['severidade']) ?>
              <b class="ms-1"><?= e_html($a['tipo']) ?></b>
              <span class="text-muted">· <?= e_html($a['fundo_nome']) ?> · <?= data_br($a['data_ref']) ?></span>
            </div>
            <div><?= badge_status($a['status']) ?></div>
          </div>
          <p class="mb-1 mt-2" style="font-size:.88rem"><i class="bi bi-robot me-1 text-muted"></i><?= e_html($a['explicacao']) ?></p>
          <?php if ($a['evidencia']): ?>
            <p class="text-muted mb-2" style="font-size:.8rem"><i class="bi bi-clipboard-data me-1"></i>Evidência: <?= e_html($a['evidencia']) ?></p>
          <?php endif; ?>
          <?php if (in_array($a['status'], ['Aberto', 'Em revisão'], true)): ?>
            <form method="post" class="d-flex gap-2 flex-wrap align-items-center">
              <?= csrf_campo() ?>
              <input type="hidden" name="alerta_id" value="<?= (int)$a['id'] ?>">
              <input class="form-control form-control-sm" style="max-width:340px" name="justificativa" placeholder="Justificativa / observação…">
              <button class="btn btn-sm btn-outline-primary" name="acao" value="revisar"><i class="bi bi-search me-1"></i>Em revisão</button>
              <button class="btn btn-sm btn-outline-danger" name="acao" value="escalar"><i class="bi bi-arrow-up-circle me-1"></i>Escalar ao compliance</button>
              <button class="btn btn-sm btn-outline-secondary" name="acao" value="falso"><i class="bi bi-x-circle me-1"></i>Falso positivo</button>
            </form>
          <?php elseif ($a['tratado_por']): ?>
            <p class="text-muted mb-0" style="font-size:.78rem"><i class="bi bi-person-check me-1"></i>
              Tratado por <?= e_html($a['tratado_por']) ?> em <?= $a['tratado_em'] ? date('d/m/Y H:i', strtotime($a['tratado_em'])) : '' ?>
              <?= $a['justificativa'] ? '— "' . e_html($a['justificativa']) . '"' : '' ?></p>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$alertas): ?><p class="text-muted text-center py-5">Nenhum alerta gerado.</p><?php endif; ?>
  </div>

  <div class="tab-pane fade" id="aba-grafo">
    <div class="card">
      <div class="card-header"><i class="bi bi-diagram-3 me-1"></i> Vínculos mapeados — <?= e_html($nomeFundoGrafo) ?></div>
      <div class="card-body">
        <canvas id="grafo-partes"></canvas>
        <div class="d-flex gap-3 mt-2 flex-wrap" style="font-size:.78rem">
          <span><span class="semaforo" style="background:#6a50ac"></span>gestora</span>
          <span><span class="semaforo" style="background:#14b8a6"></span>fundo</span>
          <span><span class="semaforo" style="background:#3b82f6"></span>contraparte</span>
          <span><span class="semaforo" style="background:#8b5cf6"></span>pessoa física</span>
          <span class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>tracejado vermelho = vínculo suspeito</span>
        </div>
      </div>
    </div>
  </div>

  <div class="tab-pane fade" id="aba-regras">
    <div class="card">
      <div class="card-header"><i class="bi bi-gear me-1"></i> Motor de regras do piloto — <?= count($regras) ?> regras determinísticas sobre a base do batch diário</div>
      <div class="card-body p-0">
        <p class="text-muted px-3 pt-3 mb-2" style="font-size:.82rem">
          A administradora <b>marca os ativos de forma independente</b> e vê as boletas do gestor, a posição do custodiante, o
          passivo dos cotistas e o cadastro de contrapartes. O motor cruza essas fontes — a marcação é a <b>régua</b>; o alvo é o
          comportamento do <b>gestor, do cotista ou da contraparte</b> que destoa dela. Marcação errada, por si, é erro operacional
          (vai para conciliação/precificação), não fraude.
        </p>
        <table class="table mb-0">
          <thead><tr><th>Regra</th><th>Nome</th><th>Como dispara (dado × dado + limiar)</th><th>Base legal / caso</th></tr></thead>
          <tbody>
          <?php $catAtual = ''; foreach ($regras as [$r, $cat, $nome, $disp, $base]): ?>
            <?php if ($cat !== $catAtual): $catAtual = $cat; ?>
              <tr class="table-light"><td colspan="4" class="fw-semibold text-uppercase" style="font-size:.72rem; letter-spacing:.04em"><?= e_html($cat) ?></td></tr>
            <?php endif; ?>
            <tr>
              <td><?= badge($r, 'gold') ?></td>
              <td style="font-size:.84rem"><b><?= e_html($nome) ?></b></td>
              <td class="text-muted" style="font-size:.82rem"><?= e_html($disp) ?></td>
              <td class="text-muted" style="font-size:.76rem"><?= e_html($base) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="card-footer text-muted" style="font-size:.76rem">
        Os alertas nascem destas regras; a mesma trilha de dados alimenta modelos de detecção de anomalias, e todo tratamento
        (quem viu, o que decidiu, quando) fica registrado — evidência perante a CVM.
        <b>Notas de honestidade:</b> os <b>limiares</b> (bandas de preço, nº de desvios, % do PL, horário de corte) são parametrização
        interna / convenção de mercado, não números fixados em resolução. No PLD, o <b>R$ 50 mil em espécie</b> e o <b>prazo de 45+45 dias</b>
        são norma bancária (BCB), não da CVM — em fundos o gatilho é a <b>comunicação de operação suspeita por atipicidade</b>, sem valor mínimo.
        Piloto: dados simulados.
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const desenhar = () => desenharGrafo('grafo-partes', <?= json_encode($nos, JSON_UNESCAPED_UNICODE) ?>, <?= json_encode($arestas, JSON_UNESCAPED_UNICODE) ?>);
  desenhar();
  document.querySelector('[data-bs-target="#aba-grafo"]').addEventListener('shown.bs.tab', desenhar);
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php page_end();
