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
    <div class="modal-header">
      <div class="modal-title">Актуальний курс валют</div>
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

  if (typeof window.openStaffCash !== 'function') {
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
        const roleLabels = {
          accountant: 'Бухгалтер',
          foreman: 'Прораб',
          ntv: 'НТВ',
          serviceman_1: 'Савенков',
          serviceman_2: 'Малінін',
        };

        const staffWallets = wallets.filter(w => {
          const owner = w?.owner;
          if (!owner) return false;
          if (ownerActors.includes(owner)) return false;
          return true;
        });

        if (staffWallets.length === 0) {
          list.innerHTML = '<div class="project-history-empty">Кеш рахунків співробітників не знайдено</div>';
        } else {
          list.innerHTML = staffWallets.map(w => `
            <button type="button" class="rate-card" style="width:100%; text-align:left;" onclick="window.openStaffWallet?.(${Number(w.id)})">
              <div style="display:flex;justify-content:space-between;align-items:center;">
                <div class="rate-title">${String(w.name || '')}</div>
                <div class="staff-badge">${String(roleLabels[w.owner] || w.owner || '')}</div>
              </div>
              <div style="margin-top:6px;font-size:16px;font-weight:700;">
                ${Number(w.balance || 0).toFixed(2)} ${String(w.currency || '')}
              </div>
            </button>
          `).join('');
        }

        setStaffModalVisible(true);
      } catch (err) {
        list.innerHTML = `<div class="project-history-empty">${String(err.message || 'Не вдалося завантажити кеш співробітників')}</div>`;
        setStaffModalVisible(true);
      }
    };
  }

  if (typeof window.closeStaffCash !== 'function') {
    window.closeStaffCash = function () {
      setStaffModalVisible(false);
    };
  }

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
