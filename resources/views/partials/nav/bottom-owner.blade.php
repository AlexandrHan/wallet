@php
  $path = trim(request()->path(), '/');
  $is = function(string $p): bool {
    $p = trim($p, '/');
    return request()->is($p) || request()->is($p.'/*');
  };

  $activeWallet = ($path === '');
  $activeSales  = $is('finance') || $is('sales');
  $activeDebts  = $is('stock/supplier-cash');
  $activeStock  = $is('stock') && !$activeDebts;

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
    <a class="tg-fab" href="#tgOwnerMenu" aria-label="Меню">
      <span class="tg-fab-ico">☰</span>
      <span class="tg-fab-label">Меню</span>
    </a>
  </div>
</nav>

@include('partials.nav._owner-menu')
