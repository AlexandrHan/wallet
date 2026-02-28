@php
  $path = trim(request()->path(), '/'); // '' для /
  $is = function(string $p): bool {
    $p = trim($p, '/');
    return request()->is($p) || request()->is($p.'/*');
  };

  // ✅ активні стани
  $activeWallet = ($path === '');
  $activeSales  = $is('finance');               // /finance/*
  $activeDebts  = $is('stock/supplier-cash');   // /stock/supplier-cash/*
  $activeStock  = $is('stock') && !$activeDebts; // /stock/* крім боргів

  // ✅ вкладки (ОДИН список на весь застосунок)
  $tabs = [
    ['href'=>'/',                    'icon'=>'💼', 'label'=>'Гаманець', 'active'=>$activeWallet],
    ['href'=>'/finance',             'icon'=>'📈', 'label'=>'Продажі',  'active'=>$activeSales],
    ['href'=>'/stock/supplier-cash', 'icon'=>'💸', 'label'=>'Борги',    'active'=>$activeDebts],
    ['href'=>'/stock',               'icon'=>'📦', 'label'=>'Склад',    'active'=>$activeStock],
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
    <details class="tg-acc" >
      <summary class="tg-acc__title">💳 Гаманець</summary>
      <div class="tg-acc__body">
        <a class="tg-menu__item" href="/">🏦 Мій гаманець</a>
        @if(auth()->user()->role === 'owner')
          <button type="button" class="tg-menu__item js-staff-cash">👥 КЕШ співробітників</button>
        @endif

        <button type="button" class="tg-menu__item js-show-rates">💱 Обмінник</button>
      </div>
    </details>

    <details class="tg-acc">
      <summary class="tg-acc__title">📈 Продажі</summary>
      <div class="tg-acc__body">
        <a class="tg-menu__item" href="/finance">🧾 Сторінка продажів</a>
        <a class="tg-menu__item" href="/sales">📋 Проєкти</a>
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

    <details class="tg-acc">
      <summary class="tg-acc__title">🏗 Будівництво</summary>
      <div class="tg-acc__body">
        <a class="tg-menu__item" href="/projects"> 🧾 Проекти</a>
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
      <button type="submit" class="tg-menu__item danger">🚪 Вийти</button>
    </form>
  </div>
</div>

<script>
(function(){
  const btn  = document.getElementById('tgFabBtn');
  const menu = document.getElementById('tgFabMenu');
  if(!btn || !menu) return;

  const close = () => { menu.classList.add('hidden'); btn.setAttribute('aria-expanded','false'); };
  const toggle = (e) => {
    e && e.stopPropagation();
    menu.classList.toggle('hidden');
    btn.setAttribute('aria-expanded', menu.classList.contains('hidden') ? 'false' : 'true');
  };

  btn.addEventListener('click', toggle);
  document.addEventListener('click', (e) => {
    if(menu.classList.contains('hidden')) return;
    if(menu.contains(e.target) || btn.contains(e.target)) return;
    close();
  });
  document.addEventListener('keydown', (e) => { if(e.key === 'Escape') close(); });
})();
</script>

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
