@php
  $qcCount = \Illuminate\Support\Facades\DB::table('quality_checks')
    ->whereIn('status', ['pending', 'has_deficiencies', 'deficiencies_fixed'])
    ->count();

  $pendingSalaryCount = app(\App\Services\SalaryAccrualEligibilityService::class)
    ->countPendingEligible();
@endphp

<div id="tgOwnerMenu" class="tg-menu">
  <div class="tg-menu__top">
    <div class="tg-menu__title">Меню</div>
    <a class="tg-menu__close" href="#" aria-label="Закрити">✕</a>
  </div>

  <div class="tg-menu__content">
    <a class="tg-menu__item" href="/messages" style="font-size:15px; font-weight:600; padding:14px 18px; margin-bottom:14px; border-bottom:1px solid rgba(255,255,255,0.06);">💬 Чат</a>

    <details class="tg-acc">
      <summary class="tg-acc__title">💳 Гаманець</summary>
      <div class="tg-acc__body">
        <a class="tg-menu__item" href="/">🏦 Мій гаманець</a>
        <button type="button" class="tg-menu__item js-staff-cash">👥 КЕШ співробітників</button>
        <button type="button" class="tg-menu__item js-show-rates">💱 Обмінник</button>
      </div>
    </details>

    <details class="tg-acc">
      <summary class="tg-acc__title">🏗 Технічний відділ</summary>
      <div class="tg-acc__body">
        <a class="tg-menu__item" href="/projects">🏗 Проекти (активні)</a>
        <a class="tg-menu__item" href="/projects/service-repair">🛠 Сервіс та ремонт</a>
        <a class="tg-menu__item" href="{{ route('reclamations.index') }}">🧾 Рекламації</a>
        <a class="tg-menu__item" href="/quality-checks" style="display:flex; justify-content:space-between; align-items:center;">
          <span>🔍 Контроль якості</span>
          @if($qcCount > 0)
            <span style="min-width:20px; height:20px; padding:0 6px; background:#e53e3e; color:#fff;
              border-radius:99px; font-size:11px; font-weight:800; line-height:20px; text-align:center;">{{ $qcCount }}</span>
          @endif
        </a>
        <button type="button" class="tg-menu__item tg-menu__item--static" disabled>📊 Графіки</button>
      </div>
    </details>

    <details class="tg-acc">
      <summary class="tg-acc__title">📈 Відділ продажу</summary>
      <div class="tg-acc__body">
        <a class="tg-menu__item" href="/finance">🧾 Продажі</a>
        <a class="tg-menu__item" href="/sales/amo-ntv-report">📋 АМО звіт</a>
      </div>
    </details>

    <details class="tg-acc">
      <summary class="tg-acc__title">📦 Склад (Solar Glass)</summary>
      <div class="tg-acc__body">
        <a class="tg-menu__item" href="/solar-glass">☀️ Solar Glass</a>
        <a class="tg-menu__item" href="/equipment-orders">📦 Потреба в обладнанні</a>
        <a class="tg-menu__item" href="/equipment-purchase-orders">🛒 Замовлення обладнання</a>
        <a class="tg-menu__item" href="/projects/delivered">🚚 Доставлено на об'єкт</a>
      </div>
    </details>

    <details class="tg-acc">
      <summary class="tg-acc__title">📦 Sun Fix</summary>
      <div class="tg-acc__body">
        <a class="tg-menu__item" href="/stock">📦 Склад</a>
        <a class="tg-menu__item" href="/deliveries">🚚 Поставки</a>
        <a class="tg-menu__item" href="/equipment-orders">📦 Потреба в обладнанні</a>
        <a class="tg-menu__item" href="/stock/sales-reports">📊 Історія звітів складу</a>
        <a class="tg-menu__item" href="/stock/supplier-cash">💸 Борги</a>
      </div>
    </details>

    <details class="tg-acc">
      <summary class="tg-acc__title">
        <span>💰 Зарплатня</span>
        @if($pendingSalaryCount > 0)
          <span style="min-width:20px; height:20px; padding:0 6px; background:#e53e3e; color:#fff;
            border-radius:99px; font-size:11px; font-weight:800; line-height:20px; text-align:center;">{{ $pendingSalaryCount > 99 ? '99+' : $pendingSalaryCount }}</span>
        @endif
      </summary>
      <div class="tg-acc__body">
        <a class="tg-menu__item" href="/salary">💰 З/П всих співробітників</a>
        <a class="tg-menu__item" href="/salary/accruals">💸 З/П технічному відділу</a>
        <a class="tg-menu__item" href="/salary/settings">⚙️ Налаштування зарплатні</a>
      </div>
    </details>

    <details class="tg-acc">
      <summary class="tg-acc__title">📊 Аналітика</summary>
      <div class="tg-acc__body">
        <a class="tg-menu__item" href="/analytics">📊 Огляд</a>
        <a class="tg-menu__item" href="/ai">🤖 AI Аналітик</a>
      </div>
    </details>

    <details class="tg-acc">
      <summary class="tg-acc__title">⚙️ Налаштування</summary>
      <div class="tg-acc__body">
        <a class="tg-menu__item" href="/norm-rules">🔧 Нормалізація обладнання</a>
        @if(mb_strtolower(auth()->user()->name ?? '') === 'hlushchenko')
          <a class="tg-menu__item" href="/suspicious-actions" style="color:#f88;">🚨 Підозрілі дії</a>
        @endif
      </div>
    </details>

    <details class="tg-acc">
      <summary class="tg-acc__title">🔐 Профіль</summary>
      <div class="tg-acc__body">
        <a class="tg-menu__item" href="/users/manage">👤 Користувачі</a>
        <a class="tg-menu__item" href="/profile">👤 Профіль</a>
      </div>
    </details>
  </div>

  <div class="tg-menu__bottom">
    <form method="POST" action="{{ route('logout') }}">
      @csrf
      <button type="submit" class="tg-menu__item danger">🚪 Вийти з облікового запису</button>
    </form>
  </div>
</div>

<script>
document.addEventListener('click', (e) => {
  const target = e.target instanceof Element ? e.target : null;
  if (!target) return;

  const ratesBtn = target.closest('.js-show-rates');
  const staffBtn = target.closest('.js-staff-cash');
  if (!ratesBtn && !staffBtn) return;

  e.preventDefault();
  if (ratesBtn && typeof window.openRatesModalFlow === 'function') {
    window.openRatesModalFlow();
  }
  if (staffBtn && typeof window.openStaffCash === 'function') {
    window.openStaffCash();
  }
  window.location.hash = '';
});
</script>
