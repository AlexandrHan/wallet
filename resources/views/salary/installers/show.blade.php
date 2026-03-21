@extends('layouts.app')

@push('styles')
  <link rel="stylesheet" href="/css/nav-telegram.css?v={{ filemtime(public_path('css/nav-telegram.css')) }}">
  <link rel="stylesheet" href="/css/project.css?v={{ filemtime(public_path('css/project.css')) }}">
@endpush

@section('content')
<main class="">
  <div class="card" style="margin-bottom:15px;">
    <a href="/salary/installers" style="display:block; font-weight:800; font-size:18px; text-align:center; text-decoration:none; color:inherit;">
      🛠 <span id="salaryInstallerNameTitle">Монтажна бригада</span>
    </a>
  </div>

  <div id="installerSalaryProjects"></div>
</main>

{{-- Payment modal --}}
<div id="payModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.75); z-index:9000;
  align-items:center; justify-content:center; padding:16px;">
  <div class="card" style="width:100%; max-width:400px; padding:20px;">
    <div style="font-weight:800; font-size:16px; margin-bottom:6px;" id="payModalTitle">Виплатити зарплату</div>
    <div style="font-size:14px; opacity:.8; margin-bottom:16px;" id="payModalDesc"></div>
    <div class="project-field-label">Гаманець для списання</div>
    <select id="payWalletSelect" class="btn" style="width:100%; margin-bottom:16px;">
      <option value="">Завантаження...</option>
    </select>
    <div style="display:flex; gap:8px;">
      <button id="payConfirmBtn" type="button" class="btn save" style="flex:1;">Виплатити</button>
      <button type="button" class="btn" style="flex:1;" onclick="closePayModal()">Скасувати</button>
    </div>
  </div>
</div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
let walletsCache = [];
let currentPayUserId = null;

function fmoney(amount, currency) {
  const sym = { UAH: '₴', USD: '$', EUR: '€' };
  return new Intl.NumberFormat('uk-UA', { maximumFractionDigits: 0 })
    .format(Number(amount) || 0) + '\u00a0' + (sym[currency] || currency || '');
}

async function openPayModal(userId, userName, total, currency) {
  currentPayUserId = userId;
  document.getElementById('payModalTitle').textContent = `Виплатити — ${userName}`;
  document.getElementById('payModalDesc').textContent  = `Сума: ${fmoney(total, currency)}`;
  const sel = document.getElementById('payWalletSelect');
  sel.innerHTML = '<option value="">Завантаження...</option>';
  try {
    if (!walletsCache.length) {
      const r = await fetch('/api/quality-checks/wallets');
      walletsCache = r.ok ? await r.json() : [];
    }
    const matching = walletsCache.filter(w => w.currency === currency);
    sel.innerHTML = matching.length
      ? matching.map(w => `<option value="${w.id}">${String(w.name ?? '').replace(/"/g,'&quot;')} (${w.currency})</option>`).join('')
      : `<option value="">Немає гаманців у ${currency}</option>`;
  } catch (_) {
    sel.innerHTML = '<option value="">Помилка завантаження</option>';
  }
  document.getElementById('payModal').style.display = 'flex';
}

function closePayModal() {
  document.getElementById('payModal').style.display = 'none';
  currentPayUserId = null;
}

