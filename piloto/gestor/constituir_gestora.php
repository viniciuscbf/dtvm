<?php
// Trilha guiada "Constituir gestora" — orientação pública (o interessado ainda NÃO é gestor).
// Dois caminhos alternáveis: gestora PESSOA JURÍDICA (padrão) e gestor PESSOA FÍSICA.
// Apoio DOCUMENTAL/EDUCACIONAL: a autorização é da CVM; os documentos são minutas.
// $modelos: [nome, descrição, ícone, caminho] — caminho 'pj' = só pessoa jurídica.
$modelos = [
    ['Contrato social da gestora', 'Constitui a empresa; objeto de gestão de recursos.', 'bi-building', 'pj'],
    ['Formulário de referência (Res. CVM 21, Anexo E)', 'Peça central do pedido de autorização.', 'bi-file-earmark-text', ''],
    ['Política de gestão de riscos', 'Governança e limites de risco das carteiras.', 'bi-graph-down', ''],
    ['Política de PLD/FT (Res. CVM 50)', 'Prevenção à lavagem: ABR, KYC, COAF.', 'bi-shield-check', ''],
    ['Código de ética e conduta', 'Dever fiduciário, conflitos, sigilo.', 'bi-journal-check', ''],
    ['Política de rateio e divisão de ordens', 'Alocação equitativa entre os fundos.', 'bi-diagram-3', ''],
    ['Política de investimentos pessoais', 'Regras para operações pessoais.', 'bi-person-lock', ''],
    ['Plano de continuidade de negócios', 'Continuidade diante de contingências.', 'bi-arrow-repeat', ''],
];
// Passos por caminho: [ícone, título, descrição, estimativa]. Estimativa só nas ESPERAS EXTERNAS
// (análise da CVM/ANBIMA) — o resto depende do ritmo do gestor, então não se estima.
$passosPJ = [
    ['bi-patch-check', 'Certificação do gestor', 'O futuro diretor-gestor obtém a certificação — <b>CGA</b> ou <b>CGE</b> da ANBIMA (esta para fundos estruturados), ou <b>CFA nível III</b> — mais curso superior e reputação ilibada (Res. CVM 21, art. 3º). Pessoal e intransferível.', ''],
    ['bi-building-add', 'Constituição da empresa', 'Contrato social com <b>objeto exclusivo de gestão de recursos</b> (CNAE 6630-4/00) → registro na <b>Junta Comercial</b> → CNPJ da gestora.', ''],
    ['bi-people', 'Diretores e políticas', 'Nomear os diretores estatutários <b>segregados</b> (gestão; risco; compliance/PLD) e elaborar as <b>políticas obrigatórias</b> — modelos prontos abaixo.', ''],
    ['bi-send-check', 'Pedido de autorização (Anexo E)', 'Protocolar o requerimento com o <b>Formulário de Referência (Anexo E)</b> e anexos, pelo convênio CVM/ANBIMA.', ''],
    ['bi-hourglass-split', 'Análise da CVM/ANBIMA', 'Adesão ao código, <b>due diligence</b> e análise. Podem vir <b>exigências</b> (você responde em 20+10 dias; cada uma reinicia a contagem).', 'espera externa ~30 dias corridos'],
    ['bi-award', 'Ato declaratório da CVM', 'Deferido, a CVM emite o <b>Ato Declaratório</b> que autoriza a atividade. A partir daí você gere fundos — e constitui o seu aqui.', ''],
];
$passosPF = [
    ['bi-patch-check', 'Certificação do gestor', 'Curso superior + <b>certificação</b> (CGA/CGE da ANBIMA ou CFA nível III) + reputação ilibada + domicílio no Brasil (Res. CVM 21, art. 3º). Pessoal e intransferível.', ''],
    ['bi-folder-check', 'Controles e políticas próprios', 'Mesmo como PF, o gestor mantém controles: <b>código de ética, PLD/FT (Res. 50), gestão de risco e investimentos pessoais</b> — em escala pessoal. Modelos abaixo.', ''],
    ['bi-send-check', 'Pedido de autorização — pessoa física', 'Protocolar o requerimento com o <b>Formulário de Referência de pessoa física (Anexo D)</b>. <b>Sem empresa, sem CNPJ de gestora.</b>', ''],
    ['bi-hourglass-split', 'Análise da CVM/ANBIMA', 'Análise do pedido; podem vir <b>exigências</b> (você responde em 20+10 dias; cada uma reinicia a contagem).', 'espera externa ~30 dias corridos'],
    ['bi-award', 'Ato declaratório da CVM', 'Deferido, você é autorizado como <b>gestor pessoa física</b> e passa a gerir fundos — cujo CNPJ é do <b>próprio fundo</b>, não seu.', ''],
];
$refs = [
    ['Resolução CVM 21/2021 — administração de carteiras (consolidada)', 'https://conteudo.cvm.gov.br/export/sites/cvm/legislacao/resolucoes/anexos/001/resol021consolid.pdf'],
    ['Guia CVM de credenciamento — administrador de carteiras', 'https://conteudo.cvm.gov.br/export/sites/cvm/menu/regulados/administradores/guia-acvm-credenciamento.pdf'],
    ['ANBIMA — Guia de habilitação de gestor (PJ)', 'https://www.anbima.com.br/data/files/BF/F1/E3/C5/8BE5A7103955D5A76B2BA2A8/Guia-para-Habilitacao-PJ.pdf'],
    ['gov.br — Registrar administrador de carteira (CVM)', 'https://www.gov.br/pt-br/servicos/registrar-administrador-de-carteira-cvm'],
    ['ANBIMA — passo a passo para criar uma gestora', 'https://www.anbima.com.br/pt_br/noticias/guia-traz-o-passo-a-passo-para-a-criacao-de-uma-gestora-de-investimentos.htm'],
    ['Resolução CVM 50/2021 — PLD/FT', 'https://conteudo.cvm.gov.br/legislacao/resolucoes/resol050.html'],
];

