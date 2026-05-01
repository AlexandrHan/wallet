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
  <div id="qcSections" style="display:none;">
    <!-- ⚡ Електрики -->
    <div style="margin-bottom:12px;">
      <div class="qc-section-header" onclick="toggleQcSection('electric')"
        style="display:flex; align-items:center; gap:10px; padding:12px 16px;
          background:rgba(255,255,255,.06); border-radius:12px; cursor:pointer;
          user-select:none; margin-bottom:6px;">
        <span style="font-weight:700; font-size:15px; flex:1;">⚡ Електрики</span>
        <span id="qcBadge_electric" style="display:none; background:rgba(255,200,50,.2);
          color:#f4c842; padding:2px 9px; border-radius:10px; font-size:13px; font-weight:700;"></span>
        <span id="qcArrow_electric" style="opacity:.5; font-size:13px;">▼</span>
      </div>
      <div id="qcSection_electric"></div>
    </div>
    <!-- 🏗 Монтажники -->
    <div style="margin-bottom:12px;">
      <div class="qc-section-header" onclick="toggleQcSection('panel')"
        style="display:flex; align-items:center; gap:10px; padding:12px 16px;
          background:rgba(255,255,255,.06); border-radius:12px; cursor:pointer;
          user-select:none; margin-bottom:6px;">
        <span style="font-weight:700; font-size:15px; flex:1;">🏗 Монтажники</span>
        <span id="qcBadge_panel" style="display:none; background:rgba(100,200,100,.2);
          color:#7ec87e; padding:2px 9px; border-radius:10px; font-size:13px; font-weight:700;"></span>
        <span id="qcArrow_panel" style="opacity:.5; font-size:13px;">▼</span>
      </div>
      <div id="qcSection_panel"></div>
    </div>
    <!-- 🔧 Сервіси -->
    <div id="qcServiceBlock" style="display:none; margin-bottom:12px;">
      <div class="qc-section-header" onclick="toggleQcSection('service')"
        style="display:flex; align-items:center; gap:10px; padding:12px 16px;
          background:rgba(255,255,255,.06); border-radius:12px; cursor:pointer;
          user-select:none; margin-bottom:6px;">
        <span style="font-weight:700; font-size:15px; flex:1;">🔧 Сервіси</span>
        <span id="qcBadge_service" style="display:none; background:rgba(100,180,255,.2);
          color:#7ec8e3; padding:2px 9px; border-radius:10px; font-size:13px; font-weight:700;"></span>
        <span id="qcArrow_service" style="opacity:.5; font-size:13px;">▼</span>
      </div>
      <div id="qcSection_service"></div>
    </div>
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
  const isService = c.check_type === 'service';

  let borderStyle = '';
  let approveDisabled = '';
  let approveOpacity  = '';
  let statusNote      = '';

  const isElectric  = c.check_type === 'electric';
  const workerLabel = isService ? 'електрика' : (isElectric ? 'електрика' : 'монтажника');
  const submitterType = isService
    ? {
        label: '⚡ Електрик',
        name: c.electrician || c.submitted_by || 'не вказано',
        bg: 'rgba(34,211,238,.14)',
        color: '#7ec8e3',
      }
    : (isElectric
      ? {
          label: '⚡ Електрик',
          name: c.electrician || c.submitted_by || 'не вказано',
          bg: 'rgba(34,211,238,.14)',
          color: '#7ec8e3',
        }
      : {
          label: '🔩 Монтажна бригада',
          name: c.installation_team || c.submitted_by || 'не вказано',
          bg: 'rgba(100,200,100,.14)',
          color: '#7ec87e',
        });
  const submitterBadgeHtml = `
    <div style="display:flex; flex-wrap:wrap; gap:6px; align-items:center; margin:6px 0 10px;">
      <span style="display:inline-flex; align-items:center; gap:5px; font-size:12px; font-weight:800;
        padding:4px 10px; border-radius:999px; background:${submitterType.bg}; color:${submitterType.color};
        border:1px solid rgba(255,255,255,.08);">
        ${submitterType.label}: ${esc(submitterType.name)}
      </span>
      ${c.check_type ? '' : `<span style="display:inline-flex; font-size:12px; font-weight:700; padding:4px 10px;
        border-radius:999px; background:rgba(255,255,255,.08); color:#ccc;">❔ Тип не визначено</span>`}
    </div>`;

  if (status === 'has_deficiencies') {
    borderStyle     = 'border:2px solid #e53e3e;';
    approveDisabled = 'disabled';
    approveOpacity  = 'opacity:.4;';
    statusNote      = `<div style="margin-bottom:10px; padding:8px 10px; border-radius:7px;
        background:rgba(229,62,62,.15); font-size:13px; color:#f88;">
      ❌ Є недоліки — очікує виправлення від ${workerLabel}
    </div>`;
  } else if (status === 'deficiencies_fixed') {
    borderStyle = 'border:2px solid #d4a017;';
    statusNote  = `<div style="margin-bottom:10px; padding:8px 10px; border-radius:7px;
        background:rgba(212,160,23,.15); font-size:13px; color:#f4c842;">
      🔧 Недоліки виправлені — можна затвердити
    </div>`;
  }

  const prefillText = c.deficiencies ? esc(c.deficiencies) : '';

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

  // ── Type-specific info block ────────────────────────────────────────────
  let infoHtml = '';
  if (isService) {
    infoHtml = `
      <div style="display:inline-block; font-size:11px; font-weight:700; padding:2px 8px; border-radius:10px;
        background:rgba(26,74,106,.5); color:#7ec8e3; margin-bottom:8px;">⚡ Сервісний виклик</div>
      <div style="font-size:13px; opacity:.75; margin-bottom:6px;">
        ${c.electrician ? '⚡ ' + esc(c.electrician) : ''}
        ${c.settlement ? ' &nbsp;🏘 ' + esc(c.settlement) : ''}
      </div>
      ${c.description ? `<div style="font-size:13px; opacity:.8; margin-bottom:10px; white-space:pre-line;">${esc(c.description)}</div>` : ''}
    `;
  } else {
    infoHtml = `
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
    `;
  }

  const approveBtnLabel = isService
    ? '✅ Сервіс прийнятий'
    : (isElectric ? '✅ Електрику прийнято' : '✅ Монтаж прийнятий');

  return `
    <div class="card" style="margin-bottom:14px; ${borderStyle}" data-check-id="${c.id}" data-check-status="${esc(status)}">
      <div style="font-weight:700; font-size:16px; margin-bottom:4px;">${esc(c.client_name)}</div>
      ${submitterBadgeHtml}
      ${infoHtml}
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

      <div style="display:flex; gap:8px; margin-top:4px;">
        <button type="button" class="btn save qc-approve-btn" data-id="${c.id}"
          data-orig-label="${esc(approveBtnLabel)}"
          style="flex:1; ${approveOpacity}" ${approveDisabled}>
          ${approveBtnLabel}
        </button>
        <button type="button" class="btn qc-cancel-btn" data-id="${c.id}"
          style="padding:0 14px; background:rgba(229,62,62,.15); color:#f88; border:1px solid rgba(229,62,62,.3);">
          ✕ Скасувати
        </button>
      </div>
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

