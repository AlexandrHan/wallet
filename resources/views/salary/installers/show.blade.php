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
  align-items:center; justify-content:center; padding:16px; overflow-y:auto;">
  <div class="card" style="width:100%; max-width:420px; padding:20px; margin:auto;">
    <div style="font-weight:800; font-size:16px; margin-bottom:14px;" id="payModalTitle">💸 Виплатити зарплату</div>

    {{-- Salary summary line --}}
    <div id="payModalSalaryLine" style="font-size:15px; font-weight:700; margin-bottom:4px;"></div>

    {{-- Bonus row --}}
    <div style="margin-bottom:14px;">
      <div class="project-field-label" style="margin-bottom:6px;">Премія</div>
      <div style="display:flex; gap:8px; align-items:center;">
        <input id="bonusAmountInput" type="number" min="0" step="1" placeholder="0"
          class="btn" style="flex:1; text-align:right;" value="0">
        <div style="display:flex; border-radius:8px; overflow:hidden; border:1px solid rgba(255,255,255,.2);">
          <button id="bonusCurrencyUsd" type="button"
            style="padding:8px 14px; font-weight:700; background:rgba(245,200,66,.25); color:#f5c842; border:none; cursor:pointer;">
            USD
          </button>
          <button id="bonusCurrencyUah" type="button"
            style="padding:8px 14px; font-weight:700; background:transparent; color:inherit; border:none; cursor:pointer; opacity:.5;">
            UAH
          </button>
        </div>
      </div>
    </div>

    {{-- USD to pay --}}
    <div style="margin-bottom:10px;">
      <div class="project-field-label" style="margin-bottom:6px;">Виплатити в USD</div>
      <input id="usdPaidInput" type="number" min="0" step="1"
        class="btn" style="width:100%; text-align:right;">
    </div>

    {{-- Live breakdown --}}
    <div id="payBreakdown" style="margin-bottom:14px; font-size:13px; opacity:.8; padding:10px 12px;
      border-radius:8px; background:rgba(255,255,255,.06); line-height:1.7;"></div>

    {{-- USD wallet --}}
    <div id="usdWalletRow" style="margin-bottom:12px;">
      <div class="project-field-label" style="margin-bottom:6px;">USD гаманець</div>
      <select id="usdWalletSelect" class="btn" style="width:100%;">
        <option value="">Завантаження...</option>
      </select>
    </div>

    {{-- UAH wallet --}}
    <div id="uahWalletRow" style="margin-bottom:16px;">
      <div class="project-field-label" style="margin-bottom:6px;">UAH гаманець</div>
      <select id="uahWalletSelect" class="btn" style="width:100%;">
        <option value="">Завантаження...</option>
      </select>
    </div>

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
let _payModalSalaryUsd = 0;
let _bonusCurrency = 'USD';
let _FX_USD = 40; // loaded from fx_rates on modal open

function fmoney(amount, currency) {
  const sym = { UAH: '₴', USD: '$', EUR: '€' };
  return new Intl.NumberFormat('uk-UA', { maximumFractionDigits: 2 })
    .format(Number(amount) || 0) + '\u00a0' + (sym[currency] || currency || '');
}

function fmt(n) {
  return new Intl.NumberFormat('uk-UA', { maximumFractionDigits: 2 }).format(Number(n) || 0);
}

function setBonusCurrency(cur) {
  _bonusCurrency = cur;
  const usdBtn = document.getElementById('bonusCurrencyUsd');
  const uahBtn = document.getElementById('bonusCurrencyUah');
  if (cur === 'USD') {
    usdBtn.style.background = 'rgba(245,200,66,.25)';
    usdBtn.style.color = '#f5c842';
    usdBtn.style.opacity = '1';
    uahBtn.style.background = 'transparent';
    uahBtn.style.opacity = '.5';
  } else {
    uahBtn.style.background = 'rgba(100,180,255,.2)';
    uahBtn.style.color = '#7ec8f8';
    uahBtn.style.opacity = '1';
    usdBtn.style.background = 'transparent';
    usdBtn.style.opacity = '.5';
  }
  updatePayModalCalc();
}

