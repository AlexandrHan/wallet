@extends('layouts.app')

@push('styles')
  <link rel="stylesheet" href="/css/nav-telegram.css?v={{ filemtime(public_path('css/nav-telegram.css')) }}">
  <link rel="stylesheet" href="/css/project.css?v={{ filemtime(public_path('css/project.css')) }}">
@endpush

@section('content')
<main class="">
  <a href="/" class="card" style="margin-bottom:15px; display:block; text-decoration:none; color:inherit;">
    <div style="font-weight:800; font-size:18px; text-align:center;">
      💰 Моя зарплатня
    </div>
    <div style="font-size:13px; opacity:.7; text-align:center; margin-top:6px;">
      Натисни, щоб повернутись назад
    </div>
  </a>

  <div id="mySalaryRoot"></div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const root = document.getElementById('mySalaryRoot');
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

  const currentSalaryMonths = (months) => {
    const currentDate = new Date();
    const currentMonth = currentDate.getFullYear() === year ? (currentDate.getMonth() + 1) : 12;
    const list = Array.isArray(months) ? months : [];
    const currentMonthData = list.find(item => Number(item?.month) === currentMonth);
    const pastMonths = list
      .filter(item => Number(item?.month) < currentMonth)
      .sort((a, b) => Number(b?.month || 0) - Number(a?.month || 0));

    return [
      ...(currentMonthData ? [currentMonthData] : []),
      ...pastMonths
    ];
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

  function bindCollapse(card) {
    const body = card.querySelector('[data-month-body]');
    card.querySelector('[data-month-toggle]')?.addEventListener('click', function (e) {
      if (e.target.closest('button, input, select, textarea, a, label')) return;
      if (!body) return;

      const isClosed = window.getComputedStyle(body).display === 'none';
      body.style.display = isClosed ? 'block' : 'none';
    });
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

  function renderFixed(payload) {
    const currency = payload?.currency || 'UAH';
    const months = currentSalaryMonths(payload?.months || []);

    root.innerHTML = `
      <div class="card" style="margin-bottom:15px;">
        <div style="font-weight:800; font-size:16px; margin-bottom:6px;">${esc(payload.staff_name || 'Співробітник')}</div>
        <div style="opacity:.75; font-size:14px;">Базова ставка: ${formatMoney(payload.monthly_amount || 0, currency)}</div>
      </div>
      ${!months.length ? `
        <div class="card">
          <div style="font-size:14px; opacity:.8; text-align:center;">Для цього року ще немає доступних місяців.</div>
        </div>
      ` : ''}
      ${months.map(item => `
        <div class="card my-salary-month" style="margin-bottom:12px;">
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

    root.querySelectorAll('.my-salary-month').forEach((card, index) => {
      const item = months[index];
      syncCardState(card, item?.bonus_total || 0, item?.penalty_total || 0);
      bindCollapse(card);
    });
  }

  function renderManager(payload) {
    const months = currentSalaryMonths(payload?.months || []);

    const totalsLine = (items) => {
      const list = Array.isArray(items) ? items : [];
      if (!list.length) return 'Нарахувань немає';
      return list.map(item => formatMoney(item.amount || 0, item.currency || 'UAH')).join(' + ');
    };

    root.innerHTML = `
      <div class="card" style="margin-bottom:15px;">
        <div style="font-weight:800; font-size:16px; margin-bottom:6px;">${esc(payload.staff_name || 'Менеджер')}</div>
        <div style="opacity:.75; font-size:14px;">Зарплата відділу продажів за ${payload.year}</div>
      </div>
      ${!months.length ? `
        <div class="card">
          <div style="font-size:14px; opacity:.8; text-align:center;">Для цього року ще немає доступних місяців.</div>
        </div>
      ` : ''}
      ${months.map(item => `
        <div class="card my-salary-month" style="margin-bottom:12px;">
          <div data-month-toggle style="display:flex; justify-content:space-between; gap:10px; align-items:center; cursor:pointer;">
            <div>
              <div style="font-weight:800; font-size:16px;">${monthNames[item.month] || item.month}</div>
              <div style="font-size:13px; opacity:.72; margin-top:4px;">${totalsLine(item.totals_by_currency)}</div>
            </div>
            <div style="text-align:right;">
              <div style="font-size:12px; opacity:.72;">Проєктів</div>
              <div style="font-weight:900; font-size:18px;">${Array.isArray(item.projects) ? item.projects.length : 0}</div>
            </div>
          </div>

          <div data-month-body style="display:none;">
            ${Array.isArray(item.projects) && item.projects.length ? item.projects.map(project => `
              <div style="margin-top:8px; padding:8px 10px; border-radius:10px; background:rgba(255,255,255,.04);">
                <div style="display:flex; justify-content:space-between; gap:10px;">
                  <div style="font-weight:800; font-size:13px;">${esc(project.client_name || 'Проєкт')}</div>
                  <div style="font-size:12px; opacity:.72;">${esc(project.paid_at || '')}</div>
                </div>
                <div style="font-size:12px; opacity:.8; margin-top:4px;">Проєкт: ${formatMoney(project.project_amount || 0, project.currency || 'UAH')}</div>
                <div style="font-size:12px; opacity:.8; margin-top:2px;">1%: ${formatMoney(project.commission || 0, project.currency || 'UAH')}</div>
              </div>
            `).join('') : `
              <div style="opacity:.6; font-size:13px; margin-top:8px;">Нарахувань за цей місяць немає.</div>
            `}
          </div>
        </div>
      `).join('')}
    `;

    root.querySelectorAll('.my-salary-month').forEach(card => bindCollapse(card));
  }


  async function load() {
    root.innerHTML = `
      <div class="card">
        <div style="font-size:14px; opacity:.8; text-align:center;">Завантаження зарплатні...</div>
      </div>
    `;

    try {
      const response = await fetch(`/api/salary/my?year=${year}`, {
        headers: { 'Accept': 'application/json' }
      });
      const payload = await response.json();

      if (!response.ok) {
        throw new Error(payload.error || 'Не вдалося завантажити зарплатню');
      }

      if (payload.view_type === 'fixed') {
        renderFixed(payload);
        return;
      }

      if (payload.view_type === 'manager') {
        renderManager(payload);
        return;
      }

      root.innerHTML = `
        <div class="card">
          <div style="font-weight:800; margin-bottom:6px;">Моя зарплатня</div>
          <div style="opacity:.75; font-size:14px;">${esc(payload.message || 'Для вашого профілю персональна зарплатня поки не налаштована.')}</div>
        </div>
      `;
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
