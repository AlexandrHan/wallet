@extends('layouts.app')

@push('styles')
  <link rel="stylesheet" href="/css/nav-telegram.css?v={{ filemtime(public_path('css/nav-telegram.css')) }}">
  <link rel="stylesheet" href="/css/project.css?v={{ filemtime(public_path('css/project.css')) }}">
@endpush

@section('content')
<main class="">
  <a href="/salary" class="card" style="margin-bottom:15px; display:block; text-decoration:none; color:inherit;">
    <div style="font-weight:800; font-size:18px; text-align:center;">
      📈 Зарплата відділу продажів
    </div>
  </a>

  <div class="card" style="margin-bottom:15px;">
    <div style="font-weight:800; font-size:15px; margin-bottom:10px;">
      Період нарахування
    </div>

    <div style="display:flex; gap:10px;">
      <div style="flex:1;">
        <div style="font-size:12px; opacity:.7; margin-bottom:6px;">Рік</div>
        <select id="salaryYear" class="btn" style="width:100%; margin-bottom:0;">
          <option value="2026" selected>2026</option>
        </select>
      </div>

      <div style="flex:1;">
        <div style="font-size:12px; opacity:.7; margin-bottom:6px;">Місяць</div>
        <select id="salaryMonth" class="btn" style="width:100%; margin-bottom:0;">
          <option value="1">Січень</option>
          <option value="2">Лютий</option>
          <option value="3">Березень</option>
          <option value="4">Квітень</option>
          <option value="5">Травень</option>
          <option value="6">Червень</option>
          <option value="7">Липень</option>
          <option value="8">Серпень</option>
          <option value="9">Вересень</option>
          <option value="10">Жовтень</option>
          <option value="11">Листопад</option>
          <option value="12">Грудень</option>
        </select>
      </div>
    </div>
  </div>

  <div id="managerSalaryContainer"></div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const yearEl      = document.getElementById('salaryYear');
  const monthEl     = document.getElementById('salaryMonth');
  const container   = document.getElementById('managerSalaryContainer');

  const MONTH_NAMES = {
    1:'Січень', 2:'Лютий', 3:'Березень', 4:'Квітень',
    5:'Травень', 6:'Червень', 7:'Липень', 8:'Серпень',
    9:'Вересень', 10:'Жовтень', 11:'Листопад', 12:'Грудень',
  };

  // Accordion open/closed state preserved across re-renders
  const accState = {};

  function esc(v) {
    return String(v ?? '')
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
  }

  function fmt(value) {
    return new Intl.NumberFormat('uk-UA', { minimumFractionDigits:0, maximumFractionDigits:2 }).format(Number(value || 0));
  }

  function defaultMonth() {
    const now = new Date();
    if (now.getFullYear() === 2026) {
      monthEl.value = String(now.getMonth() + 1);
    }
  }

  // ── Accordion helper ─────────────────────────────────────────────────────
  function makeAccordion(key, headerHtml, bodyHtml, defaultOpen) {
    const open = accState.hasOwnProperty(key) ? accState[key] : defaultOpen;
    return `
      <div class="card" style="margin-bottom:12px;" data-acc-key="${esc(key)}">
        <button type="button" class="acc-toggle"
          style="width:100%; display:flex; align-items:center; gap:10px; background:none; border:none;
                 color:inherit; cursor:pointer; padding:0; text-align:left;"
          data-acc-btn="${esc(key)}">
          <span style="flex:1;">${headerHtml}</span>
          <span style="font-size:11px; opacity:.65;" data-acc-chevron="${esc(key)}">${open ? '▼' : '▶'}</span>
        </button>
        <div data-acc-body="${esc(key)}" style="display:${open ? 'block' : 'none'}; margin-top:12px;">
          ${bodyHtml}
        </div>
      </div>
    `;
  }

  function bindAccordions(root) {
    root.querySelectorAll('[data-acc-btn]').forEach(btn => {
      btn.addEventListener('click', function () {
        const key  = this.dataset.accBtn;
        const body = root.querySelector(`[data-acc-body="${key}"]`);
        const chev = root.querySelector(`[data-acc-chevron="${key}"]`);
        if (!body) return;
        const opening = body.style.display === 'none';
        body.style.display = opening ? 'block' : 'none';
        if (chev) chev.textContent = opening ? '▼' : '▶';
        accState[key] = opening;
      });
    });
  }

  // ── NTV block ────────────────────────────────────────────────────────────
  function renderNtv(managers, monthName) {
    if (!Array.isArray(managers) || !managers.length) {
      return `
        <div class="card" style="margin-bottom:12px;">
          <div style="font-weight:700; font-size:15px; margin-bottom:4px;">NTV</div>
          <div style="font-size:13px; opacity:.6;">Немає менеджерів NTV.</div>
        </div>
      `;
    }

    const bodyHtml = managers.map(manager => {
      const totals   = Array.isArray(manager.totals_by_currency) ? manager.totals_by_currency : [];
      const projects = Array.isArray(manager.projects) ? manager.projects : [];

      const totalsHtml = totals.length
        ? totals.map(item => {
            const sym = {UAH:'₴', USD:'$', EUR:'€'}[item.currency] || item.currency;
            return `<span class="tag" style="margin-right:6px; margin-bottom:4px; display:inline-flex;">${fmt(item.amount)} ${sym}</span>`;
          }).join('')
        : `<span style="opacity:.6; font-size:13px;">Нарахувань за ${monthName.toLowerCase()} немає</span>`;

      const projHtml = projects.length
        ? projects.map(p => {
            const sym = {UAH:'₴', USD:'$', EUR:'€'}[p.currency] || p.currency;
            return `
              <div style="padding:8px 12px; border-radius:10px; background:rgba(255,255,255,.04);
                          border:1px solid rgba(255,255,255,.06); margin-top:6px;">
                <div style="display:flex; justify-content:space-between; gap:8px;">
                  <div style="font-weight:600;">${esc(p.client_name)}</div>
                  <div style="opacity:.6; font-size:12px; white-space:nowrap;">${esc(p.paid_at)}</div>
                </div>
                <div style="margin-top:6px; display:flex; justify-content:space-between; font-size:13px;">
                  <div>Проєкт: ${fmt(p.project_amount)} ${sym}</div>
                  <div style="font-weight:800; color:#66f2a8;">1%: ${fmt(p.commission)} ${sym}</div>
                </div>
              </div>`;
          }).join('')
        : `<div style="font-size:13px; opacity:.6; margin-top:6px;">Оплачених проєктів немає.</div>`;

      return `
        <div style="margin-bottom:14px;">
          <div style="font-weight:700; margin-bottom:4px;">${esc(manager.name)}</div>
          <div style="margin-bottom:6px;">${totalsHtml}</div>
          ${projHtml}
        </div>`;
    }).join('<hr style="border:none; border-top:1px solid rgba(255,255,255,.08); margin:12px 0;">');

    const totalCommission = managers.reduce((s, m) => {
      return s + (Array.isArray(m.totals_by_currency) ? m.totals_by_currency.reduce((ss, t) => ss + (t.amount || 0), 0) : 0);
    }, 0);

    const headerHtml = `
      <div>
        <div style="font-weight:800; font-size:15px;">NTV</div>
        ${totalCommission > 0
          ? `<div style="font-size:12px; opacity:.7; margin-top:2px;">Разом: ${fmt(totalCommission)} ₴</div>`
          : `<div style="font-size:12px; opacity:.5; margin-top:2px;">Нарахувань немає</div>`}
      </div>`;

    return makeAccordion('ntv', headerHtml, bodyHtml, false);
  }

  // ── Sales managers block ─────────────────────────────────────────────────
  function renderSalesManagers(salesManagers, monthName, usdRate) {
    if (!Array.isArray(salesManagers) || !salesManagers.length) return '';

    return salesManagers.map(mgr => {
      const projects    = Array.isArray(mgr.projects) ? mgr.projects : [];
      const percent     = Number(mgr.commission_percent || 0);
      const total_usd   = Number(mgr.total_amount || 0);
      const comm_usd    = Number(mgr.commission_usd || 0);
      const comm_uah    = Number(mgr.commission_uah || 0);
      const count       = projects.length;

      const commLabel = comm_usd > 0
        ? `<span style="font-weight:800; font-size:13px; color:#66f2a8;">${fmt(comm_usd)} $ / ${fmt(comm_uah)} ₴</span>`
        : `<span style="font-size:12px; opacity:.5;">Нарахувань немає</span>`;

      const headerHtml = `
        <div>
          <div style="font-weight:800; font-size:15px;">${esc(mgr.name)}</div>
          <div style="font-size:12px; opacity:.7; margin-top:2px; display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
            ${count > 0 ? `<span>${count} проєктів</span><span>·</span>` : ''}
            ${percent > 0 ? `<span>${percent}%</span><span>·</span>` : ''}
            ${commLabel}
          </div>
        </div>`;

      const renderDealRows = (rows) => rows.length
        ? rows.map(p => `
            <div style="padding:10px 12px; border-radius:10px; background:rgba(255,255,255,.04);
                        border:1px solid rgba(255,255,255,.06); margin-top:8px;">
              <div style="display:flex; justify-content:space-between; gap:8px; margin-bottom:4px;">
                <div style="font-weight:600; font-size:14px;">${esc(p.client_name || '—')}</div>
                <div style="opacity:.6; font-size:12px; white-space:nowrap;">
                  ${p.amo_closed_at ? `Дата закриття AMO: ${esc(p.amo_closed_at)}` : ''}
                </div>
              </div>
              ${p.deal_name ? `<div style="font-size:12px; opacity:.6; margin-bottom:4px;">${esc(p.deal_name)}</div>` : ''}
              <div style="display:flex; justify-content:space-between; align-items:center; font-size:13px; margin-top:4px;">
                <div style="opacity:.8;">
                  Сума: <strong>${fmt(p.total_amount)} ${esc(p.currency || 'USD')}</strong>
                  ${p.currency && p.currency !== 'USD' ? `<span style="opacity:.6;">≈ ${fmt(p.total_amount_usd)} $</span>` : ''}
                </div>
                <div style="font-size:11px; opacity:.45; text-align:right;">
                  ${p.pipeline_label ? `<div>${esc(p.pipeline_label)}</div>` : ''}
                  ${p.wallet_project_id
                    ? `<div>ERP #${esc(p.wallet_project_id)}</div>`
                    : (p.amo_deal_id ? `<div>AMO #${esc(p.amo_deal_id)}</div>` : '')}
                </div>
              </div>
            </div>`)
          .join('')
        : `<div style="font-size:13px; opacity:.6; margin-top:6px;">Немає угод</div>`;

      const sections = Array.isArray(window.salaryPipelineSections) ? window.salaryPipelineSections : [];
      const groupedHtml = sections.map(section => {
        const rows = projects.filter(p => String(p.pipeline_type || '') === String(section.type || ''));
        const color = section.type === 'retail' ? '#66f2a8' : '#90cdf4';

        return `
          <div style="margin-top:${section.type === 'retail' ? '16px' : '0'};">
            <div style="font-weight:800; font-size:14px; margin-bottom:8px; color:${color};">${esc(section.label || 'Pipeline')}</div>
            ${renderDealRows(rows)}
          </div>`;
      }).join('');

      const projHtml = projects.length
        ? groupedHtml
        : `<div style="font-size:13px; opacity:.6; margin-top:6px;">Виграних угод за ${monthName.toLowerCase()} немає.</div>`;

      const rateLabel = usdRate ? `<span style="opacity:.5; font-size:11px;">Курс: ${fmt(usdRate)} ₴/$</span>` : '';

      const bodyHtml = `
        ${percent > 0 ? `
          <div style="margin-bottom:10px; padding:8px 12px; border-radius:8px; background:rgba(102,242,168,.07);
                      border:1px solid rgba(102,242,168,.15); font-size:13px;">
            <div style="display:flex; justify-content:space-between;">
              <span>Сума угод</span>
              <strong>${fmt(total_usd)} $</strong>
            </div>
            <div style="display:flex; justify-content:space-between; margin-top:4px;">
              <span>Комісія (${percent}%)</span>
              <strong style="color:#66f2a8;">${fmt(comm_usd)} $ / ${fmt(comm_uah)} ₴</strong>
            </div>
            <div style="margin-top:4px; text-align:right;">${rateLabel}</div>
          </div>` : `
          <div style="margin-bottom:10px; padding:8px 12px; border-radius:8px; background:rgba(255,255,255,.04);
                      font-size:12px; opacity:.6;">
            Відсоток не налаштовано — задайте у <a href="/salary/settings" style="color:#90cdf4;">Налаштуваннях</a>.
          </div>`}
        ${projHtml}`;

      return makeAccordion(`sales-${mgr.name}`, headerHtml, bodyHtml, false);
    }).join('');
  }

  // ── Render all ───────────────────────────────────────────────────────────
  function render(payload) {
    const monthName = MONTH_NAMES[Number(payload?.month || monthEl.value)] || '';
    const usdRate   = Number(payload?.usd_uah_rate || 0);
    window.salaryPipelineSections = Array.isArray(payload?.pipeline_sections) ? payload.pipeline_sections : [];
    const ntvHtml   = renderNtv(payload?.managers || [], monthName);
    const salesHtml = renderSalesManagers(payload?.sales_managers || [], monthName, usdRate);

    container.innerHTML = ntvHtml + salesHtml;
    bindAccordions(container);
  }

  // ── Load ─────────────────────────────────────────────────────────────────
  async function load() {
    const year  = yearEl.value;
    const month = monthEl.value;

    container.innerHTML = `
      <div class="card">
        <div style="opacity:.7; font-size:14px;">Завантаження...</div>
      </div>`;

    try {
      const res     = await fetch(`/api/salary/managers-data?year=${encodeURIComponent(year)}&month=${encodeURIComponent(month)}`,
        { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      const payload = await res.json();

      if (!res.ok) throw new Error(payload.error || 'Не вдалося завантажити нарахування');

      render(payload);
    } catch (err) {
      container.innerHTML = `
        <div class="card" style="border-color:rgba(255,0,0,.35);">
          <div style="font-weight:800; margin-bottom:6px;">Помилка</div>
          <div style="opacity:.8; font-size:14px;">${esc(err.message)}</div>
        </div>`;
    }
  }

  defaultMonth();
  yearEl.addEventListener('change', load);
  monthEl.addEventListener('change', load);
  load();
});
</script>

@include('partials.nav.bottom')
@endsection
