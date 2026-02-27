@extends('layouts.app')

@push('styles')
  <link rel="stylesheet" href="/css/nav-telegram.css?v={{ filemtime(public_path('css/nav-telegram.css')) }}">
@endpush

@section('content')
<body class="{{ auth()->check() ? 'has-tg-nav' : '' }}">



<main class="">


  <div class="card">
    <div>

      <button id="createProjectBtn" class="btn" style="align-items:center;width: 100%;background:rgba(84, 192, 134, 0.71); margin-bottom:0;">➕ Новий проект</button>
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
  const OPEN_PAID_KEY = 'finance_open_paid_projects';
  const rememberOpenProject = (id) => localStorage.setItem(OPEN_KEY, String(id));
  const getOpenProject = () => {
    const v = localStorage.getItem(OPEN_KEY);
    return v ? Number(v) : null;
  };
  const POLL_MS = 4000;
  let isProjectsLoading = false;
  let projectsPollTimer = null;

  function renderProjects(projects) {
    const container = document.getElementById('projectsContainer');
    if (!container) return;

    const openId = getOpenProject();
    const isPaidOpen = localStorage.getItem(OPEN_PAID_KEY) === '1';
    container.innerHTML = '';

    const byName = (a, b) => String(a.client_name || '').localeCompare(String(b.client_name || ''), 'uk', { sensitivity: 'base' });
    const activeProjects = (projects || [])
      .filter(p => Number(p.remaining_amount || 0) > 0)
      .sort(byName);
    const paidProjects = (projects || [])
      .filter(p => Number(p.remaining_amount || 0) <= 0)
      .sort(byName);

    function buildProjectCard(p) {
      const card = document.createElement('div');
      card.className = 'card';
      card.style.marginTop = '15px';
      card.style.cursor = 'pointer';

      const debt = p.remaining_amount;

      const transfersHtml = (p.transfers.length === 0)
        ? `<div style="opacity:.6;">Немає авансів</div>`
        : p.transfers.map(t => {
            const convertedInfo =
              (t.currency !== p.currency && t.exchange_rate)
                ? `
                    <div style="font-size:12px; opacity:.7;">
                      ≈ ${formatMoney(t.project_amount ?? t.usd_amount, p.currency)}
                    </div>
                    <div style="font-size:12px; opacity:.6;">
                      Курс: ${t.exchange_rate}
                    </div>
                  `
                : '';

            const canAccept = IS_OWNER && t.target_owner && (t.target_owner === AUTH_USER.actor);
            const today = new Date();
            const todayFormatted = today.toLocaleDateString('uk-UA');
            const isToday = t.created_at.startsWith(todayFormatted);

            const statusBlock = t.status === 'accepted'
              ? `— ✅ Прийнято`
              : `
                  — ⏳ В очікуванні
                  ${canAccept ? `
                    <button 
                      class="btn accept-advance-btn"
                      data-id="${t.id}"
                      style="margin-top:6px; width:100%;">
                      ✔ Прийняти
                    </button>
                  ` : ''}
                  ${isToday ? `
                      <button 
                        class="btn edit-advance-btn hidden-edit-btn"
                        data-id="${t.id}"
                        data-amount="${t.amount}"
                        style="margin-top:6px; width:100%; background:#333; display:none;">
                        ✏️ Редагувати
                      </button>
                  ` : ''} 
                `;

            return `
              <div 
                class="advance-card"
                data-transfer-id="${t.id}"
                style="margin-top:5px; padding:8px; background:#111; border-radius:6px; cursor:pointer;">
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
            
            ${(AUTH_USER && (AUTH_USER.role === 'ntv' || AUTH_USER.role === 'owner')) ? `
            <div style="margin-top:12px;">
              <button class="btn create-advance-btn" style="width:100%;" data-id="${p.id}" data-currency="${p.currency}">
                ➕ Створити аванс
              </button>
            </div>
          ` : ``}

            <div style="margin-top:10px; font-weight:600;">Аванси:</div>
            ${transfersHtml}

            ${(AUTH_USER && AUTH_USER.role !== 'owner') ? `
              <hr>
              <div style="font-size:16px; font-weight:800; margin-bottom: 14px; text-align:center;margin-top:24px;">Передати кошти</div>
              ${transferButtonsHtml}
            ` : ``}

          </div>
        `;

      card.addEventListener('click', function(e) {
        if (e.target.closest('button, input, select, textarea, a, label')) return;

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

      if (openId && Number(p.id) === openId) {
        const details = card.querySelector('.project-details');
        if (details) details.style.display = 'block';
      }

      return card;
    }

    if (paidProjects.length > 0) {
      const shouldOpenPaid = isPaidOpen || !!paidProjects.find(p => Number(p.id) === openId);

      const paidCard = document.createElement('div');
      paidCard.className = 'card';
      paidCard.style.marginTop = '15px';
      paidCard.innerHTML = `
        <div class="paid-projects-toggle" style="display:flex; justify-content:space-between; align-items:center; cursor:pointer;">
          <div style="font-weight:700;">✅ Оплачені</div>
          <div style="opacity:.75;">${paidProjects.length}</div>
        </div>
        <div class="paid-projects-details" style="display:${shouldOpenPaid ? 'block' : 'none'}; margin-top:12px; border-top:1px solid #ffffff; padding-top:10px;"></div>
      `;

      const paidDetails = paidCard.querySelector('.paid-projects-details');
      paidProjects.forEach(p => paidDetails.appendChild(buildProjectCard(p)));

      paidCard.querySelector('.paid-projects-toggle')?.addEventListener('click', function () {
        const details = paidCard.querySelector('.paid-projects-details');
        const nextOpen = details.style.display === 'none';
        details.style.display = nextOpen ? 'block' : 'none';
        localStorage.setItem(OPEN_PAID_KEY, nextOpen ? '1' : '0');
      });

      container.appendChild(paidCard);
    }

    activeProjects.forEach(p => {
      container.appendChild(buildProjectCard(p));
    });

    if (activeProjects.length === 0) {
      const emptyCard = document.createElement('div');
      emptyCard.className = 'card';
      emptyCard.style.marginTop = '15px';
      emptyCard.innerHTML = `<div style="opacity:.75; text-align:center;">Немає активних проектів</div>`;
      container.appendChild(emptyCard);
    }
  }

  function loadProjects(opts = {}) {
    const { silent = false } = opts;
    if (isProjectsLoading) return;
    isProjectsLoading = true;

    return fetch('/api/sales-projects')
      .then(r => r.json())
      .then(projects => {
        renderProjects(projects);
      })
      .catch(err => {
        if (!silent) console.warn('Projects refresh error:', err);
      })
      .finally(() => {
        isProjectsLoading = false;
      });
  }

  window.refreshSalesProjects = () => loadProjects({ silent: false });

  loadProjects();
  projectsPollTimer = window.setInterval(() => loadProjects({ silent: true }), POLL_MS);
  window.addEventListener('beforeunload', () => {
    if (projectsPollTimer) window.clearInterval(projectsPollTimer);
  });

});

// ===== Toggle кнопки редагування при кліку на аванс =====
// document.addEventListener('click', function(e){
//
//   const advanceCard = e.target.closest('.advance-card');
//   if(!advanceCard) return;
//
//   const editBtn = advanceCard.querySelector('.edit-advance-btn');
//   if(!editBtn) return;
//
//   const isVisible = editBtn.style.display === 'block';
//
//   document.querySelectorAll('.edit-advance-btn').forEach(b => {
//     b.style.display = 'none';
//   });
//
//   if(!isVisible){
//     editBtn.style.display = 'block';
//   }
//
// });

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
      window.refreshSalesProjects?.();
    } else {
      alert(res.error || 'Помилка');
    }
  });

};



