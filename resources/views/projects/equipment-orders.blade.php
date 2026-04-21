@push('styles')
  <link rel="stylesheet" href="/css/project.css?v={{ filemtime(public_path('css/project.css')) }}">
@endpush

@extends('layouts.app')

@section('content')
<main class="projects-main">

  <div class="projects-title-card">
    <div class="projects-title">📦 Замовлення обладнання</div>
    <div style="font-size:12px; opacity:.6; margin-top:2px;">Склад vs активні проекти</div>
  </div>

  <div id="equipmentOrdersContainer">
    <div style="text-align:center; padding:40px; opacity:.5;">Завантаження...</div>
  </div>

</main>

<style>
/* collapsible */
.eo-toggle {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
  background: none;
  border: none;
  cursor: pointer;
  padding: 0;
  text-align: left;
  color: inherit;
}
.eo-caret { font-size: 12px; opacity: .5; transition: transform .2s; flex-shrink: 0; }

/* equipment table */
.eq-table {
  width: 100%;
  border-collapse: collapse;
  border-radius: 8px;
  font-size: 13px;
  margin-top: 12px;
  margin-bottom: 0;
  
}
tbody {background-color: transparent;}
.eo-body{
  background: transparent;
}
.eq-table tbody tr { 
  border: none; 
  border-bottom: 1px solid rgba(255,255,255,0.07);
  background: transparent;
  backdrop-filter: none;
}
.eq-table thead { display: table-header-group !important; }
.eq-table thead th {
  padding: 0 8px 7px;
  font-size: 11px;
  font-weight: 600;
  opacity: .45;
  text-transform: uppercase;
  letter-spacing: .03em;
  text-align: right;
  white-space: nowrap;
  border-bottom: 1px solid rgba(255,255,255,0.1);
}
.eq-table thead th:first-child { text-align: left; }

.eq-table tbody tr:last-child { border-bottom: none; }

