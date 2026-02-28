@extends('layouts.app')

@section('content')
<main class="">

  <div class="card" style="margin-bottom:15px;">
    <div style="font-weight:800; font-size:18px; text-align:center;">
      🏗 Проекти СЕС
    </div>
  </div>

  <div id="constructionProjectsContainer"></div>

</main>

<script>
const AUTH_USER = @json(auth()->user());
const IS_OWNER = AUTH_USER && AUTH_USER.role === 'owner';
let STAFF_OPTIONS = {
  electrician: [
    { id: null, name: 'Малінін' },
    { id: null, name: 'Савенков' },
    { id: null, name: 'Комаренко' },
  ],
  installation_team: [
    { id: null, name: 'Кукуяка' },
    { id: null, name: 'Шевченко' },
    { id: null, name: 'Крижановський' },
  ],
};

const OPEN_PROJECT_KEY = 'construction_open_project_id';
const rememberOpenProject = (id) => localStorage.setItem(OPEN_PROJECT_KEY, String(id));

function esc(v) {
  return String(v ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

const projectLastSavedSnapshot = new Map();
const projectSaveTimers = new Map();
const projectSaving = new Set();
const staffDeleteUnlockTimers = new WeakMap();

function isAddOptionToken(v) {
  return v === '__add_new_electrician__' || v === '__add_new_installation_team__';
}

function isDeleteOptionToken(v) {
  return v === '__delete_current_electrician__' || v === '__delete_current_installation_team__';
}

function getProjectIdFromBody(body) {
  return body?.querySelector('.save-project-btn')?.dataset.id || null;
}

function getProjectPayload(body) {
  const payload = {};
  body.querySelectorAll('[data-field]').forEach(el => {
    const field = el.dataset.field;
    if (!field || el.type === 'file') return;
    if (isAddOptionToken(el.value)) return;
    if (el.type === 'checkbox') {
      payload[field] = el.checked ? '1' : '0';
      return;
    }
    payload[field] = el.value ?? '';
  });
  return payload;
}

function getProjectSnapshot(body) {
  return JSON.stringify(getProjectPayload(body));
}

function getProjectFormData(body, includeFile = true) {
  const fd = new FormData();
  body.querySelectorAll('[data-field]').forEach(el => {
    const field = el.dataset.field;
    if (!field) return;

    if (el.type === 'file') {
      if (includeFile && el.files && el.files.length) {
        Array.from(el.files).forEach(file => {
          if (field === 'photos' || field === 'attachments') {
            fd.append(`${field}[]`, file);
          } else {
            fd.append(field, file);
          }
        });
      }
      return;
    }

    if (isAddOptionToken(el.value)) return;
    if (el.type === 'checkbox') {
      fd.append(field, el.checked ? '1' : '0');
      return;
    }
    fd.append(field, el.value ?? '');
  });
  return fd;
}

async function saveProjectDraft(projectId, body, opts = {}) {
  if (!projectId || !body) return;

  const {
    notify = false,
    force = false,
    reloadAfter = false,
    keepalive = false,
  } = opts;

  const id = String(projectId);
  const snapshot = getProjectSnapshot(body);
  const prev = projectLastSavedSnapshot.get(id);
  const hasFile = Array.from(body.querySelectorAll('input[type="file"][data-field]'))
    .some(input => input.files && input.files.length > 0);

  if (!force && !hasFile && prev === snapshot) return;
  if (!force && projectSaving.has(id)) return;

  if (keepalive) {
    fetch(`/api/sales-projects/${id}/construction`, {
      method: 'POST',
      keepalive: true,
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
      },
      body: JSON.stringify(getProjectPayload(body))
    }).catch(() => {});

    projectLastSavedSnapshot.set(id, snapshot);
    return;
  }

  projectSaving.add(id);

  try {
    const r = await fetch(`/api/sales-projects/${id}/construction`, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
      },
      body: getProjectFormData(body, true)
    });

    const res = await r.json();
    if (!r.ok || !res.ok) throw new Error(res.error || 'Помилка збереження');

    projectLastSavedSnapshot.set(id, snapshot);

    if (notify) alert('Збережено');
    if (reloadAfter) {
      await loadConstructionProjects();
      rememberOpenProject(id);
    }
  } catch (err) {
    if (notify) alert(err.message || 'Помилка збереження');
  } finally {
    projectSaving.delete(id);
  }
}

