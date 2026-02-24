@auth
  @php
    $user = auth()->user();
  @endphp

  @if($user->role === 'owner')
    @include('partials.nav.bottom-owner')

  @elseif($user->role === 'accountant')
    @include('partials.nav.bottom-accountant')

  @elseif($user->role === 'ntv')
    @include('partials.nav.bottom-ntv')

  @elseif($user->role === 'sunfix_manager')
    @include('partials.nav.bottom-sunfix-manager')

  @elseif($user->role === 'sunfix')
    @include('partials.nav.bottom-sunfix-service')

  {{-- 👇 ось головне --}}
  @elseif($user->role === 'worker' && $user->position === 'foreman')
    @include('partials.nav.bottom-prorab')

  @elseif($user->role === 'worker' && $user->position === 'electrician')
    @include('partials.nav.bottom-electrik')

  @else
    @include('partials.nav.bottom-ntv')
  @endif
@endauth