<?php
// Fundos & Clientes — cadastro consultivo. Fundos novos NÃO nascem aqui:
// nascem pela solicitação do gestor (com o checklist documental CVM 175) e
// são analisados/lançados na aba Aberturas.
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

$u = exigir_perfil('admin');

// O vínculo gestor↔fundo (acesso multi-fundo) é gerido pelo próprio gestor principal
// em "Equipe do fundo" (convites + permissões). Esta tela é só consultiva.
$fundos = $pdo->query('SELECT * FROM fundos ORDER BY FIELD(status,"Ativo","Em abertura","Fechado","Encerrado"), pl_atual DESC')->fetchAll();
$usuarios = $pdo->query("SELECT us.*, f.nome fundo_nome FROM usuarios us LEFT JOIN fundos f ON f.id=us.fundo_id ORDER BY FIELD(us.perfil,'admin','gestor'), us.id")->fetchAll();
// contas de acesso do portal do cotista, com os vínculos (visão de supervisão)
$contasPortal = [];
try {
    $contasPortal = $pdo->query("SELECT cc.*, GROUP_CONCAT(f.nome ORDER BY f.id SEPARATOR ' · ') fundos_vinc
                                 FROM cotista_contas cc
                                 LEFT JOIN cotistas c ON c.conta_id = cc.id
                                 LEFT JOIN fundos f ON f.id = c.fundo_id
                                 GROUP BY cc.id ORDER BY cc.status, cc.criado_em DESC")->fetchAll();
} catch (Throwable $t) { /* tabela lazy pode não existir ainda */ }

page_start('Fundos & Clientes', 'Fundos & Clientes', $u,
    'Cadastro consultivo — a constituição de fundos passa pelo checklist documental e pela aba Aberturas');
?>

<div class="alert alert-light border d-flex justify-content-between align-items-center" style="font-size:.85rem">
  <span><i class="bi bi-info-circle me-1 text-primary"></i>
    Fundo novo <b>não se cria por atalho</b>: a gestora solicita pelo portal (com regulamento, políticas e demais
    documentos exigidos pela Res. CVM 175), a administradora analisa documento a documento e o lançamento
    acontece na aba <b>Aberturas</b> quando todos os checks estão aprovados.</span>
  <a class="btn btn-sm btn-dark" href="aberturas.php">Ir para Aberturas →</a>
</div>

<div class="card mb-4">
  <div class="card-header"><i class="bi bi-folder2 me-1"></i> Fundos sob administração</div>
  <div class="card-body p-0">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Fundo</th><th>Classe</th><th>Gestora</th><th class="text-end">PL</th>
        <th class="text-end">Taxas (adm / gestão / perf.)</th><th>Público-alvo</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($fundos as $f): ?>
        <tr>
          <td><b><?= e_html($f['nome']) ?></b><br><span class="text-muted" style="font-size:.75rem"><?= e_html($f['cnpj']) ?></span></td>
          <td><?= badge($f['classe'], 'secondary') ?></td>
          <td style="font-size:.83rem"><?= e_html($f['gestora']) ?></td>
          <td class="text-end"><?= moeda_compacta($f['pl_atual']) ?></td>
          <td class="text-end" style="font-size:.83rem">
            <?= pct((float)$f['taxa_adm'] * 100) ?> / <?= pct((float)$f['taxa_gestao'] * 100) ?> /
            <?= (float)$f['taxa_performance'] > 0 ? pct((float)$f['taxa_performance'] * 100, 0) . ' s/ CDI' : '—' ?>
            <br><span class="text-muted" style="font-size:.7rem">gestão e performance: definidas pelo gestor no regulamento</span></td>
          <td style="font-size:.83rem"><?= e_html($f['publico_alvo']) ?></td>
          <td><?= badge_status($f['status']) ?></td>
          <td class="text-end"><a class="btn btn-sm btn-outline-secondary" title="Ver dashboard do fundo"
                 href="<?= BASE_URL ?>gestor/index.php?fundo_id=<?= (int)$f['id'] ?>"><i class="bi bi-eye"></i></a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer text-muted" style="font-size:.75rem">
    Taxa de administração: 0,08% a.a. com piso de R$ 100/mês (única taxa padronizada — é a taxa da plataforma).
    As demais taxas são livremente pactuadas no regulamento de cada fundo.
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-people me-1"></i> Usuários da plataforma</div>
      <div class="card-body p-0">
        <table class="table table-hover mb-0" style="font-size:.86rem">
          <thead><tr><th>Nome</th><th>E-mail</th><th>Perfil</th><th>Gestora / Fundo</th><th>KYC/PLD</th></tr></thead>
          <tbody>
          <?php foreach ($usuarios as $us): ?>
            <tr>
              <td><b><?= e_html($us['nome']) ?></b></td>
              <td class="text-muted"><?= e_html($us['email']) ?></td>
              <td><?= badge($us['perfil'] === 'admin' ? 'Administradora' : 'Gestor', $us['perfil'] === 'admin' ? 'gold' : 'success') ?></td>
              <td style="font-size:.8rem"><?= e_html($us['gestora'] ?: '—') ?><br>
                <span class="text-muted" style="font-size:.72rem"><?= e_html($us['fundo_nome'] ?? 'todos os fundos') ?></span></td>
              <td><?= badge_status($us['kyc_status']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-person-badge me-1"></i> Contas do portal do cotista (visão de supervisão)</div>
      <div class="card-body p-0" style="max-height:340px;overflow-y:auto">
        <table class="table table-sm table-hover mb-0" style="font-size:.78rem">
          <thead><tr><th>Titular</th><th>E-mail</th><th>Fundos vinculados</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($contasPortal as $t): ?>
            <tr class="<?= $t['status'] === 'Bloqueada' ? 'text-muted' : '' ?>">
              <td><?= e_html($t['nome']) ?><br><span class="text-muted" style="font-size:.7rem"><?= e_html($t['documento']) ?></span></td>
              <td style="font-size:.72rem"><?= e_html($t['email']) ?></td>
              <td style="font-size:.72rem"><?= $t['fundos_vinc'] ? e_html($t['fundos_vinc']) : '<span class="text-muted">sem vínculo</span>' ?></td>
              <td><?= badge_status($t['status'] === 'Ativa' ? 'Ativo' : 'Encerrado') ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$contasPortal): ?><tr><td colspan="4" class="text-muted text-center py-3">Nenhuma conta criada ainda.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="card-footer text-muted" style="font-size:.72rem">O cotista se cadastra (ou o gestor cria o acesso); a transparência da carteira é uma política GLOBAL por fundo, definida pelo gestor. A administradora supervisiona.</div>
    </div>
  </div>
</div>
<?php page_end();
