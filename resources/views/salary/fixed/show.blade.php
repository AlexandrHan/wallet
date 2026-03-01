@php
  $staffGroup = trim((string) request('staff_group', ''));
  $staffName = trim((string) request('staff_name', ''));

  $titles = [
    'electrician' => '⚡ ' . ($staffName !== '' ? $staffName : 'Співробітник'),
    'installation_team' => '🛠 ' . ($staffName !== '' ? $staffName : 'Бригада'),
    'manager' => '📈 ' . ($staffName !== '' ? $staffName : 'Співробітник'),
    'accountant' => '🧾 ' . ($staffName !== '' ? $staffName : 'Співробітник'),
    'foreman' => '🏗 ' . ($staffName !== '' ? $staffName : 'Співробітник'),
  ];

  $backLinks = [
    'electrician' => '/salary/electricians',
    'installation_team' => '/salary/installers',
    'manager' => '/salary/managers',
    'accountant' => '/salary',
    'foreman' => '/salary',
  ];

  $pageTitle = $titles[$staffGroup] ?? ($staffName !== '' ? $staffName : 'Помісячні виплати');
  $backLink = $backLinks[$staffGroup] ?? '/salary';
@endphp

@extends('layouts.app')

@push('styles')
  <link rel="stylesheet" href="/css/nav-telegram.css?v={{ filemtime(public_path('css/nav-telegram.css')) }}">
  <link rel="stylesheet" href="/css/project.css?v={{ filemtime(public_path('css/project.css')) }}">
@endpush

