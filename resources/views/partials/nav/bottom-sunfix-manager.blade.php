@php
  $path = request()->path(); // без початкового "/"

  $isActive = function(string $prefix) use ($path): bool {
    $prefix = trim($prefix, '/');
    return $path === $prefix || str_starts_with($path, $prefix . '/');
  };

  // ✅ Гаманець = окрема сторінка всередині /stock
  $activeWallet = $isActive('stock/supplier-cash');

  // ✅ Склад активний на /stock*, але НЕ на сторінці гаманця
  $activeStock = $isActive('stock') && !$activeWallet;

  $activeDeliveries = $isActive('deliveries');
  $activeService    = $isActive('reclamations');
@endphp


<nav class="tg-bottom-nav">
  <div class="tg-bottom-left">
    <a class="tg-tab {{ $activeWallet ? 'is-active' : '' }}" href="/stock/supplier-cash">💸<span>Борги</span></a>
    <a class="tg-tab {{ $activeStock ? 'is-active' : '' }}" href="/stock">📦<span>Склад</span></a>
    <a class="tg-tab {{ $activeDeliveries ? 'is-active' : '' }}" href="/deliveries">🚚<span>Поставки</span></a>
    <a class="tg-tab {{ $activeService ? 'is-active' : '' }}" href="/reclamations">🛠️<span>Сервіс</span></a>
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
        <button type="button" class="tg-menu__item" style="margin-bottom:15px;" onclick="window.openRatesModalFlow?.(); location.hash='';">
          💱 Обмінник
        </button>

        <a class="tg-menu__item" style="margin-bottom:15px;" href="/stock/supplier-cash">💸 Борги</a>

        <a class="tg-menu__item" style="margin-bottom:15px;" href="/stock">📦 Склад SunFix</a>

        <a class="tg-menu__item" style="margin-bottom:15px;" href="/deliveries">🚚 Поставки</a>
        
        <a class="tg-menu__item" style="margin-bottom:15px;" href="/stock/sales-reports">📊 Історія звітів складу</a>

        <a class="tg-menu__item" style="margin-bottom:15px;" href="/reclamations">🛠️ Сервіс</a>

        <a class="tg-menu__item" href="/profile">👤 Профіль</a>


  </div>

  <div class="tg-menu__bottom">
    <form method="POST" action="{{ route('logout') }}">
      @csrf
      <button type="submit" class="tg-menu__item danger">🚪 Вийти з облікового запису</button>
    </form>
  </div>
</div>