function updatePayModalCalc() {
  const salaryUsd  = _payModalSalaryUsd;
  const bonusAmt   = Math.max(0, parseFloat(document.getElementById('bonusAmountInput')?.value || 0));
  const bonusUsd   = _bonusCurrency === 'USD' ? bonusAmt : 0;
  const bonusUah   = _bonusCurrency === 'UAH' ? bonusAmt : 0;
  const usdTotal   = salaryUsd + bonusUsd;

  const usdPaidInput = parseFloat(document.getElementById('usdPaidInput')?.value || 0);
  const usdToPay  = (usdPaidInput > 0 && usdPaidInput < usdTotal) ? usdPaidInput : usdTotal;
  const usdRemain = Math.max(0, usdTotal - usdToPay);
  const uahFromUsd = usdRemain * _FX_USD;
  const uahTotal   = uahFromUsd + bonusUah;

  // Breakdown text
  const lines = [];
  lines.push(`ЗП: ${fmt(salaryUsd)} USD`);
  if (bonusUsd > 0) lines.push(`Премія: ${fmt(bonusUsd)} USD`);
  if (bonusUah > 0) lines.push(`Премія: ${fmt(bonusUah)} ₴`);
  lines.push(`─────────────`);
  lines.push(`💵 USD виплата: ${fmt(usdToPay)} USD`);
  if (usdRemain > 0) {
    lines.push(`💱 Залишок: ${fmt(usdRemain)} USD × ${fmt(_FX_USD)} = ${fmt(uahFromUsd)} ₴`);
  }
  if (bonusUah > 0) lines.push(`  + премія ${fmt(bonusUah)} ₴`);
  if (uahTotal > 0) lines.push(`🇺🇦 UAH виплата: ${fmt(uahTotal)} ₴`);
  lines.push(`<span style="opacity:.45; font-size:11px;">Курс (купівля): ${fmt(_FX_USD)} ₴/$</span>`);

  const bd = document.getElementById('payBreakdown');
  if (bd) bd.innerHTML = lines.join('<br>');

  // Show/hide wallet rows
  const usdRow = document.getElementById('usdWalletRow');
  const uahRow = document.getElementById('uahWalletRow');
  if (usdRow) usdRow.style.display = usdToPay > 0 ? 'block' : 'none';
  if (uahRow) uahRow.style.display = uahTotal > 0 ? 'block' : 'none';
}

async function loadPayWallets() {
  const usdSel = document.getElementById('usdWalletSelect');
  const uahSel = document.getElementById('uahWalletSelect');
  try {
    if (!walletsCache.length) {
      const r = await fetch('/api/quality-checks/wallets');
      walletsCache = r.ok ? await r.json() : [];
    }
    const usdWallets = walletsCache.filter(w => w.currency === 'USD');
    const uahWallets = walletsCache.filter(w => w.currency === 'UAH');

    usdSel.innerHTML = usdWallets.length
      ? usdWallets.map(w => `<option value="${w.id}">${String(w.name ?? '').replace(/"/g,'&quot;')} (USD)</option>`).join('')
      : '<option value="">Немає USD гаманців</option>';

    uahSel.innerHTML = uahWallets.length
      ? uahWallets.map(w => `<option value="${w.id}">${String(w.name ?? '').replace(/"/g,'&quot;')} (UAH)</option>`).join('')
      : '<option value="">Немає UAH гаманців</option>';
  } catch (_) {
    usdSel.innerHTML = '<option value="">Помилка завантаження</option>';
    uahSel.innerHTML = '<option value="">Помилка завантаження</option>';
  }
}

async function openPayModal(userId, userName, salaryUsd) {
  currentPayUserId  = userId;
  _payModalSalaryUsd = salaryUsd;

  document.getElementById('payModalTitle').textContent = `Виплатити — ${userName}`;
  document.getElementById('payModalSalaryLine').textContent = `ЗП: ${fmoney(salaryUsd, 'USD')}`;

  document.getElementById('bonusAmountInput').value = 0;
  document.getElementById('usdPaidInput').value = Math.round(salaryUsd);

  // Load FX rate from internal exchange
  try {
    const r = await fetch('/api/fx/rates', { headers: { Accept: 'application/json' } });
    if (r.ok) {
      const d = await r.json();
      const usdRow = (d.rates || []).find(x => x.currency === 'USD');
      if (usdRow?.purchase) _FX_USD = usdRow.purchase;
    }
  } catch (_) {}

  setBonusCurrency('USD');
  updatePayModalCalc();
  loadPayWallets();

  document.getElementById('payModal').style.display = 'flex';
}

