<?php
// Layout compartilhado: head + sidebar (menu por perfil) + topo | rodapé
require_once __DIR__ . '/helpers.php';

function menu_itens(array $u): array {
    $b = base_url();
    if ($u['perfil'] === 'admin') {
        return [
            ['Painel geral',        $b.'admin/index.php',          'bi-speedometer2'],
            ['Processamento & Cota', $b.'admin/processamento.php', 'bi-cpu'],
            ['Processamento em lote', $b.'admin/batch.php',        'bi-diagram-3-fill'],
            ['Lançamentos & Ajustes', $b.'admin/lancamentos.php',  'bi-pencil-square'],
            ['Custódia & Liquidação', $b.'admin/custodia.php',     'bi-safe2'],
            ['Conciliação',         $b.'admin/conciliacao.php',    'bi-check2-square'],
            ['Contabilidade',       $b.'admin/contabilidade.php',  'bi-clipboard-data'],
            ['Precificação',        $b.'admin/precificacao.php',   'bi-tag'],
            ['Regulatório CVM',     $b.'admin/regulatorio.php',    'bi-send-check'],
            ['Passivo & Tributação', $b.'admin/passivo.php',       'bi-receipt'],
            ['Onboarding de cotistas', $b.'admin/onboarding_cotista.php', 'bi-person-plus'],
            ['Base de instrumentos', $b.'admin/ativos.php',        'bi-database'],
            ['Contrapartes',        $b.'admin/contrapartes.php',   'bi-people'],
            ['Suporte',             $b.'admin/tickets.php',        'bi-life-preserver'],
            ['Aberturas de fundos', $b.'admin/aberturas.php',      'bi-rocket-takeoff'],
            ['Classes de cotas',    $b.'admin/classes.php',        'bi-collection'],
            ['Classes & Subclasses', $b.'admin/subclasses.php',    'bi-diagram-3'],
            ['FIP / Private Equity', $b.'admin/fip.php',           'bi-building-gear'],
            ['Carteiras',           $b.'admin/carteiras.php',      'bi-collection'],
            ['Pendências',          $b.'admin/pendencias.php',     'bi-list-task'],
            ['IA · Fraude',         $b.'admin/fraude.php',         'bi-shield-exclamation'],
            ['Repasses',            $b.'admin/repasses.php',       'bi-cash-stack'],
            ['Fundos & Clientes',   $b.'admin/fundos.php',         'bi-folder-plus'],
        ];
    }
    if ($u['perfil'] === 'custodia') {
        return [
            ['Painel da custódia',    $b.'custodia/index.php',      'bi-safe2'],
            ['Mensageria SPB',        $b.'custodia/mensageria.php', 'bi-envelope-arrow-down'],
            ['Instruções & Liquidação', $b.'custodia/instrucoes.php', 'bi-arrow-left-right'],
            ['Arquivos & Extratos',   $b.'custodia/arquivos.php',   'bi-file-earmark-zip'],
            ['Trilha de auditoria',   $b.'custodia/auditoria.php',  'bi-shield-lock'],
        ];
    }
    // gestor — menu filtrado por permissões do fundo em foco (principal vê tudo)
    global $pdo;
    $itens = [
        ['Visão geral',       $b.'gestor/index.php',        'bi-speedometer2'],
        ['Aprovação de cota', $b.'gestor/cotas.php',        'bi-clipboard-check'],
        ['Boletar operação',  $b.'gestor/boletas.php',      'bi-receipt-cutoff'],
        ['Catálogo de ativos', $b.'gestor/ativos.php',      'bi-database'],
        ['Carteira',          $b.'gestor/carteira.php',     'bi-pie-chart'],
        ['Caixa & Fluxo',     $b.'gestor/caixa.php',        'bi-wallet2'],
        ['Cotistas',          $b.'gestor/cotistas.php',     'bi-people'],
        ['Acessos de cotistas', $b.'gestor/acessos.php',    'bi-key'],
        ['Performance',       $b.'gestor/performance.php',  'bi-graph-up-arrow'],
        ['Relatórios',        $b.'gestor/relatorios.php',   'bi-file-earmark-text'],
        ['Enquadramento',     $b.'gestor/enquadramento.php','bi-check-circle'],
        ['Assembleias',       $b.'gestor/assembleias.php',  'bi-megaphone'],
        ['Comunicados',       $b.'gestor/comunicados.php',  'bi-chat-left-text'],
        ['Chamados de cotistas', $b.'gestor/chamados_cotistas.php', 'bi-chat-dots'],
        ['Equipe do fundo',   $b.'gestor/equipe.php',       'bi-people-fill'],
        ['Criar fundo',       $b.'gestor/novo_fundo.php',   'bi-plus-square'],
        ['Suporte',           $b.'gestor/tickets.php',      'bi-life-preserver'],
    ];
    // Sempre visíveis, independem de fundo/permissão
    $livres = ['Equipe do fundo', 'Criar fundo', 'Suporte'];
    if (!isset($pdo) || !function_exists('perms_no_fundo')) return $itens;
    $fundo = fundo_do_usuario($pdo, $u);
    if (!$fundo) {
        // gestor sem fundo (conta nova ou recém-convidada): só o essencial
        return array_values(array_filter($itens, fn($it) => in_array($it[0], $livres, true)));
    }
    $fid = (int)$fundo['id'];
    $principal = eh_principal($pdo, $u, $fid);
    $mapa = permissao_de_menu();
    return array_values(array_filter($itens, function ($it) use ($mapa, $principal, $pdo, $u, $fid, $livres) {
        if (in_array($it[0], $livres, true)) return true;
        $req = $mapa[$it[0]] ?? null;           // permissão exigida
        if ($req === null) return true;         // item sem restrição
        if ($req === '__principal__') return $principal;
        return $principal || pode($pdo, $u, $fid, $req);
    }));
}

