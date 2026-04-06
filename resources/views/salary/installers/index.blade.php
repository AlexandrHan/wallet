@extends('layouts.app')

@push('styles')
  <link rel="stylesheet" href="/css/nav-telegram.css?v={{ filemtime(public_path('css/nav-telegram.css')) }}">
  <link rel="stylesheet" href="/css/project.css?v={{ filemtime(public_path('css/project.css')) }}">
@endpush

@section('content')
<main class="">
  <div class="card" style="margin-bottom:15px;">
    <a href="/salary" style="display:block; font-weight:800; font-size:18px; text-align:center; text-decoration:none; color:inherit;">
      🛠 З/П монтажникам
    </a>
  </div>

  <div id="salaryInstallersList"></div>
</main>

{{-- Payment modal --}}
<div id="payModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.75); z-index:9000;
  align-items:center; justify-content:center; padding:16px; overflow-y:auto;">
  <div class="card" style="width:100%; max-width:420px; padding:20px; margin:auto;">
    <div style="font-weight:800; font-size:16px; margin-bottom:4px;" id="payModalTitle">Виплатити зарплату</div>
    <div style="font-size:13px; opacity:.7; margin-bottom:16px;" id="payModalDesc"></div>

    {{-- USD частина --}}
    <div class="project-field-label">Сума в $ (USD)</div>
    <input id="payUsdAmount" type="number" min="0" step="1" class="btn"
      style="width:100%; margin-bottom:8px; text-align:right;"
      placeholder="0">

    <div class="project-field-label">Гаманець USD</div>
    <select id="payUsdWallet" class="btn" style="width:100%; margin-bottom:16px;">
      <option value="">— не платити в $ —</option>
    </select>

    {{-- UAH частина --}}
    <div style="border-top:1px solid rgba(255,255,255,.12); padding-top:14px; margin-bottom:14px;">
      <div class="project-field-label">Сума в ₴ (UAH)</div>
      <input id="payUahAmount" type="number" min="0" step="1" class="btn"
        style="width:100%; margin-bottom:4px; text-align:right;"
        placeholder="0">
      <div id="payRateHint" style="font-size:12px; opacity:.6; margin-bottom:8px;"></div>

      <div class="project-field-label">Гаманець UAH</div>
      <select id="payUahWallet" class="btn" style="width:100%; margin-bottom:0;">
        <option value="">— не платити в ₴ —</option>
      </select>
    </div>

    <div id="payTotalHint" style="font-size:13px; opacity:.75; margin-bottom:16px; text-align:right;"></div>

    <div style="display:flex; gap:8px;">
      <button id="payConfirmBtn" type="button" class="btn save" style="flex:1;">Виплатити</button>
      <button type="button" class="btn" style="flex:1;" onclick="closePayModal()">Скасувати</button>
    </div>
  </div>
</div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
let walletsCache = [];
let fxRate = 0;
let currentPayUserId = null;
let currentPayTotal = 0;
let currentPayCurrency = 'USD';

function fmoney(amount, currency) {
  const sym = { UAH: '₴', USD: '$', EUR: '€' };
  return new Intl.NumberFormat('uk-UA', { maximumFractionDigits: 2 })
    .format(Number(amount) || 0) + '\u00a0' + (sym[currency] || currency || '');
}

function updateTotalHint() {
  const usd = parseFloat(document.getElementById('payUsdAmount').value) || 0;
  const uah = parseFloat(document.getElementById('payUahAmount').value) || 0;
  const hint = document.getElementById('payTotalHint');
  const parts = [];
  if (usd > 0) parts.push(fmoney(usd, 'USD'));
  if (uah > 0) parts.push(fmoney(uah, 'UAH'));
  hint.textContent = parts.length ? 'Разом: ' + parts.join(' + ') : '';
}

function updateUahFromRemainder() {
  if (currentPayCurrency !== 'USD' || fxRate <= 0) return;
  const usdInput = parseFloat(document.getElementById('payUsdAmount').value) || 0;
  const remainder = Math.max(0, currentPayTotal - usdInput);
  const uah = remainder > 0 ? Math.round(remainder * fxRate) : 0;
  document.getElementById('payUahAmount').value = uah || '';
  document.getElementById('payRateHint').textContent =
    remainder > 0 ? `${fmoney(remainder, 'USD')} × ${fxRate} = ${fmoney(uah, 'UAH')}` : '';
  updateTotalHint();
}

