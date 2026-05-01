@php
  $path = request()->path();

  $isActive = function(string $prefix) use ($path): bool {
    $prefix = trim($prefix, '/');
    return $path === $prefix || str_starts_with($path, $prefix . '/');
  };

  $activeWallet  = ($path === '/');
  $activeFinance = $isActive('finance');
  $activeStock   = $isActive('solar-glass');
  $activeSalary  = $isActive('salary');
@endphp

<nav class="tg-bottom-nav">
  <div class="tg-bottom-left">
    <a class="tg-tab {{ $activeWallet  ? 'is-active' : '' }}" href="/">💼<span>Гаманець</span></a>
    <a class="tg-tab {{ $activeFinance ? 'is-active' : '' }}" href="/finance">📈<span>Продажі</span></a>
    <a class="tg-tab {{ $activeStock   ? 'is-active' : '' }}" href="/solar-glass">🔆<span>Склад</span></a>
  </div>

  <div class="tg-fab-wrap">
    <a class="tg-fab" href="#tgManagerMenu" aria-label="Меню">
      <span class="tg-fab-ico">☰</span>
      <span class="tg-fab-label">Меню</span>
    </a>
  </div>
</nav>

{{-- FULLSCREEN MENU --}}
<div id="tgManagerMenu" class="tg-menu">
  <div class="tg-menu__top">
    <div class="tg-menu__title">Меню</div>
    <a class="tg-menu__close" href="#" aria-label="Закрити">✕</a>
  </div>

  <div class="tg-menu__content">
    <a class="tg-menu__item" style="margin-bottom:15px;" href="/">💼 Гаманець</a>
    <a class="tg-menu__item" style="margin-bottom:15px;" href="/finance">📈 Продажі</a>
    <details class="tg-acc">
      <summary class="tg-acc__title">📦 Склад</summary>
      <div class="tg-acc__body">
        <a class="tg-menu__item" href="/solar-glass">☀️ Solar Glass</a>
        <a class="tg-menu__item" href="/equipment-orders">📦 Потреба в обладнанні</a>
        <a class="tg-menu__item" href="/projects/delivered">🚚 Доставлено на об'єкт</a>
      </div>
    </details>
    <a class="tg-menu__item" style="margin-top:6px;margin-bottom:15px;" href="/salary/my">💰 Зарплатня</a>
    <button type="button" class="tg-menu__item" style="margin-bottom:15px;" onclick="window.openRatesModalFlow?.(); location.hash='';">💱 Обмінник</button>
    <a class="tg-menu__item" href="/profile">👤 Профіль</a>
  </div>

  <div class="tg-menu__bottom">
    <form method="POST" action="{{ route('logout') }}">
      @csrf
      <button type="submit" class="tg-menu__item danger">🚪 Вийти з облікового запису</button>
    </form>
  </div>
</div>
