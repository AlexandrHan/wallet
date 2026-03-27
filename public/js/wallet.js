
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const FX_CACHE_TTL_MS = 60 * 1000;
  // ===== BANK TRANSACTIONS (temporary, test data) =====


const AUTH_USER = window.AUTH_USER;

document.addEventListener('DOMContentLoaded', () => {
  if (AUTH_USER.role === 'owner') {
    document.getElementById('staffCashBtn')?.classList.remove('hidden');
  }
});

const AUTH_ACTOR = AUTH_USER.actor; // ← ПОВЕРНУЛИ

if (!['accountant', 'manager'].includes(AUTH_USER.role) && !AUTH_ACTOR) {
    alert('Не задано actor для користувача...');
}

  const _actorTagEl = document.getElementById('actorTag');
  if (_actorTagEl) _actorTagEl.textContent = AUTH_ACTOR;

  const state = {
    actor: AUTH_ACTOR,
    viewOwner: AUTH_ACTOR,
    wallets: [],
    bankAccounts: [],
    selectedWalletId: null,
    selectedWallet: null,
    entries: [],
    activeEntryId: null,
    pendingReceiptFile: null,
    pendingReceiptUrl: null,

    fxLoading: false,
    fx: null,
    holdingCurrency: 'UAH',
    holdingOps: null,
    entrySubmitting: false,
    entryIdemKey: null,


    delArmedId: null,
    delTimer: null,
    pendingOpenStaffWalletId: null,
  };

const BOOT_QUERY = new URLSearchParams(window.location.search);
const pendingOpenStaffWalletId = Number(BOOT_QUERY.get('open_staff_wallet') || 0);
if (pendingOpenStaffWalletId > 0) {
  state.pendingOpenStaffWalletId = pendingOpenStaffWalletId;
}


let isRenderingWallets = false;
let isLoadingWallets = false;

// =======================
// Lazy load Chart.js (ONE implementation)
// Завантажуємо Chart.js тільки коли реально треба
// =======================
const _scriptOnce = new Map();

/** Завантажує зовнішній script рівно 1 раз. */
function loadScriptOnce(src){
  if (_scriptOnce.has(src)) return _scriptOnce.get(src);

  const p = new Promise((resolve, reject) => {
    const s = document.createElement('script');
    s.src = src;
    s.async = true; // для динамічного скрипта це ок
    s.onload = () => resolve(true);
    s.onerror = () => reject(new Error('Failed to load: ' + src));
    document.head.appendChild(s);
  });

  _scriptOnce.set(src, p);
  return p;
}

/** true якщо Chart.js доступний (window.Chart існує) */
async function ensureChartJs(){
  if (window.Chart) return true;

  try{
    await loadScriptOnce('https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js');
    return !!window.Chart;
  }catch(e){
    console.error(e);
    return false;
  }
}

// =======================
// FX refresh: не дублюємо online listener
// =======================
let _onlineFxBound = false;

function bindOnlineFxRefreshOnce(){
  if (_onlineFxBound) return;
  _onlineFxBound = true;

  window.addEventListener('online', async () => {
    await loadFx(true);
    updateHoldingCardTotalsUI();
    renderHoldingStatsUI?.();
    renderHoldingAccountsStatsUI?.();
  });
}


/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

async function loadBankTransactions() {
  const res = await fetch('/api/bank/transactions');
  if (!res.ok) {
    console.error('Bank transactions fetch failed');
    return [];
  }
  return await res.json();
}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

function checkOnline() {
  if (navigator.onLine) return true;
  alert('❌ Немає інтернету. Операції тимчасово недоступні.');
  return false;
}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  // DOM
  const walletsView = document.getElementById('walletsView');
  const opsView = document.getElementById('opsView');
  // ===== STATS UI =====
  const btnToggleStats = document.getElementById('toggleStats');
  const elStatsBox     = document.getElementById('statsBox');
  const ctxChart = document.getElementById('catChart')?.getContext('2d');
  const sheetEntry = document.getElementById('sheetEntry');



  let catChartInstance = null;

    btnToggleStats.onclick = async () => {
    elStatsBox.classList.toggle('hidden');

    if (!elStatsBox.classList.contains('hidden')) {
        const ok = await ensureChartJs();
        if (!ok) return;

        setTimeout(() => {
        renderCategoryChart();
        }, 60);
    }
    };




  const sheetCategory = document.getElementById('sheetCategory');


  const elWallets = document.getElementById('wallets');
  const elEntries = document.getElementById('entries');
  const elWalletTitle = document.getElementById('walletTitle');
  const elWalletBalance = document.getElementById('walletBalance');
  const elSummary      = document.getElementById('entriesSummary');
  const elSumTotal     = document.getElementById('sumTotal');
  const elSumCount     = document.getElementById('sumCount');
  const elSumAvg       = document.getElementById('sumAvg');
  const elCatBox  = document.getElementById('categoryStats');
  const elCatList = document.getElementById('catList');



  const roTag = document.getElementById('roTag');
  const viewHint = document.getElementById('viewHint');

  const btnIncome = document.getElementById('addIncome');
  const btnExpense = document.getElementById('addExpense');
  const btnBack = document.getElementById('backToWallets');

  const btnViewK = document.getElementById('view-k');
  const btnViewH = document.getElementById('view-h');
  if (AUTH_USER.role !== 'owner') {
  btnViewK?.classList.add('hidden');
  btnViewH?.classList.add('hidden');
}

  const IS_ACCOUNTANT = AUTH_USER.role === 'accountant';


  const btnAddWallet = document.getElementById('addWallet');

  // Sheet entry

  const sheetEntryTitle = document.getElementById('sheetEntryTitle');
  const sheetAmount = document.getElementById('sheetAmount');
  const sheetComment = document.getElementById('sheetComment');
  const sheetConfirm = document.getElementById('sheetConfirm');
  // закриття по кліку на бекдроп
  sheetEntry?.querySelector('.sheet-backdrop')?.addEventListener('click', closeEntrySheet);

  // кнопка "Зберегти" в модалці операції
    sheetConfirm.onclick = async () => {
    if (state.entrySubmitting) return;           // ⛔ повторний клік
    state.entrySubmitting = true;
    sheetConfirm.disabled = true;

    try {
        const amount = Number(sheetAmount.value);
        if (!amount || amount <= 0) {
        alert('Введи суму більше 0');
        return;
        }

        const ok = await submitEntry(sheetType, amount, sheetComment.value);
        if (ok) closeEntrySheet();

    } finally {
        state.entrySubmitting = false;
        sheetConfirm.disabled = false;
    }
    };


  let sheetType = null;

  // Sheet wallet
  const sheetWallet = document.getElementById('sheetWallet');
  const walletName = document.getElementById('walletName');
  const walletCurrency = document.getElementById('walletCurrency');
  const walletConfirm = document.getElementById('walletConfirm');
  const receiptBtn = document.getElementById('receiptBtn');
  const receiptInput = document.getElementById('receiptInput');
  const receiptBadge = document.getElementById('receiptBadge');
  const receiptPreview = document.getElementById('receiptPreview');
  const receiptImg = document.getElementById('receiptImg');

  function resetReceiptUI(){
    if (state.pendingReceiptUrl) URL.revokeObjectURL(state.pendingReceiptUrl);
    state.pendingReceiptUrl = null;
    state.pendingReceiptFile = null;

    receiptBadge?.classList.add('hidden');
    receiptPreview?.classList.add('hidden');
    if (receiptImg) receiptImg.src = '';
    if (receiptInput) receiptInput.value = '';
  }

  receiptBtn?.addEventListener('click', () => {
    receiptInput?.click();
  });

  receiptInput?.addEventListener('change', () => {
    const file = receiptInput.files?.[0];
    if (!file) return;

    // тільки 1 фото (бо у тебе 1 поле receipt_path)
    resetReceiptUI();

    state.pendingReceiptFile = file;
    state.pendingReceiptUrl = URL.createObjectURL(file);

    if (receiptImg) receiptImg.src = state.pendingReceiptUrl;
    receiptBadge?.classList.remove('hidden');
    receiptPreview?.classList.remove('hidden');
  });


  // категорії в коментарях
  const CATEGORIES = {
    expense: [
      'Логістика',
      'Зарплата',
      'Обмін валют',
      'Обладнання',      
      'Комплектуючі',
      'Нова пошта',
      'Оренда',
      'Хоз. витрати',
      'Їжа',
      'Digital',
      'Благодійність',
      'Туда Сюда',
      'Дивіденди',
      'Інше',
    ],
    income: [
      'Продаж СЕС',
      'Продаж комплектуючих',
      'Обмін валют',
      'Монтаж СЕС',
      'Зелений тариф',
      'Послуги',
      'Туда Сюда',
      'Інше',
    ],
  };

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
function applyEntrySheetColor(type){
  sheetEntry.classList.remove('entry-income', 'entry-expense');

  if (type === 'income') {
    sheetEntry.classList.add('entry-income');
  } else if (type === 'expense') {
    sheetEntry.classList.add('entry-expense');
  }
}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

function formatDateParts(dateStr){
  if (!dateStr) return { dayMonth: '—', year: '' };

  const d = new Date(dateStr);
  if (isNaN(d)) return { dayMonth: '—', year: '' };

  return {
    dayMonth: `${String(d.getDate()).padStart(2,'0')}.${String(d.getMonth()+1).padStart(2,'0')}`,
    year: `${d.getFullYear()}р.`
  };
}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  function showWallets(){
    opsView.style.display = 'none';
    walletsView.style.display = '';
  }

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  function showOps(){
    walletsView.style.display = 'none';
    opsView.style.display = '';
  }

function canWriteWallet(walletOwner){
  return walletOwner === state.actor;
}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  function disarmDelete(){
    state.delArmedId = null;
    if (state.delTimer) clearTimeout(state.delTimer);
    state.delTimer = null;
  }

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

function setViewOwner(owner){
  if (IS_ACCOUNTANT) return;

  state.viewOwner = owner;

  // Безпечніше: якщо кнопок нема (раптово) — не падаємо
  btnViewK?.classList.toggle('active', owner === 'kolisnyk');
  btnViewH?.classList.toggle('active', owner === 'hlushchenko');

  const isMineView = (owner === state.actor);
  viewHint.textContent = isMineView ? 'Редагування' : 'Перегляд';

  // "+ рахунок" тільки коли дивимось свої
  if (btnAddWallet) btnAddWallet.style.display = isMineView ? '' : 'none';

  // reset selection
  state.selectedWalletId = null;
  state.selectedWallet = null;
  state.entries = [];
  elWalletTitle.textContent = '';
  elEntries.innerHTML = '';
  roTag.style.display = 'none';
  btnIncome.disabled = true;
  btnExpense.disabled = true;

  disarmDelete();
  showWallets();

  // ✅ ти правильно повернув це
  loadWallets();
}


let _chartJsPromise = null;




/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