// ===== Модалка авансу =====
const advanceModal = document.getElementById('advanceModal');
const exchangeInput = document.getElementById('exchangeRate');

let currentProjectId = null;

document.addEventListener('click', function (e) {
  const btn = e.target.closest('.create-advance-btn'); // ✅ ВАЖЛИВО: closest()
  if (!btn) return;

  currentProjectId = btn.dataset.id;

  const pCur = String(btn.dataset.currency || 'USD').toUpperCase();

  // reset полів
  document.getElementById('advanceAmount').value = '';
  document.getElementById('exchangeRate').value = '';

  // ✅ валюта авансу по дефолту = валюті проекту
  const advCurEl = document.getElementById('advanceCurrency');
  advCurEl.value = pCur;

  // ✅ встановлюємо валюту проекту ТИХО (без алертів)
  window.setAdvanceProjectCurrency?.(pCur, { silent: true });

  advanceModal.style.display = 'flex';
});

document.getElementById('closeAdvanceBtn').onclick = () => {
  advanceModal.style.display = 'none';
};


// ===== Прямий аванс в гаманець власника аванс =====
document.getElementById('saveAdvanceBtn').onclick = function(){
  if (!window.validateAdvanceFx()) return;


  const amount = document.getElementById('advanceAmount').value;
  const currency = document.getElementById('advanceCurrency').value;
  const projectCurrency = String(window.ADV_PROJECT_CURRENCY || 'USD').toUpperCase();
  const exchange_rate = (String(currency).toUpperCase() === projectCurrency)
    ? 1
    : document.getElementById('exchangeRate').value;

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
      window.refreshSalesProjects?.();
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
        window.refreshSalesProjects?.();
      } else {
        alert(res.error || 'Помилка');
      }
    });

  }

});
// ===== Редагувати аванс =====
document.addEventListener('click', function(e){

  if(!e.target.classList.contains('edit-advance-btn')) return;

  const transferId = e.target.dataset.id;
  const currentAmount = e.target.dataset.amount;

  const newAmount = prompt('Нова сума авансу:', currentAmount);

  if(!newAmount) return;

  fetch(`/api/cash-transfers/${transferId}`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    },
    body: JSON.stringify({
      amount: newAmount
    })
  })
  .then(r => r.json())
  .then(res => {
    if(res.success){
      window.refreshSalesProjects?.();
    } else {
      alert(res.error || 'Помилка');
    }
  });

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
      window.refreshSalesProjects?.();
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
      window.refreshSalesProjects?.();
    } else {
      alert(res.error || 'Помилка');
    }
  });
});


