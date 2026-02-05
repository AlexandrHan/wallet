<!doctype html>
<html lang="uk">
<head>
<meta charset="utf-8"/>
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0b0d10">
<title>Рекламації • SolarGlass</title>

<style>
:root{
  --stroke:rgba(255,255,255,.08);
  --text:#e9eef6;
  --muted:#9aa6bc;
  --blur:20px;
  --radius-xl:22px;
  --radius-lg:18px;
}
.main-row{
    margin-top:5rem;
}
*{ box-sizing:border-box; }


/* ФОН */
body{
  margin:0;
  font-family:system-ui;
  color:var(--text);
  background:none;
}

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
  background-attachment:fixed;
}


/* HEADER ЯК У ГАМАНЦІ */
header{
  position:fixed;
  top:0; left:0; right:0;
  z-index:1000;
  background:linear-gradient(to bottom,
    rgba(11,16,12,.88) 0%,
    rgba(11,16,12,.49) 35%,
    rgba(11,16,12,.36) 65%,
    rgba(11,16,12,0) 100%);
}
.wrap{max-width:980px;margin:0 auto;padding:18px;}
.row{display:flex;gap:14px;align-items:center;flex-wrap:wrap;}
.top-area{width:100%;display:flex;justify-content:space-between;align-items:center;}
.logo img{height:48px;filter:drop-shadow(0 0 6px rgba(0,0,0,.6));}
.userName{font-weight:800}

