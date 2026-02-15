       <div class="burger-wrap">
        <button type="button" id="burgerBtn" class="burger-btn">☰</button>

        <div id="burgerMenu" class="burger-menu hidden">
            <a href="/profile" class="burger-item">🔐 Адмінка / пароль</a>
            @if(in_array(auth()->user()?->role, ['owner', 'accountant'], true))
              <a href="/stock" class="burger-item">📦 Склад SunFix</a>
            @endif
            @if(auth()->user()->role !== 'accountant')
              <a href="{{ route('reclamations.index') }}" class="burger-item">🧾 Рекламації</a>
            @endif

        <div id="staffCashBtn" class="menu-item burger-item hidden" onclick="openStaffCash()">
          👥 КЕШ співробітників
        </div>


        <div class="burger-actions">
          <button id="showRatesBtn" type="button" class="burger-item">💱 Обмінник</button>

          <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="burger-item danger">🚪 Вийти</button>
          </form>
        </div>

  