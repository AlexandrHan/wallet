<header>
  <div style="margin-top:-1rem;" class="wrap row">
    <div class="top-area">
      <a href="{{ url('/') }}" class="logo">
        <img src="/img/logo.png" alt="SolarGlass">
      </a>

      <div class="userName">
        <span style="font-weight:800;">
          {{ collect(explode(' ', trim(auth()->user()->name)))->first() }}
        </span>
      </div>

      @include('partials.nav.top-avatar-placeholder')

    </div>

    <div class="header-right" style="display:flex; align-items:center; gap:6px;">
      <span class="tag" id="actorTag" style="display:none"></span>

      {{-- Notification bell --}}
      @auth
      <div id="notifBellWrap" style="position:relative; display:inline-flex;">
        <button id="notifBell" aria-label="Сповіщення"
          style="background:none; border:none; cursor:pointer; font-size:20px; padding:6px; color:inherit; position:relative; line-height:1;">
          🔔
          <span id="notifBadge"
            style="display:none; position:absolute; top:1px; right:1px; min-width:15px; height:15px; border-radius:8px; background:#e53935; color:#fff; font-size:9px; font-weight:700; line-height:15px; text-align:center; padding:0 3px; pointer-events:none;"></span>
        </button>

        {{-- Dropdown panel --}}
        <div id="notifPanel"
          style="position:absolute; top:calc(100% + 6px); right:0; width:310px; max-height:400px; background:#1a1d27; border:1px solid rgba(255,255,255,0.12); border-radius:14px; box-shadow:0 8px 32px rgba(0,0,0,0.55); z-index:9999; overflow:hidden; flex-direction:column; display:none;">
          <div style="padding:11px 14px 8px; display:flex; align-items:center; justify-content:space-between; flex-shrink:0; border-bottom:1px solid rgba(255,255,255,0.07);">
            <span style="font-weight:700; font-size:14px;">🔔 Сповіщення</span>
            <button id="notifMarkAll" style="background:none; border:none; cursor:pointer; font-size:11px; opacity:.55; color:inherit; padding:0;">Всі прочитані</button>
          </div>
          <div id="notifList" style="overflow-y:auto; flex:1; min-height:60px;"></div>
          <div style="padding:8px 12px; flex-shrink:0; border-top:1px solid rgba(255,255,255,0.07); text-align:center;">
            <a href="{{ route('messages.index') }}" style="font-size:12px; opacity:.55; color:inherit; text-decoration:none;">💬 Повідомлення</a>
          </div>
        </div>
      </div>
      @endauth
    </div>
  </div>
</header>

@auth
<style>
.notif-item { padding:10px 14px; border-bottom:1px solid rgba(255,255,255,0.05); cursor:pointer; transition:background .15s; }
.notif-item:last-child { border-bottom:none; }
.notif-item:hover { background:rgba(255,255,255,0.05); }
.notif-item.unread { background:rgba(100,180,255,0.07); }
.notif-item .notif-title { font-size:13px; font-weight:600; }
.notif-item .notif-msg { font-size:12px; opacity:.6; margin-top:2px; line-height:1.4; }
.notif-item .notif-time { font-size:10px; opacity:.35; margin-top:3px; }
</style>

<script>
(function () {
  const bell       = document.getElementById('notifBell');
  const badge      = document.getElementById('notifBadge');
  const panel      = document.getElementById('notifPanel');
  const list       = document.getElementById('notifList');
  const markAllBtn = document.getElementById('notifMarkAll');
  const csrf       = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
  const ICONS      = { finance:'💰', salary:'💸', project:'🏗', system:'⚙️', message:'💬', stock:'📦', salary_alert:'💸', finance_alert:'💰', project_alert:'🏗', stock_alert:'📦' };

  let isOpen = false, lastCount = 0;

  function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

  function timeAgo(iso) {
    const s = Math.floor((Date.now() - new Date(iso)) / 1000);
    if (s < 60)    return s + ' сек тому';
    if (s < 3600)  return Math.floor(s/60) + ' хв тому';
    if (s < 86400) return Math.floor(s/3600) + ' год тому';
    return new Date(iso).toLocaleDateString('uk-UA');
  }

  function updateBadge(n) {
    if (n > 0) { badge.textContent = n > 99 ? '99+' : n; badge.style.display = 'block'; }
    else        { badge.style.display = 'none'; }
  }

  async function fetchCount() {
    try {
      const d = await fetch('/api/notifications/count', { headers:{'Accept':'application/json'} }).then(r=>r.json());
      if (d.unread_count > lastCount && lastCount > 0) {
        try { new Audio('/sounds/moneta.mp3').play(); } catch {}
      }
      lastCount = d.unread_count;
      updateBadge(d.unread_count);
    } catch {}
  }

  async function loadPanel() {
    list.innerHTML = '<div style="padding:20px; text-align:center; opacity:.4; font-size:13px;">Завантаження...</div>';
    try {
      const d = await fetch('/api/notifications?limit=25', { headers:{'Accept':'application/json'} }).then(r=>r.json());
      updateBadge(d.unread_count);
      lastCount = d.unread_count;
      const items = d.notifications;
      if (!items || items.length === 0) {
        list.innerHTML = '<div style="padding:24px; text-align:center; opacity:.4; font-size:13px;">Немає сповіщень</div>';
        return;
      }
      list.innerHTML = items.map(n => `
        <div class="notif-item ${n.is_read ? '' : 'unread'}" data-id="${n.id}" onclick="sgNotifRead(${n.id},this)">
          <div class="notif-title">${ICONS[n.type] ?? '🔔'} ${esc(n.title)}</div>
          <div class="notif-msg">${esc(n.message)}</div>
          <div class="notif-time">${timeAgo(n.created_at)}</div>
        </div>`).join('');
    } catch {
      list.innerHTML = '<div style="padding:20px; text-align:center; opacity:.4; font-size:13px;">Помилка завантаження</div>';
    }
  }

  window.sgNotifRead = async function(id, el) {
    el.classList.remove('unread');
    try {
      const d = await fetch('/api/notifications/read', {
        method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
        body: JSON.stringify({ id })
      }).then(r=>r.json());
      updateBadge(d.unread_count);
      lastCount = d.unread_count;
    } catch {}
  };

  markAllBtn.addEventListener('click', async () => {
    document.querySelectorAll('.notif-item.unread').forEach(e => e.classList.remove('unread'));
    try {
      const d = await fetch('/api/notifications/read', {
        method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
        body: '{}'
      }).then(r=>r.json());
      updateBadge(d.unread_count);
      lastCount = d.unread_count;
    } catch {}
  });

  bell.addEventListener('click', e => {
    e.stopPropagation();
    isOpen = !isOpen;
    panel.style.display = isOpen ? 'flex' : 'none';
    if (isOpen) loadPanel();
  });

  document.addEventListener('click', e => {
    if (isOpen && !panel.contains(e.target) && e.target !== bell) {
      isOpen = false; panel.style.display = 'none';
    }
  });

  // Initial count + poll every 30s
  fetchCount();
  setInterval(fetchCount, 30000);
})();
</script>
@endauth
