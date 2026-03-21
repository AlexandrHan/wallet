@push('styles')
  <link rel="stylesheet" href="/css/project.css?v={{ filemtime(public_path('css/project.css')) }}">
@endpush

@extends('layouts.app')

@section('content')
<main style="padding:0 0 80px;">

  <div class="projects-title-card">
    <div class="projects-title">🔍 Контроль якості</div>
  </div>

  <div id="qcContainer">
    <div class="card" style="text-align:center; opacity:.7;">Завантаження...</div>
  </div>

</main>

{{-- Photo preview modal --}}
<div id="photoModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.85); z-index:9999; align-items:center; justify-content:center;">
  <img id="photoModalImg" src="" style="max-width:95vw; max-height:90vh; border-radius:8px;">
  <button onclick="document.getElementById('photoModal').style.display='none'"
    style="position:absolute; top:16px; right:16px; background:none; border:none; color:#fff; font-size:28px; cursor:pointer;">✕</button>
</div>

<script>
const AUTH_USER = @json(auth()->user());

function esc(v) {
  return String(v ?? '')
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

// ── Auto-save state ────────────────────────────────────────────────────────
const autoSaveTimers = {};
// Per-check: track what's already been uploaded so we don't duplicate
const uploadedState  = {}; // { [id]: { photosUploaded: bool, voiceUploaded: bool } }

function getUploaded(id) {
  return uploadedState[id] || (uploadedState[id] = { photosUploaded: false, voiceUploaded: false });
}

function setSaveIndicator(id, state) {
  const el = document.getElementById(`saveIndicator_${id}`);
  if (!el) return;
  if (state === 'saving') {
    el.textContent = '⏳ Збереження...';
    el.style.color = '';
    el.style.opacity = '.7';
  } else if (state === 'saved') {
    el.textContent = '💾 Збережено';
    el.style.color = '#5f5';
    el.style.opacity = '1';
    setTimeout(() => { if (el) el.style.opacity = '.45'; }, 2000);
  } else if (state === 'error') {
    el.textContent = '⚠️ Помилка збереження';
    el.style.color = '#f88';
    el.style.opacity = '1';
  }
}

async function autoSave(id) {
  const card = document.querySelector(`[data-check-id="${id}"]`);
  if (!card) return;

  const textarea   = card.querySelector('.qc-deficiencies');
  const photoInput = card.querySelector('.qc-photos');
  const hasText    = (textarea?.value || '').trim() !== '';
  const hasPhoto   = (photoInput?.files?.length || 0) > 0;
  const hasVoice   = !!(voiceState[id]?.audioBlob);

  if (!hasText && !hasPhoto && !hasVoice) return;

  const up = getUploaded(id);
  const fd = new FormData();
  fd.append('deficiencies', textarea?.value || '');

  if (hasPhoto && !up.photosUploaded) {
    Array.from(photoInput.files).forEach(f => fd.append('photos[]', f));
  }
  if (hasVoice && !up.voiceUploaded) {
    fd.append('voice_memo', voiceState[id].audioBlob, 'voice.webm');
  }

  setSaveIndicator(id, 'saving');

  try {
    const r = await fetch(`/api/quality-checks/${id}/save-deficiencies`, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
      body: fd,
    });
    const data = await r.json();

    if (r.ok && data.ok) {
      if (hasPhoto && !up.photosUploaded) up.photosUploaded = true;
      if (hasVoice && !up.voiceUploaded)  up.voiceUploaded  = true;
      card.dataset.checkStatus = 'has_deficiencies';
      setSaveIndicator(id, 'saved');
      updateCardState(id);
    } else {
      setSaveIndicator(id, 'error');
    }
  } catch (e) {
    setSaveIndicator(id, 'error');
  }
}

function scheduleAutoSave(id, delay = 1500) {
  clearTimeout(autoSaveTimers[id]);
  autoSaveTimers[id] = setTimeout(() => autoSave(id), delay);
}

// ── Voice recording state ─────────────────────────────────────────────────
const voiceState = {};

