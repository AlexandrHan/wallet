@php
  $current = '/'.trim(request()->path(), '/'); // '/', '/finance', '/stock/...'

  $activeWallet = ($current === '/');
  $activeSales  = str_starts_with($current, '/finance');
  $activeStock  = str_starts_with($current, '/stock');

  $tabs = [
    ['href'=>'/',        'icon'=>'💼', 'label'=>'Гаманець', 'active'=>$activeWallet],
    ['href'=>'/finance', 'icon'=>'📈', 'label'=>'Продажі',  'active'=>$activeSales],
    ['href'=>'/stock',   'icon'=>'📦', 'label'=>'Склад',    'active'=>$activeStock],
  ];
@endphp

<nav class="tg-bottom-nav">
  <div class="tg-bottom-left">
    @foreach($tabs as $t)
      <a class="tg-tab {{ $t['active'] ? 'is-active' : '' }}" href="{{ $t['href'] }}">
        {!! $t['icon'] !!}<span>{{ $t['label'] }}</span>
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

        <a class="tg-menu__item" style="margin-bottom:15px;" href="/finance">🧾 Сторінка продажів</a>

        <a class="tg-menu__item" style="margin-bottom:15px;" href="/stock">📦 Склад SunFix</a>

        <a class="tg-menu__item" style="margin-bottom:15px;" href="/solar-glass">🌞 Залишки SolarGlass</a>

        <a class="tg-menu__item" style="margin-bottom:15px;" href="/salary/my">💰 Зарплатня</a>

        <a class="tg-menu__item" href="/profile">👤 Профіль</a>


  </div>

  <div class="tg-menu__bottom">
    <form method="POST" action="{{ route('logout') }}">
      @csrf
      <button type="submit" class="tg-menu__item danger">🚪 Вийти з облікового запису</button>
    </form>
  </div>
</div>
