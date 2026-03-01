@extends('layouts.app')

@push('styles')
  <link rel="stylesheet" href="/css/nav-telegram.css?v={{ filemtime(public_path('css/nav-telegram.css')) }}">
  <link rel="stylesheet" href="/css/project.css?v={{ filemtime(public_path('css/project.css')) }}">
@endpush

@section('content')
<main class="">
  <div class="card" style="margin-bottom:15px;">
    <div style="font-weight:800; font-size:18px; text-align:center;">
      💰 Зарплатня
    </div>
  </div>

  <a href="/salary/electricians" class="card" style="margin-bottom:12px; display:block; text-decoration:none; color:inherit;">
    <div style="font-weight:800; font-size:16px; margin-bottom:6px;">
      ⚡ Зарплата електрикам
    </div>
    <div style="font-size:14px; opacity:.75;">
      Перейти до карток електриків.
    </div>
    <div id="salarySummaryElectricians" style="margin-top:10px; font-weight:800; font-size:15px;">
      Завантаження...
    </div>
  </a>

  <a href="/salary/installers" class="card" style="margin-bottom:12px; display:block; text-decoration:none; color:inherit;">
    <div style="font-weight:800; font-size:16px; margin-bottom:6px;">
      🛠 Зарплата Монтажникам
    </div>
    <div style="font-size:14px; opacity:.75;">
      Перейти до карток монтажних бригад.
    </div>
    <div id="salarySummaryInstallers" style="margin-top:10px; font-weight:800; font-size:15px;">
      Завантаження...
    </div>
  </a>

  <a href="/salary/managers" class="card" style="margin-bottom:12px; display:block; text-decoration:none; color:inherit;">
    <div style="font-weight:800; font-size:16px; margin-bottom:6px;">
      📈 Зарплата відділу продажів
    </div>
    <div style="font-size:14px; opacity:.75;">
      Начальник торгового відділу, Менеджери, Діловод.
    </div>
    <div id="salarySummaryManagers" style="margin-top:10px; font-weight:800; font-size:15px;">
      Завантаження...
    </div>
  </a>

  <a href="/salary" id="salaryAccountantCard" class="card" style="margin-bottom:12px; display:block; text-decoration:none; color:inherit;">
    <div style="font-weight:800; font-size:16px; margin-bottom:6px;">
      🧾 Зарплата Соловей
    </div>
    <div style="font-size:14px; opacity:.75;">
      Помісячна зарплата Соловей.
    </div>
    <div id="salarySummaryAccountant" style="margin-top:10px; font-weight:800; font-size:15px;">
      Завантаження...
    </div>
  </a>

  <a href="/salary" id="salaryForemanCard" class="card" style="display:block; text-decoration:none; color:inherit;">
    <div style="font-weight:800; font-size:16px; margin-bottom:6px;">
      🏗 Зарплата Оніпко
    </div>
    <div style="font-size:14px; opacity:.75;">
      Помісячна зарплата Оніпко.
    </div>
    <div id="salarySummaryForeman" style="margin-top:10px; font-weight:800; font-size:15px;">
      Завантаження...
    </div>
  </a>
</main>