async function startRecording(id) {
  const state = voiceState[id] || (voiceState[id] = {});
  if (state.recorder && state.recorder.state === 'recording') return;

  try {
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    const recorder = new MediaRecorder(stream);
    const chunks = [];
    recorder.ondataavailable = e => { if (e.data.size > 0) chunks.push(e.data); };
    recorder.onstop = () => {
      stream.getTracks().forEach(t => t.stop());
      const blob = new Blob(chunks, { type: recorder.mimeType || 'audio/webm' });
      state.audioBlob = blob;
      state.audioUrl  = URL.createObjectURL(blob);
      renderVoiceBlock(id);
      updateCardState(id);
      scheduleAutoSave(id, 0); // immediate on voice ready
    };
    state.recorder = recorder;
    state.chunks   = chunks;
    recorder.start();
    renderVoiceBlock(id);
  } catch (err) {
    alert('Мікрофон недоступний: ' + (err.message || err));
  }
}

function stopRecording(id) {
  const state = voiceState[id];
  if (state?.recorder?.state === 'recording') state.recorder.stop();
}

function deleteVoice(id) {
  const state = voiceState[id];
  if (!state) return;
  if (state.audioUrl) URL.revokeObjectURL(state.audioUrl);
  delete state.audioBlob;
  delete state.audioUrl;
  if (state.recorder) {
    try { state.recorder.stream?.getTracks().forEach(t => t.stop()); } catch(_) {}
    delete state.recorder;
  }
  // Reset uploaded flag so a new voice can be uploaded
  if (uploadedState[id]) uploadedState[id].voiceUploaded = false;
  renderVoiceBlock(id);
  updateCardState(id);
}

function renderVoiceBlock(id) {
  const el = document.getElementById(`voiceBlock_${id}`);
  if (!el) return;
  const state = voiceState[id] || {};
  const isRecording = state.recorder?.state === 'recording';
  const hasAudio    = !!state.audioUrl;

  if (isRecording) {
    el.innerHTML = `
      <div style="display:flex; align-items:center; gap:10px; padding:8px 0;">
        <span style="display:inline-block; width:10px; height:10px; background:#f55; border-radius:50%;
          animation:qc-blink .8s infinite;"></span>
        <span style="font-size:13px; opacity:.8;">Запис...</span>
        <button type="button" class="btn" style="padding:4px 12px; font-size:13px;"
          onclick="stopRecording(${id})">⏹ Стоп</button>
      </div>`;
    return;
  }

  if (hasAudio) {
    el.innerHTML = `
      <div style="padding:8px 0;">
        <audio controls src="${state.audioUrl}"
          style="width:100%; max-width:100%; height:36px; margin-bottom:8px;"></audio>
        <div style="display:flex; gap:8px;">
          <button type="button" class="btn" style="flex:1; font-size:13px; padding:5px 8px;"
            onclick="startRecording(${id})">🎙 Перезаписати</button>
          <button type="button" class="btn" style="font-size:13px; padding:5px 10px; color:#f77;"
            onclick="deleteVoice(${id})">🗑</button>
        </div>
      </div>`;
    return;
  }

  el.innerHTML = `
    <button type="button" class="btn" style="width:100%; font-size:13px; padding:7px;"
      onclick="startRecording(${id})">🎙 Надиктувати коментар</button>`;
}

// ── Card state (border + approve button) ─────────────────────────────────

