//////////////////////////////////////////////////////////////////
// Рендер коментаря з категорією
//////////////////////////////////////////////////////////////////

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

//////////////////////////////////////////////////////////////////
// Баланс відкритого рахунку
//////////////////////////////////////////////////////////////////

function renderWalletBalance(){
  const sum = state.entries.reduce((acc, e) => {
    return acc + Number(e.signed_amount || 0);
  }, 0);

  const cls = sum >= 0 ? 'pos' : 'neg';
  elWalletBalance.className = `big ${cls}`;
  elWalletBalance.textContent =
    `${fmt(sum)} ${state.selectedWallet.currency}`;
}

//////////////////////////////////////////////////////////////////
// Summary (разом / кількість / середнє)
//////////////////////////////////////////////////////////////////

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

//////////////////////////////////////////////////////////////////
// Таблиця операцій
//////////////////////////////////////////////////////////////////

function renderEntries(){
  elEntries.innerHTML = '';

  state.entries.forEach(e => {

    const signed = Number(e.signed_amount || 0);
    const cls = signed >= 0 ? 'pos' : 'neg';
    const sign = signed >= 0 ? '+' : '';

    const editable =
      isToday(e.posting_date) &&
      canWriteWallet(state.selectedWallet.owner);

    const isActive = state.activeEntryId === e.id;

    const d = new Date(e.posting_date);
    const dateHtml = `
      ${String(d.getDate()).padStart(2,'0')}.${String(d.getMonth()+1).padStart(2,'0')}
      <div style="font-size:11px;opacity:.6">${d.getFullYear()}р</div>
    `;

    const tr = document.createElement('tr');
    tr.className = `entry-row ${isActive ? 'active' : ''}`;

    tr.onclick = (ev) => {
      ev.stopPropagation();
      state.activeEntryId = (state.activeEntryId === e.id) ? null : e.id;
      renderEntries();
    };

    tr.innerHTML = `
      <td class="muted date-cell">
        ${dateHtml}
      </td>

      <td class="entry-comment">
        ${renderComment(e.comment)}

        ${editable ? `
          <div class="entry-actions">
            <button onclick="editEntry(${e.id}); event.stopPropagation()">✏️</button>
            <button onclick="deleteEntry(${e.id}); event.stopPropagation()">🗑</button>
          </div>
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
}