function scheduleProjectAutosave(body, delay = 900) {
  const id = getProjectIdFromBody(body);
  if (!id) return;

  const key = String(id);
  const oldTimer = projectSaveTimers.get(key);
  if (oldTimer) clearTimeout(oldTimer);

  const timer = setTimeout(() => {
    saveProjectDraft(id, body, { notify: false });
  }, delay);

  projectSaveTimers.set(key, timer);
}

function saveAllProjectsOnExit() {
  document.querySelectorAll('.project-body').forEach(body => {
    const id = getProjectIdFromBody(body);
    if (!id) return;
    saveProjectDraft(id, body, { force: true, keepalive: true });
  });
}

async function loadConstructionProjects() {
  try {
    const rStaff = await fetch('/api/construction-staff-options');
    if (rStaff.ok) {
      const staff = await rStaff.json();
      STAFF_OPTIONS = {
        electrician: Array.isArray(staff.electrician) ? staff.electrician : STAFF_OPTIONS.electrician,
        installation_team: Array.isArray(staff.installation_team) ? staff.installation_team : STAFF_OPTIONS.installation_team,
      };
    }
  } catch (_) {}

  const r = await fetch('/api/sales-projects');
  const projects = await r.json();
  const sortedProjects = [...projects].sort((a, b) => {
    const aHasDefects = String(a.defects_note || '').trim() !== '';
    const bHasDefects = String(b.defects_note || '').trim() !== '';

    if (aHasDefects !== bHasDefects) {
      return aHasDefects ? -1 : 1;
    }

    return String(a.client_name || '').localeCompare(String(b.client_name || ''), 'uk', { sensitivity: 'base' });
  });

  localStorage.removeItem(OPEN_PROJECT_KEY);

  const container = document.getElementById('constructionProjectsContainer');
  if (!container) return;
  container.innerHTML = '';

  const activeProjects = sortedProjects.filter(p => p.status !== 'completed');
  const completedProjects = sortedProjects.filter(p => p.status === 'completed');

  function buildProjectCard(p) {
    const isClosed = p.status === 'completed';
    const hasDefects = String(p.defects_note || '').trim() !== '';
    const hasGreenTariff = !!p.has_green_tariff;
    const photoHtml = p.defects_photo_url
      ? `<a href="${p.defects_photo_url}" target="_blank" style="font-size:12px; opacity:.8;">📎 Поточне фото недоліків</a>`
      : '';
    const electricianValues = [...STAFF_OPTIONS.electrician];
    const teamValues = [...STAFF_OPTIONS.installation_team];
    const imageThumbs = (p.attachments || [])
      .filter(a => a.is_image)
      .map(a => `<a href="${a.url}" target="_blank"><img src="${a.url}" alt="${esc(a.name)}" style="width:54px; height:54px; object-fit:cover; border-radius:8px; border:1px solid #ffffff33;"></a>`)
      .join('');
    const fileLinks = (p.attachments || [])
      .filter(a => !a.is_image)
      .map(a => `<a href="${a.url}" target="_blank" style="display:block; font-size:12px; margin-top:4px;">📎 ${esc(a.name)}</a>`)
      .join('');

    const electricianOptionsHtml = electricianValues.map(opt => `
      <option value="${esc(opt.name)}" data-option-id="${opt.id ?? ''}" ${String(p.electrician || '') === String(opt.name) ? 'selected' : ''}>${esc(opt.name)}</option>
    `).join('');
    const teamOptionsHtml = teamValues.map(opt => `
      <option value="${esc(opt.name)}" data-option-id="${opt.id ?? ''}" ${String(p.installation_team || '') === String(opt.name) ? 'selected' : ''}>${esc(opt.name)}</option>
    `).join('');

    const card = document.createElement('div');
    card.className = 'card';
    card.style.marginBottom = '12px';
    card.style.cursor = 'pointer';
    if (hasGreenTariff) {
      card.style.background = 'linear-gradient(180deg, rgba(38,120,68,.28), rgba(18,56,33,.18)), rgba(255,255,255,.03)';
      card.style.border = '1px solid rgba(102, 242, 168, .45)';
      card.style.boxShadow = '0 10px 24px rgba(24,110,60,.10)';
    }
    if (hasDefects) {
      card.style.border = '2px solid #e63946';
      card.style.boxShadow = '0 0 0 1px rgba(230,57,70,.18), 0 10px 24px rgba(230,57,70,.08)';
    }
    card.innerHTML = `
      <div class="project-header" style="display:flex; justify-content:space-between;">
        <div style="font-weight:700;">${esc(p.client_name)}</div>
        <div style="opacity:.6; font-size:12px;">
          ${esc(p.created_at)} ${isClosed ? '• ✅ Закритий' : ''}
        </div>
      </div>

      <div class="project-body" style="display:none; margin-top:12px; border-top:1px solid #ffffff20; padding-top:12px;">
       <div style="font-size:18px; font-weight:700; opacity:.9; margin-bottom:8px; text-align:center;">Дані клієнта</div>
        <div style="font-size:12px; opacity:.8; margin-bottom:4px;">Посилання на Telegram групу</div>
        <input class="btn" data-field="telegram_group_link" value="${esc(p.telegram_group_link)}" placeholder="Вставте посилання на Telegram" style="width:100%; margin-bottom:8px;">

        <button class="btn open-telegram-btn" style="width:100%; margin-bottom:10px; background:#229ED9; border-color:#229ED9; color:#fff; display:inline-flex; align-items:center; justify-content:center; gap:10px; padding:10px 20px;">
          <img src="/img/telegram.png" alt="Telegram" style="width:24px; height:24px; object-fit:contain;">
          <span>Відкрити Telegram</span>
        </button>

        <div style="font-size:12px; opacity:.8; margin-bottom:4px;">Посилання на геолокацію</div>
        <input class="btn" data-field="geo_location_link" value="${esc(p.geo_location_link)}" placeholder="Вставте посилання на геолокацію" style="width:100%; margin-bottom:8px;">

        <button class="btn open-maps-btn" style="width:100%; margin-bottom:10px; background:#1a73e8; border-color:#1a73e8; color:#fff; display:inline-flex; align-items:center; justify-content:center; gap:10px; padding:10px 20px;">
          <span style="font-size:18px; line-height:1;">📍</span>
          <span>Відкрити Google Maps</span>
        </button>

        <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:10px; padding:10px 12px; border:1px solid #ffffff20; border-radius:12px;">
          <div>
            <div style="font-size:12px; opacity:.8;">Зелений тариф</div>
            <div style="font-size:11px; opacity:.6;">${hasGreenTariff ? 'Є' : 'Немає'}</div>
          </div>
          <input type="checkbox" data-field="has_green_tariff" ${hasGreenTariff ? 'checked' : ''} style="width:18px; height:18px;">
        </div>

        <hr style="border:none; border-top:1px solid #ffffff20; margin:12px 0 8px;">
        <div style="font-size:18px; font-weight:700; opacity:.9; margin-bottom:8px; text-align:center;">Обладнання</div>

        <div style="font-size:12px; opacity:.8; margin-bottom:4px;">Інвертор</div>
        <input class="btn" data-field="inverter" value="${esc(p.inverter)}" placeholder="Інвертор" style="width:100%; margin-bottom:8px;">
        <div style="font-size:12px; opacity:.8; margin-bottom:4px;">BMS</div>
        <input class="btn" data-field="bms" value="${esc(p.bms)}" placeholder="BMS" style="width:100%; margin-bottom:8px;">

        <!-- АКБ -->
        <div style="margin-bottom:12px;">
          <div style="display:flex; gap:6px; margin-bottom:2px; font-size:11px; opacity:.7;">
            <div style="flex:2;">АКБ</div>
            <div style="flex:1; text-align:center;">К-сть</div>
          </div>
          <div style="display:flex; gap:6px;">
            <input class="btn" data-field="battery_name" value="${esc(p.battery_name)}" placeholder="Назва АКБ" style="flex:2;">
            <input type="number" class="btn" data-field="battery_qty" value="${p.battery_qty ?? ''}" placeholder="0" style="flex:1; text-align:center; width:70%;">
          </div>
        </div>

        <!-- ФЕМ -->
        <div>
          <div style="display:flex; gap:6px; margin-bottom:2px; font-size:11px; opacity:.7;">
            <div style="flex:2;">ФЕМ</div>
            <div style="flex:1; text-align:center;">К-сть</div>
          </div>
          <div style="display:flex; gap:6px;">
            <input class="btn" data-field="panel_name" value="${esc(p.panel_name)}" placeholder="Назва ФЕМ" style="flex:2;">
            <input type="number" class="btn" data-field="panel_qty" value="${p.panel_qty ?? ''}" placeholder="0" style="flex:1; text-align:center; width:70%;">
          </div>
        </div>

        <hr style="border:none; border-top:1px solid #ffffff20; margin:12px 0 8px;">
        <div style="font-size:18px; font-weight:700; opacity:.9; margin-bottom:8px; text-align:center;">Персонал</div>

        <div style="font-size:12px; opacity:.8; margin-bottom:4px;">Електрик</div>
        <select class="btn" data-field="electrician" style="width:100%; margin-bottom:8px;">
          <option value="">Оберіть електрика</option>
          ${electricianOptionsHtml}
          ${IS_OWNER ? '<option value="__add_new_electrician__">➕ Додати електрика...</option>' : ''}
          ${IS_OWNER ? '<option value="__delete_current_electrician__" hidden>🗑 Видалити вибраного...</option>' : ''}
        </select>


        <div style="font-size:12px; opacity:.8; margin-bottom:4px;">Монтажна бригада</div>
        <select class="btn" data-field="installation_team" style="width:100%; margin-bottom:8px;">
          <option value="">Оберіть бригаду</option>
          ${teamOptionsHtml}
          ${IS_OWNER ? '<option value="__add_new_installation_team__">➕ Додати монтажника...</option>' : ''}
          ${IS_OWNER ? '<option value="__delete_current_installation_team__" hidden>🗑 Видалити вибраного...</option>' : ''}
        </select>
        

        <div style="font-size:12px; opacity:.8; margin-bottom:4px;">Доп. роботи</div>
        <input class="btn" data-field="extra_works" value="${esc(p.extra_works)}" placeholder="Вкажіть додаткові роботи" style="width:100%; margin-bottom:8px;">

        <hr style="border:none; border-top:1px solid #ffffff20; margin:12px 0 8px;">

        <div style="font-size:18px; font-weight:700; opacity:.9; margin-bottom:8px; text-align:center;">Недоліки</div>

        <div style="margin-top:10px; margin-bottom:4px; font-size:12px; opacity:.8;">Опис проблемних місць</div>

        <textarea class="btn" data-field="defects_note" placeholder="Опис недоліків..." style="width:100%; height:70px; margin-bottom:8px;">${esc(p.defects_note)}</textarea>


        <div style="font-size:12px; opacity:.8; margin-bottom:4px;">Головне фото недоліків</div>
        ${photoHtml}
        <input type="file" data-field="defects_photo" accept="image/*" style="width:100%; margin-bottom:10px;">
        <hr style="border:none; border-top:1px solid #ffffff20; margin:12px 0 8px;">


        <div style="font-size:18px; font-weight:700; opacity:.9; margin-bottom:8px; text-align:center;">Фото та файли</div>
        <div style="font-size:12px; opacity:.8; margin-bottom:4px;">Фото з телефону</div>
        <input type="file" data-field="photos" accept="image/*" capture="environment" multiple style="width:100%; margin-bottom:8px;">
        <div style="font-size:12px; opacity:.8; margin-bottom:4px;">Файли</div>
        <input type="file" data-field="attachments" multiple style="width:100%; margin-bottom:8px;">
        ${(imageThumbs || fileLinks) ? `
          <div style="font-size:12px; opacity:.8; margin-bottom:4px;">Додані матеріали</div>
          ${imageThumbs ? `<div style="display:flex; gap:6px; flex-wrap:wrap; margin-bottom:6px;">${imageThumbs}</div>` : ''}
          ${fileLinks ? `<div style="margin-bottom:8px;">${fileLinks}</div>` : ''}
        ` : ''}

        <hr style="border:none; border-top:1px solid #ffffff20; margin:22px 0;">

        <button class="btn save-project-btn" data-id="${p.id}" style="width:100%; margin-bottom:8px;" ${isClosed ? 'disabled' : ''}>
          💾 Зберегти
        </button>


        <button class="btn close-project-btn" data-id="${p.id}" style="width:100%; background:${hasDefects ? '#4a2a2a' : '#7a1c1c'};" ${(isClosed || hasDefects) ? 'disabled' : ''}>
          ${isClosed ? '✅ Проект закритий' : (hasDefects ? '⚠️ Є недоліки, закриття заборонено' : '🔒 Закрити проект')}
        </button>
      </div>
    `;

    card.querySelector('.project-header')?.addEventListener('click', function(){
      const body = card.querySelector('.project-body');
      const open = body.style.display === 'none';
      if (!open) {
        const id = getProjectIdFromBody(body);
        saveProjectDraft(id, body, { force: true, notify: false });
      }
      body.style.display = open ? 'block' : 'none';
      if (open) rememberOpenProject(p.id);
    });

    container.appendChild(card);

    const initialBody = card.querySelector('.project-body');
    if (initialBody) {
      initialBody.querySelectorAll('select[data-field="electrician"], select[data-field="installation_team"]').forEach(select => {
        select.dataset.previousValue = select.value || '';
      });
      projectLastSavedSnapshot.set(String(p.id), getProjectSnapshot(initialBody));
    }

    return card;
  }

  if (completedProjects.length > 0) {
    const completedWrap = document.createElement('div');
    completedWrap.className = 'card';
    completedWrap.style.marginBottom = '12px';
    completedWrap.innerHTML = `
      <div class="completed-projects-toggle" style="display:flex; justify-content:space-between; align-items:center; cursor:pointer;">
        <div style="font-weight:800;">✅ Завершені проекти</div>
        <div style="opacity:.7;">${completedProjects.length}</div>
      </div>
      <div class="completed-projects-body" style="display:none; margin-top:12px; border-top:1px solid #ffffff20; padding-top:12px;"></div>
    `;

    const completedBody = completedWrap.querySelector('.completed-projects-body');
    completedProjects.forEach(p => {
      completedBody.appendChild(buildProjectCard(p));
    });

    completedWrap.querySelector('.completed-projects-toggle')?.addEventListener('click', function () {
      const body = completedWrap.querySelector('.completed-projects-body');
      const open = body.style.display === 'none';
      body.style.display = open ? 'block' : 'none';
    });

    container.appendChild(completedWrap);
  }

  activeProjects.forEach(p => {
    container.appendChild(buildProjectCard(p));
  });
}

