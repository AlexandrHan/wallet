@extends('layouts.app')



@push('styles')
<link rel="stylesheet" href="/css/stock.css?v={{ filemtime(public_path('css/stock.css')) }}">
@endpush

@section('content')

<main class="wrap stock-wrap {{ auth()->check() ? 'has-tg-nav' : '' }}">


 


    <!-- ADD ITEM -->
    <div class="card" style="margin-top:16px;">
        <div class="delivery-row-start" style="font-weight:700; margin-bottom:10px; text-align:center;">
            Додати товар
        </div>

        <div class="stock-form">

            <!-- <div class="stock-row-top" style="gap:10px; display:flex; flex-wrap:wrap;">
                <select class="btn" id="new_category_id" style="flex:1; min-width:220px;">
                    <option value="">Оберіть категорію</option>
                </select>

                <input class="btn btn-input" id="new_product_name" type="text" placeholder="Назва нового товару" style="flex:2; min-width:260px;">

                <button class="btn primary" type="button" onclick="createProductSmart()">
                    ➕ Додати товар
                </button>
                </div>

                <div class="stock-row-top" style="margin-top:10px; gap:10px; display:flex; flex-wrap:wrap;">
                <select class="btn" id="edit_product_id" style="flex:1; min-width:220px;">
                    <option value="">Редагувати існуючий товар</option>
                </select>

                <input class="btn btn-input" id="edit_product_name" type="text" placeholder="Нова назва" style="flex:2; min-width:260px;">

                <select class="btn" id="edit_category_id" style="flex:1; min-width:220px;">
                    <option value="">Змінити категорію</option>
                </select>

                <button class="btn primary" type="button" onclick="saveProduct()">
                    💾 Зберегти
                </button>

                <button class="btn" type="button" onclick="deactivateProduct()" style="background:rgba(255,80,80,.15);">
                    🗑 Видалити
                </button>
            </div>-->


<div class="stock-row-top" style="display:flex; gap:10px;">
  <button type="button" class="btn" onclick="toggleProductPanel('add')">
    ➕ Додати новий товар
  </button>

  <button type="button" class="btn" onclick="toggleProductPanel('edit')">
    ✏️ Редагувати існуючий товар
  </button>
</div>

<!-- Панель: додати -->
<div id="productPanelAdd" class="card" style="margin-top:12px; display:none;">
  <div style="font-weight:700; margin-bottom:10px; text-align:center;">Додати новий товар</div>

  <select class="btn" id="new_category_id" style="width:100%; margin-bottom:10px;">
    <option value="">Оберіть категорію</option>
  </select>

  <input class="btn" id="new_product_name" placeholder="Назва товару" style="width:100%; margin-bottom:10px;">

  <button type="button" class="btn primary" style="width:100%;" onclick="createProductFromPanel()">
    Зберегти товар
  </button>
</div>

<!-- Панель: редагувати -->
<div id="productPanelEdit" class="card" style="margin-top:12px; display:none;">
  <div style="font-weight:700; margin-bottom:10px; text-align:center;">Редагування товару</div>

  <select class="btn" id="edit_product_id" style="width:100%; margin-bottom:10px;" onchange="fillEditProductFields()">
    <option value="">Оберіть товар</option>
  </select>

  <select class="btn" id="edit_category_id" style="width:100%; margin-bottom:10px;">
    <option value="">Оберіть категорію</option>
  </select>

  <input class="btn" id="edit_product_name" placeholder="Нова назва товару" style="width:100%; margin-bottom:10px;">

  <div style="display:flex; gap:10px;">
    <button type="button" class="btn primary" style="width:70%;" onclick="updateProductFromPanel()">
      Зберегти
    </button>

    <button type="button" class="btn" style="width:30%; background:rgba(255,80,80,.15);" onclick="deleteProductFromPanel()">
      🗑
    </button>
  </div>
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
        <button class="btn primary" id="shipBtn" style="width:100%; display:none;" onclick="markShipped()">
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

// сховаємо "Відправити" доки нема чого відправляти
function setShipVisible(visible){
  const btn = document.getElementById('shipBtn');
  if (!btn) return;
  btn.style.display = visible ? 'block' : 'none';
}

/* =====================
   CREATE DELIVERY (ONLY WHEN NEEDED)
===================== */
async function ensureDeliveryCreated() {
  if (DELIVERY_ID) return DELIVERY_ID;

  const res = await fetch('/api/deliveries', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? ''
    },
    body: JSON.stringify({ supplier_id: 1 })
  });

  const text = await res.text();
  let data = {};
  try { data = text ? JSON.parse(text) : {}; } catch(e) {}

  if (!res.ok || !data.id) {
    alert(data.error ?? `Не вдалося створити партію (${res.status})`);
    throw new Error('create delivery failed: ' + text);
  }

  DELIVERY_ID = data.id;
  return DELIVERY_ID;
}

