@extends('layouts.app')

@push('styles')
  <link rel="stylesheet" href="/css/nav-telegram.css?v={{ filemtime(public_path('css/nav-telegram.css')) }}">
  <link rel="stylesheet" href="/css/project.css?v={{ filemtime(public_path('css/project.css')) }}">
@endpush

@section('content')
<main class="">
  <a href="/projects" class="card" style="margin-bottom:15px; display:block; text-decoration:none; color:inherit;">
    <div style="font-weight:800; font-size:18px; text-align:center;">
      🏗 Моя зарплатня
    </div>
    <div style="font-size:13px; opacity:.7; text-align:center; margin-top:6px;">
      Натисни, щоб повернутись до проєктів
    </div>
  </a>

  <div id="foremanSalaryRoot"></div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const root = document.getElementById('foremanSalaryRoot');
  if (!root) return;

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

  function syncCardState(card, bonusTotal, penaltyTotal) {
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
  }

  function renderAdjustmentList(entries, emptyText, currency) {
    const items = Array.isArray(entries) ? entries : [];
    if (!items.length) {
      return `<div style="opacity:.6; font-size:13px; margin-top:8px;">${emptyText}</div>`;
    }

    return items.map(item => `
      <div style="margin-top:8px; padding:8px 10px; border-radius:10px; background:rgba(255,255,255,.04);">
        <div style="font-weight:800; font-size:13px;">${formatMoney(item.amount || 0, currency)}</div>
        <div style="font-size:12px; opacity:.76; margin-top:4px;">${esc(item.description || 'Без опису')}</div>
      </div>
    `).join('');
  }

  function bindMonthCard(card) {
    const body = card.querySelector('[data-month-body]');
    card.querySelector('[data-month-toggle]')?.addEventListener('click', function (e) {
      if (e.target.closest('button, input, select, textarea, a, label')) return;
      if (!body) return;

      const isClosed = window.getComputedStyle(body).display === 'none';
      body.style.display = isClosed ? 'block' : 'none';
    });
  }

  function render(payload) {
    if (String(payload?.mode || '') !== 'fixed') {
      root.innerHTML = `
        <div class="card">
          <div style="font-weight:800; margin-bottom:6px;">Помісячна ставка не налаштована</div>
          <div style="opacity:.75; font-size:14px;">Зараз для вашого профілю не задано режим фіксованої зарплати.</div>
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
        <div style="font-weight:800; font-size:16px; margin-bottom:6px;">Виплати за ${payload.year}</div>
        <div style="opacity:.75; font-size:14px;">Базова ставка: ${formatMoney(payload.monthly_amount || 0, currency)}</div>
      </div>
      ${!months.length ? `
        <div class="card">
          <div style="font-size:14px; opacity:.8; text-align:center;">Для цього року ще немає доступних місяців.</div>
        </div>
      ` : ''}
      ${months.map(item => `
        <div class="card foreman-salary-month" style="margin-bottom:12px;">
          <div data-month-toggle style="display:flex; justify-content:space-between; gap:10px; align-items:center; cursor:pointer;">
            <div>
              <div style="font-weight:800; font-size:16px;">${monthNames[item.month] || item.month}</div>
              <div style="font-size:13px; opacity:.72; margin-top:4px;">Ставка: ${formatMoney(item.monthly_amount, currency)}</div>
            </div>
            <div style="text-align:right;">
              <div style="font-size:12px; opacity:.72;">До виплати</div>
              <div style="font-weight:900; font-size:18px;">${formatMoney(item.net_amount, currency)}</div>
            </div>
          </div>

          <div data-month-body style="display:none;">
            <hr style="margin:12px 0; border:none; border-top:1px solid rgba(255,255,255,.08);">
            <div style="font-size:12px; opacity:.72;">Премії</div>
            ${renderAdjustmentList(item.bonuses, 'Премій немає', currency)}

            <hr style="margin:12px 0; border:none; border-top:1px solid rgba(255,255,255,.08);">
            <div style="font-size:12px; opacity:.72;">Штрафи</div>
            ${renderAdjustmentList(item.penalties, 'Штрафів немає', currency)}
          </div>
        </div>
      `).join('')}
    `;

    root.querySelectorAll('.foreman-salary-month').forEach((card, index) => {
      const item = months[index];
      syncCardState(card, item?.bonus_total || 0, item?.penalty_total || 0);
      bindMonthCard(card);
    });
  }

  async function load() {
    root.innerHTML = `
      <div class="card">
        <div style="font-size:14px; opacity:.8; text-align:center;">Завантаження зарплатні...</div>
      </div>
    `;

    try {
      const response = await fetch(`/api/salary/foreman/my?year=${year}`, {
        headers: { 'Accept': 'application/json' }
      });
      const payload = await response.json();

      if (!response.ok) {
        throw new Error(payload.error || 'Не вдалося завантажити зарплатню');
      }

      render(payload);
    } catch (error) {
      root.innerHTML = `
        <div class="card">
          <div style="font-weight:800; margin-bottom:6px;">Помилка</div>
          <div style="opacity:.75; font-size:14px;">${esc(error.message || 'Не вдалося завантажити зарплатню')}</div>
        </div>
      `;
    }
  }

  load();
});
</script>

@include('partials.nav.bottom')
@endsection
