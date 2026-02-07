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


  <div class="row content topbar topbar-actions">
    <a href="{{ route('reclamations.new') }}" class="btn create-reclam">Створити рекламацію</a> 
    <button type="button" class="btn" id="searchToggleBtn">🔎 Пошук</button>
       
  </div>

  <div id="searchPanel" class="search-panel hidden">
  <form method="GET" action="{{ route('reclamations.index') }}" class="search-form">

    <input
      class="btn"
      type="text"
      name="q"
      placeholder="Пошук по прізвищу…"
      value="{{ request('q') }}"
      autocomplete="off"
    />

    {{-- 3 кнопки-статуси --}}
    <input type="hidden" name="status" id="statusInput" value="{{ request('status') }}">

    <div class="status-filters" id="statusFilters">
      <button type="button" class="btn pill {{ request('status')==='accepted' ? 'active' : '' }}" data-status="accepted">
        Прийняли заявку
      </button>
      <button type="button" class="btn pill {{ request('status')==='shipped' ? 'active' : '' }}" data-status="shipped">
        Відправили на ремонт
      </button>

    </div>

    {{-- dropdown етапів --}}
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
        <option value="{{ $k }}" {{ $selStep===$k ? 'selected' : '' }}>{{ $label }}</option>
      @endforeach
    </select>

    <button type="submit" class="btn primary">Знайти</button>
    <a href="{{ route('reclamations.index') }}" class="btn">Скинути фільтри</a>
  </form>

  </div>



    @if($items->isEmpty())
      <div class="reclamations-empty">
        <div style="font-weight:900;">Поки немає рекламацій</div>
        <div class="muted" style="margin-top:6px;">Натисни “Створити нову рекламацію”.</div>
      </div>
    @else
      @foreach($items as $item)
        @php
          $labels = [
            'reported' => 'Дані клієнта',
            'dismantled' => 'Демонтували',
            'where_left' => 'Де залишили',
            'shipped_to_service' => 'Відправили на ремонт',
            'service_received' => 'Сервіс отримав',
            'repaired_shipped_back' => 'Відремонтували та відправили',
            'installed' => 'Встановили',
            'loaner_return' => 'Повернення підмінного',
            'closed' => 'Завершили',
          ];

          $order = array_keys($labels);

          $isDone = function($s){
            return $s && (
              $s->done_date ||
              ($s->note && trim($s->note) !== '') ||
              ($s->ttn && trim($s->ttn) !== '') ||
              (is_array($s->files) && count($s->files))
            );
          };

          // поточний/останній активний етап = останній заповнений по порядку
          $currentKey = null;
          $currentDate = null;

          foreach ($order as $k) {
            $s = $item->steps->firstWhere('step_key', $k);
            if ($isDone($s)) {
              $currentKey = $k;
              $currentDate = $s->done_date ? \Illuminate\Support\Carbon::parse($s->done_date)->format('d.m.Y') : null;
            }
          }

          $currentLabel = $currentKey ? ($labels[$currentKey] ?? $currentKey) : 'Не розпочато';
          $currentDateText = $currentDate ?: ($item->reported_at ? $item->reported_at->format('d.m.Y') : '—');

          // колір рамки
          $shipped = $item->steps->firstWhere('step_key', 'shipped_to_service');
          $hasShipped = $isDone($shipped);

          if ($item->status === 'done') {
            $borderClass = 'card-done';      // зелена
          } elseif ($hasShipped) {
            $borderClass = 'card-shipped';   // жовта
          } else {
            $borderClass = 'card-pre';       // червона
          }

          $statusClass = $item->status === 'done' ? 'status-done' : 'status-open';
          $statusText  = $item->status === 'done' ? 'Завершено' : 'В роботі';

          $filesCount = $item->steps->sum(fn($s) => is_array($s->files) ? count($s->files) : 0);
          $notesCount = $item->steps->filter(fn($s) => $s->note && trim($s->note) !== '')->count();
          $dateText = $currentDateText;

        @endphp
        @php
          // порядок етапів та назви (під твою show.blade.php)
          $stepsMap = [
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

          // беремо останній "заповнений" step (date/note/ttn/files)
          $activeStep = $item->steps
            ->filter(function($s){
              $hasFiles = is_array($s->files) && count($s->files);
              return $s->done_date || (trim((string)$s->note) !== '') || (trim((string)$s->ttn) !== '') || $hasFiles;
            })
            ->sortBy(function($s){
              return $s->done_date ? strtotime($s->done_date) : 0;
            })
            ->last();

          $activeKey = $activeStep?->step_key ?? 'reported';
          $activeLabel = $stepsMap[$activeKey] ?? $activeKey;
        @endphp



        <a href="{{ route('reclamations.show', $item->id) }}" class="card reclam-card reclam-link {{ $borderClass }}">
          <div class="reclam-top">
            <div class="reclam-title">

              <div class="reclam-sub">
                <b>{{ $item->last_name ?: '—' }}</b>
              </div>
            </div>

            <div class="reclam-status status-open">{{ $activeLabel }}</div>

          </div>

          <div class="reclam-body">

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
                <div class="muted">Проблема</div>
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