/* =====================
   ADD ITEM
===================== */
async function addItem() {
  const product_id = document.getElementById('product_id').value;
  const qty = document.getElementById('qty').value;
  const price = document.getElementById('price').value;

  if (!product_id) return alert('Оберіть товар');
  if (!qty || Number(qty) <= 0) return alert('Вкажіть кількість');
  if (price === '' || Number(price) < 0) return alert('Вкажіть ціну');

  // ✅ створюємо чернетку тільки тут, при першому додаванні
  await ensureDeliveryCreated();

  const res = await fetch(`/api/deliveries/${DELIVERY_ID}/items`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? ''
    },
    body: JSON.stringify({
      product_id,
      qty_declared: qty,
      supplier_price: price
    })
  });

  if (!res.ok) {
    const t = await res.text();
    alert('Помилка додавання товару: ' + t.slice(0,200));
    return;
  }

  document.getElementById('product_id').value = '';
  document.getElementById('qty').value = '';
  document.getElementById('price').value = '';

  await loadItems();
}

/* =====================
   DELETE ITEM (IF YOU HAVE ROUTE)
===================== */
async function deleteItem(itemId) {
  if (!DELIVERY_ID) return;

  const ok = confirm('Видалити товар з партії?');
  if (!ok) return;

  const res = await fetch(`/api/deliveries/items/${itemId}`, {
    method: 'DELETE',
    headers: {
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? ''
    }
  });

  if (!res.ok) {
    const t = await res.text();
    alert('Не вдалося видалити: ' + t.slice(0,200));
    return;
  }

  await loadItems();
}