async function paySalary() {
  const walletId = document.getElementById('payWalletSelect').value;
  if (!walletId) { alert('Оберіть гаманець'); return; }
  const btn = document.getElementById('payConfirmBtn');
  btn.disabled = true;
  btn.textContent = 'Виплата...';
  try {
    const r = await fetch(`/api/salary/pay/${currentPayUserId}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
      body: JSON.stringify({ wallet_id: walletId }),
    });
    const data = await r.json();
    if (r.ok && data.ok) {
      closePayModal();
      location.reload();
    } else {
      alert(data.error || 'Помилка виплати');
    }
  } catch (e) {
    alert('Помилка з\'єднання');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Виплатити';
  }
}

document.getElementById('payConfirmBtn').addEventListener('click', paySalary);

document.addEventListener('click', function (e) {
  const btn = e.target.closest('.pay-btn');
  if (btn) {
    e.preventDefault();
    openPayModal(
      parseInt(btn.dataset.userId),
      btn.dataset.userName,
      parseFloat(btn.dataset.total),
      btn.dataset.currency,
    );
    return;
  }
  if (e.target.id === 'payModal') closePayModal();
});

document.addEventListener('DOMContentLoaded', async function () {
  const root = document.getElementById('installerSalaryProjects');
  if (!root) return;

  const query = new URLSearchParams(window.location.search);
  const TEAM_NAME = String(query.get('name') || '').trim();

  const titleEl = document.getElementById('salaryInstallerNameTitle');
  if (titleEl) titleEl.textContent = TEAM_NAME || 'Монтажна бригада';

  const esc = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

  const formatMoney = (value, currency) => {
    const symbols = { UAH: '₴', USD: '$', EUR: '€' };
    return `${new Intl.NumberFormat('uk-UA', { maximumFractionDigits: 0 }).format(Number(value || 0))} ${symbols[currency] || currency}`;
  };

  const fdate = (dateStr) => {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('uk-UA', { day: '2-digit', month: '2-digit', year: 'numeric' });
  };

  const MONTH_NAMES = ['Січень','Лютий','Березень','Квітень','Травень','Червень',
                       'Липень','Серпень','Вересень','Жовтень','Листопад','Грудень'];

  function parsePanelWatts(panelName) {
    const text = String(panelName || '').replace(',', '.');
    const match = text.match(/(\d+(?:\.\d+)?)\s*(?:w|wp|вт)/i);
    if (match) return Number(match[1]);
    const anyNumber = text.match(/(\d+(?:\.\d+)?)/);
    return anyNumber ? Number(anyNumber[1]) : null;
  }

  async function loadRule() {
    const res = await fetch(`/api/salary-rules?staff_group=installation_team&staff_name=${encodeURIComponent(TEAM_NAME)}`);
    const payload = await res.json();
    if (!res.ok) throw new Error(payload.error || 'Не вдалося завантажити правило зарплатні');
    const rule = Array.isArray(payload.rules) ? payload.rules[0] : null;
    if (!rule) throw new Error(`Для ${TEAM_NAME || 'цієї бригади'} не налаштоване правило зарплатні`);
    return rule;
  }

  function renderProjectCard(item) {
    const project = item.project;
    const salary = item.salary;
    return `
      <div class="card project-card" data-project-card="${project.id}" style="margin-bottom:12px;">
        <div class="project-header" data-project-toggle="${project.id}">
          <div class="project-header-row">
            <div class="project-header-name">${esc(project.client_name || 'Без назви')}</div>
            <div class="project-header-meta">${salary.totalKw ? `${esc(salary.totalKw)} кВт` : '—'}</div>
          </div>
          <div class="project-header-row">
            <div class="project-header-sub">${esc(TEAM_NAME)}</div>
            <div class="project-header-meta" style="font-weight:800; opacity:.9;">${formatMoney(salary.amount, salary.currency)}</div>
          </div>
        </div>
        <div class="project-body">
          <div class="project-field-label">Обладнання</div>
          <div style="font-size:14px; opacity:.85;">
            <div><strong>ФЕМ:</strong> ${esc(project.panel_name || 'Не вказано')}</div>
            <div style="margin-top:6px;"><strong>К-сть ФЕМ:</strong> ${esc(project.panel_qty || 'Не вказано')}</div>
            <div style="margin-top:6px;"><strong>Інвертор:</strong> ${esc(project.inverter || 'Не вказано')}</div>
          </div>
        </div>
      </div>
    `;
  }

  // ── Paid history ─────────────────────────────────────────────────────────
  async function loadHistory() {
    const res = await fetch(
      `/api/salary/paid-history?staff_group=installation_team&staff_name=${encodeURIComponent(TEAM_NAME)}`
    );
    const data = res.ok ? await res.json() : { groups: [] };
    renderHistory(data.groups || []);
  }

  function renderHistory(groups) {
    const el = document.getElementById('paidHistorySection');
    if (!el) return;

    if (!groups.length) {
      el.innerHTML = `
        <div class="card" style="margin-bottom:12px;">
          <div class="project-header" data-folder-toggle="paid" style="cursor:pointer;">
            <div class="project-header-row">
              <div class="project-header-name">✅ Оплачені</div>
              <div class="project-header-meta" data-folder-icon="paid">▸</div>
            </div>
          </div>
          <div class="project-body" id="paidFolderBody" style="display:none;">
            <div style="font-size:14px; opacity:.6; text-align:center; padding:8px 0;">Виплат ще немає</div>
          </div>
        </div>`;
      bindFolderToggles(el);
      return;
    }

    const totalAll = groups.reduce((s, g) => s + (g.total || 0), 0);
    const currency = groups[0]?.currency || 'USD';

    const monthSections = groups.map((group, gi) => {
      const [y, m] = group.year_month.split('-');
      const monthLabel = MONTH_NAMES[parseInt(m) - 1] + ' ' + y;
      const monthId = `month_${group.year_month.replace('-','_')}`;

      const projectRows = (group.rows || []).map(row => `
        <div class="card project-card project-card--green"
          data-project-card="h${row.id}" style="margin-bottom:8px;">
          <div class="project-header" data-project-toggle="h${row.id}" style="cursor:pointer;">
            <div class="project-header-row">
              <div class="project-header-name">${esc(row.client_name || 'Без назви')}</div>
              <div class="project-header-meta" style="font-size:11px; opacity:.6;">${fdate(row.paid_at)}</div>
            </div>
            <div class="project-header-row">
              <div class="project-header-sub">✅ Виплачено</div>
              <div class="project-header-meta" style="font-weight:800; opacity:.9;">${formatMoney(row.amount, row.currency)}</div>
            </div>
          </div>
          <div class="project-body" style="display:none;">
            <div style="font-size:14px; opacity:.85;">
              <div><strong>ФЕМ:</strong> ${esc(row.panel_name || 'Не вказано')}</div>
              <div style="margin-top:6px;"><strong>К-сть ФЕМ:</strong> ${esc(row.panel_qty || 'Не вказано')}</div>
              <div style="margin-top:6px;"><strong>Інвертор:</strong> ${esc(row.inverter || 'Не вказано')}</div>
            </div>
          </div>
        </div>
      `).join('');

      // First (most recent) month is collapsed too — user can open any
      return `
        <div style="margin-bottom:6px;">
          <div style="cursor:pointer; display:flex; justify-content:space-between; align-items:center;
            padding:8px 4px; border-bottom:1px solid rgba(255,255,255,.08);"
            data-folder-toggle="${monthId}">
            <div style="font-weight:700; font-size:13px;">${monthLabel}</div>
            <div style="display:flex; align-items:center; gap:8px;">
              <span style="font-size:12px; opacity:.7;">${formatMoney(group.total, group.currency)}</span>
              <span data-folder-icon="${monthId}" style="opacity:.6;">▸</span>
            </div>
          </div>
          <div id="${monthId}" style="display:none; padding-top:8px;">
            ${projectRows}
          </div>
        </div>
      `;
    }).join('');

    el.innerHTML = `
      <div class="card" style="margin-bottom:12px;">
        <div class="project-header" data-folder-toggle="paid" style="cursor:pointer;">
          <div class="project-header-row">
            <div class="project-header-name">✅ Оплачені</div>
            <div class="project-header-row" style="gap:8px;">
              <span style="font-size:13px; opacity:.65;">${formatMoney(totalAll, currency)}</span>
              <span data-folder-icon="paid" style="opacity:.6;">▸</span>
            </div>
          </div>
        </div>
        <div class="project-body" id="paidFolderBody" style="display:none;">
          ${monthSections}
        </div>
      </div>`;

    bindFolderToggles(el);
  }

  function bindFolderToggles(el) {
    el.querySelectorAll('[data-folder-toggle]').forEach(trigger => {
      trigger.addEventListener('click', () => {
        const key = trigger.dataset.folderToggle;
        const body = key === 'paid'
          ? document.getElementById('paidFolderBody')
          : document.getElementById(key);
        if (!body) return;
        const open = window.getComputedStyle(body).display === 'none';
        body.style.display = open ? 'block' : 'none';
        const icon = el.querySelector(`[data-folder-icon="${key}"]`);
        if (icon) icon.textContent = open ? '▾' : '▸';
      });
    });

    el.querySelectorAll('[data-project-toggle]').forEach(toggle => {
      toggle.addEventListener('click', () => {
        const card = toggle.closest('[data-project-card]');
        const body = card?.querySelector('.project-body');
        if (!body) return;
        const isOpen = window.getComputedStyle(body).display !== 'none';
        body.style.display = isOpen ? 'none' : 'block';
      });
    });
  }

  // ── Main load ─────────────────────────────────────────────────────────────

  let allItems = [];
  let salaryRule = null;
  let pendingAccruals = [];
  let accrualUserId = null;
  let accrualCurrency = 'USD';

  async function loadProjects() {
    if (!TEAM_NAME) {
      root.innerHTML = `<div class="card"><div style="font-size:14px; opacity:.8; text-align:center;">Не вказана бригада</div></div>`;
      return;
    }

    root.innerHTML = `<div class="card"><div style="font-size:14px; opacity:.8; text-align:center;">Завантаження...</div></div>`;

    try {
      const [rule, res, accrualsRes] = await Promise.all([
        loadRule(),
        fetch('/api/salary/projects'),
        fetch('/api/salary/accruals'),
      ]);
      const projects      = await res.json();
      const accrualsGroups = accrualsRes.ok ? await accrualsRes.json() : [];

      const teamKey = TEAM_NAME.toLowerCase();
      for (const group of accrualsGroups) {
        const teamAccruals = (group.accruals ?? []).filter(
          a => a.staff_group === 'installation_team' &&
               String(a.staff_name || '').trim().toLowerCase() === teamKey
        );
        if (teamAccruals.length) {
          pendingAccruals = teamAccruals;
          accrualUserId   = group.user_id;
          accrualCurrency = group.currency || 'USD';
          break;
        }
      }

      if (!res.ok) throw new Error(projects.error || 'Не вдалося завантажити проєкти');

      salaryRule = rule;

      if (String(salaryRule.mode) !== 'piecework') {
        root.innerHTML = `<div class="card"><div style="font-size:14px; opacity:.8; text-align:center;">Для ${esc(TEAM_NAME)} зараз встановлена ставка, а не виробіток.</div></div>`;
        return;
      }

      // Active = projects assigned to this team that are NOT salary_paid
      allItems = (Array.isArray(projects) ? projects : [])
        .filter(p =>
          String(p?.installation_team || '').trim().toLowerCase() === TEAM_NAME.toLowerCase() &&
          p?.construction_status !== 'salary_paid'
        )
        .map(project => {
          const watts = parsePanelWatts(project?.panel_name);
          const qty = Number(project?.panel_qty || 0);
          const totalKwRaw = watts && qty ? (watts * qty) / 1000 : 0;
          const totalKw = Math.ceil(totalKwRaw);
          const amount = (totalKw * Number(salaryRule.piecework_unit_rate || 0)) + Number(salaryRule.foreman_bonus || 0);
          return { project, salary: { amount, currency: salaryRule.currency || 'USD', totalKw } };
        });

      render();

      // Load paid history after main render
      loadHistory();

    } catch (err) {
      root.innerHTML = `<div class="card"><div style="font-size:14px; opacity:.8; text-align:center;">${esc(err.message || 'Помилка завантаження')}</div></div>`;
    }
  }

  function renderPayFolder() {
    if (!pendingAccruals.length || !accrualUserId) return '';
    const total = pendingAccruals.reduce((s, a) => s + Number(a.amount || 0), 0);
    const rows = pendingAccruals.map(a => `
      <div style="display:flex; justify-content:space-between; align-items:center;
        padding:6px 0; border-bottom:1px solid rgba(255,255,255,.07); font-size:13px;">
        <div style="opacity:.85;">${esc(a.client_name)}</div>
        <div style="font-weight:700; white-space:nowrap; padding-left:10px;">${formatMoney(a.amount, a.currency)}</div>
      </div>
    `).join('');
    return `
      <div class="card" style="margin-bottom:12px; border:1px solid rgba(245,200,66,.25);">
        <div class="project-header" data-pay-folder-toggle="1" style="cursor:pointer;">
          <div class="project-header-row">
            <div class="project-header-name" style="color:#f5c842;">💸 Виплата зарплати</div>
            <div class="project-header-meta">▾</div>
          </div>
        </div>
        <div class="project-body" id="payFolderBody">
          ${rows}
          <button type="button" class="btn save pay-btn"
            data-user-id="${accrualUserId}"
            data-user-name="${esc(TEAM_NAME)}"
            data-total="${total}"
            data-currency="${esc(accrualCurrency)}"
            style="width:100%; margin-top:10px;">
            💸 Виплатити ${formatMoney(total, accrualCurrency)}
          </button>
        </div>
      </div>
    `;
  }

  function render() {
    root.innerHTML = `
      ${renderPayFolder()}
      <div id="paidHistorySection"></div>
      ${allItems.length
        ? allItems.map(item => renderProjectCard(item)).join('')
        : '<div class="card" style="margin-bottom:12px;"><div style="font-size:14px; opacity:.8; text-align:center;">Активних проєктів без виплати немає</div></div>'}
    `;

    document.addEventListener('click', function (e) {
      const target = e.target instanceof Element ? e.target : null;
      if (!target) return;

      const payFolderToggle = target.closest('[data-pay-folder-toggle]');
      if (payFolderToggle) {
        const body = document.getElementById('payFolderBody');
        if (!body) return;
        const open = window.getComputedStyle(body).display === 'none';
        body.style.display = open ? 'block' : 'none';
        const icon = payFolderToggle.querySelector('.project-header-meta');
        if (icon) icon.textContent = open ? '▾' : '▸';
        return;
      }

      const projectToggle = target.closest('[data-project-toggle]');
      if (!projectToggle) return;
      const card = projectToggle.closest('[data-project-card]');
      const body = card?.querySelector('.project-body');
      if (!body) return;
      const isOpen = window.getComputedStyle(body).display !== 'none';
      body.style.display = isOpen ? 'none' : 'block';
    });
  }

  loadProjects();
});
</script>

@include('partials.nav.bottom')
@endsection
