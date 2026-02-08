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

  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="SG Wallet">
  <link rel="stylesheet" href="/css/wallet.css?v={{ filemtime(public_path('css/wallet.css')) }}">
  <script src="/js/wallet.js?v={{ filemtime(public_path('js/wallet.js')) }}" defer></script>
  



  <title>SolarGlass</title>

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
  :root{color-scheme:dark}
  html{background:#0b0d10}
  body{margin:0}
  #appSplash{position:fixed;inset:0;background:#0b0d10;z-index:99999}
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

        <div class="userName">        <span style="font-weight:800;">
          {{ collect(explode(' ', trim(auth()->user()->name)))->first() }}
        </span></div>
        <div class="burger-wrap">
        <button type="button" id="burgerBtn" class="burger-btn">☰</button>

        <div id="burgerMenu" class="burger-menu hidden">
            <a href="/profile" class="burger-item">🔐 Адмінка / пароль</a>
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

        </div>
        </div>

    </div>

<div class="header-right">
  <span class="tag" id="actorTag" style="display:none"></span>
</div>

@if(auth()->user()->role !== 'accountant')
<div class="header-center">
  <div class="segmented">
    <button type="button" id="view-h" data-owner="hlushchenko">Глущенко</button>
    <button type="button" id="view-k" data-owner="kolisnyk">Колісник</button>
  </div>
</div>
@endif

  </div>
</header>

<div class="wrap">

  <!-- VIEW 1: Рахунки -->
   
  <div id="walletsView">
    <div class="row content">
      <div style="font-weight:700;">Рахунки</div>

      <button type="button" class="btn " id="addWallet">+</button>
      <button type="button" class="btn" id="refresh">Оновити</button>

    @if(auth()->user()->role !== 'accountant')
      <span class="tag right rejym" id="viewHint"></span>
    @endif

    </div>

<!-- SG HOLDING CARD -->

<div id="holdingCard" class="card {{ auth()->user()->role === 'owner' ? '' : 'hidden' }}"></div>




<div id="holdingStatsBox" class="hidden" style="margin-top:14px;">

  <div class="card">
    <div class="segmented" id="holdingStatsFilter" style="width:100%;">
      <button type="button" data-hf="all" class="active">Всі</button>
      <button type="button" data-hf="cash">Кеш</button>
      <button type="button" data-hf="bank">Банк</button>
    </div>
  </div>

  <div id="holdingAccountsResult" style="margin-top:14px;"></div>

</div>


    <div id="wallets" class="grid"></div>
  </div> <!-- END walletsView -->

  <!-- VIEW 2: Операції -->
  <div id="opsView" style="display:none;">

    <div class="content" style="text-align:center; margin-bottom:10px;">
      <div class="muted btn" id="walletTitle"></div>
      <div style="padding-bottom:0.5rem; padding-top:1.5rem;" class="muted">Поточний баланс</div>
      <div style="padding-bottom:1rem;" class="big" id="walletBalance"></div>
      

    </div>

    <div class="row">

      <button type="button" class="btn" id="backToWallets">← Назад</button>

      <span class="tag" id="roTag" style="display:none;">тільки перегляд</span>

      <button type="button" class="btn primary right" id="addIncome">+ Дохід</button>
      <button type="button" class="btn danger" id="addExpense">+ Витрата</button>
    </div>

<!--**************************** кнопка виклику статистики ************************************************-->
    <button id="toggleStats" class="btn" style="margin:2rem auto;display:block; width:100%;">
      📊 Детальна статистика
    </button>
<!--**************************** кнопка виклику статистики ************************************************-->




<div id="statsBox" class="hidden">
    <!-- Статистика витрат по категоріях -->
    <div class="card" style="margin-top:16px;">
      <div class="selector-vytraty-dohody">
          <!-- Тип -->
          <div class="segmented">
            <button type="button" id="statsExpense" class="active">Витрати</button>
            <button type="button" id="statsIncome">Доходи</button>
          </div>
      </div>

      <div class="row">
                  <!-- Місяць -->
          <select id="statsMonth" class="btn">
            <option value="">Місяць</option>
          </select>

        <button type="button" class="btn right" id="showStats">
          📊 Показати
        </button>

      </div>
    </div>


    <div id="statsResult" style="margin-top:16px;"></div>

    <!-- SUMMARY -->
    <div id="entriesSummary" class="summary hidden">
      <div class="summary-item">
        <div class="summary-label">Баланс</div>
        <div class="summary-value" id="sumTotal">0 ₴</div>
      </div>

      <div class="summary-item">
        <div class="summary-label">Операцій</div>
        <div class="summary-value" id="sumCount">0</div>
      </div>

      <div class="summary-item">
        <div class="summary-label">Середнє</div>
        <div class="summary-value" id="sumAvg">0 ₴</div>
      </div>
    </div>


  <!-- CATEGORY STATS (з КРОКУ 2) -->
  <div id="categoryStats" class="cat">
    <div class="cat-title">Витрати по категоріях</div>
    <div id="catList"></div>
  </div>

  <!-- CHART -->
  <div class="chart-wrap">
    <canvas id="catChart" height="240"></canvas>  
  </div>

</div>


    <!-- Вставляємо CSV -->
    <input style="display:none; font-weight:700; margin-bottom:10px;" type="file" id="csvInput" accept=".csv">
    
    <div id="csvPreviewBox" class="hidden" style="margin-top:20px;">
    <div class="card">
      <div style="font-weight:700; margin-bottom:10px;">
        CSV preview (банк)
      </div>

      <table class="entries-table">
        <tbody id="csvPreviewBody"></tbody>
      </table>
    </div>
  </div>

    <!-- Вставляємо CSV -->



    <table>
      <thead>
        <tr>
          <th>Дата</th>
          <th>Тип</th>
          <th>Сума</th>
          <th>Коментар</th>
        </tr>
      </thead>
      <tbody id="entries"></tbody>
    </table>
  </div>

</div>

<!-- Sheet: Нова операція -->
<div id="sheetEntry" class="sheet hidden">
  <div class="sheet-backdrop"></div>
  <div class="sheet-panel">
    <div class="sheet-handle"></div>
    <h3 id="sheetEntryTitle">Нова операція</h3>

    <input id="sheetAmount" type="number" inputmode="decimal" placeholder="Сума" />

    <select id="sheetCategory"></select>

    <input id="sheetComment" placeholder="Коментар" />
    <div class="row row-actions">
      <button type="button" id="receiptBtn" class="btn mini" title="Додати чек">📷 Додати чек</button>

      <span id="receiptBadge" class="tag hidden" style="background:rgba(206, 206, 206, 0.18);">
        📎 Завантажено
      </span>
    </div>

    <div id="receiptPreview" class="hidden" style="margin-bottom:10px;">
      <img id="receiptImg" src="" alt="receipt" style="width:88px;height:88px;border-radius:16px;object-fit:cover;border:1px solid var(--stroke);margin-bottom:18px;">
    </div>

    <input id="receiptInput" type="file" accept="image/*" capture="environment" class="hidden">


    <button type="button" id="sheetConfirm" class="btn primary save">Зберегти</button>
  </div>
</div>

<!-- Sheet: Новий рахунок -->
<div id="sheetWallet" class="sheet hidden">
  <div class="sheet-backdrop"></div>
  <div class="sheet-panel">
    <div class="sheet-handle"></div>
    <h3>Новий рахунок</h3>

    <input id="walletName" placeholder="Назва (наприклад: КЕШ Глущенко)" />
    <select id="walletCurrency">
      <option value="UAH">UAH</option>
      <option value="USD">USD</option>
      <option value="EUR">EUR</option>
    </select>

    <button type="button" id="walletConfirm" class="btn save">Створити</button>
  </div>
</div>


<div id="staffCashModal" class="modal hidden">

  <div class="modal-backdrop" onclick="closeStaffCash()"></div>

  <div class="modal-panel">

    <div class="modal-handle"></div>

    <div class="modal-header">
      <div class="modal-title modal-cash">Кеш співробітників</div>
   
    </div>

    <div class="modal-body" id="staffCashList">
      <!-- Сюди підтягуються рахунки -->
    </div>

  </div>
</div>


<!-- Exchange Rates Modal -->
<div id="ratesModal" class="modal hidden">
  <div class="modal-backdrop"></div>
  <div class="modal-panel">
    <div class="modal-handle"></div>
    <div class="modal-header">
      <div class="modal-title">Актуальний курс валют</div>

    </div>
    <div id="ratesContent" class="modal-body"></div>
    <div id="exchangeBox" class="exchange hidden">
  <div class="exchange-header">
    <div class="segmented exchange-mode">
      <button id="modeBuy" class="active">Купуємо</button>
      <button id="modeSell">Продаємо</button>
    </div>
  </div>

  <div class="exchange-row">
    <input id="exFrom" type="number" />
    <div id="exFromLabel" class="exchange-currency">UAH</div>
  </div>

  <div class="exchange-row">
    <input id="exTo" type="number" />
    <div id="exToLabel" class="exchange-currency">USD</div>
  </div>
</div>

  </div>
</div>

<!-- Receipt Viewer Modal -->
<div id="receiptModal" class="modal hidden">
  <div class="modal-backdrop" onclick="closeReceiptModal()"></div>

  <div class="modal-panel">
    <div class="modal-header" style="display:flex;justify-content:space-between;align-items:center;">
      <div class="modal-title" style="margin:0"></div>
      <button type="button" id="receiptClose" class="modal-close">✕</button>
    </div>

    <div class="modal-body">
      <img
        id="receiptFullImg"
        src=""
        alt="receipt"
        style="width:100%;max-height:70vh;object-fit:contain;border-radius:16px;border:1px solid var(--stroke);background:rgba(0,0,0,.25);"
      >

      <div class="row receipt-actions">
        <a id="receiptOpenNew" class="btn" target="_blank" rel="noopener">Відкрити окремо</a>
        <a id="receiptDownload" class="btn" download>Зберегти</a>
      </div>

    </div>
  </div>
</div>

<audio id="sndLeave" src="/sounds/leave.mp3" preload="auto"></audio>
<audio id="sndMoneta" src="/sounds/moneta.mp3" preload="auto"></audio>

<script>
  window.AUTH_USER = @json(auth()->user());
</script>

</body>
</html>
