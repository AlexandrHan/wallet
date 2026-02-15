@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="/css/stock.css?v={{ filemtime(public_path('css/stock.css')) }}">
@endpush



@section('content')

<main class="wrap stock-wrap {{ auth()->check() ? 'has-tg-nav' : '' }}">

    <div class="card" style="margin-top:12px;">
        <div style="text-align:center">
            Статус: <b id="deliveryStatus">DRAFT</b>
        </div>

        <div style="margin-top:10px;">
            <button class="btn primary" id="shipBtn" onclick="markShipped()" style="width:100%; display:none;">
                Відправити
            </button>

            <button class="btn primary" id="acceptBtn" onclick="acceptDelivery()"
                    style="width:100%; margin-top:10px; display:none;">
                Прийняти партію
            </button>
        </div>
    </div>

    <div class="card" id="addItemCard" style="margin-top:16px; display:none;">
        <div style="font-weight:700; margin-bottom:10px; text-align:center;">
            Додати товар
        </div>

        <div class="stock-form">

            <div class="stock-row-top">
                <select class="btn" id="product_id">
                    <option value="">Оберіть товар з списку</option>
                </select>
            </div>

            <div class="stock-row-top">
                <button class="btn" onclick="createProduct()">
                    Додати новий товар
                </button>
            </div>

            <div class="stock-row-bottom">
                <input class="btn btn-input" id="qty" type="number" placeholder="Кількість">
                <input class="btn btn-input" id="price" type="number" placeholder="Ціна">
                <button class="btn primary" onclick="addItem()">Додати</button>
            </div>

        </div>
    </div>

    <div class="card" style="margin-top:16px;">
        <div style="font-weight:700; margin-bottom:10px;">
            Товари у партії
        </div>

        <div id="itemsList" class="delivery-list"></div>
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

function canAccept(){
    return AUTH_ROLE === 'owner' || AUTH_ROLE === 'accountant';
}

let DELIVERY_STATUS = 'draft';

async function loadDelivery() {
    const res = await fetch('/api/deliveries/{{ $id }}');
    const d = await res.json();

    DELIVERY_STATUS = (d.status || 'draft').toLowerCase();
    document.getElementById('deliveryStatus').innerText = DELIVERY_STATUS.toUpperCase();

    // draft: можна редагувати + можна відправити
    if (DELIVERY_STATUS === 'draft') {
        document.getElementById('addItemCard').style.display = 'block';
        document.getElementById('shipBtn').style.display = 'block';
        document.getElementById('acceptBtn').style.display = 'none';
    }

    // shipped: редагування ховаємо, показуємо “Прийняти” тільки бухгалтер/власник
    if (DELIVERY_STATUS === 'shipped') {
        document.getElementById('addItemCard').style.display = 'none';
        document.getElementById('shipBtn').style.display = 'none';
        document.getElementById('acceptBtn').style.display = canAccept() ? 'block' : 'none';
    }

    // accepted: все read-only
    if (DELIVERY_STATUS === 'accepted') {
        document.getElementById('addItemCard').style.display = 'none';
        document.getElementById('shipBtn').style.display = 'none';
        document.getElementById('acceptBtn').style.display = 'none';
    }

    await loadItems();
}

async function loadItems() {
    const res = await fetch('/api/deliveries/{{ $id }}/items');
    const data = await res.json();

    const list = document.getElementById('itemsList');
    list.innerHTML = '';

    const isShipped = (DELIVERY_STATUS === 'shipped');
    const editableAccepted = isShipped && canAccept();

    data.forEach(item => {
        const itemId = item.item_id ?? item.id; // підтримка обох форматів API
        const acceptedVal = (item.qty_accepted ?? item.qty_declared ?? 0);

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
                        ${
                            editableAccepted
                                ? `<input class="btn"
                                          type="number"
                                          data-item-id="${itemId}"
                                          value="${acceptedVal}"
                                          style="width:78px; text-align:center; padding:0 10px;">`
                                : `<span class="value">${item.qty_accepted ?? '-'}</span>`
                        }
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

    
}

async function markShipped() {
    await fetch('/api/deliveries/{{ $id }}/ship', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    });

    await loadDelivery();
}

async function acceptDelivery() {
    const inputs = document.querySelectorAll('[data-item-id]');
    const items = [];

    inputs.forEach(inp => {
        items.push({
            item_id: Number(inp.dataset.itemId),
            qty_accepted: Number(inp.value || 0),
        });
    });

    const res = await fetch('/api/deliveries/{{ $id }}/accept', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ items })
    });

    const data = await res.json();

    if (!res.ok) {
        alert(data.error ?? 'Помилка');
        return;
    }

    await loadDelivery();
}

async function loadProducts() {
    const res = await fetch('/api/products');
    const products = await res.json();

    const select = document.getElementById('product_id');
    select.innerHTML = `<option value="">Оберіть товар з списку</option>`;

    products.forEach(p => {
        select.innerHTML += `<option value="${p.id}">${p.name}</option>`;
    });
}

function createProduct() {
    const name = prompt('Назва товару');
    if (!name) return;

    fetch('/api/products', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ name })
    }).then(() => loadProducts());
}

    document.addEventListener('DOMContentLoaded', () => {
    loadProducts();
    loadDelivery();
});
</script>

@endsection