.eq-table td {
  padding: 8px 8px;
  text-align: right;
  vertical-align: middle;
  background: transparent;
}
.eq-table td:first-child { text-align: left; font-weight: 500; padding-right: 16px; }
.eq-shortage { color: #f76; font-weight: 700; }
.eq-remaining { color: #4d9; font-weight: 600; }
.eq-zero { opacity: .25; }
.eq-totals td {
  font-weight: 700;
  border-top: 1px solid rgba(255,255,255,0.12) !important;
  border-bottom: none !important;
  padding-top: 10px;
}

/* section divider inside card */
.eq-section-label {
  font-size: 12px;
  font-weight: 700;
  opacity: .5;
  text-transform: uppercase;
  letter-spacing: .05em;
  margin: 18px 0 0;
  padding-top: 14px;
  border-top: 1px solid rgba(255,255,255,0.07);
}
.eq-section-label:first-child { margin-top: 0; padding-top: 0; border-top: none; }
.eq-section-row td {
  padding: 16px 8px 4px;
  font-size: 10px;
  font-weight: 700;
  opacity: .45;
  text-transform: uppercase;
  letter-spacing: .06em;
  border-top: 1px solid rgba(255,255,255,0.1) !important;
  border-bottom: none !important;
  background: transparent;
}
.eq-section-first td { padding-top: 2px; border-top: none !important; }

/* ── MOBILE ONLY ─────────────────────────────────────────────────── */
@media (max-width: 640px) {
  /* hide column headers — labels shown via ::before on each cell */
  .eq-table thead { display: none !important; }

  /* each data row becomes a block */
  .eq-table tbody tr {
    display: block;
    padding: 10px 0 8px;
    border-bottom: 1px solid rgba(255,255,255,0.10) !important;
  }
  .eq-table tbody tr:last-child { border-bottom: none !important; }

  /* name cell: full-width block, wraps naturally */
  .eq-table td:first-child {
    display: block;
    text-align: left;
    font-weight: 600;
    font-size: 13px;
    padding: 0 0 6px 0;
    white-space: normal;
    word-break: break-word;
  }

  /* numeric cells: inline-flex column — label on top, value below */
  .eq-table td:not(:first-child) {
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    font-size: 12px;
    padding: 2px 10px 2px 0;
    min-width: 44px;
    text-align: center;
    vertical-align: top;
  }

  /* mini label above value from data-label attribute */
  .eq-table td[data-label]::before {
    content: attr(data-label);
    display: block;
    font-size: 9px;
    font-weight: 600;
    opacity: .38;
    text-transform: uppercase;
    letter-spacing: .04em;
    margin-bottom: 2px;
    white-space: nowrap;
  }

  /* section header rows */
  .eq-section-row { display: block !important; }
  .eq-section-row td {
    display: block !important;
    padding: 12px 0 3px !important;
    text-align: left;
  }
}
</style>

<script>
function esc(v) {
  return String(v ?? '')
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

function makeCollapsible(card, titleHtml, bodyHtml, openByDefault) {
  card.innerHTML = `
    <button type="button" class="eo-toggle">
      <span>${titleHtml}</span>
      <span class="eo-caret">${openByDefault ? '▾' : '▸'}</span>
    </button>
    <div class="eo-body" style="display:${openByDefault ? '' : 'none'}; margin-top:14px;">${bodyHtml}</div>
  `;
  card.querySelector('.eo-toggle').addEventListener('click', function () {
    const body  = card.querySelector('.eo-body');
    const caret = card.querySelector('.eo-caret');
    const open  = body.style.display === 'none';
    body.style.display = open ? '' : 'none';
    caret.textContent  = open ? '▾' : '▸';
  });
}

/* Build Назва | На складі | В проектах | Доставлено | Активна потреба | Не вистачає | Залишок */
function buildTable(rows) {
  const dataRows = rows.filter(r => !r.is_section);
  if (!dataRows.length) return '<div style="opacity:.4;font-size:13px;padding:8px 0;">Немає даних</div>';

  let html = `
    <table class="eq-table">
      <thead><tr>
        <th>Назва</th>
        <th>На складі</th>
        <th>В проектах</th>
        <th style="color:#6bf;">Доставлено</th>
        <th>Активна потреба</th>
        <th>Не вистачає</th>
        <th>Залишок</th>
      </tr></thead>
      <tbody>`;

  rows.forEach((r, idx) => {
    if (r.is_section) {
      const isFirst = rows.slice(0, idx).every(x => x.is_section);
      html += `<tr class="eq-section-row${isFirst ? ' eq-section-first' : ''}">
        <td colspan="7">${esc(r.name)}</td></tr>`;
      return;
    }
    const delivered   = r.delivered ?? 0;
    const active      = r.active    ?? r.projects;
    const stockCell     = r.stock    === 0 ? `<td class="eq-zero" data-label="Склад">—</td>`         : `<td data-label="Склад">${r.stock}</td>`;
    const projectsCell  = r.projects === 0 ? `<td class="eq-zero" data-label="Проекти">—</td>`       : `<td data-label="Проекти">${r.projects}</td>`;
    const deliveredCell = delivered   >  0  ? `<td style="color:#6bf;font-weight:600;" data-label="Доставл.">${delivered}</td>` : `<td class="eq-zero" data-label="Доставл.">—</td>`;
    const activeCell    = active      === 0 ? `<td class="eq-zero" data-label="Потреба">—</td>`      : `<td data-label="Потреба">${active}</td>`;
    const shortageCell  = r.shortage  >  0  ? `<td class="eq-shortage" data-label="Нестача">${r.shortage}</td>` : `<td class="eq-zero" data-label="Нестача">—</td>`;
    const remainingCell = r.remaining >  0  ? `<td class="eq-remaining" data-label="Залишок">${r.remaining}</td>` : `<td class="eq-zero" data-label="Залишок">—</td>`;
    html += `<tr>
      <td>${esc(r.name)}</td>
      ${stockCell}${projectsCell}${deliveredCell}${activeCell}${shortageCell}${remainingCell}
    </tr>`;
  });

  html += '</tbody></table>';
  return html;
}

/* Simple list: name → qty */
function buildList(map) {
  const entries = Object.entries(map);
  if (!entries.length) return '<div style="opacity:.4;font-size:13px;padding:8px 0;">Немає даних</div>';
  return entries.map(([name, qty]) => `
    <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(255,255,255,0.06);font-size:13px;">
      <span style="opacity:.85;">${esc(name)}</span>
      <span style="font-weight:600;white-space:nowrap;margin-left:12px;">${qty} шт.</span>
    </div>`).join('');
}

async function loadEquipmentOrders() {
  const container = document.getElementById('equipmentOrdersContainer');
  if (!container) return;

  const r = await fetch('/api/equipment-orders');
  if (!r.ok) {
    container.innerHTML = '<div style="padding:20px;color:#f76;">Помилка завантаження</div>';
    return;
  }
  const { projects, summary, shortage, tables } = await r.json();
  container.innerHTML = '';

  function makeCard(titleHtml, body, open = false) {
    const card = document.createElement('div');
    card.className = 'card';
    card.style.marginBottom = '16px';
    makeCollapsible(card, titleHtml, body, open);
    container.appendChild(card);
  }

  // ── ☀️ ФОТОМОДУЛІ ────────────────────────────────────────
  if (tables.panels.length) {
    // Group by brand (first word of normalized name)
    const brandOrder = ['Trina', 'Longi', 'Jinko', 'Canadian'];
    const brandGroups = {};
    tables.panels.forEach(r => {
      const brand = r.name ? r.name.split(' ')[0] : 'Інше';
      if (!brandGroups[brand]) brandGroups[brand] = [];
      brandGroups[brand].push(r);
    });
    const sortedBrands = Object.keys(brandGroups).sort((a, b) => {
      const ai = brandOrder.indexOf(a), bi = brandOrder.indexOf(b);
      if (ai !== -1 && bi !== -1) return ai - bi;
      if (ai !== -1) return -1;
      if (bi !== -1) return 1;
      return a.localeCompare(b);
    });
    const panelsWithSections = [];
    sortedBrands.forEach(brand => {
      panelsWithSections.push({ is_section: true, name: brand });
      brandGroups[brand].forEach(r => panelsWithSections.push(r));
    });
    makeCard(
      '<span style="font-weight:800;font-size:15px;">☀️ Фотомодулі</span>',
      buildTable(panelsWithSections)
    );
  }

  // ── ⚡ АКБ ────────────────────────────────────────────────
  if (tables.batteries.length) {
    makeCard(
      '<span style="font-weight:800;font-size:15px;">⚡ АКБ</span>',
      buildTable(tables.batteries)
    );
  }

  // ── 🔌 ІНВЕРТОРИ ─────────────────────────────────────────
  if (tables.inverters.length) {
    makeCard(
      '<span style="font-weight:800;font-size:15px;">🔌 Інвертори</span>',
      buildTable(tables.inverters)
    );
  }

  // ── 🔧 ДОДАТКОВЕ ОБЛАДНАННЯ ──────────────────────────────
  if (tables.additional && tables.additional.length) {
    let rows = tables.additional.map(item => `
      <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid rgba(255,255,255,0.07);font-size:13px;">
        <span style="font-weight:500;">${esc(item.name)}</span>
        <span style="font-weight:700;white-space:nowrap;margin-left:12px;${item.stock > 0 ? 'color:#4d9;' : 'opacity:.35;'}">${item.stock} ${esc(item.unit)}</span>
      </div>`).join('');
    makeCard(
      '<span style="font-weight:800;font-size:15px;">🔧 Додаткове обладнання</span>',
      rows
    );
  }

  // ── 👥 КЛІЄНТИ НА КОМПЛЕКТАЦІЇ ───────────────────────────
  {
    let body = '';
    if (!projects.length) {
      body = '<div style="opacity:.5;padding:4px 0;">Немає клієнтів на етапі Комплектація</div>';
    } else {
      projects.forEach(p => {
        const eq = [];
        if (p.inverter)     eq.push(`<span style="opacity:.5;">Інвертор:</span> ${esc(p.inverter)}`);
        if (p.bms)          eq.push(`<span style="opacity:.5;">BMS:</span> ${esc(p.bms)}`);
        if (p.battery_name) eq.push(`<span style="opacity:.5;">АКБ:</span> ${esc(p.battery_name)}${p.battery_qty ? ` × ${p.battery_qty}` : ''}`);
        if (p.panel_name)   eq.push(`<span style="opacity:.5;">ФЕМ:</span> ${esc(p.panel_name)}${p.panel_qty ? ` × ${p.panel_qty}` : ''}`);
        body += `
          <div style="padding:11px 0;border-bottom:1px solid rgba(255,255,255,0.07);">
            <div style="font-weight:700;margin-bottom:4px;">${esc(p.client_name)}</div>
            ${eq.length
              ? eq.map(e => `<div style="font-size:13px;margin-bottom:2px;">${e}</div>`).join('')
              : '<div style="font-size:12px;opacity:.35;">Обладнання не вказано</div>'
            }
          </div>`;
      });
    }
    makeCard(
      `<span style="font-weight:800;font-size:15px;">👥 Клієнти на комплектації (${projects.length})</span>`,
      body
    );
  }

  // ── 📊 ЗВЕДЕННЯ ──────────────────────────────────────────
  {
    const hasSummary = Object.keys(summary.inverter).length
      || Object.keys(summary.bms).length
      || Object.keys(summary.battery).length
      || Object.keys(summary.panels).length;

    const body = hasSummary
      ? [['Інвертори', summary.inverter], ['BMS', summary.bms], ['АКБ', summary.battery], ['ФЕМ (панелі)', summary.panels]]
          .map(([title, map]) => !Object.keys(map).length ? '' : `
            <div style="margin-bottom:14px;">
              <div style="font-weight:700;font-size:13px;opacity:.55;margin-bottom:6px;text-transform:uppercase;letter-spacing:.04em;">${esc(title)}</div>
              ${buildList(map)}
            </div>`).join('')
      : '<div style="opacity:.5;">Обладнання не вказано</div>';

    const card = document.createElement('div');
    card.className = 'card';
    makeCollapsible(card,
      '<span style="font-weight:800;font-size:15px;">📊 Зведення по обладнанню (комплектація)</span>',
      body, false);
    container.appendChild(card);
  }
}

document.addEventListener('DOMContentLoaded', () => {
  loadEquipmentOrders().catch(() => {
    const c = document.getElementById('equipmentOrdersContainer');
    if (c) c.innerHTML = '<div style="padding:20px;color:#f76;">Помилка завантаження</div>';
  });
});
</script>

@include('partials.nav.bottom')
@endsection
