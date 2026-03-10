//////////////////////////////////////////////////////////////////
// Валютна іконка
//////////////////////////////////////////////////////////////////

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

//////////////////////////////////////////////////////////////////
// Сховати splash
//////////////////////////////////////////////////////////////////

function hideSplash(){
  const el = document.getElementById('appSplash');
  if (!el) return;
  el.classList.add('hide');
}

//////////////////////////////////////////////////////////////////
// Рендер карток рахунків (КЕШ + БАНКИ)
//////////////////////////////////////////////////////////////////

function renderWallets() {
  if (isRenderingWallets) return;
  isRenderingWallets = true;

  elWallets.innerHTML = '';

  // ================= CASH =================
  const visible = state.wallets.filter(w => w.owner === state.viewOwner);

  visible.forEach(w => {
    const writable = canWriteWallet(w.owner);

    const card = document.createElement('div');
    card.className = 'card' + (writable ? '' : ' ro');
    card.addEventListener('click', () => loadEntries(w.id));

    const bal = Number(w.balance || 0);
    const balCls = bal >= 0 ? 'pos' : 'neg';

    card.classList.add('account-card', 'cash-account');
    card.dataset.accountId = w.id;

    card.innerHTML = `
      <div class="card-top">
        ${renderCurrencyIcon(w.currency)}
      </div>

      <div style="margin-top:-4rem;font-weight:800;">${w.name}</div>
      <div class="big ${balCls}" style="margin-top:10px;">
        ${fmt(bal)} ${w.currency}
      </div>
      <div class="muted">Cash account</div>

      <div class="pirate-overlay">
        <div class="pirate-skull">☠️</div>
        <div class="pirate-text"></div>
      </div>
    `;

    elWallets.appendChild(card);
  });

  // ================= BANK =================
  const visibleBanks = state.bankAccounts;

  visibleBanks.forEach(bank => {
    const card = document.createElement('div');
    card.className = 'card ro';
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
      ${logo}
      <div class="card-top">
        <div class="muted">${bank.currency}</div>
      </div>

      <div style="margin-top:6px;font-weight:800;">${bank.name}</div>
      <div class="big ${bank.balance >= 0 ? 'pos' : 'neg'}">
        ${fmt(bank.balance)} ${bank.currency}
      </div>
      <div class="muted">Bank account</div>
    `;

    card.onclick = () => openBankAccount(bank);
    elWallets.appendChild(card);
  });

  if (!visible.length && !visibleBanks.length) {
    elWallets.innerHTML = '<div class="muted">Немає рахунків</div>';
  }

  isRenderingWallets = false;
  initPirateDelete();
  hideSplash();
}
