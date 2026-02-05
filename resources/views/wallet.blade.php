<!doctype html>
<html lang="uk">
<head>
  <meta charset="utf-8" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <link rel="manifest" href="/manifest.webmanifest">
  <meta name="theme-color" content="#0b0d10">




  <!-- iOS home screen -->
  <link rel="apple-touch-icon" href="/apple-touch-icon.png">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="SG Wallet">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">


  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="SG Wallet">

  <title>SolarGlass</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
  /* ================== THEME ================== */
:root{
  --bg:#0b0d10;
  --panel:rgba(28,32,45,.65);
  --stroke:rgba(255,255,255,.08);
  --text:#e9eef6;
  --muted:#9aa6bc;

  --blue:#4c7dff;
  --green:#66f2a8;
  --red:#ff6b6b;

  --blur:20px;
  --radius-xl:22px;
  --radius-lg:18px;
  --radius-pill:999px;
}
/* 1) підказуємо браузеру, що сайт темний (впливає на нативні контролли) */
:root{ color-scheme: dark; }

/* 2) сам select (у тебе вже є, але додамо пару важливих дрібниць) */
.sheet-panel select{
  color: var(--text);
  background: rgba(255,255,255,.08);
  border: 1px solid var(--stroke);
}

/* 3) головне: dropdown-опції (працює на Windows/Chrome/Edge набагато краще) */
.sheet-panel select option{
  background: #0b0d10;     /* темний фон */
  color: #e9eef6;          /* нормальний текст */
}

/* якщо є optgroup */
.sheet-panel select optgroup{
  background: #0b0d10;
  color: #9aa6bc;
}


#appSplash {
  position: fixed;
  width: 100%;
  inset: 0;
  background: radial-gradient(1400px 700px at 20% -20%, #1b2450 0%, transparent 60%),
    radial-gradient(1200px 600px at 90% 10%, #0f3a2a 0%, transparent 55%),
    linear-gradient(180deg, #0b0d10 0%, #07080c 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 99999;
  transition: opacity .35s ease, visibility .35s ease;
}



#appSplash.hide {
  opacity: 0;
  visibility: hidden;
}

.splash-logo {
  width: 320px;
  max-width: 40vw;
  

  aspect-ratio: 1 / 1; /* ⬅️ АБО інше, див. нижче */

  display: flex;
  align-items: center;
  justify-content: center;
  margin-top: -7rem;
  animation: splashPulse 1.8s ease-in-out infinite;
}


.splash-logo img {
  max-width: 100%;
  max-height: 100%;
  width: auto;
  height: auto;
  object-fit: contain;
  display: block;
}


/* ===== Entry Sheet Color Modes ===== */

.sheet.entry-income {
  --accent: #3bd671; /* iOS green */
}

.sheet.entry-expense {
  --accent: #ff5a5f; /* iOS red */
}

/* header / title */
.sheet.entry-income .sheet-title,
.sheet.entry-expense .sheet-title {
  color: var(--accent);
}

/* confirm button */
.sheet.entry-income .sheet-confirm {
  background: var(--accent);
}

.sheet.entry-expense .sheet-confirm {
  background: var(--accent);
}

/* input focus */
.sheet.entry-income input:focus,
.sheet.entry-income textarea:focus {
  border-color: var(--accent);
}

.sheet.entry-expense input:focus,
.sheet.entry-expense textarea:focus {
  border-color: var(--accent);
}

/* ===== Sheet title coloring ===== */
/* ✅ Sheet entry title coloring (точно попаде) */
#sheetEntry.entry-income #sheetEntryTitle { color: var(--accent) !important; }
#sheetEntry.entry-expense #sheetEntryTitle { color: #ff001aad !important; }
#sheetEntry.entry-expense #sheetConfirm { background: #ff001aad !important; }



/* ================== BASE ================== */
*{box-sizing:border-box}
body{height:110%}
html{
  height:100%;
  background:#0b0d10; /* ⬅️ КЛЮЧОВЕ */
}
#opsView{
  background-color: transparent;
}

