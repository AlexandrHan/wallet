@push('styles')
  <link rel="stylesheet" href="/css/project.css?v={{ filemtime(public_path('css/project.css')) }}">
@endpush

@extends('layouts.app')

@section('content')
<main class="projects-main">

  <div class="projects-title-card">
    <div class="projects-title">
      🏗 Проекти СЕС
    </div>
  </div>

  <div style="padding:0 0 10px; display:flex; flex-direction:column; gap:6px;">
    <input type="search" id="projectsSearch" class="btn" placeholder="🔍 Пошук по імені клієнта..." style="width:100%; box-sizing:border-box;">
    <div style="display:flex; gap:6px; width:100%;">
      <select id="filterElectrician" class="btn" style="flex:1; min-width:0; width:50%; box-sizing:border-box;">
        <option value="">⚡ Всі електрики</option>
      </select>
      <select id="filterInstallationTeam" class="btn" style="flex:1; min-width:0; width:50%; box-sizing:border-box;">
        <option value="">🏗 Всі бригади</option>
      </select>
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
    { id: null, name: 'Самойленко' },
  ],
};

const OPEN_PROJECT_KEY = 'construction_open_project_id';
const rememberOpenProject = (id) => localStorage.setItem(OPEN_PROJECT_KEY, String(id));
const getRememberedOpenProject = () => {
  const value = localStorage.getItem(OPEN_PROJECT_KEY);
  return value ? String(value) : null;
};
const clearRememberedOpenProject = () => localStorage.removeItem(OPEN_PROJECT_KEY);

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

  if (!hasFile && prev === snapshot) {
    if (notify) alert('Змін немає');
    return;
  }
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
    body.querySelectorAll('input[type="file"][data-field]').forEach(input => {
      input.value = '';
    });

    const historyPanel = body.querySelector('.project-history-panel');
    if (historyPanel && historyPanel.classList.contains('is-open')) {
      await loadProjectHistory(id, body, true);
    }

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

function setAllProjectSections(body, open) {
  if (!body) return;
  body.querySelectorAll('.project-section').forEach(section => {
    section.classList.toggle('is-open', open);
  });
  const toggleBtn = body.querySelector('.project-expand-toggle');
  if (toggleBtn) {
    toggleBtn.textContent = open ? 'Згорнути проект' : 'Розкрити проект';
  }
}

function renderProjectHistory(history) {
  if (!Array.isArray(history) || history.length === 0) {
    return '<div class="project-history-empty">Історія змін поки що порожня</div>';
  }

  return history.map(item => `
    <div class="project-history-item">
      <div class="project-history-top">
        <div class="project-history-actor">${esc(item.actor_name || 'Невідомий')}</div>
        <div class="project-history-date">${esc(item.created_at || '')}</div>
      </div>
      <div class="project-history-meta">
        <span>${esc(item.section_name || 'Інше')}</span>
        <span>•</span>
        <span>${esc(item.field_name || 'Зміна')}</span>
      </div>
      <div class="project-history-values">
        <div class="project-history-old">Було: ${esc(item.old_value || '—')}</div>
        <div class="project-history-new">Стало: ${esc(item.new_value || '—')}</div>
      </div>
    </div>
  `).join('');
}

async function loadProjectHistory(projectId, body, force = false) {
  const historyBody = body?.querySelector('.project-history-body');
  if (!projectId || !historyBody) return;

  if (!force && historyBody.dataset.loaded === '1') return;

  historyBody.innerHTML = '<div class="project-history-loading">Завантаження...</div>';

  try {
    const r = await fetch(`/api/sales-projects/${projectId}/history`);
    const res = await r.json();
    if (!r.ok) throw new Error(res.error || 'Не вдалося завантажити історію');

    historyBody.innerHTML = renderProjectHistory(res.history || []);
    historyBody.dataset.loaded = '1';
  } catch (err) {
    historyBody.innerHTML = `<div class="project-history-empty">${esc(err.message || 'Не вдалося завантажити історію')}</div>`;
  }
}

function syncProjectCardPreview(body) {
  if (!body) return;
  const card = body.closest('.project-card');
  if (!card) return;

  const electrician = String(body.querySelector('[data-field="electrician"]')?.value || '').trim();
  const team = String(body.querySelector('[data-field="installation_team"]')?.value || '').trim();
  const clientName = String(body.querySelector('[data-field="client_name"]')?.value || '').trim();

  const electricianEl = card.querySelector('[data-project-preview="electrician"]');
  const teamEl = card.querySelector('[data-project-preview="team"]');
  const nameEl = card.querySelector('[data-project-preview="client"]');

  if (electricianEl) electricianEl.textContent = electrician || 'Електрик не вказаний';
  if (teamEl) teamEl.textContent = team || 'Бригада не вказана';
  if (nameEl && clientName) nameEl.textContent = clientName;
}

function syncProjectCardState(body) {
  if (!body) return;
  const card = body.closest('.project-card');
  const cs = card?.dataset.constructionStatus || '';
  const hasDefects = cs === 'has_deficiencies';
  const defectsNote = String(body.querySelector('[data-field="defects_note"]')?.value || '').trim();
  const hasAnyDefects = hasDefects || cs === 'deficiencies_fixed' || defectsNote !== '';
  const closeBtn = body.querySelector('.close-project-btn');

  if (card) {
    card.classList.toggle('project-card--defects', hasAnyDefects);
  }

  if (closeBtn && !closeBtn.textContent.includes('✅ Проект закритий')) {
    closeBtn.classList.toggle('is-locked', hasDefects);
    closeBtn.disabled = hasDefects;
    closeBtn.textContent = hasDefects ? '⚠️ Є недоліки, закриття заборонено' : '🔒 Закрити проект';
  }
}

