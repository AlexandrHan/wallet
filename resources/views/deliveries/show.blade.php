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
      'accountant' => 'partials.nav.bottom-accountant',
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

/* =========================
   LOAD DELIVERY
========================= */
async function loadDelivery() {

    const res = await fetch('/api/deliveries/{{ $id }}');
    const d = await res.json();

    DELIVERY_STATUS = (d.status || 'draft').toLowerCase();
    document.getElementById('deliveryStatus').innerText =
        DELIVERY_STATUS.toUpperCase();

    // UI state
    if (DELIVERY_STATUS === 'draft') {
        document.getElementById('shipBtn').style.display = 'none';
        document.getElementById('acceptBtn').style.display = 'none';
    }

    if (DELIVERY_STATUS === 'shipped') {
        document.getElementById('shipBtn').style.display = 'none';
        document.getElementById('acceptBtn').style.display =
            canAccept() ? 'block' : 'none';
    }

    if (DELIVERY_STATUS === 'accepted') {
        document.getElementById('shipBtn').style.display = 'none';
        document.getElementById('acceptBtn').style.display = 'none';
    }

    // ---- LOAD ITEMS ONCE ----
    const resItems = await fetch('/api/deliveries/{{ $id }}/items');
    const items = await resItems.json();

    // ✅ EMPTY DRAFT CHECK (ОДИН РАЗ)
    if (DELIVERY_STATUS === 'draft' && items.length === 0) {

        const confirmDelete = confirm(
            'Це порожня чернетка. Видалити?'
        );

        if (confirmDelete) {
            await deleteDraft();
            return;
        } else {
            window.location.href = '/deliveries';
            return;
        }
    }

    renderItems(items);
}

/* =========================
   RENDER ITEMS
========================= */
function renderItems(data) {

    const list = document.getElementById('itemsList');
    list.innerHTML = '';

    const isShipped = (DELIVERY_STATUS === 'shipped');
    const editableAccepted = isShipped && canAccept();

    data.forEach(item => {

        const itemId = item.item_id ?? item.id;
        const acceptedVal =
            (item.qty_accepted ?? item.qty_declared ?? 0);

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
                                      style="width:78px;text-align:center;padding:0 10px;">`
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

/* =========================
   DELETE DRAFT
========================= */
async function deleteDraft() {

    await fetch('/api/deliveries/{{ $id }}', {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN':
                document.querySelector('meta[name="csrf-token"]').content
        }
    });

    window.location.href = '/deliveries';
}

/* =========================
   SHIP
========================= */
async function markShipped() {

    await fetch('/api/deliveries/{{ $id }}/ship', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN':
                document.querySelector('meta[name="csrf-token"]').content
        }
    });

    await loadDelivery();
}

/* =========================
   ACCEPT
========================= */
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
            'X-CSRF-TOKEN':
                document.querySelector('meta[name="csrf-token"]').content
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

/* =========================
   INIT
========================= */
document.addEventListener('DOMContentLoaded', () => {
    loadDelivery();
});
</script>


@endsection
