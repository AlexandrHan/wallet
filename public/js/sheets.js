//////////////////////////////////////////////////////////////////
// SHEET: Відкрити форму операції (дохід / витрата)
//////////////////////////////////////////////////////////////////

window.openEntrySheet = function(type){

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
};

//////////////////////////////////////////////////////////////////
// Закрити форму операції
//////////////////////////////////////////////////////////////////

window.closeEntrySheet = function(){
  sheetEntry.classList.add('hidden');
  sheetType = null;
  state.editingEntryId = null;
  sheetEntry.classList.remove('entry-income', 'entry-expense');
};

//////////////////////////////////////////////////////////////////
// Створити або оновити операцію
//////////////////////////////////////////////////////////////////

window.submitEntry = async function(entry_type, amount, comment){

  if (!checkOnline()) return false;

  const finalComment = sheetCategory.value
    ? `[${sheetCategory.value}] ${comment || ''}`
    : (comment || '');

  const isEdit = !!state.editingEntryId;

  const url = isEdit
    ? `/api/entries/${state.editingEntryId}`
    : '/api/entries';

  const method = isEdit ? 'PUT' : 'POST';

  const payload = isEdit
    ? { amount: Number(amount), comment: finalComment }
    : {
        wallet_id: state.selectedWalletId,
        entry_type,
        amount: Number(amount),
        comment: finalComment
      };

  const res = await fetch(url, {
    method,
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': CSRF
    },
    body: JSON.stringify(payload)
  });

  if (!res.ok) {
    const txt = await res.text();
    alert(txt || 'Помилка');
    return false;
  }

  state.editingEntryId = null;

  await loadEntries(state.selectedWalletId);
  await loadWallets();
  return true;
};

//////////////////////////////////////////////////////////////////
// Кнопка підтвердження у формі
//////////////////////////////////////////////////////////////////

sheetEntry.querySelector('.sheet-backdrop').onclick = closeEntrySheet;

sheetConfirm.onclick = async () => {
  const amount = Number(sheetAmount.value);
  if (!amount || amount <= 0) {
    alert('Введи суму більше 0');
    return;
  }
  const ok = await submitEntry(sheetType, amount, sheetComment.value);
  if (ok) closeEntrySheet();
};

//////////////////////////////////////////////////////////////////
// SHEET: Створення гаманця
//////////////////////////////////////////////////////////////////

window.openWalletSheet = function(){
  if (state.viewOwner !== state.actor) {
    alert('У режимі перегляду партнера створення рахунків заборонено');
    return;
  }
  walletName.value = '';
  walletCurrency.value = 'UAH';
  sheetWallet.classList.remove('hidden');
  setTimeout(() => walletName.focus(), 50);
};

window.closeWalletSheet = function(){
  sheetWallet.classList.add('hidden');
};

//////////////////////////////////////////////////////////////////
// Створення нового cash рахунку
//////////////////////////////////////////////////////////////////

window.submitWallet = async function(name, currency){

  const res = await fetch('/api/wallets', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': CSRF
    },
    body: JSON.stringify({ name, currency, type: 'cash' })
  });

  if (!res.ok) {
    const txt = await res.text();
    alert(`Помилка: ${res.status}\n${txt.slice(0, 300)}`);
    return false;
  }

  await loadWallets();
  return true;
};

sheetWallet.querySelector('.sheet-backdrop').onclick = closeWalletSheet;

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

//////////////////////////////////////////////////////////////////
// Редагування існуючої операції
//////////////////////////////////////////////////////////////////

window.editEntry = function(id){
  const entry = state.entries.find(e => e.id === id);
  if (!entry) return;

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
};

//////////////////////////////////////////////////////////////////
// Видалення операції
//////////////////////////////////////////////////////////////////

window.deleteEntry = async function(id){
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
};