async function loadConstructionProjects() {
  const container = document.getElementById('constructionProjectsContainer');
  if (!container) return;

  const openBody = Array.from(container.querySelectorAll('.project-body'))
    .find(body => window.getComputedStyle(body).display !== 'none');
  const currentOpenId = getProjectIdFromBody(openBody);
  if (currentOpenId) {
    rememberOpenProject(currentOpenId);
  }
  const openProjectId = getRememberedOpenProject();

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

  // Populate filter selects from STAFF_OPTIONS
  const elSelect = document.getElementById('filterElectrician');
  const teamSelect = document.getElementById('filterInstallationTeam');
  if (elSelect && elSelect.options.length === 1) {
    STAFF_OPTIONS.electrician.forEach(opt => {
      const o = document.createElement('option');
      o.value = opt.name;
      o.textContent = opt.name;
      elSelect.appendChild(o);
    });
    const bm = document.createElement('option');
    bm.value = 'Без монтажних робіт';
    bm.textContent = 'Без монтажних робіт';
    elSelect.appendChild(bm);
  }
  if (teamSelect && teamSelect.options.length === 1) {
    STAFF_OPTIONS.installation_team.forEach(opt => {
      const o = document.createElement('option');
      o.value = opt.name;
      o.textContent = opt.name;
      teamSelect.appendChild(o);
    });
    const bm = document.createElement('option');
    bm.value = 'Без монтажних робіт';
    bm.textContent = 'Без монтажних робіт';
    teamSelect.appendChild(bm);
  }

  // Load main list + lightweight status summary in parallel
  const [rMain, rStatus] = await Promise.all([
    fetch('/api/sales-projects?layer=projects'),
    fetch('/api/projects/status-summary'),
  ]);
  const projects = await rMain.json();
  const statusProjects = rStatus.ok ? await rStatus.json() : [];

  const visibleProjects = [...projects].filter(p => !p.is_retail);
  const allVisibleProjects = statusProjects; // already filtered server-side

  const sortedProjects = visibleProjects.sort((a, b) => {
    const aHasDefects = a.construction_status === 'has_deficiencies' || a.construction_status === 'deficiencies_fixed' || String(a.defects_note || '').trim() !== '';
    const bHasDefects = b.construction_status === 'has_deficiencies' || b.construction_status === 'deficiencies_fixed' || String(b.defects_note || '').trim() !== '';

    if (aHasDefects !== bHasDefects) {
      return aHasDefects ? -1 : 1;
    }

    return String(a.client_name || '').localeCompare(String(b.client_name || ''), 'uk', { sensitivity: 'base' });
  });
  container.innerHTML = '';

  // ── Статусні блоки (зверху) ──────────────────────────────────────────────
  (function renderStatusBlocks(projects) {
    const UA_MONTHS = ['Січень','Лютий','Березень','Квітень','Травень','Червень',
                       'Липень','Серпень','Вересень','Жовтень','Листопад','Грудень'];

    const DELIVERY_STAGE = 69593834; // Здача проекту замовнику — обидва монтажі завершені

    const needsFix  = projects.filter(p => p.construction_status === 'has_deficiencies');
    const needsConf = projects.filter(p =>
      p.installation_completed_at &&
      (p.construction_status === 'waiting_quality_check' || p.construction_status === 'deficiencies_fixed')
    );
    // Завершені = salary_pending/paid + projects in final AmoCRM delivery stage (both electrical + panels done)
    const approvedIds = new Set();
    const approved  = projects.filter(p => {
      const isApproved = p.construction_status === 'salary_pending' || p.construction_status === 'salary_paid';
      const isDelivery = Number(p.amo_stage_id) === DELIVERY_STAGE;
      if ((isApproved && p.installation_completed_at) || isDelivery) {
        if (!approvedIds.has(p.id)) { approvedIds.add(p.id); return true; }
      }
      return false;
    });

    // Sort helpers
    const byDateDesc = (a, b) =>
      String(b.installation_completed_at || '0').localeCompare(String(a.installation_completed_at || '0'));

    needsFix.sort(byDateDesc);
    needsConf.sort(byDateDesc);
    approved.sort(byDateDesc);

    // Simple row for each project
    function makeRow(p) {
      const row = document.createElement('div');
      row.style.cssText = 'display:flex;justify-content:space-between;align-items:flex-start;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.07);gap:8px;';
      const dateStr = p.installation_completed_at
        ? new Date(p.installation_completed_at).toLocaleDateString('uk-UA', { day:'2-digit', month:'2-digit', year:'numeric' })
        : '';
      const teamStr = [p.installation_team, p.electrician].filter(Boolean).join(' / ');
      row.innerHTML =
        `<div>` +
          `<div style="font-size:14px;font-weight:600;">${esc(p.client_name || '—')}</div>` +
          `<div style="font-size:11px;opacity:.55;">${esc(teamStr)}</div>` +
        `</div>` +
        `<div style="font-size:12px;opacity:.65;white-space:nowrap;">${esc(dateStr)}</div>`;
      return row;
    }

    // Generic accordion builder
    function makeAccordion(label, count, defaultOpen) {
      const wrap = document.createElement('div');
      wrap.style.cssText = 'margin-bottom:10px;';

      const header = document.createElement('button');
      header.type = 'button';
      header.style.cssText = [
        'width:100%;display:flex;align-items:center;gap:10px;',
        'background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.13);',
        'border-radius:12px;padding:12px 16px;cursor:pointer;',
        'color:inherit;font-size:14px;font-weight:600;text-align:left;',
        defaultOpen ? 'border-bottom-left-radius:0;border-bottom-right-radius:0;' : '',
      ].join('');
      header.innerHTML =
        `<span style="flex:1;">${esc(label)}</span>` +
        `<span style="background:rgba(255,255,255,.18);border-radius:20px;padding:2px 10px;font-size:12px;font-weight:700;">${count}</span>` +
        `<span style="font-size:11px;opacity:.65;">${defaultOpen ? '▼' : '▶'}</span>`;

      const body = document.createElement('div');
      body.style.cssText = [
        'border:1px solid rgba(255,255,255,.13);border-top:none;',
        'border-radius:0 0 12px 12px;padding:10px 12px;',
        defaultOpen ? '' : 'display:none;',
      ].join('');

      header.addEventListener('click', () => {
        const closing = body.style.display !== 'none';
        body.style.display = closing ? 'none' : 'block';
        header.querySelector('span:last-child').textContent = closing ? '▶' : '▼';
        const r = closing ? '12px' : '0';
        header.style.borderBottomLeftRadius = r;
        header.style.borderBottomRightRadius = r;
      });

      wrap.appendChild(header);
      wrap.appendChild(body);
      return { wrap, body };
    }

    // Render "Потребують виправлення"
    if (needsFix.length > 0) {
      const { wrap, body } = makeAccordion('⚠️ Потребують виправлення', needsFix.length, true);
      needsFix.forEach(p => body.appendChild(makeRow(p)));
      container.appendChild(wrap);
    }

    // Render "Очікують підтвердження"
    if (needsConf.length > 0) {
      const { wrap, body } = makeAccordion('⏳ Очікують підтвердження', needsConf.length, true);
      needsConf.forEach(p => body.appendChild(makeRow(p)));
      container.appendChild(wrap);
    }

    // Render "Завершені" with year → month nesting
    if (approved.length > 0) {
      const { wrap: doneWrap, body: doneBody } = makeAccordion('✅ Завершені', approved.length, false);

      // Group by year → month
      const byYear = new Map();
      approved.forEach(p => {
        const d = new Date(p.installation_completed_at || Date.now());
        const y = d.getFullYear();
        const m = d.getMonth(); // 0-11
        if (!byYear.has(y)) byYear.set(y, new Map());
        if (!byYear.get(y).has(m)) byYear.get(y).set(m, []);
        byYear.get(y).get(m).push(p);
      });

      // Sort years desc, months desc
      const sortedYears = [...byYear.keys()].sort((a, b) => b - a);
      sortedYears.forEach((year, yi) => {
        const monthMap = byYear.get(year);
        const yearTotal = [...monthMap.values()].reduce((s, arr) => s + arr.length, 0);
        const yearOpen = yi === 0; // first (most recent) year open

        const yearWrap = document.createElement('div');
        yearWrap.style.cssText = 'margin-bottom:8px;';

        const yearHeader = document.createElement('button');
        yearHeader.type = 'button';
        yearHeader.style.cssText = [
          'width:100%;display:flex;align-items:center;gap:8px;',
          'background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);',
          'border-radius:8px;padding:9px 12px;cursor:pointer;',
          'color:inherit;font-size:13px;font-weight:700;text-align:left;',
          yearOpen ? 'border-bottom-left-radius:0;border-bottom-right-radius:0;' : '',
        ].join('');
        yearHeader.innerHTML =
          `<span style="flex:1;">${year}</span>` +
          `<span style="opacity:.6;font-size:12px;">${yearTotal}</span>` +
          `<span style="font-size:10px;opacity:.55;">${yearOpen ? '▼' : '▶'}</span>`;

        const yearBody = document.createElement('div');
        yearBody.style.cssText = [
          'border:1px solid rgba(255,255,255,.1);border-top:none;',
          'border-radius:0 0 8px 8px;padding:6px 8px;',
          yearOpen ? '' : 'display:none;',
        ].join('');

        yearHeader.addEventListener('click', () => {
          const closing = yearBody.style.display !== 'none';
          yearBody.style.display = closing ? 'none' : 'block';
          yearHeader.querySelector('span:last-child').textContent = closing ? '▶' : '▼';
          const r = closing ? '8px' : '0';
          yearHeader.style.borderBottomLeftRadius = r;
          yearHeader.style.borderBottomRightRadius = r;
        });

        const sortedMonths = [...monthMap.keys()].sort((a, b) => b - a);
        sortedMonths.forEach((month, mi) => {
          const monthProjects = monthMap.get(month)
            .slice()
            .sort((a, b) => String(b.installation_completed_at || '').localeCompare(String(a.installation_completed_at || '')));
          const monthOpen = yearOpen && mi === 0;

          const mWrap = document.createElement('div');
          mWrap.style.cssText = 'margin-bottom:6px;';

          const mHeader = document.createElement('button');
          mHeader.type = 'button';
          mHeader.style.cssText = [
            'width:100%;display:flex;align-items:center;gap:8px;',
            'background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);',
            'border-radius:6px;padding:7px 10px;cursor:pointer;',
            'color:inherit;font-size:13px;font-weight:600;text-align:left;',
            monthOpen ? 'border-bottom-left-radius:0;border-bottom-right-radius:0;' : '',
          ].join('');
          mHeader.innerHTML =
            `<span style="flex:1;">${UA_MONTHS[month]}</span>` +
            `<span style="opacity:.6;font-size:12px;">${monthProjects.length}</span>` +
            `<span style="font-size:10px;opacity:.55;">${monthOpen ? '▼' : '▶'}</span>`;

          const mBody = document.createElement('div');
          mBody.style.cssText = [
            'border:1px solid rgba(255,255,255,.08);border-top:none;',
            'border-radius:0 0 6px 6px;padding:6px 8px;',
            monthOpen ? '' : 'display:none;',
          ].join('');

          mHeader.addEventListener('click', () => {
            const closing = mBody.style.display !== 'none';
            mBody.style.display = closing ? 'none' : 'block';
            mHeader.querySelector('span:last-child').textContent = closing ? '▶' : '▼';
            const r = closing ? '6px' : '0';
            mHeader.style.borderBottomLeftRadius = r;
            mHeader.style.borderBottomRightRadius = r;
          });

          monthProjects.forEach(p => mBody.appendChild(makeRow(p)));

          mWrap.appendChild(mHeader);
          mWrap.appendChild(mBody);
          yearBody.appendChild(mWrap);
        });

        yearWrap.appendChild(yearHeader);
        yearWrap.appendChild(yearBody);
        doneBody.appendChild(yearWrap);
      });

      container.appendChild(doneWrap);
    }

    // Separator before main list
    if (needsFix.length + needsConf.length + approved.length > 0) {
      const sep = document.createElement('div');
      sep.style.cssText = 'height:1px;background:rgba(255,255,255,.08);margin:6px 0 14px;';
      container.appendChild(sep);
    }
  })(allVisibleProjects);

  // Кнопка Згорнути/Розкрити всі групи
  const toggleAllBtn = document.createElement('button');
  toggleAllBtn.type = 'button';
  toggleAllBtn.className = 'btn';
  toggleAllBtn.style.cssText = 'margin-bottom:12px; width:100%;';
  toggleAllBtn.textContent = 'Згорнути всі';
  toggleAllBtn.dataset.allOpen = '1';
  toggleAllBtn.addEventListener('click', function () {
    const isOpen = this.dataset.allOpen === '1';
    container.querySelectorAll('.project-stage-body').forEach(body => {
      body.style.display = isOpen ? 'none' : '';
    });
    this.dataset.allOpen = isOpen ? '0' : '1';
    this.textContent = isOpen ? 'Розкрити всі' : 'Згорнути всі';
  });
  container.appendChild(toggleAllBtn);

  const activeProjects = sortedProjects.filter(p => p.status !== 'completed');
  const completedProjects = sortedProjects.filter(p => p.status === 'completed');

  function buildProjectCard(p) {
    const isClosed = p.status === 'completed';
    const hasDefects = p.construction_status === 'has_deficiencies';
    const hasAnyDefects = hasDefects
      || p.construction_status === 'deficiencies_fixed'
      || String(p.defects_note || '').trim() !== '';
    const hasGreenTariff = !!p.has_green_tariff;
    const photoHtml = p.defects_photo_url
      ? `<a href="${p.defects_photo_url}" target="_blank" style="font-size:12px; opacity:.8;">📎 Поточне фото недоліків</a>`
      : '';
    const electricianValues = [...STAFF_OPTIONS.electrician];
    const teamValues = [...STAFF_OPTIONS.installation_team];
    const imageThumbs = (p.attachments || [])
      .filter(a => a.is_image)
      .map(a => `<a href="${a.url}" target="_blank"><img src="${a.url}" alt="${esc(a.name)}" class="project-thumb"></a>`)
      .join('');
    const fileLinks = (p.attachments || [])
      .filter(a => !a.is_image)
      .map(a => `<a href="${a.url}" target="_blank" class="project-file-link">📎 ${esc(a.name)}</a>`)
      .join('');

    const electricianOptionsHtml = electricianValues.map(opt => `
      <option value="${esc(opt.name)}" data-option-id="${opt.id ?? ''}" ${String(p.electrician || '') === String(opt.name) ? 'selected' : ''}>${esc(opt.name)}</option>
    `).join('');
    const teamOptionsHtml = teamValues.map(opt => `
      <option value="${esc(opt.name)}" data-option-id="${opt.id ?? ''}" ${String(p.installation_team || '') === String(opt.name) ? 'selected' : ''}>${esc(opt.name)}</option>
    `).join('');
    const amoIdentityHtml = (p.amo_deal_name || p.amo_deal_id)
      ? `<div style="font-size:11px; opacity:.62; margin-top:3px;">${esc([p.amo_deal_name, p.amo_deal_id ? `AMO #${p.amo_deal_id}` : ''].filter(Boolean).join(' · '))}</div>`
      : '';

    const card = document.createElement('div');
    card.className = 'card project-card';
    card.dataset.constructionStatus = p.construction_status || '';
    if (hasGreenTariff) card.classList.add('project-card--green');
    if (hasAnyDefects) card.classList.add('project-card--defects');
    card.innerHTML = `
      <div class="project-header">
        <div class="project-header-row">
          <div>
            <div class="project-header-name" data-project-preview="client">${esc(p.client_name)}</div>
            ${amoIdentityHtml}
          </div>
          <div class="project-header-meta">
            ${esc(p.created_at)} ${isClosed ? '• ✅ Закритий' : ''}
          </div>
        </div>
        <div class="project-header-row project-header-sub">
          <div data-project-preview="electrician">${esc(p.electrician || 'Електрик не вказаний')}</div>
          <div class="project-header-meta" data-project-preview="team" style="font-size:12px; opacity:.78;">
            ${esc(p.installation_team || 'Бригада не вказана')}
          </div>
        </div>
        ${p.mounting_system ? `
        <div class="project-header-row project-header-sub" style="margin-top:2px;">
          <div style="font-size:11px; opacity:.7;">🔩 ${esc(p.mounting_system)}</div>
        </div>` : ''}
        ${(p.panel_check_status === 'done' || p.electric_check_status === 'done') ? `
        <div class="project-header-row project-header-sub" style="margin-top:4px; gap:6px; flex-wrap:wrap;">
          ${p.electric_check_status === 'done' ? `<span style="font-size:11px; font-weight:700; color:#111; background:#facc15; border-radius:6px; padding:2px 8px; line-height:1.6;">⚡ Електрик завершив</span>` : ''}
          ${p.panel_check_status === 'done' ? `<span style="font-size:11px; font-weight:700; color:#111; background:#4ade80; border-radius:6px; padding:2px 8px; line-height:1.6;">✅ Монтажники завершили</span>` : ''}
        </div>` : ''}
      </div>

      <div class="project-body">
        <button type="button" class="btn project-expand-toggle">Розкрити проект</button>

        <div class="project-dates-row">
          <div class="project-date-col">
            <div class="project-field-label" style="text-align:center;">Дата початку монтажу інверторної частини</div>
            <input type="date" class="btn project-input-full" data-field="electric_work_start_date" value="${esc(p.electric_work_start_date)}">
            <div class="project-field-label" style="text-align:center;">Тривалість робіт електрика (днів)</div>
            <input type="number" min="1" max="365" class="btn project-input-full" data-field="electric_work_days" value="${esc(p.electric_work_days || 1)}">
          </div>
          <div class="project-date-col">
            <div class="project-field-label" style="text-align:center;">Дата початку монтажу ФЕМ</div>
            <input type="date" class="btn project-input-full" data-field="panel_work_start_date" value="${esc(p.panel_work_start_date)}">
            <div class="project-field-label" style="text-align:center;">Тривалість монтажу ФЕМ (днів)</div>
            <input type="number" min="1" max="365" class="btn project-input-full" data-field="panel_work_days" value="${esc(p.panel_work_days || 1)}">
          </div>
        </div>

        <div class="project-section" data-section>
          <button type="button" class="project-section-toggle">
            <span>Дані клієнта</span>
            <span class="project-section-caret">▸</span>
          </button>
          <div class="project-section-body">
            <div class="project-field-label">Посилання на Telegram групу</div>
            <input class="btn project-input-full" data-field="telegram_group_link" value="${esc(p.telegram_group_link)}" placeholder="Вставте посилання на Telegram">

            <button class="btn project-action-btn project-action-btn--telegram open-telegram-btn">
              <img src="/img/telegram.png" alt="Telegram" class="project-action-icon">
              <span>Відкрити Telegram</span>
            </button>

            <div class="project-field-label">Посилання на геолокацію</div>
            <input class="btn project-input-full" data-field="geo_location_link" value="${esc(p.geo_location_link)}" placeholder="Вставте посилання на геолокацію">

            <button class="btn project-action-btn project-action-btn--maps open-maps-btn">
              <span style="font-size:18px; line-height:1;">📍</span>
              <span>Відкрити Google Maps</span>
            </button>

            <div class="project-field-label">Номер телефону</div>
            <input class="btn project-input-full" data-field="phone_number" value="${esc(p.phone_number)}" placeholder="+380...">

            <div class="project-green-box">
              <div>
                <div class="project-field-label" style="margin-bottom:0;">Зелений тариф</div>

              </div>
              <input type="hidden" data-field="has_green_tariff" value="${hasGreenTariff ? '1' : '0'}">
              <div class="segmented project-green-segmented">
                <button type="button" class="green-tariff-btn ${hasGreenTariff ? 'active' : ''}" data-value="1">Є</button>
                <button type="button" class="green-tariff-btn ${!hasGreenTariff ? 'active' : ''}" data-value="0">Немає</button>
              </div>
            </div>
          </div>
        </div>

        <div class="project-section" data-section>
          <button type="button" class="project-section-toggle">
            <span>Обладнання</span>
            <span class="project-section-caret">▸</span>
          </button>
          <div class="project-section-body">
            <div class="project-field-label">Інвертор</div>
            <input class="btn project-input-full" data-field="inverter" value="${esc(p.inverter)}" placeholder="Інвертор">
            <div class="project-field-label">BMS</div>
            <input class="btn project-input-full" data-field="bms" value="${esc(p.bms)}" placeholder="BMS">

            <div style="margin-bottom:12px;">
              <div class="project-two-col-head">
                <div class="project-two-col-head-main" style="wi">АКБ</div>
                <div class="project-two-col-head-side">К-сть</div>
              </div>
              <div class="project-two-col-row">
                <input class="btn project-two-col-row-main" data-field="battery_name" value="${esc(p.battery_name)}" placeholder="Назва АКБ">
                <input type="number" class="btn project-two-col-row-side" data-field="battery_qty" value="${p.battery_qty ?? ''}" placeholder="0">
              </div>
            </div>

            <div>
              <div class="project-two-col-head">
                <div class="project-two-col-head-main">ФЕМ</div>
                <div class="project-two-col-head-side">К-сть</div>
              </div>
              <div class="project-two-col-row">
                <input class="btn project-two-col-row-main" data-field="panel_name" value="${esc(p.panel_name)}" placeholder="Назва ФЕМ">
                <input type="number" class="btn project-two-col-row-side" data-field="panel_qty" value="${p.panel_qty ?? ''}" placeholder="0">
              </div>
            </div>

            <div class="project-field-label">Система кріплень</div>
            <input class="btn project-input-full" data-field="mounting_system" value="${esc(p.mounting_system)}" placeholder="Система кріплень">

            <hr class="project-divider" style="margin:14px 0 10px;">

            <div class="project-subsection">
              <button type="button" class="project-section-toggle project-subsection-toggle">
                <span>📦 Доставлено на об'єкт</span>
                <span class="project-section-caret">▸</span>
              </button>
              <div class="project-subsection-body" style="display:none; padding-top:10px;">
                <div class="project-field-label" style="font-size:11px; opacity:.65; margin-bottom:6px;">
                  Ці поля синхронізуються з amoCRM. Якщо заповнені вручну — amoCRM їх не перезаписує.
                </div>
                <div class="project-field-label">Інвертор</div>
                <input class="btn project-input-full" data-field="delivered_inverter" value="${esc(p.delivered_inverter)}" placeholder="Інвертор на об'єкті">
                <div class="project-field-label">BMS</div>
                <input class="btn project-input-full" data-field="delivered_bms" value="${esc(p.delivered_bms)}" placeholder="BMS на об'єкті">
                <div class="project-field-label">АКБ</div>
                <input class="btn project-input-full" data-field="delivered_battery" value="${esc(p.delivered_battery)}" placeholder="АКБ на об'єкті">
                <div class="project-field-label">ФЕМ</div>
                <input class="btn project-input-full" data-field="delivered_panels" value="${esc(p.delivered_panels)}" placeholder="ФЕМ на об'єкті">
              </div>
            </div>
          </div>
        </div>

        <div class="project-section" data-section>
          <button type="button" class="project-section-toggle">
            <span>Персонал</span>
            <span class="project-section-caret">▸</span>
          </button>
          <div class="project-section-body">
            <div class="project-field-label">Менеджер</div>
            <input class="btn project-input-full" value="${esc(p.manager_name || '—')}" readonly>

            <hr class="project-divider" style="margin:8px 0 12px;">

            <div class="project-field-label">Електрик</div>
            <select class="btn project-input-full" data-field="electrician">
              <option value="">Оберіть електрика</option>
              <option value="Без монтажних робіт" ${'Без монтажних робіт' === String(p.electrician || '') ? 'selected' : ''}>Без монтажних робіт</option>
              ${electricianOptionsHtml}
              ${IS_OWNER ? '<option value="__add_new_electrician__">➕ Додати електрика...</option>' : ''}
              ${IS_OWNER ? '<option value="__delete_current_electrician__" hidden>🗑 Видалити вибраного...</option>' : ''}
            </select>

            <div class="project-field-label">Електрик примітки</div>
            <textarea class="btn project-textarea" data-field="electrician_note" placeholder="Примітки для електрика...">${esc(p.electrician_note || '')}</textarea>

            ${p.electrician_task_note ? `
              <div class="project-field-label">Електрик: завдання з таблиці</div>
              <div class="btn project-textarea" style="text-align:left; cursor:default;">${esc(p.electrician_task_note)}</div>
            ` : ''}

            <hr class="project-divider" style="margin:8px 0 12px;">

            <div class="project-field-label">Монтажна бригада</div>
            <select class="btn project-input-full" data-field="installation_team">
              <option value="">Оберіть бригаду</option>
              <option value="Без монтажних робіт" ${'Без монтажних робіт' === String(p.installation_team || '') ? 'selected' : ''}>Без монтажних робіт</option>
              ${teamOptionsHtml}
              ${IS_OWNER ? '<option value="__add_new_installation_team__">➕ Додати монтажника...</option>' : ''}
              ${IS_OWNER ? '<option value="__delete_current_installation_team__" hidden>🗑 Видалити вибраного...</option>' : ''}
            </select>

            <div class="project-field-label">Монтажна бригада примітки</div>
            <textarea class="btn project-textarea" data-field="installation_team_note" placeholder="Примітки для монтажної бригади...">${esc(p.installation_team_note || '')}</textarea>

            ${p.installation_team_task_note ? `
              <div class="project-field-label">Монтажна бригада: завдання з таблиці</div>
              <div class="btn project-textarea" style="text-align:left; cursor:default;">${esc(p.installation_team_task_note)}</div>
            ` : ''}

            <div class="project-field-label">Доп. роботи</div>
            <input class="btn project-input-full" data-field="extra_works" value="${esc(p.extra_works)}" placeholder="Вкажіть додаткові роботи">
          </div>
        </div>

        <div class="project-section" data-section>
          <button type="button" class="project-section-toggle">
            <span>Недоліки</span>
            <span class="project-section-caret">▸</span>
          </button>
          <div class="project-section-body">
            ${p.construction_status === 'has_deficiencies' ? `
              <div style="margin-bottom:10px; padding:8px 10px; border-radius:7px; background:rgba(229,62,62,.15); font-size:13px; color:#f88; font-weight:600;">
                ❌ Є недоліки — очікує виправлення від монтажника
              </div>` : p.construction_status === 'deficiencies_fixed' ? `
              <div style="margin-bottom:10px; padding:8px 10px; border-radius:7px; background:rgba(212,160,23,.15); font-size:13px; color:#f4c842; font-weight:600;">
                🔧 Недоліки виправлені — можна затвердити
              </div>` : ''}

            ${(p.quality_defect_photos || []).length ? `
              <div class="project-field-label">Фото від прораба (контроль якості)</div>
              <div style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:10px;">
                ${(p.quality_defect_photos || []).map(url => `<img src="${url}" style="width:60px; height:60px; object-fit:cover; border-radius:6px; cursor:pointer;"
                  onclick="window.open('${url}','_blank')">`).join('')}
              </div>` : ''}

            ${p.quality_voice_memo_url ? `
              <div class="project-field-label">Голосовий коментар прораба</div>
              <audio controls src="${p.quality_voice_memo_url}" style="width:100%; height:36px; margin-bottom:10px;"></audio>` : ''}

            <div class="project-field-label" style="margin-top:${(p.quality_defect_photos||[]).length || p.quality_voice_memo_url ? '4px' : '10px'};">Опис проблемних місць</div>
            <textarea class="btn project-textarea" data-field="defects_note" placeholder="Опис недоліків...">${esc(p.defects_note)}</textarea>

            <div class="project-field-label">Головне фото недоліків</div>
            ${photoHtml}
            <input type="file" data-field="defects_photo" accept="image/*" class="project-input-full" style="margin-bottom:10px;">
          </div>
        </div>

        <div class="project-section" data-section>
          <button type="button" class="project-section-toggle">
            <span>Фото та файли</span>
            <span class="project-section-caret">▸</span>
          </button>
          <div class="project-section-body">
            <div class="project-field-label">Фото з телефону</div>
            <input type="file" data-field="photos" accept="image/*" capture="environment" multiple class="project-input-full">
            <div class="project-field-label">Файли</div>
            <input type="file" data-field="attachments" multiple class="project-input-full">
            ${(imageThumbs || fileLinks) ? `
              <div class="project-field-label">Додані матеріали</div>
              ${imageThumbs ? `<div class="project-thumb-grid">${imageThumbs}</div>` : ''}
              ${fileLinks ? `<div>${fileLinks}</div>` : ''}
            ` : ''}
          </div>
        </div>

        <hr class="project-divider project-divider--spaced">

        <button class="btn save-project-btn project-save-btn" data-id="${p.id}" ${isClosed ? 'disabled' : ''}>
          💾 Зберегти
        </button>


        <button class="btn close-project-btn project-close-btn ${hasDefects ? 'is-locked' : ''}" data-id="${p.id}" ${(isClosed || hasDefects) ? 'disabled' : ''}>
          ${isClosed ? '✅ Проект закритий' : hasDefects ? '⚠️ Є недоліки, закриття заборонено' : '🔒 Закрити проект'}
        </button>

        <button type="button" class="btn project-history-toggle-btn" data-project-id="${p.id}">
          🕘 Історія змін
        </button>

        <div class="project-history-panel">
          <div class="project-history-body" data-loaded="0"></div>
        </div>
      </div>
    `;

    card.querySelector('.project-header')?.addEventListener('click', function(){
      const body = card.querySelector('.project-body');
      const isHidden = window.getComputedStyle(body).display === 'none';
      if (!isHidden) {
        const id = getProjectIdFromBody(body);
        saveProjectDraft(id, body, { force: true, notify: false });
        setAllProjectSections(body, false);
        clearRememberedOpenProject();
      } else {
        rememberOpenProject(p.id);
      }
      body.style.display = isHidden ? 'block' : 'none';
    });

    container.appendChild(card);

    const initialBody = card.querySelector('.project-body');
    if (initialBody) {
      initialBody.querySelectorAll('select[data-field="electrician"], select[data-field="installation_team"]').forEach(select => {
        select.dataset.previousValue = select.value || '';
      });
      projectLastSavedSnapshot.set(String(p.id), getProjectSnapshot(initialBody));
      if (openProjectId && String(openProjectId) === String(p.id)) {
        initialBody.style.display = 'block';
      }
    }

    return card;
  }

  // Групи по AmoCRM-етапах (пізніші етапи вгорі)
  const AMO_STAGES = [
    { id: 49782427, name: 'Остаточна оплата' },
    { id: 69593834, name: 'Здача проекту замовнику' },
    { id: 69593830, name: 'Електрична частина' },
    { id: 69593826, name: 'Монтаж сонячних панелей' },
    { id: 69593822, name: 'Заплановане будівництво' },
    { id: 38556550, name: 'Очікування доставки' },
    { id: 69586234, name: 'Комплектація' },
    { id: 38556547, name: 'Частично оплатив' },
  ];

  // Групуємо активні проекти по stage_id
  const stageMap = new Map();
  AMO_STAGES.forEach(s => stageMap.set(s.id, []));
  const noStageProjects = [];

  activeProjects.forEach(p => {
    const stageId = p.amo_stage_id;
    if (stageId && stageMap.has(stageId)) {
      stageMap.get(stageId).push(p);
    } else {
      noStageProjects.push(p);
    }
  });

  // Рендеримо групи в порядку воронки
  AMO_STAGES.forEach(stage => {
    const stageProjects = stageMap.get(stage.id);
    if (!stageProjects || stageProjects.length === 0) return;

    const groupWrap = document.createElement('div');
    groupWrap.className = 'card project-stage-group';
    groupWrap.style.marginTop = '15px';
    groupWrap.innerHTML = `
      <div class="project-stage-header" style="display:flex; justify-content:space-between; align-items:center; cursor:pointer; font-weight:700;">
        <div>${esc(stage.name)}</div>
        <div style="opacity:.7;">${stageProjects.length}</div>
      </div>
      <div class="project-stage-body" style="margin-top:10px;"></div>
    `;

    const stageBody = groupWrap.querySelector('.project-stage-body');
    stageProjects.forEach(p => stageBody.appendChild(buildProjectCard(p)));

    groupWrap.querySelector('.project-stage-header')?.addEventListener('click', function () {
      const body = groupWrap.querySelector('.project-stage-body');
      body.style.display = body.style.display === 'none' ? '' : 'none';
    });

    container.appendChild(groupWrap);
  });

  // Проекти без stage (створені вручну або без прив'язки)
  noStageProjects.forEach(p => container.appendChild(buildProjectCard(p)));

  // Завершені — колапсований блок внизу
  if (completedProjects.length > 0) {
    const completedWrap = document.createElement('div');
    completedWrap.className = 'card project-card';
    completedWrap.style.marginTop = '15px';
    completedWrap.innerHTML = `
      <div class="completed-projects-toggle" style="display:flex; justify-content:space-between; align-items:center; cursor:pointer;">
        <div style="font-weight:800;">✅ Завершені проекти</div>
        <div style="opacity:.7;">${completedProjects.length}</div>
      </div>
      <div class="completed-projects-body" style="display:none;"></div>
    `;

    const completedBody = completedWrap.querySelector('.completed-projects-body');
    completedProjects.forEach(p => completedBody.appendChild(buildProjectCard(p)));

    completedWrap.querySelector('.completed-projects-toggle')?.addEventListener('click', function () {
      const body = completedWrap.querySelector('.completed-projects-body');
      body.style.display = body.style.display === 'none' ? 'block' : 'none';
    });

    container.appendChild(completedWrap);
  }
}

