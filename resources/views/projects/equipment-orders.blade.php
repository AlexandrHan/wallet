@push('styles')
  <link rel="stylesheet" href="/css/project.css?v={{ filemtime(public_path('css/project.css')) }}">
@endpush

@extends('layouts.app')

@section('content')
<main class="projects-main">

  <div class="projects-title-card">
    <div class="projects-title">
      📦 Замовлення обладнання
    </div>
    <div style="font-size:12px; opacity:.6; margin-top:2px;">Клієнти на етапі Комплектація</div>
  </div>

  <div id="equipmentOrdersContainer">
    <div style="text-align:center; padding:40px; opacity:.5;">Завантаження...</div>
  </div>

</main>

<script>
function esc(v) {
  return String(v ?? '')
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

async function loadEquipmentOrders() {
  const container = document.getElementById('equipmentOrdersContainer');
  if (!container) return;

  const r = await fetch('/api/equipment-orders');
  if (!r.ok) { container.innerHTML = '<div style="padding:20px;color:red;">Помилка завантаження</div>'; return; }
  const data = await r.json();

  const { projects, summary } = data;
  container.innerHTML = '';

  // ── ЗВЕДЕННЯ ──────────────────────────────────────────────
  const summaryCard = document.createElement('div');
  summaryCard.className = 'card';
  summaryCard.style.marginBottom = '16px';

  const hasSummary = Object.keys(summary.inverter).length
    || Object.keys(summary.bms).length
    || Object.keys(summary.battery).length
    || Object.keys(summary.panels).length;

  function summaryBlock(title, map) {
    if (!Object.keys(map).length) return '';
    const rows = Object.entries(map)
      .map(([name, qty]) => `
        <div style="display:flex; justify-content:space-between; padding:5px 0; border-bottom:1px solid var(--border,#e5e7eb);">
          <div>${esc(name)}</div>
          <div style="font-weight:700; white-space:nowrap; margin-left:12px;">${qty} шт.</div>
        </div>`)
      .join('');
    return `
      <div style="margin-bottom:14px;">
        <div style="font-weight:700; font-size:13px; opacity:.65; margin-bottom:6px; text-transform:uppercase; letter-spacing:.04em;">${esc(title)}</div>
        ${rows}
      </div>`;
  }

  summaryCard.innerHTML = `
    <div style="font-weight:800; font-size:15px; margin-bottom:14px;">📊 Зведення по обладнанню</div>
    ${hasSummary ? `
      ${summaryBlock('Інвертори', summary.inverter)}
      ${summaryBlock('BMS', summary.bms)}
      ${summaryBlock('АКБ', summary.battery)}
      ${summaryBlock('ФЕМ (панелі)', summary.panels)}
    ` : '<div style="opacity:.5;">Обладнання не вказано</div>'}
  `;
  container.appendChild(summaryCard);

  // ── СПИСОК КЛІЄНТІВ ───────────────────────────────────────
  const listCard = document.createElement('div');
  listCard.className = 'card';

  if (!projects.length) {
    listCard.innerHTML = '<div style="opacity:.5; padding:10px;">Немає клієнтів на етапі Комплектація</div>';
    container.appendChild(listCard);
    return;
  }

  const header = document.createElement('div');
  header.style.cssText = 'font-weight:800; font-size:15px; margin-bottom:14px;';
  header.textContent = `👥 Клієнти (${projects.length})`;
  listCard.appendChild(header);

  projects.forEach(p => {
    const row = document.createElement('div');
    row.style.cssText = 'padding:12px 0; border-bottom:1px solid var(--border,#e5e7eb);';

    const equipment = [];
    if (p.inverter)     equipment.push(`<span style="opacity:.55;">Інвертор:</span> ${esc(p.inverter)}`);
    if (p.bms)          equipment.push(`<span style="opacity:.55;">BMS:</span> ${esc(p.bms)}`);
    if (p.battery_name) equipment.push(`<span style="opacity:.55;">АКБ:</span> ${esc(p.battery_name)}${p.battery_qty ? ` × ${p.battery_qty}` : ''}`);
    if (p.panel_name)   equipment.push(`<span style="opacity:.55;">ФЕМ:</span> ${esc(p.panel_name)}${p.panel_qty ? ` × ${p.panel_qty}` : ''}`);

    row.innerHTML = `
      <div style="font-weight:700; margin-bottom:5px;">${esc(p.client_name)}</div>
      ${equipment.length
        ? equipment.map(e => `<div style="font-size:13px; margin-bottom:3px;">${e}</div>`).join('')
        : '<div style="font-size:12px; opacity:.4;">Обладнання не вказано</div>'
      }
    `;
    listCard.appendChild(row);
  });

  container.appendChild(listCard);
}

document.addEventListener('DOMContentLoaded', () => {
  loadEquipmentOrders().catch(() => {
    const c = document.getElementById('equipmentOrdersContainer');
    if (c) c.innerHTML = '<div style="padding:20px;color:red;">Помилка завантаження</div>';
  });
});
</script>

@include('partials.nav.bottom')
@endsection
