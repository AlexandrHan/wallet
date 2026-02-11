@push('styles')
<link rel="stylesheet" href="/css/stock.css?v={{ filemtime(public_path('css/stock.css')) }}">
@endpush

@extends('layouts.app')

@section('content')

<main class="wrap stock-wrap">
    <div class="breadcrumb" style="margin-bottom:25px;">
        <a href="/stock" class="btn primary" style="width:30%">Склад</a>
        <a href="/deliveries" class="btn primary" style="width:60%">Партії поставок</a>
        
    </div>
        <div class="card" style="margin-top:12px;">
        <div style="text-align:center">
            Статус: <b id="deliveryStatus">Відправлено</b>
        </div>


    </div>

    <div class="card" style="margin-top:16px;">
        <div style="font-weight:700; margin-bottom:10px;">
            Товари у партії
        </div>

       <div id="itemsList" class="delivery-list"></div>

            <tbody id="itemsTable"></tbody>
        </table>
    </div>





</main>
<script>
async function addItem() {

    const product_id = document.getElementById('product_id').value;
    const qty = document.getElementById('qty').value;
    const price = document.getElementById('price').value;

    await fetch('/api/deliveries/{{ $id }}/items', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            product_id: product_id,
            qty_declared: qty,
            supplier_price: price
        })
    });

    document.getElementById('product_id').value = '';
    document.getElementById('qty').value = '';
    document.getElementById('price').value = '';

    loadItems();
}


async function loadItems() {

    const res = await fetch('/api/deliveries/{{ $id }}/items');
    const data = await res.json();

    const list = document.getElementById('itemsList');
    list.innerHTML = '';

    data.forEach(item => {

        list.innerHTML += `
            <div class="delivery-row">

                <div class="delivery-row-top">
                    ${item.name}
                </div>

                <div class="delivery-row-bottom">
                    <div>
                        <span class="label">Заявлено</span>
                        <span class="value">${item.qty_declared}</span>
                    </div>

                    <div>
                        <span class="label">Прийнято</span>
                        <span class="value">${item.qty_accepted ?? '-'}</span>
                    </div>

                    <div>
                        <span class="label">Ціна</span>
                        <span class="value">${item.supplier_price}</span>
                    </div>
                </div>

            </div>
        `;
    });

}

loadItems();





async function markShipped() {

    await fetch('/api/deliveries/{{ $id }}/ship', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    });

    document.getElementById('deliveryStatus').innerText = 'SHIPPED';
    document.getElementById('addItemCard').style.display = 'none';
}






</script>


@endsection
