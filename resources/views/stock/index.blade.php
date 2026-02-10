@extends('layouts.app')

@section('content')

<main class="wrap">
    <div style="margin-bottom:12px;">
    <a href="/deliveries" class="btn">
        Партії поставки
    </a>
</div>

    <div class="card" style="margin-bottom:16px;">
        
        <div style="font-size:14px; opacity:.7;">
            Склад SunFix
        </div>

        <div style="font-size:24px; font-weight:700; margin-top:6px;">
            Борг постачальнику: <span id="supplierDebt">0</span> $
        </div>
    </div>



    <div id="stockTableDesktop">
        <table border="1" cellpadding="8" cellspacing="0" style="width:100%; margin-top:20px;">
            <thead>
                <tr>
                    <th>Товар</th>
                    <th>Отримано</th>
                    <th>Продано</th>
                    <th>Залишок</th>
                    <th>Сума ($)</th>
                </tr>
            </thead>
            <tbody id="stockTable"></tbody>
        </table>
    </div>

    <div id="stockCardsMobile" style="display:none;"></div>

</main>

<script>
async function loadStock() {
    const res = await fetch('/api/stock');
    const response = await res.json();

    const table = document.getElementById('stockTable');
    const debt = document.getElementById('supplierDebt');

    table.innerHTML = '';
    debt.innerText = response.supplier_debt ?? 0;

const mobile = window.innerWidth < 768;

response.stock.forEach(row => {

    if (mobile) {
        document.getElementById('stockCardsMobile').innerHTML += `
            <div style="
                background: rgba(255,255,255,.05);
                border:1px solid rgba(255,255,255,.08);
                border-radius:16px;
                padding:14px;
                margin-bottom:12px;
            ">
                <div style="font-weight:700; margin-bottom:8px;">
                    ${row.name}
                </div>

                <div>Отримано: ${row.received}</div>
                <div>Продано: ${row.sold}</div>
                <div><b>Залишок: ${row.qty_on_stock}</b></div>
                <div>Сума: ${row.stock_value ?? 0} $</div>
            </div>
        `;
    } else {
        table.innerHTML += `
            <tr>
                <td>${row.name}</td>
                <td>${row.received}</td>
                <td>${row.sold}</td>
                <td><b>${row.qty_on_stock}</b></td>
                <td>${row.stock_value ?? 0}</td>
            </tr>
        `;
    }
});

if (mobile) {
    document.getElementById('stockTableDesktop').style.display = 'none';
    document.getElementById('stockCardsMobile').style.display = 'block';
}

}

loadStock();
</script>

@endsection
