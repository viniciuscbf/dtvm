<?php
// Onboarding de cotistas — KYC / Suitability / PLD (Res. CVM 175 + Res. CVM 50 PLD).
// SIMULAÇÃO didática: cadastro do passivo, coleta de suitability/FATCA-CRS, aceite de
// termo de adesão e validação (screening) de KYC/PLD. Em produção: validação documental,
// screening OFAC/PEP em bases de sanções e listas restritivas, suitability real (ICVM/ANBIMA).
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

$u = exigir_perfil('admin');

// Garante as colunas de KYC/suitability/PLD na tabela cotistas (DDL fora de transação).
ensure_kyc_cotista($pdo);

$msg = ''; $msgTipo = 'success';

// ---------- Seleção de fundo (por GET, guardada na sessão) ----------
$fundos = $pdo->query("SELECT * FROM fundos WHERE status='Ativo' ORDER BY pl_atual DESC")->fetchAll();
if (isset($_GET['fundo_id'])) $_SESSION['admin_fundo_id'] = (int)$_GET['fundo_id'];
$fid = (int)($_SESSION['admin_fundo_id'] ?? ($fundos[0]['id'] ?? 0));
$fundo = null;
foreach ($fundos as $f) if ((int)$f['id'] === $fid) $fundo = $f;
if (!$fundo && $fundos) { $fundo = $fundos[0]; $fid = (int)$fundo['id']; }

// ---------- Domínios ----------
const SUITABILITY_OPCOES = ['Conservador', 'Moderado', 'Arrojado'];
const FATCA_CRS_OPCOES   = ['Não US person', 'US person', 'Reportável'];
// Lista de exemplo para simular screening PLD (documentos "marcados"). NÃO é uma base real.
const PLD_LISTA_EXEMPLO  = ['00000000000', '11111111111', '99999999999', '12345678909', '00000000000191'];

/** Só dígitos do documento (para comparar com a lista de exemplo). */
function so_digitos(string $s): string { return preg_replace('/\D+/', '', $s); }

/** Perfis (suitability) considerados compatíveis com cada público-alvo do fundo. */
function suitability_compativel(?string $publico_alvo, ?string $suitability): bool {
    // Regra didática: fundos restritos exigem perfil mais arrojado.
    $exigido = match ($publico_alvo) {
        'Profissionais' => ['Arrojado'],
        'Qualificados'  => ['Moderado', 'Arrojado'],
        default          => ['Conservador', 'Moderado', 'Arrojado'], // Investidores em geral
    };
    return $suitability !== null && in_array($suitability, $exigido, true);
}

