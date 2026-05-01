<div id="staffCashModal" class="modal hidden">
  <div class="modal-backdrop" onclick="window.closeStaffCash?.()"></div>

  <div class="modal-panel">
    <div class="modal-handle"></div>

    <div class="modal-header">
      <div class="modal-title modal-cash">Кеш співробітників</div>
      <button type="button" id="staffCashClose" class="modal-close">✕</button>
    </div>

    <div class="modal-body" id="staffCashList"></div>
  </div>
</div>

<div id="ratesModal" class="modal hidden">
  <div class="modal-backdrop"></div>
  <div class="modal-panel">
    <div class="modal-handle"></div>

      <div class="modal-header" style="flex-direction: column; align-items: center; gap: 12px;">
        <div class="modal-title">Актуальний курс валют</div>
        <img src="/img/seyf.png" 
            title="Курси валют оновлюються кожні 30 хвилин. Дані надає НБУ." 
            style="width: 50px; height: 50px; opacity: 0.7; border-radius: 50%; display: block; margin: 15px auto;">
      </div>

    <div id="ratesContent" class="modal-body"></div>
    <div id="exchangeBox" class="exchange hidden">
      <div class="exchange-header">
        <div class="segmented exchange-mode">
          <button id="modeBuy" class="active">Купуємо</button>
          <button id="modeSell">Продаємо</button>
        </div>
      </div>

      <div class="exchange-row">
        <input id="exFrom" type="number" />
        <div id="exFromLabel" class="exchange-currency">UAH</div>
      </div>

      <div class="exchange-row">
        <input id="exTo" type="number" />
        <div id="exToLabel" class="exchange-currency">USD</div>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  if (window.__quickModalsBound) return;
  window.__quickModalsBound = true;

  let fallbackRate = null;
  let fallbackCurrency = null;
  let fallbackMode = 'buy';

  function setRatesModalVisible(open) {
    const modal = document.getElementById('ratesModal');
    if (!modal) return;
    modal.classList.toggle('hidden', !open);
    document.body.classList.toggle('modal-open', open);
  }

  function setStaffModalVisible(open) {
    const modal = document.getElementById('staffCashModal');
    if (!modal) return;
    modal.classList.toggle('hidden', !open);
    document.body.classList.toggle('modal-open', open);
  }

  function fallbackSelectRateCard(card) {
    document.querySelectorAll('#ratesContent .rate-card').forEach(c => c.classList.remove('active'));
    card?.classList.add('active');
  }

  function fallbackSyncExchangeLabels() {
    const fromLabel = document.getElementById('exFromLabel');
    const toLabel = document.getElementById('exToLabel');
    if (!fromLabel || !toLabel || !fallbackCurrency) return;

    if (fallbackMode === 'buy') {
      fromLabel.textContent = 'UAH';
      toLabel.textContent = fallbackCurrency;
    } else {
      fromLabel.textContent = fallbackCurrency;
      toLabel.textContent = 'UAH';
    }
  }

  function fallbackCalcExchange(source) {
    const fromInput = document.getElementById('exFrom');
    const toInput = document.getElementById('exTo');
    if (!fromInput || !toInput || !fallbackRate || !fallbackCurrency) return;

    const a = parseFloat(fromInput.value || 0);
    const b = parseFloat(toInput.value || 0);
    const sale = Number(fallbackRate.sale || 0);
    const buy = Number(fallbackRate.purchase || 0);

    if (!sale || !buy) return;

    if (fallbackMode === 'buy') {
      if (source === 'from') {
        toInput.value = a ? (a / sale).toFixed(2) : '';
      } else {
        fromInput.value = b ? (b * sale).toFixed(2) : '';
      }
      return;
    }

    if (source === 'from') {
      toInput.value = a ? (a * buy).toFixed(2) : '';
    } else {
      fromInput.value = b ? (b / buy).toFixed(2) : '';
    }
  }

  function fallbackSetExchangeMode(mode) {
    fallbackMode = mode === 'sell' ? 'sell' : 'buy';
    document.getElementById('modeBuy')?.classList.toggle('active', fallbackMode === 'buy');
    document.getElementById('modeSell')?.classList.toggle('active', fallbackMode === 'sell');
    fallbackSyncExchangeLabels();
    fallbackCalcExchange('from');
  }

  function fallbackOpenExchange(currency, purchase, sale) {
    fallbackCurrency = String(currency || '').toUpperCase();
    fallbackRate = { purchase: Number(purchase || 0), sale: Number(sale || 0) };

    document.getElementById('exchangeBox')?.classList.remove('hidden');
    document.querySelector('#ratesModal .modal-panel')?.classList.add('expanded');
    fallbackSetExchangeMode(fallbackMode);

    const fromInput = document.getElementById('exFrom');
    const toInput = document.getElementById('exTo');
    if (fromInput) fromInput.value = '';
    if (toInput) toInput.value = '';
  }

  function fallbackRenderRatesModal(data) {
    const modal = document.getElementById('ratesModal');
    const body = document.getElementById('ratesContent');
    if (!modal || !body) return;

    body.innerHTML = `<div style="text-align:center; font-size:18px;font-weight:bold;opacity:.7;margin-bottom:10px">📅 ${data.date}</div>`;

    (data.rates || []).forEach(r => {
      body.innerHTML += `
        <div class="rate-card" data-currency="${String(r.currency)}" data-buy="${Number(r.purchase)}" data-sell="${Number(r.sale)}">
          <div class="rate-title rate-title-${String(r.currency).toLowerCase()}">${String(r.currency)}</div>
          💰 Купівля: <b>${r.purchase ?? '—'}</b><br>
          🏦 Продаж: <b>${r.sale ?? '—'}</b>
        </div>
      `;
    });

    document.getElementById('exchangeBox')?.classList.add('hidden');
    document.querySelector('#ratesModal .modal-panel')?.classList.remove('expanded');
    setRatesModalVisible(true);
  }

  if (typeof window.closeRatesModal !== 'function') {
    window.closeRatesModal = function () {
      setRatesModalVisible(false);
    };
  }

  if (typeof window.openRatesModalFlow !== 'function') {
    window.openRatesModalFlow = async function (e) {
      e?.preventDefault?.();

      try {
        const res = await fetch('/api/fx/rates', { headers: { 'Accept': 'application/json' } });
        const data = await res.json();
        if (!res.ok || data.error) {
          const body = document.getElementById('ratesContent');
          if (body) body.innerHTML = '<div style="color:#ff6b6b">Не вдалося отримати курс валют</div>';
          setRatesModalVisible(true);
          return false;
        }

        fallbackRenderRatesModal(data);
        return true;
      } catch {
        const body = document.getElementById('ratesContent');
        if (body) body.innerHTML = '<div style="color:#ff6b6b">Помилка при отриманні курсу валют</div>';
        setRatesModalVisible(true);
        return false;
      }
    };
  }

  window.openStaffCash = async function () {
      const list = document.getElementById('staffCashList');
      if (!list) return;

      list.innerHTML = '<div class="project-history-loading">Завантаження...</div>';

      try {
        const res = await fetch('/api/wallets', { headers: { 'Accept': 'application/json' } });
        const wallets = await res.json();
        if (!res.ok || !Array.isArray(wallets)) {
          throw new Error('Не вдалося завантажити кеш співробітників');
        }

        const ownerActors = ['hlushchenko', 'kolisnyk'];

        const groups = [
          {
            label: '🏗 Прораб',
            actors: ['foreman'],
            names: { foreman: 'Оніпко' },
          },
          {
            label: '🔧 Монтажники',
            actors: ['kryzhanovskyi', 'kukuiaka', 'shevchenko', 'samoilenko'],
            names: { kryzhanovskyi: 'Крижановський', kukuiaka: 'Кукуяка', shevchenko: 'Шевченко', samoilenko: 'Самойленко' },
          },
          {
            label: '⚡ Електрики',
            actors: ['serviceman_1', 'serviceman_2'],
            names: { serviceman_1: 'Савенков', serviceman_2: 'Малінін' },
          },
          {
            label: '🏢 НТВ',
            actors: ['ntv'],
            names: { ntv: 'НТВ' },
          },
          {
            label: '📈 Менеджери',
            actors: ['shkarban', 'zelenko', 'vdovenko'],
            names: { shkarban: 'Шкарбан', zelenko: 'Зеленько', vdovenko: 'Вдовенко' },
          },
          {
            label: '🧾 Бухгалтер',
            actors: ['accountant'],
            names: { accountant: 'Бухгалтер' },
          },
        ];

        const currencySymbol = { UAH: '₴', USD: '$', EUR: '€' };

        const staffWallets = wallets.filter(w => w?.owner && !ownerActors.includes(w.owner));

        if (staffWallets.length === 0) {
          list.innerHTML = '<div class="project-history-empty">Кеш рахунків співробітників не знайдено</div>';
          setStaffModalVisible(true);
          return;
        }

        // Index wallets by owner
        const byOwner = {};
        staffWallets.forEach(w => {
          if (!byOwner[w.owner]) byOwner[w.owner] = [];
          byOwner[w.owner].push(w);
        });

        let html = '<div style="display:flex;flex-direction:column;gap:20px;padding-bottom:12px;">';

        groups.forEach(group => {
          const groupWallets = group.actors.flatMap(a => byOwner[a] || []);
          if (groupWallets.length === 0) return;

          html += `<div>
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;opacity:.45;margin-bottom:8px;padding:0 2px;">${group.label}</div>
            <div style="display:flex;flex-direction:column;gap:6px;">`;

          // Group by actor (person)
          group.actors.forEach(actor => {
            const personWallets = byOwner[actor] || [];
            if (personWallets.length === 0) return;

            const personName = group.names[actor] || actor;

            // Sort: UAH first, then USD, EUR
            const order = ['UAH', 'USD', 'EUR'];
            personWallets.sort((a, b) => (order.indexOf(a.currency) - order.indexOf(b.currency)));

            const currencyBadges = personWallets.map(w => {
              const bal = Number(w.balance || 0);
              const sym = currencySymbol[w.currency] || w.currency;
              const color = bal < 0 ? '#ff6b6b' : bal === 0 ? 'rgba(255,255,255,.35)' : '#4ade80';
              return `<button type="button"
                onclick="window.openStaffWallet?.(${Number(w.id)})"
                style="background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);border-radius:8px;
                       padding:5px 10px;cursor:pointer;text-align:center;min-width:72px;flex-shrink:0;">
                <div style="font-size:10px;opacity:.5;font-weight:600;letter-spacing:.04em;">${String(w.currency)}</div>
                <div style="font-size:14px;font-weight:700;color:${color};">${sym}${Math.abs(bal).toLocaleString('uk-UA', {minimumFractionDigits:2, maximumFractionDigits:2})}</div>
              </button>`;
            }).join('');

            html += `<div style="display:flex;align-items:center;justify-content:space-between;
                                  background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);
                                  border-radius:12px;padding:10px 14px;gap:10px;">
              <div style="font-weight:600;font-size:14px;white-space:nowrap;">${personName}</div>
              <div style="display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end;">${currencyBadges}</div>
            </div>`;
          });

          html += `</div></div>`;
        });

        html += '</div>';
        list.innerHTML = html;

        setStaffModalVisible(true);
      } catch (err) {
        list.innerHTML = `<div class="project-history-empty">${String(err.message || 'Не вдалося завантажити кеш співробітників')}</div>`;
        setStaffModalVisible(true);
      }
    };

  window.closeStaffCash = function () {
    setStaffModalVisible(false);
  };

  if (typeof window.openStaffWallet !== 'function') {
    window.openStaffWallet = function (walletId) {
      window.closeStaffCash?.();
      window.location.href = `/?open_staff_wallet=${encodeURIComponent(String(walletId))}`;
    };
  }

  if (typeof window.selectRateCard !== 'function') {
    window.selectRateCard = fallbackSelectRateCard;
  }

  if (typeof window.openExchange !== 'function') {
    window.openExchange = fallbackOpenExchange;
  }

  document.addEventListener('click', (e) => {
    const target = e.target instanceof Element ? e.target : null;
    if (!target) return;

    if (target.closest('#staffCashClose')) {
      window.closeStaffCash?.();
      return;
    }

    if (target.classList.contains('modal-backdrop')) {
      const modal = target.closest('.modal');
      if (modal?.id === 'ratesModal') window.closeRatesModal?.();
      if (modal?.id === 'staffCashModal') window.closeStaffCash?.();
      return;
    }

    const rateCard = target.closest('#ratesContent .rate-card');
    if (rateCard && typeof window.openExchange === 'function') {
      window.selectRateCard?.(rateCard);
      window.openExchange(
        rateCard.dataset.currency,
        Number(rateCard.dataset.buy),
        Number(rateCard.dataset.sell)
      );
    }
  });

  document.addEventListener('input', (e) => {
    const target = e.target instanceof Element ? e.target : null;
    if (!target) return;
    if (target.id === 'exFrom') fallbackCalcExchange('from');
    if (target.id === 'exTo') fallbackCalcExchange('to');
  });

  document.addEventListener('click', (e) => {
    const target = e.target instanceof Element ? e.target : null;
    if (!target) return;
    if (target.id === 'modeBuy') fallbackSetExchangeMode('buy');
    if (target.id === 'modeSell') fallbackSetExchangeMode('sell');
  });
})();
</script>
