(() => {
  ///////////////////////////////////////////////////////////////
  // helpers
  ///////////////////////////////////////////////////////////////

  const PALETTE = ['#66f2a8', '#4c7dff', '#ffb86c', '#ff6b6b', '#9aa6bc'];

  function fmt0(n) {
    const num = Number(String(n ?? 0).replace(',', '.')) || 0;
    return new Intl.NumberFormat('uk-UA', { maximumFractionDigits: 0 })
      .format(Math.round(num))
      .replace(/\u00A0/g, ' ');
  }

  function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, (m) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    }[m]));
  }

  function ensureChartJs() {
    return new Promise((resolve, reject) => {
      if (window.Chart) return resolve();

      const s = document.createElement('script');
      s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
      s.onload = () => resolve();
      s.onerror = () => reject(new Error('Не вдалося завантажити Chart.js'));
      document.head.appendChild(s);
    });
  }

  function getCanvasCtx(id) {
    const c = document.getElementById(id);
    return c ? c.getContext('2d') : null;
  }

  function asPositiveRows(labels, values) {
    const rows = (labels || [])
      .map((label, i) => ({ label: String(label), value: Number((values || [])[i] || 0) }))
      .filter(r => r.value > 0);

    rows.sort((a, b) => b.value - a.value);
    return rows;
  }

  ///////////////////////////////////////////////////////////////
  // UI
  ///////////////////////////////////////////////////////////////

  function makeCard() {
    if (document.getElementById('debtChartsCard')) return;

    const main = document.querySelector('main.stock-wrap');
    if (!main) return;

    const card = document.createElement('div');
    card.className = 'card';
    card.id = 'debtChartsCard';
    card.style.marginBottom = '14px';

    card.innerHTML = `
      <details id="debtChartsDetails">
        <summary class="debt-hero">
        <div class="debt-hero-head">
            <div class="debt-hero-title">SG Holding</div>
            <div class="debt-hero-pill">USD</div>
        </div>

        <div class="debt-hero-total">
            <span id="debtTotalVal">0</span> $
        </div>

        <!-- ✅ СТРУКТУРА БОРГУ — ВГОРІ -->
        <div style="margin-top:12px;">
            <button type="button" class="btn" data-view="total" style="width:100%;">
            📊 Загальний борг
            </button>
        </div>

        <!-- ✅ ПЛИТКИ — НИЖЧЕ -->
        <div class="debt-hero-grid" style="width:100%; display:flex;">
            <div class="debt-mini btn" role="button" tabindex="0" data-view="inverter" style="width:100%; text-align:center;">
            <div class="debt-mini-top">⚡<br> Обладнання</div>
            <div class="debt-mini-val"><span id="debtInvVal">0</span> $</div>
            </div>

            <div class="debt-mini btn" role="button" tabindex="0" data-view="fem" style="width:100%; text-align:center;">
            <div class="debt-mini-top">☀️<br> ФЕМ</div>
            <div class="debt-mini-val"><span id="debtFemVal">0</span> $</div>
            </div>
        </div>

        </summary>


        <div class="debt-hero-body">

          <!-- TOTAL -->
          <div class="card" id="debtSectionTotal" style="margin-top:10px;">
            <div style="font-weight:700; text-align:center; margin-bottom:10px;">Загальний борг</div>

            <div style="height:220px;">
              <canvas id="pieTotal"></canvas>
            </div>

            <div id="barsTotal" style="margin-top:10px;"></div>
          </div>

          <!-- INVERTERS -->
          <div class="card" id="debtSectionInverter" style="margin-top:10px;">
            <div style="font-weight:700; text-align:center; margin-bottom:10px;">Обладнання: по категоріях</div>

            <div style="height:220px;">
              <canvas id="pieInverterCats"></canvas>
            </div>

            <div id="barsInverterCats" style="margin-top:10px;"></div>
          </div>

          <!-- FEM -->
          <div class="card" id="debtSectionFem" style="margin-top:10px;">
            <div style="font-weight:700; text-align:center; margin-bottom:10px;">ФЕМ: по виробниках</div>

            <div style="height:220px;">
              <canvas id="pieFemBrands"></canvas>
            </div>

            <div id="barsFemBrands" style="margin-top:10px;"></div>
          </div>

        </div>
      </details>
    `;

    main.insertAdjacentElement('afterbegin', card);
  }




