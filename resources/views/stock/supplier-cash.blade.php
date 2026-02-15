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

  <div class="card">
    <div style="font-size:14px; opacity:.7; text-align:center">
      Борг постачальнику
    </div>

    <div style="font-size:20px; font-weight:700; margin-top:6px; text-align:center">
      <span id="supplierDebt">0</span> $
    </div>
  </div>

  <div class="card" style="margin-top:14px;">
    <div style="font-weight:700; margin-bottom:10px; text-align:center;">
      Передані кошти
    </div>

    <div id="cashTransfersList" class="delivery-list"></div>
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

</main>

@auth
  @php
    $navView = match(auth()->user()->role){
      'sunfix_manager' => 'partials.nav.bottom-sunfix-manager',
      'owner' => 'partials.nav.bottom-owner',
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

async function loadDebt(){
  const res = await fetch('/api/stock');
  if (!res.ok){
    console.warn('GET /api/stock failed:', res.status, await res.text());
    return;
  }
  const data = await res.json();
  const debtEl = document.getElementById('supplierDebt');
  if (debtEl) debtEl.innerText = data.supplier_debt ?? 0;
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
</script>

@endsection
