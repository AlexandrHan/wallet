<!doctype html>
<html lang="uk">
<head>
  <meta charset="utf-8" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <link rel="manifest" href="/manifest.webmanifest?v={{ filemtime(public_path('manifest.webmanifest')) }}">
  <meta name="theme-color" content="#0b0d10">
  <link rel="stylesheet" href="/css/nav-telegram.css?v={{ filemtime(public_path('css/nav-telegram.css')) }}">
  <link rel="stylesheet" href="/css/wallet.css?v={{ filemtime(public_path('css/wallet.css')) }}">
  <link rel="stylesheet" href="/css/reclamations.css?v={{ filemtime(public_path('css/reclamations.css')) }}">
  <script src="/js/reclamations.js?v={{ filemtime(public_path('js/reclamations.js')) }}" defer></script>
  <script src="/js/header.js?v={{ filemtime(public_path('js/header.js')) }}" defer></script>

  <title>SolarGlass • Нова рекламація</title>

  <style>
    :root{ color-scheme:dark }
    html{ background:#0b0d10 }
    body{ margin:0 }
    #appSplash{ position:fixed; inset:0; background:#0b0d10; z-index:99999 }
  </style>
</head>

<body class="{{ auth()->check() ? 'has-tg-nav' : '' }}">

  <div class="app-bg"></div>

  <div id="appSplash">
    <div class="splash-logo">
      <img src="/img/holding.png" alt="SolarGlass">
    </div>
  </div>

  {{-- той самий header що й у тебе --}}
  <header>
    <div style="margin-top:-1rem;" class="wrap row">
      <div class="top-area">
        <a href="/" class="logo"><img src="/img/logo.png" alt="SolarGlass"></a>
        <div class="userName"><span style="font-weight:800;">{{ collect(explode(' ', trim(auth()->user()->name)))->first() }}</span></div>

        <div class="burger-wrap">
          <button class="burger-btn"></button>

        </div>
      </div>

      <div class="header-right">
        <span class="tag" id="actorTag" style="display:none"></span>
      </div>
    </div>
  </header>

  <main class="wrap page">
    <div class="row content" style="align-items:center;">
      <div style="font-weight:900;">Нова рекламація</div>
      <a href="{{ route('reclamations.index') }}" class="btn right">← Назад</a>
    </div>

    <form id="reclCreateForm" class="card" style="margin-top:14px;" method="POST" action="#">
      {{-- поки action="#" (пізніше підключимо збереження в БД) --}}

      <div class="wizard-step" data-step="1">
        <div class="muted" style="margin-bottom:8px;">Дата звернення</div>
        <input class="btn" type="date" name="reported_at" required>
        <div class="reclam-actions">
          <button type="button" class="btn primary right" data-next>Далі</button>
        </div>
      </div>

      <div class="wizard-step hidden" data-step="2">
        <div class="muted" style="margin-bottom:8px;">Прізвище</div>
        <input class="btn" name="last_name" placeholder="Іваненко" required>
        <div class="reclam-actions">
          <button type="button" class="btn" data-prev>Назад</button>
          <button type="button" class="btn primary right" data-next>Далі</button>
        </div>
      </div>

      <div class="wizard-step hidden" data-step="3">
        <div class="muted" style="margin-bottom:8px;">Населений пункт</div>
        <input class="btn" name="city" placeholder="Черкаси" required>
        <div class="reclam-actions">
          <button type="button" class="btn" data-prev>Назад</button>
          <button type="button" class="btn primary right" data-next>Далі</button>
        </div>
      </div>

      <div class="wizard-step hidden" data-step="4">
        <div class="muted" style="margin-bottom:8px;">Телефон</div>
        <input class="btn" name="phone" placeholder="+380..." required>
        <div class="reclam-actions">
          <button type="button" class="btn" data-prev>Назад</button>
          <button type="button" class="btn primary right" data-next>Далі</button>
        </div>
      </div>

      <div class="wizard-step hidden" data-step="5">
        <div class="muted" style="margin-bottom:8px;">Підмінний фонд є?</div>

        <div class="segmented" style="width:100%;">
          <button type="button" data-loaner="1" class="active">Є</button>
          <button type="button" data-loaner="0">Нема</button>
        </div>

        <input type="hidden" name="has_loaner" value="1">
        <input type="hidden" name="loaner_ordered" value="0">

        <div id="loanerOrderBox" class="card hidden" style="margin-top:12px;">
          <div class="muted" style="margin-bottom:8px;">Якщо немає підмінного</div>
          <label class="row" style="gap:10px;">
            <input type="checkbox" id="loanerOrderedChk">
            <span style="font-weight:800;">Замовити підмінний</span>
          </label>
        </div>

        <div class="reclam-actions">
          <button type="button" class="btn" data-prev>Назад</button>
          <button type="button" class="btn primary right" data-next>Далі</button>
        </div>
      </div>

      <div class="wizard-step hidden" data-step="6">
        <div class="muted" style="margin-bottom:8px;">Серійний номер</div>
        <input class="btn" name="serial_number" placeholder="SN: ..." required>

        <div class="reclam-actions">
          <button type="button" class="btn" data-prev>Назад</button>
          <button type="button" class="btn primary right" onclick="alert('Далі підключимо збереження в БД'); return false;">
            Створити
          </button>
        </div>
      </div>
    </form>
  </main>
@include('partials.nav.bottom')
</body>
</html>