document.addEventListener('DOMContentLoaded', () => {
  loadConstructionProjects().catch(() => alert('Не вдалося завантажити проекти'));
});

document.addEventListener('click', async function(e){
  const target = e.target instanceof Element ? e.target : null;
  const saveBtn = target ? target.closest('.save-project-btn') : null;
  if (saveBtn) {
    const id = saveBtn.dataset.id;
    const body = saveBtn.closest('.project-body');
    if (!id || !body) return;

    saveBtn.disabled = true;
    try {
      await saveProjectDraft(id, body, { notify: true, force: true, reloadAfter: true });
    } finally {
      saveBtn.disabled = false;
    }
    return;
  }

  const closeBtn = target ? target.closest('.close-project-btn') : null;
  if (!closeBtn) {
    const telegramBtn = target ? target.closest('.open-telegram-btn') : null;
    const mapsBtn = target ? target.closest('.open-maps-btn') : null;
    if (!telegramBtn && !mapsBtn) return;

    const body = (telegramBtn || mapsBtn).closest('.project-body');
    const fieldName = telegramBtn ? 'telegram_group_link' : 'geo_location_link';
    const raw = String(body?.querySelector(`[data-field="${fieldName}"]`)?.value || '').trim();

    if (!raw) {
      alert(telegramBtn ? 'Додайте посилання на Telegram групу' : 'Додайте посилання на геолокацію');
      return;
    }

    const url = /^https?:\/\//i.test(raw) ? raw : `https://${raw}`;
    window.open(url, '_blank', 'noopener,noreferrer');
    return;
  }

  const id = closeBtn.dataset.id;
  if (!id) return;

  if (!confirm('Закрити цей проект?')) return;

  closeBtn.disabled = true;
  try {
    const r = await fetch(`/api/sales-projects/${id}/close`, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
      }
    });
    const res = await r.json();
    if (!r.ok || !res.ok) throw new Error(res.error || 'Не вдалося закрити проект');
    await loadConstructionProjects();
    rememberOpenProject(id);
  } catch (err) {
    alert(err.message || 'Не вдалося закрити проект');
    closeBtn.disabled = false;
  }
});

