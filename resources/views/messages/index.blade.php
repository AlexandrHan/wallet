@extends('layouts.app')

@section('content')
@php
  $me = auth()->user();
  // Generate consistent avatar colour per user id
  $palette = ['#5b8dee','#7c5cbf','#2eaaa8','#e07b3f','#3fa858','#c4445a','#9b7840','#4a7fa5'];
@endphp

{{-- ══════════════════════════════════════════════
     STYLES
══════════════════════════════════════════════ --}}
<style>
/* ── Reset / scope ─────────────────────────────── */
#msgApp *, #msgApp *::before, #msgApp *::after { box-sizing: border-box; }
#msgApp { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif; }

/* Keep site header above the fixed #msgApp overlay */
header { position: relative; z-index: 200; }

/* ── Layout shell ──────────────────────────────── */
#msgApp {
  position: fixed;
  inset: 0;
  display: flex;
  flex-direction: column;
  background: #0d0f14;
  color: #e8eaf0;
}

/* ── Top bar ───────────────────────────────────── */
#msgTopBar {
  display: flex;
  align-items: center;
  margin-top: 5rem;
  gap: 10px;
  padding: max(env(safe-area-inset-top, 12px), 12px) 16px 12px;
  background: rgba(17,19,26,0.96);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border-bottom: 1px solid rgba(255,255,255,0.06);
  flex-shrink: 0;

}

#msgBackBtn {
  display: flex; align-items: center; justify-content: center;
  width: 36px; height: 36px; border-radius: 50%;
  background: rgba(255,255,255,0.07);
  color: inherit; text-decoration: none; font-size: 18px;
  transition: background .15s;
  flex-shrink: 0;
}
#msgBackBtn:hover { background: rgba(255,255,255,0.12); }

#msgTitle {
  flex: 1;
  font-weight: 700;
  font-size: 17px;
  letter-spacing: -0.2px;
}

#msgOnline {
  font-size: 12px;
  color: #4caf7d;
  opacity: 0;
  transition: opacity .3s;
}
#msgOnline.visible { opacity: 1; }

/* ── Body wrapper ──────────────────────────────── */
#msgBody {
  flex: 1;
  min-height: 0;
  display: flex;
  overflow: hidden;
}

/* ── Sidebar ───────────────────────────────────── */
#msgSidebar {
  width: 260px;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  border-right: 1px solid rgba(255,255,255,0.06);
  background: #0d0f14;
  overflow: hidden;
}

/* Search box inside sidebar */
#msgSearch {
  margin: 10px 12px 8px;
  flex-shrink: 0;
}
#msgSearchInput {
  width: 100%;
  background: rgba(255,255,255,0.06);
  border: 1px solid rgba(255,255,255,0.09);
  border-radius: 12px;
  padding: 8px 14px 8px 36px;
  color: inherit;
  font-size: 13px;
  outline: none;
  transition: border-color .2s, background .2s;
}
#msgSearchInput:focus {
  border-color: rgba(100,160,255,0.4);
  background: rgba(255,255,255,0.09);
}
#msgSearchWrap {
  position: relative;
}
#msgSearchIcon {
  position: absolute; left: 11px; top: 50%; transform: translateY(-50%);
  font-size: 14px; opacity: .35; pointer-events: none;
}

/* Contact list scroll area */
#msgContactList {
  flex: 1;
  overflow-y: auto;
  overscroll-behavior: contain;
}
#msgContactList::-webkit-scrollbar { width: 3px; }
#msgContactList::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 2px; }

/* Contact row */
.msg-contact {
  display: flex;
  align-items: center;
  gap: 11px;
  padding: 10px 14px;
  cursor: pointer;
  border-bottom: 1px solid rgba(255,255,255,0.04);
  transition: background .15s;
  position: relative;
}
.msg-contact:hover { background: rgba(255,255,255,0.04); }
.msg-contact.active {
  background: rgba(91,141,238,0.12);
  border-left: 3px solid #5b8dee;
}
.msg-contact.active .msg-c-avatar { margin-left: -3px; }

/* Avatar */
.msg-c-avatar {
  width: 40px; height: 40px;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 15px; font-weight: 700;
  flex-shrink: 0;
  color: rgba(255,255,255,0.9);
  letter-spacing: -0.5px;
}

