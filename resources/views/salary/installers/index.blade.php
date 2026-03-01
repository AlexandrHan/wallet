@extends('layouts.app')

@push('styles')
  <link rel="stylesheet" href="/css/nav-telegram.css?v={{ filemtime(public_path('css/nav-telegram.css')) }}">
  <link rel="stylesheet" href="/css/project.css?v={{ filemtime(public_path('css/project.css')) }}">
@endpush

@section('content')
<main class="">
  <div class="card" style="margin-bottom:15px;">
    <a href="/salary" style="display:block; font-weight:800; font-size:18px; text-align:center; text-decoration:none; color:inherit;">
      🛠 З/П монтажникам
    </a>
  </div>

  <div id="salaryInstallersList"></div>
</main>

<script>
document.addEventListener('DOMContentLoaded', async function () {
  const list = document.getElementById('salaryInstallersList');
  if (!list) return;

  const esc = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

  const formatMoney = (value, currency) => {
    const symbols = { UAH: '₴', USD: '$', EUR: '€' };
    return `${new Intl.NumberFormat('uk-UA', {
      maximumFractionDigits: 0,
    }).format(Number(value || 0))} ${symbols[currency] || currency}`;
  };

  function parsePanelWatts(panelName) {
    const text = String(panelName || '').replace(',', '.');
    const match = text.match(/(\d+(?:\.\d+)?)\s*(?:w|wp|вт)/i);
    if (match) return Number(match[1]);

    const anyNumber = text.match(/(\d+(?:\.\d+)?)/);
    return anyNumber ? Number(anyNumber[1]) : null;
  }

  function calculateInstallerSalary(project, rule) {
    const watts = parsePanelWatts(project?.panel_name);
    const qty = Number(project?.panel_qty || 0);
    const totalKwRaw = watts && qty ? (watts * qty) / 1000 : 0;
    const totalKw = Math.ceil(totalKwRaw);
    const unitRate = Number(rule?.piecework_unit_rate || 0);
    const foremanBonus = Number(rule?.foreman_bonus || 0);
    const amount = (totalKw * unitRate) + foremanBonus;

    return {
      amount,
      currency: rule?.currency || 'USD',
      totalKw,
      watts,
      qty,
    };
  }

  function renderFixedCard(title, amount, currency) {
    return `
      <div class="card" style="margin-bottom:12px;">
        <div style="display:flex; justify-content:space-between; gap:10px; align-items:center;">
          <div>
            <div style="font-weight:800; font-size:16px;">🛠 ${esc(title)}</div>
            <div style="font-size:13px; opacity:.72; margin-top:4px;">Фіксована ставка</div>
          </div>
          <div style="font-weight:900; font-size:18px; white-space:nowrap;">
            ${formatMoney(amount, currency)}
          </div>
        </div>
      </div>
    `;
  }

  list.innerHTML = `
    <div class="card">
      <div style="font-size:14px; opacity:.8; text-align:center;">
        Завантаження монтажних бригад...
      </div>
    </div>
  `;

  try {
    const [staffRes, projectsRes, rulesRes] = await Promise.all([
      fetch('/api/construction-staff-options'),
      fetch('/api/sales-projects'),
      fetch('/api/salary-rules?staff_group=installation_team'),
    ]);

    const staff = await staffRes.json();
    const projects = await projectsRes.json();
    const rulesPayload = await rulesRes.json();

    if (!staffRes.ok) throw new Error(staff.error || 'Не вдалося завантажити монтажників');
    if (!projectsRes.ok) throw new Error(projects.error || 'Не вдалося завантажити проєкти');
    if (!rulesRes.ok) throw new Error(rulesPayload.error || 'Не вдалося завантажити правила зарплатні');

    const teams = Array.isArray(staff.installation_team) ? staff.installation_team : [];
    const rules = Array.isArray(rulesPayload.rules) ? rulesPayload.rules : [];
    const rulesMap = new Map(
      rules.map(rule => [String(rule?.staff_name || '').trim().toLowerCase(), rule])
    );
    const projectList = Array.isArray(projects) ? projects : [];

    if (!teams.length) {
      list.innerHTML = `
        <div class="card">
          <div style="font-size:14px; opacity:.8; text-align:center;">
            Монтажних бригад поки немає
          </div>
        </div>
      `;
      return;
    }

    const html = [];

    teams
      .map(team => String(team.name || '').trim())
      .filter(Boolean)
      .forEach((name) => {
        const key = name.toLowerCase();
        const rule = rulesMap.get(key);

        if (!rule) {
          html.push(`
            <div class="card" style="margin-bottom:12px;">
              <div style="font-weight:800; font-size:16px; margin-bottom:6px;">🛠 ${esc(name)}</div>
              <div style="font-size:14px; opacity:.75;">
                Правило нарахування ще не задано.
              </div>
            </div>
          `);
          return;
        }

        if (String(rule.mode) === 'fixed') {
          html.push(renderFixedCard(name, rule.fixed_amount || 0, rule.currency || 'UAH'));
          return;
        }

        const total = projectList
          .filter(project => String(project?.installation_team || '').trim().toLowerCase() === key)
          .map(project => calculateInstallerSalary(project, rule))
          .reduce((sum, calc) => sum + Number(calc.amount || 0), 0);

        html.push(`
          <a href="/salary/installers/show?name=${encodeURIComponent(name)}" class="card" style="margin-bottom:12px; display:block; text-decoration:none; color:inherit;">
            <div style="display:flex; justify-content:space-between; gap:10px; align-items:center;">
              <div>
                <div style="font-weight:800; font-size:16px;">🛠 ${esc(name)}</div>
                <div style="font-size:13px; opacity:.72; margin-top:4px;">Відкрити проєкти бригади</div>
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
