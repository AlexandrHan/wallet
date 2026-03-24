@auth
{{-- ══ Notification bell (HTML + CSS + JS) ═══════════════════════════════ --}}

{{-- Bell button + dropdown --}}
<div id="notifBellWrap" style="position:relative; display:inline-flex; margin-right:15px;">
  <button id="notifBell" aria-label="Сповіщення"
    style="background:none; border:none; cursor:pointer; padding:0; color:inherit; position:relative; line-height:1; display:inline-flex; align-items:center; justify-content:center;">
    <span style="font-size:20px; margin-top:10px;">🔔</span>
    <span id="notifBadge"
      style="display:none; position:absolute; top:1px; right:1px; min-width:15px; height:15px; border-radius:8px; background:#e53935; color:#fff; font-size:9px; font-weight:700; line-height:15px; text-align:center; padding:0 3px; pointer-events:none;"></span>
  </button>

  <div id="notifPanel"
    style="position:absolute; top:calc(100% + 8px); right:0; width:320px; max-height:440px;
           background:#021709ef; border:1px solid rgba(255,255,255,0.08); border-radius:16px;
           box-shadow:0 12px 40px rgba(0,0,0,0.7); z-index:9999; overflow:hidden;
           flex-direction:column; display:none; color:#e8eaf0;">
    <div style="padding:13px 16px 10px; display:flex; align-items:center; justify-content:space-between;
                flex-shrink:0; border-bottom:1px solid rgba(255,255,255,0.06);">
      <span style="font-weight:700; font-size:15px; letter-spacing:-0.2px;">Сповіщення</span>
      <button id="notifMarkAll"
        style="background:rgba(255,255,255,0.06); border:none; cursor:pointer; font-size:11px;
               opacity:.7; color:white; padding:4px 10px; border-radius:8px; transition:background .15s;">
        Всі прочитані
      </button>
    </div>
    <div id="notifList" style="overflow-y:auto; flex:1; min-height:60px;"></div>
    <div style="padding:9px 14px; flex-shrink:0; border-top:1px solid rgba(255,255,255,0.06); text-align:center;">
      <a href="{{ route('messages.index') }}"
         style="font-size:12px; opacity:.5; color:#e8eaf0; text-decoration:none; display:inline-flex; align-items:center; gap:5px;">
        💬 Повідомлення
      </a>
    </div>
  </div>
</div>

<style>
.notif-item { padding:11px 16px; border-bottom:1px solid rgba(255,255,255,0.05); cursor:pointer; transition:background .15s; }
.notif-item:last-child { border-bottom:none; }
.notif-item:hover { background:rgba(255,255,255,0.04); }
.notif-item.unread { background:rgba(91,141,238,0.09); border-left:2px solid #5b8dee; padding-left:14px; }
.notif-item .notif-title { font-size:13px; font-weight:600; }
.notif-item .notif-msg { font-size:12px; opacity:.6; margin-top:2px; line-height:1.4; }
.notif-item .notif-time { font-size:10px; opacity:.35; margin-top:3px; }
@media (max-width: 600px) {
  #notifPanel {
    position: fixed !important;
    top: auto !important;
    left: 50% !important;
    right: auto !important;
    transform: translateX(-50%);
    width: calc(100vw - 24px) !important;
    max-width: 400px;
    margin-top: 60px;
  }
}
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

  let isOpen = false, notifUnread = 0, chatUnread = 0;

  function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

  function timeAgo(iso) {
    const s = Math.floor((Date.now() - new Date(iso)) / 1000);
    if (s < 60)    return s + ' сек тому';
    if (s < 3600)  return Math.floor(s/60) + ' хв тому';
    if (s < 86400) return Math.floor(s/3600) + ' год тому';
    return new Date(iso).toLocaleDateString('uk-UA');
  }

  function updateBadge() {
    const total = notifUnread + chatUnread;
    if (total > 0) { badge.textContent = total > 99 ? '99+' : total; badge.style.display = 'block'; }
    else           { badge.style.display = 'none'; }
  }

  async function fetchCount() {
    try {
      const [nd, cd] = await Promise.all([
        fetch('/api/notifications/count', { headers:{'Accept':'application/json'} }).then(r=>r.json()),
        fetch('/api/messages/unread',      { headers:{'Accept':'application/json'} }).then(r=>r.json()),
      ]);
      const prevTotal = notifUnread + chatUnread;
      notifUnread = nd.unread_count ?? 0;
      chatUnread  = cd.unread_count ?? 0;
      if ((notifUnread + chatUnread) > prevTotal && prevTotal > 0) {
        try { new Audio('/sounds/moneta.mp3').play(); } catch {}
      }
      updateBadge();
    } catch {}
  }

  async function loadPanel() {
    list.innerHTML = '<div style="padding:20px; text-align:center; opacity:.4; font-size:13px;">Завантаження...</div>';
    try {
      const d = await fetch('/api/notifications?limit=25', { headers:{'Accept':'application/json'} }).then(r=>r.json());
      notifUnread = d.unread_count ?? 0;
      updateBadge();
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
      notifUnread = d.unread_count ?? 0;
      updateBadge();
    } catch {}
  };

  markAllBtn.addEventListener('click', async () => {
    document.querySelectorAll('.notif-item.unread').forEach(e => e.classList.remove('unread'));
    try {
      const d = await fetch('/api/notifications/read', {
        method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
        body: '{}'
      }).then(r=>r.json());
      notifUnread = d.unread_count ?? 0;
      updateBadge();
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

  fetchCount();

  // ── WebSocket ────────────────────────────────────────────────────────────
  const authUserId = {{ auth()->id() }};

  function waitForEcho(cb, tries = 0) {
    if (window.Echo) { cb(); return; }
    if (tries > 20)  { setInterval(fetchCount, 30000); return; }
    setTimeout(() => waitForEcho(cb, tries + 1), 300);
  }

  waitForEcho(() => {
    window.Echo
      .private(`notifications.${authUserId}`)
      .listen('.NewNotification', (e) => {
        const n = e.notification;
        if (!n) return;
        notifUnread++;
        updateBadge();
        try { new Audio('/sounds/moneta.mp3').play(); } catch {}
        if (isOpen) {
          const item = document.createElement('div');
          item.className = 'notif-item unread';
          item.setAttribute('data-id', n.id);
          item.onclick = () => window.sgNotifRead(n.id, item);
          item.innerHTML = `
            <div class="notif-title">${ICONS[n.type] ?? '🔔'} ${esc(n.title)}</div>
            <div class="notif-msg">${esc(n.message)}</div>
            <div class="notif-time">щойно</div>`;
          list.prepend(item);
        }
        if (Notification?.permission === 'granted') {
          new Notification(n.title, { body: n.message, icon: '/img/logo.png' });
        }
      });

    window.Echo
      .private(`chat.${authUserId}`)
      .listen('.NewMessage', () => {
        if (!window.location.pathname.startsWith('/messages')) {
          chatUnread++;
          updateBadge();
          try { new Audio('/sounds/moneta.mp3').play(); } catch {}
        }
      });
  });

  if ('Notification' in window && Notification.permission === 'default') {
    Notification.requestPermission().catch(() => {});
  }
})();
</script>
@endauth