/* Contact text */
.msg-c-info { flex: 1; min-width: 0; }
.msg-c-name {
  font-size: 13.5px; font-weight: 600;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  display: flex; align-items: center; gap: 6px;
}
.msg-c-preview {
  font-size: 12px; color: rgba(255,255,255,0.38);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  margin-top: 2px;
}
.msg-contact.has-unread .msg-c-preview { color: rgba(255,255,255,0.6); }

/* Unread badge */
.msg-c-badge {
  background: #5b8dee;
  color: #fff;
  border-radius: 10px;
  font-size: 10px; font-weight: 700;
  padding: 1px 6px;
  min-width: 18px; text-align: center;
  flex-shrink: 0;
}

/* Timestamp */
.msg-c-time {
  font-size: 11px; color: rgba(255,255,255,0.28);
  align-self: flex-start; margin-top: 1px;
  white-space: nowrap;
}

/* Empty contacts */
.msg-contacts-empty {
  padding: 40px 20px;
  text-align: center;
  color: rgba(255,255,255,0.25);
  font-size: 13px;
  line-height: 1.6;
}

/* ── Chat panel ─────────────────────────────────── */
#msgPanel {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  background: #0d0f14;
  position: relative;
}

/* Chat header */
#msgChatHeader {
  display: none;
  align-items: center;
  gap: 11px;
  padding: 10px 16px;
  background: rgba(14,16,22,0.95);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border-bottom: 1px solid rgba(255,255,255,0.06);
  flex-shrink: 0;
}
#msgChatAvatar {
  width: 36px; height: 36px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 14px; font-weight: 700; flex-shrink: 0;
  color: rgba(255,255,255,0.9);
}
#msgChatName {
  flex: 1; font-size: 15px; font-weight: 700; letter-spacing: -0.1px;
}
#msgChatStatus {
  font-size: 11.5px; color: rgba(255,255,255,0.35); font-weight: 400;
}

/* Empty state */
#msgEmpty {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  color: rgba(255,255,255,0.2);
  padding: 32px;
  text-align: center;
}
.msg-empty-icon {
  font-size: 52px;
  opacity: .15;
  line-height: 1;
}
.msg-empty-text {
  font-size: 14px;
  line-height: 1.6;
}

/* Messages area */
#msgHistory {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: 16px 14px 8px;
  display: none;
  flex-direction: column;
  gap: 3px;
  overscroll-behavior: contain;
  scroll-behavior: smooth;
}
#msgHistory::-webkit-scrollbar { width: 3px; }
#msgHistory::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 2px; }

/* Message groups */
.msg-mine-wrap  { align-self: flex-end;  display: flex; flex-direction: column; align-items: flex-end;   max-width: 75%; }
.msg-their-wrap { align-self: flex-start; display: flex; flex-direction: column; align-items: flex-start; max-width: 75%; }