body{
  margin:0;
  padding:0; /* ВАЖЛИВО: без padding */
  font-family:-apple-system,BlinkMacSystemFont,"SF Pro Text","SF Pro Display",system-ui;

  /* ОДНА простиня фону */
  background:
    radial-gradient(1400px 700px at 20% -20%, #1b2450 0%, transparent 60%),
    radial-gradient(1200px 600px at 90% 10%, #0f3a2a 0%, transparent 55%),
    linear-gradient(180deg, #0b0d10 0%, #07080c 100%);
  background-attachment: fixed;
  background-repeat:no-repeat;
  background-size: cover;

  color:var(--text);
  overscroll-behavior:none;
  -webkit-overflow-scrolling:touch;
}

body.modal-open{
  overflow:hidden;
  touch-action:none;
}

/* щоб receipt-модалка виглядала як твій sheet/modal */
.receipt-panel{
  background: rgba(28,32,45,.92);
}


/* ================== HEADER (FULL SCREEN + SAFE AREA) ================== */
header{
  position:fixed;
  top:0;
  left:0;
  right:0;
  z-index:1000;

  /* Прозорий верх → плавний перехід */
background: linear-gradient(
  to bottom,
  rgba(11, 16, 12, 0.88) 0%,    /* Верх — найтемніший */
  rgba(11, 16, 12, 0.49) 35%,   /* Поступове просвітлення */
  rgba(11, 16, 12, 0.36) 65%,   /* Ще прозоріший */
  rgba(11, 16, 12, 0) 100%      /* Низ — повністю прозорий */
);



  padding-top: env(safe-area-inset-top);
}
header .wrap{
  display:flex;
  align-items:center;
  position:relative;
}

/* ліва зона вже є — .top-area */

.header-right{
  margin-left:auto;
  display:flex;
  align-items:center;
}

/* центр поверх layout */
.header-center{
  position:absolute;
  left:50%;
  transform:translateX(-50%);
}
.top-area{  
    width: 100%;
    display: flex;
    justify-content: space-between;
}
.logo img{
  height:48px;
  width;
  display;

  /* 🔥 Контурна тінь навколо логотипа */
  filter:
  drop-shadow(0 0 6px rgba(0, 0, 0, 0.58))
  drop-shadow(0 2px 8px rgba(0, 0, 0, 0.7));
}
.logo{
  display:flex;
  align-items:center;
  padding:6px;
  border-radius:12px;
}
.logo{
  -webkit-tap-highlight-color: transparent;
}


/* ================== CONTENT OFFSET ================== */
main,
.content,
.app{
  padding-top: calc(6rem + env(safe-area-inset-top));
}

/* ================== LAYOUT ================== */
.wrap{max-width:980px; margin:0 auto; padding:18px;}
.row{display:flex; gap:14px; align-items:center; flex-wrap:wrap;}


/* ================== BURGER ================== */
.burger-wrap{position:relative}


.burger-btn{
  width: 50px;
  height: 50px;
  margin-top: 8px;
  border-radius:999px;
  border:1px solid var(--stroke);
  background: rgba(31, 30, 30, 0);

  /* Саме скло */
  backdrop-filter: blur(18px) saturate(160%);
  -webkit-backdrop-filter: blur(18px) saturate(160%);

  border: 1px solid rgba(255,255,255,.12);

  /* Легкий внутрішній світловий об’єм */
  box-shadow:
  inset 0 1px 1px rgba(255,255,255,.12),
  0 4px 14px rgba(0,0,0,.25);
  color:var(--text);
  font-size:30px;
  cursor:pointer;
  transition:.2s ease;
}
.burger-btn:hover{background:rgba(255,255,255,.14)}

/* =========================================================
🧊 iOS GLASS POPOVER MENU
Плаваюче скляне меню як в iOS / Telegram
========================================================= */
.burger-menu{
  position:absolute;
  right:0;
  top:90px;
  z-index:2000;

  min-width:220px;
  padding:8px;
  display:flex;
  flex-direction:column;
  gap:4px;

  border-radius:16px;

  /* 🔮 Скляна поверхня */
  background:
  linear-gradient(
  to bottom,
  rgba(255,255,255,.08),
  rgba(255,255,255,.02)
  ),
  rgba(0, 0, 0, 0.65);

  backdrop-filter:blur(12px) saturate(200%);


  border:1px solid rgba(255,255,255,.14);

  /* Обʼєм */
  box-shadow:
  inset 0 1px 0 rgba(255,255,255,.18),
  0 12px 32px rgba(0,0,0,.45);
}

.burger-menu.hidden{display:none}

.burger-item{
  padding:12px 14px;
  border-radius:12px;
  text-decoration:none;
  color:var(--text);
  background:transparent;
  border:none;
  text-align:left;
  cursor:pointer;
  font-size:14px;
  font-weight:600;
}

.burger-menu{
  display:flex;
  flex-direction:column;
}

/* щоб form поводилась як пункт меню */
.burger-menu form{
  display:contents;
}

/* універсальний стиль пункту */
.burger-item{
  position:relative;
  padding:14px 16px;
}

/* світлова лінія після кожного пункту крім останнього */
.burger-menu .burger-item:not(:last-child)::after{
  content:'';
  position:absolute;
  left:16px;
  right:16px;
  bottom:0;
  height:1px;
  background:linear-gradient(
    to right,
    transparent,
    rgba(255,255,255,.12),
    transparent
  );
}

.burger-item:hover{background:rgba(255,255,255,.1)}
.burger-item.danger{color:var(--red)}

.userName{color:#fff}

/* ================== BUTTONS ================== */
.btn{
  padding:10px 16px;
  border-radius:var(--radius-lg);
  background:rgba(255,255,255,.08);
  border:1px solid var(--stroke);
  color:var(--text);
  cursor:pointer;
  transition:.2s ease;
}
.btn:hover{background:rgba(255,255,255,.14)}
.btn.primary{background:rgba(84, 192, 134, 0.71); border-color:rgba(158, 158, 158, 0.6);}
.btn.danger{background:#ff001aad; border-color:rgba(158, 158, 158, 0.6);}
.btn.mini{padding:6px 10px; font-size:16px; border-radius:19px;}
.btn:disabled{opacity:.4}
.save {
    width: 100%;
    margin-top: 8px;
    background:rgba(84, 192, 134, 0.71);
    color: #000000;
    font-weight: 600;
    font-size: 20px;
    border-radius:5rem;
}
.tag{
  padding:10px 12px;
  border-radius:var(--radius-pill);
  background:rgba(255,255,255,.06);
  border:1px solid var(--stroke);
  font-size:12px;
  color:#fff;
  font-weight:600;
}

/* ================== TEXT ================== */
.muted{color:#fff; font-size:14px; font-weight:600}
.big{font-size:26px; font-weight:700}
.pos{color:var(--green)}
.neg{color:#ff001aad}

/* ================== CARDS ================== */
.grid{
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(360px,1fr));
  gap:22px;
  margin-top:22px;
  align-items:stretch;
}


.card{
  background:linear-gradient(180deg, rgba(255,255,255,.12), rgba(255,255,255,.02));
  backdrop-filter: blur(var(--blur));
  border:1px solid var(--stroke);
  border-radius:var(--radius-xl);
  padding:18px;
  transition:.25s ease;
}
.card:hover{transform:translateY(-2px)}
.card.ro{opacity:.65}

.card-top{display:flex; justify-content:space-between; align-items:center}

.bank-logo {
  position:absolute;
  top:34px;
  right:14px;
  height:3rem;
  width:auto;
  opacity:.85;
  filter: drop-shadow(0 0 6px rgba(25, 151, 0, 0.5));
}


/* ===== Currency icons ===== */

.currency-icon {
  position:absolute;
  top:34px;
  right:14px;

  width:2.8rem;
  height:2.8rem;

  display:flex;
  align-items:center;
  justify-content:center;

  font-size:32px;
  font-weight:700;

  border-radius:10px;
  box-shadow:0 0 6px rgba(25,151,0,.5);
  backdrop-filter:blur(8px);
  background:rgba(255,255,255,.08);
  border:1px solid var(--stroke);
}


/* UAH */
.currency-UAH {
  color: #fffb00ff;
  background: transparent;
}

/* EUR */
.currency-EUR {
  color: #ff0000ff;
  background: transparent;
}

/* USD */
.currency-USD {
  color: rgba(40, 180, 47, 1);
  background: transparent;
}


/* ================== TABLE ================== */
table{width:100%; border-collapse:separate; border-spacing:0 10px;table-layout:fixed;}
thead{display:none}
tbody tr{
/* 🔮 Скляна поверхня */
  background:
  linear-gradient(
  to bottom,
  rgba(255,255,255,.08),
  rgba(255,255,255,.02)
  ),
  rgba(18,18,20,.65);
  backdrop-filter(22px) saturate(160%);
  -webkit-backdrop-filter(22px) saturate(160%);
  border:1px solid rgba(255,255,255,.14);
}
tbody td{padding:14px; font-size:14px;}
table{
  width:100%;
  table-layout: fixed; /* ⬅️ КЛЮЧОВЕ */
}

tbody td{
  word-break: break-word;      /* ріже довгі слова */
  overflow-wrap: anywhere;     /* iOS / modern */
  white-space: normal;
}

table{
  width:100%;
  table-layout:fixed;
}

tbody td:first-child{border-radius:14px 0 0 14px;}

/* сума — компактна */
tbody td:last-child{
  width:30%;
  white-space:nowrap;
  text-align:right;
  border-radius:0 14px 14px 0;

}

/* коментар — максимально широкий */
.entry-comment{
  word-break: break-word;
  overflow-wrap: anywhere;
  white-space: normal;
}
.comment-cell{
  padding-right: 0;
  padding-left:0;
}


.receipt-btn{
  all: unset;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  width:5rem;
  height:34px;
  border-radius:999px;
  margin-top:6px;

  background: rgba(255,255,255,0.06);
  backdrop-filter: blur(8px);
  cursor:pointer;
}
.receipt-btn:active{ transform: scale(.94); }



.receipt-actions{
  display:flex;
  justify-content:center;
  gap:10px;
  margin-top:12px;

  flex-wrap:nowrap;     /* 🔥 не переносити */
  width:100%;
}

.receipt-actions .btn{
  flex:1 1 0;   
  margin-top:2rem;  
  margin-bottom:2rem;      /* 🔥 однакова ширина */
  min-width:0;          /* 🔥 щоб текст не розпирало */
  max-width:220px;      /* можна 180/200/240 як хочеш */
  text-align:center;
  text-decoration:none;

  display:inline-flex;
  align-items:center;
  justify-content:center;
}


/* ================== SEGMENTED ================== */
.segmented{
  display:flex;
  margin-top:2rem;
  border-radius:999px;
  padding:4px;
  border:1px solid var(--stroke);

  /* 🧊 iOS GLASS SURFACE */
  background: rgba(31, 30, 30, 0);

  /* Саме скло */
  backdrop-filter: blur(18px) saturate(160%);
  -webkit-backdrop-filter: blur(18px) saturate(160%);

  border: 1px solid rgba(255,255,255,.12);

  /* Легкий внутрішній світловий об’єм */
  box-shadow:
  inset 0 1px 1px rgba(255,255,255,.12),
  0 4px 14px rgba(0,0,0,.25);
  }
.segmented button{
  flex:1;
  padding:8px 14px;
  border-radius:999px;
  background:none;
  border:none;
  color:var(--muted);
  font-weight:600;
}
.segmented button.active{
  background:rgba(84, 192, 134, 0.93);
  color:#000;
}

/* ================== SHEET ================== */
.sheet.hidden{display:none}
.sheet-backdrop{position:fixed; inset:0; background:rgba(0,0,0,.4)}
.sheet-panel{
  position:fixed;
  left:0;
  right:0;
  bottom:0;
  z-index:3001;

  padding:16px 18px 80px;
  border-radius:24px 24px 0 0;

  /* 🔮 Скляна поверхня */


  backdrop-filter:blur(24px) saturate(160%);
  -webkit-backdrop-filter:blur(24px) saturate(160%);

  border:1px solid rgba(255,255,255,.12);

  /* Світло зверху + глибина знизу */
  box-shadow:
  inset 0 1px 0 rgba(255,255,255,.15),
  0 -8px 30px rgba(0,0,0,.45);

  animation:sheetUp .25s ease;
}


















.sheet-handle{
  width:40px; height:4px;
  background:rgba(255,255,255,.3);
  border-radius:999px;
  margin:0 auto 14px;
}
.sheet-panel h3{
  margin:0 0 16px; 
  text-align:center;
  font-size:28px;
}
.sheet-panel input, .sheet-panel select{
  width:100%;
  padding:14px;
  border-radius:14px;
  border:1px solid var(--stroke);
  background:rgba(255,255,255,.08);
  color:var(--text);
  margin-bottom:10px;
  margin-top:18px;
  font-size:16px;
  outline:none;
}


.date-cell{
  width:64px;
  text-align:center;
  white-space:nowrap;
}

.date-main{
  font-size:14px;
  font-weight:700;
}

.date-year{
  font-size:11px;
  opacity:.6;
}

.amount-cell{
  text-align:right;
  white-space:nowrap;
  padding-right:10px;
  padding-left: 0;
}

.amount-value{
  font-weight:700;
  font-size:15px;
}

.amount-currency{
  font-size:12px;
  opacity:.7;
  margin-left:4px;
}


.rejym{
    background:rgba(102, 242, 167, 0.53);
    color: #000;
}
img{display:block; max-height:48px}


/* ===== SUMMARY ===== */
.summary{
  display:flex;
  gap:14px;
  margin:18px 0 10px;
}

.summary.hidden{
  display:none;
}

.summary-item{
  flex:1;
  background:rgba(255,255,255,.06);
  border:1px solid var(--stroke);
  border-radius:14px;
  padding:12px 14px;
  text-align:center;
}

.summary-label{
  font-size:12px;
  color:var(--muted);
}

.summary-value{
  margin-top:4px;
  font-size:18px;
  font-weight:700;
  font-variant-numeric: tabular-nums;
}

/* ===== CATEGORY STATS ===== */
.cat{
  margin:10px 0 18px;
}

.cat.hidden{
  display:none;
}

.cat-title{
  font-size:13px;
  color:var(--muted);
  margin-bottom:8px;
}

.cat-row{
  display:flex;
  align-items:center;
  gap:10px;
  margin-bottom:6px;
}

.cat-name{
  min-width:90px;
  font-size:12px;
  white-space:nowrap;
}

.cat-bar{
  flex:1;
  height:8px;
  background:rgba(255,255,255,.08);
  border-radius:999px;
  overflow:hidden;
}

.cat-bar > div{
  height:100%;
  background:var(--red);
}

.cat-pct{
  width:38px;
  text-align:right;
  font-size:12px;
  font-variant-numeric: tabular-nums;
}

.hidden{ display:none }

.chart-wrap{
  margin:16px 0 10px;
  padding:10px;
  background:rgba(255,255,255,.04);
  border:1px solid var(--stroke);
  border-radius:16px;
}

#statsBox {
  padding: 16px;
}

#statsBox canvas {
  width: 100% !important;
  max-height: 260px;
}

.selector-vytraty-dohody{
  margin-bottom: 1rem;
}


.entry-row {
  cursor: pointer;
}

.entry-actions {
  margin-top: 4px;
  display: none;
}

.entry-row.active .entry-actions {
  display: flex;
  gap: 8px;
}




/* ===== iOS-style actions ===== */

.entry-actions button {
  all: unset;
  width: 5rem;
  height: 34px;
  border-radius: 999px;

  display: flex;
  align-items: center;
  justify-content: center;

  font-size: 16px;
  line-height: 1;

  background: rgba(255,255,255,0.06);
  backdrop-filter: blur(8px);

  cursor: pointer;
  transition: 
    background .15s ease,
    transform .1s ease,
    opacity .1s ease;
}

/* tap effect */
.entry-actions button:active {
  transform: scale(0.92);
  background: rgba(255,255,255,0.12);
}

/* ✏️ edit */
.entry-actions button:first-child {
  color: #4c7dff;
}

/* 🗑 delete */
.entry-actions button:last-child {
  color: #ff6b6b;
}

/* hover (desktop only) */
@media (hover:hover) {
  .entry-actions button:hover {
    background: rgba(255,255,255,0.12);
  }
}


/*******************************************  Видалення рахунку    ********************************************/
.account-card{
  position:relative;
  user-select:none;
}

.pirate-overlay{
  position:absolute;
  inset:0;
  display:flex;
  flex-direction:column;
  justify-content:center;
  align-items:center;
  background:rgba(0,0,0,.55);
  opacity:0;
  pointer-events:none;
  transition:.2s ease;
  border-radius:14px;
}

.account-card.stage-1 .pirate-overlay,
.account-card.stage-2 .pirate-overlay{
  opacity:1;
  pointer-events:auto;
}

.account-card.stage-2 .pirate-overlay{
  background:rgba(120,0,0,.85);
}

.pirate-skull{
  font-size:42px;
  animation: skullPulse 1.2s infinite;
  cursor:pointer;
}

.account-card.stage-2 .pirate-skull{
  animation: skullShake .6s infinite;
}

.pirate-text{
  margin-top:10px;
  font-size:14px;
  color:#ffd6d6;
  text-align:center;
  max-width:80%;
}

@keyframes skullPulse{
  0%{transform:scale(1)}
  50%{transform:scale(1.15)}
  100%{transform:scale(1)}
}

@keyframes skullShake{
  0%{transform:rotate(0)}
  25%{transform:rotate(-8deg)}
  50%{transform:rotate(8deg)}
  75%{transform:rotate(-8deg)}
  100%{transform:rotate(0)}
}

@keyframes splashPulse {
  0%   { transform: scale(1); }
  50%  { transform: scale(1.08); }
  100% { transform: scale(1); }
}


/* ===== Modal ===== */
.modal.hidden { display:none }

.modal-backdrop{
  position:fixed;
  inset:0;
  background:rgba(0,0,0,.45);
  backdrop-filter:blur(4px);
  z-index:3000;
}

/* =========================================================
🧊 iOS GLASS MODAL SHEET
Живе скло, не окремий темний екран
========================================================= */
.modal-panel{
  position:fixed;
  left:0;
  right:0;
  bottom:0;
  z-index:3001;

  padding:16px 18px 100px;
  border-radius:24px 24px 0 0;

  /* 🔮 Скляна поверхня */


  backdrop-filter:blur(24px) saturate(160%);
  -webkit-backdrop-filter:blur(24px) saturate(160%);

  border:1px solid rgba(255,255,255,.12);

  /* Світло зверху + глибина знизу */
  box-shadow:
  inset 0 1px 0 rgba(255,255,255,.15),
  0 -8px 30px rgba(0,0,0,.45);

  animation:sheetUp .25s ease;
}

@keyframes sheetUp{
  from{transform:translateY(100%)}
  to{transform:translateY(0)}
}

.modal-handle{
  width:42px;
  height:4px;
  border-radius:999px;
  background:rgba(255,255,255,.3);
  margin:0 auto 14px;
}

.modal-header{
  margin-bottom:12px;
}

.modal-title{
  margin-top: 2rem;
  text-align:center;
  font-weight:700;
  font-size:18px;
}

.modal-cash{
  margin-bottom:2rem;
}

.modal-close{
  all:unset;
  width:34px;
  height:34px;
  border-radius:999px;
  display:flex;
  align-items:center;
  justify-content:center;
  background:rgba(255,255,255,.08);
  cursor:pointer;
}

.modal-body{
  font-size:15px;
  line-height:1.5;
}

.rate-card{  
  background: linear-gradient(180deg, rgba(255, 255, 255, .12), rgba(255, 255, 255, .02));
  backdrop-filter: blur(var(--blur));
  border:1px solid var(--stroke);
  border-radius:14px;
  padding:12px;
  margin-bottom:10px;
  margin-top:1rem;
  transition:.2s;
}


.rate-title{
  font-weight:700;
  margin-bottom:6px;
}

/* 💵 USD */
.rate-title-usd{
  color:rgb(28, 231, 62);
  text-shadow:0 0 8px rgba(102,242,168,.4);
}

/* 💶 EUR */
.rate-title-eur{
  color:rgb(231, 28, 28);
  text-shadow:0 0 8px rgba(76,125,255,.4);
}


.burger-actions{
  display:flex;
  flex-direction:column;
  gap:4px;
}

.exchange{
  margin-top:14px;
  padding-top:12px;
  border-top:1px solid var(--stroke);
  animation:fadeIn .25s ease;
}

@keyframes fadeIn{
  from{opacity:0; transform:translateY(10px)}
  to{opacity:1; transform:translateY(0)}
}
.exchange-header{
  display:flex;
  justify-content:center;
  width:100%;
}

/* прибрати зайві відступи саме в обміннику, щоб не з’їжджало */
.exchange-header .segmented.exchange-mode{
  margin: 0 auto;
}


.exchange-row{
  display:flex;
  align-items:center;
  gap:10px;
  margin-bottom:10px;
}

.exchange-row input{
  flex:1;
  padding:12px;
  border-radius:12px;
  border:1px solid var(--stroke);
  background:rgba(255,255,255,.08);
  color:#fff;
  font-size:16px;
}

.exchange-currency{
  min-width:52px;
  text-align:center;
  font-weight:700;
}

.exchange-mode{
  margin-bottom:10px;
  width: 100%;
}
.modal-panel.expanded{
  max-height:85vh;
  overflow:auto;
}

.rate-card.active{
  border:1px solid rgba(102, 242, 167, 0.53);
  background:#4e876900;;
  box-shadow:0 0 8px rgba(102, 242, 167, 0.25);
  transform:translateY(-2px);
}

/* прибираємо системний колхозний фокус */
.exchange-row input{
  outline:none;
  transition:.2s;
  margin-top:1rem;
}

/* кастомний фокус */
.exchange-row input:focus{
  border:1px solid #6dff4c;
  background:rgba(76,125,255,.12);
  box-shadow:0 0 9px rgba(207, 255, 76, 0.35);
  transform:translateY(-2px);
}

/* коли поле заповнене — легкий активний стан */
.exchange-row input:not(:placeholder-shown){
  border:1px solid rgba(109,255,76,.4);
}

/* =========================================================
   🔧 BACKGROUND FIX (НЕ ЛАМАЄ ІСНУЮЧІ СТИЛІ)
   Стабілізує фон на iOS / Chrome / WebView
========================================================= */

/* 1. Окремий шар фону замість body */
.app-bg{
  position:fixed;
  inset:0;
  z-index:-1;

  background:
    radial-gradient(1400px 700px at 20% -20%, #1b2450 0%, transparent 60%),
    radial-gradient(1200px 600px at 90% 10%, #0f3a2a 0%, transparent 55%),
    linear-gradient(180deg, #0b0d10 0%, #07080c 100%);

  background-repeat:no-repeat;
  background-size:cover;

  transform:translateZ(0); /* iOS repaint fix */
}

/* 2. Відключаємо старий глючний механізм */
body{
  background:none !important;
  height:auto !important;
}

/* 3. html більше не перебиває фон */
html{
  background:#0b0d10;
}

.wallet-card--accountant {
  background: linear-gradient(135deg, #2a2f36, #1c2127);
  border: 1px dashed rgba(255,255,255,0.15);
}
.staff-badge{
  font-size:11px;
  padding:4px 8px;
  border-radius:999px;
  background:rgba(102,242,168,.15);
  border:1px solid rgba(102,242,168,.35);
  color:#66f2a8;
  font-weight:700;
  letter-spacing:.3px;
}

/* ================= ACCOUNT CARDS (MOBILE FIRST) ================= */

/* 📱 MOBILE */
.account-card-ui{
  padding:14px 16px;
  border-radius:18px;
  min-height:120px;
  display:flex;
  flex-direction:column;
  justify-content:space-between;
  position:relative;
}

.account-name{
  font-size:15px;
  font-weight:700;
}

.account-balance{
  font-size:20px;
  font-weight:800;
}

.account-type{
  font-size:11px;
  opacity:.6;
}

/* 💻 DESKTOP UPGRADE */
@media (min-width:1100px){

  .grid{
    grid-template-columns:repeat(auto-fill,minmax(380px,1fr));
    gap:26px;
  }

  .account-card-ui{
    padding:24px 26px;
    border-radius:22px;
    min-height:250px;
  }

  .account-name{
    font-size:20px;
  }

  .account-balance{
    font-size:30px;
  }
}

/* ===== HIDE SCROLLBAR (MOBILE APP STYLE) ===== */
@media (max-width: 768px){

  html, body {
    scrollbar-width: none;        /* Firefox */
    -ms-overflow-style: none;     /* IE/Edge */
  }

  html::-webkit-scrollbar,
  body::-webkit-scrollbar {
    display: none;                /* Chrome / Safari / iOS */
  }

}


/* ================= DESKTOP MODAL ================= */
@media (min-width: 900px){

  .modal-panel{
    top:50%;
    left:50%;
    right:auto;
    bottom:auto;

    transform:translate(-50%, -50%);
    width:520px;
    max-height:90vh;

    border-radius:22px;
    padding:22px 26px;

    backdrop-filter:blur(32px) saturate(160%);
    -webkit-backdrop-filter:blur(32px) saturate(160%);

    box-shadow:
      0 30px 80px rgba(0,0,0,.6),
      inset 0 1px 0 rgba(255,255,255,.15);

    animation:fadeScale .25s ease;
  }

  .modal-body{
    max-height:55vh;
    overflow:auto;
    padding-right:6px;
  }

  .modal-title{
    margin-top:0;
    font-size:20px;
  }

  .modal-handle{
    display:none;
  }
}

/* плавна поява */
@keyframes fadeScale{
  from{opacity:0; transform:translate(-50%,-45%) scale(.96)}
  to{opacity:1; transform:translate(-50%,-50%) scale(1)}
}







/* ===== Receipt actions: 2 рівні "кнопки" на всю ширину ===== */
.row.row-actions{
  display:flex;
  gap:10px;
  width:100%;
  flex-wrap:nowrap;          /* не переносити */
  margin:30px 0;
}

/* обидва елементи як рівні колонки */
.row.row-actions > #receiptBtn,
.row.row-actions > #receiptBadge{
  flex:1 1 0;                /* рівна ширина */
  min-width:0;               /* щоб текст не розпирало */
  height:44px;               /* однакова висота */
  display:flex;
  align-items:center;
  justify-content:center;
  text-align:center;
  white-space:nowrap;
}

/* робимо badge візуально як кнопку */
#receiptBadge{
  font-size:16px;            /* щоб не було "дрібний бейдж" */
  border-radius:19px;        /* як .btn.mini */
  padding:6px 10px;          /* як .btn.mini */
}

/* ВАЖЛИВО: не прибираємо з потоку, щоб не скакала ширина */
#receiptBadge.hidden{
  display:flex !important;   /* перебиває .hidden{display:none} */
  visibility:hidden;
  pointer-events:none;
}

/* ================= HOLDING CARD ================= */
.holding-card{
  margin-top:14px;
  padding:16px 18px;
}

.holding-head{
  display:flex;
  justify-content:space-between;
  align-items:flex-start;
  gap:12px;
}

.holding-title{
  font-weight:900;
  font-size:16px;
}

.holding-sub{
  margin-top:4px;
  font-size:12px;
  opacity:.7;
}

.holding-amount{
  margin-top:10px;
  font-size:30px;
  font-weight:900;
  letter-spacing:.2px;
  font-variant-numeric: tabular-nums;
}

.holding-break{
  display:flex;
  gap:10px;
  margin-top:12px;
  flex-wrap:wrap;
  align-items:center;
}

.holding-pill{
  padding:8px 10px;
  border-radius:999px;
  background:rgba(255,255,255,.06);
  border:1px solid var(--stroke);
  font-size:12px;
  font-weight:800;
  opacity:.95;
}

.segmented.holding-mode{
  margin-top:0;
  padding:3px;
  width:auto;
}

.segmented.holding-mode button{
  padding:6px 10px;
  font-size:12px;
}

.holding-warn{
  margin-top:10px;
  font-size:12px;
  opacity:.75;
}


/* ✅ Desktop: sheet виглядає як нормальна модалка по центру */
@media (min-width: 900px){
  .sheet-panel{
    left:50%;
    right:auto;
    bottom:auto;
    top:50%;
    transform:translate(-50%, -50%);

    width:min(560px, calc(100vw - 48px)); /* щоб не впиралось в краї */
    max-height:80vh;
    overflow:auto;

    border-radius:22px;
    padding:22px 26px;

    /* трішки “дорожче” скло на ПК */
    backdrop-filter: blur(32px) saturate(160%);
    -webkit-backdrop-filter: blur(32px) saturate(160%);

    box-shadow:
      0 30px 80px rgba(0,0,0,.6),
      inset 0 1px 0 rgba(255,255,255,.15);

    animation:fadeScaleSheet .22s ease;
  }

  /* хендл на десктопі не потрібен */
  .sheet-handle{ display:none; }

  /* заголовок на ПК трохи крупніший */
  .sheet-panel h3{
    font-size:20px;
    margin-bottom:14px;
  }
}

@keyframes fadeScaleSheet{
  from{opacity:0; transform:translate(-50%, -46%) scale(.97)}
  to{opacity:1; transform:translate(-50%, -50%) scale(1)}
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
            <a href="{{ route('reclamations.index') }}" class="burger-item">🧾 Рекламації</a>

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
    <!-- SG HOLDING TOTAL -->
    <div id="holdingCard" class="card holding-card hidden"></div>

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
      📊 Статистика
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

<script>

  const CSRF = document.querySelector('meta[name="csrf-token"]').content;
  // ===== BANK TRANSACTIONS (temporary, test data) =====


const AUTH_USER  = @json(auth()->user());
document.addEventListener('DOMContentLoaded', () => {
  if (AUTH_USER.role === 'owner') {
    document.getElementById('staffCashBtn')?.classList.remove('hidden');
  }
});

const AUTH_ACTOR = AUTH_USER.actor; // ← ПОВЕРНУЛИ

if (AUTH_USER.role !== 'accountant' && !AUTH_ACTOR) {
    alert('Не задано actor для користувача...');
}

  document.getElementById('actorTag').textContent = AUTH_ACTOR;

  const state = {
    actor: AUTH_ACTOR,
    viewOwner: AUTH_ACTOR,
    wallets: [],
    bankAccounts: [], // ⬅️ ДОДАЛИ
    selectedWalletId: null,
    selectedWallet: null,
    entries: [],
    activeEntryId: null,
    pendingReceiptFile: null,
    pendingReceiptUrl: null,
    fx: null,                 // { date, map: {USD:{purchase,sale}, EUR:{...}} }
    holdingCurrency: 'UAH',
    fxLoading: false,



    // для 2-крокового видалення
    delArmedId: null,
    delTimer: null,
  };

let isRenderingWallets = false;

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

async function loadBankTransactions() {
  const res = await fetch('/api/bank/transactions');
  if (!res.ok) {
    console.error('Bank transactions fetch failed');
    return [];
  }
  return await res.json();
}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

function checkOnline() {
  if (navigator.onLine) return true;
  alert('❌ Немає інтернету. Операції тимчасово недоступні.');
  return false;
}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  // DOM
  const walletsView = document.getElementById('walletsView');
  const opsView = document.getElementById('opsView');
  // ===== STATS UI =====
  const btnToggleStats = document.getElementById('toggleStats');
  const elStatsBox     = document.getElementById('statsBox');
  const ctxChart = document.getElementById('catChart')?.getContext('2d');



  let catChartInstance = null;

    btnToggleStats.onclick = () => {
      elStatsBox.classList.toggle('hidden');

      if (!elStatsBox.classList.contains('hidden')) {
        setTimeout(() => {
          renderCategoryChart();
        }, 60);
      }
  };



  const sheetCategory = document.getElementById('sheetCategory');


  const elWallets = document.getElementById('wallets');
  const elEntries = document.getElementById('entries');
  const elWalletTitle = document.getElementById('walletTitle');
  const elWalletBalance = document.getElementById('walletBalance');
  const elSummary      = document.getElementById('entriesSummary');
  const elSumTotal     = document.getElementById('sumTotal');
  const elSumCount     = document.getElementById('sumCount');
  const elSumAvg       = document.getElementById('sumAvg');
  const elCatBox  = document.getElementById('categoryStats');
  const elCatList = document.getElementById('catList');



  const roTag = document.getElementById('roTag');
  const viewHint = document.getElementById('viewHint');

  const btnIncome = document.getElementById('addIncome');
  const btnExpense = document.getElementById('addExpense');
  const btnBack = document.getElementById('backToWallets');

  const btnViewK = document.getElementById('view-k');
  const btnViewH = document.getElementById('view-h');
  if (AUTH_USER.role !== 'owner') {
  btnViewK?.classList.add('hidden');
  btnViewH?.classList.add('hidden');
}

  const IS_ACCOUNTANT = AUTH_USER.role === 'accountant';


  const btnAddWallet = document.getElementById('addWallet');

  // Sheet entry

  const sheetEntryTitle = document.getElementById('sheetEntryTitle');
  const sheetAmount = document.getElementById('sheetAmount');
  const sheetComment = document.getElementById('sheetComment');
  const sheetConfirm = document.getElementById('sheetConfirm');
  // закриття по кліку на бекдроп
  sheetEntry.querySelector('.sheet-backdrop').onclick = closeEntrySheet;

  // кнопка "Зберегти" в модалці операції
  sheetConfirm.onclick = async () => {
    const amount = Number(sheetAmount.value);

    if (!amount || amount <= 0) {
      alert('Введи суму більше 0');
      return;
    }

    const ok = await submitEntry(sheetType, amount, sheetComment.value);
    if (ok) closeEntrySheet();
  };

  let sheetType = null;

  // Sheet wallet
  const sheetWallet = document.getElementById('sheetWallet');
  const walletName = document.getElementById('walletName');
  const walletCurrency = document.getElementById('walletCurrency');
  const walletConfirm = document.getElementById('walletConfirm');
  const receiptBtn = document.getElementById('receiptBtn');
  const receiptInput = document.getElementById('receiptInput');
  const receiptBadge = document.getElementById('receiptBadge');
  const receiptPreview = document.getElementById('receiptPreview');
  const receiptImg = document.getElementById('receiptImg');

  function resetReceiptUI(){
    if (state.pendingReceiptUrl) URL.revokeObjectURL(state.pendingReceiptUrl);
    state.pendingReceiptUrl = null;
    state.pendingReceiptFile = null;

    receiptBadge?.classList.add('hidden');
    receiptPreview?.classList.add('hidden');
    if (receiptImg) receiptImg.src = '';
    if (receiptInput) receiptInput.value = '';
  }

  receiptBtn?.addEventListener('click', () => {
    receiptInput?.click();
  });

  receiptInput?.addEventListener('change', () => {
    const file = receiptInput.files?.[0];
    if (!file) return;

    // тільки 1 фото (бо у тебе 1 поле receipt_path)
    resetReceiptUI();

    state.pendingReceiptFile = file;
    state.pendingReceiptUrl = URL.createObjectURL(file);

    if (receiptImg) receiptImg.src = state.pendingReceiptUrl;
    receiptBadge?.classList.remove('hidden');
    receiptPreview?.classList.remove('hidden');
  });


  // категорії в коментарях
  const CATEGORIES = {
    expense: [
      'Логістика',
      'Зарплата',
      'Обладнання',      
      'Комплектуючі',
      'Нова пошта',
      'Оренда',
      'Хоз. витрати',
      'Їжа',
      'Digital',
      'Благодійність',
      'Туда Сюда',
      'Дивіденди',
      'Інше',
    ],
    income: [
      'Продаж СЕС',
      'Продаж комплектуючих',
      'Монтаж СЕС',
      'Послуги',
      'Туда Сюда',
      'Інше',
    ],
  };

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
function applyEntrySheetColor(type){
  sheetEntry.classList.remove('entry-income', 'entry-expense');

  if (type === 'income') {
    sheetEntry.classList.add('entry-income');
  } else if (type === 'expense') {
    sheetEntry.classList.add('entry-expense');
  }
}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

function formatDateParts(dateStr){
  if (!dateStr) return { dayMonth: '—', year: '' };

  const d = new Date(dateStr);
  if (isNaN(d)) return { dayMonth: '—', year: '' };

  return {
    dayMonth: `${String(d.getDate()).padStart(2,'0')}.${String(d.getMonth()+1).padStart(2,'0')}`,
    year: `${d.getFullYear()}р.`
  };
}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  function showWallets(){
    opsView.style.display = 'none';
    walletsView.style.display = '';
  }

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  function showOps(){
    walletsView.style.display = 'none';
    opsView.style.display = '';
  }

function canWriteWallet(walletOwner){
  return walletOwner === state.actor;
}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  function disarmDelete(){
    state.delArmedId = null;
    if (state.delTimer) clearTimeout(state.delTimer);
    state.delTimer = null;
  }

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  function setViewOwner(owner){
    if (IS_ACCOUNTANT) return;

    state.viewOwner = owner;

    btnViewK.classList.toggle('active', owner === 'kolisnyk');
    btnViewH.classList.toggle('active', owner === 'hlushchenko');

    const isMineView = (owner === state.actor);
    viewHint.textContent = isMineView ? 'Редагування' : 'Перегляд';

    // "+ рахунок" тільки коли дивимось свої
    btnAddWallet.style.display = isMineView ? '' : 'none';

    // reset selection
    state.selectedWalletId = null;
    state.selectedWallet = null;
    state.entries = [];
    elWalletTitle.textContent = '';
    elEntries.innerHTML = '';
    roTag.style.display = 'none';
    btnIncome.disabled = true;
    btnExpense.disabled = true;

    disarmDelete();
    showWallets();
      // ✅ ОЦЕ МИ ВТРАТИЛИ
    loadWallets();

  }

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

 async function loadWallets() {
  const res = await fetch('/api/wallets');
  state.wallets = await res.json();

  // ⬇️ банк вантажимо ТІЛЬКИ 1 раз
  if (!state.bankAccounts.length) {
    try {


    const [r1, r2, r3, r4, r5] = await Promise.all([
      fetch('/api/bank/accounts'),
      fetch('/api/bank/accounts-sggroup'),
      fetch('/api/bank/accounts-solarglass'),
      fetch('/api/bank/accounts-monobank'),
      fetch('/api/bank/accounts-privat'),
    ]);

    const a1 = r1.ok ? await r1.json() : [];
    const a2 = r2.ok ? await r2.json() : [];
    const a3 = r3.ok ? await r3.json() : [];
    const a4 = r4.ok ? await r4.json() : [];
    const a5 = r5.ok ? await r5.json() : [];

    state.bankAccounts = [...a1, ...a2, ...a3, ...a4, ...a5];





    } catch (e) {
      console.error('Bank accounts load failed', e);
      state.bankAccounts = [];
    }

  }
    // FX для Holding card
  await loadFx();


  renderWallets();
  renderHoldingCard();

}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//.                                      
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  async function loadEntries(walletId){
    state.selectedWalletId = walletId;

    const res = await fetch(`/api/wallets/${walletId}/entries`);
    const data = await res.json();

    state.selectedWallet = data.wallet;
    state.entries = data.entries || [];
    initStatsMonth();


    elWalletTitle.textContent = `${state.selectedWallet.name} • ${state.selectedWallet.currency}`;

    const writable = canWriteWallet(state.selectedWallet.owner);
    btnIncome.disabled = !writable;
    btnExpense.disabled = !writable;
    roTag.style.display = writable ? 'none' : '';

  renderEntries();
  renderEntriesSummary();
  renderCategoryStats();
  renderWalletBalance();
  showOps();

  }

    const ENTRY_TYPE_LABELS = {
    income: 'Дохід',
    expense: 'Витрата'
  };

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  function toggleEntryMenu(el){
  document.querySelectorAll('.entry-menu').forEach(m => {
    if (m !== el.nextElementSibling) m.classList.add('hidden');
  });
  el.nextElementSibling.classList.toggle('hidden');
}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

function pickCategory(cat){
  alert(`Категорія: ${cat}\n(поки лише UI)`);
}
const CURRENCY_SYMBOLS = {
  UAH: '₴',
  USD: '$',
  EUR: '€',
};

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  function getFilteredEntriesByStatsType() {
    return state.entries.filter(e => {
      const val = Number(e.signed_amount || 0);
      return statsType === 'expense' ? val < 0 : val > 0;
    });
  }

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  // ===== Stats UI state =====
  let statsType = 'expense';

  const statsExpense = document.getElementById('statsExpense');
  const statsIncome  = document.getElementById('statsIncome');


  function refreshStatsResult() {
    const month = document.getElementById('statsMonth').value;
    if (!month) return;

    const map = {};

    state.entries.forEach(e => {
      if (!e.posting_date.startsWith(month)) return;

      const val = Number(e.signed_amount || 0);
      if (statsType === 'expense' && val >= 0) return;
      if (statsType === 'income' && val <= 0) return;

      const m = (e.comment || '').match(/^\[(.+?)\]/);
      const cat = m ? m[1] : 'Без категорії';

      map[cat] = (map[cat] || 0) + Math.abs(val);
    });

    renderStats(map);
  }

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  function refreshStatsUI() {
    renderCategoryStats();
    renderCategoryChart();
  }


  statsExpense.onclick = () => {
    statsType = 'expense';
    statsExpense.classList.add('active');
    statsIncome.classList.remove('active');

    refreshStatsUI();      // chart + bars
    refreshStatsResult();  // ⬅️ ОЦЕ БУЛО ВІДСУТНЄ
  };

  statsIncome.onclick = () => {
    statsType = 'income';
    statsIncome.classList.add('active');
    statsExpense.classList.remove('active');

    refreshStatsUI();
    refreshStatsResult();
  };


/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  function renderEntries(){
    elEntries.innerHTML = '';

    state.entries.forEach(e => {

      const signed = Number(e.signed_amount || 0);
      const cls = signed >= 0 ? 'pos' : 'neg';
      const sign = signed >= 0 ? '+' : '';

      const editable =
        isToday(e.posting_date) &&
        canWriteWallet(state.selectedWallet.owner);

      const isActive = state.activeEntryId === e.id;

      const d = new Date(e.posting_date);
      const dateHtml = `
        ${String(d.getDate()).padStart(2,'0')}.${String(d.getMonth()+1).padStart(2,'0')}
        <div style="font-size:11px;opacity:.6">${d.getFullYear()}р</div>
      `;

      const tr = document.createElement('tr');
      tr.className = `entry-row ${isActive ? 'active' : ''}`;

      tr.onclick = (ev) => {
        ev.stopPropagation();
        state.activeEntryId = (state.activeEntryId === e.id) ? null : e.id;
        renderEntries();
      };

      tr.innerHTML = `
        <td class="muted date-cell">
          ${dateHtml}
        </td>

        <td class="entry-comment">
          ${renderComment(e.comment)}

          ${e.receipt_url ? `
            <button class="receipt-btn" onclick="openReceipt('${e.receipt_url}'); event.stopPropagation()">
              📎
            </button>
        ` : ''}

          ${editable ? `
            <div class="entry-actions">
              <button onclick="editEntry(${e.id}); event.stopPropagation()">✏️</button>
              <button onclick="deleteEntry(${e.id}); event.stopPropagation()">🗑</button>
            </div>
          ` : ''}
        </td>

        <td class="amount-cell ${cls}">
          ${sign}${fmt(Math.abs(signed))}
          <span class="amount-currency">
            ${CURRENCY_SYMBOLS[state.selectedWallet.currency] ?? ''}
          </span>
        </td>
      `;

      elEntries.appendChild(tr);
    });
  }


/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
function renderCurrencyIcon(currency) {
  const map = {
    UAH: '₴',
    EUR: '€',
    USD: '$'
  };

  return `
    <div class="currency-icon currency-${currency}">
      ${map[currency] ?? '¤'}
    </div>
  `;
}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

function renderWallets() {
  if (isRenderingWallets) return;
  isRenderingWallets = true;

  elWallets.innerHTML = '';

// ================= CASH =================
let visible;

if (AUTH_USER.role === 'accountant') {
  // бухгалтер НЕ тут, його кеші у модалці
  visible = state.wallets.filter(w => w.owner === state.viewOwner);

} else if (AUTH_USER.role === 'worker') {
  // прораб бачить ТІЛЬКИ свій кеш
  visible = state.wallets.filter(w => w.owner === AUTH_USER.actor);

} else {
  // owner / партнер — як було
  visible = state.wallets.filter(w => w.owner === state.viewOwner);
}


visible.forEach(w => {
  const writable = canWriteWallet(w.owner);
  const bal = Number(w.balance || 0);
  const balCls = bal >= 0 ? 'pos' : 'neg';

  const card = document.createElement('div');
  card.className = `card account-card account-card-ui account-cash ${writable ? '' : 'ro'}`;
  card.dataset.accountId = w.id;
  card.onclick = () => loadEntries(w.id);

  card.innerHTML = `
    <div class="account-top">
      <div class="account-currency">${w.currency}</div>
      ${renderCurrencyIcon(w.currency)}
    </div>

    <div class="account-name">${w.name}</div>

    <div class="account-balance ${balCls}">
      ${fmt(bal)} ${w.currency}
    </div>

    <div class="account-type">Cash account</div>

    <div class="pirate-overlay">
      <div class="pirate-skull">☠️</div>
      <div class="pirate-text"></div>
    </div>
  `;

  elWallets.appendChild(card);
});



  // ================= BANK =================
  const visibleBanks = (AUTH_USER.role === 'worker') ? [] : state.bankAccounts;



  visibleBanks.forEach(bank => {
    const card = document.createElement('div');
    card.className = 'card account-card-ui account-bank ro';


    card.style.position = 'relative';

    let logo = '';
    if (bank.bankCode === 'monobank') {
      logo = `<img src="/img/monoLogo.png" class="bank-logo">`;
    }
    if (bank.bankCode?.includes('ukrgasbank')) {
      logo = `<img src="/img/ukrgasLogo.png" class="bank-logo">`;
    }
    if (bank.bankCode === 'privatbank') {
      logo = `<img src="/img/privatLogo.png" class="bank-logo">`;
    }


card.innerHTML = `
  <div class="account-top">
    <div class="account-currency">${bank.currency}</div>
    ${logo}
  </div>

  <div class="account-name">${bank.name}</div>

  <div class="account-balance ${bank.balance >= 0 ? 'pos' : 'neg'}">
    ${fmt(bank.balance)} ${bank.currency}
  </div>

  <div class="account-type">Bank account</div>
`;


    card.onclick = () => openBankAccount(bank);
    elWallets.appendChild(card);
  });

  if (!visible.length && !visibleBanks.length) {
    elWallets.innerHTML = '<div class="muted">Немає рахунків</div>';
  }

  isRenderingWallets = false;
  initPirateDelete();
  hideSplash();
}



function hideSplash(){
  const el = document.getElementById('appSplash');
  if (!el) return;
  el.classList.add('hide');
}


/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  function renderEntriesSummary(){
    if (!state.entries.length){
      elSummary.classList.add('hidden');
      return;
    }

    const values = state.entries.map(e => Number(e.signed_amount || 0));
    const total  = values.reduce((a,b) => a + b, 0);
    const count  = values.length;
    const avg    = total / count;

    elSummary.classList.remove('hidden');

    elSumTotal.textContent =
      `${fmt(total)} ${CURRENCY_SYMBOLS[state.selectedWallet.currency]}`;

    elSumCount.textContent = count;

    elSumAvg.textContent =
      `${fmt(avg)} ${CURRENCY_SYMBOLS[state.selectedWallet.currency]}`;

    elSumTotal.className = 'summary-value ' + (total >= 0 ? 'pos' : 'neg');
  }

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

async function loadBankAccounts() {
  const res = await fetch('/api/bank/accounts');
  if (!res.ok) return [];
  return await res.json();
}


/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

function renderCategoryStats() {
  const entries = getFilteredEntriesByStatsType();

  if (!entries.length) {
    elCatBox.classList.add('hidden');
    return;
  }

  const map = {};
  let total = 0;

  entries.forEach(e => {
    const amount = Math.abs(Number(e.signed_amount));
    total += amount;

    const m = (e.comment || '').match(/^\[(.+?)\]/);
    const cat = m ? m[1] : 'Інше';

    map[cat] = (map[cat] || 0) + amount;
  });

  elCatList.innerHTML = '';
  elCatBox.classList.remove('hidden');

  Object.entries(map)
    .sort((a, b) => b[1] - a[1])
    .forEach(([cat, sum]) => {
      const pct = Math.round((sum / total) * 100);

      elCatList.insertAdjacentHTML('beforeend', `
        <div class="cat-row">
          <div class="cat-name">${cat}</div>
          <div class="cat-bar"><div style="width:${pct}%"></div></div>
          <div class="cat-pct">${pct}%</div>
        </div>
      `);
    });
}



/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
  
function renderCategoryChart() {
  if (!ctxChart || typeof Chart === 'undefined') return;

  const entries = getFilteredEntriesByStatsType();
  if (!entries.length) return;

  const data = {};

  entries.forEach(e => {
    const m = (e.comment || '').match(/^\[(.+?)\]/);
    if (!m) return;

    const cat = m[1];
    data[cat] = (data[cat] || 0) + Math.abs(Number(e.signed_amount));
  });

  const labels = Object.keys(data);
  const values = Object.values(data);

  if (catChartInstance) catChartInstance.destroy();

  catChartInstance = new Chart(ctxChart, {
    type: 'pie',
    data: {
      labels,
      datasets: [{
        data: values,
        backgroundColor: [
          '#66f2a8',
          '#4c7dff',
          '#ffb86c',
          '#ff6b6b',
          '#9aa6bc'
        ]
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { labels: { color: '#e9eef6' } }
      }
    }
  });
}


/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  document.getElementById('showStats').onclick = () => {
    const month = document.getElementById('statsMonth').value;
    if (!month) {
      alert('Вибери місяць');
      return;
    }

    const map = {};

    state.entries.forEach(e => {
      if (!e.posting_date.startsWith(month)) return;
      if (e.entry_type !== statsType) return;

      const m = (e.comment || '').match(/^\[(.+?)\]/);
      const cat = m ? m[1] : 'Без категорії';

      map[cat] = (map[cat] || 0) + Math.abs(Number(e.signed_amount));
    });

    renderStats(map);
  };

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  function renderStats(map){
  const el = document.getElementById('statsResult');
  el.innerHTML = '';

  const entries = Object.entries(map);
  if (!entries.length){
    el.innerHTML = '<div class="muted">Немає даних</div>';
    return;
  }

  let total = 0;
  const card = document.createElement('div');
  card.className = 'card';

  entries.forEach(([cat,sum]) => {
    total += sum;
    card.innerHTML += `
      <div class="row" style="margin-bottom:6px;">
        <div>${cat}</div>
        <div class="right ${statsType==='expense'?'neg':'pos'}">
          ${fmt(sum)} ${CURRENCY_SYMBOLS[state.selectedWallet.currency]}
        </div>
      </div>
    `;
  });

  card.innerHTML += `
    <hr style="opacity:.1">
    <div class="row">
      <div><b>Разом</b></div>
      <div class="right big ${statsType==='expense'?'neg':'pos'}">
        ${fmt(total)} ${CURRENCY_SYMBOLS[state.selectedWallet.currency]}
      </div>
    </div>
  `;

  el.appendChild(card);
}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    function renderComment(text){
    if (!text) return '';

    const m = text.match(/^\[(.+?)\]\s*(.*)$/);

    if (!m) {
      return `<div>${text}</div>`;
    }

    return `
      <div style="font-weight:700;font-size:13px">
        ${m[1]}
      </div>
      <div style="font-size:12px;opacity:.7">
        ${m[2]}
      </div>
    `;
  }

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  function renderWalletBalance(){
  const sum = state.entries.reduce((acc, e) => {
    return acc + Number(e.signed_amount || 0);
  }, 0);

  const cls = sum >= 0 ? 'pos' : 'neg';
  elWalletBalance.className = `big ${cls}`;
  elWalletBalance.textContent =
    `${fmt(sum)} ${state.selectedWallet.currency}`;
}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  // ===== Sheet: Entry =====
function openEntrySheet(type){
  if (!state.selectedWalletId || !state.selectedWallet) {
    alert('Спочатку відкрий рахунок');
    return;
  }

  if (!canWriteWallet(state.selectedWallet.owner)) {
    alert('Режим перегляду: редагування заборонено');
    return;
  }

  sheetType = type;
  applyEntrySheetColor(type);

  sheetEntryTitle.textContent =
    type === 'income' ? 'Додати дохід' : 'Додати витрату';

  sheetCategory.innerHTML = '<option value="">Категорія</option>';
  CATEGORIES[type].forEach(cat => {
    const opt = document.createElement('option');
    opt.value = cat;
    opt.textContent = cat;
    sheetCategory.appendChild(opt);
  });

  sheetAmount.value = '';
  sheetComment.value = '';
  sheetCategory.value = '';

  sheetEntry.classList.remove('hidden');
  resetReceiptUI();

}


/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

function closeEntrySheet(){
  sheetEntry.classList.add('hidden');
  sheetType = null;
  state.editingEntryId = null;
  sheetEntry.classList.remove('entry-income', 'entry-expense');
  resetReceiptUI();

}


/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

async function submitEntry(entry_type, amount, comment){
  if (!checkOnline()) return false;

  const finalComment = sheetCategory.value
    ? `[${sheetCategory.value}] ${comment || ''}`
    : (comment || '');

  const isEdit = !!state.editingEntryId;

  const url = isEdit
    ? `/api/entries/${state.editingEntryId}`
    : '/api/entries';

  const method = isEdit ? 'PUT' : 'POST';

  const payload = isEdit
    ? { amount: Number(amount), comment: finalComment }
    : {
        wallet_id: state.selectedWalletId,
        entry_type,
        amount: Number(amount),
        comment: finalComment
      };

  const res = await fetch(url, {
    method,
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': CSRF,
      'Accept': 'application/json',
    },
    body: JSON.stringify(payload)
  });

  if (!res.ok) {
    const txt = await res.text();
    alert(txt || 'Помилка');
    return false;
  }

  entryFeedback(entry_type);




  // 1) Витягуємо id створеної операції (тільки для POST)
  let createdId = null;
  if (!isEdit) {
    try {
      const data = await res.json();
      createdId = data?.id ?? data?.entry?.id ?? null;
    } catch (e) {
      // якщо бек повернув не JSON — тоді createdId буде null
    }
  }

  // 2) Якщо є фото і це нова операція — завантажуємо чек
  if (!isEdit && state.pendingReceiptFile) {

    if (!createdId) {
      alert('Операцію створено, але сервер не повернув id. Треба щоб POST /api/entries повертав JSON {id: ...}.');
      // не валимо всю операцію, просто без фото
    } else {
      const form = new FormData();
      form.append('file', state.pendingReceiptFile);

      const up = await fetch(`/api/entries/${createdId}/receipt`, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': CSRF,
          'Accept': 'application/json',
        },
        body: form
      });

      if (!up.ok) {
        const txt = await up.text();
        alert('Чек не завантажився: ' + (txt || up.status));
        // операцію не відміняємо, просто фото не прикріпилось
      } else {
        resetReceiptUI();
      }
    }
  }

  state.editingEntryId = null;

  await loadEntries(state.selectedWalletId);
  await loadWallets();
  return true;
}


/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  // ===== Sheet: Wallet =====
  function openWalletSheet(){
    if (state.viewOwner !== state.actor) {
      alert('У режимі перегляду партнера створення рахунків заборонено');
      return;
    }
    walletName.value = '';
    walletCurrency.value = 'UAH';
    sheetWallet.classList.remove('hidden');
    setTimeout(() => walletName.focus(), 50);
  }

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  function closeWalletSheet(){
    sheetWallet.classList.add('hidden');
  }

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  async function submitWallet(name, currency){
    const res = await fetch('/api/wallets', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': CSRF
      },
      body: JSON.stringify({
        name,
        currency,
        type: 'cash'
      })
    });

    if (!res.ok) {
      const txt = await res.text();
      alert(`Помилка: ${res.status}\n${txt.slice(0, 300)}`);
      return false;
    }

    await loadWallets();
    return true;
  }

  sheetWallet.querySelector('.sheet-backdrop').onclick = closeWalletSheet;
  walletConfirm.onclick = async () => {
    const name = (walletName.value || '').trim();
    const currency = walletCurrency.value;

    if (!name) {
      alert('Введи назву рахунку');
      return;
    }

    const ok = await submitWallet(name, currency);
    if (ok) closeWalletSheet();
  };

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  // ===== Delete wallet (мережа) =====
  async function deleteWallet(walletId, walletName){
    if (state.viewOwner !== state.actor) {
      alert('У режимі перегляду партнера видалення заборонено');
      return;
    }

    const res = await fetch(`/api/wallets/${walletId}`, {
      method: 'DELETE',
      headers: { 'X-CSRF-TOKEN': CSRF }
    });

    if (!res.ok) {
      const txt = await res.text();
      alert(`Помилка: ${res.status}\n${txt.slice(0, 300)}`);
      return;
    }

    if (state.selectedWalletId === walletId) {
      showWallets();
      state.selectedWalletId = null;
      state.selectedWallet = null;
      state.entries = [];
    }

    await loadWallets();
  }

  // ESC close any sheet + роззброїти delete
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    if (!sheetEntry.classList.contains('hidden')) closeEntrySheet();
    if (!sheetWallet.classList.contains('hidden')) closeWalletSheet();

    disarmDelete();
    renderWallets();
  });

  // UI events
  document.getElementById('refresh').onclick = (e) => { e.preventDefault(); loadWallets(); };
  btnBack.onclick = (e) => { e.preventDefault(); showWallets(); };

  btnIncome.onclick = (e) => { e.preventDefault(); openEntrySheet('income'); };
  btnExpense.onclick = (e) => { e.preventDefault(); openEntrySheet('expense'); };

  btnAddWallet.onclick = (e) => { e.preventDefault(); openWalletSheet(); };

  if (btnViewK) {
    btnViewK.onclick = (e) => { e.preventDefault(); setViewOwner('kolisnyk'); };
  }

  if (btnViewH) {
    btnViewH.onclick = (e) => { e.preventDefault(); setViewOwner('hlushchenko'); };
  }


  // init
  if (!IS_ACCOUNTANT) {
    setViewOwner(state.viewOwner);
  }
  loadWallets();


    const burgerBtn = document.getElementById('burgerBtn');
    const burgerMenu = document.getElementById('burgerMenu');

    burgerBtn.onclick = (e) => {
    e.stopPropagation();
    burgerMenu.classList.toggle('hidden');
    };

    // клік поза меню — закрити
    document.addEventListener('click', () => {
    if (!burgerMenu.classList.contains('hidden')) {
        burgerMenu.classList.add('hidden');
    }
    });

    function fmt(n) {
  return Number(n || 0).toLocaleString('uk-UA');
}


