@push('styles')
<link rel="stylesheet" href="/css/nav-telegram.css?v={{ filemtime(public_path('css/nav-telegram.css')) }}">
@endpush

@extends('layouts.app')

@section('content')

{{-- Messages area --}}
<main id="aiMain" style="position:fixed; top:8rem; bottom:110px; left:50%; transform:translateX(-50%); width:100%; max-width:680px; padding:0; display:flex; flex-direction:column; overflow:hidden;">

  {{-- Header --}}
  <div style="padding:14px 16px 10px; flex-shrink:0;">
    <div style="font-weight:700; font-size:17px; text-align:center;">🤖 AI Фінансовий аналітик</div>
  </div>

  {{-- Suggested questions --}}
  <div id="aiSuggestions" style="padding:0 12px 10px; flex-shrink:0; display:flex; justify-content:center; gap:8px; flex-wrap:wrap;">
    @foreach([
      'Який прогноз витрат на наступний місяць?',
      'Чи вистачить грошей на всі активні проекти?',
      'Які найбільші витрати цього місяця?',
      'Чи буде касовий розрив?',
      'Скільки обладнання не вистачає?',
    ] as $q)
    <button class="btn ai-suggestion" data-q="{{ $q }}"
      style="font-size:12px; padding:5px 12px; border-radius:20px; opacity:.7; white-space:nowrap;">
      {{ $q }}
    </button>
    @endforeach
  </div>

  {{-- Chat messages --}}
  <div id="aiMessages" style="flex:1; overflow-y:auto; padding:0 12px 12px; display:flex; flex-direction:column; gap:10px;">
    <div class="ai-msg ai-msg--bot" style="align-self:flex-start;">
      <div class="ai-bubble">
        Привіт! Я фінансовий аналітик SolarGlass. Маю доступ до балансів, витрат, проектів та залишків обладнання. Що вас цікавить?
      </div>
    </div>
  </div>

</main>

{{-- Input bar — fixed at bottom, replaces tg-fab on mobile --}}
<div id="aiInputBar" style="position:fixed; bottom:0; left:50%; transform:translateX(-50%); width:100%; max-width:680px; z-index:200; background:var(--app-bg,#0b0d10); border-top:1px solid rgba(255,255,255,0.07); padding:8px 12px max(14px,env(safe-area-inset-bottom,14px));">
  {{-- Model selector --}}
  <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
    <label for="aiModel" style="font-size:12px; opacity:.5; white-space:nowrap;">Модель:</label>
    <select id="aiModel" style="flex:1; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.15); border-radius:10px; padding:5px 10px; color:inherit; font-size:13px; cursor:pointer;">
      <option value="local">⚡ Local AI (швидко, безкоштовно)</option>
      <option value="claude">🧠 Claude (глибокий аналіз)</option>
    </select>
  </div>
  {{-- Textarea + send --}}
  <div style="display:flex; gap:8px; align-items:flex-end;">
    <textarea
      id="aiInput"
      rows="1"
      placeholder="Задайте питання..."
      style="flex:1; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.15); border-radius:16px; padding:10px 14px; color:inherit; font-size:15px; resize:none; max-height:120px; overflow-y:auto; line-height:1.4;"
    ></textarea>
    <button id="aiSend"
      style="width:44px; height:44px; border-radius:50%; background:rgba(255,255,255,0.15); border:none; cursor:pointer; font-size:18px; flex-shrink:0; display:flex; align-items:center; justify-content:center;"
      aria-label="Надіслати">
      ➤
    </button>
  </div>
</div>

{{-- Include nav for menu overlay only — the nav bar itself is hidden below --}}
@include('partials.nav.bottom')

<style>
/* AI chat page: hide bottom nav bar, keep menu overlay functional */
.tg-bottom-nav,
.tg-bottom-nav--project-owner { display: none !important; }
</style>

<style>
.ai-msg {
  max-width: 85%;
  display: flex;
  flex-direction: column;
}
.ai-msg--user { align-self: flex-end; }
.ai-msg--bot  { align-self: flex-start; }
.ai-bubble {
  padding: 10px 14px;
  border-radius: 16px;
  font-size: 14px;
  line-height: 1.5;
  white-space: pre-wrap;
  word-break: break-word;
}
.ai-msg--user .ai-bubble {
  background: rgba(100, 180, 255, 0.2);
  border-bottom-right-radius: 4px;
}
.ai-msg--bot .ai-bubble {
  background: rgba(255, 255, 255, 0.07);
  border-bottom-left-radius: 4px;
}
.ai-msg--bot.ai-thinking .ai-bubble { opacity: .5; }
.ai-time {
  font-size: 11px;
  opacity: .35;
  margin-top: 3px;
  padding: 0 4px;
}
.ai-model-badge {
  font-size: 11px;
  opacity: .45;
  margin-bottom: 3px;
  padding: 0 4px;
}
.ai-msg--user .ai-time { text-align: right; }
.ai-suggestion { transition: opacity .15s; }
.ai-suggestion:hover { opacity: 1 !important; }
#aiModel option { background: #1e2535; }
</style>

