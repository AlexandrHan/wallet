@php
  $path = request()->path(); // '' для /
  $is = function(string $p): bool {
    $p = trim($p, '/');
    return request()->is($p) || request()->is($p.'/*');
  };

  // ✅ активні стани
  $activeWallet = request()->routeIs('home') || $is('wallet') || $path === '';
  $activeDeliveries = $is('deliveries');       // /deliveries/*
 

  // ✅ борги тільки тут
  $activeDebts      = $is('stock/supplier-cash'); // /stock/supplier-cash

  // ✅ склад: все /stock/*, КРІМ /stock/supplier-cash
  $activeStock      = $is('stock') && !$activeDebts;

  // ✅ контекст (потім розширимо)
  $context = match(true) {
    $activeStock || $activeDeliveries || $activeDebts => 'stock',
  
    $activeWallet => 'wallet',
    default => 'wallet',
  };

  // ✅ таби
  $tabs = match($context) {
    'stock' => [
      ['href'=>'/',                   'icon'=>'💼', 'label'=>'Гаманець', 'active'=>$activeWallet],
      ['href'=>'/stock',              'icon'=>'📦', 'label'=>'Склад',    'active'=>$activeStock],
      ['href'=>'/deliveries',         'icon'=>'🚚', 'label'=>'Поставки', 'active'=>$activeDeliveries],
      ['href'=>'/stock/supplier-cash','icon'=>'💸', 'label'=>'Борги',    'active'=>$activeDebts],

    ],
    default => [
      ['href'=>'/',                   'icon'=>'💼', 'label'=>'Гаманець', 'active'=>$activeWallet],
      ['href'=>'/stock',              'icon'=>'📦', 'label'=>'Склад',    'active'=>$activeStock],
      ['href'=>'/deliveries',         'icon'=>'🚚', 'label'=>'Поставки', 'active'=>$activeDeliveries],
      ['href'=>'/stock/supplier-cash','icon'=>'💸', 'label'=>'Борги',    'active'=>$activeDebts],
    ],
  };
@endphp




<nav class="tg-bottom-nav">
  <div class="tg-bottom-left">
    @foreach($tabs as $t)
      <a class="tg-tab {{ $t['active'] ? 'is-active' : '' }}" href="{{ $t['href'] }}">
        {!! $t['icon'] !!}<span>{{ $t['label'] }}</span>
      </a>
    @endforeach
  </div>

</nav>

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