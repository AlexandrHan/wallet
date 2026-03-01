@extends('layouts.app')

@push('styles')
  <link rel="stylesheet" href="/css/nav-telegram.css?v={{ filemtime(public_path('css/nav-telegram.css')) }}">
  <link rel="stylesheet" href="/css/project.css?v={{ filemtime(public_path('css/project.css')) }}">
@endpush

@section('content')
<main class="">
  <div class="card" style="margin-bottom:15px;">
    <a href="/salary" style="display:block; font-weight:800; font-size:18px; text-align:center; text-decoration:none; color:inherit;">
      ⚡ З/П електрикам
    </a>
  </div>

  <div id="salaryElectriciansList"></div>
</main>

<script>
document.addEventListener('DOMContentLoaded', async function () {
  const list = document.getElementById('salaryElectriciansList');
  if (!list) return;

  const esc = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

  const formatMoney = (value, currency) => {
    const symbols = { UAH: '₴', USD: '$' };
    const num = Number(value || 0);
    return `${new Intl.NumberFormat('uk-UA', {
      maximumFractionDigits: 0,
    }).format(num)} ${symbols[currency] || currency}`;
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

  function calculatePieceworkSalary(project, rule) {
    const inverter = String(project?.inverter || '').trim();
    const power = parseInverterPower(inverter);
    const hybrid = isLikelyHybrid(inverter);
    const underOrEqual50 = power === null ? true : power <= 50;
    const toNumber = (value) => Number(value || 0);

    if (hybrid) {
      return {
        amount: underOrEqual50
          ? toNumber(rule?.piecework_hybrid_le_50)
          : toNumber(rule?.piecework_hybrid_gt_50),
        currency: rule?.currency || 'USD',
        typeLabel: 'Гібрид',
        power,
      };
    }

    return {
      amount: underOrEqual50
        ? toNumber(rule?.piecework_grid_le_50)
        : toNumber(rule?.piecework_grid_gt_50),
      currency: rule?.currency || 'USD',
      typeLabel: 'Мережева',
      power,
    };
  }

  function renderFixedCard(title, subtitle, amount, currency, href, icon = '⚡') {
    return `
      <a href="${esc(href)}" class="card" style="margin-bottom:12px; display:block; text-decoration:none; color:inherit;">
        <div style="display:flex; justify-content:space-between; gap:10px; align-items:center;">
          <div>
            <div style="font-weight:800; font-size:16px;">${icon} ${esc(title)}</div>
            <div style="font-size:13px; opacity:.72; margin-top:4px;">${esc(subtitle)}</div>
          </div>
          <div style="font-weight:900; font-size:18px; white-space:nowrap;">
            ${formatMoney(amount, currency)}
          </div>
        </div>
      </a>
    `;
  }

  list.innerHTML = `
    <div class="card">
      <div style="font-size:14px; opacity:.8; text-align:center;">
        Завантаження електриків...
      </div>
    </div>
  `;

  try {
    const [staffRes, projectsRes, rulesRes] = await Promise.all([
      fetch('/api/construction-staff-options'),
      fetch('/api/sales-projects'),
      fetch('/api/salary-rules?staff_group=electrician'),
    ]);

    const staff = await staffRes.json();
    const projects = await projectsRes.json();
    const rulesPayload = await rulesRes.json();

    if (!staffRes.ok) {
      throw new Error(staff.error || 'Не вдалося завантажити електриків');
    }

    if (!projectsRes.ok) {
      throw new Error(projects.error || 'Не вдалося завантажити проєкти');
    }

    if (!rulesRes.ok) {
      throw new Error(rulesPayload.error || 'Не вдалося завантажити правила зарплатні');
    }

    const electricians = Array.isArray(staff.electrician) ? staff.electrician : [];
    const rules = Array.isArray(rulesPayload.rules) ? rulesPayload.rules : [];
    const rulesMap = new Map(
      rules.map(rule => [String(rule?.staff_name || '').trim().toLowerCase(), rule])
    );

    if (!electricians.length) {
      list.innerHTML = `
        <div class="card">
          <div style="font-size:14px; opacity:.8; text-align:center;">
            Електриків поки немає
          </div>
        </div>
      `;
      return;
    }

    const projectList = Array.isArray(projects) ? projects : [];

    const html = [];

    electricians
      .map(person => String(person.name || '').trim())
      .filter(Boolean)
      .forEach((name) => {
        const key = name.toLowerCase();
        const rule = rulesMap.get(key);

        if (!rule) {
          html.push(`
            <div class="card" style="margin-bottom:12px;">
              <div style="font-weight:800; font-size:16px; margin-bottom:6px;">⚡ ${esc(name)}</div>
              <div style="font-size:14px; opacity:.75;">
                Правило нарахування ще не задано.
              </div>
            </div>
          `);
          return;
        }

        if (String(rule.mode) === 'fixed') {
          html.push(renderFixedCard(
            name,
            'Помісячна зарплата',
            rule.fixed_amount || 0,
            rule.currency || 'UAH',
            `/salary/fixed/show?staff_group=electrician&staff_name=${encodeURIComponent(name)}`,
            '⚡'
          ));
          return;
        }

        const total = projectList
          .filter(project => String(project?.electrician || '').trim().toLowerCase() === key)
          .map(project => calculatePieceworkSalary(project, rule))
          .reduce((sum, calc) => sum + Number(calc.amount || 0), 0);

        html.push(`
          <a href="/salary/electricians/show?name=${encodeURIComponent(name)}" class="card" style="margin-bottom:12px; display:block; text-decoration:none; color:inherit;">
            <div style="display:flex; justify-content:space-between; gap:10px; align-items:center;">
              <div>
                <div style="font-weight:800; font-size:16px;">⚡ ${esc(name)}</div>
                <div style="font-size:13px; opacity:.72; margin-top:4px;">Відкрити проєкти ${esc(name)}</div>
              </div>
              <div style="font-weight:900; font-size:18px; white-space:nowrap;">
                ${formatMoney(total, rule.currency || 'USD')}
              </div>
            </div>
          </a>
        `);
      });

    list.innerHTML = html.join('');
  } catch (err) {
    list.innerHTML = `
      <div class="card">
        <div style="font-size:14px; opacity:.8; text-align:center;">
          ${esc(err.message || 'Помилка завантаження')}
        </div>
      </div>
    `;
  }
});
</script>

@include('partials.nav.bottom')
@endsection