async function loadWallets() {

  if (isLoadingWallets) return;
  isLoadingWallets = true;

  try {

    const res = await fetch('/api/wallets');
    state.wallets = res.ok ? await res.json() : [];

    renderWallets();

    if (state.pendingOpenStaffWalletId) {
      const walletId = Number(state.pendingOpenStaffWalletId);
      const exists = state.wallets.some(w => Number(w.id) === walletId);

      if (exists) {
        state.pendingOpenStaffWalletId = null;
        await loadEntries(walletId);

        const params = new URLSearchParams(window.location.search);
        params.delete('open_staff_wallet');
        const nextQuery = params.toString();
        const nextUrl = `${window.location.pathname}${nextQuery ? `?${nextQuery}` : ''}${window.location.hash || ''}`;
        window.history.replaceState({}, '', nextUrl);
      }
    }

    setTimeout(async () => {
      try {

        if (!['worker', 'manager'].includes(AUTH_USER.role) && !state.bankAccounts.length) {

          const requests = [
            '/api/bank/accounts',
            '/api/bank/accounts-sggroup',
            '/api/bank/accounts-solarglass',
            '/api/bank/accounts-monobank',
            '/api/bank/accounts-privat',
          ];

          const results = await Promise.allSettled(
            requests.map(url => fetch(url))
          );

          const data = await Promise.all(
            results.map(async r =>
              r.status === 'fulfilled' && r.value.ok
                ? await r.value.json()
                : []
            )
          );

          state.bankAccounts = data.flat();
        }

        await loadFx();

        renderHoldingCard();
        renderWallets();

      } catch (e) {
        console.error('Background load failed', e);
      } finally {
        isLoadingWallets = false;
      }

    }, 0);

  } catch (e) {
    console.error('Wallet load failed', e);
    isLoadingWallets = false;
  }
}


/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//.                                      
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  async function loadEntries(walletId){
    state.selectedWalletId = walletId;

    const res = await fetch(`/api/wallets/${walletId}/entries`);
    if (!res.ok) {
      console.error('loadEntries failed', res.status);
      return;
    }
    const data = await res.json();

    state.selectedWallet = data.wallet;
    state.entries = data.entries || [];
    initStatsMonth();


    elWalletTitle.textContent = `${state.selectedWallet.name} • ${state.selectedWallet.currency}`;

    const writable = canWriteWallet(state.selectedWallet.owner);
    btnIncome.disabled = !writable;
    btnExpense.disabled = !writable;
    roTag.style.display = writable ? 'none' : '';

  renderEntries();
  renderEntriesSummary();
  renderCategoryStats();
  renderWalletBalance();
  showOps();

  }

    const ENTRY_TYPE_LABELS = {
    income: 'Дохід',
    expense: 'Витрата'
  };

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  function toggleEntryMenu(el){
  document.querySelectorAll('.entry-menu').forEach(m => {
    if (m !== el.nextElementSibling) m.classList.add('hidden');
  });
  el.nextElementSibling.classList.toggle('hidden');
}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

function pickCategory(cat){
  alert(`Категорія: ${cat}\n(поки лише UI)`);
}
const CURRENCY_SYMBOLS = {
  UAH: '₴',
  USD: '$',
  EUR: '€',
};

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  function getFilteredEntriesByStatsType() {
    return state.entries.filter(e => {
      const val = Number(e.signed_amount || 0);
      return statsType === 'expense' ? val < 0 : val > 0;
    });
  }

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  // ===== Stats UI state =====
  let statsType = 'expense';

  const statsExpense = document.getElementById('statsExpense');
  const statsIncome  = document.getElementById('statsIncome');


  function refreshStatsResult() {
    const month = document.getElementById('statsMonth').value;
    if (!month) return;

    const map = {};

    state.entries.forEach(e => {
      if (!e.posting_date.startsWith(month)) return;

      const val = Number(e.signed_amount || 0);
      if (statsType === 'expense' && val >= 0) return;
      if (statsType === 'income' && val <= 0) return;

      const m = (e.comment || '').match(/^\[(.+?)\]/);
      const cat = m ? m[1] : 'Без категорії';

      map[cat] = (map[cat] || 0) + Math.abs(val);
    });

    renderStats(map);
  }

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    function refreshStatsUI() {
    renderCategoryStats();
    if (window.Chart) renderCategoryChart(); // графік тільки якщо вже є Chart
    }



  statsExpense.onclick = () => {
    statsType = 'expense';
    statsExpense.classList.add('active');
    statsIncome.classList.remove('active');

    refreshStatsUI();      // chart + bars
    refreshStatsResult();  // ⬅️ ОЦЕ БУЛО ВІДСУТНЄ
  };

  statsIncome.onclick = () => {
    statsType = 'income';
    statsIncome.classList.add('active');
    statsExpense.classList.remove('active');

    refreshStatsUI();
    refreshStatsResult();
  };


/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  function renderEntries(){
    elEntries.innerHTML = '';

    const lockedWalletNames = new Set([
      'КЕШ НТВ (UAH)',
      'КЕШ НТВ (USD)',
      'КЕШ НТВ (EUR)',
    ]);
    const isLockedNtvCash =
      state.selectedWallet?.type !== 'bank' &&
      lockedWalletNames.has(String(state.selectedWallet?.name || ''));

    state.entries.forEach(e => {
      const signed = Number(e.signed_amount || 0);
      const cls = signed >= 0 ? 'pos' : 'neg';
      const sign = signed >= 0 ? '+' : '';

      const editable =
        isToday(e.posting_date) &&
        canWriteWallet(state.selectedWallet.owner) &&
        !isLockedNtvCash &&
        !e.cash_transfer_id;

      const isTransfer = !!e.cash_transfer_id;

      const d = new Date(e.posting_date);
      const dateHtml = `
        ${String(d.getDate()).padStart(2,'0')}.${String(d.getMonth()+1).padStart(2,'0')}
        <div style="font-size:11px;opacity:.6">${d.getFullYear()}р</div>
      `;

      const tr = document.createElement('tr');
      tr.className = 'entry-row';

      // Long press support
      let lpTimer = null;
      let lpFired = false;

      function startLp(ev) {
        lpFired = false;
        lpTimer = setTimeout(() => {
          lpFired = true;
          vibrate(30);
          showEntryActions(e, editable, isTransfer);
        }, 500);
      }
      function cancelLp() { clearTimeout(lpTimer); }

      tr.addEventListener('touchstart', startLp, { passive: true });
      tr.addEventListener('touchmove',  cancelLp, { passive: true });
      tr.addEventListener('touchend', (ev) => {
        cancelLp();
        if (lpFired) ev.preventDefault();
      });
      tr.addEventListener('mousedown', startLp);
      tr.addEventListener('mouseup',   cancelLp);
      tr.addEventListener('mouseleave', cancelLp);

      tr.innerHTML = `
        <td class="muted date-cell">
          ${dateHtml}
        </td>

        <td class="entry-comment">
          ${renderComment(e.comment)}
          ${isTransfer ? '<span class="transfer-badge">💸</span>' : ''}

          ${e.receipt_url ? `
            <button class="receipt-btn" onclick="openReceipt('${e.receipt_url}'); event.stopPropagation()">
              📎
            </button>
          ` : ''}
        </td>

        <td class="amount-cell ${cls}">
          ${sign}${fmt(Math.abs(signed))}
          <span class="amount-currency">
            ${CURRENCY_SYMBOLS[state.selectedWallet.currency] ?? ''}
          </span>
        </td>
      `;

      elEntries.appendChild(tr);
    });

    window.onEntriesRendered?.();
  }


/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
function renderCurrencyIcon(currency) {
  const map = {
    UAH: '₴',
    EUR: '€',
    USD: '$'
  };

  return `
    <div class="currency-icon currency-${currency}">
      ${map[currency] ?? '¤'}
    </div>
  `;
}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

function renderWallets() {
  if (isRenderingWallets) return;
  isRenderingWallets = true;

  elWallets.innerHTML = '';

// ================= CASH =================
let visible;

if (AUTH_USER.role === 'accountant') {
  // бухгалтер НЕ тут, його кеші у модалці
  visible = state.wallets.filter(w => w.owner === state.viewOwner);

} else if (AUTH_USER.role === 'worker' || AUTH_USER.role === 'manager') {
  // прораб / менеджер бачить ТІЛЬКИ свій кеш
  visible = state.wallets.filter(w => w.owner === AUTH_USER.actor);

} else {
  // owner / партнер — як було
  visible = state.wallets.filter(w => w.owner === state.viewOwner);
}


visible.forEach(w => {
  const writable = canWriteWallet(w.owner);
  const bal = Number(w.balance || 0);
  const balCls = bal >= 0 ? 'pos' : 'neg';

  const card = document.createElement('div');
  card.className = `card account-card account-card-ui account-cash ${writable ? '' : 'ro'}`;
  card.dataset.accountId = w.id;
  card.onclick = () => loadEntries(w.id);

  card.innerHTML = `
    <div class="account-top">
      <div class="account-currency">${w.currency}</div>
      ${renderCurrencyIcon(w.currency)}
    </div>

    <div class="account-name">${w.name}</div>

    <div class="account-balance ${balCls}">
      ${fmt(bal)} ${w.currency}
    </div>

    <div class="account-type">Cash account</div>

    <div class="pirate-overlay">
      <div class="pirate-skull">☠️</div>
      <div class="pirate-text"></div>
    </div>
  `;

  elWallets.appendChild(card);
});



  // ================= BANK =================
  const visibleBanks = (AUTH_USER.role === 'worker' || AUTH_USER.role === 'ntv' || AUTH_USER.role === 'manager') ? [] : state.bankAccounts;



  visibleBanks.forEach(bank => {
    const card = document.createElement('div');
    card.className = 'card account-card-ui account-bank ro';


    card.style.position = 'relative';

    let logo = '';
    if (bank.bankCode === 'monobank') {
      logo = `<img src="/img/monoLogo.png" class="bank-logo">`;
    }
    if (bank.bankCode?.includes('ukrgasbank')) {
      logo = `<img src="/img/ukrgasLogo.png" class="bank-logo">`;
    }
    if (bank.bankCode === 'privatbank') {
      logo = `<img src="/img/privatLogo.png" class="bank-logo">`;
    }


card.innerHTML = `
  <div class="account-top">
    <div class="account-currency">${bank.currency}</div>
    ${logo}
  </div>

  <div class="account-name">${bank.name}</div>

  <div class="account-balance ${bank.balance >= 0 ? 'pos' : 'neg'}">
    ${fmt(bank.balance)} ${bank.currency}
  </div>

  <div class="account-type">Bank account</div>
`;


    card.onclick = () => openBankAccount(bank);
    elWallets.appendChild(card);
  });

  if (!visible.length && !visibleBanks.length) {
    elWallets.innerHTML = '<div class="muted">Немає рахунків</div>';
  }

  isRenderingWallets = false;
  initPirateDelete();

}






/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  function renderEntriesSummary(){
    if (!state.entries.length){
      elSummary.classList.add('hidden');
      return;
    }

    const values = state.entries.map(e => Number(e.signed_amount || 0));
    const total  = values.reduce((a,b) => a + b, 0);
    const count  = values.length;
    const avg    = total / count;

    elSummary.classList.remove('hidden');

    elSumTotal.textContent =
      `${fmt(total)} ${CURRENCY_SYMBOLS[state.selectedWallet.currency]}`;

    elSumCount.textContent = count;

    elSumAvg.textContent =
      `${fmt(avg)} ${CURRENCY_SYMBOLS[state.selectedWallet.currency]}`;

    elSumTotal.className = 'summary-value ' + (total >= 0 ? 'pos' : 'neg');
  }

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