async function openPayModal(userId, userName, total, currency) {
  currentPayUserId = userId;
  currentPayTotal  = total;
  currentPayCurrency = currency;

  document.getElementById('payModalTitle').textContent = `Виплатити — ${userName}`;
  document.getElementById('payModalDesc').textContent  = `Нараховано: ${fmoney(total, currency)}`;

  // Defaults
  document.getElementById('payUsdAmount').value = currency === 'USD' ? total : 0;
  document.getElementById('payUahAmount').value = '';
  document.getElementById('payRateHint').textContent = '';
  document.getElementById('payTotalHint').textContent = '';

  // Load wallets + FX in parallel
  const [walletsRes, fxRes] = await Promise.all([
    walletsCache.length ? Promise.resolve(null) : fetch('/api/quality-checks/wallets'),
    fxRate > 0          ? Promise.resolve(null) : fetch('/api/salary/fx-rate'),
  ]);

  if (walletsRes) walletsCache = walletsRes.ok ? await walletsRes.json() : [];
  if (fxRes)      fxRate = fxRes.ok ? (await fxRes.json()).usd_buy || 0 : 0;

  // Populate USD wallets
  const usdSel = document.getElementById('payUsdWallet');
  const usdWallets = walletsCache.filter(w => w.currency === 'USD');
  usdSel.innerHTML = '<option value="">— не платити в $ —</option>' +
    usdWallets.map(w => `<option value="${w.id}">${String(w.name ?? '').replace(/"/g,'&quot;')}</option>`).join('');

  // Populate UAH wallets
  const uahSel = document.getElementById('payUahWallet');
  const uahWallets = walletsCache.filter(w => w.currency === 'UAH');
  uahSel.innerHTML = '<option value="">— не платити в ₴ —</option>' +
    uahWallets.map(w => `<option value="${w.id}">${String(w.name ?? '').replace(/"/g,'&quot;')}</option>`).join('');

  if (fxRate > 0) {
    document.getElementById('payRateHint').textContent = `Курс обмінника: ${fxRate} ₴/$ (залишок конвертується автоматично)`;
  }

  updateTotalHint();
  document.getElementById('payModal').style.display = 'flex';
}

function closePayModal() {
  document.getElementById('payModal').style.display = 'none';
  currentPayUserId = null;
}

