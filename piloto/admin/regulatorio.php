<?php
// Regulatório CVM/ANBIMA — envios periódicos, ofícios recebidos e assembleias de cotistas
// Prazos reais: informe diário em 1 d.u.; balancete, CDA e perfil mensal em 10 d.u.; DFs auditadas anuais.
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

$u = exigir_perfil('admin');
$msg = ''; $msgTipo = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // enviar/reenviar obrigação ao regulador
    if (!empty($_POST['enviar'])) {
        $st = $pdo->prepare("SELECT e.*, f.nome fundo_nome FROM envios_regulatorios e JOIN fundos f ON f.id=e.fundo_id WHERE e.id = ?");
        $st->execute([(int)$_POST['enviar']]);
        if ($e = $st->fetch()) {
            // regra real: informe diário só sai com a cota do dia APROVADA pelo gestor
            if ($e['tipo'] === 'Informe Diário') {
                $fech = fechamento($pdo, (int)$e['fundo_id'], $e['competencia']);
                if (!$fech || !in_array($fech['status'], ['Aprovada', 'Republicada'], true)) {
                    $msg = 'Informe diário bloqueado: a cota de ' . data_br($e['competencia']) . ' ainda não foi aprovada pelo gestor.';
                    $msgTipo = 'danger';
                }
            }
            if ($msgTipo !== 'danger') {
                $protocolo = strtoupper($e['destino']) . '-' . date('Ymd') . '-' . str_pad((string)random_int(1, 99999), 5, '0', STR_PAD_LEFT);
                $pdo->prepare("UPDATE envios_regulatorios SET status='Enviado', protocolo=?, enviado_em=NOW(), mensagem=NULL WHERE id=?")
                    ->execute([$protocolo, $e['id']]);
                $pdo->prepare("INSERT INTO log_processamento (fundo_id, data_ref, etapa, nivel, mensagem) VALUES (?,?,?,?,?)")
                    ->execute([$e['fundo_id'], date('Y-m-d'), 'ANBIMA', 'INFO',
                               "{$e['tipo']} ({$e['competencia']}) enviado à {$e['destino']} — protocolo $protocolo"]);
                $msg = "{$e['tipo']} de {$e['fundo_nome']} enviado à {$e['destino']} — protocolo $protocolo.";
            }
        }
    }
    // responder / dar ciência em ofício
    elseif (!empty($_POST['responder_oficio']) && trim($_POST['resposta'] ?? '') !== '') {
        $pdo->prepare("UPDATE oficios_cvm SET status='Respondido', resposta=?, respondido_por=?, respondido_em=NOW() WHERE id=?")
            ->execute([trim($_POST['resposta']), $u['nome'], (int)$_POST['responder_oficio']]);
        $msg = 'Resposta protocolada junto ao regulador.';
    } elseif (!empty($_POST['ciencia_oficio'])) {
        $pdo->prepare("UPDATE oficios_cvm SET status='Ciente', respondido_por=?, respondido_em=NOW() WHERE id=?")
            ->execute([$u['nome'], (int)$_POST['ciencia_oficio']]);
        $msg = 'Ciência registrada.';
    }
    // assembleias: convocar / registrar resultado
    elseif (!empty($_POST['convocar'])) {
        $pdo->prepare("UPDATE assembleias SET status='Convocada', data_convocacao=CURDATE(), data_realizacao=DATE_ADD(CURDATE(), INTERVAL 30 DAY) WHERE id=? AND status='Solicitada'")
            ->execute([(int)$_POST['convocar']]);
        $msg = 'Assembleia convocada (realização em 30 dias, modo eletrônico) — cotistas serão comunicados.';
    } elseif (!empty($_POST['registrar_resultado']) && trim($_POST['resultado'] ?? '') !== '') {
        $pdo->prepare("UPDATE assembleias SET status='Realizada', resultado=?, quorum=?, data_realizacao=CURDATE() WHERE id=? AND status='Convocada'")
            ->execute([trim($_POST['resultado']), trim($_POST['quorum'] ?? 'maioria simples presente'), (int)$_POST['registrar_resultado']]);
        $msg = 'Resultado da assembleia registrado — ata disponível aos cotistas.';
    }
}

