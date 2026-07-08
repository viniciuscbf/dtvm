/* Helpers de gráfico (Chart.js) e grafo de partes relacionadas */

const CORES = ['#14b8a6', '#c9a227', '#3b82f6', '#ef4444', '#8b5cf6', '#f97316', '#22c55e', '#64748b'];

/* ------------------------------------------------------------------
   Date pickers — turbina TODO input[type=date] com o Flatpickr (pt-BR).
   O campo visível mostra dd/mm/aaaa; o valor enviado ao backend
   permanece em Y-m-d (formato esperado pelo PHP). Idempotente.
   ------------------------------------------------------------------ */
document.addEventListener('DOMContentLoaded', function () {
  if (!window.flatpickr) return;
  if (flatpickr.l10ns && flatpickr.l10ns.pt) flatpickr.localize(flatpickr.l10ns.pt);
  document.querySelectorAll('input[type="date"]').forEach(function (el) {
    if (el._flatpickr) return;                 // já inicializado
    flatpickr(el, {
      dateFormat: 'Y-m-d',                     // valor real (backend)
      altInput: true,                          // campo visível em dd/mm/aaaa
      altFormat: 'd/m/Y',
      allowInput: true,
      disableMobile: true
    });
  });
});

function fmtBRL(v) {
  return v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 });
}

function graficoLinha(canvasId, labels, datasets, opcoes = {}) {
  const el = document.getElementById(canvasId);
  if (!el) return null;
  return new Chart(el, {
    type: 'line',
    data: { labels, datasets: datasets.map((d, i) => ({
      borderColor: d.cor || CORES[i], backgroundColor: (d.cor || CORES[i]) + '22',
      borderWidth: 2, pointRadius: 0, tension: .25, fill: d.fill ?? false, ...d
    })) },
    options: {
      responsive: true, maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: { legend: { labels: { boxWidth: 12, usePointStyle: true } } },
      scales: { x: { ticks: { maxTicksLimit: 9 }, grid: { display: false } },
                y: { grid: { color: '#eef1f6' } } },
      ...opcoes
    }
  });
}

function graficoRosca(canvasId, labels, valores) {
  const el = document.getElementById(canvasId);
  if (!el) return null;
  return new Chart(el, {
    type: 'doughnut',
    data: { labels, datasets: [{ data: valores, backgroundColor: CORES, borderWidth: 2, borderColor: '#fff' }] },
    options: { responsive: true, maintainAspectRatio: false, cutout: '62%',
      plugins: { legend: { position: 'right', labels: { boxWidth: 12, usePointStyle: true } } } }
  });
}

function graficoBarras(canvasId, labels, datasets, empilhado = false) {
  const el = document.getElementById(canvasId);
  if (!el) return null;
  return new Chart(el, {
    type: 'bar',
    data: { labels, datasets: datasets.map((d, i) => ({
      backgroundColor: d.cor || CORES[i], borderRadius: 4, ...d })) },
    options: { responsive: true, maintainAspectRatio: false,
      plugins: { legend: { labels: { boxWidth: 12, usePointStyle: true } } },
      scales: { x: { stacked: empilhado, grid: { display: false } },
                y: { stacked: empilhado, grid: { color: '#eef1f6' } } } }
  });
}

/* Gráfico cota vs CDI alimentado pela API, com troca de período */
function iniciarGraficoCota(canvasId, baseUrl, fundoId, periodoInicial = '12m') {
  let chart = null;
  async function carregar(periodo) {
    const r = await fetch(`${baseUrl}api/cota_historico.php?fundo_id=${fundoId}&periodo=${periodo}`);
    const d = await r.json();
    if (chart) chart.destroy();
    chart = graficoLinha(canvasId, d.labels, [
      { label: 'Fundo (base 100)', data: d.fundo, cor: '#14b8a6', fill: true },
      { label: 'CDI (base 100)', data: d.cdi, cor: '#c9a227', borderDash: [6, 4] }
    ]);
  }
  document.querySelectorAll('[data-periodo]').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('[data-periodo]').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      carregar(btn.dataset.periodo);
    });
  });
  carregar(periodoInicial);
}

/* Grafo simples de partes relacionadas (canvas puro) */
function desenharGrafo(canvasId, nos, arestas) {
  const canvas = document.getElementById(canvasId);
  if (!canvas) return;
  const dpr = window.devicePixelRatio || 1;
  const w = canvas.clientWidth, h = canvas.clientHeight;
  canvas.width = w * dpr; canvas.height = h * dpr;
  const ctx = canvas.getContext('2d');
  ctx.scale(dpr, dpr);

  // posiciona: nós em círculo, com o primeiro (gestora) ao centro
  const cx = w / 2, cy = h / 2, raio = Math.min(w, h) / 2 - 70;
  const pos = {};
  nos.forEach((n, i) => {
    if (i === 0) { pos[n.id] = { x: cx, y: cy }; return; }
    const ang = (i - 1) / (nos.length - 1) * Math.PI * 2 - Math.PI / 2;
    pos[n.id] = { x: cx + raio * Math.cos(ang), y: cy + raio * Math.sin(ang) };
  });

  // arestas
  arestas.forEach(a => {
    const p1 = pos[a.de], p2 = pos[a.para];
    if (!p1 || !p2) return;
    ctx.beginPath();
    ctx.moveTo(p1.x, p1.y); ctx.lineTo(p2.x, p2.y);
    ctx.strokeStyle = a.suspeito ? '#ef4444' : 'rgba(148,163,184,.45)';
    ctx.lineWidth = a.suspeito ? 2.5 : 1.2;
    if (a.suspeito) ctx.setLineDash([7, 4]); else ctx.setLineDash([]);
    ctx.stroke();
    ctx.setLineDash([]);
    // rótulo do vínculo
    const mx = (p1.x + p2.x) / 2, my = (p1.y + p2.y) / 2;
    ctx.font = '10px Segoe UI';
    ctx.fillStyle = a.suspeito ? '#fca5a5' : '#64748b';
    ctx.textAlign = 'center';
    ctx.fillText(a.rotulo || '', mx, my - 5);
  });

  // nós
  const corTipo = { gestora: '#c9a227', fundo: '#14b8a6', contraparte: '#3b82f6', pessoa: '#8b5cf6' };
  nos.forEach((n, i) => {
    const p = pos[n.id];
    ctx.beginPath();
    ctx.arc(p.x, p.y, i === 0 ? 26 : 20, 0, Math.PI * 2);
    ctx.fillStyle = n.suspeito ? '#7f1d1d' : '#14243a';
    ctx.fill();
    ctx.lineWidth = 2.5;
    ctx.strokeStyle = n.suspeito ? '#ef4444' : (corTipo[n.tipo] || '#64748b');
    ctx.stroke();
    ctx.font = 'bold 10.5px Segoe UI';
    ctx.fillStyle = '#e2e8f0';
    ctx.textAlign = 'center';
    // quebra o rótulo em até 2 linhas
    const palavras = (n.rotulo || '').split(' ');
    let l1 = '', l2 = '';
    palavras.forEach(pal => { if ((l1 + pal).length <= 16 && !l2) l1 += pal + ' '; else l2 += pal + ' '; });
    ctx.fillText(l1.trim(), p.x, p.y + (i === 0 ? 40 : 34));
    if (l2) ctx.fillText(l2.trim(), p.x, p.y + (i === 0 ? 52 : 46));
  });
}