// ---------- Ações (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_validar()) {
    $_POST = [];
    $msg = 'Requisição inválida (proteção CSRF). Recarregue a página.'; $msgTipo = 'danger';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !nonce_valido()) {
    $_POST = [];
    $msg = 'Ação já processada — envio duplicado ignorado.'; $msgTipo = 'warning';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $fundo) {
    try {
        // 1) Cadastro / onboarding de novo cotista
        if (!empty($_POST['onboard'])) {
            $nome       = trim($_POST['nome'] ?? '');
            $documento  = trim($_POST['documento'] ?? '');
            $tipoPessoa = ($_POST['tipo_pessoa'] ?? 'PF') === 'PJ' ? 'PJ' : 'PF';
            $suit       = in_array($_POST['suitability'] ?? '', SUITABILITY_OPCOES, true) ? $_POST['suitability'] : null;
            $fatca      = in_array($_POST['fatca_crs'] ?? '', FATCA_CRS_OPCOES, true) ? $_POST['fatca_crs'] : null;
            $aceite     = !empty($_POST['termo']);

            if ($nome === '' || $documento === '') {
                $msg = 'Informe nome e documento (CPF/CNPJ) do cotista.'; $msgTipo = 'warning';
            } elseif ($suit === null) {
                $msg = 'Selecione o perfil de suitability do cotista.'; $msgTipo = 'warning';
            } elseif (!$aceite) {
                $msg = 'É necessário registrar o aceite do termo de adesão.'; $msgTipo = 'warning';
            } else {
                com_transacao($pdo, function () use ($pdo, $fid, $nome, $documento, $tipoPessoa, $suit, $fatca) {
                    $st = $pdo->prepare(
                        "INSERT INTO cotistas (fundo_id, nome, documento, tipo_pessoa, cotas, custo_total, data_entrada,
                                               suitability, kyc_status, pld_status, fatca_crs, termo_aceite)
                         VALUES (?,?,?,?,0,0,?,?,?,?,?,NOW())"
                    );
                    $st->execute([$fid, $nome, $documento, $tipoPessoa, date('Y-m-d'),
                                  $suit, 'Pendente', 'Pendente', $fatca]);
                    $novoId = (int)$pdo->lastInsertId();
                    registrar_auditoria($pdo, 'cotista_onboarding', [
                        'entidade' => 'cotista', 'entidade_id' => $novoId, 'fundo_id' => $fid,
                        'detalhe'  => "Onboarding de {$nome} ({$documento}, {$tipoPessoa}) · suitability {$suit}" .
                                      ($fatca ? " · FATCA/CRS {$fatca}" : '') . ' · termo aceito · KYC/PLD Pendente',
                    ]);
                });
                $msg = "Cotista {$nome} cadastrado. KYC e PLD ficam PENDENTES até a validação.";
            }
        }

        // 2) Validação KYC (Aprovar / Reprovar)
        elseif (!empty($_POST['kyc'])) {
            $cotistaId = (int)($_POST['cotista_id'] ?? 0);
            $novo = ($_POST['kyc'] === 'Aprovado') ? 'Aprovado' : 'Reprovado';
            $st = $pdo->prepare('SELECT * FROM cotistas WHERE id = ? AND fundo_id = ?');
            $st->execute([$cotistaId, $fid]);
            if ($c = $st->fetch()) {
                com_transacao($pdo, function () use ($pdo, $cotistaId, $fid, $novo, $c) {
                    $pdo->prepare('UPDATE cotistas SET kyc_status = ? WHERE id = ? AND fundo_id = ?')
                        ->execute([$novo, $cotistaId, $fid]);
                    registrar_auditoria($pdo, 'cotista_kyc', [
                        'entidade' => 'cotista', 'entidade_id' => $cotistaId, 'fundo_id' => $fid,
                        'detalhe'  => "KYC de {$c['nome']} → {$novo} (simulação)",
                    ]);
                });
                $msg = "KYC de {$c['nome']} atualizado para {$novo}.";
                $msgTipo = $novo === 'Aprovado' ? 'success' : 'warning';
            } else { $msg = 'Cotista não encontrado.'; $msgTipo = 'warning'; }
        }

        // 2b) Screening PLD (simulação): documento na lista de exemplo → Alerta; senão → OK
        elseif (!empty($_POST['pld'])) {
            $cotistaId = (int)($_POST['cotista_id'] ?? 0);
            $st = $pdo->prepare('SELECT * FROM cotistas WHERE id = ? AND fundo_id = ?');
            $st->execute([$cotistaId, $fid]);
            if ($c = $st->fetch()) {
                if ($_POST['pld'] === 'auto') {
                    // Simulação de screening: bate o documento contra uma lista de exemplo.
                    $novo = in_array(so_digitos($c['documento'] ?? ''), PLD_LISTA_EXEMPLO, true) ? 'Alerta' : 'OK';
                    $origem = 'screening simulado';
                } else {
                    $novo = ($_POST['pld'] === 'Alerta') ? 'Alerta' : 'OK';
                    $origem = 'decisão manual';
                }
                com_transacao($pdo, function () use ($pdo, $cotistaId, $fid, $novo, $origem, $c) {
                    $pdo->prepare('UPDATE cotistas SET pld_status = ? WHERE id = ? AND fundo_id = ?')
                        ->execute([$novo, $cotistaId, $fid]);
                    registrar_auditoria($pdo, 'cotista_pld', [
                        'entidade' => 'cotista', 'entidade_id' => $cotistaId, 'fundo_id' => $fid,
                        'detalhe'  => "PLD de {$c['nome']} → {$novo} ({$origem})",
                    ]);
                });
                $msg = "PLD de {$c['nome']} → {$novo} ({$origem})." .
                       ($novo === 'Alerta' ? ' Encaminhar para análise reforçada do compliance.' : '');
                $msgTipo = $novo === 'Alerta' ? 'danger' : 'success';
            } else { $msg = 'Cotista não encontrado.'; $msgTipo = 'warning'; }
        }
    } catch (Throwable $e) {
        $msg = 'Não foi possível concluir: ' . $e->getMessage(); $msgTipo = 'danger';
    }
}