@section('content')
<main class="">
  <a href="{{ $backLink }}" class="card" style="margin-bottom:15px; display:block; text-decoration:none; color:inherit;">
    <div style="font-weight:800; font-size:18px; text-align:center;">
      {{ $pageTitle }}
    </div>
  </a>

  <div id="fixedSalaryRoot"></div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const root = document.getElementById('fixedSalaryRoot');
  if (!root) return;

  const staffGroup = @json($staffGroup);
  const staffName = @json($staffName);
  const year = 2026;

  const monthNames = {
    1: 'Січень',
    2: 'Лютий',
    3: 'Березень',
    4: 'Квітень',
    5: 'Травень',
    6: 'Червень',
    7: 'Липень',
    8: 'Серпень',
    9: 'Вересень',
    10: 'Жовтень',
    11: 'Листопад',
    12: 'Грудень'
  };

  const esc = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

  const formatMoney = (value, currency) => {
    const symbols = { UAH: '₴', USD: '$', EUR: '€' };
    return `${new Intl.NumberFormat('uk-UA', {
      minimumFractionDigits: 0,
      maximumFractionDigits: 2,
    }).format(Number(value || 0))} ${symbols[currency] || currency}`;
  };

  function adjustmentRowHtml(entry = {}, type = 'penalty') {
    const amountPlaceholder = type === 'bonus' ? 'Сума' : 'Сума';
    const descPlaceholder = type === 'bonus' ? 'Опис премії' : 'Опис штрафу';

    return `
      <div class="salary-penalty-row" style="display:flex; flex-wrap:wrap; gap:8px; margin-top:8px; align-items:flex-start;">
        <input
          type="number"
          step="0.01"
          class="btn"
          data-amount-input
          value="${esc(entry.amount ?? '')}"
          placeholder="${amountPlaceholder}"
          style="flex:1 1 calc(30% - 4px); min-width:0; box-sizing:border-box; margin-bottom:0; padding:8px 10px; font-size:12px;"
        >
        <textarea
          class="btn"
          data-description-input
          placeholder="${descPlaceholder}"
          style="
            flex:1 1 calc(70% - 4px);
            min-width:0;
            box-sizing:border-box;
            margin-bottom:0;
            font-size:12px;
            min-height:40px;
            line-height:1.35;
            padding:8px 10px;
            resize:none;
            overflow:hidden;
          "
          rows="1"
        >${esc(entry.description ?? '')}</textarea>
      </div>
    `;
  }

  function autosizeTextarea(textarea) {
    if (!(textarea instanceof HTMLTextAreaElement)) return;
    textarea.style.height = 'auto';
    textarea.style.height = `${textarea.scrollHeight}px`;
  }

  function bindMonthCard(card, currency) {
    const body = card.querySelector('[data-month-body]');

    const syncCardState = (bonusTotal, penaltyTotal) => {
      const hasBonus = Number(bonusTotal || 0) > 0;
      const hasPenalty = Number(penaltyTotal || 0) > 0;

      if (hasBonus && hasPenalty) {
        card.style.border = '2px solid rgba(76, 125, 255, .65)';
      } else if (hasPenalty) {
        card.style.border = '2px solid rgba(255, 80, 80, .55)';
      } else if (hasBonus) {
        card.style.border = '2px solid rgba(242, 194, 0, .65)';
      } else {
        card.style.border = '';
      }
    };

    const recalc = () => {
      const monthlyAmount = Number(card.dataset.monthlyAmount || 0);
      const bonusTotal = Array.from(card.querySelectorAll('[data-bonus-rows] [data-amount-input]'))
        .reduce((sum, input) => sum + Number(String(input.value || '').replace(',', '.') || 0), 0);
      const penaltyTotal = Array.from(card.querySelectorAll('[data-penalty-rows] [data-amount-input]'))
        .reduce((sum, input) => sum + Number(String(input.value || '').replace(',', '.') || 0), 0);

      const netAmount = monthlyAmount + bonusTotal - penaltyTotal;
      const bonusTotalEl = card.querySelector('[data-bonus-total]');
      const penaltyTotalEl = card.querySelector('[data-penalty-total]');
      const netAmountEl = card.querySelector('[data-net-amount]');

      if (bonusTotalEl) bonusTotalEl.textContent = formatMoney(bonusTotal, currency);
      if (penaltyTotalEl) penaltyTotalEl.textContent = formatMoney(penaltyTotal, currency);
      if (netAmountEl) netAmountEl.textContent = formatMoney(netAmount, currency);
      syncCardState(bonusTotal, penaltyTotal);
    };

    card.querySelector('[data-month-toggle]')?.addEventListener('click', function (e) {
      if (e.target.closest('button, input, select, textarea, a, label')) return;
      if (!body) return;

      const isClosed = window.getComputedStyle(body).display === 'none';
      body.style.display = isClosed ? 'block' : 'none';
    });

    card.querySelectorAll('[data-amount-input]').forEach(input => {
      input.addEventListener('input', recalc);
    });

    card.querySelectorAll('[data-description-input]').forEach(textarea => {
      autosizeTextarea(textarea);
      textarea.addEventListener('input', function () {
        autosizeTextarea(textarea);
      });
    });

    card.querySelector('[data-add-bonus]')?.addEventListener('click', function () {
      const rows = card.querySelector('[data-bonus-rows]');
      if (!rows) return;
      rows.insertAdjacentHTML('beforeend', adjustmentRowHtml({}, 'bonus'));
      const newRow = rows.lastElementChild;
      newRow?.querySelector('[data-amount-input]')?.addEventListener('input', recalc);
      const newTextarea = newRow?.querySelector('[data-description-input]');
      if (newTextarea) {
        autosizeTextarea(newTextarea);
        newTextarea.addEventListener('input', function () {
          autosizeTextarea(newTextarea);
        });
      }
    });

    card.querySelector('[data-add-penalty]')?.addEventListener('click', function () {
      const rows = card.querySelector('[data-penalty-rows]');
      if (!rows) return;
      rows.insertAdjacentHTML('beforeend', adjustmentRowHtml({}, 'penalty'));
      const newRow = rows.lastElementChild;
      newRow?.querySelector('[data-amount-input]')?.addEventListener('input', recalc);
      const newTextarea = newRow?.querySelector('[data-description-input]');
      if (newTextarea) {
        autosizeTextarea(newTextarea);
        newTextarea.addEventListener('input', function () {
          autosizeTextarea(newTextarea);
        });
      }
    });

    card.querySelector('[data-save-penalties]')?.addEventListener('click', async function () {
      const bonuses = Array.from(card.querySelectorAll('[data-bonus-rows] .salary-penalty-row')).map(row => {
        const amount = row.querySelector('[data-amount-input]')?.value ?? '';
        const description = row.querySelector('[data-description-input]')?.value ?? '';
        return {
          amount: String(amount).trim().replace(',', '.'),
          description: String(description).trim(),
        };
      });

      const penalties = Array.from(card.querySelectorAll('[data-penalty-rows] .salary-penalty-row')).map(row => {
        const amount = row.querySelector('[data-amount-input]')?.value ?? '';
        const description = row.querySelector('[data-description-input]')?.value ?? '';
        return {
          amount: String(amount).trim().replace(',', '.'),
          description: String(description).trim(),
        };
      });

      const button = this;
      button.disabled = true;

      try {
        const response = await fetch('/api/salary/fixed-employee/penalties', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          },
          body: JSON.stringify({
            staff_group: staffGroup,
            staff_name: staffName,
            year,
            month: Number(card.dataset.month || 0),
            bonuses,
            penalties,
          })
        });

        const payload = await response.json();
        if (!response.ok || !payload.ok) {
          throw new Error(payload.error || 'Не вдалося зберегти штрафи');
        }

        recalc();
      } catch (error) {
        alert(error.message || 'Не вдалося зберегти штрафи');
      } finally {
        button.disabled = false;
      }
    });

    recalc();
  }

  function renderMonthCards(payload) {
    if (String(payload?.mode || '') !== 'fixed') {
      root.innerHTML = `
        <div class="card">
          <div style="font-weight:800; margin-bottom:6px;">Цей співробітник не на ставці</div>
          <div style="opacity:.75; font-size:14px;">Для нього зараз використовується режим виробітку, тому помісячна сторінка штрафів недоступна.</div>
        </div>
      `;
      return;
    }

    const currency = payload?.currency || 'UAH';
    const currentDate = new Date();
    const currentMonth = currentDate.getFullYear() === year ? (currentDate.getMonth() + 1) : 12;
    const allMonths = Array.isArray(payload?.months) ? payload.months : [];
    const currentMonthData = allMonths.find(item => Number(item?.month) === currentMonth);
    const pastMonths = allMonths
      .filter(item => Number(item?.month) < currentMonth)
      .sort((a, b) => Number(b?.month || 0) - Number(a?.month || 0));
    const months = [
      ...(currentMonthData ? [currentMonthData] : []),
      ...pastMonths
    ];

    root.innerHTML = `
      <div class="card" style="margin-bottom:15px;">
        <div style="font-weight:800; font-size:16px; margin-bottom:6px;">Помісячні виплати за ${payload.year}</div>
        <div style="opacity:.75; font-size:14px;">Базова ставка: ${formatMoney(payload.monthly_amount || 0, currency)}</div>
      </div>
      ${!months.length ? `
        <div class="card">
          <div style="font-size:14px; opacity:.8; text-align:center;">Для цього року ще немає доступних місяців.</div>
        </div>
      ` : ''}
      ${months.map(item => `
        <div
          class="card salary-month-card"
          data-month="${item.month}"
          data-monthly-amount="${item.monthly_amount}"
          style="margin-bottom:12px; ${
            Number(item.bonus_total || 0) > 0 && Number(item.penalty_total || 0) > 0
              ? 'border:2px solid rgba(76, 125, 255, .65);'
              : Number(item.penalty_total || 0) > 0
                ? 'border:2px solid rgba(255, 80, 80, .55);'
                : Number(item.bonus_total || 0) > 0
                  ? 'border:2px solid rgba(242, 194, 0, .65);'
                  : ''
          }"
        >
          <div data-month-toggle style="display:flex; justify-content:space-between; gap:10px; align-items:center; cursor:pointer;">
            <div>
              <div style="font-weight:800; font-size:16px;">${monthNames[item.month] || item.month}</div>
              <div style="font-size:13px; opacity:.72; margin-top:4px;">Ставка: ${formatMoney(item.monthly_amount, currency)}</div>
            </div>
            <div style="text-align:right;">
              <div style="font-size:12px; opacity:.72;">До виплати</div>
              <div data-net-amount style="font-weight:900; font-size:18px;">${formatMoney(item.net_amount, currency)}</div>
            </div>
          </div>

          <div data-month-body style="display:none;">
            <hr style="margin:12px 0; border:none; border-top:1px solid rgba(255,255,255,.08);">
            <div style="margin-top:12px; font-size:12px; opacity:.72; color:yellow;">Премії</div>
            <div data-bonus-rows>
              ${(Array.isArray(item.bonuses) && item.bonuses.length ? item.bonuses : [{}]).map(entry => adjustmentRowHtml(entry, 'bonus')).join('')}
            </div>

            <div style="margin-top:10px; display:flex; justify-content:space-between; gap:10px; align-items:center;">
              <div style="font-size:14px;">
                Премій на суму: <span data-bonus-total style="font-weight:800;">${formatMoney(item.bonus_total || 0, currency)}</span>
              </div>
              <button type="button" class="btn" data-add-bonus style="margin-bottom:0;">+ Додати поле</button>
            </div>

            <hr style="margin:12px 0; border:none; border-top:1px solid rgba(255,255,255,.08);">

            <div style="margin-top:12px; font-size:12px; opacity:.72; color:red;">Штрафи</div>
            <div data-penalty-rows>
              ${(Array.isArray(item.penalties) && item.penalties.length ? item.penalties : [{}]).map(entry => adjustmentRowHtml(entry, 'penalty')).join('')}
            </div>

            <div style="margin-top:10px; display:flex; justify-content:space-between; gap:10px; align-items:center;">
              <div style="font-size:14px;">
                Штрафів на суму: <span data-penalty-total style="font-weight:800;">${formatMoney(item.penalty_total, currency)}</span>
              </div>
              <button type="button" class="btn" data-add-penalty style="margin-bottom:0;">+ Додати поле</button>
            </div>

            <button type="button" class="btn primary" data-save-penalties style="width:100%; margin-top:10px; margin-bottom:0;">
              Зберегти
            </button>
          </div>
        </div>
      `).join('')}
    `;

    root.querySelectorAll('.salary-month-card').forEach(card => bindMonthCard(card, currency));
  }

  async function load() {
    root.innerHTML = `
      <div class="card">
        <div style="font-size:14px; opacity:.8; text-align:center;">Завантаження помісячних виплат...</div>
      </div>
    `;

    try {
      const response = await fetch(`/api/salary/fixed-employee?staff_group=${encodeURIComponent(staffGroup)}&staff_name=${encodeURIComponent(staffName)}&year=${year}`);
      const payload = await response.json();

      if (!response.ok) {
        throw new Error(payload.error || 'Не вдалося завантажити виплати');
      }

      renderMonthCards(payload);
    } catch (error) {
      root.innerHTML = `
        <div class="card">
          <div style="font-weight:800; margin-bottom:6px;">Помилка</div>
          <div style="opacity:.75; font-size:14px;">${esc(error.message || 'Не вдалося завантажити виплати')}</div>
        </div>
      `;
    }
  }

  load();
});
</script>

@include('partials.nav.bottom')
@endsection