document.addEventListener('change', async function (e) {
  const target = e.target instanceof Element ? e.target : null;
  const select = target ? target.closest('select[data-field="electrician"], select[data-field="installation_team"]') : null;
  if (select && IS_OWNER) {
    const isElectrician = select.dataset.field === 'electrician';
    const addToken = isElectrician ? '__add_new_electrician__' : '__add_new_installation_team__';
    const deleteToken = isElectrician ? '__delete_current_electrician__' : '__delete_current_installation_team__';
    if (select.value === addToken) {
      const type = isElectrician ? 'electrician' : 'installation_team';
      const label = isElectrician ? 'електрика' : 'монтажника';
      const name = prompt(`Введіть ім'я ${label}:`);

      if (!name || !name.trim()) {
        select.value = '';
        return;
      }

      try {
        const r = await fetch('/api/construction-staff-options', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          },
          body: JSON.stringify({ type, name: name.trim() })
        });
        const res = await r.json();
        if (!r.ok || !res.ok) throw new Error(res.error || 'Не вдалося додати');

        const projectId = select.closest('.project-body')?.querySelector('.save-project-btn')?.dataset.id;
        await loadConstructionProjects();
        if (projectId) rememberOpenProject(projectId);
      } catch (err) {
        alert(err.message || 'Не вдалося додати');
        select.value = '';
      }
      return;
    }

    if (select.value === deleteToken) {
      const previousValue = select.dataset.previousValue || '';
      const previousOption = Array.from(select.options).find(opt => opt.value === previousValue);
      const optionId = previousOption?.dataset?.optionId;

      if (!optionId || !previousValue) {
        alert('Спочатку оберіть елемент зі списку, потім утримуйте поле 5 сек і виберіть видалення');
        select.value = previousValue;
        return;
      }
      if (!confirm(`Видалити "${previousValue}"?`)) {
        select.value = previousValue;
        return;
      }

      try {
        const r = await fetch(`/api/construction-staff-options/${optionId}`, {
          method: 'DELETE',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          }
        });
        const res = await r.json();
        if (!r.ok || !res.ok) throw new Error(res.error || 'Не вдалося видалити');

        const projectId = select.closest('.project-body')?.querySelector('.save-project-btn')?.dataset.id;
        await loadConstructionProjects();
        if (projectId) rememberOpenProject(projectId);
      } catch (err) {
        alert(err.message || 'Не вдалося видалити');
        select.value = previousValue;
      }
      return;
    }

    if (!isAddOptionToken(select.value) && !isDeleteOptionToken(select.value)) {
      select.dataset.previousValue = select.value;
    }
  }

  const field = target ? target.closest('[data-field]') : null;
  if (!field) return;
  const body = field.closest('.project-body');
  if (!body) return;

  if (field.type === 'file') {
    const id = getProjectIdFromBody(body);
    saveProjectDraft(id, body, { force: true, notify: false });
    return;
  }

  scheduleProjectAutosave(body, 400);
});

