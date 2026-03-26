@push('styles')
  <link rel="stylesheet" href="/css/nav-telegram.css?v={{ filemtime(public_path('css/nav-telegram.css')) }}">
  <link rel="stylesheet" href="/css/project.css?v={{ filemtime(public_path('css/project.css')) }}">
@endpush

@extends('layouts.app')

@section('content')
<main style="padding:0 0 80px;">

  <div class="projects-title-card">
    <div class="projects-title">
      <a href="/salary" style="color:inherit; text-decoration:none; font-size:14px; opacity:.6;">← Зарплатня</a>
      <div style="margin-top:4px;">💸 Виплата зарплат</div>
    </div>
  </div>

  <div id="accrualsRoot">
    <div class="card" style="text-align:center; opacity:.6; font-size:14px;">Завантаження...</div>
  </div>

</main>

{{-- Payment modal --}}
<div id="payModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.75); z-index:9000;
  align-items:center; justify-content:center; padding:16px; overflow-y:auto;">
  <div class="card" style="width:100%; max-width:420px; padding:20px; margin:auto;">
    <div style="font-weight:800; font-size:16px; margin-bottom:14px;" id="payModalTitle">💸 Виплатити зарплату</div>

    <div id="payModalSalaryLine" style="font-size:15px; font-weight:700; margin-bottom:14px;"></div>

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

    <div style="margin-bottom:12px;">
      <div class="project-field-label" style="margin-bottom:6px;">Виплатити в USD</div>
      <input id="usdPaidInput" type="number" min="0" step="1"
        class="btn" style="width:100%; text-align:right;">
    </div>

    <div id="payBreakdown" style="margin-bottom:14px; font-size:13px; opacity:.8; padding:10px 12px;
      border-radius:8px; background:rgba(255,255,255,.06); line-height:1.7;"></div>

    <div id="usdWalletRow" style="margin-bottom:12px;">
      <div class="project-field-label" style="margin-bottom:6px;">USD гаманець</div>
      <select id="usdWalletSelect" class="btn" style="width:100%;">
        <option value="">Завантаження...</option>
      </select>
    </div>

    <div id="uahWalletRow" style="margin-bottom:16px; display:none;">
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

