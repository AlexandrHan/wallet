@push('styles')
<link rel="stylesheet" href="/css/stock.css?v={{ filemtime(public_path('css/stock.css')) }}">
@endpush

@extends('layouts.app')

@section('content')

<main class="wrap stock-wrap">
    
    <div class="breadcrumb-inner">
        <div class="breadcrumb" style="margin-bottom:20px;">
            <a href="/deliveries" class="btn primary" style="width:100%">
                🚚 Список поставок
            </a>
        </div>

        @if(auth()->check() && in_array(auth()->user()->role, ['owner','accountant']))
        <div class="breadcrumb" style="margin-bottom:20px;">
            <a href="/stock/sales" class="btn primary" style="width:100%;">
                📅 Тижневий звіт
            </a>
        </div>
        @endif
    </div>

    <div class="card">
        <div style="font-size:14px; opacity:.7; text-align:center">
            Склад SunFix
        </div>

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

</main>

<script>
async function loadStock() {

    const res = await fetch('/api/stock');
    const response = await res.json();

    const list = document.getElementById('stockList');
    const debt = document.getElementById('supplierDebt');

    list.innerHTML = '';
    debt.innerText = response.supplier_debt ?? 0;

    response.stock.forEach(item => {

        list.innerHTML += `
            <div class="delivery-row">

                <div class="delivery-row-top delivery-row-start">
                    ${item.name}
                </div>

                <div class="delivery-row-bottom">
                    <div class="kv">
                        <span class="label">Отримано</span>
                        <span class="value">${item.received}</span>
                    </div>

                    <div class="kv">
                        <span class="label">Продано</span>
                        <span class="value">${item.sold}</span>
                    </div>

                    <div class="kv">
                        <span class="label">Залишок</span>
                        <span class="value">${item.qty_on_stock ?? item.qty_on_stock}</span>
                    </div>

                    <div class="kv">
                        <span class="label">Ціна</span>
                        <span class="value">${item.supplier_price ?? '-'}</span>
                    </div>

                    <div class="kv">
                        <span class="label">Сума</span>
                        <span class="value">${item.stock_value ?? 0}</span>
                    </div>
                </div>

            </div>
        `;
    });

}

loadStock();
</script>

@endsection

