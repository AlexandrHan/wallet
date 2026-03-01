@extends('layouts.app')

@push('styles')
  <link rel="stylesheet" href="/css/nav-telegram.css?v={{ filemtime(public_path('css/nav-telegram.css')) }}">
  <link rel="stylesheet" href="/css/project.css?v={{ filemtime(public_path('css/project.css')) }}">
@endpush

@section('content')
<main class="">
  <a href="/salary" class="card" style="margin-bottom:15px; display:block; text-decoration:none; color:inherit;">
    <div style="font-weight:800; font-size:18px; text-align:center;">
      📈 Зарплата відділу продажів
    </div>

  </a>

  <div class="card" style="margin-bottom:15px;">
    <div style="font-weight:800; font-size:15px; margin-bottom:10px;">
      Період нарахування
    </div>

    <div style="display:flex; gap:10px;">
      <div style="flex:1;">
        <div style="font-size:12px; opacity:.7; margin-bottom:6px;">Рік</div>
        <select id="salaryYear" class="btn" style="width:100%; margin-bottom:0;">
          <option value="2026" selected>2026</option>
        </select>
      </div>

      <div style="flex:1;">
        <div style="font-size:12px; opacity:.7; margin-bottom:6px;">Місяць</div>
        <select id="salaryMonth" class="btn" style="width:100%; margin-bottom:0;">
          <option value="1">Січень</option>
          <option value="2">Лютий</option>
          <option value="3">Березень</option>
          <option value="4">Квітень</option>
          <option value="5">Травень</option>
          <option value="6">Червень</option>
          <option value="7">Липень</option>
          <option value="8">Серпень</option>
          <option value="9">Вересень</option>
          <option value="10">Жовтень</option>
          <option value="11">Листопад</option>
          <option value="12">Грудень</option>
        </select>
      </div>
    </div>
  </div>

  <div id="managerSalaryContainer"></div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const yearEl = document.getElementById('salaryYear');
  const monthEl = document.getElementById('salaryMonth');
  const container = document.getElementById('managerSalaryContainer');

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

  const money = (value, currency) => {
    const symbols = { UAH: '₴', USD: '$', EUR: '€' };
    return `${new Intl.NumberFormat('uk-UA', {
      minimumFractionDigits: 0,
      maximumFractionDigits: 2
    }).format(Number(value || 0))} ${symbols[currency] || currency}`;
  };

  function defaultMonth() {
    const now = new Date();
    const currentYear = now.getFullYear();
    const month = currentYear === 2026 ? now.getMonth() + 1 : 1;
    monthEl.value = String(month);
  }

  function renderManagers(payload) {
    const managers = Array.isArray(payload?.managers) ? payload.managers : [];
    const titleMonth = monthNames[Number(payload?.month || monthEl.value)] || '';

    if (!managers.length) {
      container.innerHTML = `
        <div class="card">
          <div style="font-weight:800; margin-bottom:6px;">Немає менеджерів</div>
          <div style="opacity:.7; font-size:14px;">У базі не знайдено менеджерів для нарахувань.</div>
        </div>
      `;
      return;
    }

    container.innerHTML = managers.map(manager => {
      const totals = Array.isArray(manager.totals_by_currency) ? manager.totals_by_currency : [];
      const projects = Array.isArray(manager.projects) ? manager.projects : [];

      const totalsHtml = totals.length
        ? totals.map(item => `
            <span class="tag" style="margin-right:6px; margin-bottom:6px; display:inline-flex;">
              ${money(item.amount, item.currency)}
            </span>
          `).join('')
        : `<span style="opacity:.65; font-size:14px;">Нарахувань за ${titleMonth.toLowerCase()} немає</span>`;

      const projectsHtml = projects.length
        ? projects.map(project => `
            <div style="padding:10px 12px; border-radius:14px; background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.06); margin-top:8px;">
              <div style="display:flex; justify-content:space-between; gap:10px;">
                <div style="font-weight:700;">${project.client_name}</div>
                <div style="opacity:.7; font-size:12px;">${project.paid_at}</div>
              </div>
              <div style="margin-top:8px; display:flex; justify-content:space-between; gap:10px; font-size:14px;">
                <div>Проєкт: ${money(project.project_amount, project.currency)}</div>
                <div style="font-weight:800; color:#66f2a8;">1%: ${money(project.commission, project.currency)}</div>
              </div>
            </div>
          `).join('')
        : `<div style="opacity:.6; font-size:14px; margin-top:8px;">Оплачених проєктів за цей місяць немає.</div>`;

      return `
        <div class="card" style="margin-bottom:12px;">
          <div style="font-weight:800; font-size:16px; margin-bottom:8px;">${manager.name}</div>
          <div style="font-size:12px; opacity:.7; margin-bottom:8px;">Загальна сума нарахувань</div>
          <div style="margin-bottom:10px;">${totalsHtml}</div>
          <div style="font-weight:700; font-size:14px; margin-top:4px;">Оплачені проєкти</div>
          ${projectsHtml}
        </div>
      `;
    }).join('');
  }

  async function loadManagerSalary() {
    const year = yearEl.value;
    const month = monthEl.value;

    container.innerHTML = `
      <div class="card">
        <div style="opacity:.7; font-size:14px;">Завантаження...</div>
      </div>
    `;

    try {
      const response = await fetch(`/api/salary/managers-data?year=${encodeURIComponent(year)}&month=${encodeURIComponent(month)}`, {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
      });

      const payload = await response.json();

      if (!response.ok) {
        throw new Error(payload.error || 'Не вдалося завантажити нарахування менеджерам');
      }

      renderManagers(payload);
    } catch (error) {
      container.innerHTML = `
        <div class="card" style="border-color:rgba(255,0,0,.35);">
          <div style="font-weight:800; margin-bottom:6px;">Помилка</div>
          <div style="opacity:.8; font-size:14px;">${error.message}</div>
        </div>
      `;
    }
  }

  defaultMonth();
  yearEl.addEventListener('change', loadManagerSalary);
  monthEl.addEventListener('change', loadManagerSalary);
  loadManagerSalary();
});
</script>

@include('partials.nav.bottom')
@endsection