function updateCardState(id) {
  const card = document.querySelector(`[data-check-id="${id}"]`);
  if (!card) return;

  const status     = card.dataset.checkStatus || 'pending';
  const textarea   = card.querySelector('.qc-deficiencies');
  const photoInput = card.querySelector('.qc-photos');
  const hasText    = (textarea?.value || '').trim() !== '';
  const hasPhoto   = (photoInput?.files?.length || 0) > 0;
  const hasVoice   = !!(voiceState[id]?.audioBlob);
  const hasAny     = hasText || hasPhoto || hasVoice;

  const approveBtn = card.querySelector('.qc-approve-btn');

  // Border and approve state driven by server status only.
  // hasAny is used only to show a visual hint — the auto-save handles actual persistence.
  if (status === 'has_deficiencies') {
    card.style.border = '2px solid #e53e3e';
    if (approveBtn) { approveBtn.disabled = true; approveBtn.style.opacity = '.4'; }
  } else if (status === 'deficiencies_fixed') {
    card.style.border = '2px solid #d4a017';
    if (approveBtn) { approveBtn.disabled = false; approveBtn.style.opacity = ''; }
  } else if (hasAny) {
    // pending with unsaved content — red border hint, but approve NOT blocked
    // (auto-save will fire; foreman can still approve with deficiencies text)
    card.style.border = '2px solid #e53e3e';
    if (approveBtn) { approveBtn.disabled = false; approveBtn.style.opacity = ''; }
  } else {
    card.style.border = '';
    if (approveBtn) { approveBtn.disabled = false; approveBtn.style.opacity = ''; }
  }
}

// ── Render ────────────────────────────────────────────────────────────────

function renderCheck(c) {
  const status = c.check_status || 'pending';

  let borderStyle = '';
  let approveDisabled = '';
  let approveOpacity  = '';
  let statusNote      = '';

  if (status === 'has_deficiencies') {
    borderStyle     = 'border:2px solid #e53e3e;';
    approveDisabled = 'disabled';
    approveOpacity  = 'opacity:.4;';
    statusNote      = `<div style="margin-bottom:10px; padding:8px 10px; border-radius:7px;
        background:rgba(229,62,62,.15); font-size:13px; color:#f88;">
      ❌ Є недоліки — очікує виправлення від монтажника
    </div>`;
  } else if (status === 'deficiencies_fixed') {
    borderStyle = 'border:2px solid #d4a017;';
    statusNote  = `<div style="margin-bottom:10px; padding:8px 10px; border-radius:7px;
        background:rgba(212,160,23,.15); font-size:13px; color:#f4c842;">
      🔧 Недоліки виправлені — можна затвердити
    </div>`;
  }

  const prefillText = c.deficiencies ? esc(c.deficiencies) : '';

  // Saved media (loaded from DB on page open)
  const savedPhotosHtml = (c.photos || []).length
    ? `<div style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:10px;">
        ${(c.photos || []).map(url => `
          <img src="${url}" style="width:64px; height:64px; object-fit:cover; border-radius:6px; cursor:pointer;"
            onclick="document.getElementById('photoModalImg').src='${url}';document.getElementById('photoModal').style.display='flex';">`
        ).join('')}
      </div>`
    : '';
  const savedVoiceHtml = c.voice_memo_url
    ? `<div style="margin-bottom:10px;">
        <div class="project-field-label">Збережений голосовий коментар</div>
        <audio controls src="${c.voice_memo_url}"
          style="width:100%; max-width:100%; height:36px;"></audio>
      </div>`
    : '';

  return `
    <div class="card" style="margin-bottom:14px; ${borderStyle}" data-check-id="${c.id}" data-check-status="${esc(status)}">
      <div style="font-weight:700; font-size:16px; margin-bottom:4px;">${esc(c.client_name)}</div>
      <div style="font-size:13px; opacity:.75; margin-bottom:10px;">
        ${c.installation_team ? '🏗 ' + esc(c.installation_team) + '&nbsp;&nbsp;' : ''}
        ${c.electrician ? '⚡ ' + esc(c.electrician) : ''}
      </div>
      <div style="font-size:13px; opacity:.8; margin-bottom:4px;">
        ${c.panel_name ? 'ФЕМ: ' + esc(c.panel_name) + (c.panel_qty ? ' × ' + esc(c.panel_qty) : '') : ''}
      </div>
      <div style="font-size:13px; opacity:.8; margin-bottom:10px;">
        ${c.inverter ? 'Інвертор: ' + esc(c.inverter) : ''}
      </div>
      <div style="font-size:12px; opacity:.55; margin-bottom:14px;">
        Подав: ${esc(c.submitted_by)} — ${new Date(c.created_at).toLocaleDateString('uk-UA')}
      </div>

      ${statusNote}

      ${savedPhotosHtml}
      ${savedVoiceHtml}

      <div class="project-field-label">Недоліки (необов'язково)</div>
      <textarea class="btn project-textarea qc-deficiencies" data-for="${c.id}"
        placeholder="Опишіть виявлені недоліки..."
        style="min-height:80px; margin-bottom:8px; width:100%; box-sizing:border-box;">${prefillText}</textarea>

      <div id="voiceBlock_${c.id}" style="margin-bottom:12px;"></div>

      <div class="project-field-label">Фото (необов'язково)</div>
      <div style="margin-bottom:10px;">
        <label style="display:block; cursor:pointer;">
          <div class="btn" style="text-align:center; font-size:13px; padding:8px;">
            📷 Додати фото
          </div>
          <input type="file" multiple accept="image/*" class="qc-photos" data-for="${c.id}"
            style="display:none;">
        </label>
        <div class="qc-photos-preview" data-for="${c.id}"
          style="display:flex; flex-wrap:wrap; gap:6px; margin-top:6px;"></div>
      </div>

      <div id="saveIndicator_${c.id}"
        style="font-size:12px; text-align:right; min-height:18px; margin-bottom:6px; transition:opacity .4s;"></div>

      <button type="button" class="btn save qc-approve-btn" data-id="${c.id}"
        style="width:100%; ${approveOpacity}" ${approveDisabled}>
        ✅ Проект прийнятий
      </button>
    </div>
  `;
}