///////////////////////////////////////////////////////////////
// View switching + toggle open/close + active highlight
///////////////////////////////////////////////////////////////

let currentView = 'total';

function setView(view) {
  currentView = view;

  const secTotal = document.getElementById('debtSectionTotal');
  const secInv   = document.getElementById('debtSectionInverter');
  const secFem   = document.getElementById('debtSectionFem');

  if (secTotal) secTotal.style.display = (view === 'total') ? '' : 'none';
  if (secInv)   secInv.style.display   = (view === 'inverter') ? '' : 'none';
  if (secFem)   secFem.style.display   = (view === 'fem') ? '' : 'none';
}

function setActive(view) {
  const details = document.getElementById('debtChartsDetails');
  if (!details) return;

  details.querySelectorAll('[data-view]').forEach(el => {
    el.classList.toggle('active', el.getAttribute('data-view') === view);
  });
}

function hookViewClicks() {
  const details = document.getElementById('debtChartsDetails');
  if (!details) return;

  // ✅ guard: щоб не навішувати слухачі повторно (якщо скрипт підвантажився ще раз)
  if (details.dataset.bound === '1') return;
  details.dataset.bound = '1';

  // стартовий стан
  details.open = false;
  details.dataset.view = details.dataset.view || 'total';
  setView(details.dataset.view);
  setActive(''); // коли закрито - нічого не підсвічуємо

    const toggleView = (view) => {
    const current = details.dataset.view || 'total';

    // повторний клік по тому ж -> закрити
    if (details.open && current === view) {
        details.open = false;
        return;
    }

    // записуємо поточний view
    details.dataset.view = view;

    // якщо details вже відкритий -> toggle не спрацює, тому міняємо UI руками
    if (details.open) {
        setView(view);
        setActive(view);

        const sec =
        view === 'total' ? document.getElementById('debtSectionTotal') :
        view === 'inverter' ? document.getElementById('debtSectionInverter') :
        document.getElementById('debtSectionFem');

        if (sec) setTimeout(() => sec.scrollIntoView({ behavior: 'smooth', block: 'start' }), 0);
        return;
    }

    // якщо був закритий -> відкриваємо (toggle listener сам підхопить setView/setActive)
    details.open = true;
    };


  // ✅ тільки CLICK (не pointerdown + click разом)
  details.querySelectorAll('[data-view]').forEach(el => {
    const handler = (e) => {
      e.preventDefault();
      e.stopPropagation();
      toggleView(el.getAttribute('data-view'));
    };

    el.addEventListener('click', handler);

    el.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') handler(e);
    });
  });

  // ✅ коли details відкривається/закривається нативно (клік по summary)
  details.addEventListener('toggle', () => {
    if (details.open) {
      const view = details.dataset.view || 'total';
      setView(view);
      setActive(view);
    } else {
      setActive('');
    }
  });
}



  ///////////////////////////////////////////////////////////////
  // Bars (wallet-like)
  ///////////////////////////////////////////////////////////////

  function renderBars(el, labels, values) {
    if (!el) return;

    const rows = asPositiveRows(labels, values);
    if (!rows.length) {
      el.innerHTML = `<div class="delivery-row"><div class="delivery-row-top" style="opacity:.7;">Нема боргу</div></div>`;
      return;
    }

    const total = rows.reduce((s, r) => s + r.value, 0);

    el.innerHTML = '';
    rows.forEach((r, idx) => {
      const pct = total > 0 ? Math.round((r.value / total) * 100) : 0;
      const color = PALETTE[idx % PALETTE.length];

      el.insertAdjacentHTML('beforeend', `
        <div style="display:flex; align-items:center; gap:10px; padding:10px 4px; border-bottom:1px solid rgba(255,255,255,.06);">
          <div style="flex:1; min-width:0;">
            <div style="font-weight:700; font-size:14px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
              ${escapeHtml(r.label)}
            </div>
            <div style="margin-top:6px; height:10px; border-radius:999px; background:rgba(255,255,255,.08); overflow:hidden;">
              <div style="height:100%; width:${pct}%; background:${color};"></div>
            </div>
          </div>

          <div style="text-align:right; min-width:76px;">
            <div style="font-weight:800;">${pct}%</div>
            <div style="opacity:.7; font-size:12px;">${fmt0(r.value)} $</div>
          </div>
        </div>
      `);
    });

    const last = el.lastElementChild;
    if (last) last.style.borderBottom = 'none';
  }

  ///////////////////////////////////////////////////////////////
  // Charts (pie)
  ///////////////////////////////////////////////////////////////

  let chartTotal = null;
  let chartInvCats = null;
  let chartFemBrands = null;

  function upsertPie(chartRef, canvasId, labels, data) {
    const ctx = getCanvasCtx(canvasId);
    if (!ctx) return null;

    const rows = asPositiveRows(labels, data);

    const safeLabels = rows.length ? rows.map(r => r.label) : ['Нема боргу'];
    const safeData   = rows.length ? rows.map(r => r.value) : [1];
    const colors     = safeLabels.map((_, i) => PALETTE[i % PALETTE.length]);

    if (chartRef) {
      chartRef.data.labels = safeLabels;
      chartRef.data.datasets[0].data = safeData;
      chartRef.data.datasets[0].backgroundColor = colors;
      chartRef.update();
      return chartRef;
    }

    return new Chart(ctx, {
      type: 'pie',
      data: {
        labels: safeLabels,
        datasets: [{
          data: safeData,
          backgroundColor: colors,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: true,
            position: 'bottom',
            labels: {
              color: '#e9eef6',
              boxWidth: 10,
              boxHeight: 10,
              padding: 12,
              font: { size: 12, weight: '600' }
            }
          },
          tooltip: {
            callbacks: {
              label: (item) => {
                if (safeLabels.length === 1 && safeLabels[0] === 'Нема боргу') {
                  return `${item.label}: 0 $`;
                }
                const v = Number(item.raw || 0);
                return `${item.label}: ${fmt0(v)} $`;
              }
            }
          }
        }
      }
    });
  }

  ///////////////////////////////////////////////////////////////
  // Data
  ///////////////////////////////////////////////////////////////

  async function loadAndRender() {
    const res = await fetch('/api/debt-chart', { credentials: 'same-origin' });
    if (!res.ok) throw new Error(`GET /api/debt-chart failed (${res.status})`);

    const d = await res.json();

    // верхні цифри
    const totalEl = document.getElementById('debtTotalVal');
    const invEl   = document.getElementById('debtInvVal');
    const femEl   = document.getElementById('debtFemVal');

    if (totalEl) totalEl.innerText = fmt0(d.total_debt || 0);
    if (invEl)   invEl.innerText   = fmt0(d.inverter_debt || 0);
    if (femEl)   femEl.innerText   = fmt0(d.fem_debt || 0);

    // TOTAL
    const totalLabels = ['Обладнання', 'ФЕМ'];
    const totalData = [Number(d.inverter_debt || 0), Number(d.fem_debt || 0)];
    chartTotal = upsertPie(chartTotal, 'pieTotal', totalLabels, totalData);
    renderBars(document.getElementById('barsTotal'), totalLabels, totalData);

    // INVERTER by category
    const invRows = Array.isArray(d.inverter_by_category) ? d.inverter_by_category : [];
    const invLabels = invRows.map(r => r.category);
    const invData = invRows.map(r => Number(r.debt || 0));
    chartInvCats = upsertPie(chartInvCats, 'pieInverterCats', invLabels, invData);
    renderBars(document.getElementById('barsInverterCats'), invLabels, invData);

    // FEM by brand
    const femRows = Array.isArray(d.fem_by_brand) ? d.fem_by_brand : [];
    const femLabels = femRows.map(r => r.brand);
    const femData = femRows.map(r => Number(r.debt || 0));
    chartFemBrands = upsertPie(chartFemBrands, 'pieFemBrands', femLabels, femData);
    renderBars(document.getElementById('barsFemBrands'), femLabels, femData);
  }

  async function boot() {
    makeCard();
    hookViewClicks();
    await ensureChartJs();
    await loadAndRender();
    setInterval(() => loadAndRender().catch(() => {}), 15000);
  }

  document.addEventListener('DOMContentLoaded', () => {
    boot().catch((e) => console.warn('Debt charts:', e.message));
  });
})();
