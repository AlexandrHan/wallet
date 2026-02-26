@extends('layouts.app')

@section('content')

<div class="container" style="max-width:1100px;margin:10px auto 40px;">

  <h2 style="margin-bottom:24px;font-weight:800;text-align:center;">Гаманці співробітників</h2>

  <div id="staffWalletsGrid" class="wallet-grid"></div>
  <!-- VIEW 2: Операції (обовʼязково для wallet.js) -->
<div id="opsView" style="display:none;">

  <div class="content" style="text-align:center; margin-bottom:10px;">
    <div class="muted btn" id="walletTitle"></div>
    <div style="padding-bottom:0.5rem; padding-top:1.5rem;" class="muted">Поточний баланс</div>
    <div style="padding-bottom:1rem;" class="big" id="walletBalance"></div>
  </div>

  <div class="row">
    <button type="button" class="btn" id="backToWallets">← Назад</button>
    <span class="tag" id="roTag" style="display:inline-block;">тільки перегляд</span>

    <!-- на сторінці staff-wallets ці кнопки не потрібні -->
    <button type="button" class="btn primary right" id="addIncome" style="display:none">+ Дохід</button>
    <button type="button" class="btn danger" id="addExpense" style="display:none">+ Витрата</button>
  </div>

  <table>
    <thead>
      <tr>
        <th>Дата</th>
        <th>Тип</th>
        <th>Сума</th>
        <th>Коментар</th>
      </tr>
    </thead>
    <tbody id="entries"></tbody>
  </table>

</div>

</div>
@include('partials.nav.bottom')

<script>
document.addEventListener('DOMContentLoaded', async () => {


  const grid = document.getElementById('staffWalletsGrid');

  const res = await fetch('/api/wallets');
  if(!res.ok){
    grid.innerHTML = 'Помилка';
    return;
  }

  const wallets = await res.json();

const staff = wallets.filter(w =>
  w.owner &&
  !['hlushchenko','kolisnyk'].includes(w.owner)
);
  grid.innerHTML = staff.map(w => `
    <div class="card staff-wallet-card" data-id="${w.id}">
      <div class="sw-top">
        <div class="sw-name">${w.name}</div>
        <div class="sw-owner">${w.owner}</div>
      </div>

      <div class="sw-balance">
        ${Number(w.balance).toLocaleString('uk-UA')} ${w.currency}
      </div>

      <div class="sw-entries" style="display:none;margin-top:15px;"></div>
    </div>
  `).join('');

});
</script>

<script>
document.addEventListener('click', async function(e){

  const card = e.target.closest('.staff-wallet-card');
  if(!card) return;

  const entriesBox = card.querySelector('.sw-entries');
  const walletId = card.dataset.id;

  const isOpen = entriesBox.style.display === 'block';

  // Закрити всі
  document.querySelectorAll('.sw-entries').forEach(b => b.style.display = 'none');

  if(isOpen) return;

  entriesBox.style.display = 'block';
  entriesBox.innerHTML = 'Завантаження...';

  const res = await fetch(`/api/wallets/${walletId}/entries`);
  if(!res.ok){
    entriesBox.innerHTML = 'Помилка завантаження';
    return;
  }

  const entries = await res.json();

  if(!entries.length){
    entriesBox.innerHTML = '<div style="opacity:.6;">Немає операцій</div>';
    return;
  }

  entriesBox.innerHTML = entries.map(e => `
    <div style="padding:8px 0;border-bottom:1px solid #222;">
      <div style="font-weight:600;">
        ${e.entry_type === 'income' ? '➕' : '➖'}
        ${Number(e.amount).toLocaleString('uk-UA')}
      </div>
      <div style="font-size:12px;opacity:.6;">
        ${e.comment ?? ''}
      </div>
    </div>
  `).join('');

});
</script>

@endsection