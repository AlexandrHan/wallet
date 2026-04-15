<!doctype html>
<html lang="uk">
<head>
  <meta charset="utf-8" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <link rel="manifest" href="/manifest.webmanifest?v={{ filemtime(public_path('manifest.webmanifest')) }}">
  <meta name="theme-color" content="#0b0d10">

  <!-- iOS home screen -->
  <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="SG Wallet">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="mobile-web-app-capable" content="yes">

  <link rel="stylesheet" href="/css/nav-telegram.css?v={{ filemtime(public_path('css/nav-telegram.css')) }}">

  <link rel="stylesheet" href="/css/wallet.css?v={{ filemtime(public_path('css/wallet.css')) }}">
  <link rel="stylesheet" href="/css/reclamations.css?v={{ filemtime(public_path('css/reclamations.css')) }}">
  <script src="/js/reclamations.js?v={{ filemtime(public_path('js/reclamations.js')) }}" defer></script>
  <script src="/js/header.js?v={{ filemtime(public_path('js/header.js')) }}" defer></script>

  <title>SolarGlass &bull; Рекламації</title>

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

    .reclam-accordion__toggle {
      display: flex;
      align-items: center;
      gap: 8px;
      width: 100%;
      background: rgba(255,255,255,0.06);
      border: 1px solid rgba(255,255,255,0.10);
      border-radius: 12px;
      padding: 12px 16px;
      color: #fff;
      font-size: 1rem;
      font-weight: 700;
      cursor: pointer;
      text-align: left;
      margin-bottom: 0;
    }
    .reclam-accordion__toggle:active {
      background: rgba(255,255,255,0.10);
    }
    .reclam-accordion__count {
      background: rgba(255,255,255,0.15);
      border-radius: 999px;
      padding: 1px 9px;
      font-size: .8rem;
      font-weight: 600;
    }
    .reclam-accordion__chevron {
      margin-left: auto;
      font-size: 1.1rem;
      transition: transform .2s;
      display: inline-block;
    }
    .reclam-accordion__chevron.is-open {
      transform: rotate(180deg);
    }
    .reclam-accordion__body {
      padding-top: 8px;
    }
    .reclam-accordion {
      margin-bottom: 4px;
    }
  </style>