<script>
document.addEventListener('DOMContentLoaded', async function () {
  const summaryEls = {
    electricians: document.getElementById('salarySummaryElectricians'),
    installers: document.getElementById('salarySummaryInstallers'),
    managers: document.getElementById('salarySummaryManagers'),
    accountant: document.getElementById('salarySummaryAccountant'),
    foreman: document.getElementById('salarySummaryForeman'),
  };
  const accountantCard = document.getElementById('salaryAccountantCard');
  const foremanCard = document.getElementById('salaryForemanCard');

  const setText = (key, text) => {
    if (summaryEls[key]) summaryEls[key].textContent = text;
  };

  const formatMoney = (value, currency) => {
    const symbols = { UAH: '₴', USD: '$', EUR: '€' };
    return `${new Intl.NumberFormat('uk-UA', {
      minimumFractionDigits: 0,
      maximumFractionDigits: 2,
    }).format(Number(value || 0))} ${symbols[currency] || currency}`;
  };

  const renderTotals = (totals) => {
    const entries = Object.entries(totals || {}).filter(([, amount]) => Number(amount || 0) > 0);
    if (!entries.length) return 'Нарахувань немає';
    return entries.map(([currency, amount]) => formatMoney(amount, currency)).join(' + ');
  };

  const addAmount = (bucket, currency, amount) => {
    const cur = String(currency || 'UAH');
    if (!bucket[cur]) bucket[cur] = 0;
    bucket[cur] += Number(amount || 0);
  };

  function parseInverterPower(inverterName) {
    const text = String(inverterName || '').replace(',', '.');
    const matches = text.match(/(\d+(?:\.\d+)?)\s*(?:k|kw|квт|кв)/i);
    if (matches) return Number(matches[1]);

    const anyNumber = text.match(/(\d+(?:\.\d+)?)/);
    return anyNumber ? Number(anyNumber[1]) : null;
  }

  function isLikelyHybrid(inverterName) {
    const text = String(inverterName || '').toLowerCase().replace(/\s+/g, ' ').trim();
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
      'гиб'
    ].some(token => text.includes(token));
  }

  function calculateElectricianSalary(project, rule) {
    const inverter = String(project?.inverter || '').trim();
    const power = parseInverterPower(inverter);
    const hybrid = isLikelyHybrid(inverter);
    const underOrEqual50 = power === null ? true : power <= 50;
    const toNumber = (value) => Number(value || 0);

    if (hybrid) {
      return underOrEqual50
        ? toNumber(rule?.piecework_hybrid_le_50)
        : toNumber(rule?.piecework_hybrid_gt_50);
    }

    return underOrEqual50
      ? toNumber(rule?.piecework_grid_le_50)
      : toNumber(rule?.piecework_grid_gt_50);
  }

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
    return (totalKw * unitRate) + foremanBonus;
  }

  function currentSalaryMonth() {
    const now = new Date();
    return now.getFullYear() === 2026 ? now.getMonth() + 1 : 1;
  }

  try {
    const month = currentSalaryMonth();

    const [
      staffRes,
      projectsRes,
      electricianRulesRes,
      installerRulesRes,
      accountantRulesRes,
      foremanRulesRes,
      managerRes
    ] = await Promise.all([
      fetch('/api/construction-staff-options', { headers: { 'Accept': 'application/json' } }),
      fetch('/api/sales-projects', { headers: { 'Accept': 'application/json' } }),
      fetch('/api/salary-rules?staff_group=electrician', { headers: { 'Accept': 'application/json' } }),
      fetch('/api/salary-rules?staff_group=installation_team', { headers: { 'Accept': 'application/json' } }),
      fetch('/api/salary-rules?staff_group=accountant', { headers: { 'Accept': 'application/json' } }),
      fetch('/api/salary-rules?staff_group=foreman', { headers: { 'Accept': 'application/json' } }),
      fetch(`/api/salary/managers-data?year=2026&month=${month}`, { headers: { 'Accept': 'application/json' } }),
    ]);

    const staff = await staffRes.json();
    const projects = await projectsRes.json();
    const electricianRulesPayload = await electricianRulesRes.json();
    const installerRulesPayload = await installerRulesRes.json();
    const accountantRulesPayload = await accountantRulesRes.json();
    const foremanRulesPayload = await foremanRulesRes.json();
    const managerPayload = await managerRes.json();

    if (!staffRes.ok) throw new Error(staff.error || 'Не вдалося завантажити персонал');
    if (!projectsRes.ok) throw new Error(projects.error || 'Не вдалося завантажити проєкти');
    if (!electricianRulesRes.ok) throw new Error(electricianRulesPayload.error || 'Не вдалося завантажити правила електриків');
    if (!installerRulesRes.ok) throw new Error(installerRulesPayload.error || 'Не вдалося завантажити правила монтажників');
    if (!accountantRulesRes.ok) throw new Error(accountantRulesPayload.error || 'Не вдалося завантажити правила бухгалтера');
    if (!foremanRulesRes.ok) throw new Error(foremanRulesPayload.error || 'Не вдалося завантажити правила прораба');
    if (!managerRes.ok) throw new Error(managerPayload.error || 'Не вдалося завантажити правила відділу продажів');

    const projectList = Array.isArray(projects) ? projects : [];

    const electricians = Array.isArray(staff?.electrician) ? staff.electrician : [];
    const electricianRules = new Map(
      (Array.isArray(electricianRulesPayload?.rules) ? electricianRulesPayload.rules : [])
        .map(rule => [String(rule?.staff_name || '').trim().toLowerCase(), rule])
    );
    const electricianTotals = {};

    electricians
      .map(item => String(item?.name || '').trim())
      .filter(Boolean)
      .forEach(name => {
        const rule = electricianRules.get(name.toLowerCase());
        if (!rule) return;

        if (String(rule.mode) === 'fixed') {
          addAmount(electricianTotals, rule.currency || 'UAH', rule.fixed_amount || 0);
          return;
        }

        projectList
          .filter(project => String(project?.electrician || '').trim().toLowerCase() === name.toLowerCase())
          .forEach(project => {
            addAmount(electricianTotals, rule.currency || 'USD', calculateElectricianSalary(project, rule));
          });
      });

    const installerTeams = Array.isArray(staff?.installation_team) ? staff.installation_team : [];
    const installerRules = new Map(
      (Array.isArray(installerRulesPayload?.rules) ? installerRulesPayload.rules : [])
        .map(rule => [String(rule?.staff_name || '').trim().toLowerCase(), rule])
    );
    const installerTotals = {};

    installerTeams
      .map(item => String(item?.name || '').trim())
      .filter(Boolean)
      .forEach(name => {
        const rule = installerRules.get(name.toLowerCase());
        if (!rule) return;

        if (String(rule.mode) === 'fixed') {
          addAmount(installerTotals, rule.currency || 'UAH', rule.fixed_amount || 0);
          return;
        }

        projectList
          .filter(project => String(project?.installation_team || '').trim().toLowerCase() === name.toLowerCase())
          .forEach(project => {
            addAmount(installerTotals, rule.currency || 'USD', calculateInstallerSalary(project, rule));
          });
      });

    const accountantTotals = {};
    const accountantRules = Array.isArray(accountantRulesPayload?.rules) ? accountantRulesPayload.rules : [];
    accountantRules.forEach(rule => {
      if (String(rule?.mode) === 'fixed') {
        addAmount(accountantTotals, rule.currency || 'UAH', rule.fixed_amount || 0);
      }
    });
    const firstFixedAccountant = accountantRules.find(rule => String(rule?.mode) === 'fixed' && String(rule?.staff_name || '').trim() !== '');
    if (accountantCard && firstFixedAccountant) {
      accountantCard.href = `/salary/fixed/show?staff_group=accountant&staff_name=${encodeURIComponent(String(firstFixedAccountant.staff_name || '').trim())}`;
    }

    const foremanTotals = {};
    const foremanRules = Array.isArray(foremanRulesPayload?.rules) ? foremanRulesPayload.rules : [];
    foremanRules.forEach(rule => {
      if (String(rule?.mode) === 'fixed') {
        addAmount(foremanTotals, rule.currency || 'UAH', rule.fixed_amount || 0);
      }
    });
    const firstFixedForeman = foremanRules.find(rule => String(rule?.mode) === 'fixed' && String(rule?.staff_name || '').trim() !== '');
    if (foremanCard && firstFixedForeman) {
      foremanCard.href = `/salary/fixed/show?staff_group=foreman&staff_name=${encodeURIComponent(String(firstFixedForeman.staff_name || '').trim())}`;
    }

    const managerTotals = {};
    (Array.isArray(managerPayload?.managers) ? managerPayload.managers : []).forEach(manager => {
      (Array.isArray(manager?.totals_by_currency) ? manager.totals_by_currency : []).forEach(item => {
        addAmount(managerTotals, item.currency || 'UAH', item.amount || 0);
      });
    });

    setText('electricians', renderTotals(electricianTotals));
    setText('installers', renderTotals(installerTotals));
    setText('managers', renderTotals(managerTotals));
    setText('accountant', renderTotals(accountantTotals));
    setText('foreman', renderTotals(foremanTotals));
  } catch (error) {
    const text = error?.message || 'Помилка завантаження';
    setText('electricians', text);
    setText('installers', text);
    setText('managers', text);
    setText('accountant', text);
    setText('foreman', text);
  }
});
</script>

@include('partials.nav.bottom')
@endsection