async function paySalary() {
  const usdAmount = parseFloat(document.getElementById('payUsdAmount').value) || 0;
  const uahAmount = parseFloat(document.getElementById('payUahAmount').value) || 0;
  const usdWalletId = document.getElementById('payUsdWallet').value;
  const uahWalletId = document.getElementById('payUahWallet').value;

  if (usdAmount <= 0 && uahAmount <= 0) {
    alert('Введіть суму для виплати');
    return;
  }
  if (usdAmount > 0 && !usdWalletId) { alert('Оберіть USD гаманець'); return; }
  if (uahAmount > 0 && !uahWalletId) { alert('Оберіть UAH гаманець'); return; }

  const btn = document.getElementById('payConfirmBtn');
  btn.disabled = true;
  btn.textContent = 'Виплата...';

  const body = {
    usd_wallet_id: usdWalletId ? parseInt(usdWalletId) : 0,
    uah_wallet_id: uahWalletId ? parseInt(uahWalletId) : 0,
    usd_paid:      usdAmount,
    uah_paid:      uahAmount,
  };

  try {
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
document.getElementById('payUsdAmount').addEventListener('input', updateUahFromRemainder);

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
  const list = document.getElementById('salaryInstallersList');
  if (!list) return;

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

  function parsePanelWatts(panelName) {
    const text = String(panelName || '').replace(',', '.');
    const match = text.match(/(\d+(?:\.\d+)?)\s*(?:w|wp|вт)/i);
    if (match) return Number(match[1]);
    const anyNumber = text.match(/(\d+(?:\.\d+)?)/);
    return anyNumber ? Number(anyNumber[1]) : null;
  }

  function calculateInstallerSalary(project, rule) {
    const watts = parsePanelWatts(project?.panel_name);
    const qty = Number(project?.panel_qty || 0);
    const totalKwRaw = watts && qty ? (watts * qty) / 1000 : 0;
    const totalKw = Math.ceil(totalKwRaw);
    const unitRate = Number(rule?.piecework_unit_rate || 0);
    const foremanBonus = Number(rule?.foreman_bonus || 0);
    return { amount: (totalKw * unitRate) + foremanBonus, currency: rule?.currency || 'USD' };
  }

  function renderPendingFolder(accruals, userId, userName, currency) {
    if (!accruals || !accruals.length) return '';

    const total = accruals.reduce((s, a) => s + Number(a.amount || 0), 0);
    const rows = accruals.map(a => `
      <div style="display:flex; justify-content:space-between; align-items:center;
        padding:6px 0; border-bottom:1px solid rgba(255,255,255,.07); font-size:13px;">
        <div style="opacity:.85;">${esc(a.client_name)}</div>
        <div style="font-weight:700; white-space:nowrap; padding-left:10px;">${formatMoney(a.amount, a.currency)}</div>
      </div>
    `).join('');

    return `
      <details style="margin-top:12px;" open>
        <summary style="cursor:pointer; font-size:13px; font-weight:700; color:#f5c842; padding:4px 0; list-style:none;">
          📁 Очікує виплати (${accruals.length} ${accruals.length === 1 ? 'проєкт' : 'проєкти'}) — ${formatMoney(total, currency)}
        </summary>
        <div style="margin-top:6px;">
          ${rows}
          <button type="button" class="btn save pay-btn"
            data-user-id="${userId}"
            data-user-name="${esc(userName)}"
            data-total="${total}"
            data-currency="${esc(currency)}"
            style="width:100%; margin-top:10px;">
            💸 Виплатити ${formatMoney(total, currency)}
          </button>
        </div>
      </details>
    `;
  }

  list.innerHTML = `<div class="card"><div style="font-size:14px; opacity:.8; text-align:center;">Завантаження...</div></div>`;

  try {
    const [staffRes, projectsRes, rulesRes, accrualsRes] = await Promise.all([
      fetch('/api/construction-staff-options'),
      fetch('/api/salary/projects'),
      fetch('/api/salary-rules?staff_group=installation_team'),
      fetch('/api/salary/accruals'),
    ]);

    const staff = await staffRes.json();
    const projects = await projectsRes.json();
    const rulesPayload = await rulesRes.json();
    const accrualsGroups = accrualsRes.ok ? await accrualsRes.json() : [];

    if (!staffRes.ok) throw new Error(staff.error || 'Не вдалося завантажити монтажників');
    if (!projectsRes.ok) throw new Error(projects.error || 'Не вдалося завантажити проєкти');
    if (!rulesRes.ok) throw new Error(rulesPayload.error || 'Не вдалося завантажити правила зарплатні');

    // Map accruals by staff_name (lowercase), only installation_team group
    const accrualsByName = {};
    for (const group of accrualsGroups) {
      const installerAccruals = (group.accruals ?? []).filter(a => a.staff_group === 'installation_team');
      if (!installerAccruals.length) continue;
      const key = String(installerAccruals[0].staff_name || '').trim().toLowerCase();
      if (key) accrualsByName[key] = { ...group, accruals: installerAccruals };
    }

    const teams = Array.isArray(staff.installation_team) ? staff.installation_team : [];
    const rules = Array.isArray(rulesPayload.rules) ? rulesPayload.rules : [];
    const rulesMap = new Map(rules.map(rule => [String(rule?.staff_name || '').trim().toLowerCase(), rule]));
    const projectList = Array.isArray(projects) ? projects : [];

    if (!teams.length) {
      list.innerHTML = `<div class="card"><div style="font-size:14px; opacity:.8; text-align:center;">Монтажних бригад поки немає</div></div>`;
      return;
    }

    const html = [];

    teams
      .map(team => String(team.name || '').trim())
      .filter(Boolean)
      .forEach((name) => {
        const key = name.toLowerCase();
        const rule = rulesMap.get(key);
        const accrualGroup = accrualsByName[key];
        const pendingAccruals = accrualGroup?.accruals ?? [];
        const accrualCurrency = accrualGroup?.currency || 'USD';
        const userId = accrualGroup?.user_id;

        if (!rule) {
          html.push(`
            <div class="card" style="margin-bottom:12px;">
              <div style="font-weight:800; font-size:16px; margin-bottom:6px;">🛠 ${esc(name)}</div>
              <div style="font-size:14px; opacity:.75;">Правило нарахування ще не задано.</div>
              ${userId ? renderPendingFolder(pendingAccruals, userId, name, accrualCurrency) : ''}
            </div>
          `);
          return;
        }

        if (String(rule.mode) === 'fixed') {
          const pending = userId ? renderPendingFolder(pendingAccruals, userId, name, accrualCurrency) : '';
          if (!pending) {
            html.push(`
              <a href="/salary/fixed/show?staff_group=installation_team&staff_name=${encodeURIComponent(name)}" class="card" style="margin-bottom:12px; display:block; text-decoration:none; color:inherit;">
                <div style="display:flex; justify-content:space-between; gap:10px; align-items:center;">
                  <div>
                    <div style="font-weight:800; font-size:16px;">🛠 ${esc(name)}</div>
                    <div style="font-size:13px; opacity:.72; margin-top:4px;">Помісячна зарплата</div>
                  </div>
                  <div style="font-weight:900; font-size:18px; white-space:nowrap;">${formatMoney(rule.fixed_amount || 0, rule.currency || 'UAH')}</div>
                </div>
              </a>
            `);
          } else {
            html.push(`
              <div class="card" style="margin-bottom:12px;">
                <div style="display:flex; justify-content:space-between; gap:10px; align-items:center;">
                  <div>
                    <div style="font-weight:800; font-size:16px;">🛠 ${esc(name)}</div>
                    <div style="font-size:13px; opacity:.72; margin-top:4px;">Помісячна зарплата</div>
                  </div>
                  <a href="/salary/fixed/show?staff_group=installation_team&staff_name=${encodeURIComponent(name)}"
                    style="font-weight:900; font-size:18px; white-space:nowrap; text-decoration:none; color:inherit;">
                    ${formatMoney(rule.fixed_amount || 0, rule.currency || 'UAH')}
                  </a>
                </div>
                ${pending}
              </div>
            `);
          }
          return;
        }

        const total = projectList
          .filter(project => String(project?.installation_team || '').trim().toLowerCase() === key)
          .map(project => calculateInstallerSalary(project, rule))
          .reduce((sum, calc) => sum + Number(calc.amount || 0), 0);

        const pending = userId ? renderPendingFolder(pendingAccruals, userId, name, accrualCurrency) : '';

        if (!pending) {
          html.push(`
            <a href="/salary/installers/show?name=${encodeURIComponent(name)}" class="card" style="margin-bottom:12px; display:block; text-decoration:none; color:inherit;">
              <div style="display:flex; justify-content:space-between; gap:10px; align-items:center;">
                <div>
                  <div style="font-weight:800; font-size:16px;">🛠 ${esc(name)}</div>
                  <div style="font-size:13px; opacity:.72; margin-top:4px;">Відкрити проєкти бригади</div>
                </div>
                <div style="font-weight:900; font-size:18px; white-space:nowrap;">${formatMoney(total, rule.currency || 'USD')}</div>
              </div>
            </a>
          `);
        } else {
          html.push(`
            <div class="card" style="margin-bottom:12px;">
              <div style="display:flex; justify-content:space-between; gap:10px; align-items:center;">
                <div>
                  <div style="font-weight:800; font-size:16px;">🛠 ${esc(name)}</div>
                  <div style="font-size:13px; opacity:.72; margin-top:4px;">
                    <a href="/salary/installers/show?name=${encodeURIComponent(name)}" style="color:inherit;">Відкрити проєкти →</a>
                  </div>
                </div>
                <div style="font-weight:900; font-size:18px; white-space:nowrap;">${formatMoney(total, rule.currency || 'USD')}</div>
              </div>
              ${pending}
            </div>
          `);
        }
      });

    list.innerHTML = html.join('');
  } catch (err) {
    list.innerHTML = `<div class="card"><div style="font-size:14px; opacity:.8; text-align:center;">${String(err.message || 'Помилка завантаження').replace(/</g,'&lt;')}</div></div>`;
  }
});
</script>

@include('partials.nav.bottom')
@endsection