</head>
<body class="{{ auth()->check() ? 'has-tg-nav' : '' }}">

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

        @include('partials.nav.top-avatar-placeholder')
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
    <a href="{{ route('reclamations.create') }}" class="btn create-reclam">Створити рекламацію</a>
    <button type="button" class="btn" id="searchToggleBtn">🔎 Пошук</button>
  </div>

  <div id="searchPanel" class="search-panel hidden">

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

    <form method="GET" action="{{ route('reclamations.index') }}" class="search-form">

      <input
        class="btn"
        type="text"
        name="q"
        placeholder="Пошук по прізвищу…"
        value="{{ request('q') }}"
        autocomplete="off"
      />

      {{-- status --}}
      <input type="hidden" name="status" id="statusInput" value="{{ request('status') }}">

      <div class="search-grid">

        {{-- LEFT --}}
        <div class="search-left">

          <div class="search-fields">
            <select name="step" class="btn">
              @foreach($stepsMap as $k => $label)
                <option value="{{ $k }}" {{ $selStep===$k ? 'selected' : '' }}>{{ $label }}</option>
              @endforeach
            </select>
          </div>

          <div class="search-actions">
            <button type="submit" class="btn primary">Знайти</button>
            <a href="{{ route('reclamations.index') }}" class="btn">Скинути</a>
          </div>

        </div>

        {{-- RIGHT --}}
        <div class="search-right">
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
        </div>

      </div>
    </form>

  </div>

  @if($items->isEmpty())
    <div class="reclamations-empty">
      <div style="font-weight:900;">Поки немає рекламацій</div>
      <div class="muted" style="margin-top:6px;">Натисни "Створити нову рекламацію".</div>
    </div>
  @else

  @php
    $labels = [
        'reported'              => 'Дані клієнта',
        'dismantled'            => 'Демонтували',
        'where_left'            => 'Де залишили',
        'shipped_to_service'    => 'Відправили НП на ремонт',
        'service_received'      => 'Сервіс отримав',
        'repaired_shipped_back' => 'Відремонтували та відправили',
        'installed'             => 'Встановили',
        'loaner_return'         => 'Повернення підмінного',
        'closed'                => 'Завершили',
    ];
    $order = array_keys($labels);

    $activeItems = $items->filter(fn($i) => $i->status !== 'done')->values();
    $doneItems   = $items->filter(fn($i) => $i->status === 'done')->values();
  @endphp

  {{-- АКТИВНІ --}}
  <div class="reclam-accordion">
    <button type="button" class="reclam-accordion__toggle" data-accordion="active">
      <span>Активні</span>
      <span class="reclam-accordion__count">{{ $activeItems->count() }}</span>
      <span class="reclam-accordion__chevron is-open" id="chev-active">&#9662;</span>
    </button>
    <div class="reclam-accordion__body" id="body-active">

    @foreach($activeItems as $item)
    @php
      $stepsByKey = $item->steps->keyBy('step_key');
      $isDone = function($key) use ($stepsByKey) {
          $s = $stepsByKey->get($key);
          if (!$s) return false;
          return !empty($s->done_date)
              || (is_string($s->note) && trim($s->note) !== '')
              || (is_string($s->ttn)  && trim($s->ttn)  !== '');
      };
      $activeKey = null;
      foreach ($order as $k) { if ($isDone($k)) $activeKey = $k; }
      if (!$activeKey) $activeKey = 'reported';
      $activeLabel = $labels[$activeKey] ?? $activeKey;
      $activeStep  = $stepsByKey->get($activeKey);
      if ($activeStep && $activeStep->done_date) {
          $dateText = \Carbon\Carbon::parse($activeStep->done_date)->format('d.m.Y');
      } elseif ($item->reported_at) {
          $dateText = $item->reported_at->format('d.m.Y');
      } else {
          $dateText = '—';
      }
      $shipped  = $stepsByKey->get('shipped_to_service');
      $repaired = $stepsByKey->get('repaired_shipped_back');
      $closed   = $stepsByKey->get('closed');
      $isShipped  = $shipped  && ($shipped->done_date  || $shipped->ttn);
      $isRepaired = $repaired && ($repaired->done_date || $repaired->ttn);
      $isClosed   = ($closed  && $closed->done_date)   || $item->status === 'done';
      $borderClass = 'card-pre';
      if ($isClosed)       $borderClass = 'card-done';
      elseif ($isRepaired) $borderClass = 'card-repaired';
      elseif ($isShipped)  $borderClass = 'card-service';
      $logo = ($borderClass === 'card-service') ? '/img/sunfix.png' : '/img/solarglass.png';
      $filesCount = $item->steps->sum(fn($s) => is_array($s->files) ? count($s->files) : 0);
      $notesCount = $item->steps->filter(fn($s) => is_string($s->note) && trim($s->note) !== '')->count();
    @endphp

    <a href="{{ route('reclamations.show', $item->id) }}" class="card reclam-card reclam-link {{ $borderClass }}">
        <div class="reclam-top">
            <div class="reclam-title">
                <div class="reclam-sub"><b>{{ $item->last_name ?: '—' }}</b></div>
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
            <div class="reclam-arrow">
                <img src="{{ $logo }}" class="reclam-footer-logo">
            </div>
        </div>
    </a>
    @endforeach

    </div>
  </div>

  {{-- ЗАВЕРШЕНІ --}}
  <div class="reclam-accordion" style="margin-top:12px;">
    <button type="button" class="reclam-accordion__toggle" data-accordion="done">
      <span>Завершені</span>
      <span class="reclam-accordion__count">{{ $doneItems->count() }}</span>
      <span class="reclam-accordion__chevron" id="chev-done">&#9662;</span>
    </button>
    <div class="reclam-accordion__body" id="body-done" style="display:none">

    @foreach($doneItems as $item)
    @php
      $stepsByKey = $item->steps->keyBy('step_key');
      $isDone = function($key) use ($stepsByKey) {
          $s = $stepsByKey->get($key);
          if (!$s) return false;
          return !empty($s->done_date)
              || (is_string($s->note) && trim($s->note) !== '')
              || (is_string($s->ttn)  && trim($s->ttn)  !== '');
      };
      $activeKey = null;
      foreach ($order as $k) { if ($isDone($k)) $activeKey = $k; }
      if (!$activeKey) $activeKey = 'reported';
      $activeLabel = $labels[$activeKey] ?? $activeKey;
      $activeStep  = $stepsByKey->get($activeKey);
      if ($activeStep && $activeStep->done_date) {
          $dateText = \Carbon\Carbon::parse($activeStep->done_date)->format('d.m.Y');
      } elseif ($item->reported_at) {
          $dateText = $item->reported_at->format('d.m.Y');
      } else {
          $dateText = '—';
      }
      $shipped  = $stepsByKey->get('shipped_to_service');
      $repaired = $stepsByKey->get('repaired_shipped_back');
      $closed   = $stepsByKey->get('closed');
      $isShipped  = $shipped  && ($shipped->done_date  || $shipped->ttn);
      $isRepaired = $repaired && ($repaired->done_date || $repaired->ttn);
      $isClosed   = ($closed  && $closed->done_date)   || $item->status === 'done';
      $borderClass = 'card-pre';
      if ($isClosed)       $borderClass = 'card-done';
      elseif ($isRepaired) $borderClass = 'card-repaired';
      elseif ($isShipped)  $borderClass = 'card-service';
      $logo = ($borderClass === 'card-service') ? '/img/sunfix.png' : '/img/solarglass.png';
      $filesCount = $item->steps->sum(fn($s) => is_array($s->files) ? count($s->files) : 0);
      $notesCount = $item->steps->filter(fn($s) => is_string($s->note) && trim($s->note) !== '')->count();
    @endphp

    <a href="{{ route('reclamations.show', $item->id) }}" class="card reclam-card reclam-link {{ $borderClass }}">
        <div class="reclam-top">
            <div class="reclam-title">
                <div class="reclam-sub"><b>{{ $item->last_name ?: '—' }}</b></div>
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
            <div class="reclam-arrow">
                <img src="{{ $logo }}" class="reclam-footer-logo">
            </div>
        </div>
    </a>
    @endforeach

    </div>
  </div>

  @endif

