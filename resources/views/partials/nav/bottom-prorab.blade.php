@php
  $route = request()->route()?->getName();

  $activeWallet       = $route === 'home' || $route === 'wallet.index';
  $activeReclamations = str_starts_with($route ?? '', 'reclamations.');
  $activeStock        = str_starts_with($route ?? '', 'stock.');
  $activeProjects     = str_starts_with($route ?? '', 'projects.');

  $activeQuality = str_starts_with($route ?? '', 'quality-checks');

  $qcCount = \Illuminate\Support\Facades\DB::table('quality_checks')
    ->whereIn('status', ['pending', 'has_deficiencies', 'deficiencies_fixed'])
    ->count();

  $tabs = [
    [
      'href'   => route('home'),
      'icon'   => '💼',
      'label'  => 'Гаманець',
      'active' => $activeWallet,
      'badge'  => 0,
    ],
    [
      'href'   => url('/quality-checks'),
      'icon'   => '🔍',
      'label'  => 'Перевірка',
      'active' => $activeQuality,
      'badge'  => $qcCount,
    ],
    [
      'href'   => url('/projects'),
      'icon'   => '🏗️',
      'label'  => 'Проекти',
      'active' => $activeProjects
    ],
    [
      'href'   => route('reclamations.index'),
      'icon'   => '🛠️',
      'label'  => 'Сервіс',
      'active' => $activeReclamations
    ],
  ];
@endphp

<nav class="tg-bottom-nav">
  <div class="tg-bottom-left">
    @foreach($tabs as $t)
      <a class="tg-tab {{ $t['active'] ? 'is-active' : '' }}" href="{{ $t['href'] }}" style="position:relative;">
        {!! $t['icon'] !!}
        @if(!empty($t['badge']) && $t['badge'] > 0)
          <span style="position:absolute; top:2px; right:2px; min-width:16px; height:16px; padding:0 4px;
            background:#e53e3e; color:#fff; border-radius:99px; font-size:10px; font-weight:800;
            line-height:16px; text-align:center; pointer-events:none;">{{ $t['badge'] }}</span>
        @endif
        <span>{{ $t['label'] }}</span>
      </a>
    @endforeach
  </div>

    <div class="tg-fab-wrap">
    {{-- Відкриття БЕЗ JS: працює навіть коли JS-кліки глючать --}}
      <a class="tg-fab" href="#tgOwnerMenu" aria-label="Меню">
        <span class="tg-fab-ico">☰</span>
        <span class="tg-fab-label">Меню</span>
      </a>
    </div>
  

</nav>

{{-- FULLSCREEN MENU --}}
<div id="tgOwnerMenu" class="tg-menu">
  <div class="tg-menu__top">
    <div class="tg-menu__title">Меню</div>
    <a class="tg-menu__close" href="#" aria-label="Закрити">✕</a>
  </div>

  <div class="tg-menu__content">
    <a class="tg-menu__item" href="/messages" style="font-size:15px; font-weight:600; padding:14px 18px; margin-bottom:14px; border-bottom:1px solid rgba(255,255,255,0.06);">💬 Чат</a>


        <a class="tg-menu__item" style="margin-bottom:15px;" href="/">🏦 Мій гаманець</a>

        <button type="button" class="tg-menu__item" style="margin-bottom:15px;" onclick="window.openRatesModalFlow?.(); location.hash='';">
          💱 Обмінник
        </button>

        <a class="tg-menu__item" style="margin-bottom:15px;" href="/stock">📦 Склад SunFix</a>

        <details class="tg-acc">
          <summary class="tg-acc__title">📦 Склад SolarGlass</summary>
          <div class="tg-acc__body">
            <a class="tg-menu__item" href="/solar-glass">☀️ Solar Glass</a>
            <a class="tg-menu__item" href="/equipment-orders">🛒 Замовлення обладнання</a>
            <a class="tg-menu__item" href="/projects/delivered">🚚 Доставлено на об'єкт</a>
          </div>
        </details>

        <a class="tg-menu__item" style="margin-top:6px;margin-bottom:15px;" href="/reclamations">🛠️ Сервіс</a>
        
        <details class="tg-acc">
          <summary class="tg-acc__title">🏗 Технічний відділ</summary>
          <div class="tg-acc__body">
            <a class="tg-menu__item" href="/projects"> 🧾 Проекти</a>
            <a class="tg-menu__item" href="/projects/service-repair"> 🛠 Сервіс та ремонт</a>
            <a class="tg-menu__item" href="/quality-checks" style="display:flex; justify-content:space-between; align-items:center;">
              <span>🔍 Контроль якості</span>
              @if($qcCount > 0)
                <span style="min-width:20px; height:20px; padding:0 6px; background:#e53e3e; color:#fff;
                  border-radius:99px; font-size:11px; font-weight:800; line-height:20px; text-align:center;">{{ $qcCount }}</span>
              @endif
            </a>
          </div>
        </details>

        <a class="tg-menu__item" style="margin-top:15px; margin-bottom:15px;" href="/salary/my">💰 Зарплатня</a>

        <a class="tg-menu__item" href="/profile">👤 Профіль</a>


  </div>

  <div class="tg-menu__bottom">
    <form method="POST" action="{{ route('logout') }}">
      @csrf
      <button type="submit" class="tg-menu__item danger">🚪 Вийти з облікового запису</button>
    </form>
  </div>
</div>