// ── Accordion helpers ─────────────────────────────────────────────────────

function toggleQcSection(key) {
  const section = document.getElementById(`qcSection_${key}`);
  const arrow   = document.getElementById(`qcArrow_${key}`);
  if (!section) return;
  const isHidden = section.style.display === 'none';
  section.style.display = isHidden ? '' : 'none';
  if (arrow) arrow.textContent = isHidden ? '▼' : '▶';
}

function getSectionKey(c) {
  if (c.check_type === 'electric') return 'electric';
  if (c.check_type === 'service')  return 'service';
  return 'panel';
}

function updateSectionBadge(key) {
  const section = document.getElementById(`qcSection_${key}`);
  const badge   = document.getElementById(`qcBadge_${key}`);
  if (!section || !badge) return;
  const count = section.querySelectorAll('[data-check-id]').length;
  badge.textContent = count;
  badge.style.display = count > 0 ? '' : 'none';
}

// ── Initial load + smart polling ─────────────────────────────────────────

let firstLoad = true;

async function loadChecks() {
  const loader    = document.getElementById('qcContainer');
  const sections  = document.getElementById('qcSections');

  try {
    const r = await fetch('/api/quality-checks');
    const checks = await r.json();

    if (!r.ok) {
      if (firstLoad) loader.innerHTML = `<div class="card" style="text-align:center; color:#f66;">${esc(checks.error || 'Помилка')}</div>`;
      return;
    }

    // ── First load: full render ──
    if (firstLoad) {
      firstLoad = false;
      loader.style.display = 'none';

      if (!checks.length) {
        loader.style.display = '';
        loader.innerHTML = `<div class="card" style="text-align:center; opacity:.7;">Немає проєктів, що очікують перевірки 🎉</div>`;
        return;
      }

      sections.style.display = '';

      // Show service block only if there are service checks
      const hasServices = checks.some(c => c.check_type === 'service');
      document.getElementById('qcServiceBlock').style.display = hasServices ? '' : 'none';

      checks.forEach(c => {
        const key = getSectionKey(c);
        const container = document.getElementById(`qcSection_${key}`);
        if (container) insertCard(c, container);
      });

      ['electric', 'panel', 'service'].forEach(updateSectionBadge);
      return;
    }

    // ── Subsequent polls: smart merge ──
    const byId = new Map(checks.map(c => [c.id, c]));

    // Remove cards that are no longer in the response
    document.querySelectorAll('[data-check-id]').forEach(card => {
      const cardId = card.dataset.checkId;
      // byId uses original IDs (may be string like "orphan-771")
      if (!byId.has(cardId) && !byId.has(+cardId)) card.remove();
    });

    // Update changed cards / add new ones
    checks.forEach(c => {
      const existing = document.querySelector(`[data-check-id="${c.id}"]`);

      if (!existing) {
        // New check appeared — add to correct section
        const key = getSectionKey(c);
        const container = document.getElementById(`qcSection_${key}`);
        if (container) {
          const div = document.createElement('div');
          div.innerHTML = renderCheck(c).trim();
          const card = div.firstElementChild;
          container.prepend(card);
          renderVoiceBlock(c.id);
          bindCardListeners(card);
        }
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

    // Update badges
    ['electric', 'panel', 'service'].forEach(updateSectionBadge);

    // Show service block only if it has cards
    const serviceSection = document.getElementById('qcSection_service');
    if (serviceSection) {
      document.getElementById('qcServiceBlock').style.display =
        serviceSection.querySelectorAll('[data-check-id]').length > 0 ? '' : 'none';
    }

    // If all sections are empty — show global empty state
    const totalCards = document.querySelectorAll('[data-check-id]').length;
    if (totalCards === 0 && !checks.length) {
      sections.style.display = 'none';
      loader.style.display = '';
      loader.innerHTML = `<div class="card" style="text-align:center; opacity:.7;">Немає проєктів, що очікують перевірки 🎉</div>`;
    }

  } catch (e) {
    if (firstLoad) loader.innerHTML = `<div class="card" style="text-align:center; color:#f66;">Помилка з'єднання</div>`;
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
      setTimeout(() => { card.remove(); ['electric','panel','service'].forEach(updateSectionBadge); }, 1200);
    } else {
      alert(data.error || 'Помилка');
      btn.disabled = false;
      btn.textContent = btn.dataset.origLabel || '✅ Прийнятий';
    }
  } catch (e) {
    alert('Помилка з\'єднання');
    btn.disabled = false;
    btn.textContent = btn.dataset.origLabel || '✅ Прийнятий';
  }
}

async function approveOrphan(projectId, btn) {
  btn.disabled = true;
  btn.textContent = 'Збереження...';
  const card = btn.closest('[data-check-id]');

  try {
    const r = await fetch(`/api/projects/${projectId}/orphan-approve`, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
    });
    const data = await r.json();

    if (r.ok && data.ok) {
      card.style.opacity = '.4';
      card.style.pointerEvents = 'none';
      btn.textContent = '✅ Прийнято';
      setTimeout(() => { card.remove(); ['electric','panel','service'].forEach(updateSectionBadge); }, 1200);
    } else {
      alert(data.error || 'Помилка');
      btn.disabled = false;
      btn.textContent = btn.dataset.origLabel || '✅ Прийнятий';
    }
  } catch (e) {
    alert('Помилка з\'єднання');
    btn.disabled = false;
    btn.textContent = btn.dataset.origLabel || '✅ Прийнятий';
  }
}

async function cancelCheck(id, btn) {
  btn.disabled = true;
  btn.textContent = '...';

  const card = btn.closest('[data-check-id]');

  try {
    const r = await fetch(`/api/quality-checks/${id}/cancel`, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
    });
    const data = await r.json();

    if (r.ok && data.ok) {
      card.style.opacity = '.4';
      card.style.pointerEvents = 'none';
      setTimeout(() => { card.remove(); ['electric','panel','service'].forEach(updateSectionBadge); }, 800);
    } else {
      alert(data.error || 'Помилка');
      btn.disabled = false;
      btn.textContent = '✕ Скасувати';
    }
  } catch (e) {
    alert('Помилка з\'єднання');
    btn.disabled = false;
    btn.textContent = '✕ Скасувати';
  }
}