/* BURGER */
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
.burger-menu{
  position:absolute;right:0;top:90px;z-index:2000;
  min-width:220px;padding:8px;
  border-radius:16px;
  background:linear-gradient(to bottom, rgba(255,255,255,.08), rgba(255,255,255,.02)), rgba(0,0,0,.65);
  backdrop-filter:blur(12px) saturate(200%);
  border:1px solid rgba(255,255,255,.14);
  display:flex;flex-direction:column;gap:4px;
}
.burger-menu.hidden{display:none}
.burger-item{
  padding:14px 16px;border-radius:12px;color:var(--text);
  text-decoration:none;font-weight:600;background:transparent;border:none;
}
.burger-item:hover{background:rgba(255,255,255,.1)}
.burger-item.danger{color:#ff6b6b}

/* ВІДСТУП ПІД ХЕДЕР */
main{
  margin-top:5rem;   /* ← твій відступ */
  padding-bottom:2rem;
}


.controls{
  display:flex;
  flex-direction:column;
  gap:12px;
  margin:5rem 0 16px 0;
  width:100%;
}

.controls-row{
  display:flex;
  gap:12px;
  width:100%;
}

.filter,
.btn{
  width:100%;
}

.equal{
  flex:1;
  height:48px;
}



/* UI */
.btn{
  padding:10px 16px;border-radius:var(--radius-lg);
  background:rgba(255,255,255,.08);
  border:1px solid var(--stroke);
  color:var(--text);cursor:pointer;
}
.btn.primary{background:rgba(84,192,134,.8);color:#000;width: 45%;}
.btn.mini{padding:6px 10px;font-size:13px;border-radius:14px;width: 45%;}
.filter{
  width:100%;padding:12px;border-radius:14px;
  border:1px solid var(--stroke);
  background:rgba(255,255,255,.08);
  color:var(--text);
}
.reclamations-grid{
  display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));
  gap:18px;margin-top:20px;
}
.reclamation-card{
  padding:18px;border-radius:var(--radius-xl);
  background:linear-gradient(180deg, rgba(255,255,255,.12), rgba(255,255,255,.02));
  backdrop-filter:blur(var(--blur));
  border:1px solid var(--stroke);
  display:flex;flex-direction:column;gap:10px;
}
.reclamation-top{display:flex;justify-content:space-between;font-weight:700;}
.reclamation-status{padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700;}
.status-new .reclamation-status{background:#4c7dff33;color:#4c7dff;}
.status-work .reclamation-status{background:#ffb86c33;color:#ffb86c;}
.status-wait .reclamation-status{background:#9aa6bc33;color:#9aa6bc;}
.status-done .reclamation-status{background:#66f2a833;color:#66f2a8;}
.reclamation-title{font-weight:800;}
.reclamation-meta{font-size:13px;opacity:.75;}
.reclamation-footer{margin-top:auto;display:flex;justify-content:space-between;}
.priority-high{
    background:#ff6b6b33;
    color:#ff6b6b;padding:4px 10px;
    text-align:center;
    border-radius:999px;width: 9rem;
}
.priority-mid{text-align:center;background:#ffb86c33;color:#ffb86c;padding:4px 10px;border-radius:999px;width: 9rem;}
.priority-low{text-align:center;background:#66f2a833;color:#66f2a8;padding:4px 10px;border-radius:999px;width: 9rem;}

.modal.hidden{ display:none; }

.modal{
  position:fixed;
  inset:0;
  z-index:5000;
  display:flex;
  justify-content:center;
  align-items:flex-end;
}

.modal-backdrop{
  position:absolute;
  inset:0;
  background:rgba(0,0,0,.55);
  backdrop-filter:blur(4px);
}

.modal-sheet{
  position:relative;
  width:100%;
  max-width:600px;
  background:linear-gradient(180deg, rgba(255,255,255,.12), rgba(255,255,255,.02));
  backdrop-filter:blur(25px) saturate(160%);
  border:1px solid var(--stroke);
  border-radius:24px 24px 0 0;
  padding:20px;
  animation:sheetUp .25s ease;
}

@keyframes sheetUp{
  from{ transform:translateY(100%); opacity:0 }
  to{ transform:translateY(0); opacity:1 }
}

.modal-header{
  display:flex;
  justify-content:space-between;
  align-items:center;
  margin-bottom:14px;
}

.modal-title{
  font-weight:800;
  font-size:18px;
}

.modal-close{
  background:none;
  border:none;
  color:#fff;
  font-size:20px;
  cursor:pointer;
}

.form-group{ margin-bottom:12px; display:flex; flex-direction:column; gap:6px; }
.form-row{ display:flex; gap:12px; }
.form-row .form-group{ flex:1; }

.service-case{
  margin-top:5rem;
  padding:20px;
  border-radius:22px;
  background:linear-gradient(180deg, rgba(255,255,255,.12), rgba(255,255,255,.02));
  backdrop-filter:blur(25px);
  border:1px solid rgba(255,255,255,.08);
}

.case-header{
  display:flex;
  justify-content:space-between;
  align-items:center;
  margin-bottom:20px;
}

.case-title{font-size:20px;font-weight:800}
.case-sub{opacity:.7;font-size:13px}

.case-stage{
  padding:6px 14px;
  border-radius:999px;
  font-size:13px;
  font-weight:700;
}
.stage-repair{background:#ffb86c33;color:#ffb86c;}

.timeline{display:flex;flex-direction:column;gap:18px;}

.tl-item{display:flex;gap:14px;position:relative;}
.tl-dot{
  width:12px;height:12px;border-radius:50%;
  background:#555;flex-shrink:0;margin-top:5px;
}
.tl-item.done .tl-dot{background:#66f2a8;}
.tl-item.active .tl-dot{background:#ffb86c;}

.pulse{animation:pulse 1.2s infinite;}
@keyframes pulse{
  0%{box-shadow:0 0 0 0 rgba(255,184,108,.6)}
  70%{box-shadow:0 0 0 10px rgba(255,184,108,0)}
}

.tl-title{font-weight:700}
.tl-meta{font-size:12px;opacity:.7}

.loan-box{
  margin-top:24px;
  padding:16px;
  border-radius:16px;
  background:rgba(255,255,255,.05);
}

.loan-title{font-weight:700;margin-bottom:10px}
.loan-row{display:flex;gap:14px;flex-wrap:wrap}

.badge{
  padding:4px 10px;
  border-radius:999px;
  font-size:12px;
}
.badge.ours{background:#66f2a833;color:#66f2a8}

.case-actions{
  margin-top:24px;
  display:flex;
  gap:12px;
  flex-wrap:wrap;
}

.hidden{display:none!important;}



@media (min-width:1200px){
  .wrap{ max-width:1100px; }
}

</style>
</head>

<body>
<div class="app-bg"></div>


<header>
  <div class="wrap row">
    <div class="top-area">
      <a href="/" class="logo"><img src="/img/logo.png"></a>

      <div class="userName">
        {{ collect(explode(' ', trim(auth()->user()->name)))->first() }}
      </div>

      <div class="burger-wrap">
        <button type="button" id="burgerBtn" class="burger-btn">☰</button>

        <div id="burgerMenu" class="burger-menu hidden">
          <a href="/profile" class="burger-item">🔐 Адмінка / пароль</a>
          <a href="{{ url('/') }}" class="burger-item">💼 Гаманець</a>


          <div>

            <form method="POST" action="{{ route('logout') }}">
              @csrf
              <button type="submit" class="burger-item danger">🚪 Вийти</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</header>

<main class="wrap">
    <div class="controls">

    <input class="filter full" placeholder="Пошук">

    <div class="controls-row">
        <select class="filter equal">
        <option>Усі</option>
        <option>Нові</option>
        </select>

        <button class="btn primary equal" onclick="openReclamationModal()">
        ＋ Нова рекламація
        </button>

    </div>

    </div>


  <div id="reclamationsList" class="reclamations-grid"></div>











<div id="serviceView" class="service-case hidden">


  <div class="case-header">
    
    <div>
        <button class="btn" onclick="closeServiceCase()">← Назад</button>

      <div class="case-title">Solax X3 15k LV</div>
      <div class="case-sub">Клієнт: ТОВ Енергія • Черкаси</div>
    </div>
    <div class="case-stage stage-repair">В ремонті</div>
  </div>

  <!-- TIMELINE -->
  <div class="timeline">

    <div class="tl-item done">
      <div class="tl-dot"></div>
      <div class="tl-content">
        <div class="tl-title">Заявка створена</div>
        <div class="tl-meta">03.02.2026</div>
      </div>
    </div>

    <div class="tl-item done">
      <div class="tl-dot"></div>
      <div class="tl-content">
        <div class="tl-title">Демонтаж обладнання</div>
        <div class="tl-meta">Серійник: SXLV-77821</div>
      </div>
    </div>

    <div class="tl-item done">
      <div class="tl-dot"></div>
      <div class="tl-content">
        <div class="tl-title">Відправлено в сервіс</div>
        <div class="tl-meta">ТТН: 590003211</div>
      </div>
    </div>

    <div class="tl-item active">
      <div class="tl-dot pulse"></div>
      <div class="tl-content">
        <div class="tl-title">В ремонті</div>
        <div class="tl-meta">Очікуємо повернення</div>
      </div>
    </div>

    <div class="tl-item">
      <div class="tl-dot"></div>
      <div class="tl-content">
        <div class="tl-title">Повернувся з ремонту</div>
      </div>
    </div>

    <div class="tl-item">
      <div class="tl-dot"></div>
      <div class="tl-content">
        <div class="tl-title">Встановлено клієнту</div>
      </div>
    </div>

  </div>

  <!-- LOAN EQUIPMENT -->
  <div class="loan-box">
    <div class="loan-title">Підмінний фонд</div>
    <div class="loan-row">
      <div>Модель: Solax X3</div>
      <div>SN: SXLV-90012</div>
      <div class="badge ours">Наш склад</div>
    </div>
  </div>

  <!-- ACTIONS -->
  <div class="case-actions">
    <button class="btn">Повернувся з ремонту</button>
    <button class="btn primary">Встановлено клієнту</button>
  </div>

</div>
</main>

@verbatim
<script type="text/template" id="reclamationCardTpl">
<div class="reclamation-card status-__statusClass__">
  <div class="reclamation-top">
    <div>#__id__</div>
    <div class="reclamation-status">__status__</div>
  </div>
  <div class="reclamation-title">__title__</div>
  <div class="reclamation-meta">
    <div>👤 __client__</div>
    <div>📦 __product__</div>
    <div>📅 __date__</div>
  </div>
  <div class="reclamation-footer">
    <div class="priority-__priorityClass__">__priority__</div>
    <button class="btn mini" onclick="openServiceCase()">Відкрити</button>


  </div>
</div>
</script>
@endverbatim

<script>
document.getElementById('burgerBtn').onclick=e=>{
 e.stopPropagation();
 document.getElementById('burgerMenu').classList.toggle('hidden');
};
document.addEventListener('click',()=>burgerMenu.classList.add('hidden'));

const demoRecs=[
{id:104,status:"Нова",statusClass:"new",title:"Тріщина на панелі",client:"ТОВ Енергія",product:"JA Solar",date:"03.02.2026",priority:"Високий",priorityClass:"high"},
{id:105,status:"В роботі",statusClass:"work",title:"Інвертор не запускається",client:"ФОП Коваленко",product:"Deye",date:"02.02.2026",priority:"Середній",priorityClass:"mid"},
{id:106,status:"Очікує клієнта",statusClass:"wait",title:"Механічне пошкодження",client:"Solar Group",product:"Кабель",date:"31.01.2026",priority:"Низький",priorityClass:"low"}
];

function renderRecs(){
 const wrap=document.getElementById('reclamationsList');
 const tpl=document.getElementById('reclamationCardTpl').innerHTML;
 wrap.innerHTML=demoRecs.map(r=>{
  let h=tpl;
  h=h.replaceAll('__id__',r.id)
     .replaceAll('__status__',r.status)
     .replaceAll('__statusClass__',r.statusClass)
     .replaceAll('__title__',r.title)
     .replaceAll('__client__',r.client)
     .replaceAll('__product__',r.product)
     .replaceAll('__date__',r.date)
     .replaceAll('__priority__',r.priority)
     .replaceAll('__priorityClass__',r.priorityClass);
  return h;
 }).join('');
}
renderRecs();

function openExchange(){
  window.location.href = "/?modal=exchange";
}

function openStaffCash(){
  window.location.href = "/?modal=staffcash";
  
}


function openReclamationModal(){
  document.getElementById('reclamationModal').classList.remove('hidden');
}

function closeReclamationModal(){
  document.getElementById('reclamationModal').classList.add('hidden');
}

function createReclamation(){
  const rec = {
    id: Math.floor(Math.random()*1000),
    client: document.getElementById('recClient').value,
    product: document.getElementById('recProduct').value,
    title: document.getElementById('recTitle').value,
    statusClass: document.getElementById('recStatus').value,
    status: document.getElementById('recStatus').selectedOptions[0].text,
    priorityClass: document.getElementById('recPriority').value,
    priority: document.getElementById('recPriority').selectedOptions[0].text,
    date: new Date().toLocaleDateString()
  };

  demoRecs.unshift(rec);
  renderRecs();
  closeReclamationModal();
}

function openServiceCase(){
  document.getElementById('reclamationsList').classList.add('hidden');
  document.querySelector('.controls').classList.add('hidden');
  document.getElementById('serviceView').classList.remove('hidden');
}

function closeServiceCase(){
  document.getElementById('reclamationsList').classList.remove('hidden');
  document.querySelector('.controls').classList.remove('hidden');
  document.getElementById('serviceView').classList.add('hidden');
}

</script>









</body>
</html>
