<!doctype html>
<html lang="uk">

<head>
  <meta charset="utf-8" />
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <link rel="manifest"
        href="/manifest.webmanifest?v={{ filemtime(public_path('manifest.webmanifest')) }}">

  <meta name="theme-color" content="#0b0d10">

  <!-- iOS home screen -->
  <link rel="apple-touch-icon" href="/apple-touch-icon.png">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="SG Wallet">

  <meta name="viewport"
        content="width=device-width, initial-scale=1, viewport-fit=cover">

  <meta name="mobile-web-app-capable" content="yes">

  <link rel="stylesheet"
        href="/css/wallet.css?v={{ filemtime(public_path('css/wallet.css')) }}">

  <link rel="stylesheet"
        href="/css/reclamations.css?v={{ filemtime(public_path('css/reclamations.css')) }}">

  <script src="/js/reclamations.js?v={{ filemtime(public_path('js/reclamations.js')) }}" defer></script>
  <script src="/js/header.js?v={{ filemtime(public_path('js/header.js')) }}" defer></script>

  <title>SolarGlass • Рекламації</title>

  <script>
    (function () {
      try {
        if (sessionStorage.getItem('sg_splash_shown') === '1') {
          document.documentElement.classList.add('no-splash');
        }
      } catch (e) {}
    })();
  </script>

  <style>
    :root { color-scheme: dark }
    html { background: #0b0d10 }
    body { margin: 0 }

    #appSplash {
      position: fixed;
      inset: 0;
      background: #0b0d10;
      z-index: 99999;
    }
  </style>
</head>

<body>

  <div class="app-bg"></div>

  <div id="appSplash">
    <div class="splash-logo">
      <img src="/img/holding.png" alt="SolarGlass">
    </div>
  </div>

  <!-- ================= HEADER ================= -->
  <header>
    <div class="wrap row" style="margin-top:-1rem;">

      <div class="top-area">

        <a href="/" class="logo">
          <img src="/img/logo.png" alt="SolarGlass">
        </a>

        <div class="userName">
          <span style="font-weight:800;">
            {{ collect(explode(' ', trim(auth()->user()->name)))->first() }}
          </span>
        </div>

        <div class="burger-wrap">

          <button type="button"
                  id="burgerBtn"
                  class="burger-btn">☰</button>

          <div id="burgerMenu" class="burger-menu hidden">

            <a href="/profile" class="burger-item">
              🔐 Адмінка / пароль
            </a>

            @if(auth()->user()->role !== 'sunfix')
              <a href="{{ url('/') }}" class="burger-item">
                💼 Гаманець
              </a>
            @endif

            <a href="{{ route('reclamations.index') }}"
               class="burger-item">
              🧾 Рекламації
            </a>

            <div class="burger-actions">
              <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                        class="burger-item danger">
                  🚪 Вийти
                </button>
              </form>
            </div>

          </div>
        </div>
      </div>

      <div class="header-right">
        <span class="tag" id="actorTag" style="display:none"></span>
      </div>

      @if(auth()->user()->role !== 'accountant')
        <div class="header-center"></div>
      @endif

    </div>
  </header>

  <!-- ================= MAIN ================= -->
  <main class="wrap reclamations-main">

    <!-- TOPBAR -->
    <div class="row content topbar topbar-actions">
      <a href="{{ route('reclamations.new') }}"
         class="btn create-reclam">
        Створити рекламацію
      </a>

      <button type="button"
              class="btn"
              id="searchToggleBtn">
        🔎 Пошук
      </button>
    </div>

    <!-- SEARCH PANEL -->
    <div id="searchPanel" class="search-panel hidden">

      <form method="GET"
            action="{{ route('reclamations.index') }}"
            class="search-form">

        <input class="btn"
               type="text"
               name="q"
               placeholder="Пошук по прізвищу…"
               value="{{ request('q') }}"
               autocomplete="off" />

        <input type="hidden"
               name="status"
               id="statusInput"
               value="{{ request('status') }}">

        <div class="status-filters" id="statusFilters">

          <button type="button"
                  class="btn pill {{ request('status')==='accepted' ? 'active' : '' }}"
                  data-status="accepted">
            Прийняли заявку
          </button>

          <button type="button"
                  class="btn pill {{ request('status')==='shipped' ? 'active' : '' }}"
                  data-status="shipped">
            Відправили на ремонт
          </button>

        </div>

        @php
          $stepsMap = [
            '' => 'Пошук по етапах',
            'reported' => 'Дані клієнта',
            'dismantled' => 'Демонтували',
            'where_left' => 'Де залишили',
            'shipped_to_service' => 'Відправили НП на ремонт',
            'service_received' => 'Сервіс отримав',
            'repaired_shipped_back' => 'Відремонтували та відправили',
            'installed' => 'Встановили',
            'loaner_return' => 'Повернення підмінного',
            'closed' => 'Завершили',
          ];

          $selStep = request('step','');
        @endphp

        <select name="step" class="btn">
          @foreach($stepsMap as $k => $label)
            <option value="{{ $k }}"
              {{ $selStep===$k ? 'selected' : '' }}>
              {{ $label }}
            </option>
          @endforeach
        </select>

        <button type="submit" class="btn primary">Знайти</button>

        <a href="{{ route('reclamations.index') }}"
           class="btn">
          Скинути фільтри
        </a>

      </form>
    </div>

    <!-- ДАЛІ ЙДУТЬ КАРТКИ (твій код без змін) -->

  </main>

</body>
</html>
