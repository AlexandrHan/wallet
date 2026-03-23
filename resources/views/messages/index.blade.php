@extends('layouts.app')

@section('content')

@php $me = auth()->user(); @endphp

<div id="msgApp" style="position:fixed; top:0; left:0; right:0; bottom:0; display:flex; flex-direction:column; background:var(--app-bg,#0b0d10);">

  {{-- Header --}}
  <div style="padding:14px 16px 10px; border-bottom:1px solid rgba(255,255,255,0.07); display:flex; align-items:center; gap:12px; flex-shrink:0; padding-top:max(14px,env(safe-area-inset-top,14px));">
    <a href="{{ url('/') }}" style="color:inherit; font-size:20px; text-decoration:none;">←</a>
    <div id="msgTitle" style="font-weight:700; font-size:16px; flex:1;">💬 Повідомлення</div>
    <div id="msgOnline" style="font-size:12px; opacity:.5;"></div>
  </div>

  {{-- Body: sidebar + chat --}}
  <div style="flex:1; min-height:0; display:flex;">

    {{-- Contacts list --}}
    <div id="msgSidebar" style="width:220px; flex-shrink:0; border-right:1px solid rgba(255,255,255,0.07); overflow-y:auto;">
      @foreach($users as $u)
      <div class="msg-contact {{ $u->unread_count > 0 ? 'has-unread' : '' }}"
           data-uid="{{ $u->id }}" data-name="{{ $u->name }}"
           onclick="openChat({{ $u->id }}, '{{ addslashes($u->name) }}')"
           style="padding:11px 14px; cursor:pointer; border-bottom:1px solid rgba(255,255,255,0.05); transition:background .15s;">
        <div style="display:flex; align-items:center; gap:8px;">
          <div style="width:34px; height:34px; border-radius:50%; background:rgba(255,255,255,0.1); display:flex; align-items:center; justify-content:center; font-size:14px; flex-shrink:0;">
            {{ mb_substr($u->name, 0, 1) }}
          </div>
          <div style="flex:1; min-width:0;">
            <div style="font-size:13px; font-weight:600; display:flex; align-items:center; gap:5px;">
              {{ $u->name }}
              @if($u->unread_count > 0)
              <span style="background:#e53935; color:#fff; border-radius:8px; font-size:9px; font-weight:700; padding:1px 5px;">{{ $u->unread_count }}</span>
              @endif
            </div>
            @if($u->last_message)
            <div style="font-size:11px; opacity:.5; overflow:hidden; white-space:nowrap; text-overflow:ellipsis; max-width:140px;">{{ $u->last_message }}</div>
            @endif
          </div>
        </div>
      </div>
      @endforeach
      @if($users->isEmpty())
      <div style="padding:24px; text-align:center; opacity:.4; font-size:13px;">Немає інших користувачів</div>
      @endif
    </div>

    {{-- Chat area --}}
    <div style="flex:1; min-width:0; display:flex; flex-direction:column;">
      {{-- Empty state --}}
      <div id="msgEmpty" style="flex:1; display:flex; align-items:center; justify-content:center; opacity:.35; font-size:14px;">
        Виберіть співробітника для спілкування
      </div>

      {{-- Messages --}}
      <div id="msgHistory" style="flex:1; min-height:0; overflow-y:auto; padding:12px 16px; display:none; flex-direction:column; gap:8px;"></div>

      {{-- Input --}}
      <div id="msgInput" style="display:none; padding:8px 12px; border-top:1px solid rgba(255,255,255,0.07); gap:8px; align-items:flex-end;">
        <textarea id="msgText" rows="1" placeholder="Написати повідомлення..."
          style="flex:1; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.15); border-radius:14px; padding:9px 13px; color:inherit; font-size:14px; resize:none; max-height:100px; overflow-y:auto; line-height:1.4;"></textarea>
        <button id="msgSend"
          style="width:40px; height:40px; border-radius:50%; background:rgba(255,255,255,0.15); border:none; cursor:pointer; font-size:17px; flex-shrink:0; display:flex; align-items:center; justify-content:center;">
          ➤
        </button>
      </div>
    </div>
  </div>
