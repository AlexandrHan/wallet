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
  const historySection   = document.getElementById('etHistorySection');
  const historyList      = document.getElementById('etHistoryList');

  // ── Helpers ──────────────────────────────────────────────────
  async function apiFetch(url, opts = {}) {
    const res = await fetch(url, {
      headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json', ...(opts.headers || {}) },
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

  function statusLabel(status) {
    return { pending: 'Очікує', accepted: 'Підтверджено', declined: 'Відхилено', cancelled: 'Скасовано' }[status] ?? status;
  }

  function statusColor(status) {
    return { pending: '#f59e0b', accepted: '#22c55e', declined: '#ef4444', cancelled: '#6b7280' }[status] ?? '#6b7280';
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
      // Refresh history
      if (IS_OWNER) loadOwnerHistory();
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

  function onWalletOpened() {
    if (!IS_OWNER) {
      loadPendingForEmployee();
      return;
    }
    // For owners: show "Transfer" button only on cash wallets they own
    const currency = getCurrentWalletCurrency();
    if (currency && btnTransfer) {
      btnTransfer.style.display = '';
      loadStaffWallets(currency);
      loadOwnerHistory();
    }
  }

  function onWalletClosed() {
    if (btnTransfer) btnTransfer.style.display = 'none';
    if (historySection) historySection.style.display = 'none';
    if (pendingBanner) pendingBanner.style.display = 'none';
  }

  btnTransfer?.addEventListener('click', openModal);

  // ── Owner: history ─────────────────────────────────────────────
  async function loadOwnerHistory() {
    if (!historyList || !historySection) return;

    const res = await apiFetch('/api/employee-transfers/history');
    if (!res.ok) return;
    const transfers = await res.json();

    if (!transfers.length) {
      historySection.style.display = 'none';
      return;
    }

    historySection.style.display = '';
    historyList.innerHTML = '';

    transfers.forEach(t => {
      const isToday = (t.created_at ?? '').slice(0, 10) === todayStr();
      const canCancel = t.status === 'accepted' && isToday;

      const el = document.createElement('div');
      el.className = 'card';
      el.style.cssText = 'padding:10px 12px; margin-bottom:8px; display:flex; justify-content:space-between; align-items:center; gap:8px;';
      el.innerHTML = `
        <div style="flex:1; min-width:0;">
          <div style="font-size:.85rem; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
            ${escHtml(t.employee_owner ?? t.employee_wallet_name ?? '—')}
          </div>
          <div style="font-size:.75rem; color:var(--muted);">
            ${(t.created_at ?? '').slice(0, 16).replace('T', ' ')}
            ${t.comment ? '· ' + escHtml(t.comment) : ''}
          </div>
        </div>
        <div style="text-align:right; white-space:nowrap;">
          <div style="font-weight:700;">${fmt(t.amount, t.currency)}</div>
          <div style="font-size:.75rem; color:${statusColor(t.status)};">${statusLabel(t.status)}</div>
        </div>
        ${canCancel ? `<button class="btn danger et-cancel-btn" data-id="${t.id}" style="flex-shrink:0;">Скасувати</button>` : ''}
      `;
      historyList.appendChild(el);
    });

    historyList.querySelectorAll('.et-cancel-btn').forEach(btn => {
      btn.addEventListener('click', async () => {
        if (!confirm('Скасувати передачу коштів?')) return;
        btn.disabled = true;
        const res = await apiFetch(`/api/employee-transfers/${btn.dataset.id}/cancel`, { method: 'POST' });
        const data = await res.json();
        if (!res.ok) { alert(data.error ?? 'Помилка'); btn.disabled = false; return; }
        loadOwnerHistory();
      });
    });
  }

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
    loadPendingForEmployee();
  }
});