function esc(v) {
  return String(v ?? '')
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

function fmoney(amount, currency) {
  const sym = { UAH: '₴', USD: '$', EUR: '€' };
  return new Intl.NumberFormat('uk-UA', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
    .format(Number(amount) || 0) + '\u00a0' + (sym[currency] || currency || '');
}

function fdate(dateStr) {
  if (!dateStr) return '—';
  return new Date(dateStr).toLocaleDateString('uk-UA', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

// ── State ─────────────────────────────────────────────────────────────────
let walletsCache = [];
let currentPayUserId = null;
let currentPayUserName = '';
let allGroups = [];       // pending groups (from API)
let paidGroups = [];      // paid groups (from paid API)

// ── Render ────────────────────────────────────────────────────────────────

function renderWorkerBlock(pendingGroup, paidForUser) {
  const userId   = pendingGroup?.user_id ?? (paidForUser?.[0]?.user_id);
  const userName = pendingGroup?.user_name ?? (paidForUser?.[0]?.user_name ?? '');
  const currency = pendingGroup?.currency ?? (paidForUser?.[0]?.currency ?? 'USD');

  const pendingAccruals = pendingGroup?.accruals ?? [];
  const paidAccruals    = paidForUser ?? [];

  const pendingTotal = pendingAccruals.reduce((s, a) => s + Number(a.amount || 0), 0);

  // Pending rows
  const pendingRows = pendingAccruals.map(a => `
    <div style="display:flex; justify-content:space-between; align-items:flex-start;
      padding:8px 0; border-bottom:1px solid rgba(255,255,255,.07);">
      <div>
        <div style="font-size:14px;">${esc(a.client_name)}</div>
        <div style="font-size:11px; opacity:.55;">${esc(a.details || '')} • ${fdate(a.created_at)}</div>
      </div>
      <div style="font-size:14px; font-weight:700; white-space:nowrap; padding-left:12px;">
        ${fmoney(a.amount, a.currency)}
      </div>
    </div>
  `).join('');

  // Paid rows
  const paidRows = paidAccruals.map(a => {
    let paidLine = '';
    if (a.paid_usd > 0 && a.paid_uah > 0) {
      paidLine = `${fmoney(a.paid_usd, 'USD')} + ${fmoney(a.paid_uah, 'UAH')}`;
    } else if (a.paid_usd > 0) {
      paidLine = fmoney(a.paid_usd, 'USD');
    } else if (a.paid_uah > 0) {
      paidLine = fmoney(a.paid_uah, 'UAH');
    } else {
      paidLine = fmoney(a.amount, a.currency);
    }
    const rateNote = a.paid_rate ? `<span style="opacity:.45;"> • курс ${a.paid_rate}</span>` : '';
    return `
    <div style="display:flex; justify-content:space-between; align-items:flex-start;
      padding:8px 0; border-bottom:1px solid rgba(255,255,255,.05); opacity:.65;">
      <div>
        <div style="font-size:14px;">${esc(a.client_name)}</div>
        <div style="font-size:11px; opacity:.7;">Виплачено: ${fdate(a.paid_at)}${rateNote}</div>
      </div>
      <div style="font-size:13px; font-weight:700; white-space:nowrap; padding-left:12px; text-align:right;">
        ${paidLine}
      </div>
    </div>`;
  }).join('');

  const salaryUsd = pendingAccruals.filter(a => a.currency === 'USD').reduce((s, a) => s + Number(a.amount || 0), 0);

  const payBtn = pendingAccruals.length ? `
    <button type="button" class="btn save pay-btn"
      data-user-id="${userId}"
      data-user-name="${esc(userName)}"
      data-salary-usd="${salaryUsd}"
      data-total="${pendingTotal}"
      data-currency="${esc(currency)}"
      style="width:100%; margin-top:12px;">
      💸 Виплатити ${fmoney(pendingTotal, currency)}
    </button>
  ` : `
    <div style="font-size:13px; opacity:.5; text-align:center; padding:8px 0;">
      Нарахувань немає
    </div>
  `;

  const paidSection = paidAccruals.length ? `
    <details style="margin-top:8px;">
      <summary style="cursor:pointer; font-size:13px; font-weight:700; opacity:.7; padding:6px 0; list-style:none;">
        📁 Оплачені (${paidAccruals.length})
      </summary>
      <div style="margin-top:4px;">
        ${paidRows}
      </div>
    </details>
  ` : '';

  return `
    <div class="card" style="margin-bottom:16px;" data-worker-id="${userId}">
      <div style="font-weight:800; font-size:16px; margin-bottom:14px;">
        ЗП — ${esc(userName)}
      </div>

      <div style="font-size:13px; font-weight:700; margin-bottom:8px; opacity:.85;">
        📁 Очікує на виплату
        ${pendingAccruals.length ? `<span style="opacity:.6; font-weight:400;">(${pendingAccruals.length} проєкт${pendingAccruals.length > 1 ? 'и' : ''})</span>` : ''}
      </div>

      ${pendingRows || '<div style="font-size:13px; opacity:.45; padding:4px 0;">Немає записів</div>'}

      ${payBtn}

      ${paidSection}
    </div>
  `;
}

async function load() {
  const root = document.getElementById('accrualsRoot');

  const [pendingRes, paidRes] = await Promise.all([
    fetch('/api/salary/accruals'),
    fetch('/api/salary/accruals/paid'),
  ]);

  allGroups  = pendingRes.ok ? await pendingRes.json() : [];
  paidGroups = paidRes.ok  ? await paidRes.json()  : [];

  render();
}

function render() {
  const root = document.getElementById('accrualsRoot');

  // Collect all unique user ids (union of pending + paid)
  const userIds = new Set([
    ...allGroups.map(g => g.user_id),
    ...paidGroups.map(g => g.user_id),
  ]);

  if (!userIds.size) {
    root.innerHTML = `<div class="card" style="text-align:center; opacity:.6; font-size:14px;">
      Нарахувань немає 🎉
    </div>`;
    return;
  }

  // Build index for quick lookup
  const pendingByUser = Object.fromEntries(allGroups.map(g => [g.user_id, g]));
  const paidByUser    = Object.fromEntries(paidGroups.map(g => [g.user_id, g.accruals ?? []]));

  // Render: workers with pending first, then paid-only workers
  const withPending = allGroups.map(g =>
    renderWorkerBlock(g, paidByUser[g.user_id] ?? [])
  );
  const paidOnly = paidGroups
    .filter(g => !pendingByUser[g.user_id])
    .map(g => renderWorkerBlock(null, g.accruals ?? []));

  root.innerHTML = withPending.join('') + paidOnly.join('');
}

// ── Payment modal ─────────────────────────────────────────────────────────

let FX_USD = 40; // will be loaded from fx_rates
let _payModalSalaryUsd = 0;
let _bonusCurrency = 'USD';

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
  updatePayCalc();
}

function updatePayCalc() {
  const salaryUsd   = _payModalSalaryUsd;
  const bonusAmt    = Math.max(0, parseFloat(document.getElementById('bonusAmountInput')?.value || 0));
  const bonusUsd    = _bonusCurrency === 'USD' ? bonusAmt : 0;
  const bonusUah    = _bonusCurrency === 'UAH' ? bonusAmt : 0;
  const usdTotal    = salaryUsd + bonusUsd;

  const usdPaidInput = parseFloat(document.getElementById('usdPaidInput').value || 0);
  const usdToPay    = (usdPaidInput > 0 && usdPaidInput < usdTotal) ? usdPaidInput : usdTotal;
  const usdRemain   = Math.max(0, usdTotal - usdToPay);
  const uahFromUsd  = Math.round(usdRemain * FX_USD * 100) / 100;
  const uahTotal    = uahFromUsd + bonusUah;

  const lines = [];
  if (bonusUsd > 0) lines.push(`Премія: ${fmt(bonusUsd)} $`);
  if (bonusUah > 0) lines.push(`Премія: ${fmt(bonusUah)} ₴`);
  lines.push(`💵 USD виплата: ${fmt(usdToPay)} $`);
  if (usdRemain > 0) {
    lines.push(`💱 Залишок: ${fmt(usdRemain)} $ × ${fmt(FX_USD)} = ${fmt(uahFromUsd)} ₴`);
  }
  if (uahTotal > 0) lines.push(`🇺🇦 UAH виплата: ${fmt(uahTotal)} ₴`);
  lines.push(`<span style="opacity:.45; font-size:11px;">Курс (купівля): ${fmt(FX_USD)} ₴/$</span>`);
  document.getElementById('payBreakdown').innerHTML = lines.join('<br>');

  document.getElementById('usdWalletRow').style.display = usdToPay > 0 ? 'block' : 'none';
  document.getElementById('uahWalletRow').style.display = uahTotal > 0 ? 'block' : 'none';
}

async function loadPayWallets() {
  const usdSel = document.getElementById('usdWalletSelect');
  const uahSel = document.getElementById('uahWalletSelect');
  try {
    if (!walletsCache.length) {
      const r = await fetch('/api/quality-checks/wallets');
      walletsCache = r.ok ? await r.json() : [];
    }
    const usdW = walletsCache.filter(w => w.currency === 'USD');
    const uahW = walletsCache.filter(w => w.currency === 'UAH');
    usdSel.innerHTML = usdW.length
      ? usdW.map(w => `<option value="${w.id}">${esc(w.name)} (USD)</option>`).join('')
      : '<option value="">Немає USD гаманців</option>';
    uahSel.innerHTML = uahW.length
      ? uahW.map(w => `<option value="${w.id}">${esc(w.name)} (UAH)</option>`).join('')
      : '<option value="">Немає UAH гаманців</option>';
  } catch (_) {
    usdSel.innerHTML = '<option value="">Помилка</option>';
    uahSel.innerHTML = '<option value="">Помилка</option>';
  }
}

async function openPayModal(userId, userName, salaryUsd) {
  currentPayUserId   = userId;
  currentPayUserName = userName;
  _payModalSalaryUsd = salaryUsd;

  document.getElementById('payModalTitle').textContent = `Виплатити — ${userName}`;
  document.getElementById('payModalSalaryLine').textContent = `ЗП: ${fmoney(salaryUsd, 'USD')}`;
  document.getElementById('usdPaidInput').value = Math.round(salaryUsd);
  document.getElementById('bonusAmountInput').value = 0;
  setBonusCurrency('USD');

  // Load FX rate from internal exchange
  try {
    const r = await fetch('/api/fx/rates', { headers: { Accept: 'application/json' } });
    if (r.ok) {
      const d = await r.json();
      const usdRow = (d.rates || []).find(x => x.currency === 'USD');
      if (usdRow?.purchase) FX_USD = usdRow.purchase;
    }
  } catch (_) {}

  updatePayCalc();
  loadPayWallets();

  document.getElementById('payModal').style.display = 'flex';
}

function closePayModal() {
  document.getElementById('payModal').style.display = 'none';
  currentPayUserId = null;
}

async function paySalary() {
  const salaryUsd    = _payModalSalaryUsd;
  const bonusAmt     = Math.max(0, parseFloat(document.getElementById('bonusAmountInput').value || 0));
  const bonusUsd     = _bonusCurrency === 'USD' ? bonusAmt : 0;
  const bonusUah     = _bonusCurrency === 'UAH' ? bonusAmt : 0;
  const usdTotal     = salaryUsd + bonusUsd;
  const usdPaidInput = parseFloat(document.getElementById('usdPaidInput').value || 0);
  const usdToPay     = (usdPaidInput > 0 && usdPaidInput < usdTotal) ? usdPaidInput : usdTotal;
  const usdRemain    = Math.max(0, usdTotal - usdToPay);
  const uahTotal     = Math.round((usdRemain * FX_USD + bonusUah) * 100) / 100;

  const usdWalletId = usdToPay > 0 ? document.getElementById('usdWalletSelect').value : '';
  const uahWalletId = uahTotal > 0 ? document.getElementById('uahWalletSelect').value : '';

  if (usdToPay > 0 && !usdWalletId) { alert('Оберіть USD гаманець'); return; }
  if (uahTotal > 0 && !uahWalletId) { alert('Оберіть UAH гаманець'); return; }

  const btn = document.getElementById('payConfirmBtn');
  btn.disabled = true;
  btn.textContent = 'Виплата...';

  try {
    const r = await fetch(`/api/salary/pay/${currentPayUserId}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
      body: JSON.stringify({
        usd_wallet_id:  parseInt(usdWalletId) || 0,
        uah_wallet_id:  parseInt(uahWalletId) || 0,
        usd_paid:       usdToPay,
        bonus_amount:   bonusAmt,
        bonus_currency: _bonusCurrency,
      }),
    });
    const data = await r.json();
    if (r.ok && data.ok) {
      closePayModal();
      await load();
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

// ── Events ────────────────────────────────────────────────────────────────

document.getElementById('usdPaidInput').addEventListener('input', updatePayCalc);
document.getElementById('bonusAmountInput').addEventListener('input', updatePayCalc);
document.getElementById('bonusCurrencyUsd').addEventListener('click', () => setBonusCurrency('USD'));
document.getElementById('bonusCurrencyUah').addEventListener('click', () => setBonusCurrency('UAH'));

document.addEventListener('click', function (e) {
  const btn = e.target.closest('.pay-btn');
  if (btn) {
    openPayModal(
      parseInt(btn.dataset.userId),
      btn.dataset.userName,
      parseFloat(btn.dataset.salaryUsd || btn.dataset.total || 0),
    );
    return;
  }
  if (e.target.id === 'payModal') closePayModal();
});

document.getElementById('payConfirmBtn').addEventListener('click', paySalary);

document.addEventListener('DOMContentLoaded', () => load().catch(console.error));
</script>

@include('partials.nav.bottom')
@endsection
