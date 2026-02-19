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
    const rows = labels
      .map((label, i) => ({ label: String(label), value: Number(values[i] || 0) }))
      .filter(r => r.value > 0);

    rows.sort((a, b) => b.value - a.value);
    return rows;
  }

  ///////////////////////////////////////////////////////////////
  // UI card
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
      <div style="font-size:14px; opacity:.7; text-align:center">
        Загальний борг
      </div>

      <div style="font-size:20px; font-weight:700; margin-top:6px; text-align:center">
        <span id="debtTotalVal">0</span> $
      </div>

      <div style="margin-top:12px;">
        <!-- TOTAL -->
        <details open>
          <summary class="stock-cat-summary" style="padding:6px 2px;">
            <div class="stock-cat-left">
              <span class="stock-cat-title">Структура боргу</span>
            </div>
            <div class="stock-cat-right"><span class="chev">›</span></div>
          </summary>

          <div class="stock-cat-body">
            <div style="height:220px;">
              <canvas id="pieTotal"></canvas>
            </div>

            <div id="barsTotal" style="margin-top:10px;"></div>
          </div>
        </details>

        <!-- INVERTER CATS -->
        <details style="margin-top:10px;">
          <summary class="stock-cat-summary" style="padding:6px 2px;">
            <div class="stock-cat-left">
              <span class="stock-cat-title">Інвертори: борг по категоріях</span>
            </div>
            <div class="stock-cat-right"><span class="chev">›</span></div>
          </summary>

          <div class="stock-cat-body">
            <div style="height:220px;">
              <canvas id="pieInverterCats"></canvas>
            </div>

            <div id="barsInverterCats" style="margin-top:10px;"></div>
          </div>
        </details>

        <!-- FEM BRANDS -->
        <details style="margin-top:10px;">
          <summary class="stock-cat-summary" style="padding:6px 2px;">
            <div class="stock-cat-left">
              <span class="stock-cat-title">ФЕМ: борг по виробниках</span>
            </div>
            <div class="stock-cat-right"><span class="chev">›</span></div>
          </summary>

          <div class="stock-cat-body">
            <div style="height:220px;">
              <canvas id="pieFemBrands"></canvas>
            </div>

            <div id="barsFemBrands" style="margin-top:10px;"></div>
          </div>
        </details>
      </div>
    `;

    main.insertAdjacentElement('afterbegin', card);
  }

  ///////////////////////////////////////////////////////////////
  // Bars (як у wallet: назва + прогрес + %)
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

    // прибираємо останній бордер
    const last = el.lastElementChild;
    if (last) last.style.borderBottom = 'none';
  }

  function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, (m) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    }[m]));
  }

  ///////////////////////////////////////////////////////////////
  // Charts (як у wallet: фікс палітра + світла легенда)
  ///////////////////////////////////////////////////////////////

  let chartTotal = null;
  let chartInvCats = null;
  let chartFemBrands = null;

  function upsertPie(chartRef, canvasId, labels, data) {
    const ctx = getCanvasCtx(canvasId);
    if (!ctx) return null;

    const rows = asPositiveRows(labels, data);

    // щоб pie не ламався на 0
    const safeLabels = rows.length ? rows.map(r => r.label) : ['Нема боргу'];
    const safeData = rows.length ? rows.map(r => r.value) : [1];
    const colors = safeLabels.map((_, i) => PALETTE[i % PALETTE.length]);

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
                const v = Number(item.raw || 0);
                // якщо "Нема боргу" (штучний 1) показуємо 0
                if (safeLabels.length === 1 && safeLabels[0] === 'Нема боргу') {
                  return `${item.label}: 0 $`;
                }
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

    const totalEl = document.getElementById('debtTotalVal');
    if (totalEl) totalEl.innerText = fmt0(d.total_debt || 0);

    // TOTAL (inverter vs fem)
    const totalLabels = ['Інвертори', 'ФЕМ'];
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
    await ensureChartJs();
    await loadAndRender();

    // не часто, щоб не душити сторінку
    setInterval(() => loadAndRender().catch(() => {}), 15000);
  }

  document.addEventListener('DOMContentLoaded', () => {
    boot().catch((e) => console.warn('Debt charts:', e.message));
  });
})();
