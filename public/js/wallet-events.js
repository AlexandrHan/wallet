///////////////////////////////////////////////////////////////
// ===== VIEW SWITCH (мій / партнер)
///////////////////////////////////////////////////////////////

window.setViewOwner = function(owner){
  state.viewOwner = owner;

  btnViewK.classList.toggle('active', owner === 'kolisnyk');
  btnViewH.classList.toggle('active', owner === 'hlushchenko');

  const isMineView = (owner === state.actor);
  viewHint.textContent = isMineView ? 'Редагування' : 'Перегляд';
  btnAddWallet.style.display = isMineView ? '' : 'none';

  state.selectedWalletId = null;
  state.selectedWallet = null;
  state.entries = [];

  elWalletTitle.textContent = '';
  elEntries.innerHTML = '';
  roTag.style.display = 'none';
  btnIncome.disabled = true;
  btnExpense.disabled = true;

  showWallets();
  loadWallets();
};

///////////////////////////////////////////////////////////////
// ===== NAVIGATION
///////////////////////////////////////////////////////////////

window.showWallets = function(){
  opsView.style.display = 'none';
  walletsView.style.display = '';
};

window.showOps = function(){
  walletsView.style.display = 'none';
  opsView.style.display = '';
};

///////////////////////////////////////////////////////////////
// ===== BUTTONS
///////////////////////////////////////////////////////////////

document.getElementById('refresh').onclick = e => {
  e.preventDefault();
  loadWallets();
};

btnBack.onclick = e => {
  e.preventDefault();
  showWallets();
};

btnIncome.onclick = e => {
  e.preventDefault();
  openEntrySheet('income');
};

btnExpense.onclick = e => {
  e.preventDefault();
  openEntrySheet('expense');
};

btnAddWallet.onclick = e => {
  e.preventDefault();
  openWalletSheet();
};

btnViewK.onclick = e => {
  e.preventDefault();
  setViewOwner('kolisnyk');
};

btnViewH.onclick = e => {
  e.preventDefault();
  setViewOwner('hlushchenko');
};

///////////////////////////////////////////////////////////////
// ===== BURGER MENU
///////////////////////////////////////////////////////////////

const burgerBtn = document.getElementById('burgerBtn');
const burgerMenu = document.getElementById('burgerMenu');

burgerBtn.onclick = (e) => {
  e.stopPropagation();
  burgerMenu.classList.toggle('hidden');
};

document.addEventListener('click', () => {
  burgerMenu.classList.add('hidden');
});

///////////////////////////////////////////////////////////////
// ===== ESC CLOSE SHEETS
///////////////////////////////////////////////////////////////

document.addEventListener('keydown', (e) => {
  if (e.key !== 'Escape') return;

  if (!sheetEntry.classList.contains('hidden')) closeEntrySheet();
  if (!sheetWallet.classList.contains('hidden')) closeWalletSheet();
});

///////////////////////////////////////////////////////////////
// ===== INTERNET STATUS
///////////////////////////////////////////////////////////////

window.addEventListener('offline', () => {
  alert('❌ Втрачено інтернет-з’єднання');
});

///////////////////////////////////////////////////////////////
// ===== APP INIT
///////////////////////////////////////////////////////////////

document.addEventListener('DOMContentLoaded', () => {

  if (!state.actor) {
    alert('Не задано actor у користувача');
    return;
  }

  document.getElementById('actorTag').textContent = state.actor;

  setViewOwner(state.actor);
});
