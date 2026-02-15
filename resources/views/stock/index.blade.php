@push('styles')
<link rel="stylesheet" href="/css/stock.css?v={{ filemtime(public_path('css/stock.css')) }}">
{{-- якщо tg-nav стилі не підключені глобально в layout — підключи тут --}}
<link rel="stylesheet" href="/css/nav-telegram.css?v={{ filemtime(public_path('css/nav-telegram.css')) }}">
@endpush

@extends('layouts.app')

@section('content')

<main class="wrap stock-wrap has-tg-nav">

  <div class="breadcrumb-inner">
    @if(auth()->check() && in_array(auth()->user()->role, ['owner','accountant']))
      <div class="breadcrumb" style="margin-bottom:20px; max-width:58%">
        <a href="/stock/sales" class="btn primary">📅 Тижневий звіт</a>
      </div>
    @endif
  </div>

  @if(auth()->user()->role === 'owner')
    <div class="card" style="margin-bottom:20px;">
      <button class="btn primary" onclick="openSendCashModal()" style="width:100%">
        Передати кошти менеджеру
      </button>
    </div>
  @endif

  <div class="card">
    <div style="font-size:14px; opacity:.7; text-align:center">Склад SunFix</div>

    <div style="font-size:20px; font-weight:700; margin-top:6px; text-align:center">
      Борг постачальнику:
      <span id="supplierDebt">0</span> $
    </div>
  </div>

  <div class="card" style="margin-top:14px;">
    <div class="list-item" style="font-weight:700; margin-bottom:10px; text-align:center;">
      Товари на складі
    </div>
    <div id="stockList" class="delivery-list"></div>
  </div>

  <div id="sendCashModal" class="modal hidden">
    <div class="modal-card">
      <div class="modal-title">Передати кошти менеджеру</div>

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
async function loadStock() {
  const res = await fetch('/api/stock');
  if (!res.ok) { console.warn('GET /api/stock failed:', res.status, await res.text()); return; }
  const response = await res.json();

  const list = document.getElementById('stockList');
  const debt = document.getElementById('supplierDebt');

  if(list) list.innerHTML = '';
  if(debt) debt.innerText = response.supplier_debt ?? 0;

  (response.stock || []).forEach(item => {
    list.innerHTML += `
      <div class="delivery-row">
        <div class="delivery-row-top delivery-row-start">${item.name}</div>
        <div class="delivery-row-bottom">
          <div class="kv"><span class="label">Отримано</span><span class="value">${item.received}</span></div>
          <div class="kv"><span class="label">Продано</span><span class="value">${item.sold}</span></div>
          <div class="kv"><span class="label">Залишок</span><span class="value">${item.qty_on_stock ?? item.qty_on_stock}</span></div>
          <div class="kv"><span class="label">Ціна</span><span class="value">${item.supplier_price ?? '-'}</span></div>
          <div class="kv"><span class="label">Сума</span><span class="value">${item.stock_value ?? 0}</span></div>
        </div>
      </div>
    `;
  });
}

function openSendCashModal(){
  document.getElementById('sendCashAmount').value = '';
  document.getElementById('sendCashModal').classList.remove('hidden');
}
function closeSendCashModal(){
  document.getElementById('sendCashModal').classList.add('hidden');
}

async function confirmSendCash(){
  const amount = Number(document.getElementById('sendCashAmount').value);
  if (!amount || amount <= 0){ alert('Введи суму'); return; }

  const res = await fetch('/api/supplier-cash', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    },
    body: JSON.stringify({ amount })
  });

  const out = await res.json();
  if (!res.ok){ alert(out.error ?? 'Помилка'); return; }

  closeSendCashModal();
  await loadStock();
}

loadStock();
</script>

@endsection