// ---------- Dados ----------
$cotistas = [];
if ($fundo) {
    $st = $pdo->prepare('SELECT * FROM cotistas WHERE fundo_id = ? ORDER BY id DESC');
    $st->execute([$fid]);
    $cotistas = $st->fetchAll();
}

// KPIs
$total = count($cotistas);
$pendKyc = 0; $alertasPld = 0; $aptos = 0;
foreach ($cotistas as $c) {
    if (($c['kyc_status'] ?? 'Pendente') === 'Pendente') $pendKyc++;
    if (($c['pld_status'] ?? '') === 'Alerta') $alertasPld++;
    $apto = ($c['kyc_status'] ?? '') === 'Aprovado' && ($c['pld_status'] ?? '') === 'OK' && !empty($c['termo_aceite']);
    if ($apto) $aptos++;
}

$publicoAlvo = $fundo['publico_alvo'] ?? 'Investidores em geral';

// Mapeia badge de PLD (OK/Alerta/Pendente) para cor.
function badge_pld(?string $s): string {
    $s = $s ?: 'Pendente';
    $cor = ['OK' => 'success', 'Alerta' => 'danger', 'Pendente' => 'warning'][$s] ?? 'secondary';
    return badge($s, $cor);
}
// Mapeia badge de KYC (Aprovado/Reprovado/Pendente) para cor.
function badge_kyc(?string $s): string {
    $s = $s ?: 'Pendente';
    $cor = ['Aprovado' => 'success', 'Reprovado' => 'danger', 'Pendente' => 'warning'][$s] ?? 'secondary';
    return badge($s, $cor);
}

page_start('Onboarding de cotistas', 'Onboarding de cotistas', $u,
    'Cadastro do passivo com KYC, suitability e PLD — Res. CVM 175 (adesão/suitability) e Res. CVM 50 (PLD/FT)');
?>

<?php if ($msg): ?><div class="alert alert-<?= $msgTipo ?> py-2"><i class="bi bi-info-circle me-1"></i><?= e_html($msg) ?></div><?php endif; ?>

<div class="alert alert-warning py-2" style="font-size:.82rem">
  <i class="bi bi-exclamation-triangle me-1"></i>
  <b>Simulação.</b> KYC e PLD aqui são <b>demonstrativos</b>. Em produção: validação documental,
  screening em listas de sanções/PEP (OFAC, ONU, CVM/BACEN), suitability real conforme a regulação e
  a autorregulação ANBIMA. O screening de PLD abaixo apenas confere o documento contra uma <i>lista de exemplo</i>.
