const CSRF = document.querySelector('meta[name="csrf-token"]').content;

window.AUTH_ACTOR = window.AUTH_ACTOR || null;

window.state = {
  actor: AUTH_ACTOR,
  viewOwner: AUTH_ACTOR,
  wallets: [],
  bankAccounts: [],
  selectedWalletId: null,
  selectedWallet: null,
  entries: [],
  activeEntryId: null,
  delArmedId: null,
  delTimer: null,
  editingEntryId: null
};

let isRenderingWallets = false;
