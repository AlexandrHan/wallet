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
            @if(!in_array(auth()->user()->role, ['sunfix', 'sunfix_manager'], true))
              <a href="{{ url('/') }}" class="burger-item">💼 Гаманець</a>
            @endif

            @if(in_array(auth()->user()?->role, ['owner', 'accountant', 'sunfix_manager'], true))
              <a href="/stock" class="burger-item">📦 Склад SunFix</a>
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
      <div class="muted" style="margin-top:6px;">Натисни “Створити нову рекламацію”.</div>
    </div>
  @else

@foreach($items as $item)

@php

$labels = [
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

$order = array_keys($labels);
$stepsByKey = $item->steps->keyBy('step_key');

$isDone = function($key) use ($stepsByKey) {
    $s = $stepsByKey->get($key);
    if (!$s) return false;

    return !empty($s->done_date)
        || (is_string($s->note) && trim($s->note) !== '')
        || (is_string($s->ttn) && trim($s->ttn) !== '');
};

//
// ===== АКТИВНИЙ ЕТАП =====
//
$activeKey = null;
foreach ($order as $k) {
    if ($isDone($k)) $activeKey = $k;
}
if (!$activeKey) $activeKey = 'reported';

$activeLabel = $labels[$activeKey] ?? $activeKey;

//
// ===== ДАТА =====
//
$activeStep = $stepsByKey->get($activeKey);

if ($activeStep && $activeStep->done_date) {
    $dateText = \Carbon\Carbon::parse($activeStep->done_date)->format('d.m.Y');
} elseif ($item->reported_at) {
    $dateText = $item->reported_at->format('d.m.Y');
} else {
    $dateText = '—';
}

//
// ===== СТАТУС РАМКИ =====
//
$shipped  = $stepsByKey->get('shipped_to_service');
$repaired = $stepsByKey->get('repaired_shipped_back');
$closed   = $stepsByKey->get('closed');

$isShipped  = $shipped && ($shipped->done_date || $shipped->ttn);
$isRepaired = $repaired && ($repaired->done_date || $repaired->ttn);
$isClosed   = ($closed && $closed->done_date) || $item->status === 'done';

$borderClass = 'card-pre';

if ($isClosed) {
    $borderClass = 'card-done';
}
elseif ($isRepaired) {
    $borderClass = 'card-repaired';
}
elseif ($isShipped) {
    $borderClass = 'card-service';
}

//
// ===== ЛОГОТИП (ПІСЛЯ borderClass) =====
//
$logo = '/img/solarglass.png';

if ($borderClass === 'card-service') {
    $logo = '/img/sunfix.png';
}

//
// ===== ЛІЧИЛЬНИКИ =====
//
$filesCount = $item->steps->sum(
    fn($s) => is_array($s->files) ? count($s->files) : 0
);

$notesCount = $item->steps
    ->filter(fn($s) => is_string($s->note) && trim($s->note) !== '')
    ->count();

@endphp


<a href="{{ route('reclamations.show', $item->id) }}"
   class="card reclam-card reclam-link {{ $borderClass }}">

    <div class="reclam-top">
        <div class="reclam-title">
            <div class="reclam-sub">
                <b>{{ $item->last_name ?: '—' }}</b>
            </div>
        </div>

        <div class="reclam-status status-open">
            {{ $activeLabel }}
        </div>
    </div>

    <div class="reclam-body">

        <div class="reclam-row">
            <div class="muted">Нас. пункт</div>
            <div class="right">
                <b>{{ $item->city ?: '—' }}</b>
            </div>
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

  @endif

</main>


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

      // фокус на хрестик (щоб Esc/Tab були логічні)
      closeBtn?.focus({ preventScroll: true });
    }

    function closeViewer(){
      // важливо: зняти фокус з кнопки, перш ніж ховати батька
      document.activeElement?.blur();

      viewer.classList.add('hidden');
      viewer.setAttribute('aria-hidden', 'true');
      img.src = '';

      // повернути фокус туди, де був
      if (lastFocusEl && typeof lastFocusEl.focus === 'function') {
        lastFocusEl.focus({ preventScroll: true });
      }
      lastFocusEl = null;
    }

    // 1) делегований клік по фотках (використай data-img-viewer)
    document.addEventListener('click', (e) => {
      const a = e.target.closest('a[data-img-viewer]');
      if (!a) return;

      e.preventDefault();
      const src = a.getAttribute('href');
      if (src) openViewer(src);
    });

    // 2) закриття по хрестику
    closeBtn?.addEventListener('click', (e) => {
      e.preventDefault();
      closeViewer();
    });

    // 3) закриття по фону
    backdrop?.addEventListener('click', closeViewer);

    // 4) Esc
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && !viewer.classList.contains('hidden')) {
        closeViewer();
      }
    });
  })();
</script>


</body>
</html>
