/**
 * Employee Cash Transfer — Owner → Employee
 * Runs after wallet.js (both deferred), safe to access wallet.js globals.
 */

document.addEventListener('DOMContentLoaded', () => {
  const CSRF     = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
  const AUTH     = window.AUTH_USER;
  const IS_OWNER = AUTH?.role === 'owner';

  // ── DOM refs ──────────────────────────────────────────────────
  const btnTransfer      = document.getElementById('btnEmployeeTransfer');
  const modal            = document.getElementById('etModal');
  const modalBackdrop    = document.getElementById('etModalBackdrop');
  const selectEmployee   = document.getElementById('etEmployeeSelect');
  const inputAmount      = document.getElementById('etAmount');
  const inputComment     = document.getElementById('etComment');
  const btnCancel        = document.getElementById('etCancel');
  const btnSubmit        = document.getElementById('etSubmit');
  const pendingBanner    = document.getElementById('etPendingBanner');

  // ── Helpers ──────────────────────────────────────────────────
  async function apiFetch(url, opts = {}) {
    const res = await fetch(url, {
      headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json', 'Accept': 'application/json', ...(opts.headers || {}) },
      ...opts,
    });
    return res;
  }

  function fmt(amount, currency) {
    return Number(amount).toLocaleString('uk-UA', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + currency;
  }

  function todayStr() {
    return new Date().toISOString().slice(0, 10);
  }

  // ── Modal open/close ─────────────────────────────────────────
  function openModal() {
    modal?.classList.remove('hidden');
    inputAmount.value = '';
    inputComment.value = '';
    inputAmount.focus();
  }

  function closeModal() {
    modal?.classList.add('hidden');
  }

  btnCancel?.addEventListener('click', closeModal);
  modalBackdrop?.addEventListener('click', closeModal);

  // ── Load staff wallets into dropdown ──────────────────────────
  async function loadStaffWallets(currency) {
    const res = await apiFetch('/api/wallets');
    if (!res.ok) return;
    const wallets = await res.json();
    const OWNERS = ['hlushchenko', 'kolisnyk'];

    selectEmployee.innerHTML = '<option value="">— Оберіть співробітника —</option>';

    wallets
      .filter(w => w.owner && !OWNERS.includes(w.owner) && w.currency === currency)
      .forEach(w => {
        const opt = document.createElement('option');
        opt.value = w.id;
        opt.textContent = `${w.owner} • ${w.name} • ${fmt(w.balance ?? 0, w.currency)}`;
        selectEmployee.appendChild(opt);
      });
  }

  // ── Create transfer ───────────────────────────────────────────
  btnSubmit?.addEventListener('click', async () => {
    const walletId = Number(selectEmployee.value);
    const amount   = parseFloat(inputAmount.value);
    const comment  = inputComment.value.trim();

    if (!walletId) { alert('Оберіть співробітника'); return; }
    if (!amount || amount <= 0) { alert('Введіть суму'); return; }

    btnSubmit.disabled = true;
    btnSubmit.textContent = '…';

    try {
      const res = await apiFetch('/api/employee-transfers', {
        method: 'POST',
        body: JSON.stringify({ employee_wallet_id: walletId, amount, comment: comment || null }),
      });
      const data = await res.json();

      if (!res.ok) { alert(data.error ?? 'Помилка'); return; }

      closeModal();
    } catch (e) {
      alert('Помилка мережі');
    } finally {
      btnSubmit.disabled = false;
      btnSubmit.textContent = 'Передати';
    }
  });

  // ── Show/hide button + load data when wallet is opened ────────
  // Wallet.js dispatches no custom event, so we poll the DOM state
  // by observing opsView display changes.
  const opsView = document.getElementById('opsView');
  if (opsView) {
    const obs = new MutationObserver(() => {
      const visible = opsView.style.display !== 'none';
      if (visible) {
        onWalletOpened();
      } else {
        onWalletClosed();
      }
    });
    obs.observe(opsView, { attributes: true, attributeFilter: ['style'] });
  }

  function getCurrentWalletCurrency() {
    // wallet.js sets walletTitle text to "Name • CURRENCY"
    const title = document.getElementById('walletTitle')?.textContent ?? '';
    const m = title.match(/•\s*([A-Z]{3})\s*$/);
    return m ? m[1] : null;
  }

  function getCurrentWalletOwner() {
    // wallet.js exposes state.selectedWallet but it's not on window.
    // Read from the walletTitle aria or from data attributes if available.
    // Fallback: read AUTH_USER.actor for the current user.
    return AUTH?.actor ?? null;
  }

  // ── Owner: pending transfers — polling + virtual rows in entries table ──
  let _pollTimer      = null;
  let _pendingIds     = new Set();
  let _pendingList    = [];   // full transfer objects for current wallet
  const elEntries = document.getElementById('entries');

  // Inject pending-transfer rows at the top of the entries tbody
  function injectPendingRows() {
    if (!elEntries || !IS_OWNER) return;
    // Remove previously injected rows
    elEntries.querySelectorAll('.et-pending-row').forEach(r => r.remove());

    const walletId = typeof window.getSelectedWalletId === 'function'
      ? window.getSelectedWalletId() : null;

    const toShow = walletId
      ? _pendingList.filter(t => t.from_wallet_id === walletId)
      : _pendingList;

    if (!toShow.length) return;

    // Prepend rows (newest first)
    [...toShow].reverse().forEach(t => {
      const tr = document.createElement('tr');
      tr.className = 'entry-row et-pending-row';
      tr.style.cssText = 'opacity:.75;';

      const today = new Date();
      const dd = String(today.getDate()).padStart(2, '0');
      const mm = String(today.getMonth() + 1).padStart(2, '0');
      const yyyy = today.getFullYear();

      const name = escHtml(t.employee_owner ?? t.employee_wallet_name ?? '—');
      const comment = t.comment ? ' · ' + escHtml(t.comment) : '';

      tr.innerHTML = `
        <td class="muted date-cell">
          ${dd}.${mm}
          <div style="font-size:11px;opacity:.6">${yyyy}р</div>
        </td>
        <td class="entry-comment">
          ${name}${comment}
          <span class="transfer-badge">⏳ очікує підтвердження</span>
        </td>
        <td class="amount-cell neg">
          -${fmtAmount(t.amount, t.currency)}
        </td>
      `;

      elEntries.prepend(tr);
    });
  }

  function fmtAmount(amount, currency) {
    const sym = { UAH: '₴', USD: '$', EUR: '€' }[currency] ?? currency;
    return Number(amount).toLocaleString('uk-UA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
      + ' ' + sym;
  }

  // Re-inject after wallet.js re-renders the entries tbody
  window.onEntriesRendered = () => { if (IS_OWNER) injectPendingRows(); };

  async function fetchAndInjectPending() {
    const res = await apiFetch('/api/employee-transfers/pending');
    if (!res.ok) return;
    _pendingList = await res.json();
    _pendingIds  = new Set(_pendingList.map(t => t.id));
    injectPendingRows();
  }

  async function pollOwnerPending() {
    const res = await apiFetch('/api/employee-transfers/pending');
    if (!res.ok) return;
    const transfers = await res.json();
    const currentIds = new Set(transfers.map(t => t.id));

    // If any previously-tracked pending transfer is gone → employee responded
    let changed = false;
    _pendingIds.forEach(id => { if (!currentIds.has(id)) changed = true; });

    _pendingList = transfers;
    _pendingIds  = currentIds;
    injectPendingRows();

    if (changed && typeof window.reloadCurrentWallet === 'function') {
      await window.reloadCurrentWallet();
    }
  }

  function startOwnerPoll() {
    stopOwnerPoll();
    fetchAndInjectPending();
    _pollTimer = setInterval(pollOwnerPending, 10_000);
  }

  function stopOwnerPoll() {
    if (_pollTimer) { clearInterval(_pollTimer); _pollTimer = null; }
    _pendingList = [];
    _pendingIds  = new Set();
    if (elEntries) elEntries.querySelectorAll('.et-pending-row').forEach(r => r.remove());
  }

  // ── Employee polling ──────────────────────────────────────────
  let _empPollTimer = null;

  function startEmployeePoll() {
    stopEmployeePoll();
    loadPendingForEmployee();
    _empPollTimer = setInterval(loadPendingForEmployee, 10_000);
  }

  function stopEmployeePoll() {
    if (_empPollTimer) { clearInterval(_empPollTimer); _empPollTimer = null; }
  }

  function onWalletOpened() {
    if (!IS_OWNER) {
      startEmployeePoll();
      return;
    }
    // For owners: show "Transfer" button only on cash wallets they own
    const currency = getCurrentWalletCurrency();
    if (currency && btnTransfer) {
      btnTransfer.style.display = '';
      loadStaffWallets(currency);
    }
    startOwnerPoll();
  }

  function onWalletClosed() {
    if (btnTransfer) btnTransfer.style.display = 'none';
    if (pendingBanner) pendingBanner.style.display = 'none';
    stopOwnerPoll();
    stopEmployeePoll();
  }

  btnTransfer?.addEventListener('click', openModal);

  // ── Employee: pending banner ───────────────────────────────────
  async function loadPendingForEmployee() {
    if (!pendingBanner) return;

    const res = await apiFetch('/api/employee-transfers/pending');
    if (!res.ok) return;
    const transfers = await res.json();

    if (!transfers.length) {
      pendingBanner.style.display = 'none';
      return;
    }

    pendingBanner.style.display = '';
    pendingBanner.innerHTML = '';

    transfers.forEach(t => {
      const el = document.createElement('div');
      el.className = 'card';
      el.style.cssText = 'padding:12px 14px; margin-bottom:8px; border:1px solid #f59e0b44; background:rgba(245,158,11,.08);';
      el.innerHTML = `
        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:8px; margin-bottom:10px;">
          <div>
            <div style="font-weight:700; font-size:.9rem;">💰 Вам передають кошти</div>
            <div style="font-size:.8rem; color:var(--muted);">від: ${escHtml(t.sender_name ?? '—')}</div>
            ${t.comment ? `<div style="font-size:.8rem; color:var(--muted); margin-top:2px;">${escHtml(t.comment)}</div>` : ''}
          </div>
          <div style="font-weight:800; font-size:1.05rem; white-space:nowrap;">${fmt(t.amount, t.currency)}</div>
        </div>
        <div class="row" style="gap:8px;">
          <button class="btn primary et-accept-btn" data-id="${t.id}" style="flex:1;">Отримати</button>
          <button class="btn danger et-decline-btn" data-id="${t.id}" style="flex:1;">Відхилити</button>
        </div>
      `;
      pendingBanner.appendChild(el);
    });

    pendingBanner.querySelectorAll('.et-accept-btn').forEach(btn => {
      btn.addEventListener('click', async () => {
        btn.disabled = true;
        const res = await apiFetch(`/api/employee-transfers/${btn.dataset.id}/accept`, { method: 'POST' });
        const data = await res.json();
        if (!res.ok) { alert(data.error ?? 'Помилка'); btn.disabled = false; return; }
        loadPendingForEmployee();
        // Reload wallet entries if wallet.js exposes a refresh function
        if (typeof window.reloadCurrentWallet === 'function') window.reloadCurrentWallet();
      });
    });

    pendingBanner.querySelectorAll('.et-decline-btn').forEach(btn => {
      btn.addEventListener('click', async () => {
        if (!confirm('Відхилити передачу?')) return;
        btn.disabled = true;
        const res = await apiFetch(`/api/employee-transfers/${btn.dataset.id}/decline`, { method: 'POST' });
        const data = await res.json();
        if (!res.ok) { alert(data.error ?? 'Помилка'); btn.disabled = false; return; }
        loadPendingForEmployee();
      });
    });
  }

  // ── XSS-safe string escape ────────────────────────────────────
  function escHtml(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  // ── Check pending on initial load (for employees) ─────────────
  if (!IS_OWNER) {
    startEmployeePoll();
  }
});
