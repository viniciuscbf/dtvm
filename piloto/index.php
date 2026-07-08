<?php
// Landing institucional — três portais separados (gestor, cotista, administradora)
define('BASE_URL', './');
require_once __DIR__ . '/includes/auth.php';
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Argus DTVM · Plataforma de Fundos</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
<style>
.hero { min-height: 100vh; background: radial-gradient(1400px 700px at 75% -15%, #1d3354 0%, var(--navy) 55%);
        color: #e2e8f0; display: flex; flex-direction: column; }
.portal-card { background: rgba(255,255,255,.045); border: 1px solid rgba(255,255,255,.1); border-radius: 16px;
        padding: 30px 26px; height: 100%; transition: transform .15s, border-color .15s; }
.portal-card:hover { transform: translateY(-4px); border-color: var(--gold); }
.portal-icone { width: 54px; height: 54px; border-radius: 12px; display: flex; align-items: center;
        justify-content: center; font-size: 1.5rem; margin-bottom: 16px; }
.hero a.btn-portal { background: var(--gold); border: none; color: var(--navy); font-weight: 600; }
.hero a.btn-portal-outline { border: 1px solid rgba(255,255,255,.35); color: #e2e8f0; }
.metrica b { font-size: 1.4rem; color: #fff; }
</style>
</head>
<body>
<div class="hero">
  <div class="container py-4 d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center gap-2">
      <i class="bi bi-bank2" style="font-size:1.5rem;color:var(--gold)"></i>
      <span style="font-weight:700;letter-spacing:2px;font-size:.9rem">ARGUS <span style="font-weight:400;color:var(--gold)">DTVM</span></span>
      <span class="text-secondary" style="font-size:.7rem">· piloto · dados simulados</span>
    </div>
    <a class="btn btn-sm btn-portal-outline btn" href="gestor/cadastro.php">Constitua seu fundo →</a>
  </div>

  <div class="container my-auto py-5">
    <div class="row align-items-center g-5">
      <div class="col-lg-5">
        <h1 style="font-size:2.1rem;color:#fff">A administradora fiduciária desenhada para <span style="color:var(--gold)">fundos pequenos</span></h1>
        <p class="text-secondary mt-3" style="font-size:.95rem">
          Taxa de administração de 0,08% a.a. com piso de R$ 100/mês. Abertura padronizada,
          cota diária automatizada com aprovação do gestor, monitoramento de fraude por IA
          e transparência total para o cotista.
        </p>
        <div class="d-flex gap-4 mt-4 metrica">
          <div><b>R$ 204 mi</b><br><span class="text-secondary" style="font-size:.75rem">sob administração</span></div>
          <div><b>8</b><br><span class="text-secondary" style="font-size:.75rem">fundos na plataforma</span></div>
          <div><b>D-1</b><br><span class="text-secondary" style="font-size:.75rem">cota validada pelo gestor</span></div>
        </div>
      </div>

      <div class="col-lg-7">
        <div class="row g-3">
          <div class="col-md-6 col-lg-3">
            <div class="portal-card">
              <div class="portal-icone" style="background:rgba(20,184,166,.18);color:#2dd4bf"><i class="bi bi-briefcase"></i></div>
              <h6 style="color:#fff">Portal do Gestor</h6>
              <p class="text-secondary" style="font-size:.78rem">Acompanhe carteira, caixa e cotistas; aprove a cota diária;
                 gere acessos para seus cotistas; constitua novos fundos.</p>
              <a class="btn btn-sm btn-portal w-100" href="gestor/login.php">Entrar</a>
              <a class="d-block text-center mt-2 text-secondary" style="font-size:.72rem" href="gestor/cadastro.php">ainda não tem fundo? cadastre-se</a>
            </div>
          </div>
          <div class="col-md-6 col-lg-3">
            <div class="portal-card">
              <div class="portal-icone" style="background:rgba(59,130,246,.18);color:#93c5fd"><i class="bi bi-person-badge"></i></div>
              <h6 style="color:#fff">Portal do Cotista</h6>
              <p class="text-secondary" style="font-size:.78rem">Veja a evolução do fundo contra o benchmark e a composição
                 da carteira. Acesso simples: apenas o token fornecido pelo gestor.</p>
              <a class="btn btn-sm btn-portal w-100" href="cotista/index.php">Acessar com token</a>
              <span class="d-block text-center mt-2 text-secondary" style="font-size:.72rem">sem cadastro, sem senha</span>
            </div>
          </div>
          <div class="col-md-6 col-lg-3">
            <div class="portal-card">
              <div class="portal-icone" style="background:rgba(201,162,39,.18);color:#eeda9a"><i class="bi bi-shield-lock"></i></div>
              <h6 style="color:#fff">Administradora</h6>
              <p class="text-secondary" style="font-size:.78rem">Área restrita da equipe: processamento diário, conciliação,
                 aprovações de abertura, lançamentos e monitoramento de fraude.</p>
              <a class="btn btn-sm btn-portal-outline btn w-100" href="admin/login.php">Área restrita</a>
              <span class="d-block text-center mt-2 text-secondary" style="font-size:.72rem">acesso interno</span>
            </div>
          </div>
          <div class="col-md-6 col-lg-3">
            <div class="portal-card">
              <div class="portal-icone" style="background:rgba(96,165,250,.16);color:#93c5fd"><i class="bi bi-safe2"></i></div>
              <h6 style="color:#fff">Banco Custodiante</h6>
              <p class="text-secondary" style="font-size:.78rem">Mesa de custódia: contas nas centrais (SELIC/B3),
                 mensageria SPB, liquidação DVP e arquivos de posição.</p>
              <a class="btn btn-sm btn-portal-outline btn w-100" href="custodia/login.php">Mesa de custódia</a>
              <span class="d-block text-center mt-2 text-secondary" style="font-size:.72rem">retaguarda do banco</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="container pb-4 text-secondary" style="font-size:.72rem">
    Ambiente de demonstração local (XAMPP) · nenhum dado real · a cota do dia só é publicada após aprovação do gestor e liberação da administradora.
    &nbsp;·&nbsp; <a href="simulador/index.php" style="color:#64748b"><i class="bi bi-joystick"></i> Simulador Master</a>
  </div>
</div>
</body>
</html>
