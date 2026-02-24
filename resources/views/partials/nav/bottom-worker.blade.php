@php
  $current = '/'.trim(request()->path(), '/'); // '/', '/reclamations/', ...'

  $activeWallet = ($current === '/');
  $activeReclamations  = str_starts_with($current, '/reclamations');

  $tabs = [
    ['href'=>'/',        'icon'=>'💼', 'label'=>'Гаманець', 'active'=>$activeWallet],
    ['href'=>'/reclamations', 'icon'=>'🛠️', 'label'=>'Сервіс',  'active'=>$activeReclamations],

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

