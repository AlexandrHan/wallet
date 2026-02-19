@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="/css/stock.css?v={{ filemtime(public_path('css/stock.css')) }}">
@endpush

@section('content')

@auth
  <script>
    // щоб не лізти в layout, але мати body.has-tg-nav для нижнього меню
    document.addEventListener('DOMContentLoaded', () => document.body.classList.add('has-tg-nav'));
  </script>
@endauth

<main class="wrap stock-wrap">


  @if(auth()->check() && auth()->user()->role === 'owner')
    <div class="card" style="margin-bottom:14px;">
      <button class="btn primary" onclick="openSendCashModal()" style="width:100%">
        Передати кошти менеджеру
      </button>
    </div>
  @endif

  {{-- ================= FEM DEBT ================= --}}
  <div class="card stock-card" id="femDebtCard" style="margin-top:14px; margin-bottom:14px;">
    <details>
      <summary style="cursor:pointer;">
        <div style="font-size:14px; opacity:.7; text-align:center">
          Борг за ФЕМ
        </div>

        <div style="font-size:20px; font-weight:700; margin-top:6px; text-align:center">
          <span id="femTotalDebt">0</span> $
        </div>
      </summary>


      <div class="stock-cat-body">

        @if(auth()->check() && auth()->user()->role === 'sunfix_manager')
          <button class="btn fem-add-btn" onclick="openFemCreateModal()">
            + Додати поставку ФЕМ
          </button>
        @endif



        <div id="femRows"></div>

      </div>
    </details>
  </div>


  <div class="card" style="margin-top:14px;">
    <details id="supplierDebtCard">
      <summary style="cursor:pointer; text-align:center;">
        <div style="font-size:14px; opacity:.7;">
          Борг за інверторне обладнання
        </div>

        <div style="font-size:20px; font-weight:700; margin-top:6px;">
          <span id="supplierDebt">0</span> $
        </div>
      </summary>

      <div style="margin-top:14px;">
        <div style="font-weight:700; margin-bottom:10px; text-align:center;">
          Передані кошти
        </div>

        <div id="cashTransfersList" class="delivery-list"></div>
      </div>
    </details>
  </div>


  <div id="sendCashModal" class="modal hidden">
    <div class="modal-card">

      <div class="modal-title">
        Передати кошти менеджеру
      </div>

      <input
        type="number"
        id="sendCashAmount"
        class="btn btn-input"
        placeholder="Сума $"
        min="1"
        style="width:100%; margin-top:12px;"
      >

      <div class="modal-actions">
        <button class="btn" onclick="closeSendCashModal()">Скасувати</button>
        <button class="btn primary" onclick="confirmSendCash()">Підтвердити</button>
      </div>

    </div>
  </div>


  <div id="femCreateModal" class="modal hidden">
    <div class="modal-card">
      <div class="modal-title">Нова поставка ФЕМ</div>

      <input type="text"
            id="femPanelName"
            class="btn btn-input"
            placeholder="Назва панелей"
            style="width:100%; margin-top:12px;">

      <input type="number"
            id="femAmount"
            class="btn btn-input"
            placeholder="Сума $"
            min="1"
            style="width:100%; margin-top:10px;">

      <div class="modal-actions">
        <button class="btn" onclick="closeFemCreateModal()">Скасувати</button>
        <button class="btn primary" onclick="confirmFemCreate()">Зберегти</button>
      </div>
    </div>
  </div>

  <div id="femPayModal" class="modal hidden">
    <div class="modal-card">
      <div class="modal-title">Передати оплату</div>

      <input type="number"
            id="femPayAmount"
            class="btn btn-input"
            placeholder="Сума $"
            min="1"
            style="width:100%; margin-top:12px;">

      <div class="modal-actions">
        <button class="btn" onclick="closeFemPayModal()">Скасувати</button>
        <button class="btn primary" onclick="confirmFemPay()">Передати</button>
      </div>
    </div>
  </div>


</main>

@auth
  @php
    $navView = match(auth()->user()->role){
      'sunfix_manager' => 'partials.nav.bottom-sunfix-manager',
      'owner' => 'partials.nav.bottom-owner',
      'accountant' => 'partials.nav.bottom-accountant',
      default => null,
    };
  @endphp

  @if($navView)
    @include($navView)
  @endif
@endauth

