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


            <div class="card" style="margin-top:16px;">
                <div style="font-weight:700; margin-bottom:10px; text-align:center;">
                    Каталог товарів
                </div>

                <div style="display:flex; gap:10px;">
                    <button type="button" class="btn" onclick="toggleProductPanel('new')">➕ Додати новий товар</button>
                    <button type="button" class="btn" onclick="toggleProductPanel('edit')">✏️ Редагувати існуючий товар</button>
                </div>

                <!-- NEW -->
                <div id="newProductPanel" style="display:none; margin-top:12px;">
                    <div class="stock-row-top">
                    <select class="btn" id="new_category_id">
                        <option value="">Оберіть категорію</option>
                    </select>
                    </div>

                    <div class="stock-row-top" style="margin-top:10px;">
                    <input class="btn btn-input" id="new_product_name" type="text" placeholder="Назва нового товару">
                    </div>

                    <div style="margin-top:10px;">
                    <button type="button" class="btn primary" style="width:100%" onclick="createProductFromPanel()">
                        Зберегти товар
                    </button>
                    </div>
                </div>

                <!-- EDIT -->
                <div id="editProductPanel" style="display:none; margin-top:12px;">
                    <div class="stock-row-top">
                    <select class="btn" id="edit_product_id" onchange="fillEditProduct()">
                        <option value="">Оберіть товар</option>
                    </select>
                    </div>

                    <div class="stock-row-top" style="margin-top:10px;">
                    <select class="btn" id="edit_category_id">
                        <option value="">Оберіть категорію</option>
                    </select>
                    </div>

                    <div class="stock-row-top" style="margin-top:10px;">
                    <input class="btn btn-input" id="edit_product_name" type="text" placeholder="Нова назва">
                    </div>

                    <div style="display:flex; gap:10px; margin-top:10px;">
                    <button type="button" class="btn primary" style="flex:1" onclick="updateProductFromPanel()">
                        Зберегти зміни
                    </button>

                    <button type="button" class="btn" style="flex:1; background:rgba(255,80,80,.15);" onclick="archiveProductFromPanel()">
                        🗑 В архів
                    </button>
                    </div>
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

                <button class="btn btn-trash" style="padding:4px 2px; border:none;" onclick="deleteItem(${item.item_id})">
                    🗑
                </button>


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

    // групуємо по категоріях
    const groups = {};

    products.forEach(p => {
        const cat = p.category_name || 'Інше';

        if (!groups[cat]) {
            groups[cat] = [];
        }

        groups[cat].push(p);
    });

    // малюємо optgroup
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



async function createProduct() {

    // 1. отримуємо категорії
    const res = await fetch('/api/product-categories');
    const categories = await res.json();

    // будуємо список
    let text = 'Оберіть категорію:\n\n';
    categories.forEach(c => {
        text += `${c.id} — ${c.name}\n`;
    });

    const category_id = prompt(text);
    if (!category_id) return;

    // 2. назва товару
    const name = prompt('Назва товару');
    if (!name) return;

    const save = await fetch('/api/products', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document
                .querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            name,
            category_id
        })
    });

    const data = await save.json();

    if (!save.ok) {
        alert(data.error ?? 'Помилка');
        return;
    }

    // перезавантажуємо список
    await loadProducts();

    alert('Товар створено');
}


async function safeJson(res){
  const text = await res.text();
  try { return JSON.parse(text); } catch(e) { return { error: text }; }
}

async function loadCategories(){
  const res = await fetch('/api/product-categories');
  if (!res.ok) {
    alert('Не відкривається /api/product-categories');
    return;
  }
  const cats = await res.json();

  const selNew  = document.getElementById('new_category_id');
  const selEdit = document.getElementById('edit_category_id');

  selNew.innerHTML  = `<option value="">Оберіть категорію</option>`;
  selEdit.innerHTML = `<option value="">Змінити категорію</option>`;

  cats.forEach(c => {
    selNew.innerHTML  += `<option value="${c.id}">${c.name}</option>`;
    selEdit.innerHTML += `<option value="${c.id}">${c.name}</option>`;
  });
}

