@php
  $current = '/'.trim(request()->path(), '/');

  $activeWallet = ($current === '/');
  $activeProjects = str_starts_with($current, '/projects/my-installation');
  $activeSalary = str_starts_with($current, '/salary');
@endphp

<nav class="tg-bottom-nav">
  <div class="tg-bottom-left">
    <a class="tg-tab {{ $activeWallet ? 'is-active' : '' }}" href="/">
      💼<span>Гаманець</span>
    </a>

    <a class="tg-tab {{ $activeProjects ? 'is-active' : '' }}" href="/projects/my-installation">
      🏗️<span>Проекти</span>
    </a>

    <a class="tg-tab {{ $activeSalary ? 'is-active' : '' }}" href="/salary/my">
      💰<span>Зарплатня</span>
    </a>

    <button type="button" class="tg-tab" onclick="window.openRatesModalFlow?.();">
      💱<span>Обмінник</span>
    </button>
  </div>

  <div class="tg-fab-wrap">
    <a class="tg-fab" href="#tgOwnerMenu" aria-label="Меню">
      <span class="tg-fab-ico">☰</span>
      <span class="tg-fab-label">Меню</span>
    </a>
  </div>
</nav>

<div id="tgOwnerMenu" class="tg-menu">
  <div class="tg-menu__top">
    <div class="tg-menu__title">Меню</div>
    <a class="tg-menu__close" href="#" aria-label="Закрити">✕</a>
  </div>

  <div class="tg-menu__content">
    <a class="tg-menu__item" style="margin-bottom:15px;" href="/">🏦 Гаманець</a>

    <a class="tg-menu__item" style="margin-bottom:15px;" href="/projects/my-installation">🏗️ Проекти</a>

    <a class="tg-menu__item" style="margin-bottom:15px;" href="/salary/my">💰 Зарплатня</a>

    <button type="button" class="tg-menu__item" style="margin-bottom:15px;" onclick="window.openRatesModalFlow?.(); location.hash='';">
      💱 Обмінник
    </button>

    <a class="tg-menu__item" href="/profile">👤 Профіль</a>
  </div>

  <div class="tg-menu__bottom">
    <form method="POST" action="{{ route('logout') }}">
      @csrf
      <button type="submit" class="tg-menu__item danger">🚪 Вийти з облікового запису</button>
    </form>
  </div>
</div>
