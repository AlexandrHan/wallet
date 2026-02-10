@extends('layouts.app')

@section('content')

<main class="wrap">
    <div style="margin-bottom:12px;">
    <a href="/stock" class="btn">← Назад до складу</a>
</div>


<div class="card" style="margin-top:16px;">

    <div style="font-weight:700; margin-bottom:10px;">
        Додати товар
    </div>

    <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <select id="product_id">
            <option value="">Оберіть товар</option>
        </select>

        <input id="qty" type="number" placeholder="Кількість">
        <input id="price" type="number" placeholder="Ціна">

        <button class="btn" onclick="addItem()">
            Додати
        </button>
    </div>
    <div class="card" style="margin-top:16px;">
        <div style="font-weight:700; margin-bottom:10px;">
            Товари у партії
        </div>

        <table style="width:100%;">
            <thead>
                <tr>
                    <th>Товар</th>
                    <th>Кількість</th>
                    <th>Ціна</th>
                </tr>
            </thead>
            <tbody id="itemsTable"></tbody>
        </table>
    </div>


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

    const table = document.getElementById('itemsTable');
    table.innerHTML = '';

    data.forEach(item => {
        table.innerHTML += `
            <tr>
                <td>${item.name}</td>
                <td>${item.qty_declared}</td>
                <td>${item.supplier_price}</td>
            </tr>
        `;
    });
}

loadItems();


async function loadProducts() {

    const res = await fetch('/api/products');
    const products = await res.json();

    const select = document.getElementById('product_id');

    products.forEach(p => {
        select.innerHTML += `
            <option value="${p.id}">
                ${p.name}
            </option>
        `;
    });
}

loadProducts();


</script>


@endsection