async function loadBankAccounts() {
  const res = await fetch('/api/bank/accounts');
  if (!res.ok) return [];
  return await res.json();
}


/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

function renderCategoryStats() {
  const entries = getFilteredEntriesByStatsType();

  if (!entries.length) {
    elCatBox.classList.add('hidden');
    return;
  }

  const map = {};
  let total = 0;

  entries.forEach(e => {
    const amount = Math.abs(Number(e.signed_amount));
    total += amount;

    const m = (e.comment || '').match(/^\[(.+?)\]/);
    const cat = m ? m[1] : 'Інше';

    map[cat] = (map[cat] || 0) + amount;
  });

  elCatList.innerHTML = '';
  elCatBox.classList.remove('hidden');

  Object.entries(map)
    .sort((a, b) => b[1] - a[1])
    .forEach(([cat, sum]) => {
      const pct = Math.round((sum / total) * 100);

      elCatList.insertAdjacentHTML('beforeend', `
        <div class="cat-row">
          <div class="cat-name">${escapeHtml(cat)}</div>
          <div class="cat-bar"><div style="width:${pct}%"></div></div>
          <div class="cat-pct">${pct}%</div>
        </div>
      `);
    });
}



/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

function renderCategoryChart() {
  if (!ctxChart || typeof Chart === 'undefined') return;

  const entries = getFilteredEntriesByStatsType();
  if (!entries.length) return;

  const data = {};

  entries.forEach(e => {
    const m = (e.comment || '').match(/^\[(.+?)\]/);
    if (!m) return;

    const cat = m[1];
    data[cat] = (data[cat] || 0) + Math.abs(Number(e.signed_amount));
  });

  const labels = Object.keys(data);
  const values = Object.values(data);

  if (catChartInstance) catChartInstance.destroy();

  catChartInstance = new Chart(ctxChart, {
    type: 'pie',
    data: {
      labels,
      datasets: [{
        data: values,
        backgroundColor: [
          '#66f2a8',
          '#4c7dff',
          '#ffb86c',
          '#ff6b6b',
          '#9aa6bc'
        ]
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { labels: { color: '#e9eef6' } }
      }
    }
  });
}


/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  document.getElementById('showStats').onclick = () => {
    const month = document.getElementById('statsMonth').value;
    if (!month) {
      alert('Вибери місяць');
      return;
    }

    const map = {};

    state.entries.forEach(e => {
      if (!e.posting_date.startsWith(month)) return;
      if (e.entry_type !== statsType) return;

      const m = (e.comment || '').match(/^\[(.+?)\]/);
      const cat = m ? m[1] : 'Без категорії';

      map[cat] = (map[cat] || 0) + Math.abs(Number(e.signed_amount));
    });

    renderStats(map);
  };

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  function renderStats(map){
  const el = document.getElementById('statsResult');
  el.innerHTML = '';

  const entries = Object.entries(map);
  if (!entries.length){
    el.innerHTML = '<div class="muted">Немає даних</div>';
    return;
  }

  let total = 0;
  const card = document.createElement('div');
  card.className = 'card';

  entries.forEach(([cat,sum]) => {
    total += sum;
    card.innerHTML += `
      <div class="row" style="margin-bottom:6px;">
        <div>${cat}</div>
        <div class="right ${statsType==='expense'?'neg':'pos'}">
          ${fmt(sum)} ${CURRENCY_SYMBOLS[state.selectedWallet.currency]}
        </div>
      </div>
    `;
  });

  card.innerHTML += `
    <hr style="opacity:.1">
    <div class="row">
      <div><b>Разом</b></div>
      <div class="right big ${statsType==='expense'?'neg':'pos'}">
        ${fmt(total)} ${CURRENCY_SYMBOLS[state.selectedWallet.currency]}
      </div>
    </div>
  `;

  el.appendChild(card);
}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    function renderComment(text){
    if (!text) return '';

    const m = text.match(/^\[(.+?)\]\s*(.*)$/);

    if (!m) {
      return `<div>${text}</div>`;
    }

    return `
      <div style="font-weight:700;font-size:13px">
        ${m[1]}
      </div>
      <div style="font-size:12px;opacity:.7">
        ${m[2]}
      </div>
    `;
  }

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  function renderWalletBalance(){
  const sum = state.entries.reduce((acc, e) => {
    return acc + Number(e.signed_amount || 0);
  }, 0);

  const cls = sum >= 0 ? 'pos' : 'neg';
  elWalletBalance.className = `big ${cls}`;
  elWalletBalance.textContent =
    `${fmt(sum)} ${state.selectedWallet.currency}`;
}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  // ===== Sheet: Entry =====
function openEntrySheet(type){
  if (!state.selectedWalletId || !state.selectedWallet) {
    alert('Спочатку відкрий рахунок');
    return;
  }

  if (!canWriteWallet(state.selectedWallet.owner)) {
    alert('Режим перегляду: редагування заборонено');
    return;
  }

  sheetType = type;
  applyEntrySheetColor(type);

  // ✅ ключ на одну "спробу створення" (збережеться навіть якщо інет залип)
  state.entryIdemKey = makeIdempotencyKey();

  sheetEntryTitle.textContent =
    type === 'income' ? 'Додати дохід' : 'Додати витрату';

  sheetCategory.innerHTML = '<option value="">Категорія</option>';
  CATEGORIES[type].forEach(cat => {
    const opt = document.createElement('option');
    opt.value = cat;
    opt.textContent = cat;
    sheetCategory.appendChild(opt);
  });

  sheetAmount.value = '';
  sheetComment.value = '';
  sheetCategory.value = '';

  sheetEntry.classList.remove('hidden');
  resetReceiptUI();
}



/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

function closeEntrySheet(){
  sheetEntry.classList.add('hidden');
  sheetType = null;
  state.editingEntryId = null;

  // ✅ закрили шитку — це вже інша операція
  state.entryIdemKey = null;

  sheetEntry.classList.remove('entry-income', 'entry-expense');
  resetReceiptUI();
}



/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

async function submitEntry(entry_type, amount, comment){
  if (!checkOnline()) return false;

  const finalComment = sheetCategory.value
    ? `[${sheetCategory.value}] ${comment || ''}`
    : (comment || '');

  const isEdit = !!state.editingEntryId;

  const url = isEdit
    ? `/api/entries/${state.editingEntryId}`
    : '/api/entries';

  const method = isEdit ? 'PUT' : 'POST';

  // ✅ idempotency key ТІЛЬКИ для створення (POST)
  if (!isEdit && !state.entryIdemKey) {
    state.entryIdemKey = makeIdempotencyKey();
  }

  const payload = isEdit
    ? { amount: Number(amount), comment: finalComment }
    : {
        wallet_id: state.selectedWalletId,
        entry_type,
        amount: Number(amount),
        comment: finalComment,
        client_request_id: state.entryIdemKey, // ✅
      };

  let res;
  try {
    res = await fetch(url, {
      method,
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': CSRF,
        'Accept': 'application/json',
        ...( (!isEdit && state.entryIdemKey) ? { 'X-Idempotency-Key': state.entryIdemKey } : {} ),
      },
      body: JSON.stringify(payload)
    });
  } catch (e) {
    // 🔥 Оце і є "поганий інет": запит міг піти, а відповідь не дійшла.
    // НЕ міняємо ключ, щоб повторний клік “Зберегти” не створив дубль.
    alert('Звʼязок поганий. Натисни "Зберегти" ще раз, я не створю дубль.');
    return false;
  }

  if (!res.ok) {
    const txt = await res.text();
    alert(txt || 'Помилка');
    return false;
  }

  entryFeedback(entry_type);

  // 1) Дістаємо id (для POST і для idempotency теж)
  let createdId = null;
  try {
    const data = await res.json();
    createdId = data?.id ?? data?.entry?.id ?? null;
  } catch {}

  // ✅ якщо операція створилась/підтвердилась — ключ можна обнулити
  if (!isEdit) state.entryIdemKey = null;

  // ... далі твій код з receipt upload (як був)
  // важливо: createdId тепер буде однаковий навіть при повторі

  // 2) upload receipt (твій існуючий блок лишаємо)
  if (!isEdit && state.pendingReceiptFile) {
    if (!createdId) {
      alert('Операцію створено, але сервер не повернув id. Треба щоб POST /api/entries повертав JSON {id: ...}.');
    } else {
      const form = new FormData();
      form.append('file', state.pendingReceiptFile);

      const up = await fetch(`/api/entries/${createdId}/receipt`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: form
      });

      if (!up.ok) {
        const txt = await up.text();
        alert('Чек не завантажився: ' + (txt || up.status));
      } else {
        resetReceiptUI();
      }
    }
  }

  state.editingEntryId = null;

  await loadEntries(state.selectedWalletId);
  await loadWallets();
  return true;
}



/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  // ===== Sheet: Wallet =====
  function openWalletSheet(){
    if (state.viewOwner !== state.actor) {
      alert('У режимі перегляду партнера створення рахунків заборонено');
      return;
    }
    walletName.value = '';
    walletCurrency.value = 'UAH';
    sheetWallet.classList.remove('hidden');
    setTimeout(() => walletName.focus(), 50);
  }

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  function closeWalletSheet(){
    sheetWallet.classList.add('hidden');
  }

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  async function submitWallet(name, currency){
    const res = await fetch('/api/wallets', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': CSRF
      },
      body: JSON.stringify({
        name,
        currency,
        type: 'cash'
      })
    });

    if (!res.ok) {
      const txt = await res.text();
      alert(`Помилка: ${res.status}\n${txt.slice(0, 300)}`);
      return false;
    }

    await loadWallets();
    return true;
  }

  sheetWallet?.querySelector('.sheet-backdrop')?.addEventListener('click', closeWalletSheet);
  walletConfirm.onclick = async () => {
    const name = (walletName.value || '').trim();
    const currency = walletCurrency.value;

    if (!name) {
      alert('Введи назву рахунку');
      return;
    }

    const ok = await submitWallet(name, currency);
    if (ok) closeWalletSheet();
  };

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  // ===== Delete wallet (мережа) =====
  async function deleteWallet(walletId, walletName){
    if (state.viewOwner !== state.actor) {
      alert('У режимі перегляду партнера видалення заборонено');
      return;
    }

    const res = await fetch(`/api/wallets/${walletId}`, {
      method: 'DELETE',
      headers: { 'X-CSRF-TOKEN': CSRF }
    });

    if (!res.ok) {
      const txt = await res.text();
      alert(`Помилка: ${res.status}\n${txt.slice(0, 300)}`);
      return;
    }

    if (state.selectedWalletId === walletId) {
      showWallets();
      state.selectedWalletId = null;
      state.selectedWallet = null;
      state.entries = [];
    }

    await loadWallets();
  }

  // ESC close any sheet + роззброїти delete
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    if (!sheetEntry.classList.contains('hidden')) closeEntrySheet();
    if (!sheetWallet.classList.contains('hidden')) closeWalletSheet();

    disarmDelete();
    renderWallets();
  });

  // UI events
  document.getElementById('refresh').onclick = (e) => { e.preventDefault(); loadWallets(); };
  btnBack.onclick = (e) => { e.preventDefault(); showWallets(); };

  btnIncome.onclick = (e) => { e.preventDefault(); openEntrySheet('income'); };
  btnExpense.onclick = (e) => { e.preventDefault(); openEntrySheet('expense'); };

  btnAddWallet.onclick = (e) => { e.preventDefault(); openWalletSheet(); };

  if (btnViewK) {
    btnViewK.onclick = (e) => { e.preventDefault(); setViewOwner('kolisnyk'); };
  }

  if (btnViewH) {
    btnViewH.onclick = (e) => { e.preventDefault(); setViewOwner('hlushchenko'); };
  }
    bindOnlineFxRefreshOnce();
    scheduleDailyFxRefresh();


    // init
    if (!IS_ACCOUNTANT) {
    setViewOwner(state.viewOwner); // setViewOwner сам викличе loadWallets()
    } else {
        
    loadWallets(); // бухгалтеру setViewOwner не викликається
    }



    

