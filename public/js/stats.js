///////////////////////////////////////////////////////////////
// ===== STATS STATE =====
///////////////////////////////////////////////////////////////

let statsType = 'expense'; // expense | income
let catChartInstance = null;

const ctxChart = document.getElementById('catChart')?.getContext('2d');
const statsExpense = document.getElementById('statsExpense');
const statsIncome  = document.getElementById('statsIncome');

///////////////////////////////////////////////////////////////
// Фільтр операцій по типу
///////////////////////////////////////////////////////////////

function getFilteredEntriesByStatsType() {
  return state.entries.filter(e => {
    const val = Number(e.signed_amount || 0);
    return statsType === 'expense' ? val < 0 : val > 0;
  });
}

///////////////////////////////////////////////////////////////
// Категорії — список з барами %
///////////////////////////////////////////////////////////////

window.renderCategoryStats = function () {
  const entries = getFilteredEntriesByStatsType();
  const elCatBox  = document.getElementById('categoryStats');
  const elCatList = document.getElementById('catList');

  if (!entries.length) {
    elCatBox.classList.add('hidden');
    return;
  }

  const map = {};
  let total = 0;

  entries.forEach(e => {
    const amount = Math.abs(Number(e.signed_amount));
    total += amount;

    const m = (e.comment || '').match(/^\[(.+?)\]/);
    const cat = m ? m[1] : 'Інше';

    map[cat] = (map[cat] || 0) + amount;
  });

  elCatList.innerHTML = '';
  elCatBox.classList.remove('hidden');

  Object.entries(map)
    .sort((a, b) => b[1] - a[1])
    .forEach(([cat, sum]) => {
      const pct = Math.round((sum / total) * 100);

      elCatList.insertAdjacentHTML('beforeend', `
        <div class="cat-row">
          <div class="cat-name">${cat}</div>
          <div class="cat-bar"><div style="width:${pct}%"></div></div>
          <div class="cat-pct">${pct}%</div>
        </div>
      `);
    });
};

///////////////////////////////////////////////////////////////
// Кругова діаграма
///////////////////////////////////////////////////////////////

window.renderCategoryChart = function () {
  if (!ctxChart || typeof Chart === 'undefined') return;

  const entries = getFilteredEntriesByStatsType();
  if (!entries.length) return;

  const data = {};

  entries.forEach(e => {
    const m = (e.comment || '').match(/^\[(.+?)\]/);
    if (!m) return;
    const cat = m[1];
    data[cat] = (data[cat] || 0) + Math.abs(Number(e.signed_amount));
  });

  const labels = Object.keys(data);
  const values = Object.values(data);

  if (catChartInstance) catChartInstance.destroy();

  catChartInstance = new Chart(ctxChart, {
    type: 'pie',
    data: {
      labels,
      datasets: [{
        data: values,
        backgroundColor: [
          '#66f2a8',
          '#4c7dff',
          '#ffb86c',
          '#ff6b6b',
          '#9aa6bc'
        ]
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { labels: { color: '#e9eef6' } }
      }
    }
  });
};

///////////////////////////////////////////////////////////////
// Перемикач ДОХІД / ВИТРАТА
///////////////////////////////////////////////////////////////

statsExpense.onclick = () => {
  statsType = 'expense';
  statsExpense.classList.add('active');
  statsIncome.classList.remove('active');
  renderCategoryStats();
  renderCategoryChart();
};

statsIncome.onclick = () => {
  statsType = 'income';
  statsIncome.classList.add('active');
  statsExpense.classList.remove('active');
  renderCategoryStats();
  renderCategoryChart();
};

///////////////////////////////////////////////////////////////
// Місячна аналітика (кнопка "Показати")
///////////////////////////////////////////////////////////////

document.getElementById('showStats').onclick = () => {
  const month = document.getElementById('statsMonth').value;
  const el = document.getElementById('statsResult');
  el.innerHTML = '';

  if (!month) {
    alert('Вибери місяць');
    return;
  }

  const map = {};

  state.entries.forEach(e => {
    if (!e.posting_date.startsWith(month)) return;

    const val = Number(e.signed_amount || 0);
    if (statsType === 'expense' && val >= 0) return;
    if (statsType === 'income' && val <= 0) return;

    const m = (e.comment || '').match(/^\[(.+?)\]/);
    const cat = m ? m[1] : 'Без категорії';

    map[cat] = (map[cat] || 0) + Math.abs(val);
  });

  renderStats(map);
};

///////////////////////////////////////////////////////////////
// Відмалювати підсумки по місяцю
///////////////////////////////////////////////////////////////

function renderStats(map){
  const el = document.getElementById('statsResult');
  el.innerHTML = '';

  const entries = Object.entries(map);
  if (!entries.length){
    el.innerHTML = '<div class="muted">Немає даних</div>';
    return;
  }

  let total = 0;
  const card = document.createElement('div');
  card.className = 'card';

  entries.forEach(([cat,sum]) => {
    total += sum;
    card.innerHTML += `
      <div class="row" style="margin-bottom:6px;">
        <div>${cat}</div>
        <div class="right ${statsType==='expense'?'neg':'pos'}">
          ${fmt(sum)}
        </div>
      </div>
    `;
  });

  card.innerHTML += `
    <hr style="opacity:.1">
    <div class="row">
      <div><b>Разом</b></div>
      <div class="right big ${statsType==='expense'?'neg':'pos'}">
        ${fmt(total)}
      </div>
    </div>
  `;

  el.appendChild(card);
}