<script>
(function () {
  const messagesEl = document.getElementById('aiMessages');
  const inputEl    = document.getElementById('aiInput');
  const sendBtn    = document.getElementById('aiSend');
  const modelEl    = document.getElementById('aiModel');
  const csrfToken  = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

  let thinking = false;

  const MODEL_LABELS = { local: '⚡ Local AI', claude: '🧠 Claude', quick: '⚡ Швидка відповідь', 'local-fallback': '⚡ Local AI', 'sql-agent': '🗄 SQL агент' };

  function scrollBottom() { messagesEl.scrollTop = messagesEl.scrollHeight; }

  function timeStr() {
    const d = new Date();
    return d.getHours().toString().padStart(2,'0') + ':' + d.getMinutes().toString().padStart(2,'0');
  }

  function addMessage(text, role) {
    const wrap   = document.createElement('div');
    wrap.className = 'ai-msg ai-msg--' + role;
    const bubble = document.createElement('div');
    bubble.className = 'ai-bubble';
    bubble.textContent = text;
    const time   = document.createElement('div');
    time.className = 'ai-time';
    time.textContent = timeStr();
    wrap.appendChild(bubble);
    wrap.appendChild(time);
    messagesEl.appendChild(wrap);
    scrollBottom();
    return wrap;
  }

  function addBotMessage(text, modelUsed) {
    const wrap = document.createElement('div');
    wrap.className = 'ai-msg ai-msg--bot';

    if (modelUsed && MODEL_LABELS[modelUsed]) {
      const badge = document.createElement('div');
      badge.className = 'ai-model-badge';
      badge.textContent = MODEL_LABELS[modelUsed];
      wrap.appendChild(badge);
    }

    const bubble = document.createElement('div');
    bubble.className = 'ai-bubble';
    // Simple markdown: **bold** and newlines
    bubble.innerHTML = text
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
      .replace(/\n/g, '<br>');

    const time = document.createElement('div');
    time.className = 'ai-time';
    time.textContent = timeStr();

    wrap.appendChild(bubble);
    wrap.appendChild(time);
    messagesEl.appendChild(wrap);
    scrollBottom();
    return wrap;
  }

  function addThinking() {
    const wrap = document.createElement('div');
    wrap.className = 'ai-msg ai-msg--bot ai-thinking';
    wrap.innerHTML = '<div class="ai-bubble">...</div>';
    messagesEl.appendChild(wrap);
    scrollBottom();
    return wrap;
  }

  async function send(question) {
    if (thinking || !question.trim()) return;
    thinking = true;
    sendBtn.disabled = true;

    const selectedModel = modelEl.value;
    const sugEl = document.getElementById('aiSuggestions');
    if (sugEl) sugEl.style.display = 'none';

    addMessage(question, 'user');
    inputEl.value = '';
    inputEl.style.height = 'auto';

    const thinkEl = addThinking();

    try {
      const resp = await fetch('/api/ai/chat', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN':  csrfToken,
          'Accept':        'application/json',
        },
        body: JSON.stringify({ message: question, model: selectedModel }),
      });

      thinkEl.remove();

      if (!resp.ok) {
        const err = await resp.json().catch(() => ({}));
        addBotMessage('Помилка: ' + (err.message || resp.status), null);
      } else {
        const data = await resp.json();
        let msg = data.response;
        if (data.model_used === 'sql-agent' && data.sql) {
          msg += '\n\n🗄 _SQL: `' + data.sql.replace(/\s+/g, ' ').trim().substring(0, 120) + (data.sql.length > 120 ? '…' : '') + '`_';
        }
        addBotMessage(msg, data.model_used);
      }
    } catch (e) {
      thinkEl.remove();
      addBotMessage('Мережева помилка: ' + e.message, null);
    } finally {
      thinking = false;
      sendBtn.disabled = false;
      inputEl.focus();
    }
  }

  sendBtn.addEventListener('click', () => send(inputEl.value.trim()));

  inputEl.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      send(this.value.trim());
    }
  });

  inputEl.addEventListener('input', function () {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
  });

  document.querySelectorAll('.ai-suggestion').forEach(btn => {
    btn.addEventListener('click', () => send(btn.dataset.q));
  });

  // Keep main bottom in sync with input bar height
  const mainEl    = document.getElementById('aiMain');
  const inputBar  = document.getElementById('aiInputBar');
  const syncBottom = () => {
    mainEl.style.bottom = inputBar.offsetHeight + 'px';
  };
  new ResizeObserver(syncBottom).observe(inputBar);
  syncBottom();

  inputEl.focus();
  scrollBottom();
})();
</script>

@endsection
