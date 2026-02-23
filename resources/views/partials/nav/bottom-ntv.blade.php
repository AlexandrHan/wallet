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

</nav>

