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

  const AUTH_USER = @json(auth()->user());
  const IS_OWNER = AUTH_USER && AUTH_USER.role === 'owner';

  const formatMoney = (value, currency) => {
    const symbols = { UAH: '₴', USD: '$', EUR: '€' };
    const formatted = new Intl.NumberFormat('uk-UA').format(value);
    return `${formatted} ${symbols[currency] ?? currency}`;
  };

  // ✅ FIX: запам'ятовуємо відкриту картку, щоб після reload вона не згорталась
  const OPEN_KEY = 'finance_open_project_id';
  const rememberOpenProject = (id) => localStorage.setItem(OPEN_KEY, String(id));
  const getOpenProject = () => {
    const v = localStorage.getItem(OPEN_KEY);
    return v ? Number(v) : null;
  };
  const openId = getOpenProject();

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

        const transfersHtml = (p.transfers.length === 0)
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

              const canAccept = IS_OWNER && t.target_owner && (t.target_owner === AUTH_USER.actor);

              const statusBlock = t.status === 'accepted'
                ? `— ✅ Прийнято`
                : (
                    canAccept
                      ? `
                          — ⏳ В очікуванні
                          <button 
                            class="btn accept-advance-btn"
                            data-id="${t.id}"
                            style="margin-top:6px; width:100%;">
                            ✔ Прийняти
                          </button>
                        `
                      : `— ⏳ В очікуванні`
                  );

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
          }).join('');

        // блок "Передати кошти" (НТО: або 2 кнопки, або 1 "Відмінити")
        const transferButtonsHtml = (AUTH_USER && AUTH_USER.role !== 'owner' && p.pending_target_owner)
          ? `
              <button 
                class="btn cancel-owner-btn"
                data-project="${p.id}"
                style="width:100%; background:#333;">
                ↩️ Відмінити переказ
              </button>
            `
          : `
              <button class="btn send-owner-btn" data-project="${p.id}" data-owner="hlushchenko" style="margin-right:5px;">
                💸 Глущенко
              </button>
              <button class="btn send-owner-btn" data-project="${p.id}" data-owner="kolisnyk">
                💸 Колісник
              </button>
            `;

        const hasNtoMoney = Number(p.pending_amount || 0) > 0;

          if (hasNtoMoney) {
            card.style.border = '2px solid #f2c200';
          }

        card.innerHTML = `
          <div class="project-toggle" style="display:flex; justify-content:space-between;">
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
            ${transfersHtml}

            ${(AUTH_USER && AUTH_USER.role !== 'owner') ? `
              <hr>
              <div style="font-size:16px; font-weight:800; margin-bottom: 14px; text-align:center;margin-top:24px;">Передати кошти</div>
              ${transferButtonsHtml}
            ` : ``}

          </div>
        `;

        // ✅ відкривати/закривати тільки по кліку на шапку
        card.querySelector('.project-toggle')?.addEventListener('click', function() {
          const details = card.querySelector('.project-details');
          const isOpen = details.style.display !== 'none';

          if (isOpen) {
            details.style.display = 'none';
            localStorage.removeItem(OPEN_KEY);
          } else {
            details.style.display = 'block';
            rememberOpenProject(p.id);
          }
        });

        container.appendChild(card);

        // ✅ після reload залишаємо відкритою потрібну картку
        if (openId && Number(p.id) === openId) {
          const details = card.querySelector('.project-details');
          if (details) details.style.display = 'block';
        }
      });

    });

});

// ===== Модалка проекту =====
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
    body: JSON.stringify({ client_name, total_amount, currency })
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

// ===== Модалка авансу =====
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
    body: JSON.stringify({ amount, currency, exchange_rate })
  })
  .then(r => r.json())
  .then(res => {
    if(res.ok){
      advanceModal.style.display = 'none';
      localStorage.setItem('finance_open_project_id', String(currentProjectId)); // ✅ ключове
      location.reload();
    } else {
      alert(res.error || 'Помилка');
    }
  });

};

// ===== Прийняти аванс =====
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

// ===== НТО: вибір власника =====
document.addEventListener('click', function(e){
  if(!e.target.classList.contains('send-owner-btn')) return;

  const projectId = e.target.dataset.project;
  const owner = e.target.dataset.owner;

  fetch(`/api/sales-projects/${projectId}/target-owner`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    },
    body: JSON.stringify({ target_owner: owner })
  })
  .then(r => r.json())
  .then(res => {
    if(res.ok){
      localStorage.setItem('finance_open_project_id', String(projectId));
      location.reload();
    } else {
      alert(res.error || 'Помилка');
    }
  });
});

// ===== НТО: відмінити переказ =====
document.addEventListener('click', function(e){
  if(!e.target.classList.contains('cancel-owner-btn')) return;

  const projectId = e.target.dataset.project;

  fetch(`/api/sales-projects/${projectId}/target-owner-cancel`, {
    method: 'POST',
    headers: {
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    }
  })
  .then(r => r.json())
  .then(res => {
    if(res.ok){
      localStorage.setItem('finance_open_project_id', String(projectId));
      location.reload();
    } else {
      alert(res.error || 'Помилка');
    }
  });
});
</script>




  @auth
    @include('partials.nav.bottom-wallet')
  @endauth

</body>
@endsection