// ── Bind listeners to a single card element ──────────────────────────────

function bindCardListeners(card) {
  card.querySelectorAll('.qc-photos').forEach(input => {
    input.addEventListener('change', () => {
      const id = input.dataset.for;
      const preview = card.querySelector(`.qc-photos-preview[data-for="${id}"]`);
      if (preview) {
        preview.innerHTML = '';
        Array.from(input.files).forEach(file => {
          const url = URL.createObjectURL(file);
          preview.insertAdjacentHTML('beforeend',
            `<img src="${url}" style="width:64px; height:64px; object-fit:cover; border-radius:6px; cursor:pointer;"
              onclick="document.getElementById('photoModalImg').src='${url}';document.getElementById('photoModal').style.display='flex';">`
          );
        });
      }
      updateCardState(id);
      scheduleAutoSave(id, 0);
    });
  });

  card.querySelectorAll('.qc-deficiencies').forEach(ta => {
    ta.addEventListener('input', () => {
      updateCardState(ta.dataset.for);
      scheduleAutoSave(ta.dataset.for, 1500);
    });
  });
}

function hasLocalEdits(id) {
  const card = document.querySelector(`[data-check-id="${id}"]`);
  if (!card) return false;
  const hasText  = (card.querySelector('.qc-deficiencies')?.value || '').trim() !== '';
  const hasPhoto = (card.querySelector('.qc-photos')?.files?.length || 0) > 0;
  const hasVoice = !!(voiceState[id]?.audioBlob);
  return hasText || hasPhoto || hasVoice;
}

function insertCard(c, container) {
  const div = document.createElement('div');
  div.innerHTML = renderCheck(c).trim();
  const card = div.firstElementChild;
  container.appendChild(card);
  renderVoiceBlock(c.id);
  bindCardListeners(card);
}

// ── Initial load + smart polling ─────────────────────────────────────────

let firstLoad = true;

