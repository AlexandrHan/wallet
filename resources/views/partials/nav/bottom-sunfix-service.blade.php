@php
  $activeReclamations = request()->routeIs('reclamations.index');
  $activeCreate       = request()->routeIs('reclamations.new');

  $tabs = [
    [
      'href'   => route('reclamations.index'),
      'icon'   => '🛠️',
      'label'  => 'Сервіс',
      'active' => $activeReclamations
    ],
    [
      'href'   => route('reclamations.new'),
      'icon'   => '📦',
      'label'  => 'Створити',
      'active' => $activeCreate
    ],
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
        <button type="button" class="tg-menu__item" style="margin-bottom:15px;" onclick="window.openRatesModalFlow?.(); location.hash='';">
          💱 Обмінник
        </button>

        <a class="tg-menu__item" style="margin-bottom:15px;" href="/stock">📦 Сервіс</a>

        <a class="tg-menu__item" href="/profile">👤 Профіль</a>


  </div>

  <div class="tg-menu__bottom">
    <form method="POST" action="{{ route('logout') }}">
      @csrf
      <button type="submit" class="tg-menu__item danger">🚪 Вийти</button>
    </form>
  </div>
</div>