function applyProjectFilters() {
  const q = (document.getElementById('projectsSearch')?.value || '').trim().toLowerCase();
  const filterEl = (document.getElementById('filterElectrician')?.value || '').trim();
  const filterTeam = (document.getElementById('filterInstallationTeam')?.value || '').trim();
  const container = document.getElementById('constructionProjectsContainer');
  if (!container) return;

  container.querySelectorAll('.project-card').forEach(card => {
    const name = card.querySelector('[data-project-preview="client"]')?.textContent?.toLowerCase() || '';
    const el = (card.querySelector('[data-project-preview="electrician"]')?.textContent || '').trim();
    const team = (card.querySelector('[data-project-preview="team"]')?.textContent || '').trim();

    const matchQ = !q || name.includes(q);
    const matchEl = !filterEl || el === filterEl;
    const matchTeam = !filterTeam || team === filterTeam;

    card.style.display = (matchQ && matchEl && matchTeam) ? '' : 'none';
  });

  // показуємо/ховаємо групи залежно від того чи є в них видимі картки
  container.querySelectorAll('.project-stage-group').forEach(group => {
    const hasVisible = Array.from(group.querySelectorAll('.project-card'))
      .some(c => c.style.display !== 'none');
    group.style.display = hasVisible ? '' : 'none';
    if (hasVisible && (q || filterEl || filterTeam)) {
      const stageBody = group.querySelector('.project-stage-body');
      if (stageBody) stageBody.style.display = '';
    }
  });
}