/** Renderiza um conjunto de passos. */
function render_passos(array $passos): void { foreach ($passos as $i => [$ic, $tit, $desc, $est]): ?>
      <div class="col-md-6"><div class="passo">
        <div class="passo-n"><i class="bi <?= $ic ?>"></i></div>
        <div><div style="font-weight:600;font-size:.92rem"><?= $i + 1 ?>. <?= htmlspecialchars($tit) ?>
            <?php if ($est): ?><span class="badge bg-primary-subtle text-primary border ms-1" style="font-weight:500"><i class="bi bi-clock me-1"></i><?= htmlspecialchars($est) ?></span><?php endif; ?></div>
          <div class="text-muted mt-1" style="font-size:.82rem"><?= $desc ?></div></div>
      </div></div>
<?php endforeach; }
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Constituir gestora · Argus</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="../assets/css/style.css" rel="stylesheet">
<style>
  body{background:var(--bg)}
  .cg-hero{background:radial-gradient(900px 400px at 80% -20%, #1d3354 0%, var(--navy) 60%);color:#e2e8f0;border-radius:16px;padding:34px 38px}
  .passo{display:flex;gap:14px;align-items:flex-start}
  .passo-n{flex:0 0 40px;height:40px;border-radius:10px;background:rgba(106,80,172,.14);color:var(--navy);display:flex;align-items:center;justify-content:center;font-size:1.2rem}
  .modelo-item{display:flex;justify-content:space-between;align-items:center;gap:12px;border:1px solid var(--borda);border-radius:10px;padding:12px 14px}
  .via{font-size:.72rem;color:#64748b}
  .path-tabs .btn{font-size:.9rem}
</style>
</head>
<body>
<div class="container py-4" style="max-width:1000px">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex align-items-center gap-2">
      <img src="../assets/favicon.png" alt="Argus" style="height:28px;width:28px;object-fit:contain">
      <b style="letter-spacing:1px">ARGUS</b><span class="text-muted" style="font-size:.8rem">· constituir gestora</span>
    </div>
    <a href="../index.php" class="btn btn-sm btn-outline-secondary">← Portais</a>
  </div>

  <div class="cg-hero mb-4">
    <h1 style="font-size:1.7rem;color:#fff">Antes do fundo, o <span style="color:var(--gold)">gestor autorizado</span></h1>
    <p class="mt-2 mb-0" style="max-width:700px;font-size:.95rem;color:#cbd5e1">
      Gerir recursos de terceiros é atividade privativa de quem a CVM autoriza como <b>administrador de carteiras — gestor de recursos</b> (Res. CVM 21).
      O gestor pode ser <b>pessoa jurídica</b> (uma gestora) ou <b>pessoa física</b> — escolha o caminho abaixo. Em ambos, o <b>fundo</b> terá CNPJ próprio.</p>
  </div>

  <!-- toggle PJ / PF -->
  <div class="path-tabs btn-group w-100 mb-3" role="group" aria-label="Tipo de gestor">
    <button type="button" class="btn btn-outline-primary active" data-pathbtn="pj"><i class="bi bi-building me-1"></i>Gestora — pessoa jurídica</button>
    <button type="button" class="btn btn-outline-primary" data-pathbtn="pf"><i class="bi bi-person me-1"></i>Gestor — pessoa física</button>
  </div>

  <div class="card mb-4" data-path="pj"><div class="card-body py-3">
    <h6 class="mb-1"><i class="bi bi-building me-1 text-primary"></i>Gestora — pessoa jurídica <span class="badge bg-primary-subtle text-primary border ms-1">mais comum</span></h6>
    <p class="text-muted mb-0" style="font-size:.88rem">Empresa com objeto de gestão, <b>três diretores segregados</b> (gestão, risco, compliance/PLD) e políticas próprias. Preferida para escala e captação, e <b>limita a responsabilidade</b> ao capital da empresa. Tem CNPJ de gestora.</p>
  </div></div>
  <div class="card mb-4" data-path="pf" style="display:none"><div class="card-body py-3">
    <h6 class="mb-1"><i class="bi bi-person me-1 text-primary"></i>Gestor — pessoa física</h6>
    <p class="text-muted mb-0" style="font-size:.88rem">Você é o gestor <b>como pessoa física — sem abrir empresa e sem CNPJ de gestora</b>. Exige curso superior, certificação, reputação ilibada e domicílio no Brasil. Menor custo, mas a <b>responsabilidade é pessoal (ilimitada)</b>.
    <b>Atenção:</b> o <b>fundo</b> que você gerir continua tendo <b>CNPJ próprio</b> (criado no registro do fundo) — quem não precisa de CNPJ é <b>você, o gestor</b>, não o fundo.</p>
  </div></div>

  <div class="card mb-4">
    <div class="card-header"><i class="bi bi-signpost-2 me-1"></i> Passo a passo até a autorização (CVM)</div>
    <div class="card-body">
      <div class="row g-4" data-path="pj"><?php render_passos($passosPJ); ?></div>
      <div class="row g-4" data-path="pf" style="display:none"><?php render_passos($passosPF); ?></div>
      <div class="alert alert-secondary mt-3 mb-0 py-2" style="font-size:.8rem"><i class="bi bi-clock-history me-1"></i>
        <b>Sobre prazos:</b> estimamos só a <b>espera externa</b> — a análise da CVM/ANBIMA (~30 dias corridos, reiniciados a cada exigência). O tempo de <b>estudar para a certificação, constituir a empresa e montar as políticas depende do seu ritmo</b>, então não faz sentido estimar. &nbsp;
        <b>Custo:</b> o registro na CVM é <b>gratuito</b>; pesam certificação, advogado, contador, ANBIMA e sistemas, além da taxa trimestral de fiscalização. <b>Sem capital mínimo</b> exigido do gestor.</div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header"><i class="bi bi-link-45deg me-1"></i> Referências oficiais</div>
    <div class="card-body">
      <div class="row g-2">
        <?php foreach ($refs as [$t, $url]): ?>
          <div class="col-md-6"><a href="<?= htmlspecialchars($url) ?>" target="_blank" rel="noopener" style="font-size:.83rem"><i class="bi bi-box-arrow-up-right me-1"></i><?= htmlspecialchars($t) ?></a></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header"><i class="bi bi-download me-1"></i> Modelos de documentos (.docx, prontos para editar)</div>
    <div class="card-body">
      <div class="row g-2 align-items-end mb-3">
        <div class="col-md-8"><label class="form-label" style="font-size:.8rem">Nome do gestor / da gestora — preenche os modelos</label>
          <input class="form-control form-control-sm" id="gestora" placeholder="Ex.: Aurora Capital Gestão de Recursos Ltda."></div>
        <div class="col-md-4 text-md-end"><span class="text-muted" style="font-size:.75rem">Cada modelo baixa em Word, já com o nome preenchido.</span></div>
      </div>
      <div class="row g-2">
        <?php foreach ($modelos as [$nome, $desc, $ic, $path]): ?>
          <div class="col-md-6" <?= $path ? 'data-path="' . $path . '"' : '' ?>><div class="modelo-item">
            <div><i class="bi <?= $ic ?> me-2 text-muted"></i><b style="font-size:.86rem"><?= htmlspecialchars($nome) ?></b>
              <div class="via"><?= htmlspecialchars($desc) ?></div></div>
            <button type="button" class="btn btn-sm btn-outline-primary text-nowrap flex-shrink-0" onclick='baixarModelo(<?= htmlspecialchars(json_encode($nome), ENT_QUOTES) ?>)'><i class="bi bi-download me-1"></i>.docx</button>
          </div></div>
        <?php endforeach; ?>
      </div>
      <p class="text-muted mt-3 mb-0" style="font-size:.76rem"><i class="bi bi-info-circle me-1"></i>
        <span data-path="pf" style="display:none"><b>Pessoa física:</b> o contrato social não se aplica e o formulário de referência é o <b>Anexo D</b> (de PF), não o Anexo E. </span>
        Os modelos são <b>minutas</b>: adapte à sua estrutura e submeta a revisão de advogado de mercado de capitais e do contador. O <b>ato declaratório</b> é emitido pela CVM ao fim do processo — não há modelo dele. A Argus presta apoio documental e educacional; <b>não</b> emite a autorização, não substitui advogado/contador, não faz a certificação por você nem decide os investimentos.</p>
    </div>
  </div>

  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 pb-4">
    <span class="text-muted" style="font-size:.85rem">Já é gestor autorizado pela CVM?</span>
    <a href="cadastro.php" class="btn btn-dark btn-sm"><i class="bi bi-rocket-takeoff me-1"></i>Constituir seu fundo →</a>
  </div>
</div>

<script>
function baixarModelo(nome){
  var g = (document.getElementById('gestora').value || '').trim();
  window.open('template_modelo.php?' + new URLSearchParams({ doc: nome, gestora: g }).toString(), '_blank');
}
(function(){
  var btns = [].slice.call(document.querySelectorAll('[data-pathbtn]'));
  function setPath(p){
    btns.forEach(function(b){ b.classList.toggle('active', b.dataset.pathbtn===p); });
    document.querySelectorAll('[data-path]').forEach(function(el){ el.style.display = (el.dataset.path===p)?'':'none'; });
  }
  btns.forEach(function(b){ b.addEventListener('click', function(){ setPath(b.dataset.pathbtn); }); });
  setPath('pj');
})();
</script>
</body>
</html>
