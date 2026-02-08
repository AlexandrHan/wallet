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

      <div class="burger-wrap">
        <button type="button" id="burgerBtn" class="burger-btn">☰</button>

        <div id="burgerMenu" class="burger-menu hidden">
          <a href="/profile" class="burger-item">🔐 Адмінка / пароль</a>
          @if(auth()->user()->role !== 'SunFix')
            <a href="{{ url('/') }}" class="burger-item">💼 Гаманець</a>
          @endif

          <a href="{{ route('reclamations.index') }}" class="burger-item">🧾 Рекламації</a>

          <div class="burger-actions">
            <form method="POST" action="{{ route('logout') }}">
              @csrf
              <button type="submit" class="burger-item danger">🚪 Вийти</button>
            </form>
          </div>
        </div>
      </div>
    </div>

    <div class="header-right">
      <span class="tag" id="actorTag" style="display:none"></span>
    </div>
  </div>
</header>
