//////////////////////////////////////////////////////////////////
// Відкриття банківського рахунку
//////////////////////////////////////////////////////////////////

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

  //////////////////////////////////////////////////////////////
  // 🟢 MONOBANK
  //////////////////////////////////////////////////////////////
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

  //////////////////////////////////////////////////////////////
  // 🟣 PRIVAT
  //////////////////////////////////////////////////////////////
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

  //////////////////////////////////////////////////////////////
  // 🟡 UKRGAS
  //////////////////////////////////////////////////////////////
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