document.addEventListener('click', async (e) => {
  const curBtn = e.target.closest('[data-hcur]');
  if (curBtn){
    state.holdingCurrency = curBtn.dataset.hcur;
    if (state.holdingCurrency !== 'UAH' && !state.fx) await loadFx();
    renderHoldingCard();
    return;
  }

  if (e.target.closest('#holdingRefreshFx')){
    await loadFx();
    renderHoldingCard();
  }
});



function fmtMoney(n){
  return Number(n || 0).toLocaleString('uk-UA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function fxMapFromApi(data){
  const map = {};
  (data?.rates || []).forEach(r => {
    if (!r?.currency) return;
    map[r.currency] = {
      purchase: Number(r.purchase),
      sale: Number(r.sale),
    };
  });
  return { date: data?.date || '', map };
}

async function loadFx(){
  if (state.fxLoading) return state.fx;
  state.fxLoading = true;
  try{
    const res = await fetch('/api/exchange-rates', { headers: { 'Accept': 'application/json' } });
    const data = await res.json().catch(()=>null);
    if (!res.ok || !data?.rates) return state.fx;

    state.fx = fxMapFromApi(data);
    return state.fx;
  } finally {
    state.fxLoading = false;
  }
}

function convertAmount(amount, from, to){
  amount = Number(amount || 0);
  if (!amount) return 0;
  if (from === to) return amount;

  // UAH базова без курсу
  if (to === 'UAH') {
    if (from === 'UAH') return amount;
    const r = state.fx?.map?.[from];
    if (!r?.purchase) return NaN;
    return amount * r.purchase; // foreign -> UAH (sell foreign)
  }

  if (from === 'UAH') {
    const r = state.fx?.map?.[to];
    if (!r?.sale) return NaN;
    return amount / r.sale; // UAH -> foreign (buy foreign)
  }

  // foreign -> foreign через UAH
  const uah = convertAmount(amount, from, 'UAH');
  return convertAmount(uah, 'UAH', to);
}

function computeHoldingTotals(base){
  let cash = 0;
  let bank = 0;
  const missing = new Set();

  // cash wallets
  state.wallets.forEach(w => {
    const v = convertAmount(w.balance, w.currency, base);
    if (Number.isFinite(v)) cash += v;
    else missing.add(w.currency);
  });

  // bank accounts
  state.bankAccounts.forEach(b => {
    const v = convertAmount(b.balance, b.currency, base);
    if (Number.isFinite(v)) bank += v;
    else missing.add(b.currency);
  });

  return { cash, bank, total: cash + bank, missing: [...missing] };
}

function renderHoldingCard(){
  const el = document.getElementById('holdingCard');
  if (!el) return;

  // робітнику не показуємо холдинг
  if (AUTH_USER.role === 'worker'){
    el.classList.add('hidden');
    return;
  }

  el.classList.remove('hidden');

  const base = state.holdingCurrency || 'UAH';

  // якщо курс ще не підвантажили
  if (!state.fx && base !== 'UAH'){
    el.innerHTML = `
      <div class="holding-head">
        <div>
          <div class="holding-title">SG Holding</div>
          <div class="holding-sub">Потрібен курс обмінника для конвертації</div>
        </div>
        <button class="btn mini" id="holdingRefreshFx">↻ Курс</button>
      </div>
    `;
    return;
  }

  const hasFx = !!state.fx;
  const totals = (base === 'UAH' || hasFx)
    ? computeHoldingTotals(base)
    : {cash:0, bank:0, total:0, missing:[]};

  const cls = totals.total >= 0 ? 'pos' : 'neg';
  const sym = CURRENCY_SYMBOLS[base] ?? '';

  el.innerHTML = `
    <div class="holding-head">
      <div>
        <div class="holding-title">SG Holding</div>
        <div class="holding-sub">
          Загальний баланс по всіх рахунках
          ${state.fx?.date ? `• курс: ${state.fx.date}` : ''}
        </div>
      </div>

      <div>
        <div class="segmented holding-mode">
          <button type="button" data-hcur="UAH" class="${base==='UAH'?'active':''}">UAH</button>
          <button type="button" data-hcur="USD" class="${base==='USD'?'active':''}">USD</button>
          <button type="button" data-hcur="EUR" class="${base==='EUR'?'active':''}">EUR</button>
        </div>
      </div>
    </div>

    <div class="holding-amount ${cls}">
      ${fmtMoney(totals.total)} ${sym} ${base}
    </div>

    <div class="holding-break">
      <div class="holding-pill">💵 Cash: ${fmtMoney(totals.cash)} ${sym}</div>
      <div class="holding-pill">🏦 Bank: ${fmtMoney(totals.bank)} ${sym}</div>
      <button class="btn mini" id="holdingRefreshFx">↻ Курс</button>
    </div>

    ${totals.missing.length ? `
      <div class="holding-warn">
        ⚠️ Немає курсу для: <b>${totals.missing.join(', ')}</b>
      </div>
    ` : ''}
  `;
}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  function initStatsMonth(){
    const sel = document.getElementById('statsMonth');
    sel.innerHTML = '<option value="">Місяць</option>';

    const months = {};
    state.entries.forEach(e => {
      const ym = e.posting_date.slice(0,7); // YYYY-MM
      months[ym] = true;
    });

    Object.keys(months)
      .sort()
      .reverse()
      .forEach(ym => {
        const [y,m] = ym.split('-');
        const opt = document.createElement('option');
        opt.value = ym;
        opt.textContent = `${m}.${y}`;
        sel.appendChild(opt);
      });
  }

  const csvInput = document.getElementById('csvInput');

  if (csvInput) {
    csvInput.addEventListener('change', async () => {
      const file = csvInput.files[0];
      if (!file) return;

      const form = new FormData();
      form.append('file', file);

      const res = await fetch('/api/bank/csv-preview', {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': CSRF
        },
        body: form
      });

      const data = await res.json();
      console.log('CSV PREVIEW', data);

     renderCsvPreview(data.rows);

    });
  }

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////