(() => {
  ///////////////////////////////////////////////////////////////
  // helpers
  ///////////////////////////////////////////////////////////////

  const PALETTE = ['#66f2a8', '#4c7dff', '#ffb86c', '#ff6b6b', '#9aa6bc'];

  function fmt0(n) {
    const num = Number(String(n ?? 0).replace(',', '.')) || 0;
    return new Intl.NumberFormat('uk-UA', { maximumFractionDigits: 0 })
      .format(Math.round(num))
      .replace(/\u00A0/g, ' ');
  }

  function ensureChartJs() {
    return new Promise((resolve, reject) => {
      if (window.Chart) return resolve();

      const s = document.createElement('script');
      s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
      s.onload = () => resolve();
      s.onerror = () => reject(new Error('Не вдалося завантажити Chart.js'));
      document.head.appendChild(s);
    });
  }

  function getCanvasCtx(id) {
    const c = document.getElementById(id);
    return c ? c.getContext('2d') : null;
  }

  ///////////////////////////////////////////////////////////////
  // UI
  ///////////////////////////////////////////////////////////////

  function makeSalesCard() {
    if (document.getElementById('salesChartsCard')) return;

    const main = document.querySelector('main');
    if (!main) return;

    const card = document.createElement('div');
    card.className = 'card';
    card.id = 'salesChartsCard';
    card.style.marginBottom = '15px';
    card.style.listStyle = 'none';
    card.style.cursor = 'pointer';
    card.style.padding = '18px 18px 16px';
    card.style.borderRadius = '22px';
    card.style.background = 'radial-gradient(120% 180% at 50% 0%, rgba(102, 242, 168, .22) 0%, rgba(255, 255, 255, .08) 35%, rgba(255, 255, 255, .05) 70%, rgba(255, 255, 255, .04) 100%)';
    card.style.border = '1px solid rgba(255, 255, 255, .10)';
    card.style.boxShadow = '0 18px 48px rgba(0, 0, 0, .42), inset 0 1px 0 rgba(255, 255, 255, .12)';
    card.style.backdropFilter = 'blur(18px)';
    // ✅ прибрати маркер summary (трикутник)
    const st = document.createElement('style');
    st.textContent = `
      #salesChartsDetails > summary { list-style: none; }
      #salesChartsDetails > summary::-webkit-details-marker { display: none; }
      #salesChartsDetails > summary::marker { content: ""; }
    `;
    document.head.appendChild(st);

    card.innerHTML = `
      <details id="salesChartsDetails">
        <summary style="cursor:pointer;">
          <div style="display:flex; align-items:center; justify-content:space-between; gap:10px;">
            <div style="font-weight:800;font-size: 22px;
              font-weight: 800;
              letter-spacing: .2px;
              opacity: .95;">SG Holding</div>
            <div style="display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 6px 12px;
                border-radius: 999px;
                font-weight: 800;
                font-size: 13px;
                color: #0b0d10;
                background: rgba(102, 242, 168, .95);
                box-shadow: 0 6px 16px rgba(0, 0, 0, .25);">
              USD
            </div>
          </div>

          <div style="margin-top:10px; font-size:28px; font-weight:900; letter-spacing:.3px;text-align:center;">
            <span id="salesTotalVal">0</span> $
          </div>

          <div style="margin-top:10px; display:flex; gap:10px;">
            <div class="btn" style="flex:1; text-align:center; padding:10px 8px; background:rgba(255, 255, 255, 0.1); border:1px solid rgba(255, 255, 255, 0.25);">
              <div style="opacity:.85; font-weight:700; font-size:12px;">✅ Авансовано</div>
              <div style="font-weight:900; margin-top:4px;"><span id="salesAdvVal">0</span> $</div>
            </div>

            <div class="btn" style="flex:1; text-align:center; padding:10px 8px; background:rgba(255, 255, 255, 0.1); border:1px solid rgba(255, 255, 255, 0.25);">
              <div style="opacity:.85; font-weight:700; font-size:12px;">🟡 Залишок</div>
              <div style="font-weight:900; margin-top:4px;"><span id="salesRemVal">0</span> $</div>
            </div>
          </div>

        </summary>

        <div style="margin-top:12px;">
          <div class="card" style="margin-top:10px;">
            <div style="font-weight:800; text-align:center; margin-bottom:10px;">Загальний бюджет / авансовано / залишок</div>

            <div style="height:220px;">
              <canvas id="pieSales"></canvas>
            </div>

            <div id="barsSales" style="margin-top:10px;"></div>
          </div>
        </div>
      </details>
    `;

      // ✅ статистика завжди найперша в main
      main.insertAdjacentElement('afterbegin', card);
  }

  ///////////////////////////////////////////////////////////////
  // Bars (wallet-like)
  ///////////////////////////////////////////////////////////////

  function renderBars(el, labels, values) {
    if (!el) return;

    const rows = labels.map((label, i) => ({
      label,
      value: Number(values[i] || 0),
    }));

    const total = rows.reduce((s, r) => s + r.value, 0) || 1;

    el.innerHTML = '';

    rows.forEach((r, idx) => {
      const pct = Math.round((r.value / total) * 100);
      const color = PALETTE[idx % PALETTE.length];

      el.insertAdjacentHTML('beforeend', `
        <div style="display:flex; align-items:center; gap:10px; padding:10px 4px; border-bottom:1px solid rgba(255,255,255,.06);">
          <div style="flex:1; min-width:0;">
            <div style="font-weight:800; font-size:14px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
              ${r.label}
            </div>
            <div style="margin-top:6px; height:10px; border-radius:999px; background:rgba(255,255,255,.08); overflow:hidden;">
              <div style="height:100%; width:${pct}%; background:${color};"></div>
            </div>
          </div>

          <div style="text-align:right; min-width:88px;">
            <div style="font-weight:900;">${pct}%</div>
            <div style="opacity:.7; font-size:12px;">${fmt0(r.value)} $</div>
          </div>
        </div>
      `);
    });

    const last = el.lastElementChild;
    if (last) last.style.borderBottom = 'none';
  }

  ///////////////////////////////////////////////////////////////
  // Chart
  ///////////////////////////////////////////////////////////////

  let chartSales = null;

  function upsertPie(labels, data) {
    const ctx = getCanvasCtx('pieSales');
    if (!ctx) return;

    const colors = labels.map((_, i) => PALETTE[i % PALETTE.length]);

    if (chartSales) {
      chartSales.data.labels = labels;
      chartSales.data.datasets[0].data = data;
      chartSales.data.datasets[0].backgroundColor = colors;
      chartSales.update();
      return;
    }

    chartSales = new Chart(ctx, {
      type: 'pie',
      data: {
        labels,
        datasets: [{
          data,
          backgroundColor: colors,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: true,
            position: 'bottom',
            labels: {
              color: '#e9eef6',
              boxWidth: 10,
              boxHeight: 10,
              padding: 12,
              font: { size: 12, weight: '600' }
            }
          },
          tooltip: {
            callbacks: {
              label: (item) => `${item.label}: ${fmt0(Number(item.raw || 0))} $`
            }
          }
        }
      }
    });
  }

  ///////////////////////////////////////////////////////////////
  // Data
  ///////////////////////////////////////////////////////////////

  async function loadAndRenderSales() {
    const res = await fetch('/api/sales-projects', { credentials: 'same-origin' });
    if (!res.ok) throw new Error(`GET /api/sales-projects failed (${res.status})`);
    const projects = await res.json();

    // ✅ беремо тільки USD (як у твоєму борговому блоці USD-пілл)
    const usd = Array.isArray(projects) ? projects.filter(p => p.currency === 'USD') : [];

    const totalBudget = usd.reduce((s, p) => s + Number(p.total_amount || 0), 0);

    // "Авансовано" = прийнято + очікує (бо це вже внесені суми по проекту, просто частина ще в НТО)
    const advanced = usd.reduce((s, p) => s + Number(p.paid_amount || 0) + Number(p.pending_amount || 0), 0);

    const remaining = Math.max(0, totalBudget - advanced);

    const totalEl = document.getElementById('salesTotalVal');
    const advEl   = document.getElementById('salesAdvVal');
    const remEl   = document.getElementById('salesRemVal');

    if (totalEl) totalEl.innerText = fmt0(totalBudget);
    if (advEl)   advEl.innerText   = fmt0(advanced);
    if (remEl)   remEl.innerText   = fmt0(remaining);

    const labels = ['✅ Авансовано', '🟡 Залишок'];
    const data   = [advanced, remaining];

    upsertPie(labels, data);
    renderBars(document.getElementById('barsSales'), labels, data);
  }

  async function boot() {
    makeSalesCard();
    await ensureChartJs();
    await loadAndRenderSales();
    setInterval(() => loadAndRenderSales().catch(() => {}), 15000);
  }

  document.addEventListener('DOMContentLoaded', () => {
    boot().catch((e) => console.warn('Sales charts:', e.message));
  });
})();