/* animate in */
.msg-mine-wrap, .msg-their-wrap {
  animation: msgSlideIn .18s ease-out both;
}
@keyframes msgSlideIn {
  from { opacity: 0; transform: translateY(6px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* Bubbles */
.msg-bubble {
  padding: 9px 13px;
  font-size: 14px;
  line-height: 1.55;
  word-break: break-word;
  white-space: pre-wrap;
  position: relative;
}
.msg-bubble.mine {
  background: #2a5298;
  background: linear-gradient(135deg, #2f5fbd 0%, #1e43a0 100%);
  border-radius: 18px 18px 4px 18px;
  color: #e8f0ff;
  box-shadow: 0 2px 8px rgba(30,67,160,0.35);
}
.msg-bubble.theirs {
  background: #1e2130;
  border-radius: 18px 18px 18px 4px;
  color: #dde1ed;
  box-shadow: 0 2px 6px rgba(0,0,0,0.25);
}

/* Consecutive message tightening */
.msg-mine-wrap + .msg-mine-wrap   { margin-top: -1px; }
.msg-their-wrap + .msg-their-wrap { margin-top: -1px; }
.msg-mine-wrap + .msg-their-wrap,
.msg-their-wrap + .msg-mine-wrap  { margin-top: 8px; }

/* Timestamp */
.msg-time {
  font-size: 10.5px;
  color: rgba(255,255,255,0.25);
  margin-top: 3px;
  padding: 0 4px;
}

/* Date divider */
.msg-date-divider {
  align-self: center;
  font-size: 11px;
  color: rgba(255,255,255,0.25);
  background: rgba(255,255,255,0.05);
  border-radius: 20px;
  padding: 3px 12px;
  margin: 10px 0 6px;
  letter-spacing: 0.3px;
}

/* Jump to bottom button */
#msgJumpBtn {
  position: absolute;
  bottom: 76px;
  right: 16px;
  width: 36px; height: 36px;
  border-radius: 50%;
  background: #1e2130;
  border: 1px solid rgba(255,255,255,0.12);
  box-shadow: 0 3px 12px rgba(0,0,0,0.4);
  display: none;
  align-items: center; justify-content: center;
  cursor: pointer;
  font-size: 16px;
  color: rgba(255,255,255,0.7);
  transition: transform .15s, background .15s;
  z-index: 5;
}
#msgJumpBtn:hover { background: #252840; transform: scale(1.08); }
#msgJumpBtn.visible { display: flex; }
#msgJumpUnread {
  position: absolute; top: -5px; right: -5px;
  background: #5b8dee; color: #fff;
  font-size: 9px; font-weight: 700;
  border-radius: 8px; padding: 1px 4px;
  min-width: 16px; text-align: center;
  display: none;
}

/* ── Composer ───────────────────────────────────── */
#msgInput {
  display: none;
  padding: 8px 12px max(env(safe-area-inset-bottom, 10px), 10px);
  background: rgba(14,16,22,0.97);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border-top: 1px solid rgba(255,255,255,0.06);
  gap: 8px;
  align-items: flex-end;
  flex-shrink: 0;
}

#msgText {
  flex: 1;
  background: rgba(255,255,255,0.06);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 20px;
  padding: 10px 16px;
  color: #e8eaf0;
  font-size: 15px;
  resize: none;
  max-height: 120px;
  overflow-y: auto;
  line-height: 1.45;
  outline: none;
  transition: border-color .2s, background .2s;
  font-family: inherit;
}
#msgText::placeholder { color: rgba(255,255,255,0.25); }
#msgText:focus {
  border-color: rgba(91,141,238,0.5);
  background: rgba(255,255,255,0.08);
}

#msgSend {
  width: 44px; height: 44px;
  border-radius: 50%;
  background: #2f5fbd;
  border: none;
  cursor: pointer;
  font-size: 18px;
  flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  color: #fff;
  box-shadow: 0 2px 10px rgba(47,95,189,0.45);
  transition: transform .12s, background .15s, box-shadow .15s;
}
#msgSend:hover  { background: #3a6fd4; transform: scale(1.06); }
#msgSend:active { transform: scale(0.94); }

/* ── Desktop: centered container ───────────────── */
@media (min-width: 601px) {
  #msgTopBar, #msgBody {
    max-width: 960px;
    width: 100%;
    align-self: center;
  }
}

@media (max-width: 600px) {
  #msgTopBar, #msgBody {
    z-index: 10;
  }
}

/* ── Mobile: sidebar hidden, panel full width ───── */
@media (max-width: 600px) {
  #msgSidebar {
    width: 100%;
    border-right: none;
    position: absolute; top: 50px; left: 0; right: 0; bottom: 0; z-index: 20;
    background: #0d0f14;
    transition: transform .22s cubic-bezier(.4,0,.2,1);
    margin-top: 5rem;
  }
  #msgSidebar.slide-out {
    transform: translateX(-100%);
    pointer-events: none;
  }
  #msgPanel { width: 100%; }
  #msgChatHeader { display: flex; }
  #msgMobileBack {
    display: flex; align-items: center; justify-content: center;
    width: 34px; height: 34px; border-radius: 50%;
    background: rgba(255,255,255,0.07);
    color: inherit; cursor: pointer; border: none;
    font-size: 18px; flex-shrink: 0;
    transition: background .15s;
  }
  #msgMobileBack:hover { background: rgba(255,255,255,0.12); }
  #msgJumpBtn { bottom: 82px; }
}
@media (min-width: 601px) {
  #msgChatHeader { display: none !important; }
  #msgMobileBack { display: none !important; }
}
</style>

