<?php
// Classes & Subclasses de cotas (Res. CVM 175) — estrutura/metadados do fundo.
// Uma CLASSE segrega ATIVOS (patrimônio segregado, CNPJ próprio); uma SUBCLASSE
// segrega PASSIVOS (cotistas) e difere por público-alvo, prazos e taxas.
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

ensure_subclasses($pdo);   // DDL (commit implícito) — sempre FORA de transação
ensure_regulamento($pdo);  // garante coluna reg_html na subclasse (suplemento gerado)

$u = exigir_perfil('admin');
$msg = ''; $msgTipo = 'success';

// ---------- Seletor de fundo ----------
$fundos = $pdo->query("SELECT * FROM fundos WHERE status='Ativo' ORDER BY pl_atual DESC")->fetchAll();
if (isset($_GET['fundo_id'])) $_SESSION['admin_fundo_id'] = (int)$_GET['fundo_id'];
$fid = (int)($_SESSION['admin_fundo_id'] ?? ($fundos[0]['id'] ?? 0));
$fundo = null;
foreach ($fundos as $f) if ((int)$f['id'] === $fid) $fundo = $f;
if (!$fundo && $fundos) { $fundo = $fundos[0]; $fid = (int)$fundo['id']; }

// ---------- Opções dos selects ----------
const PUBLICOS_ALVO = ['Investidores em geral', 'Investidores qualificados', 'Investidores profissionais'];
const PRAZOS_COTIZACAO = ['D+0', 'D+1', 'D+30'];
const PRAZOS_LIQUIDACAO = ['D+1', 'D+2', 'D+3'];
const CONDOMINIOS = ['Aberto', 'Fechado'];

// ---------- Proteções de POST ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_validar()) {
    $_POST = [];
    $msg = 'Requisição inválida (proteção CSRF). Recarregue a página.'; $msgTipo = 'danger';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !nonce_valido()) {
    $_POST = [];
    $msg = 'Ação já processada — envio duplicado ignorado.'; $msgTipo = 'warning';
}

