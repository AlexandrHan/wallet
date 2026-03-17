@push('styles')
<link rel="stylesheet" href="/css/stock.css?v={{ filemtime(public_path('css/stock.css')) }}">
<link rel="stylesheet" href="/css/nav-telegram.css?v={{ filemtime(public_path('css/nav-telegram.css')) }}">
@endpush

@extends('layouts.app')

@section('content')
<main class="wrap stock-wrap has-tg-nav">

  <div class="projects-title-card">
    <div class="projects-title">☀️ Склад Solar Glass</div>
    <div style="font-size:12px; opacity:.6; margin-top:2px;">Фотомодулі, АКБ, Інвертори</div>
  </div>

  {{-- Фільтри --}}
  <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px;">
    <button class="sg-filter is-active" data-cat="">Всі</button>
    <button class="sg-filter" data-cat="panels">☀️ Панелі</button>
    <button class="sg-filter" data-cat="inverters">🔌 Інвертори</button>
    <button class="sg-filter" data-cat="batteries">⚡ АКБ</button>
  </div>

  {{-- Пошук --}}
  <div style="margin-bottom:14px;">
    <input id="sgSearch" type="text" placeholder="Пошук по назві..."
      style="width:100%; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.15); border-radius:12px; padding:10px 14px; color:inherit; font-size:14px; box-sizing:border-box;">
  </div>

  {{-- Список --}}
  <div id="sgList">
    <div style="text-align:center; padding:40px; opacity:.5;">Завантаження...</div>
  </div>

</main>

<style>
.sg-filter {
  background: rgba(255,255,255,0.07);
  border: 1px solid rgba(255,255,255,0.12);
  border-radius: 20px;
  padding: 6px 14px;
  font-size: 13px;
  color: inherit;
  cursor: pointer;
  opacity: .6;
  transition: opacity .15s, background .15s;
}
.sg-filter.is-active, .sg-filter:hover { opacity: 1; background: rgba(255,255,255,0.14); }
.sg-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 0;
  border-bottom: 1px solid rgba(255,255,255,0.06);
  gap: 12px;
}
.sg-row:last-child { border-bottom: none; }
.sg-name { font-size: 14px; flex: 1; }
.sg-code { font-size: 11px; opacity: .35; margin-top: 2px; }
.sg-qty { font-weight: 700; font-size: 15px; white-space: nowrap; flex-shrink: 0; }
.sg-qty-high { color: #4d9; }
.sg-qty-low  { color: #fa0; }
</style>

<script>
let sgCat = '', sgQ = '', sgTimer;

function esc(v) {
  return String(v ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

async function loadStock() {
  const list = document.getElementById('sgList');
  const p = new URLSearchParams();
  if (sgCat) p.set('category', sgCat);
  if (sgQ)   p.set('q', sgQ);
  list.innerHTML = '<div style="text-align:center;padding:30px;opacity:.5;">Завантаження...</div>';
  try {
    const items = await fetch('/api/solar-glass/stock?' + p).then(r => r.json());
    if (!items.length) {
      list.innerHTML = '<div style="text-align:center;padding:30px;opacity:.4;">Нічого не знайдено</div>';
      return;
    }
    const card = document.createElement('div');
    card.className = 'card';
    items.forEach(item => {
      const qc = item.qty >= 100 ? 'sg-qty-high' : item.qty <= 10 ? 'sg-qty-low' : '';
      card.insertAdjacentHTML('beforeend', `
        <div class="sg-row">
          <div>
            <div class="sg-name">${esc(item.item_name)}</div>
            <div class="sg-code">${esc(item.item_code)}</div>
          </div>
          <div class="sg-qty ${qc}">${item.qty} шт.</div>
        </div>`);
    });
    list.innerHTML = '';
    list.appendChild(card);
  } catch(e) {
    list.innerHTML = '<div style="padding:20px;color:#f76;">Помилка завантаження</div>';
  }
}

document.querySelectorAll('.sg-filter').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.sg-filter').forEach(b => b.classList.remove('is-active'));
    btn.classList.add('is-active');
    sgCat = btn.dataset.cat;
    loadStock();
  });
});

document.getElementById('sgSearch').addEventListener('input', function() {
  clearTimeout(sgTimer);
  sgTimer = setTimeout(() => { sgQ = this.value.trim(); loadStock(); }, 300);
});

loadStock();
</script>

@include('partials.nav.bottom')
@endsection