{{-- ══════════════════════════════════════════════
     HTML
══════════════════════════════════════════════ --}}
<div id="msgApp">

  {{-- Top bar (desktop only — on mobile each panel has its own header) --}}
  <div id="msgTopBar">
    <a id="msgBackBtn" href="{{ url('/') }}" aria-label="Назад">←</a>
    <div id="msgTitle">💬 Повідомлення</div>
    <div id="msgOnline"></div>
  </div>

  {{-- Body --}}
  <div id="msgBody">

    {{-- ── Sidebar ───────────────────────── --}}
    <div id="msgSidebar">
      {{-- Search --}}
      <div id="msgSearch">
        <div id="msgSearchWrap">
          <span id="msgSearchIcon">🔍</span>
          <input id="msgSearchInput" type="search" placeholder="Пошук..." autocomplete="off">
        </div>
      </div>

      {{-- Contact list --}}
      <div id="msgContactList">
        @forelse($users as $u)
        @php
          $avatarBg  = $palette[$u->id % count($palette)];
          $initials  = collect(explode(' ', trim($u->name)))->take(2)->map(fn($w)=>mb_substr($w,0,1))->join('');
          $timeLabel = $u->last_at ? \Carbon\Carbon::parse($u->last_at)->diffForHumans(null, true, true) : '';
        @endphp
        <div class="msg-contact {{ $u->unread_count > 0 ? 'has-unread' : '' }}"
             data-uid="{{ $u->id }}"
             data-name="{{ $u->name }}"
             data-avatar-bg="{{ $avatarBg }}"
             data-initials="{{ $initials }}"
             onclick="openChat({{ $u->id }}, '{{ addslashes($u->name) }}', '{{ $avatarBg }}', '{{ $initials }}')"
             role="button" tabindex="0">
          <div class="msg-c-avatar" style="background:{{ $avatarBg }}20; color:{{ $avatarBg }}; border: 1.5px solid {{ $avatarBg }}40;">
            {{ $initials }}
          </div>
          <div class="msg-c-info">
            <div class="msg-c-name">
              {{ $u->name }}
              @if($u->unread_count > 0)
              <span class="msg-c-badge">{{ $u->unread_count }}</span>
              @endif
            </div>
            @if($u->last_message)
            <div class="msg-c-preview">{{ $u->last_message }}</div>
            @else
            <div class="msg-c-preview" style="font-style:italic; opacity:.5;">Немає повідомлень</div>
            @endif
          </div>
          @if($timeLabel)
          <div class="msg-c-time">{{ $timeLabel }}</div>
          @endif
        </div>
        @empty
        <div class="msg-contacts-empty">
          <div style="font-size:36px; margin-bottom:8px;">👥</div>
          Немає інших<br>користувачів
        </div>
        @endforelse
      </div>
    </div>

    {{-- ── Chat panel ────────────────────── --}}
    <div id="msgPanel">
      {{-- Mobile chat header (shown only on mobile) --}}
      <div id="msgChatHeader">
        <button id="msgMobileBack" onclick="closeMobileChat()" aria-label="Назад">←</button>
        <div id="msgChatAvatar" style="background:rgba(255,255,255,0.07);"></div>
        <div>
          <div id="msgChatName"></div>
        </div>
      </div>

      {{-- Empty state --}}
      <div id="msgEmpty" style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center;">
        <div class="msg-empty-icon">💬</div>
        <div class="msg-empty-text">Виберіть співробітника<br>для спілкування</div>
      </div>

      {{-- Messages --}}
      <div id="msgHistory"></div>

      {{-- Jump to bottom --}}
      <button id="msgJumpBtn" onclick="jumpToBottom()" aria-label="До останнього">↓<span id="msgJumpUnread"></span></button>

      {{-- Composer --}}
      <div id="msgInput">
        <textarea id="msgText" rows="1" placeholder="Написати повідомлення…" autocomplete="off"></textarea>
        <button id="msgSend" aria-label="Надіслати">➤</button>
      </div>
    </div>

  </div>{{-- /msgBody --}}
</div>{{-- /msgApp --}}


