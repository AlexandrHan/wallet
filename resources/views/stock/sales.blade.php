@push('styles')
<link rel="stylesheet" href="/css/stock.css?v={{ filemtime(public_path('css/stock.css')) }}">
@endpush

@extends('layouts.app')

@section('content')

<main class="wrap stock-wrap">


    <div class="card">
        <div style="font-weight:700; text-align:center; margin-bottom:10px;">
            Продажі (ввести кількість і порахувати борг)
        </div>

        <div class="stock-row-bottom">
            <input class="btn btn-input" id="from" type="date">
            <input class="btn btn-input" id="to" type="date">
        </div>


        <div style="margin-top:12px; text-align:center; font-weight:700;">
            До сплати за період: <span id="summaryTotal">0</span> $
        </div>

    </div>

    <div class="card" style="margin-top:14px;">
        <div style="font-weight:700; margin-bottom:10px;">
            Введи “продано” по товарах
        </div>

        <div id="salesList" class="delivery-list"></div>
    </div>

    <div class="stock-row-bottom" style="margin-top:10px;">
      <button class="btn primary" onclick="saveSales()" style="width:100%">
        Зберегти продажі
      </button>
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
function iso(d){
  const z = n => String(n).padStart(2,'0');
  return d.getFullYear() + '-' + z(d.getMonth()+1) + '-' + z(d.getDate());
}

function setDefaultDates(){
  const now = new Date();

  // поточний тиждень (ПН..НД)
  const day = now.getDay(); // 0..6 (0=нд)
  const diffToMon = (day === 0 ? -6 : 1 - day);

  const mon = new Date(now);
  mon.setDate(now.getDate() + diffToMon);

  const sun = new Date(mon);
  sun.setDate(mon.getDate() + 6);

  const fromEl = document.getElementById('from');
  const toEl   = document.getElementById('to');

  if (fromEl) fromEl.value = iso(mon);
  if (toEl)   toEl.value   = iso(sun);
}

function csrf(){
  return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

async function fetchJson(url, options = {}){
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
  try { data = text ? JSON.parse(text) : null; }
  catch (e) {
    console.error('NON-JSON RESPONSE:', res.status, text.slice(0, 300));
    throw new Error(`Сервер повернув не JSON (status ${res.status}). Дивись Console.`);
  }

  if (!res.ok) {
    const msg = (data && (data.error || data.message)) ? (data.error || data.message) : `Помилка (status ${res.status})`;
    throw new Error(msg);
  }

  return data;
}

async function loadSalesForm(){
  const data = await fetchJson('/api/stock');

  const list = document.getElementById('salesList');
  list.innerHTML = '';

  (data.stock || []).forEach(item => {
    const pid = item.product_id ?? item.id; // страховка, якщо api віддає id
    list.innerHTML += `
      <div class="delivery-row">
        <div class="delivery-row-top ">${item.name}</div>

        <div class="delivery-row-bottom">
          <div>
            <span class="label">Залишок</span>
            <span class="value">${item.qty_on_stock ?? 0}</span>
          </div>

          <div>
            <span class="label">Ціна</span>
            <span class="value">${item.supplier_price ?? '-'}</span>
          </div>

          <div>
            <span class="label">Продано</span>
            <input class="btn"
                   type="number"
                   min="0"
                   data-product-id="${pid}"
                   value="0"
                   style="width:78px; text-align:center; padding:0 10px;">
          </div>
        </div>
      </div>
    `;
  });
}

async function saveSales(){
  const to = document.getElementById('to')?.value; // дата запису продажів
  if (!to) { alert('Вибери дату "to"'); return; }

  const inputs = document.querySelectorAll('[data-product-id]');
  const items = [];

  inputs.forEach(inp => {
    const qty = Number(inp.value || 0);
    if (qty > 0) {
    items.push({
    product_id: Number(inp.dataset.productId),
    qty: qty,          // <- ПРАВИЛЬНА назва під твою БД
    });

    }
  });

  if (items.length === 0) {
    alert('Нема що зберігати (всюди 0).');
    return;
  }

  try {
    await fetchJson('/api/sales/batch', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf(),
      },
      body: JSON.stringify({ sold_at: to, items })
    });

    alert('Збережено ✅');
    await loadSalesForm();
    await calcSummary();

  } catch (e) {
    alert(e.message);
  }
}

async function calcSummary(){
  const from = document.getElementById('from')?.value;
  const to   = document.getElementById('to')?.value;

  const out = await fetchJson(`/api/sales/summary?from=${from}&to=${to}`);

  console.log('SUMMARY RESPONSE:', out);

  document.getElementById('summaryTotal').innerText = out.total ?? 0;
}


document.addEventListener('DOMContentLoaded', async () => {
  setDefaultDates();
  try {
    await loadSalesForm();
    await calcSummary();
  } catch (e) {
    alert(e.message);
  }
});
</script>

@endsection