$envios = $pdo->query("SELECT e.*, f.nome fundo_nome FROM envios_regulatorios e JOIN fundos f ON f.id=e.fundo_id
                       ORDER BY FIELD(e.status,'Erro','Aguardando cota','Pendente','Enviado'), e.prazo, e.fundo_id")->fetchAll();
$pendEnvios = array_filter($envios, fn($e) => $e['status'] !== 'Enviado');
$atrasados = array_filter($pendEnvios, fn($e) => $e['prazo'] && $e['prazo'] < date('Y-m-d'));

$oficios = $pdo->query("SELECT o.*, f.nome fundo_nome FROM oficios_cvm o LEFT JOIN fundos f ON f.id=o.fundo_id
                        ORDER BY FIELD(o.status,'Recebido','Em resposta','Respondido','Ciente'), o.recebido_em DESC")->fetchAll();
$oficiosAbertos = array_filter($oficios, fn($o) => in_array($o['status'], ['Recebido', 'Em resposta'], true));

$assembleias = $pdo->query("SELECT a.*, f.nome fundo_nome FROM assembleias a JOIN fundos f ON f.id=a.fundo_id
                            ORDER BY FIELD(a.status,'Solicitada','Convocada','Realizada','Cancelada'), a.criado_em DESC")->fetchAll();

page_start('Regulatório CVM / ANBIMA', 'Regulatório CVM', $u,
    'Informe diário (1 d.u.) · balancete, CDA e perfil mensal (10 d.u.) · DFs anuais · ofícios recebidos · assembleias');
?>

<?php if ($msg): ?><div class="alert alert-<?= $msgTipo ?> py-2"><i class="bi bi-info-circle me-1"></i><?= e_html($msg) ?></div><?php endif; ?>

<div class="row row-cols-2 row-cols-md-4 g-3 mb-4">
  <?= kpi('Envios pendentes', (string)count($pendEnvios), 'bi-send',
          count($atrasados) ? '<span class="text-danger">' . count($atrasados) . ' fora do prazo</span>' : 'dentro dos prazos') ?>
  <?= kpi('Ofícios a tratar', (string)count($oficiosAbertos), 'bi-envelope-exclamation') ?>
  <?= kpi('Assembleias em curso', (string)count(array_filter($assembleias, fn($a) => in_array($a['status'], ['Solicitada', 'Convocada'], true))), 'bi-megaphone') ?>
  <?= kpi('Enviados no período', (string)count(array_filter($envios, fn($e) => $e['status'] === 'Enviado')), 'bi-check2-circle') ?>
</div>

<ul class="nav nav-tabs mb-3" role="tablist" style="font-size:.88rem">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#aba-envios">Envios ao regulador</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#aba-oficios">Ofícios recebidos <?= $oficiosAbertos ? '<span class="badge text-bg-danger ms-1">' . count($oficiosAbertos) . '</span>' : '' ?></button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#aba-assembleias">Assembleias de cotistas</button></li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="aba-envios">
    <div class="card">
      <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0" style="font-size:.83rem">
          <thead><tr><th>Fundo</th><th>Obrigação</th><th>Destino</th><th>Competência</th><th>Prazo</th><th>Status</th><th>Protocolo</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($envios as $e):
              $atrasado = $e['status'] !== 'Enviado' && $e['prazo'] && $e['prazo'] < date('Y-m-d'); ?>
            <tr class="<?= $atrasado ? 'table-danger' : '' ?>">
              <td><b><?= e_html($e['fundo_nome']) ?></b></td>
              <td><?= e_html($e['tipo']) ?></td>
              <td><?= badge($e['destino'], $e['destino'] === 'CVM' ? 'info' : 'secondary') ?></td>
              <td><?= e_html($e['competencia']) ?></td>
              <td><?= data_br($e['prazo']) ?> <?= $atrasado ? badge('atrasado', 'danger') : '' ?></td>
              <td><?= badge_status($e['status'] === 'Aguardando cota' ? 'Pendente' : $e['status']) ?>
                <?= $e['status'] === 'Aguardando cota' ? '<br><span class="text-muted" style="font-size:.7rem">aguardando aprovação da cota pelo gestor</span>' : '' ?>
                <?= $e['mensagem'] ? '<br><span class="text-danger" style="font-size:.7rem">' . e_html($e['mensagem']) . '</span>' : '' ?></td>
              <td class="text-muted" style="font-size:.76rem"><?= e_html($e['protocolo'] ?? '—') ?>
                <?= $e['enviado_em'] ? '<br>' . date('d/m H:i', strtotime($e['enviado_em'])) : '' ?></td>
              <td class="text-end">
                <?php if ($e['status'] !== 'Enviado'): ?>
                  <form method="post"><input type="hidden" name="enviar" value="<?= (int)$e['id'] ?>">
                    <button class="btn btn-sm btn-dark"><i class="bi bi-send me-1"></i><?= $e['status'] === 'Erro' ? 'Reenviar' : 'Enviar' ?></button></form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="card-footer text-muted" style="font-size:.72rem">
        Prazos da Res. CVM 175: informe diário em 1 dia útil; balancete, CDA (composição e diversificação) e perfil mensal
        em 10 dias úteis do fim do mês; demonstrações contábeis auditadas anualmente. O informe diário só é liberado
        para envio depois que o gestor aprova a cota do dia.
      </div>
    </div>
  </div>

  <div class="tab-pane fade" id="aba-oficios">
    <?php foreach ($oficios as $o): ?>
      <div class="card mb-3 <?= in_array($o['status'], ['Recebido', 'Em resposta'], true) ? 'border-danger' : '' ?>">
        <div class="card-body py-3" style="font-size:.87rem">
          <div class="d-flex justify-content-between flex-wrap gap-2">
            <div>
              <?= badge($o['origem'], 'info') ?> <b><?= e_html($o['numero']) ?></b> — <?= e_html($o['assunto']) ?>
              <span class="text-muted">· <?= $o['fundo_nome'] ? e_html($o['fundo_nome']) : 'todos os fundos' ?></span>
            </div>
            <div><?= badge_status($o['status']) ?>
              <?php if ($o['prazo_resposta'] && in_array($o['status'], ['Recebido', 'Em resposta'], true)): ?>
                <?= badge('prazo: ' . data_br($o['prazo_resposta']), $o['prazo_resposta'] < date('Y-m-d', strtotime('+5 days')) ? 'danger' : 'warning') ?>
              <?php endif; ?></div>
          </div>
          <p class="text-muted mb-2 mt-2" style="font-size:.83rem"><?= e_html($o['teor']) ?></p>
          <?php if (in_array($o['status'], ['Recebido', 'Em resposta'], true)): ?>
            <?php if ($o['prazo_resposta']): ?>
              <form method="post" class="d-flex gap-2">
                <input type="hidden" name="responder_oficio" value="<?= (int)$o['id'] ?>">
                <input class="form-control form-control-sm" name="resposta" placeholder="Resposta ao regulador (será protocolada)…" required>
                <button class="btn btn-sm btn-dark"><i class="bi bi-reply me-1"></i>Protocolar resposta</button>
              </form>
            <?php else: ?>
              <form method="post"><input type="hidden" name="ciencia_oficio" value="<?= (int)$o['id'] ?>">
                <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-check me-1"></i>Registrar ciência</button></form>
            <?php endif; ?>
          <?php elseif ($o['resposta']): ?>
            <div class="p-2 rounded" style="background:#f0fdf4;border-left:3px solid #16a34a;font-size:.8rem">
              <b>Resposta:</b> <?= e_html($o['resposta']) ?>
              <span class="text-muted">— <?= e_html($o['respondido_por']) ?>, <?= $o['respondido_em'] ? date('d/m/Y H:i', strtotime($o['respondido_em'])) : '' ?></span>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$oficios): ?><p class="text-muted text-center py-4">Nenhum ofício recebido.</p><?php endif; ?>
  </div>

  <div class="tab-pane fade" id="aba-assembleias">
    <div class="card">
      <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0" style="font-size:.84rem">
          <thead><tr><th>Fundo</th><th>Tipo</th><th>Pauta</th><th>Origem</th><th>Datas</th><th>Status</th><th style="min-width:280px">Ação / Resultado</th></tr></thead>
          <tbody>
          <?php foreach ($assembleias as $a): ?>
            <tr>
              <td><b><?= e_html($a['fundo_nome']) ?></b></td>
              <td><?= badge($a['tipo'], 'secondary') ?> <span class="text-muted" style="font-size:.7rem"><?= e_html($a['modo']) ?></span></td>
              <td style="font-size:.82rem"><?= e_html($a['pauta']) ?></td>
              <td style="font-size:.78rem"><?= e_html($a['origem']) ?><br><span class="text-muted" style="font-size:.7rem"><?= e_html($a['criado_por']) ?></span></td>
              <td style="font-size:.78rem">
                <?= $a['data_convocacao'] ? 'convocada ' . data_br($a['data_convocacao']) : '—' ?><br>
                <?= $a['data_realizacao'] ? 'realização ' . data_br($a['data_realizacao']) : '' ?></td>
              <td><?= badge_status($a['status'] === 'Solicitada' ? 'Pendente' : ($a['status'] === 'Convocada' ? 'Em andamento' : ($a['status'] === 'Realizada' ? 'OK' : 'Encerrado'))) ?>
                <br><span class="text-muted" style="font-size:.7rem"><?= e_html($a['status']) ?></span></td>
              <td>
                <?php if ($a['status'] === 'Solicitada'): ?>
                  <form method="post"><input type="hidden" name="convocar" value="<?= (int)$a['id'] ?>">
                    <button class="btn btn-sm btn-dark"><i class="bi bi-megaphone me-1"></i>Convocar assembleia</button></form>
                <?php elseif ($a['status'] === 'Convocada'): ?>
                  <form method="post" class="d-flex gap-1 flex-wrap">
                    <input type="hidden" name="registrar_resultado" value="<?= (int)$a['id'] ?>">
                    <input class="form-control form-control-sm" name="resultado" placeholder="Resultado da deliberação…" required style="max-width:180px">
                    <input class="form-control form-control-sm" name="quorum" placeholder="quórum" style="max-width:90px">
                    <button class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i></button>
                  </form>
                <?php else: ?>
                  <span style="font-size:.8rem"><?= e_html($a['resultado'] ?? '—') ?></span>
                  <?= $a['quorum'] ? '<br><span class="text-muted" style="font-size:.7rem">quórum: ' . e_html($a['quorum']) . '</span>' : '' ?>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$assembleias): ?><tr><td colspan="7" class="text-muted text-center py-4">Nenhuma assembleia.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="card-footer text-muted" style="font-size:.72rem">
        Alteração de regulamento, substituição de prestadores essenciais e mudanças de taxa dependem de assembleia de
        cotistas (Res. CVM 175 — pode ser 100% eletrônica). O gestor solicita pela aba Assembleias do portal dele;
        a administradora convoca, conduz e registra a ata.
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php page_end();