<script>
const AUTH_ROLE = @json(auth()->check() ? auth()->user()->role : null);
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

function fmt0(n){
  const num = Number(String(n ?? 0).replace(',', '.')) || 0;

  // Intl часто ставить нерозривний пробіл, замінимо на звичайний
  return new Intl.NumberFormat('uk-UA', { maximumFractionDigits: 0 })
    .format(Math.round(num))
    .replace(/\u00A0/g, ' ');
}


async function loadDebt(){
  const res = await fetch('/api/stock');
  if (!res.ok){
    console.warn('GET /api/stock failed:', res.status, await res.text());
    return;
  }
  const data = await res.json();
  const debtEl = document.getElementById('supplierDebt');
  if (debtEl) debtEl.innerText = fmt0(data.supplier_debt ?? 0);

}

async function loadCashTransfers(){
  const res = await fetch('/api/supplier-cash');
  if (!res.ok){
    console.warn('GET /api/supplier-cash failed:', res.status, await res.text());
    return;
  }
  const rows = await res.json();

  const box = document.getElementById('cashTransfersList');
  if (!box) return;

  box.innerHTML = '';

  (Array.isArray(rows) ? rows : []).forEach(r => {
    const isReceived = Number(r.is_received ?? 0) === 1;
    const status = isReceived ? 'RECEIVED' : 'SENT';

    box.innerHTML += `
      <div class="delivery-row">
        <div class="delivery-row-top">
          ${isReceived ? '✅' : '🕓'} Передача #${r.id}
        </div>

        <div class="delivery-row-bottom">
          <div>
            <span class="label">Сума</span>
            <span class="value">${r.amount} $</span>
          </div>

          <div>
            <span class="label">Статус</span>
            <span class="value">${status}</span>
          </div>

          <div>
            <span class="label">Дата</span>
            <span class="value">${String(r.created_at).substring(0,10)}</span>
          </div>

          ${
            (!isReceived && AUTH_ROLE === 'sunfix_manager')
              ? `<button class="btn primary" onclick="receiveCash(${r.id})">Отримати</button>`
              : ''
          }
        </div>
      </div>
    `;
  });
}

async function receiveCash(id){
  const res = await fetch(`/api/supplier-cash/${id}/received`, {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': CSRF }
  });

  const text = await res.text();
  let out = {};
  try { out = text ? JSON.parse(text) : {}; } catch (e) {}

  if (!res.ok){
    alert(out.error ?? 'Помилка');
    return;
  }

  await loadCashTransfers();
  await loadDebt();
}

function openSendCashModal(){
  const el = document.getElementById('sendCashAmount');
  if (el) el.value = '';
  document.getElementById('sendCashModal')?.classList.remove('hidden');
}
function closeSendCashModal(){
  document.getElementById('sendCashModal')?.classList.add('hidden');
}

async function confirmSendCash(){
  const amount = Number(document.getElementById('sendCashAmount')?.value || 0);
  if (!amount || amount <= 0){
    alert('Введи суму');
    return;
  }

  const res = await fetch('/api/supplier-cash', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': CSRF
    },
    body: JSON.stringify({ amount })
  });

  const text = await res.text();
  let out = {};
  try { out = text ? JSON.parse(text) : {}; } catch (e) {}

  if (!res.ok){
    alert(out.error ?? 'Помилка');
    return;
  }

  closeSendCashModal();

  await loadCashTransfers();
  await loadDebt();
}

let _refreshTimer = null;
function startAutoRefresh(){
  if (_refreshTimer) return;

  const tick = async () => {
    await loadCashTransfers();
    await loadDebt();
  };

  tick();
  _refreshTimer = setInterval(tick, 5000);
}

document.addEventListener('visibilitychange', () => {
  if (document.hidden) {
    clearInterval(_refreshTimer);
    _refreshTimer = null;
  } else {
    startAutoRefresh();
  }
});

startAutoRefresh();















// =======================
// FEM (uses /api/fem/*)
// =======================

const FEM_CAN_EDIT = (AUTH_ROLE === 'sunfix_manager');
const FEM_CAN_PAY  = (AUTH_ROLE === 'owner' || AUTH_ROLE === 'accountant');

let femContainers = [];
let femEditId = null;
let femPayId  = null;

function femEl(...ids){
  for (const id of ids){
    const el = document.getElementById(id);
    if (el) return el;
  }
  return null;
}