document.addEventListener('DOMContentLoaded', () => {
  loadConstructionProjects().catch(() => alert('Не вдалося завантажити проекти'));

  document.getElementById('projectsSearch')?.addEventListener('input', applyProjectFilters);
  document.getElementById('filterElectrician')?.addEventListener('change', applyProjectFilters);
  document.getElementById('filterInstallationTeam')?.addEventListener('change', applyProjectFilters);
});

document.addEventListener('click', async function(e){
  const target = e.target instanceof Element ? e.target : null;
  const expandBtn = target ? target.closest('.project-expand-toggle') : null;
  if (expandBtn) {
    const body = expandBtn.closest('.project-body');
    const sections = Array.from(body?.querySelectorAll('.project-section') || []);
    const shouldOpen = sections.some(section => !section.classList.contains('is-open'));
    setAllProjectSections(body, shouldOpen);
    return;
  }

  const subsectionBtn = target ? target.closest('.project-subsection-toggle') : null;
  if (subsectionBtn) {
    const subsectionBody = subsectionBtn.closest('.project-subsection')?.querySelector('.project-subsection-body');
    if (subsectionBody) {
      const isHidden = subsectionBody.style.display === 'none';
      subsectionBody.style.display = isHidden ? '' : 'none';
      const caret = subsectionBtn.querySelector('.project-section-caret');
      if (caret) caret.textContent = isHidden ? '▾' : '▸';
    }
    return;
  }

  const sectionBtn = target ? target.closest('.project-section-toggle') : null;
  if (sectionBtn) {
    const section = sectionBtn.closest('.project-section');
    if (!section) return;
    section.classList.toggle('is-open');

    const body = section.closest('.project-body');
    const sections = Array.from(body?.querySelectorAll('.project-section') || []);
    const allOpen = sections.length > 0 && sections.every(item => item.classList.contains('is-open'));
    const expandToggle = body?.querySelector('.project-expand-toggle');
    if (expandToggle) {
      expandToggle.textContent = allOpen ? 'Згорнути проект' : 'Розкрити проект';
    }
    return;
  }

  const tariffBtn = target ? target.closest('.green-tariff-btn') : null;
  if (tariffBtn) {
    const body = tariffBtn.closest('.project-body');
    const hiddenInput = body?.querySelector('[data-field="has_green_tariff"]');
    const stateEl = body?.querySelector('[data-green-tariff-state]');
    const card = tariffBtn.closest('.project-card');
    if (!body || !hiddenInput || !card) return;

    const nextValue = String(tariffBtn.dataset.value || '0');
    hiddenInput.value = nextValue;
    body.querySelectorAll('.green-tariff-btn').forEach(btn => {
      btn.classList.toggle('active', btn === tariffBtn);
    });
    if (stateEl) stateEl.textContent = nextValue === '1' ? 'Є' : 'Немає';
    card.classList.toggle('project-card--green', nextValue === '1');

    scheduleProjectAutosave(body, 200);
    return;
  }

  const saveBtn = target ? target.closest('.save-project-btn') : null;
  if (saveBtn) {
    const id = saveBtn.dataset.id;
    const body = saveBtn.closest('.project-body');
    if (!id || !body) return;

    saveBtn.disabled = true;
    try {
      await saveProjectDraft(id, body, { notify: true, force: true, reloadAfter: false });
      syncProjectCardPreview(body);
      syncProjectCardState(body);
      const historyPanel = body.querySelector('.project-history-panel');
      if (historyPanel && historyPanel.classList.contains('is-open')) {
        await loadProjectHistory(id, body, true);
      }
    } finally {
      saveBtn.disabled = false;
    }
    return;
  }

  const historyBtn = target ? target.closest('.project-history-toggle-btn') : null;
  if (historyBtn) {
    const body = historyBtn.closest('.project-body');
    const historyPanel = body?.querySelector('.project-history-panel');
    const projectId = historyBtn.dataset.projectId;
    if (!body || !historyPanel || !projectId) return;

    const shouldOpen = !historyPanel.classList.contains('is-open');
    historyPanel.classList.toggle('is-open', shouldOpen);
    historyBtn.textContent = shouldOpen ? '🕘 Сховати історію змін' : '🕘 Історія змін';

    if (shouldOpen) {
      await loadProjectHistory(projectId, body);
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

  if (field.dataset.field === 'electrician' || field.dataset.field === 'installation_team' || field.dataset.field === 'client_name') {
    syncProjectCardPreview(body);
  }
  if (field.dataset.field === 'defects_note') {
    syncProjectCardState(body);
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

  if (field.dataset.field === 'defects_note') {
    syncProjectCardState(body);
  }

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