async function loadChecks() {
  const container = document.getElementById('qcContainer');
  try {
    const r = await fetch('/api/quality-checks');
    const checks = await r.json();

    if (!r.ok) {
      if (firstLoad) container.innerHTML = `<div class="card" style="text-align:center; color:#f66;">${esc(checks.error || 'Помилка')}</div>`;
      return;
    }

    // ── First load: full render ──
    if (firstLoad) {
      firstLoad = false;
      if (!checks.length) {
        container.innerHTML = `<div class="card" style="text-align:center; opacity:.7;">Немає проєктів, що очікують перевірки 🎉</div>`;
        return;
      }
      container.innerHTML = '';
      checks.forEach(c => insertCard(c, container));
      return;
    }

    // ── Subsequent polls: smart merge ──
    const byId = new Map(checks.map(c => [c.id, c]));

    // Remove cards that are no longer in the response
    container.querySelectorAll('[data-check-id]').forEach(card => {
      if (!byId.has(+card.dataset.checkId)) card.remove(); // dataset.checkId = data-check-id in camelCase
    });

    // Update changed cards / add new ones
    checks.forEach(c => {
      const existing = container.querySelector(`[data-check-id="${c.id}"]`);

      if (!existing) {
        // New check appeared — prepend it
        const div = document.createElement('div');
        div.innerHTML = renderCheck(c).trim();
        const card = div.firstElementChild;
        container.prepend(card);
        renderVoiceBlock(c.id);
        bindCardListeners(card);
        return;
      }

      // If status changed and no local edits — replace card
      const statusChanged = existing.dataset.checkStatus !== (c.check_status || 'pending');
      if (statusChanged && !hasLocalEdits(c.id)) {
        const div = document.createElement('div');
        div.innerHTML = renderCheck(c).trim();
        const newCard = div.firstElementChild;
        existing.replaceWith(newCard);
        renderVoiceBlock(c.id);
        bindCardListeners(newCard);
      }
    });

    // Show empty state if all cards removed
    if (!container.querySelector('[data-check-id]') && !checks.length) {
      container.innerHTML = `<div class="card" style="text-align:center; opacity:.7;">Немає проєктів, що очікують перевірки 🎉</div>`;
    }

  } catch (e) {
    if (firstLoad) container.innerHTML = `<div class="card" style="text-align:center; color:#f66;">Помилка з'єднання</div>`;
  }
}

async function approveCheck(id, btn) {
  btn.disabled = true;
  btn.textContent = 'Збереження...';

  const card = btn.closest('[data-check-id]');

  const fd = new FormData();
  fd.append('deficiencies', card.querySelector('.qc-deficiencies')?.value || '');

  const photoInput = card.querySelector('.qc-photos');
  if (photoInput?.files?.length) {
    Array.from(photoInput.files).forEach(f => fd.append('photos[]', f));
  }
  const vs = voiceState[id];
  if (vs?.audioBlob) fd.append('voice_memo', vs.audioBlob, 'voice.webm');

  try {
    const r = await fetch(`/api/quality-checks/${id}/approve`, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
      body: fd,
    });
    const data = await r.json();

    if (r.ok && data.ok) {
      card.style.opacity = '.4';
      card.style.pointerEvents = 'none';
      btn.textContent = '✅ Прийнято';
      setTimeout(() => card.remove(), 1200);
    } else {
      alert(data.error || 'Помилка');
      btn.disabled = false;
      btn.textContent = '✅ Проект прийнятий';
    }
  } catch (e) {
    alert('Помилка з\'єднання');
    btn.disabled = false;
    btn.textContent = '✅ Проект прийнятий';
  }
}

document.addEventListener('DOMContentLoaded', () => {
  loadChecks();
  setInterval(() => loadChecks().catch(() => {}), 10000);
});

document.addEventListener('click', function (e) {
  const approveBtn = e.target.closest('.qc-approve-btn');
  if (approveBtn && !approveBtn.disabled) {
    const id = parseInt(approveBtn.dataset.id);
    if (confirm('Підтвердити прийняття проєкту?')) {
      approveCheck(id, approveBtn);
    }
    return;
  }

  if (e.target.id === 'photoModal') {
    document.getElementById('photoModal').style.display = 'none';
  }
});
</script>

<style>
@keyframes qc-blink {
  0%, 100% { opacity: 1; }
  50%       { opacity: 0; }
}
</style>

@include('partials.nav.bottom')
@endsection
