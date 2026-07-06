<?php
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

$u = exigir_perfil('admin');
$msg = '';

// AÇÕES REAIS: comentar em fundo, responder chamado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['comentar_fundo']) && !empty($_POST['texto'])) {
        $pdo->prepare('INSERT INTO comentarios (fundo_id, autor, texto) VALUES (?,?,?)')
            ->execute([(int)$_POST['comentar_fundo'], $u['nome'], trim($_POST['texto'])]);
        $msg = 'Comentário registrado.';
    } elseif (!empty($_POST['responder_chamado']) && !empty($_POST['resposta'])) {
        $pdo->prepare("UPDATE chamados SET status='Respondido', resposta=?, respondido_por=?, respondido_em=NOW() WHERE id=?")
            ->execute([trim($_POST['resposta']), $u['nome'], (int)$_POST['responder_chamado']]);
        $msg = 'Chamado respondido — o cliente já vê a resposta na área dele.';
    }
}

$dataBatch = $pdo->query('SELECT MAX(data_ref) FROM processamento')->fetchColumn();

// fila unificada de pendências
$pendencias = [];
$st = $pdo->prepare("SELECT p.*, f.nome fundo_nome FROM processamento p JOIN fundos f ON f.id=p.fundo_id
                     WHERE p.data_ref=? AND p.status='Erro'");
$st->execute([$dataBatch]);
foreach ($st->fetchAll() as $r)
    $pendencias[] = ['tipo' => 'Erro de batch', 'cor' => 'danger', 'fundo' => $r['fundo_nome'],
                     'detalhe' => $r['etapa'] . ': ' . $r['mensagem'], 'link' => 'processamento.php'];
foreach ($pdo->query("SELECT fe.*, f.nome fundo_nome FROM fechamentos fe JOIN fundos f ON f.id=fe.fundo_id
                      WHERE fe.status='Rejeitada'
                        AND fe.versao=(SELECT MAX(versao) FROM fechamentos x WHERE x.fundo_id=fe.fundo_id AND x.data_ref=fe.data_ref)") as $r)
    $pendencias[] = ['tipo' => 'Cota rejeitada', 'cor' => 'danger', 'fundo' => $r['fundo_nome'],
                     'detalhe' => data_br($r['data_ref']) . ' — ' . ($r['motivo'] ?: 'sem motivo registrado') . ' (corrigir e reprocessar)',
                     'link' => 'lancamentos.php?fundo_id=' . $r['fundo_id'] . '&data=' . $r['data_ref']];
foreach ($pdo->query("SELECT c.*, f.nome fundo_nome FROM conciliacao c JOIN fundos f ON f.id=c.fundo_id WHERE c.situacao='Divergente'") as $r)
    $pendencias[] = ['tipo' => 'Divergência', 'cor' => 'danger', 'fundo' => $r['fundo_nome'],
                     'detalhe' => $r['origem'] . ' — ' . moeda($r['valor_diferenca']), 'link' => 'conciliacao.php'];
foreach ($pdo->query("SELECT a.*, f.nome fundo_nome FROM alertas_fraude a JOIN fundos f ON f.id=a.fundo_id WHERE a.status IN ('Aberto','Em revisão')") as $r)
    $pendencias[] = ['tipo' => 'Alerta IA ' . $r['regra'], 'cor' => 'warning', 'fundo' => $r['fundo_nome'],
                     'detalhe' => $r['tipo'] . ' (' . $r['severidade'] . ')', 'link' => 'fraude.php'];
foreach ($pdo->query("SELECT DISTINCT a.codigo, f.nome fundo_nome FROM ativos_carteira a
                      JOIN fundos f ON f.id=a.fundo_id
                      JOIN (SELECT fundo_id, MAX(data_ref) md FROM ativos_carteira GROUP BY fundo_id) ult
                        ON ult.fundo_id=a.fundo_id AND ult.md=a.data_ref
                      WHERE a.fonte_preco='Comitê'") as $r)
    $pendencias[] = ['tipo' => 'Preço do comitê', 'cor' => 'info', 'fundo' => $r['fundo_nome'],
                     'detalhe' => 'Validar preço de ' . $r['codigo'], 'link' => 'carteiras.php'];
foreach ($pdo->query("SELECT e.*, f.nome fundo_nome FROM envios_regulatorios e JOIN fundos f ON f.id=e.fundo_id
                      WHERE e.status <> 'Enviado'") as $r)
    $pendencias[] = ['tipo' => 'Envio ' . $r['destino'], 'cor' => ($r['prazo'] && $r['prazo'] < date('Y-m-d')) ? 'danger' : 'info',
                     'fundo' => $r['fundo_nome'],
                     'detalhe' => $r['tipo'] . ' (' . $r['competencia'] . ')' . (($r['prazo'] && $r['prazo'] < date('Y-m-d')) ? ' — FORA DO PRAZO' : ''),
                     'link' => 'regulatorio.php'];
foreach ($pdo->query("SELECT o.*, f.nome fundo_nome FROM oficios_cvm o LEFT JOIN fundos f ON f.id=o.fundo_id
                      WHERE o.status IN ('Recebido','Em resposta')") as $r)
    $pendencias[] = ['tipo' => 'Ofício ' . $r['origem'], 'cor' => 'danger', 'fundo' => $r['fundo_nome'] ?? 'Geral',
                     'detalhe' => $r['numero'] . ' — ' . $r['assunto'] . ($r['prazo_resposta'] ? ' (prazo ' . data_br($r['prazo_resposta']) . ')' : ''),
                     'link' => 'regulatorio.php'];
foreach ($pdo->query("SELECT a.*, f.nome fundo_nome FROM assembleias a JOIN fundos f ON f.id=a.fundo_id WHERE a.status='Solicitada'") as $r)
    $pendencias[] = ['tipo' => 'Assembleia', 'cor' => 'warning', 'fundo' => $r['fundo_nome'],
                     'detalhe' => 'Solicitação do gestor: ' . $r['pauta'], 'link' => 'regulatorio.php'];
foreach ($pdo->query("SELECT b.*, f.nome fundo_nome FROM boletas b JOIN fundos f ON f.id=b.fundo_id WHERE b.status='Enviada'") as $r)
    $pendencias[] = ['tipo' => 'Boleta do gestor', 'cor' => 'warning', 'fundo' => $r['fundo_nome'],
                     'detalhe' => $r['operacao'] . ' de ' . $r['ativo_codigo'] . ' (' . moeda($r['valor']) . ') aguardando aceite da custódia',
                     'link' => '#'];
foreach ($pdo->query("SELECT l.*, f.nome fundo_nome FROM liquidacoes l JOIN fundos f ON f.id=l.fundo_id WHERE l.status='Pendente'") as $r)
    $pendencias[] = ['tipo' => 'Liquidação', 'cor' => 'info', 'fundo' => $r['fundo_nome'],
                     'detalhe' => $r['operacao'] . ' de ' . $r['ativo_codigo'] . ' liquida em ' . data_br($r['data_liquidacao']),
                     'link' => 'custodia.php'];
foreach ($pdo->query("SELECT e.*, f.nome fundo_nome FROM eventos_corporativos e JOIN fundos f ON f.id=e.fundo_id WHERE e.status <> 'Liquidado'") as $r)
    $pendencias[] = ['tipo' => 'Evento corporativo', 'cor' => 'info', 'fundo' => $r['fundo_nome'],
                     'detalhe' => $r['tipo'] . ' de ' . $r['ativo_codigo'] . ' — ' . $r['status'] . ', pagamento ' . data_br($r['data_pagamento']),
                     'link' => 'custodia.php'];

$chamados = $pdo->query("SELECT ch.*, f.nome fundo_nome, us.nome usuario_nome FROM chamados ch
                         JOIN fundos f ON f.id=ch.fundo_id JOIN usuarios us ON us.id=ch.usuario_id
                         ORDER BY FIELD(ch.status,'Aberto','Em análise','Respondido'), ch.criado_em DESC")->fetchAll();
foreach ($chamados as $r)
    if ($r['status'] === 'Aberto')
        $pendencias[] = ['tipo' => 'Chamado', 'cor' => 'info', 'fundo' => $r['fundo_nome'],
                         'detalhe' => $r['assunto'] . ' (' . $r['usuario_nome'] . ')', 'link' => '#chamados'];

$fundos = $pdo->query("SELECT id, nome FROM fundos ORDER BY nome")->fetchAll();
$comentarios = $pdo->query("SELECT c.*, f.nome fundo_nome FROM comentarios c JOIN fundos f ON f.id=c.fundo_id
                            ORDER BY c.criado_em DESC LIMIT 15")->fetchAll();

page_start('Pendências & Comentários', 'Pendências', $u, 'Tudo que precisa de ação humana hoje, em um lugar só');
?>

<?php if ($msg): ?><div class="alert alert-success py-2"><i class="bi bi-check-circle me-1"></i><?= e_html($msg) ?></div><?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between">
        <span><i class="bi bi-list-task me-1"></i> Fila de pendências do dia</span>
        <?= badge(count($pendencias) . ' itens', count($pendencias) ? 'warning' : 'success') ?>
      </div>
      <div class="card-body p-0" style="max-height:420px;overflow-y:auto">
        <?php foreach ($pendencias as $p): ?>
          <a href="<?= $p['link'] ?>" class="d-flex justify-content-between align-items-center p-2 px-3 border-bottom link-limpo text-body">
            <div style="font-size:.86rem">
              <?= badge($p['tipo'], $p['cor']) ?> <b><?= e_html($p['fundo']) ?></b><br>
              <span class="text-muted" style="font-size:.8rem"><?= e_html($p['detalhe']) ?></span>
            </div>
            <i class="bi bi-chevron-right text-muted"></i>
          </a>
        <?php endforeach; ?>
        <?php if (!$pendencias): ?><p class="text-success text-center py-4 mb-0"><i class="bi bi-check-circle me-1"></i>Nenhuma pendência — dia limpo.</p><?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-chat-left-text me-1"></i> Comentários operacionais</div>
      <div class="card-body">
        <form method="post" class="mb-3">
          <div class="d-flex gap-2 mb-2">
            <select name="comentar_fundo" class="form-select form-select-sm" required>
              <option value="">Fundo…</option>
              <?php foreach ($fundos as $f): ?><option value="<?= (int)$f['id'] ?>"><?= e_html($f['nome']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="d-flex gap-2">
            <input class="form-control form-control-sm" name="texto" placeholder="Ex.: aguardando preço da debênture X…" required>
            <button class="btn btn-sm btn-dark"><i class="bi bi-plus-lg"></i></button>
          </div>
        </form>
        <div style="max-height:300px;overflow-y:auto">
          <?php foreach ($comentarios as $c): ?>
            <div class="border-bottom py-2" style="font-size:.82rem">
              <b><?= e_html($c['fundo_nome']) ?></b>
              <span class="text-muted float-end" style="font-size:.72rem"><?= date('d/m H:i', strtotime($c['criado_em'])) ?></span><br>
              <?= e_html($c['texto']) ?> <span class="text-muted">— <?= e_html($c['autor']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card" id="chamados">
  <div class="card-header"><i class="bi bi-ticket-detailed me-1"></i> Chamados dos clientes</div>
  <div class="card-body p-0">
    <table class="table align-middle mb-0">
      <thead><tr><th>Quando</th><th>Fundo / Solicitante</th><th>Assunto</th><th>Status</th><th style="min-width:300px">Resposta</th></tr></thead>
      <tbody>
      <?php foreach ($chamados as $ch): ?>
        <tr>
          <td class="text-muted" style="font-size:.8rem"><?= date('d/m/Y H:i', strtotime($ch['criado_em'])) ?></td>
          <td><b><?= e_html($ch['fundo_nome']) ?></b><br><span class="text-muted" style="font-size:.78rem"><?= e_html($ch['usuario_nome']) ?></span></td>
          <td style="font-size:.86rem"><b><?= e_html($ch['assunto']) ?></b><br><span class="text-muted" style="font-size:.8rem"><?= e_html($ch['mensagem']) ?></span></td>
          <td><?= badge_status($ch['status']) ?></td>
          <td>
            <?php if ($ch['status'] === 'Respondido'): ?>
              <span style="font-size:.82rem"><?= e_html($ch['resposta']) ?></span>
              <div class="text-muted" style="font-size:.72rem">— <?= e_html($ch['respondido_por']) ?></div>
            <?php else: ?>
              <form method="post" class="d-flex gap-1">
                <input type="hidden" name="responder_chamado" value="<?= (int)$ch['id'] ?>">
                <input class="form-control form-control-sm" name="resposta" placeholder="Responder ao cliente…" required>
                <button class="btn btn-sm btn-success"><i class="bi bi-send"></i></button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$chamados): ?><tr><td colspan="5" class="text-muted text-center py-4">Sem chamados.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php page_end();