</div>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <form method="get">
    <select name="fundo_id" class="form-select form-select-sm" onchange="this.form.submit()">
      <?php foreach ($fundos as $f): ?>
        <option value="<?= (int)$f['id'] ?>" <?= (int)$f['id'] === $fid ? 'selected' : '' ?>><?= e_html($f['nome']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <div style="font-size:.82rem" class="text-muted">
    Público-alvo do fundo: <?= badge($publicoAlvo, $publicoAlvo === 'Investidores em geral' ? 'secondary' : 'info') ?>
  </div>
</div>

<div class="row row-cols-2 row-cols-md-4 g-3 mb-4">
  <?= kpi('Cotistas', (string)$total, 'bi-people') ?>
  <?= kpi('Pendentes de KYC', (string)$pendKyc, 'bi-hourglass-split') ?>
  <?= kpi('Alertas PLD', (string)$alertasPld, 'bi-shield-exclamation') ?>
  <?= kpi('Aptos', (string)$aptos, 'bi-check-circle', 'KYC Aprovado + PLD OK + termo') ?>
</div>

<?php if (!$fundo): ?>
  <div class="alert alert-info">Nenhum fundo ativo disponível.</div>
<?php else: ?>

<div class="row g-3 mb-4">
  <!-- Formulário de onboarding -->
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-person-plus me-1"></i> Onboarding de novo cotista</div>
      <div class="card-body">
        <form method="post">
          <?= csrf_campo() ?><?= nonce_campo() ?>
          <label class="form-label" style="font-size:.8rem">Nome / razão social</label>
          <input name="nome" class="form-control form-control-sm mb-2" placeholder="Nome completo ou razão social" required>

          <div class="d-flex gap-2 mb-2">
            <div class="flex-grow-1">
              <label class="form-label" style="font-size:.8rem">Documento (CPF/CNPJ)</label>
              <input name="documento" class="form-control form-control-sm" placeholder="000.000.000-00" required>
            </div>
            <div style="max-width:90px">
              <label class="form-label" style="font-size:.8rem">Tipo</label>
              <select name="tipo_pessoa" class="form-select form-select-sm">
                <option value="PF">PF</option>
                <option value="PJ">PJ</option>
              </select>
            </div>
          </div>

          <label class="form-label" style="font-size:.8rem">Suitability (perfil do investidor)</label>
          <select name="suitability" class="form-select form-select-sm mb-2" required>
            <option value="">Selecione…</option>
            <?php foreach (SUITABILITY_OPCOES as $s): ?>
              <option value="<?= $s ?>"><?= $s ?></option>
            <?php endforeach; ?>
          </select>

          <label class="form-label" style="font-size:.8rem">FATCA / CRS</label>
          <select name="fatca_crs" class="form-select form-select-sm mb-2">
            <option value="">Não informado</option>
            <?php foreach (FATCA_CRS_OPCOES as $fc): ?>
              <option value="<?= $fc ?>"><?= $fc ?></option>
            <?php endforeach; ?>
          </select>

          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="termo" id="termo" value="1">
            <label class="form-check-label" for="termo" style="font-size:.8rem">
              Registro do aceite do <b>termo de adesão</b> e ciência de risco (grava data/hora do aceite).
            </label>
          </div>

          <button name="onboard" value="1" class="btn btn-sm btn-dark w-100">
            <i class="bi bi-check-lg me-1"></i>Cadastrar cotista
          </button>
        </form>
        <p class="text-muted mb-0 mt-2" style="font-size:.72rem">
          O cadastro entra com <b>KYC Pendente</b> e <b>PLD Pendente</b>. Valide na tabela ao lado.
        </p>
      </div>
    </div>
  </div>

  <!-- Compatibilidade suitability × público-alvo (informativo) -->
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-ui-checks me-1"></i> Suitability × público-alvo (informativo)</div>
      <div class="card-body">
        <p style="font-size:.82rem" class="mb-2">
          Este fundo é destinado a <?= badge($publicoAlvo, $publicoAlvo === 'Investidores em geral' ? 'secondary' : 'info') ?>.
          Cotistas com perfil incompatível são <b>sinalizados</b> abaixo — alerta apenas informativo (não bloqueia).
        </p>
        <table class="table table-sm align-middle mb-0" style="font-size:.82rem">
          <thead><tr><th>Cotista</th><th>Suitability</th><th class="text-center">Compatível?</th></tr></thead>
          <tbody>
          <?php foreach ($cotistas as $c):
              $compat = suitability_compativel($publicoAlvo, $c['suitability'] ?? null); ?>
            <tr>
              <td><?= e_html($c['nome']) ?></td>
              <td><?= $c['suitability'] ? badge($c['suitability'], 'secondary') : '<span class="text-muted">—</span>' ?></td>
              <td class="text-center">
                <?php if ($compat): ?>
                  <span class="text-success"><i class="bi bi-check-circle-fill"></i></span>
                <?php else: ?>
                  <span class="text-danger" title="Perfil incompatível com o público-alvo do fundo">
                    <i class="bi bi-exclamation-triangle-fill"></i> incompatível
                  </span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$cotistas): ?><tr><td colspan="3" class="text-muted text-center py-3">Sem cotistas cadastrados.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="card-footer text-muted" style="font-size:.72rem">
        Regra didática: <b>Qualificados</b> exige perfil Moderado/Arrojado; <b>Profissionais</b>, Arrojado.
        Em produção, a adequação combina suitability, qualificação do investidor e política do fundo.
      </div>
    </div>
  </div>
</div>

