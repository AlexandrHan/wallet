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





  <div class="card" style="margin-top:14px;">
    <div class="list-item" style="font-weight:700; margin-bottom:10px; text-align:center;">
      Товари на складі
    </div>
    <button id="expandAllStockBtn" class="btn" style="width:100%; margin-bottom:10px;">
      Дивитись всі товари
    </button>
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

@include('partials.nav.bottom')


<script>
async function loadStock() {
  const list = document.getElementById('stockList');
  if (!list) return;

  // 1) тягнемо склад
  const rStock = await fetch('/api/stock', { headers: { 'Accept': 'application/json' } });
  if (!rStock.ok) {
    console.warn('GET /api/stock failed:', rStock.status, await rStock.text());
    return;
  }
  const stockJson = await rStock.json();
  if (stockJson.balance_ok === false) {
  console.warn('⚠️ Баланс складу не сходиться');
}

  const stock = stockJson.stock || [];

  // 2) тягнемо довідник товарів з категоріями
  const rProducts = await fetch('/api/products', { headers: { 'Accept': 'application/json' } });
  if (!rProducts.ok) {
    console.warn('GET /api/products failed:', rProducts.status, await rProducts.text());
    return;
  }
  const products = await rProducts.json();

  // 3) робимо map: product_id -> category_name
  const catByProductId = {};
  (products || []).forEach(p => {
    catByProductId[Number(p.id)] = p.category_name || 'Інше';
  });

  // 4) групуємо stock по категоріях
  const groups = {};
  stock.forEach(item => {
    const pid = Number(item.product_id);
    const cat = catByProductId[pid] || 'Інше';
    (groups[cat] ||= []).push(item);
  });

  // 5) сортуємо категорії, "Інше" в кінець
  const cats = Object.keys(groups).sort((a,b) => {
    if (a === 'Інше') return 1;
    if (b === 'Інше') return -1;
    return a.localeCompare(b, 'uk');
  });

  // 6) рендер акордеона (collapsed by default)
  list.innerHTML = cats.map(cat => {
    const items = groups[cat];

    // сортуємо товари в категорії по назві
    items.sort((a,b) => String(a.name).localeCompare(String(b.name), 'uk'));

    const totalValue = items.reduce((s,x)=> s + Number(x.stock_value || 0), 0);
    const totalQty   = items.reduce((s,x)=> s + Number(x.qty_on_stock || 0), 0);

    const rowsHtml = items.map(item => `
      <div class="delivery-row">
        <div class="delivery-row-top">${item.name}</div>
        <div class="delivery-row-bottom">
          <div class="kv"><span class="label">Отримано</span><span class="value">${item.received}</span></div>
          <div class="kv"><span class="label">Продано</span><span class="value">${item.sold}</span></div>
          <div class="kv"><span class="label">Залишок</span><span class="value">${item.qty_on_stock}</span></div>
          <div class="kv"><span class="label">Ціна</span><span class="value">${item.supplier_price ?? '-'}</span></div>
          <div class="kv"><span class="label">Сума</span><span class="value">${item.stock_value ?? 0}</span></div>
        </div>
      </div>
    `).join('');

    return `
      <div class="card stock-cat-card" style="margin-top:14px;">
        <details class="stock-cat">
          <summary class="stock-cat-summary">
            <div class="stock-cat-left">
              <span class="chev">▸</span>
              <span class="stock-cat-title">${cat}</span>
            </div>

            <div class="stock-cat-right">

               <span class="stock-cat-meta ps">${totalValue}</span>
            </div>
          </summary>

          <div class="stock-cat-body">
            ${rowsHtml}
          </div>
        </details>
      </div>
    `;

  }).join('');
}

function expandAllStockCategories() {
  document.querySelectorAll('#stockList details.stock-cat').forEach(el => {
    el.open = true;
  });
}

document.addEventListener('DOMContentLoaded', async () => {
  await loadStock();

  const expandBtn = document.getElementById('expandAllStockBtn');
  if (!expandBtn) return;

  expandBtn.addEventListener('click', expandAllStockCategories);
});
</script>





@endsection