// ---------- Ações ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $fundo) {
    try {
        if (!empty($_POST['criar'])) {
            $nome = trim($_POST['nome'] ?? '');
            $publico = in_array($_POST['publico_alvo'] ?? '', PUBLICOS_ALVO, true) ? $_POST['publico_alvo'] : PUBLICOS_ALVO[0];
            $aplicacaoMinima = (float)str_replace(['.', ','], ['', '.'], $_POST['aplicacao_minima'] ?? '0');
            // taxas informadas em % — armazenadas como fração (0,50% -> 0,005)
            $taxaAdm  = (float)str_replace(',', '.', $_POST['taxa_adm'] ?? '0') / 100;
            $taxaPerf = (float)str_replace(',', '.', $_POST['taxa_performance'] ?? '0') / 100;
            $cotizacao  = in_array($_POST['prazo_cotizacao'] ?? '', PRAZOS_COTIZACAO, true) ? $_POST['prazo_cotizacao'] : PRAZOS_COTIZACAO[0];
            $liquidacao = in_array($_POST['prazo_liquidacao'] ?? '', PRAZOS_LIQUIDACAO, true) ? $_POST['prazo_liquidacao'] : PRAZOS_LIQUIDACAO[0];
            $condominio = in_array($_POST['condominio'] ?? '', CONDOMINIOS, true) ? $_POST['condominio'] : CONDOMINIOS[0];

            $anexo   = trim($_FILES['regulamento_anexo']['name'] ?? '');
            $dataVig = trim($_POST['data_vigencia'] ?? '');
            if ($nome === '') {
                $msg = 'Informe o nome da subclasse.'; $msgTipo = 'warning';
            } elseif ($anexo === '') {
                $msg = 'Anexe a minuta do anexo ao regulamento (exigência da Res. CVM 175 para instituir a subclasse).'; $msgTipo = 'warning';
            } elseif ($dataVig === '') {
                $msg = 'Informe a data pretendida de início de vigência.'; $msgTipo = 'warning';
            } else {
                // suplemento (documento) gerado a partir do passivo da subclasse
                $suplemento = reg_suplemento_subclasse_html([
                    'nome' => $nome, 'publico_alvo' => $publico, 'aplicacao_minima' => $aplicacaoMinima,
                    'taxa_adm' => $taxaAdm, 'taxa_performance' => $taxaPerf, 'prazo_cotizacao' => $cotizacao,
                    'prazo_liquidacao' => $liquidacao, 'condominio' => $condominio, 'data_vigencia' => $dataVig,
                ], $fundo['nome'] ?? '');
                com_transacao($pdo, function () use ($pdo, $fid, $nome, $publico, $aplicacaoMinima, $taxaAdm, $taxaPerf, $cotizacao, $liquidacao, $condominio, $anexo, $dataVig, $suplemento) {
                    // nasce "Em registro": ainda não é vigente até protocolo + vigência
                    $st = $pdo->prepare('INSERT INTO subclasses
                        (fundo_id, nome, publico_alvo, aplicacao_minima, taxa_adm, taxa_performance, prazo_cotizacao, prazo_liquidacao, condominio, status, etapa_registro, regulamento_anexo, data_vigencia, reg_html)
                        VALUES (?,?,?,?,?,?,?,?,?, "Ativa", "Em registro", ?, ?, ?)');
                    $st->execute([$fid, $nome, $publico, $aplicacaoMinima, $taxaAdm, $taxaPerf, $cotizacao, $liquidacao, $condominio, $anexo, $dataVig, $suplemento]);
                    $novoId = (int)$pdo->lastInsertId();
                    registrar_auditoria($pdo, 'subclasse_criada', [
                        'entidade' => 'subclasse', 'entidade_id' => $novoId, 'fundo_id' => $fid,
                        'detalhe' => "Subclasse \"$nome\" ($publico) em registro — anexo \"$anexo\", vigência pretendida $dataVig",
                    ]);
                });
                $msg = 'Subclasse "' . $nome . '" registrada (etapa "Em registro"). Protocole na CVM e coloque em vigência para ativá-la.';
            }
        } elseif (!empty($_POST['protocolar'])) {
            $subId = (int)($_POST['subclasse_id'] ?? 0);
            $protocolo = 'CVM-' . date('Y') . '-' . str_pad((string)$subId, 5, '0', STR_PAD_LEFT);
            $st = $pdo->prepare("UPDATE subclasses SET etapa_registro='Protocolada', protocolo_cvm=? WHERE id=? AND fundo_id=? AND etapa_registro='Em registro'");
            $st->execute([$protocolo, $subId, $fid]);
            if ($st->rowCount()) {
                registrar_auditoria($pdo, 'subclasse_protocolada', ['entidade' => 'subclasse', 'entidade_id' => $subId, 'fundo_id' => $fid,
                    'detalhe' => "Subclasse protocolada na CVM sob $protocolo"]);
                $msg = "Anexo protocolado na CVM (nº $protocolo). Após o decurso do prazo, coloque em vigência.";
            } else { $msg = 'Só é possível protocolar uma subclasse na etapa "Em registro".'; $msgTipo = 'warning'; }
        } elseif (!empty($_POST['vigencia'])) {
            $subId = (int)($_POST['subclasse_id'] ?? 0);
            $st = $pdo->prepare("UPDATE subclasses SET etapa_registro='Vigente', data_vigencia=IF(data_vigencia<CURDATE(), CURDATE(), data_vigencia) WHERE id=? AND fundo_id=? AND etapa_registro='Protocolada'");
            $st->execute([$subId, $fid]);
            if ($st->rowCount()) {
                registrar_auditoria($pdo, 'subclasse_vigente', ['entidade' => 'subclasse', 'entidade_id' => $subId, 'fundo_id' => $fid,
                    'detalhe' => 'Subclasse entrou em vigência — passa a aceitar aplicações']);
                $msg = 'Subclasse em vigência — já pode receber aplicações do público-alvo definido.';
            } else { $msg = 'Só entra em vigência quem já foi protocolado na CVM.'; $msgTipo = 'warning'; }
        } elseif (!empty($_POST['encerrar'])) {
            $subId = (int)($_POST['subclasse_id'] ?? 0);
            com_transacao($pdo, function () use ($pdo, $fid, $subId, &$msg) {
                $st = $pdo->prepare("SELECT * FROM subclasses WHERE id = ? AND fundo_id = ?");
                $st->execute([$subId, $fid]);
                $sub = $st->fetch();
                if (!$sub) { $msg = 'Subclasse não encontrada neste fundo.'; return; }
                $pdo->prepare("UPDATE subclasses SET status = 'Encerrada' WHERE id = ? AND fundo_id = ?")->execute([$subId, $fid]);
                registrar_auditoria($pdo, 'subclasse_encerrada', [
                    'entidade' => 'subclasse', 'entidade_id' => $subId, 'fundo_id' => $fid,
                    'detalhe' => 'Subclasse "' . $sub['nome'] . '" encerrada',
                ]);
            });
            if ($msg === '') $msg = 'Subclasse encerrada.';
        }
    } catch (Throwable $e) {
        $msg = 'Não foi possível concluir: ' . $e->getMessage(); $msgTipo = 'danger';
    }
}

