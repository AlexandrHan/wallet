<!doctype html>
<html lang="uk">
<head>
  <meta charset="utf-8" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <link rel="manifest" href="/manifest.webmanifest?v={{ filemtime(public_path('manifest.webmanifest')) }}">
  <meta name="theme-color" content="#0b0d10">

  <!-- iOS home screen -->
  <link rel="apple-touch-icon" href="/apple-touch-icon.png">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="SG Wallet">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="mobile-web-app-capable" content="yes">

  <link rel="stylesheet" href="/css/wallet.css?v={{ filemtime(public_path('css/wallet.css')) }}">
  <link rel="stylesheet" href="/css/reclamations.css?v={{ filemtime(public_path('css/reclamations.css')) }}">
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
    :root{ color-scheme:dark }
    html{ background:#0b0d10 }
    body{ margin:0 }
    #appSplash{ position:fixed; inset:0; background:#0b0d10; z-index:99999 }
  </style>
</head>

<body>
  <div class="app-bg"></div>

  <div id="appSplash">
    <div class="splash-logo">
      <img src="/img/holding.png" alt="SolarGlass">
    </div>
  </div>

  <header>
    <div style="margin-top:-1rem;" class="wrap row">
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
          <button type="button" id="burgerBtn" class="burger-btn">☰</button>

          <div id="burgerMenu" class="burger-menu hidden">
            <a href="/profile" class="burger-item">🔐 Адмінка / пароль</a>
            <a href="{{ url('/') }}" class="burger-item">💼 Гаманець</a>
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

      @if(auth()->user()->role !== 'accountant')
      <div class="header-center">

      </div>
      @endif
    </div>
  </header>

<main class="wrap reclamations-main">



  <div class="row content topbar" style="align-items:center;">
    <div style="font-weight:900;">Рекламації</div>
    <a href="{{ route('reclamations.new') }}" class="btn primary right">+ Додати рекламацію</a>
  </div>
  



  <a href="{{ route('reclamations.show', 21) }}" class="card reclam-card reclam-link">
    <div class="reclam-top">
      <div class="reclam-title">
        <div class="reclam-id">R-00021</div>
        <div class="reclam-sub">Клієнт: <b>Іваненко</b></div>
      </div>

      <div class="reclam-status status-open">В роботі</div>
    </div>

    <div class="reclam-body">
      <div class="reclam-row">
        <div class="muted">Товар</div>
        <div class="right"><b>Інвертор Deye 8kW</b></div>
      </div>

      <div class="reclam-row">
        <div class="muted">Серійник</div>
        <div class="right">SN: <span class="mono">DEY-8K-39420</span></div>
      </div>

      <div class="reclam-row">
        <div class="muted">Дата</div>
        <div class="right">05.02.2026</div>
      </div>

      <div class="reclam-row">
        <div class="muted">Суть</div>
        <div class="right">Не стартує після монтажу</div>
      </div>
    </div>

    <div class="reclam-footer">
      <div class="reclam-pill">📎 2 файли</div>
      <div class="reclam-pill">💬 5 коментів</div>
      <div class="reclam-arrow">→</div>
    </div>
  </a>


</main>



</body>
</html>
