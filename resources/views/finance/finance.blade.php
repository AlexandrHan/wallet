@extends('layouts.app')

@push('styles')
  <link rel="stylesheet" href="/css/nav-telegram.css?v={{ filemtime(public_path('css/nav-telegram.css')) }}">
@endpush

@section('content')
<body class="{{ auth()->check() ? 'has-tg-nav' : '' }}">



<main class="wrap">

  <div class="card">
    <div>
      <div style="text-align:center; font-weight:700; margin-bottom:14px;">Продажі</div>
      <button id="createProjectBtn" class="btn" style="align-items:center;width: 100%;">➕ Новий проект</button>
    </div>
  </div>

  <div id="projectsContainer" style="margin-top:20px;"></div>
  <div id="projectModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); align-items:center; justify-content:center;">
  <div style="background:#111; padding:20px; border-radius:10px; width:320px;">

    <div style="font-weight:600; margin-bottom:10px;">Новий проект</div>

    <input id="clientName" class="btn" placeholder="ПІБ клієнта" style="width:100%; margin-bottom:10px;">

    <input id="totalAmount" type="number" class="btn" placeholder="Сума проекту" style="width:100%; margin-bottom:10px;">

    <select id="projectCurrency" class="btn" style="width:100%; margin-bottom:15px;">
      <option value="USD">USD</option>
      <option value="UAH">UAH</option>
      <option value="EUR">EUR</option>
    </select>

    <button id="saveProjectBtn" class="btn" style="width:100%; margin-bottom:8px;">Створити</button>
    <button id="closeModalBtn" class="btn" style="width:100%; background:#333;">Скасувати</button>

  </div>
</div>

<div id="advanceModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); align-items:center; justify-content:center;">
  <div style="background:#111; padding:20px; border-radius:10px; width:320px;">

    <div style="font-weight:600; margin-bottom:10px;">Створити аванс</div>

    <input id="advanceAmount" type="number" class="btn" placeholder="Сума авансу" style="width:100%; margin-bottom:10px;">

    <select id="advanceCurrency" class="btn" style="width:100%; margin-bottom:10px;">
      <option value="USD">USD</option>
      <option value="UAH">UAH</option>
      <option value="EUR">EUR</option>
    </select>

    <input id="exchangeRate" type="number" step="0.0001" class="btn" placeholder="Курс до USD" style="width:100%; margin-bottom:15px; display:none;">

    <button id="saveAdvanceBtn" class="btn" style="width:100%; margin-bottom:8px;">Зберегти</button>
    <button id="closeAdvanceBtn" class="btn" style="width:100%; background:#333;">Скасувати</button>

  </div>
</div>

</main>

