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
      </div>

      @include('partials.nav.top-avatar-placeholder')

    </div>

    <div class="header-right">
      <span class="tag" id="actorTag" style="display:none"></span>
    </div>
  </div>
</header>