// ================= BANK ACCOUNT OPEN =================
window.openBankAccount = async function (bank) {

  state.selectedWalletId = null;
  state.selectedWallet = {
    id: bank.id,
    name: bank.name,
    currency: bank.currency,
    type: 'bank'
  };

  elWalletTitle.textContent = `${bank.name} • ${bank.currency}`;
  elEntries.innerHTML = '<tr><td class="muted">Завантаження…</td></tr>';

  elWalletBalance.className = `big ${bank.balance >= 0 ? 'pos' : 'neg'}`;
  elWalletBalance.textContent = `${fmt(bank.balance)} ${bank.currency}`;

  btnIncome.disabled = true;
  btnExpense.disabled = true;
  roTag.style.display = '';

  showOps();

  // 🟢 MONOBANK
  if (bank.bankCode === 'monobank') {
    try {
      const res = await fetch(`/api/bank/transactions-monobank?id=${bank.id.replace('mono_','')}`);
      const rows = res.ok ? await res.json() : [];

      state.entries = rows.map(r => ({
        posting_date: r.date,
        signed_amount: r.amount,
        comment: r.comment,
      }));

      renderEntries();
      renderEntriesSummary();
    } catch (e) {
      elEntries.innerHTML = '<tr><td class="muted">Помилка завантаження</td></tr>';
    }
    return;
  }

  // 🟣 PRIVAT
  if (bank.bankCode === 'privatbank') {
    try {
      const res = await fetch(`/api/bank/transactions-privat?id=${bank.id.replace('privat_','')}`);
      const rows = res.ok ? await res.json() : [];

      state.entries = rows.map(r => ({
        posting_date: r.date,
        signed_amount: r.amount,
        comment: r.comment,
      }));

      renderEntries();
      renderEntriesSummary();
    } catch {
      elEntries.innerHTML = '<tr><td class="muted">Помилка завантаження</td></tr>';
    }
    return;
  }

    if (bank.bankCode === 'ukrgasbank_solarglass') {
    const res = await fetch(`/api/bank/transactions-solarglass?iban=${encodeURIComponent(bank.iban)}`);
    const rows = res.ok ? await res.json() : [];

    state.entries = rows.map(r => ({
      posting_date: r.date,
      signed_amount: r.amount,
      comment: r.comment || r.counterparty || '',
    }));

    renderEntries();
    renderEntriesSummary();
    return;
  }



  // 🟡 UKRGAS
  const url =
    bank.bankCode === 'ukrgasbank_sggroup'
      ? `/api/bank/transactions-sggroup?iban=${encodeURIComponent(bank.iban)}`
      : `/api/bank/transactions-engineering?iban=${encodeURIComponent(bank.iban)}`;

  try {
    const res = await fetch(url);
    const rows = res.ok ? await res.json() : [];

    state.entries = rows.map(r => ({
      posting_date: r.date,
      signed_amount: r.amount,
      comment: r.comment || r.counterparty || '',
    }));

    renderEntries();
    renderEntriesSummary();

  } catch (e) {
    elEntries.innerHTML = '<tr><td class="muted">Помилка завантаження</td></tr>';
  }
};



