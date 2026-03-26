@auth
  @php
    $user = auth()->user();
  @endphp

  @if($user->role === 'owner' && (request()->is('projects') || request()->is('projects/*') || request()->is('salary') || request()->is('salary/*')))
    @include('partials.nav.bottom-project-owner')

  @elseif($user->role === 'owner')
    @include('partials.nav.bottom-owner')

  @elseif($user->role === 'accountant')
    @include('partials.nav.bottom-accountant')

  @elseif($user->role === 'ntv')
    @include('partials.nav.bottom-ntv')

  @elseif($user->role === 'manager')
    @include('partials.nav.bottom-manager')

  @elseif($user->role === 'sunfix_manager')
    @include('partials.nav.bottom-sunfix-manager')

  @elseif($user->role === 'sunfix')
    @include('partials.nav.bottom-sunfix-service')

  @elseif($user->role === 'manager')
    @include('partials.nav.bottom-manager')

  {{-- 👇 ось головне --}}
  @elseif($user->role === 'worker' && $user->position === 'foreman')
    @include('partials.nav.bottom-prorab')

  @elseif($user->role === 'worker' && $user->position === 'electrician')
    @include('partials.nav.bottom-electrik')

  @elseif($user->role === 'worker' && in_array(mb_strtolower((string)$user->actor), ['kryzhanovskyi', 'kukuiaka', 'shevchenko']))
    @include('partials.nav.bottom-installers')

  @elseif($user->role === 'worker')
    @include('partials.nav.bottom-worker')

  @else
    @include('partials.nav.bottom-ntv')
  @endif
@endauth

<script>
document.addEventListener('click', function (e) {
  const target = e.target instanceof Element ? e.target : null;
  if (!target) return;

  const menu = target.closest('.tg-menu');
  if (!menu) return;
  if (target !== menu) return;

  window.location.hash = '';
});
</script>
