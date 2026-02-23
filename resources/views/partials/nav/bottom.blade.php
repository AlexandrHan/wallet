@auth
  @php($role = auth()->user()->role)

  @if($role === 'owner')
    @include('partials.nav.bottom-owner')
  @elseif($role === 'accountant')
    @include('partials.nav.bottom-accountant')
  @elseif($role === 'ntv')
    @include('partials.nav.bottom-ntv')
  @else
    @include('partials.nav.bottom-ntv')
  @endif
@endauth