function isToday(dateStr) {
  const today = new Date().toISOString().slice(0, 10);
  return dateStr === today;
}



async function deleteEntry(id){
  if (!confirm('Видалити операцію?')) return;

  const res = await fetch(`/api/entries/${id}`, {
    method: 'DELETE',
    headers: { 'X-CSRF-TOKEN': CSRF }
  });

  if (!res.ok) {
    const txt = await res.text();
    alert(txt || 'Помилка видалення');
    return;
  }

  await loadEntries(state.selectedWalletId);
  await loadWallets();
}


async function editEntry(id){
  const entry = state.entries.find(e => e.id === id);
  if (!entry) return;

  if (!isToday(entry.posting_date)) {
    alert('Можна редагувати лише сьогоднішні операції');
    return;
  }

  sheetType = entry.signed_amount >= 0 ? 'income' : 'expense';
  applyEntrySheetColor(sheetType);

  state.editingEntryId = id;

  sheetEntryTitle.textContent = 'Редагувати операцію';

  sheetAmount.value = Math.abs(entry.signed_amount);
  sheetComment.value = entry.comment || '';

  sheetCategory.innerHTML = '<option value="">Категорія</option>';
  CATEGORIES[sheetType].forEach(cat => {
    const opt = document.createElement('option');
    opt.value = cat;
    opt.textContent = cat;
    sheetCategory.appendChild(opt);
  });

  const m = (entry.comment || '').match(/^\[(.+?)\]/);
  if (m) sheetCategory.value = m[1];

  sheetEntry.classList.remove('hidden');
}