/* =====================
   LOAD ITEMS
===================== */
async function loadItems() {
  const list = document.getElementById('itemsList');
  list.innerHTML = '';

  // ще нічого не створено = нуль товарів
  if (!DELIVERY_ID) {
    setShipVisible(false);
    return;
  }

  const res = await fetch(`/api/deliveries/${DELIVERY_ID}/items`);
  const data = await res.json();

  setShipVisible(data.length > 0);

  data.forEach(item => {
    list.innerHTML += `
      <div class="delivery-row">
        <div class="delivery-row-top" style="display:flex;justify-content:space-between;align-items:center;">
          <span>${item.name}</span>
          <button class="btn btn-trash" style="padding:4px 1px;" onclick="deleteItem(${item.item_id})">🗑</button>
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
  select.innerHTML = `<option value="">Оберіть товар</option>`;

  // групування по категоріях
  const groups = {};
  products.forEach(p => {
    const cat = p.category_name || 'Інше';
    (groups[cat] ||= []).push(p);
  });

  Object.keys(groups).forEach(category => {
    const group = document.createElement('optgroup');
    group.label = category;

    groups[category].forEach(p => {
      const option = document.createElement('option');
      option.value = p.id;
      option.textContent = p.name;
      group.appendChild(option);
    });

    select.appendChild(group);
  });
}

/* =====================
   SHIP DELIVERY
===================== */
async function markShipped() {
  if (!DELIVERY_ID) return alert('Немає товарів у партії');

  const res = await fetch(`/api/deliveries/${DELIVERY_ID}/ship`, {
    method: 'POST',
    headers: {
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? ''
    }
  });

  const text = await res.text();
  if (!res.ok) {
    alert(text.slice(0,200));
    return;
  }

  window.location.href = `/deliveries/${DELIVERY_ID}`;
}

function toggleProductPanel(mode) {
  const add = document.getElementById('productPanelAdd');
  const edit = document.getElementById('productPanelEdit');

  if (!add || !edit) {
    console.warn('Panels not found: productPanelAdd/productPanelEdit');
    return;
  }

  // якщо натиснули ту саму вкладку вдруге, закриваємо
  const isAddOpen = add.style.display !== 'none';
  const isEditOpen = edit.style.display !== 'none';

  if (mode === 'add') {
    edit.style.display = 'none';
    add.style.display = isAddOpen ? 'none' : 'block';
    if (add.style.display === 'block') {
      loadCategoriesToSelect('new_category_id');
    }
  }

  if (mode === 'edit') {
    add.style.display = 'none';
    edit.style.display = isEditOpen ? 'none' : 'block';
    if (edit.style.display === 'block') {
      loadCategoriesToSelect('edit_category_id');
      loadProductsForEditSelect();
    }
  }
}

async function loadCategoriesToSelect(selectId) {
  const sel = document.getElementById(selectId);
  if (!sel) return;

  const res = await fetch('/api/product-categories');
  const text = await res.text();
  let cats = [];
  try { cats = text ? JSON.parse(text) : []; } catch (e) {
    console.error('Categories not JSON:', text);
    return alert('Не вдалося завантажити категорії (дивись консоль).');
  }

  sel.innerHTML = `<option value="">Оберіть категорію</option>`;
  cats.forEach(c => {
    sel.innerHTML += `<option value="${c.id}">${c.name}</option>`;
  });
}

async function loadProductsForEditSelect() {
  const sel = document.getElementById('edit_product_id');
  if (!sel) return;

  const res = await fetch('/api/products');
  const products = await res.json();

  sel.innerHTML = `<option value="">Оберіть товар</option>`;
  products.forEach(p => {
    sel.innerHTML += `<option value="${p.id}" data-name="${escapeHtml(p.name)}" data-category="${p.category_id ?? ''}">
      ${p.category_name ? `[${p.category_name}] ` : ''}${p.name}
    </option>`;
  });
}

function fillEditProductFields() {
  const sel = document.getElementById('edit_product_id');
  const nameInp = document.getElementById('edit_product_name');
  const catSel = document.getElementById('edit_category_id');
  if (!sel || !nameInp || !catSel) return;

  const opt = sel.options[sel.selectedIndex];
  if (!opt || !opt.value) return;

  nameInp.value = opt.getAttribute('data-name') ?? '';
  catSel.value = opt.getAttribute('data-category') ?? '';
}

async function createProductFromPanel() {
  const name = document.getElementById('new_product_name')?.value?.trim();
  const category_id = document.getElementById('new_category_id')?.value;

  if (!category_id) return alert('Оберіть категорію');
  if (!name) return alert('Введіть назву товару');

  const res = await fetch('/api/products', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? ''
    },
    body: JSON.stringify({ name, category_id })
  });

  const text = await res.text();
  let data = {};
  try { data = text ? JSON.parse(text) : {}; } catch(e) {}

  if (!res.ok) {
    alert(data.error ?? ('Помилка створення: ' + text.slice(0,200)));
    return;
  }

  // оновлюємо селект товарів для додавання в партію
  await loadProducts();
  // закриваємо панель
  document.getElementById('productPanelAdd').style.display = 'none';
  document.getElementById('new_product_name').value = '';
}

async function updateProductFromPanel() {
  // ⚠️ Працює тільки якщо у тебе є бек-роут PATCH/PUT /api/products/{id}
  const id = document.getElementById('edit_product_id')?.value;
  const name = document.getElementById('edit_product_name')?.value?.trim();
  const category_id = document.getElementById('edit_category_id')?.value;

  if (!id) return alert('Оберіть товар');
  if (!category_id) return alert('Оберіть категорію');
  if (!name) return alert('Введіть назву');

  const res = await fetch(`/api/products/${id}`, {
    method: 'PATCH',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? ''
    },
    body: JSON.stringify({ name, category_id })
  });

  const text = await res.text();
  let data = {};
  try { data = text ? JSON.parse(text) : {}; } catch(e) {}

  if (!res.ok) {
    alert(data.error ?? ('Помилка збереження: ' + text.slice(0,200)));
    return;
  }

  await loadProducts();
  await loadProductsForEditSelect();
  alert('Збережено ✅');
}

async function deleteProductFromPanel() {
  // ⚠️ Працює тільки якщо у тебе є бек-роут DELETE /api/products/{id}
  const id = document.getElementById('edit_product_id')?.value;
  if (!id) return alert('Оберіть товар');

  const ok = confirm('Видалити цей товар?');
  if (!ok) return;

  const res = await fetch(`/api/products/${id}`, {
    method: 'DELETE',
    headers: {
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? ''
    }
  });

  const text = await res.text();
  let data = {};
  try { data = text ? JSON.parse(text) : {}; } catch(e) {}

  if (!res.ok) {
    alert(data.error ?? ('Помилка видалення: ' + text.slice(0,200)));
    return;
  }

  await loadProducts();
  await loadProductsForEditSelect();
  alert('Видалено ✅');
}

// маленький escape щоб не ламати option data-name
function escapeHtml(s){
  return String(s)
    .replaceAll('&','&amp;')
    .replaceAll('<','&lt;')
    .replaceAll('>','&gt;')
    .replaceAll('"','&quot;')
    .replaceAll("'","&#039;");
}


/* INIT */
document.addEventListener('DOMContentLoaded', () => {
  setShipVisible(false);
  loadProducts();
});
</script>


@endsection