const burgerBtn = document.getElementById('burgerBtn');
const burgerMenu = document.getElementById('burgerMenu');

if (!burgerBtn || !burgerMenu) {
  // на сторінках без хедера/бургера просто нічого не робимо
  // і не ламаємо весь wallet.js
} else {

  burgerBtn.onclick = (e) => {
    e.stopPropagation();
    burgerMenu.classList.toggle('hidden');
  };

  // клік поза меню — закрити
  document.addEventListener('click', () => {
    if (!burgerMenu.classList.contains('hidden')) {
      burgerMenu.classList.add('hidden');
    }
  });

}

function fmt(n) {
  return Number(n || 0).toLocaleString('uk-UA');
}




function fmtMoney2(n){
  return Number(n || 0).toLocaleString('uk-UA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

async function loadFx(force=false){
  if (!force && state.fx && state.fxFetchedAt && (Date.now() - state.fxFetchedAt) < FX_CACHE_TTL_MS) {
    return state.fx;
  }

  try{
    const res = await fetch('/api/fx/rates', { headers: { 'Accept':'application/json' } });
    const data = await res.json();
    if (!res.ok || data.error) return null;

    const map = {};
    (data.rates || []).forEach(r => {
      map[r.currency] = { purchase: Number(r.purchase), sale: Number(r.sale) };
    });

    state.fx = { date: data.date, map };
    state.fxFetchedAt  = Date.now();
    return state.fx;
  } catch {
    return null;
  }
}


function dayKeyLocal(d = new Date()){
  const y = d.getFullYear();
  const m = String(d.getMonth()+1).padStart(2,'0');
  const dd = String(d.getDate()).padStart(2,'0');
  return `${y}-${m}-${dd}`;
}

// =======================
// Anti-duplicate: idempotency key + submit lock
// =======================
function makeIdempotencyKey(){
  if (window.crypto?.randomUUID) return crypto.randomUUID();
  return 'k_' + Date.now() + '_' + Math.random().toString(16).slice(2);
}


function scheduleDailyFxRefresh(){
  const planNext = () => {
    const now = new Date();
    const next = new Date(now);

    // ⏰ коли саме оновлювати (я поставив 10:05, щоб курс вже точно “оновився”)
    next.setHours(10, 5, 0, 0);
    if (next <= now) next.setDate(next.getDate() + 1);

    const ms = next.getTime() - now.getTime();

    setTimeout(async () => {
      await loadFx(true);
      updateHoldingCardTotalsUI();
      renderHoldingStatsUI?.();
      renderHoldingAccountsStatsUI?.();
      planNext(); // запланували наступне
    }, ms);
  };

  planNext();
}


// valuation / conversion by exchanger:
// - CUR -> UAH : * purchase
// - UAH -> CUR : / sale
function convertAmount(amount, from, to){
  amount = Number(amount || 0);
  if (!amount) return 0;
  if (from === to) return amount;

  const fx = state.fx?.map || {};
  const USD = fx.USD;
  const EUR = fx.EUR;

  const toUAH = (a, cur) => {
    if (cur === 'UAH') return a;
    const r = fx[cur];
    if (!r?.purchase) return NaN;
    return a * r.purchase;
  };

  const fromUAH = (a, cur) => {
    if (cur === 'UAH') return a;
    const r = fx[cur];
    if (!r?.sale) return NaN;
    return a / r.sale;
  };

  // через UAH як “хаб”
  const uah = toUAH(amount, from);
  return fromUAH(uah, to);
}



async function loadBankOpsForHolding(){
  if (AUTH_USER.role === 'worker' || AUTH_USER.role === 'manager') return []; // worker/manager банк не бачить

  const banks = state.bankAccounts || [];
  const chunks = await Promise.all(
    banks.map(async bank => {
      let rows = [];

      if (bank.bankCode === 'monobank') {
        const id = String(bank.id || '').replace('mono_','');
        const res = await fetch(`/api/bank/transactions-monobank?id=${encodeURIComponent(id)}`);
        rows = res.ok ? await res.json() : [];
        return rows.map(r => ({
          source:'bank',
          posting_date: String(r.date || '').slice(0,10),
          signed_amount: Number(r.amount || 0),
          comment: r.comment || '',
          currency: bank.currency || 'UAH',
        }));
      }

      if (bank.bankCode === 'privatbank') {
        const id = String(bank.id || '').replace('privat_','');
        const res = await fetch(`/api/bank/transactions-privat?id=${encodeURIComponent(id)}`);
        rows = res.ok ? await res.json() : [];
        return rows.map(r => ({
          source:'bank',
          posting_date: String(r.date || '').slice(0,10),
          signed_amount: Number(r.amount || 0),
          comment: r.comment || '',
          currency: bank.currency || 'UAH',
        }));
      }

      // ukrgas
      const iban = bank.iban || '';
      let url = '';

      if (bank.bankCode === 'ukrgasbank_solarglass') {
        url = `/api/bank/transactions-solarglass?iban=${encodeURIComponent(iban)}`;
      } else if (bank.bankCode === 'ukrgasbank_sggroup') {
        url = `/api/bank/transactions-sggroup?iban=${encodeURIComponent(iban)}`;
      } else {
        url = `/api/bank/transactions-engineering?iban=${encodeURIComponent(iban)}`;
      }

      const res = await fetch(url);
      rows = res.ok ? await res.json() : [];

      return rows.map(r => ({
        source:'bank',
        posting_date: String(r.date || '').slice(0,10),
        signed_amount: Number(r.amount || 0),
        comment: r.comment || r.counterparty || '',
        currency: bank.currency || 'UAH',
      }));
    }).map(p => p.catch(()=>[]))
  );

  return chunks.flat();
}

async function ensureHoldingOps(){
  if (state.holdingOps) return state.holdingOps;

  await loadFx(); // потрібен курс для конвертацій

  const [cashOps, bankOps] = await Promise.all([
    loadAllCashOpsForHolding(),
    loadBankOpsForHolding(),
  ]);

  state.holdingOps = [...cashOps, ...bankOps];
  return state.holdingOps;
}

function normalizeText(s){ return String(s||'').toLowerCase(); }

// дуже базові правила, потім розшириш
const BANK_CAT_RULES = [
  { cat: 'Нова пошта',  re: /(нова пошта|novaposhta|np|нп)/i },
  { cat: 'Логістика',   re: /(логіст|перевез|доставка|shipping|transport)/i },
  { cat: 'Їжа',         re: /(silpo|атб|ashan|metro|кафе|restaurant|food|їжа)/i },
  { cat: 'Digital',     re: /(meta|facebook|google|ads|hosting|domain|digital|реклама)/i },
  { cat: 'Оренда',      re: /(оренда|rent)/i },
  { cat: 'Зарплата',    re: /(зарп|salary|аванс)/i },
  { cat: 'Обладнання',  re: /(інвертор|панел|акум|battery|solar|обладнан)/i },
  { cat: 'Хоз. витрати',re: /(хоз|канц|папір|побут)/i },
  { cat: 'Туда Сюда',   re: /(переказ|transfer|card2card|на карту)/i },
];

function extractCategoryFromEntry(entry){
  const c = entry.comment || '';

  // кеш: у тебе вже є [Категорія]
  const m = c.match(/^\[(.+?)\]/);
  if (m) return m[1];

  // банк: пробуємо правила
  if (entry.source === 'bank') {
    for (const r of BANK_CAT_RULES){
      if (r.re.test(c)) return r.cat;
    }
    return 'Інше';
  }

  return 'Інше';
}


function getHoldingTotal(){
  const base = state.holdingCurrency || 'UAH';

  // баланси рахунків (не операції)
  const wallets =
    (AUTH_USER.role === 'worker' || AUTH_USER.role === 'manager')
      ? state.wallets.filter(w => w.owner === AUTH_USER.actor)
      : state.wallets;

  const cashSum = wallets
    .filter(w => (w.type || 'cash') === 'cash')
    .reduce((acc,w) => acc + convertAmount(Number(w.balance||0), w.currency||'UAH', base), 0);

  const bankSum = (AUTH_USER.role === 'worker' || AUTH_USER.role === 'manager')
    ? 0
    : (state.bankAccounts || []).reduce((acc,b) =>
        acc + convertAmount(Number(b.balance||0), b.currency||'UAH', base), 0);

  return cashSum + bankSum;
}



// ===== Holding Stats =====
let hCatChartInstance = null;
let holdingPieInstance = null;


function initHoldingMonths(ops){
  const sel = document.getElementById('hStatsMonth');
  if (!sel) return;

  const months = {};
  ops.forEach(e => {
    const d = String(e.posting_date||'');
    if (d.length >= 7) months[d.slice(0,7)] = true;
  });

  sel.innerHTML = '<option value="">Місяць</option>';
  Object.keys(months).sort().reverse().forEach(ym => {
    const [y,m] = ym.split('-');
    const opt = document.createElement('option');
    opt.value = ym;
    opt.textContent = `${m}.${y}`;
    sel.appendChild(opt);
  });

  const first = Object.keys(months).sort().reverse()[0];
  if (first) sel.value = first;
}

function buildHoldingStats(ops, ym, type){
  const base = state.holdingCurrency || 'UAH';
  const sym  = CURRENCY_SYMBOLS[base] || base;

  const map = {};
  let total = 0;
  let count = 0;

  ops.forEach(e => {
    const d = String(e.posting_date||'');
    if (ym && !d.startsWith(ym)) return;

    const val = Number(e.signed_amount||0);
    if (type === 'expense' && val >= 0) return;
    if (type === 'income'  && val <= 0) return;

    const abs = Math.abs(val);
    const converted = convertAmount(abs, e.currency||'UAH', base);
    if (!Number.isFinite(converted)) return;

    total += converted;
    count++;

    const cat = extractCategoryFromEntry(e);
    map[cat] = (map[cat] || 0) + converted;
  });

  return { map, total, count, avg: count ? total/count : 0, sym };
}

function renderHoldingStatsUI(){
  const box = document.getElementById('holdingStatsBox');
  if (!box || box.classList.contains('hidden')) return;

  const monthEl = document.getElementById('hStatsMonth');
  const incomeBtn = document.getElementById('hStatsIncome');
  const expenseBtn = document.getElementById('hStatsExpense');
  if (!monthEl || !incomeBtn || !expenseBtn) return;

  const ym = document.getElementById('hStatsMonth')?.value || '';
  const type = (document.getElementById('hStatsIncome')?.classList.contains('active')) ? 'income' : 'expense';

  const ops = state.holdingOps || [];
  const { map, total, count, avg, sym } = buildHoldingStats(ops, ym, type);

  // summary
  const sumBox = document.getElementById('hEntriesSummary');
  if (!count) {
    sumBox.classList.add('hidden');
  } else {
    sumBox.classList.remove('hidden');
    document.getElementById('hSumTotal').textContent = `${fmtMoney2(total)} ${sym}`;
    document.getElementById('hSumCount').textContent = `${count}`;
    document.getElementById('hSumAvg').textContent   = `${fmtMoney2(avg)} ${sym}`;
    document.getElementById('hSumTotal').className = 'summary-value ' + (type==='expense'?'neg':'pos');
  }

  // bars
  const pairs = Object.entries(map).sort((a,b)=>b[1]-a[1]);
  const all = pairs.reduce((a,[,v])=>a+v,0);

  const catBox = document.getElementById('hCategoryStats');
  const catList = document.getElementById('hCatList');

  if (!pairs.length) {
    catBox.classList.add('hidden');
    document.getElementById('hStatsResult').innerHTML = '<div class="muted">Немає даних</div>';
  } else {
    catBox.classList.remove('hidden');
    catList.innerHTML = '';
    pairs.forEach(([cat, v]) => {
      const pct = all ? Math.round((v/all)*100) : 0;
      catList.insertAdjacentHTML('beforeend', `
        <div class="cat-row">
          <div class="cat-name">${cat}</div>
          <div class="cat-bar"><div style="width:${pct}%"></div></div>
          <div class="cat-pct">${pct}%</div>
        </div>
      `);
    });

    // optional: текстовий список
    const res = document.getElementById('hStatsResult');
    res.innerHTML = `
      <div class="card">
        ${pairs.slice(0,20).map(([cat,v]) => `
          <div class="row" style="margin-bottom:6px;">
            <div>${cat}</div>
            <div class="right ${type==='expense'?'neg':'pos'}">${fmtMoney2(v)} ${sym}</div>
          </div>
        `).join('')}
      </div>
    `;
  }

  // pie chart
  const ctx = document.getElementById('hCatChart')?.getContext('2d');
  if (ctx && typeof Chart !== 'undefined') {
    const labels = pairs.map(x=>x[0]);
    const values = pairs.map(x=>x[1]);

    if (hCatChartInstance) hCatChartInstance.destroy();
    hCatChartInstance = new Chart(ctx, {
      type: 'pie',
      data: {
        labels,
        datasets: [{
          data: values,
          backgroundColor: ['#66f2a8','#4c7dff','#ffb86c','#ff6b6b','#9aa6bc']
        }]
      },
      options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{ labels:{ color:'#e9eef6' } } } }
    });
  }
}