document.addEventListener('click', () => {
  if (state.activeEntryId !== null) {
    state.activeEntryId = null;
    renderEntries();
  }
});

</script>


<!-- Видалення рахунку -->

<script>
  function initPirateDelete(){
  document.querySelectorAll('.account-card.account-cash').forEach(card => {

    if (card._pirateBound) return;
    if (card.classList.contains('ro')) return;

    card._pirateBound = true;

    let pressTimer = null;
    let stage = 0;

    const skull = card.querySelector('.pirate-skull');
    const text  = card.querySelector('.pirate-text');

    let suppressClick = false;

    const start = () => {
      suppressClick = false;

      pressTimer = setTimeout(() => {
        stage = 1;
        suppressClick = true; // ⛔ блокуємо відкриття рахунку
        card.classList.add('stage-1');
        text.textContent = 'Видалити рахунок?';

        // автоскасування через 3 сек
        setTimeout(() => {
          if (stage === 1) reset();
        }, 3000);

      }, 700);
    };

    const stop = () => {
      clearTimeout(pressTimer);
    };



    card.addEventListener('mousedown', start);
    card.addEventListener('touchstart', start);
    card.addEventListener('mouseup', stop);
    card.addEventListener('mouseleave', stop);
    card.addEventListener('touchend', stop);
    card.addEventListener('click', (e) => {

      // ⛔ якщо клік по черепу — НЕ ЧІПАЄМО
      if (e.target.closest('.pirate-skull')) {
        return;
      }

      if (suppressClick) {
        e.preventDefault();
        e.stopImmediatePropagation();
        suppressClick = false;
        return;
      }

      if (stage > 0) {
        reset();
      }

    }, true); // capture




    function reset(){ 
      stage = 0;
      suppressClick = false;
      card.classList.remove('stage-1','stage-2');
      text.textContent = '';
    }




skull.onclick = (e) => {
  e.stopPropagation();

  // STAGE 1 → STAGE 2 (попередження)
  if (stage === 1) {
    stage = 2;
    card.classList.remove('stage-1');
    card.classList.add('stage-2');

    text.innerHTML = `
      Ти гарно подумав?<br>
      Відновлення буде неможливе.
    `;
    return;
  }

    // STAGE 2 → STAGE 3 (таймер 10 сек)
    if (stage === 2) {
      stage = 3;
      let seconds = 10;

      card.classList.add('stage-3');
      skull.style.pointerEvents = 'none';

      const countdown = setInterval(() => {
        text.innerHTML = `Зачекай ${seconds} сек...<br>Після цього можна видалити`;
        seconds--;

        if (seconds < 0) {
          clearInterval(countdown);
          stage = 4;
          skull.style.pointerEvents = 'auto';
          text.innerHTML = 'Тепер можна видалити ☠️';
        }
      }, 1000);

      return;
    }

    // STAGE 4 → ВИДАЛЕННЯ
    if (stage === 4) {
      deleteAccount(card);
      reset();
    }
  };

  });
}