async function createProductSmart(){
  const name = document.getElementById('new_product_name').value.trim();
  const category_id = document.getElementById('new_category_id').value;

  if (!category_id) { alert('Оберіть категорію'); return; }
  if (!name) { alert('Введіть назву товару'); return; }

  const res = await fetch('/api/products', {
    method: 'POST',
    headers: {
      'Content-Type':'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    },
    body: JSON.stringify({ name, category_id })
  });

  const data = await safeJson(res);

  if (!res.ok) {
    alert(data.error ?? 'Помилка створення товару');
    return;
  }

  document.getElementById('new_product_name').value = '';
  await loadProducts();

  // приємно: одразу вибрати щойно створений товар у селекті товарів
  const productSelect = document.getElementById('product_id');
  if (productSelect) productSelect.value = data.id;

  // і в “редагуванні” теж
  const editSelect = document.getElementById('edit_product_id');
  if (editSelect) editSelect.value = data.id;
}

async function loadProducts(){
  const res = await fetch('/api/products');
  const products = await res.json();

  // селект для додавання в партію (з optgroup як було)
  const select = document.getElementById('product_id');
  select.innerHTML = `<option value="">Оберіть товар</option>`;

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

  // селект для редагування/видалення
  const editSel = document.getElementById('edit_product_id');
  editSel.innerHTML = `<option value="">Редагувати існуючий товар</option>`;
  products.forEach(p => {
    editSel.innerHTML += `<option value="${p.id}" data-category="${p.category_id}" data-name="${p.name}">${p.category_name} • ${p.name}</option>`;
  });

  // автозаповнення полів редагування
  editSel.onchange = () => {
    const opt = editSel.options[editSel.selectedIndex];
    document.getElementById('edit_product_name').value = opt?.dataset?.name ?? '';
    document.getElementById('edit_category_id').value = opt?.dataset?.category ?? '';
  };
}

async function saveProduct(){
  const id = document.getElementById('edit_product_id').value;
  if (!id) { alert('Оберіть товар для редагування'); return; }

  const name = document.getElementById('edit_product_name').value.trim();
  const category_id = document.getElementById('edit_category_id').value;

  if (!name) { alert('Назва не може бути пустою'); return; }
  if (!category_id) { alert('Оберіть категорію'); return; }

  const res = await fetch(`/api/products/${id}`, {
    method: 'PATCH',
    headers: {
      'Content-Type':'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    },
    body: JSON.stringify({ name, category_id })
  });

  const data = await safeJson(res);

  if (!res.ok) {
    alert(data.error ?? 'Помилка збереження');
    return;
  }

  await loadProducts();
}

async function deactivateProduct(){
  const id = document.getElementById('edit_product_id').value;
  if (!id) { alert('Оберіть товар'); return; }

  const ok = confirm('Прибрати товар зі списку? (історія поставок не постраждає)');
  if (!ok) return;

  const res = await fetch(`/api/products/${id}`, {
    method: 'DELETE',
    headers: {
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    }
  });

  const data = await safeJson(res);

  if (!res.ok) {
    alert(data.error ?? 'Помилка');
    return;
  }

  document.getElementById('edit_product_id').value = '';
  document.getElementById('edit_product_name').value = '';
  document.getElementById('edit_category_id').value = '';

  await loadProducts();
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

const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

let categories = [];
let productsActive = [];
let productsAll = [];

function toggleProductPanel(which){
  const newP = document.getElementById('newProductPanel');
  const editP = document.getElementById('editProductPanel');

  if (which === 'new') {
    newP.style.display = (newP.style.display === 'none' ? 'block' : 'none');
    editP.style.display = 'none';
  } else {
    editP.style.display = (editP.style.display === 'none' ? 'block' : 'none');
    newP.style.display = 'none';
  }
}

async function loadCategories(){
  const res = await fetch('/api/product-categories', { headers: { 'Accept':'application/json' } });
  const text = await res.text();
  categories = text ? JSON.parse(text) : [];

  const selNew  = document.getElementById('new_category_id');
  const selEdit = document.getElementById('edit_category_id');

  if (selNew)  selNew.innerHTML  = `<option value="">Оберіть категорію</option>`;
  if (selEdit) selEdit.innerHTML = `<option value="">Оберіть категорію</option>`;

  categories.forEach(c => {
    const opt1 = document.createElement('option');
    opt1.value = c.id;
    opt1.textContent = c.name;
    selNew?.appendChild(opt1);

    const opt2 = document.createElement('option');
    opt2.value = c.id;
    opt2.textContent = c.name;
    selEdit?.appendChild(opt2);
  });
}

async function loadProducts(){
  // для списку у партію (тільки активні)
  const r1 = await fetch('/api/products', { headers:{'Accept':'application/json'} });
  productsActive = await r1.json();

  // для редагування (включно з неактивними)
  const r2 = await fetch('/api/products?include_inactive=1', { headers:{'Accept':'application/json'} });
  productsAll = await r2.json();

  // 1) твій селект для додавання в партію (групуємо по категоріях)
  const select = document.getElementById('product_id');
  if (select) {
    select.innerHTML = `<option value="">Оберіть товар з списку</option>`;

    const groups = {};
    productsActive.forEach(p => {
      const cat = p.category_name || 'Інше';
      (groups[cat] ||= []).push(p);
    });

    Object.keys(groups).forEach(cat => {
      const og = document.createElement('optgroup');
      og.label = cat;

      groups[cat].forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.id;
        opt.textContent = p.name;
        og.appendChild(opt);
      });

      select.appendChild(og);
    });
  }

  // 2) селект для редагування
  const editSel = document.getElementById('edit_product_id');
  if (editSel) {
    editSel.innerHTML = `<option value="">Оберіть товар</option>`;
    productsAll.forEach(p => {
      const opt = document.createElement('option');
      opt.value = p.id;
      opt.textContent = `${p.category_name ? `[${p.category_name}] ` : ''}${p.name}${p.is_active ? '' : ' (архів)'}`;
      editSel.appendChild(opt);
    });
  }
}

function fillEditProduct(){
  const id = Number(document.getElementById('edit_product_id')?.value || 0);
  const p = productsAll.find(x => Number(x.id) === id);
  if (!p) return;

  document.getElementById('edit_product_name').value = p.name || '';
  document.getElementById('edit_category_id').value = p.category_id || '';
}

async function createProductFromPanel(){
  const name = document.getElementById('new_product_name')?.value?.trim() || '';
  const category_id = Number(document.getElementById('new_category_id')?.value || 0);

  if (!category_id) return alert('Оберіть категорію');
  if (!name) return alert('Введіть назву');

  const res = await fetch('/api/products', {
    method: 'POST',
    headers: {
      'Content-Type':'application/json',
      'Accept':'application/json',
      ...(CSRF ? { 'X-CSRF-TOKEN': CSRF } : {}),
      'X-Requested-With': 'XMLHttpRequest',
    },
    body: JSON.stringify({ name, category_id })
  });

  const text = await res.text();
  let data = {};
  try { data = text ? JSON.parse(text) : {}; } catch(e){}

  if (!res.ok) return alert(data.error ?? `Помилка (${res.status})`);

  document.getElementById('new_product_name').value = '';
  document.getElementById('new_category_id').value = '';
  document.getElementById('newProductPanel').style.display = 'none';

  await loadProducts();
}

async function updateProductFromPanel(){
  const id = Number(document.getElementById('edit_product_id')?.value || 0);
  const name = document.getElementById('edit_product_name')?.value?.trim() || '';
  const category_id = Number(document.getElementById('edit_category_id')?.value || 0);

  if (!id) return alert('Оберіть товар');
  if (!category_id) return alert('Оберіть категорію');
  if (!name) return alert('Введіть назву');

  const res = await fetch(`/api/products/${id}`, {
    method: 'PATCH',
    headers: {
      'Content-Type':'application/json',
      'Accept':'application/json',
      ...(CSRF ? { 'X-CSRF-TOKEN': CSRF } : {}),
      'X-Requested-With': 'XMLHttpRequest',
    },
    body: JSON.stringify({ name, category_id })
  });

  const text = await res.text();
  let data = {};
  try { data = text ? JSON.parse(text) : {}; } catch(e){}

  if (!res.ok) return alert(data.error ?? `Помилка (${res.status})`);

  document.getElementById('editProductPanel').style.display = 'none';
  await loadProducts();
}

async function archiveProductFromPanel(){
  const id = Number(document.getElementById('edit_product_id')?.value || 0);
  if (!id) return alert('Оберіть товар');

  const ok = confirm('Відправити товар в архів? Він зникне зі списку додавання в партію.');
  if (!ok) return;

  const res = await fetch(`/api/products/${id}`, {
    method: 'DELETE',
    headers: {
      'Accept':'application/json',
      ...(CSRF ? { 'X-CSRF-TOKEN': CSRF } : {}),
      'X-Requested-With': 'XMLHttpRequest',
    }
  });

  const text = await res.text();
  let data = {};
  try { data = text ? JSON.parse(text) : {}; } catch(e){}

  if (!res.ok) return alert(data.error ?? `Помилка (${res.status})`);

  document.getElementById('editProductPanel').style.display = 'none';
  await loadProducts();
}

// INIT
document.addEventListener('DOMContentLoaded', async () => {
  await loadCategories();
  await loadProducts();
});


/* INIT */
createDelivery();
loadCategories();
loadProducts();


</script>

@endsection