document.getElementById('hShowStats')?.addEventListener('click', () => renderHoldingStatsUI());

document.getElementById('hStatsExpense')?.addEventListener('click', () => {
  document.getElementById('hStatsExpense').classList.add('active');
  document.getElementById('hStatsIncome').classList.remove('active');
  renderHoldingStatsUI();
});

document.getElementById('hStatsIncome')?.addEventListener('click', () => {
  document.getElementById('hStatsIncome').classList.add('active');
  document.getElementById('hStatsExpense').classList.remove('active');
  renderHoldingStatsUI();
});




function fmtMoney(n){
  return Number(n || 0).toLocaleString('uk-UA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}






function computeHoldingTotals(base){
  let cash = 0;
  let bank = 0;
  const missing = new Set();

  // cash wallets
  state.wallets.forEach(w => {
    const v = convertAmount(w.balance, w.currency, base);
    if (Number.isFinite(v)) cash += v;
    else missing.add(w.currency);
  });

  // bank accounts
  state.bankAccounts.forEach(b => {
    const v = convertAmount(b.balance, b.currency, base);
    if (Number.isFinite(v)) bank += v;
    else missing.add(b.currency);
  });

  return { cash, bank, total: cash + bank, missing: [...missing] };
}

function updateHoldingCardTotalsUI(){
  const base = state.holdingCurrency || 'UAH';
  const hasFx = !!state.fx;

  const totals = (base === 'UAH' || hasFx)
    ? computeHoldingTotals(base)
    : {cash:0, bank:0, total:0, missing:[]};

  const sym = CURRENCY_SYMBOLS[base] ?? '';
  const cls = totals.total >= 0 ? 'pos' : 'neg';

  const totalEl = document.getElementById('holdingTotalAmt');
  if (totalEl){
    totalEl.classList.remove('pos','neg');
    totalEl.classList.add(cls);
    totalEl.textContent = `${fmtMoney(totals.total)} ${sym}`;

  }

    const cashEl = document.getElementById('holdingCashPill');
    if (cashEl){
    const v = cashEl.querySelector('.hp-val');
    if (v) v.textContent = `${fmtMoney(totals.cash)} ${sym}`;
    }

    const bankEl = document.getElementById('holdingBankPill');
    if (bankEl){
    const v = bankEl.querySelector('.hp-val');
    if (v) v.textContent = `${fmtMoney(totals.bank)} ${sym}`;
    }


  const fxEl = document.getElementById('holdingFxDate');
  if (fxEl) fxEl.textContent = state.fx?.date ? `• курс: ${state.fx.date}` : '';

  const warnEl = document.getElementById('holdingWarn');
  if (warnEl){
    if (totals.missing.length){
      warnEl.classList.remove('hidden');
      warnEl.innerHTML = `⚠️ Немає курсу для: <b>${totals.missing.join(', ')}</b>`;
    } else {
      warnEl.classList.add('hidden');
      warnEl.innerHTML = '';
    }
  }
}


function renderHoldingCard(){
  const el = document.getElementById('holdingCard');
  if (!el) return;

  // холдинг бачить ТІЛЬКИ owner
  if (AUTH_USER.role !== 'owner'){
    el.classList.add('hidden');
    return;
  }
  el.classList.remove('hidden');


  const base = state.holdingCurrency || 'UAH';

  // якщо курс ще не підвантажили
  if (!state.fx && base !== 'UAH'){
    el.innerHTML = `
      <div class="holding-head">
        <div>
          <div class="holding-title">SG Holding</div>
          <div class="holding-sub">Потрібен курс обмінника для конвертації</div>
      </div>

      <div class="row" style="margin-top:12px;">
        <button type="button" class="btn" style="width:100%;" id="toggleHoldingStats">📊 Детальна статистика</button>
      </div>
    `;

    bindHoldingCardActions(); // ⬅️ важливо
    return;
  }

  const hasFx = !!state.fx;
  const totals = (base === 'UAH' || hasFx)
    ? computeHoldingTotals(base)
    : {cash:0, bank:0, total:0, missing:[]};

  const cls = totals.total >= 0 ? 'pos' : 'neg';
  const sym = CURRENCY_SYMBOLS[base] ?? '';

  el.innerHTML = `
    <div class="holding-head">
      <div>
        <div class="holding-title">SG Holding</div>

      </div>

      <div>
        <div class="segmented holding-mode" id="holdingCurSeg">
          <button type="button" data-hcur="UAH" class="${base==='UAH'?'active':''}">UAH</button>
          <button type="button" data-hcur="USD" class="${base==='USD'?'active':''}">USD</button>
          <button type="button" data-hcur="EUR" class="${base==='EUR'?'active':''}">EUR</button>
        </div>
      </div>
    </div>

    <div class="holding-amount ${cls}" id="holdingTotalAmt">
      ${fmtMoney(totals.total)} ${sym} 
    </div>

    <div class="holding-break">
    <div class="holding-pill" id="holdingCashPill">
        <div class="hp-top"><span>💵</span><span>Cash</span></div>
        <div class="hp-val">${fmtMoney(totals.cash)} ${sym}</div>
    </div>

    <div class="holding-pill" id="holdingBankPill">
        <div class="hp-top"><span>🏦</span><span>Bank</span></div>
        <div class="hp-val">${fmtMoney(totals.bank)} ${sym}</div>
    </div>
    </div>  


    <div class="row" style="margin-top:12px;">
      <button type="button" class="btn" id="toggleHoldingStats"style="width:100%;">📊 Детальна статистика</button>
    </div>

    <div class="holding-warn ${totals.missing.length ? '' : 'hidden'}" id="holdingWarn">
      ⚠️ Немає курсу для: <b>${totals.missing.join(', ')}</b>
    </div>
  `;

    bindHoldingCardActions(); // ⬅️ важливо
  }


function bindHoldingCardActions(){

  // 2) перемикач валюти (UAH/USD/EUR)
  const seg = document.getElementById('holdingCurSeg');
  if (seg){
    seg.onclick = async (e) => {
      const btn = e.target.closest('button[data-hcur]');
      if (!btn) return;

      const cur = btn.dataset.hcur;
      if (!cur || cur === state.holdingCurrency) return;

      state.holdingCurrency = cur;

      // плавно перемкнути active (без перерендеру)
      seg.querySelectorAll('button[data-hcur]').forEach(b => {
        b.classList.toggle('active', b.dataset.hcur === cur);
      });

      // якщо валюта не UAH — потрібен курс
      if (cur !== 'UAH') await loadFx(true);

      // оновлюємо тільки цифри
      updateHoldingCardTotalsUI();

      // якщо статистика відкрита — теж оновимо
      renderHoldingStatsUI?.();
      renderHoldingAccountsStatsUI?.();
    };
  }


  // 3) кнопка “📊 Статистика”
    const statsBtn = document.getElementById('toggleHoldingStats');
    if (statsBtn){
    statsBtn.onclick = async () => {
        const box = document.getElementById('holdingStatsBox');
        if (!box) return;

        const willOpen = box.classList.contains('hidden');
        box.classList.toggle('hidden');

        if (willOpen) {
        const ok = await ensureChartJs();
        if (!ok) {
            alert('Не вдалось завантажити графіки (Chart.js)');
            return;
        }
        renderHoldingAccountsStatsUI();
        }
    };
    }


}


state.holdingStatsFilter = 'all';

const OWNER_LABELS = {
  kolisnyk: 'Колісник',
  hlushchenko: 'Глущенко',
  accountant: 'Соловей',
  foreman: 'Оніпко',
  serviceman_1: 'Savenkov',
  serviceman_2: 'Malinin',
  shared: 'Спільне',
};

function escapeHtml(s){
  return String(s ?? '').replace(/[&<>"']/g, m => ({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
  }[m]));
}

function bankEntityLabel(b){
  const code = String(b.bankCode || '').toLowerCase();
  const name = String(b.name || '').toLowerCase();

  if (code.includes('sggroup') || name.includes('sg group')) return 'SG Group';
  if (code.includes('solarglass') || name.includes('solarglass')) return 'SolarGlass';
  if (code.includes('engineering') || name.includes('engineering')) return 'Solar Engineering';

  // fallback: якщо в назві є підказки
  if (name.includes('колісник') || name.includes('kolisnyk')) return 'Колісник';
  if (name.includes('глущ') || name.includes('hlush')) return 'Глущенко';

  return 'Банк';
}

function getHoldingAccountsList(filter){
  const base = state.holdingCurrency || 'UAH';
  const list = [];

  // CASH
  const wallets =
    (AUTH_USER.role === 'owner')
      ? state.wallets
      : state.wallets.filter(w => w.owner === AUTH_USER.actor);


  if (filter !== 'bank'){
    wallets
      .filter(w => (w.type || 'cash') === 'cash')
      .forEach(w => {
        const original = Number(w.balance || 0);
        const conv = convertAmount(original, w.currency || 'UAH', base);
        if (!Number.isFinite(conv)) return;

        list.push({
          kind: 'cash',
          entity: OWNER_LABELS[w.owner] || w.owner || 'Кеш',
          name: w.name || 'Cash',
          currency: w.currency || 'UAH',
          original,
          amountBase: conv,
        });
      });
  }

  // BANK
  if (AUTH_USER.role === 'owner' && filter !== 'cash'){
    (state.bankAccounts || []).forEach(b => {
      const original = Number(b.balance || 0);
      const conv = convertAmount(original, b.currency || 'UAH', base);
      if (!Number.isFinite(conv)) return;

      list.push({
        kind: 'bank',
        entity: bankEntityLabel(b),
        name: b.name || 'Bank',
        currency: b.currency || 'UAH',
        original,
        amountBase: conv,
      });
    });
  }

  return list;
}

function groupByEntity(list){
  const g = {};
  list.forEach(a => {
    const key = a.entity || 'Інше';
    if (!g[key]) g[key] = { total: 0, items: [] };
    g[key].total += a.amountBase;
    g[key].items.push(a);
  });

  Object.values(g).forEach(x => x.items.sort((a,b)=>b.amountBase-a.amountBase));
  return Object.entries(g).sort((a,b)=>b[1].total-a[1].total);
}

function pieColors(n){
  const base = ['#66f2a8','#4c7dff','#ffb86c','#ff6b6b','#9aa6bc','#b48cff','#2dd4bf','#f472b6'];
  const out = [];
  for (let i=0;i<n;i++) out.push(base[i % base.length]);
  return out;
}

function renderHoldingAccountsStatsUI(){
  const box = document.getElementById('holdingStatsBox');
  if (!box || box.classList.contains('hidden')) return;

  const base = state.holdingCurrency || 'UAH';

  // якщо USD/EUR і нема курсу
  if (base !== 'UAH' && !state.fx){
    const out0 = document.getElementById('holdingAccountsResult');
    if (out0) out0.innerHTML = `<div class="card">Потрібен курс валют для конвертації</div>`;
    return;
  }

  const sym  = CURRENCY_SYMBOLS[base] || base;
  const filter = state.holdingStatsFilter || 'all';

  const list = getHoldingAccountsList(filter);
  const out = document.getElementById('holdingAccountsResult');
  if (!out) return;

  if (!list.length){
    out.innerHTML = `<div class="muted">Немає даних</div>`;
    return;
  }

  // групи по субʼєкту
  const groups = groupByEntity(list);
  const total = list.reduce((s,x)=>s + x.amountBase, 0);

  // під графік беремо суми груп
  const pieLabels = groups.map(([label]) => label);
  const pieValues = groups.map(([,g]) => g.total);

  // рендер HTML (вставляємо canvas всередину)
  out.innerHTML = `
    <div class="card">
      <div class="row">
        <div class="muted">Загальний баланс (${filter === 'cash' ? 'кеш' : filter === 'bank' ? 'банк' : 'кеш + банк'})</div>
        <div class="right big">${fmtMoney2(total)} ${sym}</div>
      </div>

      <div style="height:260px;margin-top:10px;">
        <canvas id="holdingAccountsPie"></canvas>
      </div>

      <div class="muted" style="opacity:.7;margin-top:8px;">
        Рахунків: <b>${list.length}</b>
      </div>
    </div>

    ${groups.map(([label,g]) => {
      const pctGroup = total ? Math.round((g.total/total)*100) : 0;

      return `
        <div class="card" style="margin-top:14px;">
          <div class="row" style="align-items:baseline;">
            <div style="font-weight:900">${escapeHtml(label)}</div>
            <div class="right" style="font-weight:900">
              ${fmtMoney2(g.total)} ${sym}
              <span style="opacity:.7;font-size:12px">${pctGroup}%</span>
            </div>
          </div>

          <div style="margin-top:10px;">
            ${g.items.map(a => {
              const pct = g.total ? Math.round((a.amountBase/g.total)*100) : 0;
              const icon = a.kind === 'bank' ? '🏦' : '💵';
              return `
                <div style="margin-bottom:12px;">
                  <div class="row" style="margin-bottom:6px;">
                    <div style="min-width:0;white-space:normal;font-weight:700;">
                      ${icon} ${escapeHtml(a.name)}
                      <span style="opacity:.7;font-size:12px;">
                        (${fmtMoney2(a.original)} ${escapeHtml(a.currency)})
                      </span>
                    </div>
                    <div class="right" style="font-weight:800;">
                      ${fmtMoney2(a.amountBase)} ${sym}
                      <span style="opacity:.7;font-size:12px">${pct}%</span>
                    </div>
                  </div>
                  <div class="cat-bar"><div style="width:${pct}%"></div></div>
                </div>
              `;
            }).join('')}
          </div>
        </div>
      `;
    }).join('')}
  `;

  // графік
  const ctx = document.getElementById('holdingAccountsPie')?.getContext('2d');
  if (!ctx || typeof Chart === 'undefined') return;

  if (holdingPieInstance) holdingPieInstance.destroy();

  holdingPieInstance = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: pieLabels,
      datasets: [{
        data: pieValues,
        backgroundColor: pieColors(pieLabels.length),
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '55%',
      plugins: {
        legend: { labels: { color: '#e9eef6' } },
        tooltip: {
          callbacks: {
            label: (c) => {
              const val = Number(c.parsed || 0);
              const pct = total ? Math.round((val/total)*100) : 0;
              return ` ${c.label}: ${fmtMoney2(val)} ${sym} (${pct}%)`;
            }
          }
        }
      }
    }
  });
}



