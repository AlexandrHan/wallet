@php
  $path = trim(request()->path(), '/');
  $is = function(string $p): bool {
    $p = trim($p, '/');
    return request()->is($p) || request()->is($p.'/*');
  };

  $activeWallet   = ($path === '');
  $activeSales    = $is('finance') || $is('sales');
  $activeDebts    = $is('stock/supplier-cash');
  $activeStock    = $is('stock') && !$activeDebts;
  $activeProjects = $is('projects');
  $activeSalary   = $is('salary');
@endphp

<nav class="tg-bottom-nav tg-bottom-nav--project-owner">
  <div class="tg-bottom-left">
    <a class="tg-tab {{ $activeWallet ? 'is-active' : '' }}" href="/">
      💼<span>Гаманець</span>
    </a>
    <a class="tg-tab {{ $activeSales ? 'is-active' : '' }}" href="/finance">
      📈<span>Продажі</span>
    </a>
    <a class="tg-tab {{ $activeProjects ? 'is-active' : '' }}" href="/projects">
      🏗<span>Проекти</span>
    </a>
    <a class="tg-tab {{ $activeSalary ? 'is-active' : '' }}" href="/salary">
      💰<span>З/П</span>
    </a>
  </div>

  <div class="tg-fab-wrap">
    <a class="tg-fab" href="#tgOwnerMenu" aria-label="Меню">
      <span class="tg-fab-ico">☰</span>
      <span class="tg-fab-label">Меню</span>
    </a>
  </div>
</nav>

@include('partials.nav._owner-menu')
