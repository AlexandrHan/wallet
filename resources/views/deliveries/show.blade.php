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

            <button class="btn"
                    id="deleteDraftBtn"
                    onclick="deleteDraft()"
                    style="width:100%; margin-top:10px; display:none; background:rgba(255,80,80,.15);">
                🗑 Видалити чернетку
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
function canDeleteDraft(){
  return AUTH_ROLE === 'sunfix_manager';
}

let DELIVERY_STATUS = 'draft';

/* =========================
   LOAD DELIVERY
========================= */
async function loadDelivery() {

  const res = await fetch('/api/deliveries/{{ $id }}', {
    headers: { 'Accept': 'application/json' }
  });

  if (!res.ok) {
    const text = await res.text();
    alert(`Не можу завантажити поставку (${res.status}). ${text.slice(0,200)}`);
    return;
  }

  const d = await res.json();

  DELIVERY_STATUS = (d.status || 'draft').toLowerCase();
  document.getElementById('deliveryStatus').innerText = DELIVERY_STATUS.toUpperCase();

  // Показ/приховати кнопку видалення чернетки
  const deleteBtn = document.getElementById('deleteDraftBtn');
  if (deleteBtn) {
    deleteBtn.style.display = (DELIVERY_STATUS === 'draft' && canDeleteDraft()) ? 'block' : 'none';
  }

  // UI state
  document.getElementById('shipBtn').style.display = 'none';

  if (DELIVERY_STATUS === 'shipped') {
    document.getElementById('acceptBtn').style.display = canAccept() ? 'block' : 'none';
  } else {
    document.getElementById('acceptBtn').style.display = 'none';
  }

  // ---- LOAD ITEMS ONCE ----
  const resItems = await fetch('/api/deliveries/{{ $id }}/items', {
    headers: { 'Accept': 'application/json' }
  });

  if (!resItems.ok) {
    const text = await resItems.text();
    alert(`Не можу завантажити товари (${resItems.status}). ${text.slice(0,200)}`);
    return;
  }

  const items = await resItems.json();

  // ✅ EMPTY DRAFT CHECK (1 раз)
  if (DELIVERY_STATUS === 'draft' && Array.isArray(items) && items.length === 0 && canDeleteDraft()) {
    const ok = confirm('Це порожня чернетка. Видалити?');
    if (ok) {
      await deleteDraft({ skipConfirm: true });
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

  if (!Array.isArray(data)) return;

  const isShipped = (DELIVERY_STATUS === 'shipped');
  const editableAccepted = isShipped && canAccept();

  data.forEach(item => {
    const itemId = item.item_id ?? item.id;
    const acceptedVal = (item.qty_accepted ?? item.qty_declared ?? 0);

    list.innerHTML += `
      <div class="delivery-row">
        <div class="delivery-row-top">${item.name}</div>

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
async function deleteDraft({ skipConfirm = false } = {}) {

  if (!skipConfirm) {
    const ok = confirm('Видалити чернетку разом з усіма товарами в ній?');
    if (!ok) return;
  }

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

  const res = await fetch('/api/deliveries/{{ $id }}', {
    method: 'DELETE',
    credentials: 'same-origin',
    headers: {
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
    }
  });

  const text = await res.text();
  let data = {};
  try { data = text ? JSON.parse(text) : {}; } catch (e) {}

  if (!res.ok) {
    alert(data.error ?? `DELETE failed (${res.status}). ${text.slice(0,200)}`);
    return;
  }

  window.location.assign('/deliveries');
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
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    },
    body: JSON.stringify({ items })
  });

  const text = await res.text();
  let data = {};
  try { data = text ? JSON.parse(text) : {}; } catch (e) {}

  if (!res.ok) {
    alert(data.error ?? 'Помилка');
    return;
  }

  await loadDelivery();
}

/* INIT */
document.addEventListener('DOMContentLoaded', () => {
  loadDelivery();
});
</script>


@endsection