</main>

@include('partials.nav.bottom')

<script>
  (function () {
    var DONE_KEY = 'reclam_done_open';

    document.querySelectorAll('.reclam-accordion__toggle').forEach(function (btn) {
      var key    = btn.getAttribute('data-accordion');
      var body   = document.getElementById('body-' + key);
      var chev   = document.getElementById('chev-' + key);
      if (!body) return;

      if (key === 'done') {
        var open = localStorage.getItem(DONE_KEY) === '1';
        if (open) {
          body.style.display = '';
          if (chev) chev.classList.add('is-open');
        }
      }

      btn.addEventListener('click', function () {
        var visible = body.style.display !== 'none';
        body.style.display = visible ? 'none' : '';
        if (chev) chev.classList.toggle('is-open', !visible);
        if (key === 'done') localStorage.setItem(DONE_KEY, visible ? '0' : '1');
      });
    });
  })();
</script>

<script>
  (function () {
    const viewer = document.getElementById('imgViewer');
    const img = document.getElementById('imgViewerImg');
    const closeBtn = viewer?.querySelector('.img-viewer-close');
    const backdrop = viewer?.querySelector('.img-viewer-backdrop');

    if (!viewer || !img) return;

    let lastFocusEl = null;

    function openViewer(src){
      lastFocusEl = document.activeElement;

      img.src = src;
      viewer.classList.remove('hidden');
      viewer.setAttribute('aria-hidden', 'false');

      closeBtn?.focus({ preventScroll: true });
    }

    function closeViewer(){
      document.activeElement?.blur();

      viewer.classList.add('hidden');
      viewer.setAttribute('aria-hidden', 'true');
      img.src = '';

      if (lastFocusEl && typeof lastFocusEl.focus === 'function') {
        lastFocusEl.focus({ preventScroll: true });
      }
      lastFocusEl = null;
    }

    document.addEventListener('click', (e) => {
      const a = e.target.closest('a[data-img-viewer]');
      if (!a) return;

      e.preventDefault();
      const src = a.getAttribute('href');
      if (src) openViewer(src);
    });

    closeBtn?.addEventListener('click', (e) => {
      e.preventDefault();
      closeViewer();
    });

    backdrop?.addEventListener('click', closeViewer);

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && !viewer.classList.contains('hidden')) {
        closeViewer();
      }
    });
  })();
</script>

</body>
</html>