<!-- Lista de cotistas + validação KYC/PLD -->
<div class="card">
  <div class="card-header"><i class="bi bi-clipboard-check me-1"></i> Cotistas — validação KYC / PLD</div>
  <div class="card-body p-0">
    <div class="table-responsive">
    <table class="table table-hover align-middle mb-0" style="font-size:.82rem">
      <thead><tr>
        <th>Cotista</th><th>Documento</th><th>Suitability</th>
        <th class="text-center">KYC</th><th class="text-center">PLD</th>
        <th class="text-center">Termo</th><th class="text-center">Status geral</th>
        <th class="text-end">Ações</th>
      </tr></thead>
      <tbody>
      <?php foreach ($cotistas as $c):
          $kyc = $c['kyc_status'] ?: 'Pendente';
          $pld = $c['pld_status'] ?: 'Pendente';
          $temTermo = !empty($c['termo_aceite']);
          $apto = $kyc === 'Aprovado' && $pld === 'OK' && $temTermo;
          $compat = suitability_compativel($publicoAlvo, $c['suitability'] ?? null); ?>
        <tr>
          <td>
            <b><?= e_html($c['nome']) ?></b>
            <span class="text-muted" style="font-size:.72rem"><?= e_html($c['tipo_pessoa']) ?></span>
            <?php if (!$compat): ?>
              <span class="badge badge-soft-warning" title="Suitability incompatível com o público-alvo do fundo">perfil incompatível</span>
            <?php endif; ?>
          </td>
          <td style="white-space:nowrap"><?= e_html($c['documento']) ?></td>
          <td><?= $c['suitability'] ? badge($c['suitability'], 'secondary') : '<span class="text-muted">—</span>' ?></td>
          <td class="text-center"><?= badge_kyc($kyc) ?></td>
          <td class="text-center"><?= badge_pld($pld) ?></td>
          <td class="text-center">
            <?= $temTermo
                ? '<span class="text-success" title="' . e_html(data_br($c['termo_aceite'])) . '"><i class="bi bi-check-lg"></i></span>'
                : '<span class="text-danger"><i class="bi bi-x-lg"></i></span>' ?>
          </td>
          <td class="text-center"><?= $apto ? badge('Apto', 'success') : badge('Pendências', 'warning') ?></td>
          <td class="text-end" style="white-space:nowrap">
            <div class="d-flex gap-1 justify-content-end flex-wrap">
              <!-- KYC -->
              <form method="post" class="d-inline">
                <?= csrf_campo() ?><?= nonce_campo() ?>
                <input type="hidden" name="cotista_id" value="<?= (int)$c['id'] ?>">
                <button name="kyc" value="Aprovado" class="btn btn-sm btn-outline-success py-0" title="Aprovar KYC (simulação)"><i class="bi bi-check-lg"></i> KYC</button>
              </form>
              <form method="post" class="d-inline">
                <?= csrf_campo() ?><?= nonce_campo() ?>
                <input type="hidden" name="cotista_id" value="<?= (int)$c['id'] ?>">
                <button name="kyc" value="Reprovado" class="btn btn-sm btn-outline-danger py-0" title="Reprovar KYC (simulação)"><i class="bi bi-x-lg"></i> KYC</button>
              </form>
              <!-- PLD -->
              <form method="post" class="d-inline">
                <?= csrf_campo() ?><?= nonce_campo() ?>
                <input type="hidden" name="cotista_id" value="<?= (int)$c['id'] ?>">
                <button name="pld" value="auto" class="btn btn-sm btn-outline-secondary py-0" title="Rodar screening PLD simulado (confere documento na lista de exemplo)"><i class="bi bi-search"></i> Screening</button>
              </form>
              <form method="post" class="d-inline">
                <?= csrf_campo() ?><?= nonce_campo() ?>
                <input type="hidden" name="cotista_id" value="<?= (int)$c['id'] ?>">
                <button name="pld" value="OK" class="btn btn-sm btn-outline-success py-0" title="Marcar PLD OK (simulação)">PLD OK</button>
              </form>
              <form method="post" class="d-inline">
                <?= csrf_campo() ?><?= nonce_campo() ?>
                <input type="hidden" name="cotista_id" value="<?= (int)$c['id'] ?>">
                <button name="pld" value="Alerta" class="btn btn-sm btn-outline-danger py-0" title="Marcar PLD Alerta (simulação)">PLD Alerta</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$cotistas): ?>
        <tr><td colspan="8" class="text-muted text-center py-4">Sem cotistas — faça um onboarding para começar.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
    </div>
  </div>
  <div class="card-footer text-muted" style="font-size:.72rem">
    <b>Status geral "Apto"</b> = KYC Aprovado + PLD OK + termo de adesão aceito. O <b>Screening</b> é uma
    <b>simulação</b>: documentos na lista de exemplo (<?= e_html(implode(', ', array_slice(PLD_LISTA_EXEMPLO, 0, 3))) ?>…)
    disparam <i>Alerta</i>; os demais retornam <i>OK</i>. Em produção, o resultado viria de bases de
    sanções/PEP e de análise reforçada do compliance (Res. CVM 50).
  </div>
</div>

<?php endif; ?>
<?php page_end();
