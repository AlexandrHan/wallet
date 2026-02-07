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



  <div class="row content topbar" style="display:flex;justify-content:center;align-items:center;">

    <a href="{{ route('reclamations.new') }}" class="btn primary right">Створити нову рекламацію</a>
  </div>
  



    @if($items->isEmpty())
      <div class="reclamations-empty">
        <div style="font-weight:900;">Поки немає рекламацій</div>
        <div class="muted" style="margin-top:6px;">Натисни “Створити нову рекламацію”.</div>
      </div>
    @else
      @foreach($items as $item)
        @php
          // файли: рахуємо по всіх steps
          $filesCount = $item->steps->sum(fn($s) => is_array($s->files) ? count($s->files) : 0);

          // "коменти": поки беремо кількість steps з note (можеш потім зробити окрему таблицю comments)
          $notesCount = $item->steps->filter(fn($s) => $s->note && trim($s->note) !== '')->count();

          // статус бейдж
          $statusClass = $item->status === 'done' ? 'status-done' : 'status-open';
          $statusText  = $item->status === 'done' ? 'Завершено' : 'В роботі';

          $dateText = $item->reported_at ? $item->reported_at->format('d.m.Y') : '—';
        @endphp

        <a href="{{ route('reclamations.show', $item->id) }}" class="card reclam-card reclam-link">
          <div class="reclam-top">
            <div class="reclam-title">
              <div class="reclam-id">{{ $item->code }}</div>
              <div class="reclam-sub">
                Клієнт: <b>{{ $item->last_name ?: '—' }}</b>
              </div>
            </div>

            <div class="reclam-status {{ $statusClass }}">{{ $statusText }}</div>
          </div>

          <div class="reclam-body">
            <div class="reclam-row">
              <div class="muted">Серійник</div>
              <div class="right">SN: <span class="mono">{{ $item->serial_number ?: '—' }}</span></div>
            </div>

            <div class="reclam-row">
              <div class="muted">Нас. пункт</div>
              <div class="right"><b>{{ $item->city ?: '—' }}</b></div>
            </div>

            <div class="reclam-row">
              <div class="muted">Дата</div>
              <div class="right">{{ $dateText }}</div>
            </div>

            @if($item->problem)
              <div class="reclam-row">
                <div class="muted">Суть</div>
                <div class="right">{{ $item->problem }}</div>
              </div>
            @endif
          </div>

          <div class="reclam-footer">
            <div class="reclam-pill">📎 {{ $filesCount }} файли</div>
            <div class="reclam-pill">💬 {{ $notesCount }} нотатки</div>
            <div class="reclam-arrow">→</div>
          </div>
        </a>
      @endforeach
    @endif



</main>



</body>
</html>
