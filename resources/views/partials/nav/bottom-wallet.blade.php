@php
  $path = request()->path(); // '' для /
  $is = function(string $p): bool {
    $p = trim($p, '/');
    return request()->is($p) || request()->is($p.'/*');
  };

  $activeWallet  = ($path === '');        // ✅ гаманець це /
  $activeSales   = $is('finance');        // ✅ /finance + дочірні
  $activeStock   = $is('stock');          // ✅ /stock + дочірні
  $activeService = $is('reclamations');   // ✅ /reclamations + дочірні
    // ✅ Гаманець активний на /
  $activeWallet = request()->routeIs('home') || $path === '' || $is('wallet');

  $tabs = [
    ['href'=>'/',            'icon'=>'💼', 'label'=>'Гаманець', 'active'=>$activeWallet],
    ['href'=>'/stock',       'icon'=>'📦', 'label'=>'Склад',    'active'=>$activeStock],
    ['href'=>'/finance',     'icon'=>'📈', 'label'=>'Продажі',  'active'=>$activeSales],
    ['href'=>'/reclamations','icon'=>'🛠️', 'label'=>'Сервіс',   'active'=>$activeService],
  ];
@endphp

@auth
  @if(auth()->user()->role === 'owner')
    <nav class="tg-bottom-nav">
      <div class="tg-bottom-left">
        @foreach($tabs as $t)
          <a class="tg-tab {{ $t['active'] ? 'is-active' : '' }}" href="{{ $t['href'] }}">
            {!! $t['icon'] !!}<span>{{ $t['label'] }}</span>
          </a>
        @endforeach
      </div>

      <div class="tg-fab-wrap">
        <button type="button" class="tg-fab" id="tgFabBtn" aria-expanded="false">☰</button>

        <div class="tg-fab-menu hidden" id="tgFabMenu">
          <a class="tg-fab-item" href="/profile">🔐 Адмінка / пароль</a>
          <a class="tg-fab-item" href="/">💼 Гаманець</a>

          <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="tg-fab-item danger">🚪 Вийти</button>
          </form>
        </div>
      </div>
    </nav>
  @endif
@endauth

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