document.getElementById('holdingStatsFilter')?.addEventListener('click', (e) => {
  const btn = e.target.closest('button[data-hf]');
  if (!btn) return;

  document.querySelectorAll('#holdingStatsFilter button').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');

  state.holdingStatsFilter = btn.dataset.hf || 'all';
  renderHoldingAccountsStatsUI();
});



/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  function initStatsMonth(){
    const sel = document.getElementById('statsMonth');
    sel.innerHTML = '<option value="">Місяць</option>';

    const months = {};
    state.entries.forEach(e => {
      const ym = e.posting_date.slice(0,7); // YYYY-MM
      months[ym] = true;
    });

    Object.keys(months)
      .sort()
      .reverse()
      .forEach(ym => {
        const [y,m] = ym.split('-');
        const opt = document.createElement('option');
        opt.value = ym;
        opt.textContent = `${m}.${y}`;
        sel.appendChild(opt);
      });
  }

  const csvInput = document.getElementById('csvInput');

  if (csvInput) {
    csvInput.addEventListener('change', async () => {
      const file = csvInput.files[0];
      if (!file) return;

      const form = new FormData();
      form.append('file', file);

      const res = await fetch('/api/bank/csv-preview', {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': CSRF
        },
        body: form
      });

      const data = await res.json();
      console.log('CSV PREVIEW', data);

     renderCsvPreview(data.rows);

    });
  }

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////


// ================= BANK ACCOUNT OPEN =================
window.openBankAccount = async function (bank) {

  state.selectedWalletId = null;
  state.selectedWallet = {
    id: bank.id,
    name: bank.name,
    currency: bank.currency,
    type: 'bank'
  };

  elWalletTitle.textContent = `${bank.name} • ${bank.currency}`;
  elEntries.innerHTML = '<tr><td class="muted">Завантаження…</td></tr>';

  elWalletBalance.className = `big ${bank.balance >= 0 ? 'pos' : 'neg'}`;
  elWalletBalance.textContent = `${fmt(bank.balance)} ${bank.currency}`;

  btnIncome.disabled = true;
  btnExpense.disabled = true;
  roTag.style.display = '';

  showOps();

  // 🟢 MONOBANK
  if (bank.bankCode === 'monobank') {
    try {
      const res = await fetch(`/api/bank/transactions-monobank?id=${bank.id.replace('mono_','')}`);
      const rows = res.ok ? await res.json() : [];

      state.entries = rows.map(r => ({
        posting_date: r.date,
        signed_amount: r.amount,
        comment: r.comment,
      }));

      renderEntries();
      renderEntriesSummary();
    } catch (e) {
      elEntries.innerHTML = '<tr><td class="muted">Помилка завантаження</td></tr>';
    }
    return;
  }

  // 🟣 PRIVAT
  if (bank.bankCode === 'privatbank') {
    try {
      const res = await fetch(`/api/bank/transactions-privat?id=${bank.id.replace('privat_','')}`);
      const rows = res.ok ? await res.json() : [];

      state.entries = rows.map(r => ({
        posting_date: r.date,
        signed_amount: r.amount,
        comment: r.comment,
      }));

      renderEntries();
      renderEntriesSummary();
    } catch {
      elEntries.innerHTML = '<tr><td class="muted">Помилка завантаження</td></tr>';
    }
    return;
  }

    if (bank.bankCode === 'ukrgasbank_solarglass') {
    const res = await fetch(`/api/bank/transactions-solarglass?iban=${encodeURIComponent(bank.iban)}`);
    const rows = res.ok ? await res.json() : [];

    state.entries = rows.map(r => ({
      posting_date: r.date,
      signed_amount: r.amount,
      comment: r.comment || r.counterparty || '',
    }));

    renderEntries();
    renderEntriesSummary();
    return;
  }



  // 🟡 UKRGAS
  const url =
    bank.bankCode === 'ukrgasbank_sggroup'
      ? `/api/bank/transactions-sggroup?iban=${encodeURIComponent(bank.iban)}`
      : `/api/bank/transactions-engineering?iban=${encodeURIComponent(bank.iban)}`;

  try {
    const res = await fetch(url);
    const rows = res.ok ? await res.json() : [];

    state.entries = rows.map(r => ({
      posting_date: r.date,
      signed_amount: r.amount,
      comment: r.comment || r.counterparty || '',
    }));

    renderEntries();
    renderEntriesSummary();

  } catch (e) {
    elEntries.innerHTML = '<tr><td class="muted">Помилка завантаження</td></tr>';
  }
};



