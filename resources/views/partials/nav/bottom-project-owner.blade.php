@php
  $activeProjects = request()->is('projects');
  $activeSalary = request()->is('salary') || request()->is('salary/*');
@endphp

<nav class="tg-bottom-nav tg-bottom-nav--project-owner">
  <a class="tg-fab tg-project-owner-fab" href="/" aria-label="Гаманець">
    <span class="tg-project-owner-fab__icon">💼</span>
    <span class="tg-project-owner-fab__label">Гаманець</span>
  </a>

  <div class="tg-bottom-left tg-bottom-left--project-owner">
    <a class="tg-tab {{ $activeProjects ? 'is-active' : '' }}" href="/projects">
      🏗️<span>Проекти</span>
    </a>
    <a class="tg-tab {{ $activeSalary ? 'is-active' : '' }}" href="/salary">
      💰<span>З/П</span>
    </a>
    <button type="button" class="tg-tab tg-tab--static">
      📊<span>Графіки</span>
    </button>
  </div>

  <div class="tg-fab-wrap">
    <a class="tg-fab tg-project-owner-fab" href="#tgOwnerMenu" aria-label="Меню">
      <span class="tg-project-owner-fab__icon">☰</span>
      <span class="tg-project-owner-fab__label">Меню</span>
    </a>
  </div>
</nav>

<div id="tgOwnerMenu" class="tg-menu">
  <div class="tg-menu__top">
    <div class="tg-menu__title">Меню</div>
    <a class="tg-menu__close" href="#" aria-label="Закрити">✕</a>
  </div>

  <div class="tg-menu__content">
    <details class="tg-acc">
      <summary class="tg-acc__title">💳 Гаманець</summary>
      <div class="tg-acc__body">
        <a class="tg-menu__item" href="/">🏦 Мій гаманець</a>
        <button type="button" class="tg-menu__item js-staff-cash">👥 КЕШ співробітників</button>
        <button type="button" class="tg-menu__item js-show-rates">💱 Обмінник</button>
      </div>
    </details>

    <details class="tg-acc">
      <summary class="tg-acc__title">📈 Продажі</summary>
      <div class="tg-acc__body">
        <a class="tg-menu__item" href="/finance">🧾 Сторінка продажів</a>
      </div>
    </details>

    <details class="tg-acc">
      <summary class="tg-acc__title">💸 Борги</summary>
      <div class="tg-acc__body">
        <a class="tg-menu__item" href="/stock/supplier-cash">💸 Борги постачальнику</a>
      </div>
    </details>

    <details class="tg-acc">
      <summary class="tg-acc__title">📦 Склад</summary>
      <div class="tg-acc__body">
        <a class="tg-menu__item" href="/stock">📦 Склад SunFix</a>
        <a class="tg-menu__item" href="/deliveries">🚚 Поставки</a>
        <a class="tg-menu__item" href="{{ route('reclamations.index') }}">🧾 Рекламації</a>
      </div>
    </details>

    <details class="tg-acc" open>
      <summary class="tg-acc__title">🏗 Будівництво</summary>
      <div class="tg-acc__body">
        <a class="tg-menu__item" href="/projects">🏗 Проекти (активні)</a>
        <button type="button" class="tg-menu__item tg-menu__item--static" disabled>📊 Графіки</button>
      </div>
    </details>

    <details class="tg-acc" open>
      <summary class="tg-acc__title">💰 Зарплатня</summary>
      <div class="tg-acc__body">
        <a class="tg-menu__item" href="/salary">💰 Нарахування зарплатні</a>
        <a class="tg-menu__item" href="/salary/settings">⚙️ Налаштування зарплатні</a>
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