function escapeHtml(s){
  return String(s ?? '').replace(/[&<>"']/g, (m) => ({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
  }[m]));
}

function money(n){
  const num = Number(n || 0);
  return new Intl.NumberFormat('uk-UA', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(num);
}


function todayStr(){
  return new Date().toISOString().substring(0,10);
}

async function femFetchJson(url, options = {}){
  const headers = Object.assign({
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  }, options.headers || {});

  const res = await fetch(url, Object.assign({
    credentials: 'same-origin',
    headers
  }, options));

  const text = await res.text();
  let data = null;
  try { data = text ? JSON.parse(text) : null; } catch(e) {}

  if (!res.ok){
    const msg = data?.error || data?.message || `Помилка (status ${res.status})`;
    throw new Error(msg);
  }
  return data;
}

async function loadFem(){
  const data = await femFetchJson('/api/fem/containers');
  const arr = Array.isArray(data) ? data : [];

  const today = todayStr();

  femContainers = arr.map(c => {
    const payments = Array.isArray(c.payments) ? c.payments : [];
    const paid = payments.reduce((s,p) => s + Number(p.amount || 0), 0);
    const amount = Number(c.amount || 0);
    const date = String(c.date || '').substring(0,10);

    return {
      ...c,
      payments,
      paid,
      balance: amount - paid,
      is_today: date === today,
    };
  });

  renderFem();
}

function renderFem(){

  const box = document.getElementById('femDebtTable') || document.getElementById('femRows');
  if (!box) return;

  box.innerHTML = '';

  // якщо десь зверху у тебе табличний хедер (fem-table-header) і він заважає,
  // ми його не чіпаємо. Після тесту приберемо красиво в HTML.

  let totalDebt = 0;

  // гарантуємо "звернутий" порядок: найновіші зверху
  const list = [...(femContainers || [])].sort((a,b) => Number(b.id) - Number(a.id));

  list.forEach((c) => {
    const payments = Array.isArray(c.payments) ? c.payments : [];

    const paid = payments.reduce((s,p)=> s + Number(p.amount || 0), 0);
    const amount = Number(c.amount || 0);
    const balance = amount - paid;

    totalDebt += Math.max(0, balance);

    const dateStr = String(c.date || c.created_at || '').substring(0,10) || '-';
    const balanceStr = fmt0(Math.max(0, balance));


    const canPay = (FEM_CAN_PAY || typeof CAN_PAY_FEM !== 'undefined' && CAN_PAY_FEM) && balance > 0;
    const canEdit = (FEM_CAN_EDIT || typeof CAN_EDIT_FEM !== 'undefined' && CAN_EDIT_FEM) && (c.is_today === true || true); 
    // ^ якщо в тебе немає is_today у відповіді, на наступному кроці зробимо строго "тільки сьогодні"

    const paymentsHtml = payments.length
      ? payments.map(p => {
          const pDate = String(p.paid_at || p.created_at || '').substring(0,10) || '-';
          const pAmt = fmt0(p.amount || 0);

          return `
            <div class="delivery-row-bottom" style="margin-top:6px;">
              <div class="kv">
                <span class="label">Оплата</span>
                <span class="value">${pAmt} $</span>
              </div>
              <div class="kv">
                <span class="label">Дата</span>
                <span class="value">${pDate}</span>
              </div>
            </div>
          `;
        }).join('')
      : `<div class="delivery-row-start" style="padding:10px 0; opacity:.7;">Оплат ще нема</div>`;

    const summaryColor = balance > 0 ? '#fff' : '#66f2a8';

    box.innerHTML += `
      <details class="stock-cat" style="padding: 6px 2px; border-bottom:1px solid rgba(255,255,255,.06);">
        <summary class="stock-cat-summary" style="color:${summaryColor}">
          <div class="stock-cat-left">
            <span class="stock-cat-title" style="color:${summaryColor}">
              ${escapeHtml(c.name || 'Без назви')}
            </span>
          </div>
          <div class="stock-cat-right" style="color:${summaryColor}">
            <span>${balanceStr} $</span>
          </div>
        </summary>

        <div class="stock-cat-body">

          <div class="delivery-row-bottom">
            <div class="kv">
              <span class="label">Дата</span>
              <span class="value">${dateStr}</span>
            </div>
            <div class="kv">
              <span class="label">Сума</span>
              <span class="value">${fmt0(amount)} $</span>
            </div>
            <div class="kv">
              <span class="label">Оплачено</span>
              <span class="value">${fmt0(paid)} $</span>
            </div>
            <div class="kv">
              <span class="label">Залишок</span>
              <span class="value">${fmt0(balance)} $</span>
            </div>
          </div>

          ${canPay ? `
            <button class="btn primary" style="width:100%; margin-top:10px;"
              onclick="event.preventDefault(); event.stopPropagation(); openFemPayModal(${c.id})">
              ➕ Додати оплату
            </button>
          ` : ''}

          <div style="margin-top:10px;">
            ${paymentsHtml}
          </div>

          ${canEdit ? `
            <button class="btn" style="width:100%; margin-top:8px;"
              onclick="event.preventDefault(); event.stopPropagation(); openFemCreateModal(${c.id})">
              ✏️ Редагувати
            </button>
          ` : ''}

        </div>
      </details>
    `;
  });

  const totalEl = document.getElementById('femDebtTotal') || document.getElementById('femTotalDebt');
  if (totalEl) totalEl.innerText = fmt0(totalDebt);

}


/* ===== CREATE / EDIT (same modal) ===== */

function openFemCreateModal(id = null){
  if (!FEM_CAN_EDIT) return;

  femEditId = id;

  const nameEl = femEl('femPanelName', 'femCreateName');
  const amountEl = femEl('femAmount', 'femCreateAmount');

  if (!nameEl || !amountEl){
    alert('Не знайдені поля модалки ФЕМ (name/amount)');
    return;
  }

  if (id){
    const c = femContainers.find(x => Number(x.id) === Number(id));
    if (!c){ alert('Контейнер не знайдено'); return; }

    nameEl.value = c.name || '';
    amountEl.value = Number(c.amount || 0);
  } else {
    nameEl.value = '';
    amountEl.value = '';
  }

  femEl('femCreateModal')?.classList.remove('hidden');
}

function closeFemCreateModal(){
  femEl('femCreateModal')?.classList.add('hidden');
}

async function confirmFemCreate(){
  if (!FEM_CAN_EDIT) return;

  const nameEl = femEl('femPanelName', 'femCreateName');
  const amountEl = femEl('femAmount', 'femCreateAmount');

  const name = String(nameEl?.value || '').trim();
  const amount = Number(amountEl?.value || 0);

  if (name.length < 3){ alert('Введи назву панелей (мін 3 символи)'); return; }
  if (!amount || amount <= 0){ alert('Введи суму більше 0'); return; }

  if (femEditId){
    await femFetchJson(`/api/fem/containers/${femEditId}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
      body: JSON.stringify({ name, amount })
    });
  } else {
    await femFetchJson('/api/fem/containers', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
      body: JSON.stringify({ name, amount })
    });
  }

  closeFemCreateModal();
  femEditId = null;
  await loadFem();
}

/* ===== PAY ===== */

function openFemPayModal(id){
  if (!FEM_CAN_PAY) return;
  femPayId = id;
  femEl('femPayAmount')?.value && (femEl('femPayAmount').value = '');
  femEl('femPayModal')?.classList.remove('hidden');
}

function closeFemPayModal(){
  femEl('femPayModal')?.classList.add('hidden');
}

async function confirmFemPay(){
  if (!FEM_CAN_PAY) return;

  const amount = Number(femEl('femPayAmount')?.value || 0);
  if (!amount || amount <= 0){ alert('Введи суму'); return; }

  const paid_at = femEl('femPayDate')?.value || null; // якщо є поле дати в старій версії

  const payload = paid_at ? { amount, paid_at } : { amount };

  await femFetchJson(`/api/fem/containers/${femPayId}/payments`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
    body: JSON.stringify(payload)
  });

  closeFemPayModal();
  femPayId = null;
  await loadFem();
}

// сумісність зі старим onclick confirmFemPayment()
async function confirmFemPayment(){
  return confirmFemPay();
}

// старт
document.addEventListener('DOMContentLoaded', async () => {
  try { await loadFem(); } catch(e){ console.warn('FEM:', e.message); }
});





</script>
<script src="/js/stock-debt-chart.js?v={{ filemtime(public_path('js/stock-debt-chart.js')) }}"></script>


@endsection