{{-- ══════════════════════════════════════════════
     JAVASCRIPT  (all existing logic preserved)
══════════════════════════════════════════════ --}}
<script>
(function () {
  const meId   = {{ $me->id }};
  const csrf   = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
  let currentUid  = null;
  let echoChannel = null;
  let unreadBelow = 0;

  /* ── utils ──────────────────────────────────────────── */
  function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  function timeStr(iso) {
    const d = new Date(iso);
    return d.getHours().toString().padStart(2,'0') + ':' + d.getMinutes().toString().padStart(2,'0');
  }

  function isAtBottom(el, threshold = 80) {
    return el.scrollHeight - el.scrollTop - el.clientHeight < threshold;
  }

  /* ── jump button ────────────────────────────────────── */
  const jumpBtn      = document.getElementById('msgJumpBtn');
  const jumpUnread   = document.getElementById('msgJumpUnread');
  const historyEl    = document.getElementById('msgHistory');

  function updateJumpBtn() {
    if (!historyEl.offsetParent) return;
    if (!isAtBottom(historyEl, 120)) {
      jumpBtn.classList.add('visible');
      if (unreadBelow > 0) {
        jumpUnread.textContent = unreadBelow;
        jumpUnread.style.display = 'block';
      }
    } else {
      jumpBtn.classList.remove('visible');
      unreadBelow = 0;
      jumpUnread.style.display = 'none';
    }
  }

  window.jumpToBottom = function () {
    historyEl.scrollTo({ top: historyEl.scrollHeight, behavior: 'smooth' });
    unreadBelow = 0;
    jumpUnread.style.display = 'none';
  };
  historyEl.addEventListener('scroll', updateJumpBtn, { passive: true });

  /* ── mobile sidebar ─────────────────────────────────── */
  window.closeMobileChat = function () {
    document.getElementById('msgSidebar').classList.remove('slide-out');
    currentUid = null;
    document.getElementById('msgHistory').style.display = 'none';
    document.getElementById('msgInput').style.display   = 'none';
    document.getElementById('msgEmpty').style.display   = 'flex';
    document.getElementById('msgChatHeader').style.display = 'none';
    document.getElementById('msgTitle').textContent = '💬 Повідомлення';
  };

  /* ── search filter ──────────────────────────────────── */
  document.getElementById('msgSearchInput').addEventListener('input', function () {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.msg-contact').forEach(el => {
      const name = (el.dataset.name || '').toLowerCase();
      el.style.display = name.includes(q) ? '' : 'none';
    });
  });

  /* ── open conversation ──────────────────────────────── */
  window.openChat = function (uid, name, avatarBg, initials) {
    currentUid = uid;
    unreadBelow = 0;

    // Sidebar active state
    document.querySelectorAll('.msg-contact').forEach(el => el.classList.remove('active'));
    document.querySelector(`.msg-contact[data-uid="${uid}"]`)?.classList.add('active');

    // Desktop title
    document.getElementById('msgTitle').textContent = '💬 ' + name;

    // Mobile: slide sidebar out, show chat header
    if (window.innerWidth <= 600) {
      document.getElementById('msgSidebar').classList.add('slide-out');
      const chatHeader = document.getElementById('msgChatHeader');
      chatHeader.style.display = 'flex';
      document.getElementById('msgChatName').textContent = name;
      const chatAvatar = document.getElementById('msgChatAvatar');
      chatAvatar.style.background = (avatarBg ?? '#5b8dee') + '20';
      chatAvatar.style.color      = avatarBg ?? '#5b8dee';
      chatAvatar.style.border     = '1.5px solid ' + (avatarBg ?? '#5b8dee') + '40';
      chatAvatar.textContent      = initials ?? name.charAt(0);
    }

    // Show chat UI
    document.getElementById('msgEmpty').style.display   = 'none';
    document.getElementById('msgHistory').style.display = 'flex';
    document.getElementById('msgInput').style.display   = 'flex';

    loadHistory();
    subscribeToChat(uid);
    document.getElementById('msgText').focus();
  };

  /* ── load history ───────────────────────────────────── */
  async function loadHistory() {
    if (!currentUid) return;
    try {
      const d = await fetch(`/api/messages/${currentUid}`, {
        headers: { 'Accept': 'application/json' }
      }).then(r => r.json());
      renderHistory(d.messages);
      // Remove unread badge from sidebar row
      const row = document.querySelector(`.msg-contact[data-uid="${currentUid}"]`);
      row?.querySelector('.msg-c-badge')?.remove();
      row?.classList.remove('has-unread');
    } catch {}
  }

  /* ── render full history ────────────────────────────── */
  function renderHistory(msgs) {
    const h = document.getElementById('msgHistory');
    if (!msgs || msgs.length === 0) {
      h.innerHTML = '<div style="align-self:center; margin:auto; color:rgba(255,255,255,0.2); font-size:13px; text-align:center; padding:32px 0;">Повідомлень ще немає.<br>Будьте першим!</div>';
      return;
    }

    let lastDate = null;
    const html = msgs.map(m => {
      const mine  = m.from_user_id === meId;
      const mDate = new Date(m.created_at).toLocaleDateString('uk-UA', { day:'numeric', month:'long' });
      let divider = '';
      if (mDate !== lastDate) {
        divider = `<div class="msg-date-divider">${mDate}</div>`;
        lastDate = mDate;
      }
      return divider + `<div class="${mine ? 'msg-mine-wrap' : 'msg-their-wrap'}">
        <div class="msg-bubble ${mine ? 'mine' : 'theirs'}">${esc(m.message).replace(/\n/g,'<br>')}</div>
        <div class="msg-time" style="text-align:${mine ? 'right' : 'left'}">${timeStr(m.created_at)}</div>
      </div>`;
    }).join('');

    h.innerHTML = html;
    requestAnimationFrame(() => { h.scrollTop = h.scrollHeight; });
    updateJumpBtn();
  }

  /* ── append single message ──────────────────────────── */
  function appendMessage(m, mine) {
    const h        = document.getElementById('msgHistory');
    const atBottom = isAtBottom(h);

    const div = document.createElement('div');
    div.className = mine ? 'msg-mine-wrap' : 'msg-their-wrap';
    div.innerHTML = `
      <div class="msg-bubble ${mine ? 'mine' : 'theirs'}">${esc(m.message).replace(/\n/g,'<br>')}</div>
      <div class="msg-time" style="text-align:${mine ? 'right' : 'left'}">${timeStr(m.created_at)}</div>`;
    h.appendChild(div);

    if (atBottom) {
      requestAnimationFrame(() => { h.scrollTop = h.scrollHeight; });
    } else if (!mine) {
      unreadBelow++;
      updateJumpBtn();
    }
  }

  /* ── send message ───────────────────────────────────── */
  async function sendMsg() {
    const input = document.getElementById('msgText');
    const text  = input.value.trim();
    if (!text || !currentUid) return;
    input.value = '';
    input.style.height = 'auto';

    appendMessage({ message: text, created_at: new Date().toISOString(), from_user_id: meId }, true);

    try {
      await fetch('/api/messages', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        body:    JSON.stringify({ to_user_id: currentUid, message: text })
      });
    } catch {}
  }

  /* ── WebSocket subscription ─────────────────────────── */
  function subscribeToChat(uid) {
    if (echoChannel) echoChannel.stopListening('.NewMessage');
    if (!window.Echo) return;

    echoChannel = window.Echo.private(`chat.${meId}`);
    echoChannel.listen('.NewMessage', (e) => {
      const m = e.message;
      if (!m) return;
      if (m.from_user_id === currentUid) {
        appendMessage(m, false);
      } else {
        // badge on the sidebar contact row
        const row = document.querySelector(`.msg-contact[data-uid="${m.from_user_id}"]`);
        if (row) {
          let badge = row.querySelector('.msg-c-badge');
          if (!badge) {
            badge = document.createElement('span');
            badge.className = 'msg-c-badge';
            row.querySelector('.msg-c-name')?.appendChild(badge);
          }
          badge.textContent = parseInt(badge.textContent || '0') + 1;
          row.classList.add('has-unread');
        }
      }
      try { new Audio('/sounds/moneta.mp3').play(); } catch {}
    });
  }

  /* ── event listeners ────────────────────────────────── */
  document.getElementById('msgSend').addEventListener('click', sendMsg);

  document.getElementById('msgText').addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMsg(); }
  });

  document.getElementById('msgText').addEventListener('input', function () {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
  });

})();
</script>

@endsection