function isToday(dateStr) {
  const today = new Date().toISOString().slice(0, 10);
  return dateStr === today;
}



async function deleteEntry(id){
  const entry = state.entries.find(e => e.id === id);
  if (entry?.is_locked) {
    alert('🔒 Це системна операція з авансу. Видалення заборонено.');
    return;
  }
  if (!confirm('Видалити операцію?')) return;

  const res = await fetch(`/api/entries/${id}`, {
    method: 'DELETE',
    headers: { 'X-CSRF-TOKEN': CSRF }
  });

  if (!res.ok) {
    const txt = await res.text();
    alert(txt || 'Помилка видалення');
    return;
  }

  await loadEntries(state.selectedWalletId);
  await loadWallets();
}


async function editEntry(id){
  const entry = state.entries.find(e => e.id === id);
  if (!entry) return;

  if (entry.is_locked) {
    alert('🔒 Це системна операція з авансу. Редагування заборонено.');
    return;
  }

  if (!isToday(entry.posting_date)) {
    alert('Можна редагувати лише сьогоднішні операції');
    return;
  }

  sheetType = entry.signed_amount >= 0 ? 'income' : 'expense';
  applyEntrySheetColor(sheetType);

  state.editingEntryId = id;

  sheetEntryTitle.textContent = 'Редагувати операцію';

  sheetAmount.value = Math.abs(entry.signed_amount);
  sheetComment.value = entry.comment || '';

  sheetCategory.innerHTML = '<option value="">Категорія</option>';
  CATEGORIES[sheetType].forEach(cat => {
    const opt = document.createElement('option');
    opt.value = cat;
    opt.textContent = cat;
    sheetCategory.appendChild(opt);
  });

  const m = (entry.comment || '').match(/^\[(.+?)\]/);
  if (m) sheetCategory.value = m[1];

  sheetEntry.classList.remove('hidden');
}



// Long press menu — show / hide
const _lpOverlay = document.getElementById('lpOverlay');
const _lpMenu    = document.getElementById('lpMenu');
const _lpInfo    = document.getElementById('lpInfo');
const _lpAmount  = document.getElementById('lpAmount');
const _lpButtons = document.getElementById('lpButtons');

function showEntryActions(entry, editable, isTransfer) {
  const signed = Number(entry.signed_amount || 0);
  const sym    = CURRENCY_SYMBOLS[state.selectedWallet?.currency] ?? '';
  const sign   = signed >= 0 ? '+' : '';
  const IS_OWNER = AUTH_USER.role === 'owner';

  _lpInfo.textContent   = (entry.comment || '').replace(/^\[.+?\]\s*/, '') || '—';
  _lpAmount.textContent = sign + fmt(Math.abs(signed)) + ' ' + sym;

  _lpButtons.innerHTML = '';

  if (isTransfer && IS_OWNER && isToday(entry.posting_date)) {
    const btn = document.createElement('button');
    btn.className = 'lp-cancel-transfer';
    btn.textContent = '↩ Скасувати передачу';
    btn.onclick = async () => {
      hideEntryActions();
      if (!confirm('Скасувати передачу коштів?')) return;
      const res = await fetch(`/api/employee-transfers/${entry.cash_transfer_id}/cancel`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json' },
      });
      const data = await res.json();
      if (!res.ok) { alert(data.error ?? 'Помилка'); return; }
      await loadEntries(state.selectedWalletId);
      await loadWallets();
    };
    _lpButtons.appendChild(btn);
  } else if (editable && !isTransfer) {
    const btnEdit = document.createElement('button');
    btnEdit.className = 'lp-edit';
    btnEdit.textContent = '✏️ Редагувати';
    btnEdit.onclick = () => { hideEntryActions(); editEntry(entry.id); };

    const btnDel = document.createElement('button');
    btnDel.className = 'lp-delete';
    btnDel.textContent = '🗑 Видалити';
    btnDel.onclick = () => { hideEntryActions(); deleteEntry(entry.id); };

    _lpButtons.appendChild(btnEdit);
    _lpButtons.appendChild(btnDel);
  } else {
    // nothing actionable — don't show menu
    return;
  }

  const btnClose = document.createElement('button');
  btnClose.className = 'lp-close';
  btnClose.textContent = 'Закрити';
  btnClose.onclick = hideEntryActions;
  _lpButtons.appendChild(btnClose);

  _lpOverlay.classList.add('show');
  _lpMenu.classList.add('show');
}

function hideEntryActions() {
  _lpOverlay.classList.remove('show');
  _lpMenu.classList.remove('show');
}

_lpOverlay.addEventListener('click', hideEntryActions);






function initPirateDelete(){
  document.querySelectorAll('.account-card.account-cash').forEach(card => {

    if (card._pirateBound) return;
    if (card.classList.contains('ro')) return;

    card._pirateBound = true;
    card.addEventListener('contextmenu', (e) => {
      e.preventDefault();
    });


    const HOLD_MS = 6000;

    let pressTimer = null;
    let tickTimer  = null;
    let stage = 0;

    const skull = card.querySelector('.pirate-skull');
    const text  = card.querySelector('.pirate-text');

    let suppressClick = false;
    let holding = false;

    function reset(){
      stage = 0;
      holding = false;
      suppressClick = false;

      if (pressTimer) clearTimeout(pressTimer);
      if (tickTimer) clearInterval(tickTimer);
      pressTimer = null;
      tickTimer = null;

      card.classList.remove('stage-1','stage-2','stage-3');
      text.textContent = '';
    }

    function startHold(){
      if (stage > 0) return; // якщо вже “озброєно” — не стартуємо заново

      suppressClick = true;   // ⛔ блокуємо відкриття рахунку поки тримаємо
      holding = true;

      let left = Math.ceil(HOLD_MS / 1000);

      // одразу покажемо інструкцію
      text.textContent = `Тримай ${left} сек…`;

      // кожну секунду оновлюємо текст
      tickTimer = setInterval(() => {
        if (!holding) return;
        left = Math.max(0, left - 1);
        text.textContent = left > 0 ? `Тримай ${left} сек…` : 'Готово…';
      }, 1000);

      // через 8 сек вмикаємо stage-1 (показуємо череп)
      pressTimer = setTimeout(() => {
        if (!holding) return;

        stage = 1;
        card.classList.add('stage-1');
        text.textContent = 'Видалити рахунок?';

        // після “озброєння” можна дозволити кліки по картці,
        // але твій click-handler нижче все одно зробить reset якщо треба
        suppressClick = true;

        // автоскасування через 3 сек (як у тебе було)
        setTimeout(() => {
          if (stage === 1) reset();
        }, 3000);

      }, HOLD_MS);
    }

    function stopHold(){
      // якщо не дотягнув до stage-1 — просто скидаємо
      holding = false;

      if (stage === 0) {
        reset();
      } else {
        // якщо stage вже 1+ — просто зупиняємо таймери утримання
        if (pressTimer) clearTimeout(pressTimer);
        if (tickTimer) clearInterval(tickTimer);
        pressTimer = null;
        tickTimer = null;
      }
    }

    // ✅ Pointer events: і мишка, і тач одним набором
    card.addEventListener('pointerdown', (e) => {
      // тільки primary (щоб не було 2 пальці/колесо)
      if (e.isPrimary === false) return;
      // якщо натиснули прямо на череп — не стартуємо холд
      if (e.target.closest('.pirate-skull')) return;

      startHold();
    });

    card.addEventListener('pointerup', stopHold);
    card.addEventListener('pointercancel', stopHold);
    card.addEventListener('pointerleave', stopHold);

    // якщо юзер почав скролити/тягнути — скидаємо
    card.addEventListener('pointermove', (e) => {
      if (!holding || stage !== 0) return;
      // маленький поріг, щоб випадковий мікрорух не зривав
      const dx = Math.abs(e.movementX || 0);
      const dy = Math.abs(e.movementY || 0);
      if (dx + dy > 8) stopHold();
    });

    // твій захист від випадкового відкриття
    card.addEventListener('click', (e) => {
      // ⛔ якщо клік по черепу — НЕ ЧІПАЄМО
      if (e.target.closest('.pirate-skull')) return;

      if (suppressClick) {
        e.preventDefault();
        e.stopImmediatePropagation();
        suppressClick = false; // один раз “з’їли” клік
        return;
      }

      if (stage > 0) reset();
    }, true);

    // ⬇️ нижче лишаєш ТВОЮ логіку skull.onclick (stage 1→2→3→4→delete)
    skull.onclick = (e) => {
      e.stopPropagation();

      if (stage === 1) {
        stage = 2;
        card.classList.remove('stage-1');
        card.classList.add('stage-2');

        text.innerHTML = `Ти гарно подумав?<br>Відновлення буде неможливе.`;
        return;
      }

      if (stage === 2) {
        stage = 3;
        let seconds = 10;

        card.classList.add('stage-3');
        skull.style.pointerEvents = 'none';

        const countdown = setInterval(() => {
          text.innerHTML = `Зачекай ${seconds} сек...<br>Після цього можна видалити`;
          seconds--;

          if (seconds < 0) {
            clearInterval(countdown);
            stage = 4;
            skull.style.pointerEvents = 'auto';
            text.innerHTML = 'Тепер можна видалити ☠️';
          }
        }, 1000);

        return;
      }

      if (stage === 4) {
        deleteAccount(card);
        reset();
      }
    };

  });
}



function deleteAccount(card){
  const id = card.dataset.accountId

  fetch(`/api/wallets/${id}`, {

    method: 'DELETE',
    headers: {
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      'Accept':'application/json'
    }
  })
  .then(r => {
    if (!r.ok) throw new Error()
    card.remove()
  })
  .catch(() => alert('Помилка видалення рахунку'))
}



//////////////////////////////////////////////////////////////////////////////////////
// КУРС ВАЛЮТ — МОДАЛКА
//////////////////////////////////////////////////////////////////////////////////////

//////////////////////////////////////////////////////////////////////////////////////
// КУРС ВАЛЮТ — МОДАЛКА (опційно)
// Якщо кнопку видалив — код не падає
//////////////////////////////////////////////////////////////////////////////////////
const showRatesBtn = document.getElementById('showRatesBtn');

async function openRatesModalFlow(e){
  e?.preventDefault?.();

  try {
    const res = await fetch('/api/fx/rates', { headers: { 'Accept': 'application/json' } });
    const data = await res.json();

    if (!res.ok || data.error) {
      showRatesError('Не вдалося отримати курс валют');
      return false;
    }

    renderRatesModal(data);
    return true;
  } catch {
    showRatesError('Помилка при отриманні курсу валют');
    return false;
  }
}

window.openRatesModalFlow = openRatesModalFlow;
showRatesBtn?.addEventListener('click', openRatesModalFlow);


function renderRatesModal(data){
  const modal = document.getElementById('ratesModal');
  const body  = document.getElementById('ratesContent');

  body.innerHTML = `<div style="text-align:center; font-size:18px;font-weight:bold;opacity:.7;margin-bottom:10px">📅 ${data.date}</div>`;

  data.rates.forEach(r => {
    body.innerHTML += `
      <div class="rate-card" data-currency="${r.currency}"
        onclick="selectRateCard(this); openExchange('${r.currency}', ${r.purchase}, ${r.sale})">


        <div class="rate-title rate-title-${r.currency.toLowerCase()}">${r.currency}</div>
        💰 Купівля: <b>${r.purchase ?? '—'}</b><br>
        🏦 Продаж: <b>${r.sale ?? '—'}</b>
      </div>
    `;
  });

  modal.classList.remove('hidden');
}

