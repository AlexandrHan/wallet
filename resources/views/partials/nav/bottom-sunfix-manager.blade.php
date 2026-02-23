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
</nav>
