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
  align-items:center; justify-content:center; padding:16px;">
  <div class="card" style="width:100%; max-width:400px; padding:20px;">
    <div style="font-weight:800; font-size:16px; margin-bottom:6px;" id="payModalTitle">Виплатити зарплату</div>
    <div style="font-size:14px; opacity:.8; margin-bottom:16px;" id="payModalDesc"></div>

    <div class="project-field-label">Гаманець для списання</div>
    <select id="payWalletSelect" class="btn" style="width:100%; margin-bottom:16px;">
      <option value="">Завантаження...</option>
    </select>

    <div style="display:flex; gap:8px;">
      <button id="payConfirmBtn" type="button" class="btn save" style="flex:1;">
        Виплатити
      </button>
      <button type="button" class="btn" style="flex:1;" onclick="closePayModal()">
        Скасувати
      </button>
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
  const paidRows = paidAccruals.map(a => `
    <div style="display:flex; justify-content:space-between; align-items:flex-start;
      padding:8px 0; border-bottom:1px solid rgba(255,255,255,.05); opacity:.65;">
      <div>
        <div style="font-size:14px;">${esc(a.client_name)}</div>
        <div style="font-size:11px; opacity:.7;">Виплачено: ${fdate(a.paid_at)}</div>
      </div>
      <div style="font-size:14px; font-weight:700; white-space:nowrap; padding-left:12px;">
        ${fmoney(a.amount, a.currency)}
      </div>
    </div>
  `).join('');

  const payBtn = pendingAccruals.length ? `
    <button type="button" class="btn save pay-btn"
      data-user-id="${userId}"
      data-user-name="${esc(userName)}"
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

async function openPayModal(userId, userName, total, currency) {
  currentPayUserId   = userId;
  currentPayUserName = userName;

  document.getElementById('payModalTitle').textContent = `Виплатити зарплату — ${userName}`;
  document.getElementById('payModalDesc').textContent  = `Сума: ${fmoney(total, currency)}`;

  // Load wallets filtered by currency
  const sel = document.getElementById('payWalletSelect');
  sel.innerHTML = '<option value="">Завантаження...</option>';

  try {
    if (!walletsCache.length) {
      const r = await fetch('/api/quality-checks/wallets');
      walletsCache = r.ok ? await r.json() : [];
    }
    const matching = walletsCache.filter(w => w.currency === currency);
    if (matching.length) {
      sel.innerHTML = matching.map(w =>
        `<option value="${w.id}">${esc(w.name)} (${w.currency})</option>`
      ).join('');
    } else {
      sel.innerHTML = `<option value="">Немає гаманців у ${esc(currency)}</option>`;
    }
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
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': CSRF,
      },
      body: JSON.stringify({ wallet_id: walletId }),
    });
    const data = await r.json();

    if (r.ok && data.ok) {
      closePayModal();
      // Reload data and re-render
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

document.addEventListener('click', function (e) {
  const btn = e.target.closest('.pay-btn');
  if (btn) {
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

document.getElementById('payConfirmBtn').addEventListener('click', paySalary);

document.addEventListener('DOMContentLoaded', () => load().catch(console.error));
</script>

@include('partials.nav.bottom')
@endsection