// ===============================
// ADVANCE FX UI GUARD
// - валюта проекту береться з window.ADV_PROJECT_CURRENCY
// - алерти ТІЛЬКИ коли юзер руками змінив валюту авансу
// ===============================
(function () {
  const advanceCurrencyEl = document.getElementById('advanceCurrency');
  const exchangeRateEl    = document.getElementById('exchangeRate');

  if (!advanceCurrencyEl || !exchangeRateEl) return;

  // глобальний стан
  window.ADV_PROJECT_CURRENCY = String(window.ADV_PROJECT_CURRENCY || 'USD').toUpperCase();

  let userChanged = false;  // true тільки після ручної зміни select
  let alertKey = null;      // антиспам

  function showRateField(placeholderText) {
    exchangeRateEl.style.display = '';
    exchangeRateEl.required = true;
    exchangeRateEl.placeholder = placeholderText || 'Курс';
  }

  function hideRateField() {
    exchangeRateEl.style.display = 'none';
    exchangeRateEl.required = false;
    exchangeRateEl.value = '';
    exchangeRateEl.placeholder = '';
  }

  function alertIfUser(text, key) {
    if (!userChanged) return;          // ✅ тільки після ручної зміни
    if (key && alertKey === key) return;
    alertKey = key || null;
    alert(text);
  }

  function syncAdvanceFxUI() {
    const pCur = String(window.ADV_PROJECT_CURRENCY || 'USD').toUpperCase();
    const aCur = String(advanceCurrencyEl.value || 'USD').toUpperCase();

    // якщо валюта авансу = валюта проєкту -> курс не потрібен
    if (aCur === pCur) {
      hideRateField();
      return;
    }

    // різні валюти -> показуємо поле з підказкою по напрямку
    if (aCur === 'EUR' && pCur === 'USD') {
      alertIfUser(
        "⚠️ АВАНС У EUR, ПРОЄКТ У USD.\n" +
        "Потрібен крос-курс EUR→USD.\n" +
        "Приклад: 1 EUR → 1.12 USD.",
        'eur_usd'
      );
      showRateField('Крос курс EUR→USD (1 -> 1.12)');
      return;
    }

    if (aCur === 'UAH' && pCur === 'USD') {
      showRateField('Курс USD→UAH (1 -> 43.50)');
      return;
    }

    if (aCur === 'USD' && pCur === 'UAH') {
      showRateField('Курс USD→UAH (1 -> 43.50)');
      return;
    }

    if (aCur === 'EUR' && pCur === 'UAH') {
      showRateField('Курс EUR→UAH (1 -> 45.00)');
      return;
    }

    if (aCur === 'USD' && pCur === 'EUR') {
      showRateField('Крос курс USD→EUR (1 -> 0.89)');
      return;
    }

    if (aCur === 'UAH' && pCur === 'EUR') {
      showRateField('Курс EUR→UAH (1 -> 45.00)');
      return;
    }

    showRateField(`Курс ${aCur}→${pCur}`);
  }

  // доступ зовні
  window.syncAdvanceFxUI = syncAdvanceFxUI;

  // ✅ ця функція має бути "тиха" при відкритті модалки
  window.setAdvanceProjectCurrency = function (cur, opts = {}) {
    window.ADV_PROJECT_CURRENCY = String(cur || 'USD').toUpperCase();

    if (opts.silent) {
      userChanged = false;
      alertKey = null;
    }

    syncAdvanceFxUI();
  };

  // init тихо
  userChanged = false;
  alertKey = null;
  syncAdvanceFxUI();

  // ✅ алерти тільки при ручній зміні валюти авансу
  advanceCurrencyEl.addEventListener('change', () => {
    userChanged = true;
    alertKey = null;
    syncAdvanceFxUI();
  });

  // ✅ валідація перед збереженням (без алертів тут, тільки блок)
  window.validateAdvanceFx = function () {
    const pCur = String(window.ADV_PROJECT_CURRENCY || 'USD').toUpperCase();
    const aCur = String(advanceCurrencyEl.value || 'USD').toUpperCase();

    if (aCur === pCur) return true;

    const rate = Number(String(exchangeRateEl.value || '').replace(',', '.'));
    if (!rate || rate <= 0) {
      alert('Введи крос-курс');
      exchangeRateEl.focus();
      return false;
    }

    // для проєкту в UAH очікуємо звичайний курс (USD→UAH / EUR→UAH), тобто > 1
    if (pCur === 'UAH' && (aCur === 'USD' || aCur === 'EUR') && rate < 1) {
      alert('❌ Для проєкту в UAH введи звичайний курс, наприклад 43.50 або 45.00.');
      exchangeRateEl.focus();
      return false;
    }

    // швидкий захист від "53" там де має бути ~1.xx
    if (pCur === 'USD' && aCur === 'EUR' && rate >= 10) {
      alert('❌ Для EUR→USD має бути ~1.xx (типу 1.12), не 40/53.');
      exchangeRateEl.focus();
      return false;
    }

    return true;
  };
})();
</script>




@include('partials.nav.bottom')

</body>
@endsection