<script>
document.addEventListener('DOMContentLoaded', function () {

  const formatMoney = (value, currency) => {
    const symbols = {
      UAH: '₴',
      USD: '$',
      EUR: '€'
    };

    const formatted = new Intl.NumberFormat('uk-UA').format(value);
    return `${formatted} ${symbols[currency] ?? currency}`;
  };
  

  fetch('/api/sales-projects')
    .then(r => r.json())
    .then(projects => {

      const container = document.getElementById('projectsContainer');
      container.innerHTML = '';

      projects.forEach(p => {

        const card = document.createElement('div');
        card.className = 'card';
        card.style.marginTop = '15px';
        card.style.cursor = 'pointer';

        const debt = p.remaining_amount;

        card.innerHTML = `
          <div style="display:flex; justify-content:space-between;">
            <div style="font-weight:600;">
              ${p.client_name}
            </div>
            <div>
              ${formatMoney(p.total_amount, p.currency)}
            </div>
          </div>

          <div style="margin-top:5px; font-weight:600; color:${debt > 0 ? '#f20000' : '#3bc97f'};">
            Борг: ${formatMoney(debt, p.currency)}
          </div>

          <div class="project-details" style="display:none; margin-top:15px; border-top:1px solid #ffffff; padding-top:10px;">

            <div style="opacity:.7;">Створено: ${p.created_at}</div>

            <div style="margin-top:8px;">
              Оплачено: ${formatMoney(p.paid_amount, p.currency)}
            </div>

            <div>
              Очікує підтвердження: ${formatMoney(p.pending_amount, p.currency)}
            </div>

            <div style="margin-top:10px; font-weight:600;">Аванси:</div>

${
  p.transfers.length === 0
    ? `<div style="opacity:.6;">Немає авансів</div>`
    : p.transfers.map(t => {

        const convertedInfo =
          (t.currency !== 'USD' && t.exchange_rate)
            ? `
                <div style="font-size:12px; opacity:.7;">
                  ≈ ${formatMoney(t.usd_amount, 'USD')}
                </div>
                <div style="font-size:12px; opacity:.6;">
                  Курс: ${t.exchange_rate}
                </div>
              `
            : '';

        const statusBlock = t.status === 'accepted'
          ? `— ✅ Прийнято`
          : `
              — ⏳ В очікуванні
              <button 
                class="btn accept-advance-btn"
                data-id="${t.id}"
                style="margin-top:6px; width:100%;">
                ✔ Прийняти
              </button>
            `;

        return `
          <div style="margin-top:5px; padding:8px; background:#111; border-radius:6px;">
            <div>
              ${formatMoney(t.amount, t.currency)} ${statusBlock}
            </div>
            <div style="font-size:12px; opacity:.6;">
              ${t.created_at}
            </div>
            ${convertedInfo}
          </div>
        `;

      }).join('')
}

            <div style="margin-top:12px;"> 
              <button class="btn create-advance-btn"  style="width:100%;margin-bottom:34px;" data-id="${p.id}">➕ Створити аванс</button>
              <hr>
              <div style="font-size:16px; font-weight:800; margin-bottom: 14px; text-align:center;margin-top:24px;">Передати кошти</div>
              <button class="btn send-owner-btn" data-project="${p.id}" data-owner="hlushchenko" style="margin-right:5px;">
                💸 Глущенко
              </button>
              <button class="btn send-owner-btn" data-project="${p.id}" data-owner="kolisnyk">
                💸 Колісник
              </button>
            </div>

          </div>
        `;

        card.addEventListener('click', function(e) {
          if (e.target.tagName === 'BUTTON') return;

          const details = card.querySelector('.project-details');
          details.style.display =
            details.style.display === 'none' ? 'block' : 'none';
        });

        container.appendChild(card);
      });

    });

});

// ===== Модалка =====

const modal = document.getElementById('projectModal');

document.getElementById('createProjectBtn').onclick = () => {
  modal.style.display = 'flex';
};

document.getElementById('closeModalBtn').onclick = () => {
  modal.style.display = 'none';
};

document.getElementById('saveProjectBtn').onclick = () => {

  const client_name = document.getElementById('clientName').value;
  const total_amount = document.getElementById('totalAmount').value;
  const currency = document.getElementById('projectCurrency').value;

  fetch('/api/sales-projects', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    },
    body: JSON.stringify({
      client_name,
      total_amount,
      currency
    })
  })
  .then(r => r.json())
  .then(res => {
    if(res.ok){
      modal.style.display = 'none';
      location.reload();
    } else {
      alert(res.error || 'Помилка');
    }
  });

};

const advanceModal = document.getElementById('advanceModal');
const exchangeInput = document.getElementById('exchangeRate');

document.getElementById('advanceCurrency').addEventListener('change', function() {
  if (this.value !== 'USD') {
    exchangeInput.style.display = 'block';
  } else {
    exchangeInput.style.display = 'none';
    exchangeInput.value = '';
  }
});

let currentProjectId = null;

document.addEventListener('click', function(e){

  if(e.target.classList.contains('create-advance-btn')){
    currentProjectId = e.target.dataset.id;
    advanceModal.style.display = 'flex';
  }

});

document.getElementById('closeAdvanceBtn').onclick = () => {
  advanceModal.style.display = 'none';
};

document.getElementById('saveAdvanceBtn').onclick = function(){

  const amount = document.getElementById('advanceAmount').value;
  const currency = document.getElementById('advanceCurrency').value;
  const exchange_rate = document.getElementById('exchangeRate').value;

  fetch(`/api/sales-projects/${currentProjectId}/advance`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    },
    body: JSON.stringify({
      amount,
      currency,
      exchange_rate
    })
  })
  .then(r => r.json())
  .then(res => {

    if(res.ok){
      advanceModal.style.display = 'none';
      location.reload();
    } else {
      alert(res.error || 'Помилка');
    }

  });

};

document.addEventListener('click', function(e){

  if(e.target.classList.contains('accept-advance-btn')){

    const transferId = e.target.dataset.id;

    fetch(`/api/cash-transfers/${transferId}/accept`, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
      }
    })
    .then(r => r.json())
    .then(res => {
      if(res.success){
        location.reload();
      } else {
        alert(res.error || 'Помилка');
      }
    });

  }

});


</script>





  @auth
    @include('partials.nav.bottom-wallet')
  @endauth

</body>
@endsection