function deleteAccount(card){
  const id = card.dataset.accountId

  fetch(`/api/wallets/${id}`, {

    method: 'DELETE',
    headers: {
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      'Accept':'application/json'
    }
  })
  .then(r => {
    if (!r.ok) throw new Error()
    card.remove()
  })
  .catch(() => alert('Помилка видалення рахунку'))
}



//////////////////////////////////////////////////////////////////////////////////////
// КУРС ВАЛЮТ — МОДАЛКА
//////////////////////////////////////////////////////////////////////////////////////

document.getElementById('showRatesBtn').onclick = async (e) => {
  e.preventDefault(); // бо кнопка всередині form

  try {
    const res = await fetch('/api/exchange-rates', { headers: { 'Accept': 'application/json' } });
    const data = await res.json();

    if (!res.ok || data.error) {
      showRatesError('Не вдалося отримати курс валют');
      return;
    }

    renderRatesModal(data);

  } catch {
    showRatesError('Помилка при отриманні курсу валют');
  }
};

function renderRatesModal(data){
  const modal = document.getElementById('ratesModal');
  const body  = document.getElementById('ratesContent');

  body.innerHTML = `<div style="text-align:center; font-size:18px;font-weight:bold;opacity:.7;margin-bottom:10px">📅 ${data.date}</div>`;

  data.rates.forEach(r => {
    body.innerHTML += `
      <div class="rate-card" data-currency="${r.currency}"
        onclick="selectRateCard(this); openExchange('${r.currency}', ${r.purchase}, ${r.sale})">


        <div class="rate-title rate-title-${r.currency.toLowerCase()}">${r.currency}</div>
        💰 Купівля: <b>${r.purchase ?? '—'}</b><br>
        🏦 Продаж: <b>${r.sale ?? '—'}</b>
      </div>
    `;
  });

  modal.classList.remove('hidden');
}