</div>

<style>
.msg-contact:hover { background: rgba(255,255,255,0.04); }
.msg-contact.active { background: rgba(100,180,255,0.1); }
.msg-contact.has-unread .msg-name { font-weight: 800; }
.msg-bubble { max-width: 72%; padding: 9px 13px; border-radius: 14px; font-size: 14px; line-height: 1.5; word-break: break-word; white-space: pre-wrap; }
.msg-bubble.mine  { align-self:flex-end;  background:rgba(100,180,255,0.2); border-bottom-right-radius:4px; }
.msg-bubble.theirs { align-self:flex-start; background:rgba(255,255,255,0.07); border-bottom-left-radius:4px; }
.msg-time { font-size:10px; opacity:.35; margin-top:2px; padding:0 4px; }
.msg-mine-wrap  { align-self:flex-end;  display:flex; flex-direction:column; align-items:flex-end; }
.msg-their-wrap { align-self:flex-start; display:flex; flex-direction:column; align-items:flex-start; }
#msgInput { display: flex; }
</style>

<script>
(function () {
  const meId    = {{ $me->id }};
  const csrf    = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
  let currentUid = null;
  let pollTimer  = null;

  function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

  function timeStr(iso) {
    const d = new Date(iso);
    return d.getHours().toString().padStart(2,'0') + ':' + d.getMinutes().toString().padStart(2,'0');
  }

  window.openChat = function(uid, name) {
    currentUid = uid;
    document.querySelectorAll('.msg-contact').forEach(el => el.classList.remove('active'));
    document.querySelector(`.msg-contact[data-uid="${uid}"]`)?.classList.add('active');
    document.getElementById('msgTitle').textContent = '💬 ' + name;
    document.getElementById('msgEmpty').style.display   = 'none';
    document.getElementById('msgHistory').style.display = 'flex';
    document.getElementById('msgInput').style.display   = 'flex';
    loadHistory();
    clearInterval(pollTimer);
    pollTimer = setInterval(loadHistory, 5000);
    document.getElementById('msgText').focus();
  };

  async function loadHistory() {
    if (!currentUid) return;
    try {
      const d = await fetch(`/api/messages/${currentUid}`, { headers:{'Accept':'application/json'} }).then(r=>r.json());
      renderHistory(d.messages);
      // Clear unread badge in sidebar
      const el = document.querySelector(`.msg-contact[data-uid="${currentUid}"] span[style*="background:#e53935"]`);
      if (el) el.remove();
    } catch {}
  }

  function renderHistory(msgs) {
    const h = document.getElementById('msgHistory');
    const wasAtBottom = h.scrollHeight - h.scrollTop - h.clientHeight < 80;
    h.innerHTML = msgs.map(m => {
      const mine = m.from_user_id === meId;
      return `<div class="${mine ? 'msg-mine-wrap' : 'msg-their-wrap'}">
        <div class="msg-bubble ${mine ? 'mine' : 'theirs'}">${esc(m.message).replace(/\n/g,'<br>')}</div>
        <div class="msg-time" style="text-align:${mine ? 'right' : 'left'}">${timeStr(m.created_at)}</div>
      </div>`;
    }).join('');
    if (wasAtBottom || h.innerHTML) {
      requestAnimationFrame(() => { h.scrollTop = h.scrollHeight; });
    }
  }

  async function sendMsg() {
    const text = document.getElementById('msgText').value.trim();
    if (!text || !currentUid) return;
    document.getElementById('msgText').value = '';
    document.getElementById('msgText').style.height = 'auto';
    try {
      await fetch('/api/messages', {
        method:'POST',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
        body: JSON.stringify({ to_user_id: currentUid, message: text })
      });
      loadHistory();
    } catch {}
  }

  document.getElementById('msgSend').addEventListener('click', sendMsg);
  document.getElementById('msgText').addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMsg(); }
  });
  document.getElementById('msgText').addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 100) + 'px';
  });
})();
</script>

@endsection
