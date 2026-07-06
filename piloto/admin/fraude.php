<?php
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

$u = exigir_perfil('admin');
$msg = '';

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

$regras = [
    ['R1', 'Preço fora da curva', 'Preço MaM desvia mais de 5% da referência de mercado do ativo (B3/ANBIMA).'],
    ['R2', 'Parte relacionada', 'CNPJ de contraparte coincide com sócio ou empresa ligada ao gestor do fundo.'],
    ['R3', 'Movimentação atípica', 'Lançamento acima de 3 desvios-padrão do padrão histórico de movimentações do fundo.'],
    ['R4', 'Ativo fantasma', 'Posição na carteira sem correspondência na posição reportada pelo custodiante.'],
    ['R5', 'Timing suspeito', 'Aplicação ou resgate relevante na véspera de remarcação de ativo ilíquido.'],
    ['R6', 'Concentração de resgates', 'Resgates acima de limite percentual do PL em janela curta de dias.'],
    ['R7', 'Cota anômala', 'Retorno diário fora de 4 desvios-padrão da série histórica do próprio fundo.'],
];

page_start('Monitoramento de fraude · IA', 'IA · Fraude', $u,
    'Motor de vigilância que roda sobre todos os fundos, todos os dias — o que as grandes não oferecem para fundos pequenos');
?>

<?php if ($msg): ?><div class="alert alert-success py-2"><i class="bi bi-check-circle me-1"></i><?= e_html($msg) ?></div><?php endif; ?>

<div class="row row-cols-3 g-3 mb-4">
  <?= kpi('Alertas em aberto', (string)count($abertos), 'bi-shield-exclamation',
          count($altas) . ' de severidade alta') ?>
  <?= kpi('Fundos monitorados', (string)(int)$pdo->query("SELECT COUNT(*) FROM fundos WHERE status='Ativo'")->fetchColumn(),
          'bi-eye', 'varredura no batch diário') ?>
  <?= kpi('Regras ativas', '7', 'bi-cpu', 'R1 a R7 — ver "Como funciona"') ?>
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
          <span><span class="semaforo" style="background:#c9a227"></span>gestora</span>
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
      <div class="card-header"><i class="bi bi-gear me-1"></i> Motor de regras do piloto (a IA de produção treina em cima desta base)</div>
      <div class="card-body p-0">
        <table class="table mb-0">
          <thead><tr><th>Regra</th><th>Nome</th><th>Como dispara</th></tr></thead>
          <tbody>
          <?php foreach ($regras as [$r, $nome, $desc]): ?>
            <tr><td><?= badge($r, 'gold') ?></td><td><b><?= $nome ?></b></td><td class="text-muted" style="font-size:.86rem"><?= $desc ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="card-footer text-muted" style="font-size:.78rem">
        No piloto, os alertas nascem destas regras determinísticas sobre os dados simulados. Em produção, a mesma trilha de
        dados alimenta modelos de detecção de anomalias — e todo tratamento (quem viu, o que decidiu, quando) fica registrado,
        que é o que protege o banco perante a CVM.
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
