@extends('layouts.app')



@push('styles')
<link rel="stylesheet" href="/css/stock.css?v={{ filemtime(public_path('css/stock.css')) }}">
@endpush

@section('content')

<main class="wrap stock-wrap {{ auth()->check() ? 'has-tg-nav' : '' }}">

    <!-- STATUS -->
    <div class="card">
        <div style="text-align:center">
            Статус: <b>Чорновик</b>
        </div>
    </div>

    <!-- ADD ITEM -->
    <div class="card" style="margin-top:16px;">
        <div class="delivery-row-start" style="font-weight:700; margin-bottom:10px; text-align:center;">
            Додати товар
        </div>

        <div class="stock-form">

            <div class="stock-row-top">
                <button class="btn" onclick="createProduct()">
                    Додати новий товар
                </button>
            </div>

            <div class="stock-row-top">
                <select class="btn" id="product_id">
                    <option value="">Оберіть товар з списку</option>
                </select>
            </div>

            <div class="stock-row-bottom">
                <input class="btn btn-input" id="qty" type="number" placeholder="Кількість">
                <input class="btn btn-input" id="price" type="number" placeholder="Ціна">
                <button class="btn primary" onclick="addItem()">Додати</button>
            </div>

        </div>
    </div>
    

    <!-- ITEMS -->
    <div class="card" style="margin-top:16px;">
        <div style="font-weight:700; margin-bottom:10px;">
            Товари у партії
        </div>

        <div id="itemsList" class="delivery-list"></div>
    </div>

    <!-- SHIP -->
    <div class="card" style="margin-top:16px;">
        <button class="btn primary" style="width:100%" onclick="markShipped()">
            Відправити
        </button>
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

let DELIVERY_ID = null;

/* =====================
   CREATE DRAFT DELIVERY
===================== */
async function createDelivery() {

    const res = await fetch('/api/deliveries', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document
                .querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            supplier_id: 1
        })
    });

    const data = await res.json();
    DELIVERY_ID = data.id;
}

/* =====================
   ADD ITEM
===================== */
async function addItem() {

    if (!DELIVERY_ID) return;

    const product_id = document.getElementById('product_id').value;
    const qty = document.getElementById('qty').value;
    const price = document.getElementById('price').value;

    await fetch(`/api/deliveries/${DELIVERY_ID}/items`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document
                .querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            product_id,
            qty_declared: qty,
            supplier_price: price
        })
    });

    document.getElementById('product_id').value = '';
    document.getElementById('qty').value = '';
    document.getElementById('price').value = '';

    loadItems();
}

async function deleteItem(itemId) {

    const ok = confirm('Видалити товар з партії?');
    if (!ok) return;

    await fetch(`/api/deliveries/items/${itemId}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN':
                document.querySelector('meta[name="csrf-token"]').content
        }
    });

    loadItems();
}


/* =====================
   LOAD ITEMS
===================== */
async function loadItems() {

    if (!DELIVERY_ID) return;

    const res = await fetch(`/api/deliveries/${DELIVERY_ID}/items`);
    const data = await res.json();

    const list = document.getElementById('itemsList');
    list.innerHTML = '';

    data.forEach(item => {
        list.innerHTML += `
            <div class="delivery-row">

                <div class="delivery-row-top"
                    style="display:flex; justify-content:space-between; align-items:center;">
                    
                    <span>${item.name}</span>

                    ${DELIVERY_STATUS === 'draft'
                        ? `<button class="btn"
                            style="padding:4px 10px; background:rgba(255,80,80,.15);"
                            onclick="deleteItem(${item.item_id})">
                            🗑
                        </button>`
                        : ''
                    }

                </div>

                <div class="delivery-row-bottom">
                    <div>
                        <span class="label">Заявлено</span>
                        <span class="value">${item.qty_declared}</span>
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

/* =====================
   PRODUCTS
===================== */
async function loadProducts() {
    const res = await fetch('/api/products');
    const products = await res.json();

    const select = document.getElementById('product_id');

    products.forEach(p => {
        select.innerHTML += `
            <option value="${p.id}">${p.name}</option>
        `;
    });
}


function createProduct() {
    const name = prompt('Назва товару');
    if (!name) return;

    fetch('/api/products', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document
                .querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ name })
    }).then(() => {
        document.getElementById('product_id').innerHTML =
            '<option value="">Оберіть товар</option>';

        loadProducts();
    });
}

/* =====================
   SHIP DELIVERY
===================== */
async function markShipped() {

    if (!DELIVERY_ID) return;

    await fetch(`/api/deliveries/${DELIVERY_ID}/ship`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document
                .querySelector('meta[name="csrf-token"]').content
        }
    });

    window.location.href = `/deliveries/${DELIVERY_ID}`;
}

/* INIT */
createDelivery();
loadProducts();

</script>

@endsection