function page_start(string $titulo, string $ativo, array $u, string $subtitulo = ''): void {
    $b = base_url();
    $perfilRotulo = ['admin' => 'Administradora', 'gestor' => 'Gestor', 'custodia' => 'Custodiante'][$u['perfil']] ?? $u['perfil'];
    $perfilCor    = ['admin' => 'gold', 'gestor' => 'teal'][$u['perfil']] ?? 'blue';
    ?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e_html($titulo) ?> · Argus DTVM</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css" rel="stylesheet">
<link rel="icon" type="image/png" href="<?= $b ?>assets/favicon.png">
<link href="<?= $b ?>assets/css/style.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/pt.js"></script>
</head>
<body>
<div class="app-wrap">
  <aside class="sidebar">
    <div class="brand">
      <img src="<?= $b ?>assets/favicon.png" class="brand-mark" alt="Argus">
      <div>
        <div class="brand-name">ARGUS</div>
        <div class="brand-sub">Administração Fiduciária</div>
      </div>
    </div>
    <div class="perfil-chip chip-<?= $perfilCor ?>">
      <i class="bi bi-person-circle"></i> <?= e_html($u['nome']) ?>
      <span><?= $perfilRotulo ?></span>
    </div>
    <?php // conta com mais de um fundo (FIC/master, subclasses): seletor de fundo em foco
    global $pdo;
    if ($u['perfil'] === 'gestor' && isset($pdo)) {
        $meusFundos = fundos_do_usuario($pdo, $u);
        if (count($meusFundos) > 1) {
            $focoId = $_SESSION['gestor_fundo_id'] ?? (int)$meusFundos[0]['id']; ?>
      <form method="get" class="mb-2">
        <select name="fundo_id" class="form-select form-select-sm" onchange="this.form.submit()"
                style="background:#1c2f4a;color:#e2e8f0;border-color:rgba(255,255,255,.15);font-size:.78rem">
          <?php foreach ($meusFundos as $mf): ?>
            <option value="<?= (int)$mf['id'] ?>" <?= (int)$mf['id'] === $focoId ? 'selected' : '' ?>><?= e_html($mf['nome']) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
    <?php } } ?>
    <nav class="menu">
      <?php foreach (menu_itens($u) as [$rotulo, $href, $icone]): ?>
        <a href="<?= $href ?>" class="<?= $rotulo === $ativo ? 'ativo' : '' ?>">
          <i class="bi <?= $icone ?>"></i> <?= e_html($rotulo) ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <a class="sair" href="<?= $b ?>logout.php"><i class="bi bi-box-arrow-left"></i> Sair</a>
  </aside>
  <main class="conteudo">
    <div class="titulo-pagina">
      <div>
        <h1><?= e_html($titulo) ?></h1>
        <?php if ($subtitulo): ?><p class="subtitulo"><?= $subtitulo ?></p><?php endif; ?>
      </div>
      <div class="data-ref"><i class="bi bi-calendar3"></i> <?= date('d/m/Y') ?></div>
    </div>
<?php
    if (!empty($_SESSION['flash_perm'])) {
        echo '<div class="alert alert-warning py-2"><i class="bi bi-shield-lock me-1"></i>' . e_html($_SESSION['flash_perm']) . '</div>';
        unset($_SESSION['flash_perm']);
    }
}

function page_end(): void {
    $b = base_url();
    ?>
  </main>
</div>
<script src="<?= $b ?>assets/js/app.js"></script>
</body>
</html><?php
}

/** Cartão KPI padrão. */
function kpi(string $rotulo, string $valor, string $icone = 'bi-graph-up', string $extra = ''): string {
    return '<div class="col"><div class="kpi-card">
        <div class="kpi-icone"><i class="bi ' . $icone . '"></i></div>
        <div><div class="kpi-rotulo">' . e_html($rotulo) . '</div>
        <div class="kpi-valor">' . $valor . '</div>' . ($extra ? '<div class="kpi-extra">' . $extra . '</div>' : '') . '</div>
    </div></div>';
}
