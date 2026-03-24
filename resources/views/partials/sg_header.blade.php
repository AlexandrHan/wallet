<header>
  <div style="margin-top:-1rem;" class="wrap row">
    <div class="top-area">
      <a href="{{ url('/') }}" class="logo">
        <img src="/img/logo.png" alt="SolarGlass">
      </a>

      <div class="userName">
        <span style="font-weight:800;">
          {{ collect(explode(' ', trim(auth()->user()->name)))->first() }}
        </span>
        @include('partials.notif-bell')
      </div>

      @include('partials.nav.top-avatar-placeholder')

    </div>

    <div class="header-right" style="display:flex; align-items:center; gap:6px;">
      <span class="tag" id="actorTag" style="display:none"></span>
    </div>
  </div>
</header>