function showRatesError(text){
  const body  = document.getElementById('ratesContent');
  body.innerHTML = `<div style="color:#ff6b6b">${text}</div>`;
  document.getElementById('ratesModal').classList.remove('hidden');
}

function closeRatesModal(){
  document.getElementById('ratesModal')?.classList.add('hidden');
}

// клік по хрестику
document.addEventListener('click', (e) => {
  if (e.target.closest('#ratesClose')) closeRatesModal();
});

// клік по затемненню
document.addEventListener('click', (e) => {
  if (e.target.classList.contains('modal-backdrop')) closeRatesModal();
});



document.addEventListener('DOMContentLoaded', () => {

  const modalPanel = document.querySelector('.modal-panel');
  if (!modalPanel) return; // якщо ще нема — не падаємо

  let startY = 0;
  let currentY = 0;
  let dragging = false;

  modalPanel.addEventListener('touchstart', (e) => {
    startY = e.touches[0].clientY;
    dragging = true;
  });

  modalPanel.addEventListener('touchmove', (e) => {
    if (!dragging) return;
    currentY = e.touches[0].clientY;
    const diff = currentY - startY;

    if (diff > 0) {
      modalPanel.style.transform = `translateY(${diff}px)`;
    }
  });

  modalPanel.addEventListener('touchend', () => {
    dragging = false;
    const diff = currentY - startY;

    if (diff > 120) {
      closeRatesModal();
    }

    modalPanel.style.transform = '';
  });

});










let currentRate = null;
let currentCurrency = null;
let mode = 'buy';

// відкриття обмінника
window.openExchange = function(currency, purchase, sale){
  currentCurrency = currency;
  currentRate = { purchase: Number(purchase), sale: Number(sale) };

  document.getElementById('exchangeBox')?.classList.remove('hidden');
  document.querySelector('.modal-panel')?.classList.add('expanded');

  syncExchangeUI();
  updateExchange('from');
};

function syncExchangeUI(){
  const fromLabel = document.getElementById('exFromLabel');
  const toLabel   = document.getElementById('exToLabel');
  const fromInput = document.getElementById('exFrom');
  const toInput   = document.getElementById('exTo');

  if (!fromLabel || !toLabel || !fromInput || !toInput) return;

  if (mode === 'buy') {
    // Купуємо валюту: UAH -> CUR
    fromLabel.textContent = 'UAH';
    toLabel.textContent   = currentCurrency || '';
    fromInput.placeholder = 'Віддаємо (грн)';
    toInput.placeholder   = 'Отримуємо (валюта)';
  } else {
    // Продаємо валюту: CUR -> UAH
    fromLabel.textContent = currentCurrency || '';
    toLabel.textContent   = 'UAH';
    fromInput.placeholder = 'Віддаємо (валюта)';
    toInput.placeholder   = 'Отримуємо (грн)';
  }
}



document.addEventListener('click', (e) => {
  if (e.target.id === 'modeBuy')  {
    mode = 'buy';
    document.getElementById('modeBuy').classList.add('active');
    document.getElementById('modeSell').classList.remove('active');
    syncExchangeUI();
    updateExchange('from');
  }

  if (e.target.id === 'modeSell') {
    mode = 'sell';
    document.getElementById('modeSell').classList.add('active');
    document.getElementById('modeBuy').classList.remove('active');
    syncExchangeUI();
    updateExchange('from');
  }
});


document.addEventListener('input', (e) => {
  if (e.target.id === 'exFrom') updateExchange('from');
  if (e.target.id === 'exTo')   updateExchange('to');
});



window.selectRateCard = function(card){
  document.querySelectorAll('.rate-card').forEach(c => c.classList.remove('active'));
  card.classList.add('active');
};



function updateExchange(source = 'from'){
  const fromInput = document.getElementById('exFrom');
  const toInput   = document.getElementById('exTo');
  if (!fromInput || !toInput || !currentRate || !currentCurrency) return;

  const a = parseFloat(fromInput.value || 0);
  const b = parseFloat(toInput.value || 0);

  const sale = Number(currentRate.sale);       // банк продає валюту (ти купуєш)
  const buy  = Number(currentRate.purchase);   // банк купує валюту (ти продаєш)

  // BUY: UAH -> CUR, курс = sale (UAH за 1 CUR)
  if (mode === 'buy') {
    if (source === 'from') {
      toInput.value = a ? (a / sale).toFixed(2) : '';
    } else {
      fromInput.value = b ? (b * sale).toFixed(2) : '';
    }
    return;
  }

  // SELL: CUR -> UAH, курс = purchase (UAH за 1 CUR)
  if (source === 'from') {
    toInput.value = a ? (a * buy).toFixed(2) : '';
  } else {
    fromInput.value = b ? (b / buy).toFixed(2) : '';
  }
}

//////////////////////////////////////////////////////////////////////////////////////
// КЕШ співробітників — МОДАЛКА
//////////////////////////////////////////////////////////////////////////////////////
// ВІДКРИТТЯ МОДАЛКИ
window.openStaffCash = function () {

  const staffWallets = state.wallets.filter(w =>
    w.owner === 'accountant' || w.owner === 'foreman'
  );

  const list = document.getElementById('staffCashList');

  list.innerHTML = staffWallets.map(w => {

    const badge =
      w.owner === 'accountant'
        ? '<div class="staff-badge">Бухгалтер</div>'
        : '<div class="staff-badge" style="background:rgba(76,125,255,.15);border-color:rgba(76,125,255,.35);color:#4c7dff">Прораб</div>';

    return `
      <div class="rate-card" onclick="openStaffWallet(${w.id})">

        <div style="display:flex;justify-content:space-between;align-items:center;">
          <div class="rate-title">${w.name}</div>
          ${badge}
        </div>

        <div style="margin-top:6px;font-size:16px;font-weight:700;">
          ${Number(w.balance).toFixed(2)} ${w.currency}
        </div>

      </div>
    `;
  }).join('');

  document.getElementById('staffCashModal').classList.remove('hidden');
}



// ЗАКРИТТЯ
window.closeStaffCash = function(){
  document.getElementById('staffCashModal').classList.add('hidden');
}


// ВІДКРИТТЯ РАХУНКУ
window.openStaffWallet = async function(walletId){
  closeStaffCash();
  await loadEntries(walletId);
}





document.addEventListener('DOMContentLoaded', () => {

  const staffPanel = document.querySelector('#staffCashModal .modal-panel');
  if (!staffPanel) return;

  let startY = 0;
  let currentY = 0;
  let dragging = false;

  staffPanel.addEventListener('touchstart', e => {
    startY = e.touches[0].clientY;
    dragging = true;
  });

  staffPanel.addEventListener('touchmove', e => {
    if (!dragging) return;
    currentY = e.touches[0].clientY;
    const diff = currentY - startY;

    if (diff > 0) {
      staffPanel.style.transform = `translateY(${diff}px)`;
    }
  });

  staffPanel.addEventListener('touchend', () => {
    dragging = false;
    const diff = currentY - startY;

    if (diff > 120) closeStaffCash();

    staffPanel.style.transform = '';
  });

});



window.openReceipt = function(url){
  const modal = document.getElementById('receiptModal');
  const img   = document.getElementById('receiptFullImg');
  const aOpen = document.getElementById('receiptOpenNew');
  const aDown = document.getElementById('receiptDownload');

  if (!modal || !img) return;

  img.src = url;
  aOpen.href = url;
  aDown.href = url;

  modal.classList.remove('hidden');
  document.body.classList.add('modal-open');
};

window.closeReceiptModal = function(){
  const modal = document.getElementById('receiptModal');
  const img   = document.getElementById('receiptFullImg');
  if (!modal) return;

  modal.classList.add('hidden');
  document.body.classList.remove('modal-open');
  if (img) img.src = '';
};

// клік по ✕
document.addEventListener('click', (e) => {
  if (e.target.closest('#receiptClose')) closeReceiptModal();
});

// Esc
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') closeReceiptModal();
});




// =======================
// 🔊 SOUND + 📳 VIBRO
// =======================
let SND = {
  leave: null,
  moneta: null,
  alarm: null,
  unlocked: false,
};

document.addEventListener('DOMContentLoaded', () => {
  SND.leave  = document.getElementById('sndLeave');
  SND.moneta = document.getElementById('sndMoneta');
  SND.alarm  = document.getElementById('sndAlarm');

  const unlock = () => {
    if (SND.unlocked) return;
    SND.unlocked = true;

    [SND.leave, SND.moneta, SND.alarm].forEach(a => {
      if (!a) return;
      try {
        a.muted = true;
        a.play().then(() => {
          a.pause();
          a.currentTime = 0;
          a.muted = false;
        }).catch(() => {
          a.muted = false;
        });
      } catch {}
    });
  };

  // перший жест користувача "розблоковує" аудіо (особливо iOS)
  document.addEventListener('touchstart', unlock, { once: true, passive: true });
  document.addEventListener('click', unlock, { once: true });
});

function playSound(a, volume = 0.9) {
  if (!a) return;
  try {
    a.volume = volume;
    a.currentTime = 0;
    a.play().catch(()=>{});
  } catch {}
}

function vibrate(pattern) {
  // Android/Chrome: працює; iOS: зазвичай ігнорує (це нормально)
  try { navigator.vibrate?.(pattern); } catch {}
}

function entryFeedback(type) {
  if (type === 'income') {
    playSound(SND.moneta, 0.85);
    vibrate([18, 22, 18]);
  } else if (type === 'expense') {
    playSound(SND.leave, 0.9);
    vibrate([30]);
  }
}

function deleteFeedback() {
  playSound(SND.alarm, 1.0);
  vibrate([60, 40, 60, 40, 120]); // “сирена”
}







</script>

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













<audio id="sndLeave" preload="auto" src="/sounds/leave.mp3"></audio>
<audio id="sndMoneta" preload="auto" src="/sounds/moneta.mp3"></audio>


</body>
</html>