async function cancelOrphan(projectId, btn) {
  btn.disabled = true;
  btn.textContent = '...';

  const card = btn.closest('[data-check-id]');

  try {
    const r = await fetch(`/api/projects/${projectId}/orphan-cancel`, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
    });
    const data = await r.json();

    if (r.ok && data.ok) {
      card.style.opacity = '.4';
      card.style.pointerEvents = 'none';
      setTimeout(() => { card.remove(); ['electric','panel','service'].forEach(updateSectionBadge); }, 800);
    } else {
      alert(data.error || 'Помилка');
      btn.disabled = false;
      btn.textContent = '✕ Скасувати';
    }
  } catch (e) {
    alert('Помилка з\'єднання');
    btn.disabled = false;
    btn.textContent = '✕ Скасувати';
  }
}

document.addEventListener('DOMContentLoaded', () => {
  loadChecks();
  setInterval(() => loadChecks().catch(() => {}), 10000);
});

document.addEventListener('click', function (e) {
  const approveBtn = e.target.closest('.qc-approve-btn');
  if (approveBtn && !approveBtn.disabled) {
    const rawId = approveBtn.dataset.id;
    const card = approveBtn.closest('[data-check-id]');
    const sectionEl = card?.closest('[id^="qcSection_"]');
    const sectionKey = sectionEl?.id?.replace('qcSection_', '') || '';
    let confirmText;
    if (sectionKey === 'service') {
      confirmText = 'Підтвердити прийняття сервісного виклику?';
    } else if (sectionKey === 'electric') {
      confirmText = 'Підтвердити прийняття електромонтажу?';
    } else {
      confirmText = 'Підтвердити прийняття монтажу?';
    }
    if (confirm(confirmText)) {
      const isOrphan = String(rawId).startsWith('orphan-');
      if (isOrphan) {
        // orphan-e-771 or orphan-p-771 → project_id = 771
        const projectId = parseInt(String(rawId).replace(/^orphan-[ep]-/, ''));
        approveOrphan(projectId, approveBtn);
      } else {
        approveCheck(parseInt(rawId), approveBtn);
      }
    }
    return;
  }

  const cancelBtn = e.target.closest('.qc-cancel-btn');
  if (cancelBtn) {
    const rawId = cancelBtn.dataset.id;
    const card = cancelBtn.closest('[data-check-id]');
    const sectionEl = card?.closest('[id^="qcSection_"]');
    const sectionKey = sectionEl?.id?.replace('qcSection_', '') || '';
    let confirmText;
    if (sectionKey === 'service') {
      confirmText = 'Скасувати відправку сервісного виклику? Електрик зможе відправити повторно.';
    } else if (sectionKey === 'electric') {
      confirmText = 'Скасувати відправку електрики на перевірку? Електрик зможе відправити повторно.';
    } else {
      confirmText = 'Скасувати відправку монтажу на перевірку? Монтажник зможе відправити повторно.';
    }
    if (confirm(confirmText)) {
      const isOrphan = String(rawId).startsWith('orphan-');
      if (isOrphan) {
        const projectId = parseInt(String(rawId).replace(/^orphan-[ep]-/, ''));
        cancelOrphan(projectId, cancelBtn);
      } else {
        cancelCheck(parseInt(rawId), cancelBtn);
      }
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
