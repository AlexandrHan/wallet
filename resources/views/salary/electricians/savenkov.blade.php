@extends('layouts.app')

@push('styles')
  <link rel="stylesheet" href="/css/nav-telegram.css?v={{ filemtime(public_path('css/nav-telegram.css')) }}">
  <link rel="stylesheet" href="/css/project.css?v={{ filemtime(public_path('css/project.css')) }}">
@endpush

@section('content')
<main class="">
  <div class="card" style="margin-bottom:15px;">
    <div style="font-weight:800; font-size:18px; text-align:center;">
      ⚡ <span id="salaryElectricianNameTitle">Електрик</span>
    </div>
  </div>

  <div id="savenkovSalaryProjects"></div>
</main>

<script>
document.addEventListener('DOMContentLoaded', async function () {
  const root = document.getElementById('savenkovSalaryProjects');
  if (!root) return;
  const query = new URLSearchParams(window.location.search);
  const ELECTRICIAN_NAME = String(query.get('name') || '').trim();
  const ELECTRICIAN_KEY = ELECTRICIAN_NAME
    .toLowerCase()
    .replace(/[^\p{L}\p{N}]+/gu, '_')
    .replace(/^_+|_+$/g, '') || 'electrician';
  const PAID_KEY = `salary_${ELECTRICIAN_KEY}_paid_projects`;
  const PAID_OPEN_KEY = `salary_${ELECTRICIAN_KEY}_paid_open`;

  const titleEl = document.getElementById('salaryElectricianNameTitle');
  if (titleEl) titleEl.textContent = ELECTRICIAN_NAME || 'Електрик';

  const esc = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

  const formatMoney = (value, currency) => {
    const symbols = { USD: '$' };
    return `${new Intl.NumberFormat('uk-UA', {
      maximumFractionDigits: 0,
    }).format(Number(value || 0))} ${symbols[currency] || currency}`;
  };

  function parseInverterPower(inverterName) {
    const text = String(inverterName || '').replace(',', '.');
    const matches = text.match(/(\d+(?:\.\d+)?)\s*(?:k|kw|квт|кв)/i);
    if (matches) return Number(matches[1]);

    const anyNumber = text.match(/(\d+(?:\.\d+)?)/);
    return anyNumber ? Number(anyNumber[1]) : null;
  }

  function isLikelyHybrid(inverterName) {
    const text = String(inverterName || '')
      .toLowerCase()
      .replace(/\s+/g, ' ')
      .trim();

    if (!text) return false;

    return [
      'hybrid',
      'hybr',
      'hibr',
      'hibryd',
      'гібрид',
      'гибрид',
      'гiбрид',
      'гібр',
      'гибр',
      'гвбрид',
      'гиб',
    ].some(token => text.includes(token));
  }

  async function loadRule() {
    const res = await fetch(`/api/salary-rules?staff_group=electrician&staff_name=${encodeURIComponent(ELECTRICIAN_NAME)}`);
    const payload = await res.json();
    if (!res.ok) {
      throw new Error(payload.error || 'Не вдалося завантажити правило зарплатні');
    }

    const rule = Array.isArray(payload.rules) ? payload.rules[0] : null;
    if (!rule) {
      throw new Error(`Для ${ELECTRICIAN_NAME || 'цього електрика'} не налаштоване правило зарплатні`);
    }

    return rule;
  }

  function getPaidSet() {
    try {
      const raw = JSON.parse(localStorage.getItem(PAID_KEY) || '[]');
      return new Set(Array.isArray(raw) ? raw.map(Number) : []);
    } catch (_) {
      return new Set();
    }
  }

  function savePaidSet(set) {
    localStorage.setItem(PAID_KEY, JSON.stringify(Array.from(set)));
  }

  function isPaidOpen() {
    return localStorage.getItem(PAID_OPEN_KEY) === '1';
  }

  function setPaidOpen(value) {
    localStorage.setItem(PAID_OPEN_KEY, value ? '1' : '0');
  }

  function renderProjectCard(item, paid = false) {
    const project = item.project;
    const salary = item.salary;
    const cardClass = paid ? ' project-card--green' : '';

    return `
      <div class="card project-card${cardClass}" data-project-card="${project.id}" style="margin-bottom:12px;">
        <div class="project-header" data-project-toggle="${project.id}">
          <div class="project-header-row">
            <div class="project-header-name">${esc(project.client_name || 'Без назви')}</div>
            <div class="project-header-meta">${salary.power ? `${esc(salary.power)} кВт` : '—'}</div>
          </div>
          <div class="project-header-row">
            <div class="project-header-sub">${esc(ELECTRICIAN_NAME)}</div>
            <div class="project-header-meta" style="font-weight:800; opacity:.9;">${formatMoney(salary.amount, salary.currency)}</div>
          </div>
        </div>

        <div class="project-body">
          <div class="project-field-label">Обладнання</div>
          <div style="font-size:14px; opacity:.85;">
            <div><strong>Інвертор:</strong> ${esc(project.inverter || 'Не вказано')}</div>
            <div style="margin-top:6px;"><strong>BMS:</strong> ${esc(project.bms || 'Не вказано')}</div>
            <div style="margin-top:6px;"><strong>АКБ:</strong> ${esc(project.battery_name || 'Не вказано')}</div>
            <div style="margin-top:6px;"><strong>К-сть АКБ:</strong> ${esc(project.battery_qty || 'Не вказано')}</div>
          </div>

          <div class="project-divider project-divider--spaced"></div>

          <button
            type="button"
            class="btn save project-paid-btn"
            data-project-paid="${project.id}"
            ${paid ? 'disabled' : ''}>
            ${paid ? 'Оплачено' : 'Оплачено'}
          </button>
        </div>
      </div>
    `;
  }

  function renderGroupCard(title, items, open) {
    if (!items.length) {
      return `
        <div class="card" style="margin-bottom:12px;">
          <div style="font-weight:800; font-size:16px; margin-bottom:6px;">${esc(title)}</div>
          <div style="font-size:14px; opacity:.75;">Список порожній.</div>
        </div>
      `;
    }

    return `
      <div class="card" style="margin-bottom:12px;">
        <div class="project-header" data-paid-toggle="1" style="cursor:pointer;">
          <div class="project-header-row">
            <div class="project-header-name">${esc(title)}</div>
            <div class="project-header-meta">${open ? '▾' : '▸'}</div>
          </div>
        </div>
        <div class="project-body" style="display:${open ? 'block' : 'none'};">
          ${items.map(item => renderProjectCard(item, true)).join('')}
        </div>
      </div>
    `;
  }

  let allItems = [];
  let salaryRule = null;

  async function loadProjects() {
    if (!ELECTRICIAN_NAME) {
      root.innerHTML = `
        <div class="card">
          <div style="font-size:14px; opacity:.8; text-align:center;">
            Не вказано електрика
          </div>
        </div>
      `;
      return;
    }

    root.innerHTML = `
      <div class="card">
        <div style="font-size:14px; opacity:.8; text-align:center;">
          Завантаження проєктів...
        </div>
      </div>
    `;

    try {
      const [rule, res] = await Promise.all([
        loadRule(),
        fetch('/api/sales-projects'),
      ]);
      const projects = await res.json();

      if (!res.ok) {
        throw new Error(projects.error || 'Не вдалося завантажити проєкти');
      }

      salaryRule = rule;

      if (String(salaryRule.mode) !== 'piecework') {
        root.innerHTML = `
          <div class="card">
            <div style="font-size:14px; opacity:.8; text-align:center;">
              Для ${esc(ELECTRICIAN_NAME)} зараз встановлена ставка, а не виробіток.
            </div>
          </div>
        `;
        return;
      }

      allItems = (Array.isArray(projects) ? projects : [])
        .filter(project => String(project?.electrician || '').trim().toLowerCase() === String(ELECTRICIAN_NAME).trim().toLowerCase())
        .map(project => {
          const inverter = String(project?.inverter || '').trim();
        const power = parseInverterPower(inverter);
        const hybrid = isLikelyHybrid(inverter);
        const underOrEqual50 = power === null ? true : power <= 50;

        const amount = hybrid
          ? Number(underOrEqual50 ? salaryRule.piecework_hybrid_le_50 || 0 : salaryRule.piecework_hybrid_gt_50 || 0)
          : Number(underOrEqual50 ? salaryRule.piecework_grid_le_50 || 0 : salaryRule.piecework_grid_gt_50 || 0);

        return {
          project,
          salary: {
            amount,
            currency: salaryRule.currency || 'USD',
            power,
          },
        };
      });

      render();
    } catch (err) {
      root.innerHTML = `
        <div class="card">
          <div style="font-size:14px; opacity:.8; text-align:center;">
            ${esc(err.message || 'Помилка завантаження')}
          </div>
        </div>
      `;
    }
  }

  function render() {
    const paidSet = getPaidSet();
    const activeItems = allItems.filter(item => !paidSet.has(Number(item.project.id)));
    const paidItems = allItems.filter(item => paidSet.has(Number(item.project.id)));
    const paidOpen = isPaidOpen();

    const activeHtml = activeItems.length
      ? activeItems.map(item => renderProjectCard(item, false)).join('')
      : `
        <div class="card" style="margin-bottom:12px;">
          <div style="font-size:14px; opacity:.8; text-align:center;">
            Активних неоплачених проєктів немає
          </div>
        </div>
      `;

    root.innerHTML = `
      ${renderGroupCard('✅ Оплачені', paidItems, paidOpen)}
      ${activeHtml}
    `;
  }

  document.addEventListener('click', function (e) {
    const target = e.target instanceof Element ? e.target : null;
    if (!target) return;

    const paidBtn = target.closest('[data-project-paid]');
    if (paidBtn instanceof HTMLButtonElement) {
      const id = Number(paidBtn.dataset.projectPaid || 0);
      if (!id) return;
      const paidSet = getPaidSet();
      paidSet.add(id);
      savePaidSet(paidSet);
      render();
      return;
    }

    const paidToggle = target.closest('[data-paid-toggle]');
    if (paidToggle) {
      const body = paidToggle.parentElement?.querySelector('.project-body');
      if (!body) return;
      const open = window.getComputedStyle(body).display === 'none';
      body.style.display = open ? 'block' : 'none';
      const icon = paidToggle.querySelector('.project-header-meta');
      if (icon) icon.textContent = open ? '▾' : '▸';
      setPaidOpen(open);
      return;
    }

    const projectToggle = target.closest('[data-project-toggle]');
    if (!projectToggle) return;
    if (target.closest('[data-project-paid]')) return;

    const card = projectToggle.closest('[data-project-card]');
    const body = card?.querySelector('.project-body');
    if (!body) return;

    const isOpen = window.getComputedStyle(body).display !== 'none';
    body.style.display = isOpen ? 'none' : 'block';
  });

  loadProjects();
});
</script>

@include('partials.nav.bottom')
@endsection