function showRatesError(text){
  const body  = document.getElementById('ratesContent');
  body.innerHTML = `<div style="color:#ff6b6b">${text}</div>`;
  document.getElementById('ratesModal').classList.remove('hidden');
}

function closeRatesModal(){
  document.getElementById('ratesModal')?.classList.add('hidden');
}

// клік по хрестику
document.addEventListener('click', (e) => {
  if (e.target.closest('#ratesClose')) closeRatesModal();
});

// клік по затемненню
document.addEventListener('click', (e) => {
  if (e.target.classList.contains('modal-backdrop')) closeRatesModal();
});



document.addEventListener('DOMContentLoaded', () => {

  const modalPanel = document.querySelector('#ratesModal .modal-panel');

  if (!modalPanel) return; // якщо ще нема — не падаємо

  let startY = 0;
  let currentY = 0;
  let dragging = false;

  modalPanel.addEventListener('touchstart', (e) => {
    startY = e.touches[0].clientY;
    dragging = true;
  });

  modalPanel.addEventListener('touchmove', (e) => {
    if (!dragging) return;
    currentY = e.touches[0].clientY;
    const diff = currentY - startY;

    if (diff > 0) {
      modalPanel.style.transform = `translateY(${diff}px)`;
    }
  });

  modalPanel.addEventListener('touchend', () => {
    dragging = false;
    const diff = currentY - startY;

    if (diff > 120) {
      closeRatesModal();
    }

    modalPanel.style.transform = '';
  });

});










let currentRate = null;
let currentCurrency = null;
let mode = 'buy';

// відкриття обмінника
window.openExchange = function(currency, purchase, sale){
  currentCurrency = currency;
  currentRate = { purchase: Number(purchase), sale: Number(sale) };

  document.getElementById('exchangeBox')?.classList.remove('hidden');
  document.querySelector('#ratesModal .modal-panel')?.classList.add('expanded');


  syncExchangeUI();
  updateExchange('from');
};

function syncExchangeUI(){
  const fromLabel = document.getElementById('exFromLabel');
  const toLabel   = document.getElementById('exToLabel');
  const fromInput = document.getElementById('exFrom');
  const toInput   = document.getElementById('exTo');

  if (!fromLabel || !toLabel || !fromInput || !toInput) return;

  if (mode === 'buy') {
    // Купуємо валюту: UAH -> CUR
    fromLabel.textContent = 'UAH';
    toLabel.textContent   = currentCurrency || '';
    fromInput.placeholder = 'Віддаємо (грн)';
    toInput.placeholder   = 'Отримуємо (валюта)';
  } else {
    // Продаємо валюту: CUR -> UAH
    fromLabel.textContent = currentCurrency || '';
    toLabel.textContent   = 'UAH';
    fromInput.placeholder = 'Віддаємо (валюта)';
    toInput.placeholder   = 'Отримуємо (грн)';
  }
}



document.addEventListener('click', (e) => {
  if (e.target.id === 'modeBuy')  {
    mode = 'buy';
    document.getElementById('modeBuy').classList.add('active');
    document.getElementById('modeSell').classList.remove('active');
    syncExchangeUI();
    updateExchange('from');
  }

  if (e.target.id === 'modeSell') {
    mode = 'sell';
    document.getElementById('modeSell').classList.add('active');
    document.getElementById('modeBuy').classList.remove('active');
    syncExchangeUI();
    updateExchange('from');
  }
});


document.addEventListener('input', (e) => {
  if (e.target.id === 'exFrom') updateExchange('from');
  if (e.target.id === 'exTo')   updateExchange('to');
});



window.selectRateCard = function(card){
  document.querySelectorAll('.rate-card').forEach(c => c.classList.remove('active'));
  card.classList.add('active');
};



function updateExchange(source = 'from'){
  const fromInput = document.getElementById('exFrom');
  const toInput   = document.getElementById('exTo');
  if (!fromInput || !toInput || !currentRate || !currentCurrency) return;

  const a = parseFloat(fromInput.value || 0);
  const b = parseFloat(toInput.value || 0);

  const sale = Number(currentRate.sale);       // банк продає валюту (ти купуєш)
  const buy  = Number(currentRate.purchase);   // банк купує валюту (ти продаєш)

  // BUY: UAH -> CUR, курс = sale (UAH за 1 CUR)
  if (mode === 'buy') {
    if (source === 'from') {
      toInput.value = a ? (a / sale).toFixed(2) : '';
    } else {
      fromInput.value = b ? (b * sale).toFixed(2) : '';
    }
    return;
  }

  // SELL: CUR -> UAH, курс = purchase (UAH за 1 CUR)
  if (source === 'from') {
    toInput.value = a ? (a * buy).toFixed(2) : '';
  } else {
    fromInput.value = b ? (b / buy).toFixed(2) : '';
  }
}

//////////////////////////////////////////////////////////////////////////////////////
// КЕШ співробітників — МОДАЛКА
//////////////////////////////////////////////////////////////////////////////////////
// ВІДКРИТТЯ МОДАЛКИ
// ВІДКРИТТЯ МОДАЛКИ
window.openStaffCash = function () {
  

  const OWNER_ACTORS = ['hlushchenko', 'kolisnyk']; // власники (виключаємо)

  // ✅ всі кеш-рахунки, де owner заданий і це НЕ власники
  const staffWallets = state.wallets.filter(w => {
    const owner = w.owner;
    if (!owner) return false;
    if (OWNER_ACTORS.includes(owner)) return false;
    if ((w.type || 'cash') !== 'cash') return false; // на всяк: тільки cash
    return true;
  });

  const ROLE_LABELS = {
    accountant: 'Соловей',
    foreman: 'Оніпко',
    ntv: 'НТВ',
    serviceman_1: 'Савенков',
    serviceman_2: 'Малінін',
  };

  const list = document.getElementById('staffCashList');

  list.innerHTML = staffWallets.map(w => {
    const label = ROLE_LABELS[w.owner] || w.owner;

    return `
      <div class="rate-card" onclick="openStaffWallet(${w.id})">
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <div class="rate-title">${w.name}</div>
          <div class="staff-badge">${label}</div>
        </div>

        <div style="margin-top:6px;font-size:16px;font-weight:700;">
          ${Number(w.balance).toFixed(2)} ${w.currency}
        </div>
      </div>
    `;
  }).join('');

  document.getElementById('staffCashModal').classList.remove('hidden');
  document.body.classList.add('modal-open');
};



// ЗАКРИТТЯ
window.closeStaffCash = function(){
  document.getElementById('staffCashModal').classList.add('hidden');
  document.body.classList.remove('modal-open');
}


// ВІДКРИТТЯ РАХУНКУ
window.openStaffWallet = async function(walletId){
  closeStaffCash();
  await loadEntries(walletId);
}

// Exposed for employee-transfer.js: reload entries after accepting a transfer
window.reloadCurrentWallet = async function() {
  if (state.selectedWalletId) {
    await loadEntries(state.selectedWalletId);
    await loadWallets();
  }
};

window.getSelectedWalletId = () => state.selectedWalletId;


document.addEventListener('click', (e) => {
  if (e.target.closest('#staffCashClose')) window.closeStaffCash?.();
}); 


document.addEventListener('DOMContentLoaded', () => {

  const staffPanel = document.querySelector('#staffCashModal .modal-panel');
  if (!staffPanel) return;

  let startY = 0;
  let currentY = 0;
  let dragging = false;

  staffPanel.addEventListener('touchstart', e => {
    startY = e.touches[0].clientY;
    dragging = true;
  });

  staffPanel.addEventListener('touchmove', e => {
    if (!dragging) return;
    currentY = e.touches[0].clientY;
    const diff = currentY - startY;

    if (diff > 0) {
      staffPanel.style.transform = `translateY(${diff}px)`;
    }
  });

  staffPanel.addEventListener('touchend', () => {
    dragging = false;
    const diff = currentY - startY;

    if (diff > 120) closeStaffCash();

    staffPanel.style.transform = '';
  });

});



window.openReceipt = function(url){
  const modal = document.getElementById('receiptModal');
  const img   = document.getElementById('receiptFullImg');
  const aOpen = document.getElementById('receiptOpenNew');
  const aDown = document.getElementById('receiptDownload');

  if (!modal || !img) return;

  img.src = url;
  aOpen.href = url;
  aDown.href = url;

  modal.classList.remove('hidden');
  document.body.classList.add('modal-open');
};

window.closeReceiptModal = function(){
  const modal = document.getElementById('receiptModal');
  const img   = document.getElementById('receiptFullImg');
  if (!modal) return;

  modal.classList.add('hidden');
  document.body.classList.remove('modal-open');
  if (img) img.src = '';
};

// клік по ✕
document.addEventListener('click', (e) => {
  if (e.target.closest('#receiptClose')) closeReceiptModal();
});

// Esc
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') closeReceiptModal();
});




// =======================
// 🔊 SOUND + 📳 VIBRO
// =======================
let SND = {
  leave: null,
  moneta: null,
  alarm: null,
  unlocked: false,
};

document.addEventListener('DOMContentLoaded', () => {
  SND.leave  = document.getElementById('sndLeave');
  SND.moneta = document.getElementById('sndMoneta');
  SND.alarm  = document.getElementById('sndAlarm');

  const unlock = () => {
    if (SND.unlocked) return;
    SND.unlocked = true;

    [SND.leave, SND.moneta, SND.alarm].forEach(a => {
      if (!a) return;
      try {
        a.muted = true;
        a.play().then(() => {
          a.pause();
          a.currentTime = 0;
          a.muted = false;
        }).catch(() => {
          a.muted = false;
        });
      } catch {}
    });
  };

  // перший жест користувача "розблоковує" аудіо (особливо iOS)
  document.addEventListener('touchstart', unlock, { once: true, passive: true });
  document.addEventListener('click', unlock, { once: true });
});

function playSound(a, volume = 0.9) {
  if (!a) return;
  try {
    a.volume = volume;
    a.currentTime = 0;
    a.play().catch(()=>{});
    // Inform shared dedup so notif-bell won't double-play within 2s
    if (window._sgBumpSound) window._sgBumpSound();
  } catch {}
}

function vibrate(pattern) {
  // Android/Chrome: працює; iOS: зазвичай ігнорує (це нормально)
  try { navigator.vibrate?.(pattern); } catch {}
}

function entryFeedback(type) {
  if (type === 'income') {
    playSound(SND.moneta, 0.85);
    vibrate([18, 22, 18]);
  } else if (type === 'expense') {
    playSound(SND.leave, 0.9);
    vibrate([30]);
  }
}

function deleteFeedback() {
  playSound(SND.alarm, 1.0);
  vibrate([60, 40, 60, 40, 120]); // “сирена”
}
