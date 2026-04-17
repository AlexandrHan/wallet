@push('styles')
  <link rel="stylesheet" href="/css/project.css?v={{ filemtime(public_path('css/project.css')) }}">
@endpush

@extends('layouts.app')

@section('content')
<main class="projects-main">

  <div class="projects-title-card">
    <div class="projects-title">🚚 Доставлено на об'єкт</div>
    <div style="font-size:12px; opacity:.6; margin-top:2px;">Активні проекти з вже доставленим обладнанням</div>
  </div>

  <div id="deliveredContainer">
    <div style="text-align:center; padding:40px; opacity:.5;">Завантаження...</div>
  </div>

</main>

<style>
.dl-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
  margin-top: 10px;
}
.dl-table thead th {
  padding: 0 10px 8px;
  font-size: 11px;
  font-weight: 600;
  opacity: .45;
  text-transform: uppercase;
  letter-spacing: .03em;
  text-align: left;
  white-space: nowrap;
  border-bottom: 1px solid rgba(255,255,255,0.1);
}
.dl-table tbody tr {
  border-bottom: 1px solid rgba(255,255,255,0.07);
}
.dl-table tbody tr:last-child { border-bottom: none; }
.dl-table td {
  padding: 10px 10px;
  vertical-align: top;
  background: transparent;
}
.dl-num   { color: rgba(255,255,255,.3); font-size: 11px; width: 28px; text-align: center; padding-right: 4px; }
.dl-name  { font-weight: 600; }
.dl-stage { opacity: .65; font-size: 12px; white-space: nowrap; }
.dl-what  { font-size: 12px; }
.dl-what-item { margin-bottom: 3px; }
.dl-what-item:last-child { margin-bottom: 0; }
.dl-panels  { color: #6bf; }
.dl-inv     { color: #fca; }
.dl-mgr     { opacity: .6; font-size: 12px; }
.dl-geo a   { color: rgba(255,255,255,.4); font-size: 12px; text-decoration: none; }
.dl-geo a:hover { color: #6bf; }

/* summary bar */
.dl-summary {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
  padding: 12px 16px;
  background: rgba(255,255,255,.04);
  border-radius: 10px;
  margin-bottom: 14px;
  font-size: 13px;
}
.dl-summary-item { display: flex; flex-direction: column; gap: 2px; }
.dl-summary-label { font-size: 10px; opacity: .4; text-transform: uppercase; letter-spacing: .04em; }
.dl-summary-val   { font-weight: 700; }

/* ── MOBILE ── */
@media (max-width: 640px) {
  .dl-table thead { display: none; }
  .dl-table tbody tr {
    display: block;
    padding: 10px 0 8px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
  }
  .dl-table td { display: block; padding: 1px 0; }
  .dl-num   { display: inline; font-size: 11px; opacity: .35; }
  .dl-name  { font-size: 14px; margin-bottom: 3px; }
  .dl-stage { display: inline-block; margin-top: 2px; margin-bottom: 4px; }
  .dl-geo   { display: inline-block; margin-left: 8px; }
  .dl-mgr   { margin-top: 2px; }
}
</style>

<script>
function esc(v) {
  return String(v ?? '')
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

async function loadDelivered() {
  const container = document.getElementById('deliveredContainer');
  const r = await fetch('/api/projects/delivered');
  if (!r.ok) { container.innerHTML = '<div style="padding:20px;color:#f76;">Помилка завантаження</div>'; return; }
  const rows = await r.json();
  if (!rows.length) { container.innerHTML = '<div class="card" style="padding:20px;opacity:.5;">Немає проектів</div>'; return; }

  // summary counts
  const total     = rows.length;
  const panels    = rows.filter(r => r.delivered_what.some ? r.delivered_what.some(w => w.startsWith('Панелі')) : false).length;
  const panelsN   = rows.filter(r => r.delivered_what.some(w => w.startsWith('Панелі'))).length;
  const invN      = rows.filter(r => r.delivered_what.some(w => w.startsWith('Інвертор'))).length;
  const bothN     = rows.filter(r => r.delivered_what.some(w => w.startsWith('Панелі')) && r.delivered_what.some(w => w.startsWith('Інвертор'))).length;

  // stage order for badge colors
  const stageColor = {
    'Комплектація':             '#888',
    'Очікування доставки':      '#fa0',
    'Заплановане будівництво':  '#6af',
    'Монтаж панелей':           '#6df',
    'Електрична частина':       '#adf',
    'Здача проекту':            '#4d9',
    'Частково оплатив':         '#999',
  };

  let html = `
  <div class="dl-summary">
    <div class="dl-summary-item"><span class="dl-summary-label">Всього</span><span class="dl-summary-val">${total}</span></div>
    <div class="dl-summary-item"><span class="dl-summary-label" style="color:#6bf;">Панелі доставлено</span><span class="dl-summary-val" style="color:#6bf;">${panelsN}</span></div>
    <div class="dl-summary-item"><span class="dl-summary-label" style="color:#fca;">Інвертор+АКБ</span><span class="dl-summary-val" style="color:#fca;">${invN}</span></div>
    <div class="dl-summary-item"><span class="dl-summary-label">Обидва</span><span class="dl-summary-val">${bothN}</span></div>
  </div>
  <div class="card">
  <table class="dl-table">
    <thead><tr>
      <th style="width:28px;">#</th>
      <th>Клієнт</th>
      <th>Етап</th>
      <th>Що доставлено</th>
      <th>Менеджер</th>
      <th>Карта</th>
    </tr></thead>
    <tbody>`;

  rows.forEach((row, i) => {
    const color = stageColor[row.stage] ?? '#888';
    const whatHtml = row.delivered_what.map(w => {
      const isPanels = w.startsWith('Панелі');
      return `<div class="dl-what-item ${isPanels ? 'dl-panels' : 'dl-inv'}">${esc(w)}</div>`;
    }).join('');
    const geoHtml = row.geo_link
      ? `<td class="dl-geo"><a href="${esc(row.geo_link)}" target="_blank" rel="noopener">📍 Карта</a></td>`
      : `<td class="dl-geo" style="opacity:.2;">—</td>`;

    html += `<tr>
      <td class="dl-num">${i + 1}</td>
      <td class="dl-name">${esc(row.client_name)}</td>
      <td class="dl-stage"><span style="font-size:11px;font-weight:600;color:${color};">${esc(row.stage)}</span></td>
      <td class="dl-what">${whatHtml}</td>
      <td class="dl-mgr">${esc(row.manager)}</td>
      ${geoHtml}
    </tr>`;
  });

  html += `</tbody></table></div>`;
  container.innerHTML = html;
}

document.addEventListener('DOMContentLoaded', () => {
  loadDelivered().catch(() => {
    document.getElementById('deliveredContainer').innerHTML =
      '<div style="padding:20px;color:#f76;">Помилка завантаження</div>';
  });
});
</script>

@include('partials.nav.bottom')
@endsection