function closePayModal() {
  document.getElementById('payModal').style.display = 'none';
  currentPayUserId = null;
}

async function paySalary() {
  const salaryUsd   = _payModalSalaryUsd;
  const bonusAmt    = Math.max(0, parseFloat(document.getElementById('bonusAmountInput').value || 0));
  const bonusUsd    = _bonusCurrency === 'USD' ? bonusAmt : 0;
  const bonusUah    = _bonusCurrency === 'UAH' ? bonusAmt : 0;
  const usdTotal    = salaryUsd + bonusUsd;
  const usdPaidInput = parseFloat(document.getElementById('usdPaidInput').value || 0);
  const usdToPay    = (usdPaidInput > 0 && usdPaidInput < usdTotal) ? usdPaidInput : usdTotal;
  const usdRemain   = Math.max(0, usdTotal - usdToPay);
  const uahTotal    = usdRemain * _FX_USD + bonusUah;

  const usdWalletId = usdToPay > 0 ? document.getElementById('usdWalletSelect').value : '';
  const uahWalletId = uahTotal > 0 ? document.getElementById('uahWalletSelect').value : '';

  if (usdToPay > 0 && !usdWalletId) { alert('Оберіть USD гаманець'); return; }
  if (uahTotal > 0 && !uahWalletId) { alert('Оберіть UAH гаманець'); return; }

  const btn = document.getElementById('payConfirmBtn');
  btn.disabled = true;
  btn.textContent = 'Виплата...';
  try {
    const body = {
      usd_wallet_id:   parseInt(usdWalletId) || 0,
      uah_wallet_id:   parseInt(uahWalletId) || 0,
      usd_paid:        usdToPay,
      bonus_amount:    bonusAmt,
      bonus_currency:  _bonusCurrency,
    };
    const r = await fetch(`/api/salary/pay/${currentPayUserId}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
      body: JSON.stringify(body),
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
document.getElementById('bonusCurrencyUsd').addEventListener('click', () => setBonusCurrency('USD'));
document.getElementById('bonusCurrencyUah').addEventListener('click', () => setBonusCurrency('UAH'));
document.getElementById('bonusAmountInput').addEventListener('input', updatePayModalCalc);
document.getElementById('usdPaidInput').addEventListener('input', updatePayModalCalc);

document.addEventListener('click', function (e) {
  const btn = e.target.closest('.pay-btn');
  if (btn) {
    e.preventDefault();
    openPayModal(
      parseInt(btn.dataset.userId),
      btn.dataset.userName,
      parseFloat(btn.dataset.salaryUsd || btn.dataset.total || 0),
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

      const projectRows = (group.rows || []).map(row => {
        let paidLine = '';
        if (row.paid_usd > 0 && row.paid_uah > 0) {
          paidLine = `${formatMoney(row.paid_usd, 'USD')} + ${formatMoney(row.paid_uah, 'UAH')}`;
        } else if (row.paid_usd > 0) {
          paidLine = formatMoney(row.paid_usd, 'USD');
        } else if (row.paid_uah > 0) {
          paidLine = formatMoney(row.paid_uah, 'UAH');
        } else {
          paidLine = formatMoney(row.amount, row.currency);
        }
        const rateNote = row.paid_rate ? ` • курс ${row.paid_rate}` : '';
        return `
        <div class="card project-card project-card--green"
          data-project-card="h${row.id}" style="margin-bottom:8px;">
          <div class="project-header" data-project-toggle="h${row.id}" style="cursor:pointer;">
            <div class="project-header-row">
              <div class="project-header-name">${esc(row.client_name || 'Без назви')}</div>
              <div class="project-header-meta" style="font-size:11px; opacity:.6;">${fdate(row.paid_at)}${esc(rateNote)}</div>
            </div>
            <div class="project-header-row">
              <div class="project-header-sub">✅ Виплачено</div>
              <div class="project-header-meta" style="font-weight:800; opacity:.9; font-size:13px;">${paidLine}</div>
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
      `;
      }).join('');

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
            data-salary-usd="${pendingAccruals.filter(a=>a.currency==='USD').reduce((s,a)=>s+Number(a.amount||0),0)}"
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