document.addEventListener('input', function (e) {
  const target = e.target instanceof Element ? e.target : null;
  const field = target ? target.closest('[data-field]') : null;
  if (!field) return;
  if (field.type === 'file' || field.tagName === 'SELECT') return;

  const body = field.closest('.project-body');
  if (!body) return;

  scheduleProjectAutosave(body, 900);
});

window.addEventListener('beforeunload', saveAllProjectsOnExit);
document.addEventListener('visibilitychange', () => {
  if (document.visibilityState === 'hidden') saveAllProjectsOnExit();
});

function unlockStaffDelete(select) {
  if (!IS_OWNER) return;
  if (!select || !select.matches('select[data-field="electrician"], select[data-field="installation_team"]')) return;

  const deleteToken = select.dataset.field === 'electrician'
    ? '__delete_current_electrician__'
    : '__delete_current_installation_team__';

  const deleteOption = Array.from(select.options).find(opt => opt.value === deleteToken);
  if (!deleteOption) return;

  deleteOption.hidden = false;
  setTimeout(() => {
    deleteOption.hidden = true;
    if (select.value === deleteToken) {
      select.value = select.dataset.previousValue || '';
    }
  }, 10000);
}

document.addEventListener('pointerdown', function (e) {
  const target = e.target instanceof Element ? e.target : null;
  const select = target ? target.closest('select[data-field="electrician"], select[data-field="installation_team"]') : null;
  if (!select || !IS_OWNER) return;

  const timer = setTimeout(() => unlockStaffDelete(select), 5000);
  staffDeleteUnlockTimers.set(select, timer);
});

['pointerup', 'pointerleave', 'pointercancel'].forEach(eventName => {
  document.addEventListener(eventName, function (e) {
    const target = e.target instanceof Element ? e.target : null;
    const select = target ? target.closest('select[data-field="electrician"], select[data-field="installation_team"]') : null;
    if (!select) return;
    const timer = staffDeleteUnlockTimers.get(select);
    if (timer) {
      clearTimeout(timer);
      staffDeleteUnlockTimers.delete(select);
    }
  });
});
</script>

@include('partials.nav.bottom')
@endsection