// ---------- Dados ----------
$subclasses = [];
if ($fundo) {
    $st = $pdo->prepare('SELECT * FROM subclasses WHERE fundo_id = ? ORDER BY (status = \'Encerrada\'), criado_em DESC, id DESC');
    $st->execute([$fid]);
    $subclasses = $st->fetchAll();
}
$ativas = 0;
foreach ($subclasses as $s) if (($s['status'] ?? '') === 'Ativa') $ativas++;

page_start('Classes & Subclasses', 'Classes & Subclasses', $u,
    'Estrutura de classes/subclasses de cotas de um fundo sob a Res. CVM 175/2022');
?>

<?php if ($msg): ?><div class="alert alert-<?= $msgTipo ?> py-2"><i class="bi bi-info-circle me-1"></i><?= e_html($msg) ?></div><?php endif; ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <form method="get">
    <select name="fundo_id" class="form-select form-select-sm" onchange="this.form.submit()">
      <?php foreach ($fundos as $f): ?>
        <option value="<?= (int)$f['id'] ?>" <?= (int)$f['id'] === $fid ? 'selected' : '' ?>><?= e_html($f['nome']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <?php if ($fundo): ?>
  <div style="font-size:.82rem" class="text-muted">
    Classe do fundo: <?= badge($fundo['classe'] ?? '—', 'info') ?> ·
    Público-alvo: <?= e_html($fundo['publico_alvo'] ?? '—') ?> ·
    Condomínio: <?= e_html($fundo['condominio'] ?? '—') ?>
  </div>
  <?php endif; ?>
</div>

<!-- KPIs -->
<div class="row row-cols-2 row-cols-md-4 g-3 mb-4">
  <?= kpi('Subclasses ativas', (string)$ativas, 'bi-diagram-3', 'no fundo selecionado') ?>
  <?= kpi('Subclasses (total)', (string)count($subclasses), 'bi-layers') ?>
  <?= kpi('Classe do fundo', e_html($fundo['classe'] ?? '—'), 'bi-collection', 'segrega ativos') ?>
  <?= kpi('Condomínio', e_html($fundo['condominio'] ?? '—'), 'bi-building') ?>
</div>

<!-- Explicação Res. 175 -->
<div class="card mb-4">
  <div class="card-header"><i class="bi bi-mortarboard me-1"></i> Classe × Subclasse — Res. CVM 175</div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-6">
        <h6 class="mb-1"><i class="bi bi-collection me-1"></i> Classe <span class="text-muted" style="font-size:.75rem">(segrega ATIVOS)</span></h6>
        <p class="mb-0" style="font-size:.85rem">
          A <b>classe</b> tem <b>patrimônio segregado</b> e <b>CNPJ próprio</b> (Res. 175, art. 5º). Separa
          <b>ativos</b> e riscos: cada classe tem sua carteira, sua contabilidade e sua cota. A responsabilidade
          patrimonial é limitada à classe (sem contágio entre classes do mesmo fundo).
        </p>
      </div>
      <div class="col-md-6">
        <h6 class="mb-1"><i class="bi bi-diagram-3 me-1"></i> Subclasse <span class="text-muted" style="font-size:.75rem">(segrega PASSIVOS)</span></h6>
        <p class="mb-0" style="font-size:.85rem">
          A <b>subclasse</b> vive <b>dentro</b> de uma classe e separa o <b>passivo</b> (os cotistas). Difere apenas por
          <b>público-alvo</b>, <b>prazos de cotização/liquidação</b> e <b>taxas</b>. Compartilha a mesma carteira da classe —
          por isso <b>não dilui</b> a taxa CVM (a fiscalização é cobrada por classe, não por subclasse).
        </p>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <!-- Criar subclasse -->
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-plus-circle me-1"></i> Nova subclasse</div>
      <div class="card-body">
        <?php if (!$fundo): ?>
          <div class="alert alert-warning py-2 mb-0" style="font-size:.82rem">Nenhum fundo ativo disponível.</div>
        <?php else: ?>
        <form method="post" enctype="multipart/form-data">
          <?= csrf_campo() ?><?= nonce_campo() ?>
          <label class="form-label" style="font-size:.8rem">Nome da subclasse</label>
          <input name="nome" class="form-control form-control-sm mb-2" placeholder="Ex.: Sênior · Varejo · Institucional" required>

          <label class="form-label" style="font-size:.8rem">Público-alvo</label>
          <select name="publico_alvo" class="form-select form-select-sm mb-2">
            <?php foreach (PUBLICOS_ALVO as $p): ?><option value="<?= e_html($p) ?>"><?= e_html($p) ?></option><?php endforeach; ?>
          </select>

          <label class="form-label" style="font-size:.8rem">Aplicação mínima (R$)</label>
          <input name="aplicacao_minima" class="form-control form-control-sm mb-2" placeholder="1000,00">

          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label" style="font-size:.8rem">Taxa de adm. (% a.a.)</label>
              <input name="taxa_adm" class="form-control form-control-sm" placeholder="0,50">
            </div>
            <div class="col-6">
              <label class="form-label" style="font-size:.8rem">Taxa de performance (%)</label>
              <input name="taxa_performance" class="form-control form-control-sm" placeholder="20">
            </div>
          </div>

          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label" style="font-size:.8rem">Cotização</label>
              <select name="prazo_cotizacao" class="form-select form-select-sm">
                <?php foreach (PRAZOS_COTIZACAO as $p): ?><option value="<?= $p ?>"><?= $p ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label" style="font-size:.8rem">Liquidação</label>
              <select name="prazo_liquidacao" class="form-select form-select-sm">
                <?php foreach (PRAZOS_LIQUIDACAO as $p): ?><option value="<?= $p ?>"><?= $p ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>

          <label class="form-label" style="font-size:.8rem">Condomínio</label>
          <select name="condominio" class="form-select form-select-sm mb-2">
            <?php foreach (CONDOMINIOS as $c): ?><option value="<?= $c ?>"><?= $c ?></option><?php endforeach; ?>
          </select>

          <hr class="my-2">
          <div class="text-muted mb-1" style="font-size:.72rem;font-weight:700">ESTEIRA FORMAL (RES. CVM 175)</div>
          <label class="form-label" style="font-size:.8rem">Anexo ao regulamento (minuta) *</label>
          <input type="file" name="regulamento_anexo" class="form-control form-control-sm mb-2" required>
          <label class="form-label" style="font-size:.8rem">Início de vigência pretendido *</label>
          <input type="date" name="data_vigencia" class="form-control form-control-sm mb-3" required>

          <button name="criar" value="1" class="btn btn-sm btn-dark w-100"><i class="bi bi-check-lg me-1"></i>Registrar subclasse</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Nota honesta -->
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-info-circle me-1"></i> O que este módulo modela</div>
      <div class="card-body">
        <p style="font-size:.85rem">
          Aqui a subclasse é modelada como <b>estrutura/metadados</b> do fundo: registramos o <b>público-alvo</b>, os
          <b>prazos de cotização/liquidação</b>, as <b>taxas</b> e o <b>condomínio</b> que diferenciam cada subclasse do
          passivo — que é exatamente o que a Res. CVM 175 permite variar sem exigir patrimônio próprio.
        </p>
        <div class="mb-2" style="font-size:.83rem">
          <b>Esteira de registro (agora modelada):</b> a subclasse nasce <b>Em registro</b> exigindo o
          <b>anexo ao regulamento</b> e a <b>data de vigência</b>; segue para <b>Protocolada</b> na CVM (gera nº de protocolo)
          e só então <b>Vigente</b> — quando passa a aceitar aplicações. É o rito real: anexo → protocolo → vigência.
        </div>
        <div class="alert alert-warning py-2 mb-0" style="font-size:.82rem">
          <b>Nota honesta:</b> o protocolo na CVM é <b>simulado</b> (não há envio real ao sistema da CVM), o anexo guarda
          apenas o nome do arquivo, e a <b>segregação plena de patrimônio por classe</b> — carteira, contabilidade e
          <b>cota próprias</b>, com PL independente — <b>continua não implementada</b>. Modelamos o <b>rito</b> e as
          características do passivo; não a separação contábil-patrimonial nem o ato regulatório efetivo.
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Lista de subclasses -->
<div class="card">
  <div class="card-header"><i class="bi bi-diagram-3 me-1"></i> Subclasses do fundo</div>
  <div class="card-body p-0">
    <table class="table table-hover align-middle mb-0" style="font-size:.83rem">
      <thead><tr>
        <th>Subclasse</th><th>Público-alvo</th><th class="text-end">Aplic. mínima</th>
        <th class="text-end">Taxa adm.</th><th class="text-end">Perf.</th>
        <th class="text-center">Cotização</th><th class="text-center">Liquidação</th>
        <th class="text-center">Condomínio</th><th>Registro (CVM 175)</th><th class="text-center">Status</th><th></th>
      </tr></thead>
      <tbody>
      <?php foreach ($subclasses as $s): ?>
        <tr class="<?= ($s['status'] ?? '') === 'Encerrada' ? 'text-muted' : '' ?>">
          <td><b><?= e_html($s['nome']) ?></b></td>
          <td><?= e_html($s['publico_alvo']) ?></td>
          <td class="text-end"><?= moeda($s['aplicacao_minima']) ?></td>
          <td class="text-end"><?= number_format((float)$s['taxa_adm'] * 100, 4, ',', '.') ?>%</td>
          <td class="text-end"><?= number_format((float)$s['taxa_performance'] * 100, 2, ',', '.') ?>%</td>
          <td class="text-center"><?= e_html($s['prazo_cotizacao']) ?></td>
          <td class="text-center"><?= e_html($s['prazo_liquidacao']) ?></td>
          <td class="text-center"><?= e_html($s['condominio']) ?></td>
          <?php $etapa = $s['etapa_registro'] ?? 'Vigente';
                $cor = ['Em registro' => 'warning', 'Protocolada' => 'info', 'Vigente' => 'success'][$etapa] ?? 'secondary'; ?>
          <td style="font-size:.78rem">
            <?= badge($etapa, $cor) ?>
            <?php if (!empty($s['protocolo_cvm'])): ?><br><span class="text-muted"><?= e_html($s['protocolo_cvm']) ?></span><?php endif; ?>
            <?php if (!empty($s['data_vigencia'])): ?><br><span class="text-muted">vig. <?= data_br($s['data_vigencia']) ?></span><?php endif; ?>
            <?php if (!empty($s['regulamento_anexo'])): ?><br><span class="text-muted" title="anexo ao regulamento"><i class="bi bi-paperclip"></i> <?= e_html($s['regulamento_anexo']) ?></span><?php endif; ?>
            <?php if (!empty($s['reg_html'])): ?><br><a href="regulamento_ver.php?origem=subclasse&id=<?= (int)$s['id'] ?>" target="_blank" style="font-size:.75rem"><i class="bi bi-file-earmark-text"></i> suplemento</a><?php endif; ?>
          </td>
          <td class="text-center"><?= badge_status($s['status']) ?></td>
          <td class="text-end">
            <?php if (($s['status'] ?? '') === 'Ativa'): ?>
              <?php if ($etapa === 'Em registro'): ?>
                <form method="post" class="d-inline"><?= csrf_campo() ?><?= nonce_campo() ?>
                  <input type="hidden" name="subclasse_id" value="<?= (int)$s['id'] ?>">
                  <button name="protocolar" value="1" class="btn btn-sm btn-outline-info py-0" title="Protocolar anexo na CVM"><i class="bi bi-send me-1"></i>Protocolar</button></form>
              <?php elseif ($etapa === 'Protocolada'): ?>
                <form method="post" class="d-inline" onsubmit="return confirm('Colocar a subclasse em vigência? Ela passa a aceitar aplicações.')"><?= csrf_campo() ?><?= nonce_campo() ?>
                  <input type="hidden" name="subclasse_id" value="<?= (int)$s['id'] ?>">
                  <button name="vigencia" value="1" class="btn btn-sm btn-outline-success py-0" title="Colocar em vigência"><i class="bi bi-play-circle me-1"></i>Vigência</button></form>
              <?php endif; ?>
              <form method="post" onsubmit="return confirm('Encerrar esta subclasse? Novas aplicações ficam vedadas.')" class="d-inline">
                <?= csrf_campo() ?><?= nonce_campo() ?>
                <input type="hidden" name="subclasse_id" value="<?= (int)$s['id'] ?>">
                <button name="encerrar" value="1" class="btn btn-sm btn-outline-danger py-0"><i class="bi bi-x-circle me-1"></i>Encerrar</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$subclasses): ?>
        <tr><td colspan="11" class="text-muted text-center py-4">Nenhuma subclasse cadastrada — crie a primeira ao lado.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer text-muted" style="font-size:.72rem">
    A subclasse compartilha a carteira/cota da sua classe; difere apenas em público-alvo, prazos e taxas — e por isso não dilui a taxa CVM (cobrada por classe).
  </div>
</div>
<?php page_end();
