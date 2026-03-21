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

  <a href="/salary/accruals" class="card" style="margin-bottom:12px; display:block; text-decoration:none; color:inherit; border:1px solid rgba(255,200,0,.3);">
    <div style="font-weight:800; font-size:16px; margin-bottom:6px;">
      💸 Виплата зарплат
    </div>
    <div style="font-size:14px; opacity:.75;">
      Нарахування по проектах — очікують виплати та оплачені.
    </div>
    <div id="salarySummaryAccruals" style="margin-top:10px; font-weight:800; font-size:15px; color:#f5c842;">
      Завантаження...
    </div>
  </a>

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
  const setText = (id, text) => {
    const el = document.getElementById(id);
    if (el) el.textContent = text;
  };

  const month = new Date().getMonth() + 1;

  try {
    const res = await fetch(`/api/salary/summary?month=${month}&year=2026`);
    if (!res.ok) throw new Error('Помилка завантаження');
    const data = await res.json();

    setText('salarySummaryElectricians', data.electricians);
    setText('salarySummaryInstallers',   data.installers);
    setText('salarySummaryManagers',     data.managers);
    setText('salarySummaryAccountant',   data.accountant);
    setText('salarySummaryForeman',      data.foreman);

    const accrualEl = document.getElementById('salarySummaryAccruals');
    if (accrualEl) {
      const a = data.accruals;
      if (!a || !a.count) {
        accrualEl.textContent = 'Нарахувань немає';
        accrualEl.style.color = '';
      } else {
        const sym = { UAH: '₴', USD: '$', EUR: '€' };
        const formatted = new Intl.NumberFormat('uk-UA', { maximumFractionDigits: 0 })
          .format(a.total) + '\u00a0' + (sym[a.currency] || a.currency);
        accrualEl.textContent = `⏳ Очікує виплати: ${formatted} (${a.count} ${a.count === 1 ? 'працівник' : 'працівники'})`;
      }
    }

    // Set accountant/foreman card links
    try {
      const rulesRes = await fetch('/api/salary-rules/settings-data');
      if (rulesRes.ok) {
        const rulesData = await rulesRes.json();
        const accountantCard = document.getElementById('salaryAccountantCard');
        const foremanCard = document.getElementById('salaryForemanCard');
        const acc = (rulesData.rules || []).find(r => r.staff_group === 'accountant' && r.mode === 'fixed' && r.staff_name);
        const fore = (rulesData.rules || []).find(r => r.staff_group === 'foreman' && r.mode === 'fixed' && r.staff_name);
        if (accountantCard && acc) accountantCard.href = `/salary/fixed/show?staff_group=accountant&staff_name=${encodeURIComponent(acc.staff_name.trim())}`;
        if (foremanCard && fore) foremanCard.href = `/salary/fixed/show?staff_group=foreman&staff_name=${encodeURIComponent(fore.staff_name.trim())}`;
      }
    } catch (_) {}

  } catch (error) {
    const text = error?.message || 'Помилка завантаження';
    ['salarySummaryElectricians','salarySummaryInstallers','salarySummaryManagers',
     'salarySummaryAccountant','salarySummaryForeman'].forEach(id => setText(id, text));
  }
});
</script>

@include('partials.nav.bottom')
@endsection
