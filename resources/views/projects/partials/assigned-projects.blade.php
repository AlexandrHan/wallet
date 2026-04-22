<main class="projects-main">

  <style>
    @media (max-width: 768px) {
      .project-day-heading {
        text-align: center;
      }
    }
    .acc-section { margin-bottom: 10px; }
    .acc-header {
      width: 100%; display: flex; align-items: center; gap: 10px;
      background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.13);
      border-radius: 12px; padding: 13px 16px; cursor: pointer;
      color: inherit; font-size: 15px; font-weight: 600; text-align: left;
      transition: background .15s;
    }
    .acc-header:active { background: rgba(255,255,255,.12); }
    .acc-header--open { border-bottom-left-radius: 0; border-bottom-right-radius: 0; }
    .acc-title { flex: 1; }
    .acc-count {
      background: rgba(255,255,255,.18); border-radius: 20px;
      padding: 2px 10px; font-size: 13px; font-weight: 700; min-width: 26px; text-align: center;
    }
    .acc-chevron { font-size: 11px; opacity: .65; }
    .acc-body {
      border: 1px solid rgba(255,255,255,.13); border-top: none;
      border-radius: 0 0 12px 12px; padding: 10px;
    }
    .acc-empty { padding: 10px 4px; opacity: .55; font-size: 14px; }
  </style>

  <div class="projects-title-card">
    <div class="projects-title">
      {{ $title }}
    </div>
  </div>

  <div id="{{ $containerId }}"></div>

</main>

{{-- QC defect photo preview modal --}}
<div id="qcPhotoModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.85); z-index:9999; align-items:center; justify-content:center;"
  onclick="if(event.target===this)this.style.display='none'">
  <img id="qcPhotoModalImg" src="" style="max-width:95vw; max-height:90vh; border-radius:8px;">
  <button onclick="document.getElementById('qcPhotoModal').style.display='none'"
    style="position:absolute; top:16px; right:16px; background:none; border:none; color:#fff; font-size:28px; cursor:pointer;">✕</button>
</div>

<script>
document.addEventListener('DOMContentLoaded', async function () {
  const AUTH_USER = @json(auth()->user());
  const container = document.getElementById(@json($containerId));
  const MATCH_FIELD = @json($matchField);
  const ASSIGNMENT_LABEL = @json($assignmentLabel);
  const ASSIGNMENT_MAP = @json($assignmentMap);
  const EMPTY_TEXT = @json($emptyText);
  const SCHEDULE_FIELD = @json($scheduleField ?? null);
  const SCHEDULE_DURATION_FIELD = @json($scheduleDurationField ?? null);
  const SCHEDULE_DATES_KEY = @json($scheduleDatesKey ?? null);
  const RANGE_DAYS = @json($rangeDays ?? 7);
  const ACCORDION_MODE = @json($accordionMode ?? false);
  const REFRESH_MS = 8000;
  const WEEKDAY_LABELS = ['Неділя', 'Понеділок', 'Вівторок', 'Середа', 'Четвер', "П'ятниця", 'Субота'];

  let savedOpen = {};          // cardKey → { bodyVisible, openSectionIndexes[] }
  let savedAccordionState = {}; // accKey → open (bool), persists across refreshes
  let savedExtraWorks = {};    // projectId → string, preserves unsaved textarea content

  function captureOpenState() {
    savedOpen = {};
    container.querySelectorAll('[data-card-key]').forEach(card => {
      const key = card.dataset.cardKey;
      const body = card.querySelector('.project-body');
      if (!body) return;

      const isCollapsed = window.getComputedStyle(body).display === 'none';
      if (isCollapsed) {
        savedOpen[key] = { bodyVisible: false, openSections: [] };
        return;
      }

      const openSections = [];
      body.querySelectorAll('.project-section').forEach((s, i) => {
        if (s.classList.contains('is-open')) openSections.push(i);
      });
      savedOpen[key] = { bodyVisible: true, openSections };

      const ta = body.querySelector('.extra-works-textarea');
      if (ta) savedExtraWorks[ta.dataset.projectId] = ta.value;
    });
  }

  function restoreOpenState() {
    container.querySelectorAll('[data-card-key]').forEach(card => {
      const key = card.dataset.cardKey;
      const state = savedOpen[key];
      // Cards not in savedOpen are new — leave them at default (visible)
      if (!state) return;
      const body = card.querySelector('.project-body');
      if (!body) return;

      if (!state.bodyVisible) {
        body.style.display = 'none';
        return;
      }

      body.style.display = 'block';
      const sections = body.querySelectorAll('.project-section');
      state.openSections.forEach(i => {
        if (sections[i]) sections[i].classList.add('is-open');
      });
      const expandBtn = body.querySelector('.project-expand-toggle');
      if (expandBtn && sections.length > 0) {
        const allOpen = Array.from(sections).every(s => s.classList.contains('is-open'));
        expandBtn.textContent = allOpen ? 'Згорнути проект' : 'Розкрити проект';
      }

      const ta = body.querySelector('.extra-works-textarea');
      if (ta && savedExtraWorks.hasOwnProperty(ta.dataset.projectId)) {
        ta.value = savedExtraWorks[ta.dataset.projectId];
      }
    });
  }

  if (!container) return;

  const expectedValue = String(
    ASSIGNMENT_MAP[String(AUTH_USER?.actor || '')] || AUTH_USER?.name || ''
  ).trim();

  function esc(v) {
    return String(v ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function matchesAssignment(projectValue) {
    const a = String(projectValue || '').trim().toLowerCase();
    const b = expectedValue.toLowerCase();
    const c = String(AUTH_USER?.name || '').trim().toLowerCase();
    if (a === '') return false;
    if (a === b || (c && a === c)) return true;
    // Multi-team: "Кукуяка, Шевченко" → split by comma and check each part
    const parts = a.split(',').map(s => s.trim());
    return parts.includes(b) || (c && parts.includes(c));
  }

  function normalizeExternalUrl(url) {
    const value = String(url || '').trim();
    if (!value) return '';
    if (/^(https?:|tg:|geo:)/i.test(value)) return value;
    return `https://${value}`;
  }

  function normalizePhoneHref(phone) {
    const value = String(phone || '').trim();
    if (!value) return '';
    const cleaned = value.replace(/[^\d+]/g, '');
    return cleaned ? `tel:${cleaned}` : '';
  }

  function parseProjectDate(value) {
    const text = String(value || '').trim();
    if (!text) return null;
    const date = new Date(`${text}T00:00:00`);
    if (Number.isNaN(date.getTime())) return null;
    date.setHours(0, 0, 0, 0);
    return date;
  }

  function parseProjectDuration(project) {
    if (!SCHEDULE_DURATION_FIELD) return 1;
    const raw = Number(project?.[SCHEDULE_DURATION_FIELD]);
    if (!Number.isFinite(raw) || raw < 1) return 1;
    return Math.max(1, Math.round(raw));
  }

  function formatDateShort(isoString) {
    const d = parseProjectDate(isoString);
    if (!d) return '';
    return `${String(d.getDate()).padStart(2,'0')}.${String(d.getMonth()+1).padStart(2,'0')}.${String(d.getFullYear()).slice(-2)}`;
  }

  function makeServiceCard(service) {
    const card = document.createElement('div');
    const telegramUrl = normalizeExternalUrl(service.telegram_group_link);
    const mapsUrl = normalizeExternalUrl(service.geo_location_link);
    const phoneHref = normalizePhoneHref(service.phone_number);

    card.className = 'card project-card';
    card.dataset.cardKey = `service-${service.id}`;
    card.style.border = service.is_urgent ? '2px solid #ff001aad' : '2px solid rgba(59, 130, 246, 0.45)';

    card.innerHTML = `
      <div class="project-header">
        <div class="project-header-row">
          <div class="project-header-name">${esc(service.client_name)}</div>
          <div class="project-header-meta">${esc(service.created_at || '')} • 🛠 Сервіс</div>
        </div>
        <div class="project-header-row project-header-sub">
          <div>${esc(service.settlement || 'Населений пункт не вказаний')}</div>
          <div class="project-header-meta" style="font-size:12px; opacity:.78;">
            ${service.is_urgent ? 'Терміново' : 'Звичайна'}
          </div>
        </div>
      </div>

      <div class="project-body">
        <div class="project-section is-open">
          <button type="button" class="project-section-toggle" disabled>
            <span>Сервіс</span>
            <span class="project-section-caret">▸</span>
          </button>
          <div class="project-section-body">
            <div class="project-field-label">Опис</div>
            <div class="btn project-textarea" style="text-align:left; cursor:default; white-space:pre-wrap;">${esc(service.description || '—')}</div>

            <div class="project-field-label">Телефон</div>
            <a
              class="btn project-input-full ${phoneHref ? '' : 'tg-menu__item--static'}"
              href="${phoneHref ? esc(phoneHref) : '#'}"
              onclick="${phoneHref ? '' : 'return false;'}"
              style="display:block; text-align:left;"
            >
              ${esc(service.phone_number || '—')}
            </a>

            <div style="display:flex; flex-wrap:wrap; gap:8px; margin-top:10px;">
              <a
                class="btn project-action-btn project-action-btn--telegram ${telegramUrl ? '' : 'tg-menu__item--static'}"
                href="${telegramUrl ? esc(telegramUrl) : '#'}"
                ${telegramUrl ? 'target="_blank" rel="noopener noreferrer"' : 'aria-disabled="true"'}
                onclick="${telegramUrl ? '' : 'return false;'}"
              >
                <img src="/img/telegram.png" alt="Telegram" class="project-action-icon">
                <span>Відкрити Telegram</span>
              </a>

              <a
                class="btn project-action-btn project-action-btn--maps ${mapsUrl ? '' : 'tg-menu__item--static'}"
                href="${mapsUrl ? esc(mapsUrl) : '#'}"
                ${mapsUrl ? 'target="_blank" rel="noopener noreferrer"' : 'aria-disabled="true"'}
                onclick="${mapsUrl ? '' : 'return false;'}"
              >
                <span style="font-size:18px; line-height:1;">📍</span>
                <span>Відкрити Google Maps</span>
              </a>
            </div>

            ${(() => {
              if (MATCH_FIELD !== 'electrician') return '';
              const svcStatus = String(service.status || '');
              if (svcStatus === 'closed') return '';
              if (svcStatus === 'waiting_quality_check') {
                return `<div style="width:100%; margin-top:12px; padding:8px 12px; border-radius:8px;
                  background:rgba(212,160,23,.12); color:#f4c842; font-size:13px; font-weight:600; text-align:center;">
                  ⏳ Очікує перевірки прорабом</div>`;
              }
              const svcDate = service.scheduled_date || service.schedule_date || '';
              const todayStr = new Date().toISOString().slice(0, 10);
              if (svcDate && svcDate > todayStr) {
                return `<div style="width:100%; margin-top:12px; padding:10px 14px; border-radius:8px;
                  background:rgba(255,255,255,.06); color:rgba(255,255,255,.4); font-size:13px; text-align:center;">
                  🔒 Завершення доступне з ${svcDate.split('-').reverse().join('.')}</div>`;
              }
              return `<button type="button" class="btn svc-close-btn" data-service-id="${service.id}"
                style="width:100%; margin-top:12px; background:#1a4a6a; color:#fff; font-weight:700;">
                ✅ Завершити сервісний виклик</button>`;
            })()}
          </div>
        </div>
      </div>
    `;

    card.querySelector('.project-header')?.addEventListener('click', function () {
      const body = card.querySelector('.project-body');
      const isHidden = window.getComputedStyle(body).display === 'none';
      body.style.display = isHidden ? 'block' : 'none';
    });

    const closeBtn = card.querySelector('.svc-close-btn');
    if (closeBtn) {
      closeBtn.addEventListener('click', async function (e) {
        e.stopPropagation();
        if (!confirm('Підтвердити завершення сервісного виклику? Прораб отримає сповіщення для перевірки.')) return;
        this.disabled = true;
        this.textContent = 'Відправка...';
        try {
          const r = await fetch(`/api/service-requests/${service.id}/complete`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
          });
          const data = await r.json();
          if (r.ok && data.ok) {
            this.textContent = '⏳ Очікує перевірки';
            this.style.background = 'rgba(212,160,23,.3)';
            this.style.color = '#f4c842';
            setTimeout(() => loadProjects().catch(() => {}), 800);
          } else {
            alert(data.error || 'Помилка');
            this.disabled = false;
            this.textContent = '✅ Завершити сервісний виклик';
          }
        } catch {
          alert('Помилка з\'єднання');
          this.disabled = false;
          this.textContent = '✅ Завершити сервісний виклик';
        }
      });
    }

    return card;
  }

  function getWeekRange() {
    const start = new Date();
    start.setHours(0, 0, 0, 0);

    return Array.from({ length: RANGE_DAYS }, (_, index) => {
      const date = new Date(start);
      date.setDate(start.getDate() + index);
      const key = date.toISOString().slice(0, 10);
      const dateLabel = `${String(date.getDate()).padStart(2, '0')}.${String(date.getMonth() + 1).padStart(2, '0')}.${String(date.getFullYear()).slice(-2)}`;
      const weekdayLabel = WEEKDAY_LABELS[date.getDay()];
      const isWeekend = date.getDay() === 0 || date.getDay() === 6;
      return { date, key, dateLabel, weekdayLabel, isWeekend };
    });
  }

  const CS_LABELS = {
    waiting_quality_check: '🟡 Очікує перевірки',
    quality_approved:      '🟢 Прийнятий',
    salary_pending:        '💰 Очікує виплати зарплати',
    salary_paid:           '✅ Зарплата виплачена',
    has_deficiencies:      '❌ Виявлені недоліки',
    deficiencies_fixed:    '🔧 Недоліки виправлені — очікує перевірки',
  };

  function renderQcMedia(project) {
    const photos = project.quality_defect_photos || [];
    const voiceUrl = project.quality_voice_memo_url || null;
    const defText = String(project.quality_deficiencies || project.defects_note || '').trim();
    let html = '';
    if (defText) {
      html += `<div style="font-size:13px; opacity:.9; white-space:pre-wrap; margin-bottom:8px;">${esc(defText)}</div>`;
    }
    if (photos.length) {
      html += `<div style="display:flex; flex-wrap:wrap; gap:5px; margin-bottom:8px;">
        ${photos.map(url => `<img src="${url}" style="width:56px; height:56px; object-fit:cover; border-radius:5px; cursor:pointer;"
          onclick="document.getElementById('qcPhotoModalImg').src='${url}';document.getElementById('qcPhotoModal').style.display='flex';">`).join('')}
      </div>`;
    }
    if (voiceUrl) {
      html += `<audio controls src="${voiceUrl}" style="width:100%; height:34px; margin-bottom:6px;"></audio>`;
    }
    return html;
  }

  function renderConstructionStatus(project) {
    const cs = project.construction_status || null;
    const isWorker = AUTH_USER && AUTH_USER.role === 'worker';

    if (cs === 'has_deficiencies') {
      const media = renderQcMedia(project);
      return `
        <div style="margin:8px 0 12px; padding:10px 12px; border-radius:8px; background:rgba(229,62,62,.15); border:1px solid rgba(229,62,62,.35);">
          <div style="font-size:13px; font-weight:700; color:#f88; margin-bottom:${media ? '8px' : '0'};">❌ Виявлені недоліки</div>
          ${media}
          ${isWorker ? `<button type="button" class="btn fix-deficiencies-btn"
            data-project-id="${project.id}"
            style="width:100%; margin-top:4px; background:rgba(212,160,23,.2); color:#f4c842; border:1px solid rgba(212,160,23,.4); font-weight:700;">
            🔧 Виправили недоліки
          </button>` : ''}
        </div>`;
    }

    if (cs === 'deficiencies_fixed') {
      const media = renderQcMedia(project);
      return `
        <div style="margin:8px 0 12px; padding:10px 12px; border-radius:8px; background:rgba(212,160,23,.12); border:1px solid rgba(212,160,23,.35);">
          <div style="font-size:13px; font-weight:700; color:#f4c842; margin-bottom:${media ? '8px' : '0'};">🔧 Недоліки виправлені — очікує перевірки</div>
          ${media}
        </div>`;
    }

    const label = CS_LABELS[cs] || null;
    if (label) {
      const bg = 'rgba(255,255,255,.07)';
      return `<div style="margin:8px 0 12px; padding:8px 12px; border-radius:8px; background:${bg}; font-size:13px; font-weight:600;">${esc(label)}</div>`;
    }

    // Show "Завершили будівництво" button for workers only (not owner viewing)
    const isService = String(project.entry_type || '') === 'service';
    const todayStr = new Date().toISOString().slice(0, 10);

    if (!isWorker) return '';
    if (isService) {
      // Service close button only for electrician view
      if (MATCH_FIELD !== 'electrician') return '';
      const svcStatus = String(project.status || '');
      if (svcStatus === 'closed') return '';
      if (svcStatus === 'waiting_quality_check') {
        return `<div style="width:100%; margin:8px 0 12px; padding:8px 12px; border-radius:8px;
                  background:rgba(212,160,23,.12); color:#f4c842; font-size:13px; font-weight:600; text-align:center;">
          ⏳ Очікує перевірки прорабом
        </div>`;
      }
      // Block future services
      const svcDate = project.scheduled_date || '';
      if (svcDate && svcDate > todayStr) {
        return `<div style="width:100%; margin:8px 0 12px; padding:10px 14px; border-radius:8px;
                  background:rgba(255,255,255,.06); color:rgba(255,255,255,.4); font-size:13px; text-align:center;">
          🔒 Завершення доступне з ${svcDate.split('-').reverse().join('.')}
        </div>`;
      }
      return `
        <button type="button" class="btn close-service-btn"
          data-service-id="${project.id}"
          style="width:100%; margin:8px 0 12px; background:#1a4a6a; color:#fff; font-weight:700;">
          ✅ Завершити сервісний виклик
        </button>
      `;
    }

    // Блокуємо майбутні проекти — завершити можна тільки сьогодні або минулі
    const startDate = MATCH_FIELD === 'electrician'
      ? (project.electric_work_start_date || '')
      : (project.panel_work_start_date || '');
    const isFuture = startDate && startDate > todayStr;
    if (isFuture) {
      return `
        <div style="width:100%; margin:8px 0 12px; padding:10px 14px; border-radius:8px;
                    background:rgba(255,255,255,.06); color:rgba(255,255,255,.4);
                    font-size:13px; text-align:center;">
          🔒 Завершення доступне з ${startDate.split('-').reverse().join('.')}
        </div>
      `;
    }

    return `
      <button type="button" class="btn complete-construction-btn"
        data-project-id="${project.id}"
        style="width:100%; margin:8px 0 12px; background:#2a7a2a; color:#fff; font-weight:700;">
        🏁 Завершили будівництво
      </button>
    `;
  }

  function makeCard(project, cardKey = null) {
    const card = document.createElement('div');
    const isClosed = String(project.status) === 'completed';
    const hasDefects = String(project.defects_note || '').trim() !== '';
    const hasGreenTariff = !!project.has_green_tariff;
    const photoHtml = project.defects_photo_url
      ? `<a href="${project.defects_photo_url}" target="_blank" style="font-size:12px; opacity:.8;">📎 Поточне фото недоліків</a>`
      : '<div style="font-size:12px; opacity:.6;">Фото недоліків не додано</div>';
    const imageThumbs = (project.attachments || [])
      .filter(a => a.is_image)
      .map(a => `<a href="${a.url}" target="_blank"><img src="${a.url}" alt="${esc(a.name)}" class="project-thumb"></a>`)
      .join('');
    const fileLinks = (project.attachments || [])
      .filter(a => !a.is_image)
      .map(a => `<a href="${a.url}" target="_blank" class="project-file-link">📎 ${esc(a.name)}</a>`)
      .join('');
    const assignedValue = project?.[MATCH_FIELD];
    const telegramUrl = normalizeExternalUrl(project.telegram_group_link);
    const mapsUrl = normalizeExternalUrl(project.geo_location_link);
    const phoneHref = normalizePhoneHref(project.phone_number);

    card.className = 'card project-card';
    card.dataset.cardKey = cardKey || `project-${project.id}`;
    if (hasGreenTariff) card.classList.add('project-card--green');
    if (hasDefects || project.construction_status === 'has_deficiencies') card.classList.add('project-card--defects');
    if (project.construction_status === 'deficiencies_fixed') card.style.border = '2px solid #d4a017';
    if (project._isOverdue) {
      card.style.border = '2px solid rgba(127, 255, 0, 0.7)';
      card.style.background = 'rgba(127, 255, 0, 0.05)';
    }

    const scheduledDateLabel = project._scheduledDate ? formatDateShort(project._scheduledDate) : '';

    card.innerHTML = `
      <div class="project-header">
        <div class="project-header-row">
          <div class="project-header-name">${esc(project.client_name)}</div>
          <div class="project-header-meta">
            ${scheduledDateLabel ? `📅 ${esc(scheduledDateLabel)}` : esc(project.created_at || '')} ${isClosed ? '• ✅ Закритий' : ''}
          </div>
        </div>
        <div class="project-header-row project-header-sub">
          <div>${esc(project.electrician || 'Електрик не вказаний')}</div>
          <div class="project-header-meta" style="font-size:12px; opacity:.78;">
            ${esc(project.installation_team || 'Бригада не вказана')}
          </div>
        </div>
        ${project.mounting_system ? `
        <div class="project-header-row project-header-sub" style="margin-top:2px;">
          <div style="font-size:11px; opacity:.7;">🔩 ${esc(project.mounting_system)}</div>
        </div>` : ''}
      </div>

      <div class="project-body">
        <button type="button" class="btn project-expand-toggle">Розкрити проект</button>

        ${renderConstructionStatus(project)}

        <div class="project-section" data-section>
          <button type="button" class="project-section-toggle">
            <span>Дані клієнта</span>
            <span class="project-section-caret">▸</span>
          </button>
          <div class="project-section-body">
            <a
              class="btn project-action-btn project-action-btn--telegram ${telegramUrl ? '' : 'tg-menu__item--static'}"
              href="${telegramUrl ? esc(telegramUrl) : '#'}"
              ${telegramUrl ? 'target="_blank" rel="noopener noreferrer"' : 'aria-disabled="true"'}
              onclick="${telegramUrl ? '' : 'return false;'}"
            >
              <img src="/img/telegram.png" alt="Telegram" class="project-action-icon">
              <span>Відкрити Telegram</span>
            </a>

            <a
              class="btn project-action-btn project-action-btn--maps ${mapsUrl ? '' : 'tg-menu__item--static'}"
              href="${mapsUrl ? esc(mapsUrl) : '#'}"
              ${mapsUrl ? 'target="_blank" rel="noopener noreferrer"' : 'aria-disabled="true"'}
              onclick="${mapsUrl ? '' : 'return false;'}"
            >
              <span style="font-size:18px; line-height:1;">📍</span>
              <span>Відкрити Google Maps</span>
            </a>

            <div class="project-field-label">Номер телефону</div>
            <a
              class="btn project-input-full ${phoneHref ? '' : 'tg-menu__item--static'}"
              href="${phoneHref ? esc(phoneHref) : '#'}"
              onclick="${phoneHref ? '' : 'return false;'}"
              style="display:block; text-align:left;"
            >
              ${esc(project.phone_number || '—')}
            </a>

            <div class="project-green-box">
              <div>
                <div class="project-field-label" style="margin-bottom:0;">Зелений тариф</div>
              </div>
              <div class="segmented project-green-segmented">
                <button type="button" class="green-tariff-btn ${hasGreenTariff ? 'active' : ''}" disabled>Є</button>
                <button type="button" class="green-tariff-btn ${!hasGreenTariff ? 'active' : ''}" disabled>Немає</button>
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
            <div class="btn project-input-full">${esc(project.inverter || '—')}</div>

            <div class="project-field-label">BMS</div>
            <div class="btn project-input-full">${esc(project.bms || '—')}</div>

            <div style="margin-bottom:12px;">
              <div class="project-two-col-head">
                <div class="project-two-col-head-main">АКБ</div>
                <div class="project-two-col-head-side">К-сть</div>
              </div>
              <div class="project-two-col-row">
                <div class="btn project-two-col-row-main">${esc(project.battery_name || '—')}</div>
                <div class="btn project-two-col-row-side">${esc(project.battery_qty ?? '—')}</div>
              </div>
            </div>

            <div>
              <div class="project-two-col-head">
                <div class="project-two-col-head-main">ФЕМ</div>
                <div class="project-two-col-head-side">К-сть</div>
              </div>
              <div class="project-two-col-row">
                <div class="btn project-two-col-row-main">${esc(project.panel_name || '—')}</div>
                <div class="btn project-two-col-row-side">${esc(project.panel_qty ?? '—')}</div>
              </div>
            </div>

            <div class="project-field-label">Система кріплень</div>
            <div class="btn project-input-full">${esc(project.mounting_system || '—')}</div>
          </div>
        </div>

        <div class="project-section" data-section>
          <button type="button" class="project-section-toggle">
            <span>Персонал</span>
            <span class="project-section-caret">▸</span>
          </button>
          <div class="project-section-body">
            <div class="project-field-label">Електрик</div>
            <div class="btn project-input-full">${esc(project.electrician || '—')}</div>

            <div class="project-field-label">Електрик примітки</div>
            <div class="btn project-textarea" style="text-align:left; cursor:default;">${esc(project.electrician_note || '—')}</div>

            ${project.electrician_task_note ? `
              <div class="project-field-label">Електрик: завдання з таблиці</div>
              <div class="btn project-textarea" style="text-align:left; cursor:default;">${esc(project.electrician_task_note)}</div>
            ` : ''}

            <hr class="project-divider" style="margin:8px 0 12px;">

            <div class="project-field-label">Монтажна бригада</div>
            <div class="btn project-input-full">${esc(project.installation_team || '—')}</div>

            <div class="project-field-label">Монтажна бригада примітки</div>
            <div class="btn project-textarea" style="text-align:left; cursor:default;">${esc(project.installation_team_note || '—')}</div>

            ${(project._todayDescription || project.installation_team_task_note) ? `
              <div class="project-field-label">Роботи на сьогодні</div>
              <div class="btn project-textarea" style="text-align:left; cursor:default; white-space:pre-wrap;">${esc(project._todayDescription || project.installation_team_task_note)}</div>
            ` : ''}

          </div>
        </div>

        <div class="project-section" data-section>
          <button type="button" class="project-section-toggle">
            <span>✏️ Додаткові роботи${project.extra_works ? ' ●' : ''}</span>
            <span class="project-section-caret">▸</span>
          </button>
          <div class="project-section-body">
            ${AUTH_USER?.role === 'worker' ? `
              <textarea
                class="btn project-textarea extra-works-textarea"
                placeholder="Опишіть виконані додаткові роботи..."
                style="width:100%; resize:vertical; min-height:100px; text-align:left; cursor:text;"
                data-project-id="${project.id}"
              >${esc(project.extra_works || '')}</textarea>
              <button type="button" class="btn save-extra-works-btn"
                data-project-id="${project.id}"
                style="width:100%; margin-top:6px; background:rgba(59,130,246,.25); color:#93c5fd; border:1px solid rgba(59,130,246,.4); font-weight:600;">
                💾 Зберегти
              </button>
            ` : `<div class="btn project-textarea" style="white-space:pre-wrap; text-align:left; cursor:default;">${esc(project.extra_works || '—')}</div>`}
          </div>
        </div>

        <div class="project-section" data-section>
          <button type="button" class="project-section-toggle">
            <span>Недоліки</span>
            <span class="project-section-caret">▸</span>
          </button>
          <div class="project-section-body">
            <div class="project-field-label" style="margin-top:10px;">Опис проблемних місць</div>
            <div class="btn project-textarea" style="text-align:left; cursor:default;">${esc(project.defects_note || '—')}</div>

            <div class="project-field-label">Головне фото недоліків</div>
            ${photoHtml}

            ${(project.quality_defect_photos || []).length ? `
              <div class="project-field-label">Фото від майстра (контроль якості)</div>
              <div style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:8px;">
                ${(project.quality_defect_photos || []).map(url => `
                  <img src="${url}" style="width:64px; height:64px; object-fit:cover; border-radius:6px; cursor:pointer;"
                    onclick="document.getElementById('qcPhotoModalImg').src='${url}';document.getElementById('qcPhotoModal').style.display='flex';">`
                ).join('')}
              </div>
            ` : ''}

            ${project.quality_voice_memo_url ? `
              <div class="project-field-label">Голосовий коментар майстра</div>
              <audio controls src="${project.quality_voice_memo_url}"
                style="width:100%; max-width:100%; height:36px; margin-bottom:8px;"></audio>
            ` : ''}
          </div>
        </div>

        <div class="project-section" data-section>
          <button type="button" class="project-section-toggle">
            <span>Фото та файли</span>
            <span class="project-section-caret">▸</span>
          </button>
          <div class="project-section-body">
            ${(imageThumbs || fileLinks) ? `
              <div class="project-field-label">Додані матеріали</div>
              ${imageThumbs ? `<div class="project-thumb-grid">${imageThumbs}</div>` : ''}
              ${fileLinks ? `<div>${fileLinks}</div>` : ''}
            ` : '<div style="font-size:12px; opacity:.6;">Матеріали не додані</div>'}
          </div>
        </div>
      </div>
    `;

    card.querySelector('.project-header')?.addEventListener('click', function () {
      const body = card.querySelector('.project-body');
      const isHidden = window.getComputedStyle(body).display === 'none';
      if (!isHidden) {
        body.querySelectorAll('.project-section').forEach(section => section.classList.remove('is-open'));
      }
      body.style.display = isHidden ? 'block' : 'none';
    });

    card.querySelector('.project-expand-toggle')?.addEventListener('click', function (e) {
      e.stopPropagation();
      const body = card.querySelector('.project-body');
      const sections = Array.from(body?.querySelectorAll('.project-section') || []);
      const shouldOpen = sections.some(section => !section.classList.contains('is-open'));
      sections.forEach(section => section.classList.toggle('is-open', shouldOpen));
      this.textContent = shouldOpen ? 'Згорнути проект' : 'Розкрити проект';
    });

    card.querySelectorAll('.project-section-toggle').forEach(btn => {
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        const section = btn.closest('.project-section');
        if (!section) return;
        section.classList.toggle('is-open');

        const body = card.querySelector('.project-body');
        const expandBtn = body?.querySelector('.project-expand-toggle');
        const sections = Array.from(body?.querySelectorAll('.project-section') || []);
        const allOpen = sections.length > 0 && sections.every(s => s.classList.contains('is-open'));
        if (expandBtn) {
          expandBtn.textContent = allOpen ? 'Згорнути проект' : 'Розкрити проект';
        }
      });
    });

    // "Завершили будівництво" button handler
    const completeBtn = card.querySelector('.complete-construction-btn');
    if (completeBtn) {
      completeBtn.addEventListener('click', async function (e) {
        e.stopPropagation();
        if (!confirm('Підтвердити завершення будівництва? Проект перейде на перевірку.')) return;
        this.disabled = true;
        this.textContent = 'Надсилання...';
        try {
          const r = await fetch(`/api/projects/${project.id}/complete-construction`, {
            method: 'POST',
            headers: {
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
              'Accept': 'application/json',
            },
          });
          const data = await r.json();
          if (r.ok && data.ok) {
            // Project disappears completely from the installer's schedule
            card.style.transition = 'opacity 0.4s';
            card.style.opacity = '0';
            setTimeout(() => {
              const daySection = card.closest('.card:not(.project-card)');
              card.remove();
              // Remove empty day section if no project cards remain
              if (daySection && !daySection.querySelector('.project-card')) {
                daySection.remove();
              }
            }, 400);
          } else {
            alert(data.error || 'Помилка');
            this.disabled = false;
            this.textContent = '🏁 Завершили будівництво';
          }
        } catch (err) {
          alert('Помилка з\'єднання');
          this.disabled = false;
          this.textContent = '🏁 Завершили будівництво';
        }
      });
    }

    // "Виправили недоліки" button handler
    const fixBtn = card.querySelector('.fix-deficiencies-btn');
    if (fixBtn) {
      fixBtn.addEventListener('click', async function (e) {
        e.stopPropagation();
        if (!confirm('Підтвердити, що недоліки виправлено? Проект знову піде на перевірку.')) return;
        this.disabled = true;
        this.textContent = 'Надсилання...';
        try {
          const r = await fetch(`/api/projects/${project.id}/deficiencies-fixed`, {
            method: 'POST',
            headers: {
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
              'Accept': 'application/json',
            },
          });
          const data = await r.json();
          if (r.ok && data.ok) {
            // Update card to deficiencies_fixed state
            card.classList.remove('project-card--defects');
            card.style.border = '2px solid #d4a017';
            const statusBlock = this.closest('[style*="rgba(229,62,62"]') || this.closest('div');
            if (statusBlock) {
              statusBlock.outerHTML = `<div style="margin:8px 0 12px; padding:8px 12px; border-radius:8px;
                background:rgba(212,160,23,.12); font-size:13px; font-weight:600; color:#f4c842;">
                🔧 Недоліки виправлені — очікує перевірки
              </div>`;
            }
          } else {
            alert(data.error || 'Помилка');
            this.disabled = false;
            this.textContent = '🔧 Виправили недоліки';
          }
        } catch (err) {
          alert('Помилка з\'єднання');
          this.disabled = false;
          this.textContent = '🔧 Виправили недоліки';
        }
      });
    }

    // ── Save extra works ──
    const saveExtraWorksBtn = card.querySelector('.save-extra-works-btn');
    if (saveExtraWorksBtn) {
      saveExtraWorksBtn.addEventListener('click', async function (e) {
        e.stopPropagation();
        const textarea = card.querySelector('.extra-works-textarea');
        const text = textarea ? textarea.value : '';
        const origLabel = this.textContent;
        this.disabled = true;
        this.textContent = 'Збереження...';
        try {
          const r = await fetch(`/api/projects/${project.id}/extra-works`, {
            method: 'POST',
            headers: {
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
              'Accept': 'application/json',
              'Content-Type': 'application/json',
            },
            body: JSON.stringify({ extra_works: text }),
          });
          const data = await r.json();
          if (r.ok && data.ok) {
            this.textContent = '✅ Збережено';
            this.style.background = 'rgba(34,197,94,.2)';
            this.style.color = '#86efac';
            this.style.borderColor = 'rgba(34,197,94,.4)';
            setTimeout(() => {
              this.disabled = false;
              this.textContent = origLabel;
              this.style.background = '';
              this.style.color = '';
              this.style.borderColor = '';
            }, 2000);
          } else {
            alert(data.error || 'Помилка збереження');
            this.disabled = false;
            this.textContent = origLabel;
          }
        } catch {
          alert('Помилка з\'єднання');
          this.disabled = false;
          this.textContent = origLabel;
        }
      });
    }

    // ── Close service button (electrician view → goes through quality check) ──
    const closeServiceBtn = card.querySelector('.close-service-btn');
    if (closeServiceBtn) {
      closeServiceBtn.addEventListener('click', async function () {
        if (!confirm('Підтвердити завершення сервісного виклику? Прораб отримає сповіщення для перевірки.')) return;
        this.disabled = true;
        this.textContent = 'Відправка...';
        try {
          const r = await fetch(`/api/service-requests/${project.id}/complete`, {
            method: 'POST',
            headers: {
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
              'Accept': 'application/json',
            },
          });
          const data = await r.json();
          if (r.ok && data.ok) {
            this.textContent = '⏳ Очікує перевірки';
            this.style.background = 'rgba(212,160,23,.3)';
            this.style.color = '#f4c842';
            setTimeout(() => loadProjects().catch(() => {}), 800);
          } else {
            alert(data.error || 'Помилка');
            this.disabled = false;
            this.textContent = '✅ Завершити сервісний виклик';
          }
        } catch (err) {
          alert('Помилка з\'єднання');
          this.disabled = false;
          this.textContent = '✅ Завершити сервісний виклик';
        }
      });
    }

    return card;
  }

  function renderScheduledProjects(projects, target = null) {
    target = target || container;
    const days = getWeekRange();
    const grouped = new Map(days.map(day => [day.key, []]));
    const unscheduled = [];

    function getPreferredInstallerEntries(project) {
      const entries = Array.isArray(project?.installer_schedule_entries) ? project.installer_schedule_entries : [];
      return entries.filter(e => String(e?.source || '') === 'google_sheet');
    }

    // Build a map of installer_schedule_entries by date for quick lookup
    function getInstallerDescriptionForDate(project, dateKey) {
      const entries = getPreferredInstallerEntries(project);
      const entry = entries.find(e => String(e.date || '').slice(0, 10) === dateKey);
      return entry?.description || null;
    }

    projects.forEach(project => {
      // For electrician view use only electric_schedule_dates (not installer entries which belong to another team)
      const useInstallerEntries = MATCH_FIELD !== 'electrician';
      const scheduleEntries = useInstallerEntries && Array.isArray(project?.installer_schedule_entries) && project.installer_schedule_entries.length > 0
        ? getPreferredInstallerEntries(project)
        : null;

      const explicitDates = scheduleEntries
        ? scheduleEntries.map(e => e.date)
        : (Array.isArray(project?.[SCHEDULE_DATES_KEY]) ? project[SCHEDULE_DATES_KEY] : []);

      const normalizedKeys = new Set();

      explicitDates.forEach(rawDate => {
        const date = parseProjectDate(rawDate);
        if (!date) return;
        const key = date.toISOString().slice(0, 10);
        if (!grouped.has(key) || normalizedKeys.has(key)) return;
        normalizedKeys.add(key);
        const todayDesc = getInstallerDescriptionForDate(project, key);
        const projectForDay = todayDesc ? { ...project, _todayDescription: todayDesc } : project;
        grouped.get(key).push(projectForDay);
      });

      if (normalizedKeys.size > 0) {
        return;
      }

      const startDate = parseProjectDate(project?.[SCHEDULE_FIELD]);
      if (!startDate) {
        unscheduled.push(project);
        return;
      }

      const duration = parseProjectDuration(project);
      for (let offset = 0; offset < duration; offset++) {
        const date = new Date(startDate);
        date.setDate(startDate.getDate() + offset);
        const key = date.toISOString().slice(0, 10);
        if (!grouped.has(key) || normalizedKeys.has(key)) continue;
        normalizedKeys.add(key);
        grouped.get(key).push(project);
      }

      // Date is set but falls entirely outside the visible window (past or far future).
      if (normalizedKeys.size === 0) {
        // Check if all dates are in the past → overdue (not yet completed by worker)
        const todayStr = days[0].key;
        const allInPast = explicitDates.length > 0
          && explicitDates.every(rawDate => {
            const d = parseProjectDate(rawDate);
            return d && d.toISOString().slice(0, 10) < todayStr;
          });

        if (allInPast && !project.installation_completed_at) {
          // Pin overdue project to today with a flag for lime highlight
          if (grouped.has(todayStr)) {
            grouped.get(todayStr).push({ ...project, _isOverdue: true });
          }
        } else if (project.construction_status === 'has_deficiencies') {
          // Always pin defective projects to today so installer sees them highlighted in red
          if (grouped.has(todayStr)) {
            grouped.get(todayStr).push(project);
          }
        } else {
          unscheduled.push({ ...project, _scheduledDate: project[SCHEDULE_FIELD] });
        }
      }
    });

    if (!target || target === container) {
      captureOpenState();
      container.innerHTML = '';
    }

    const hasAnyProjects = Array.from(grouped.values()).some(items => items.length > 0) || unscheduled.length > 0;
    if (!hasAnyProjects) {
      target.insertAdjacentHTML('beforeend', `<div class="card">${esc(EMPTY_TEXT)}</div>`);
      restoreOpenState();
      return;
    }

    days.forEach(day => {
      const dayProjects = (grouped.get(day.key) || []).sort((a, b) => String(a.client_name || '').localeCompare(String(b.client_name || ''), 'uk'));
      if (!dayProjects.length) return;

      const daySection = document.createElement('div');
      daySection.className = 'card';
      daySection.style.marginBottom = '16px';
      daySection.innerHTML = `
        <div class="project-day-heading">
          <span style="font-weight:800; font-size:15px; color:${day.isWeekend ? '#ff6b6b' : 'inherit'};">${esc(day.dateLabel)}</span>
          <span style="font-weight:900; font-size:17px; color:${day.isWeekend ? '#ff6b6b' : 'inherit'};"> ${esc(day.weekdayLabel)}</span>
        </div>
        <hr style="margin:10px 0 12px; border:none; border-top:1px solid rgba(255,255,255,.14);">
      `;

      dayProjects.forEach(project => {
        const projectCard = project?.entry_type === 'service'
          ? makeServiceCard(project)
          : makeCard(project, `project-${project.id}-${day.key}`);
        projectCard.style.marginBottom = '12px';
        daySection.appendChild(projectCard);
      });

      const lastCard = daySection.lastElementChild;
      if (lastCard && lastCard.classList.contains('project-card')) {
        lastCard.style.marginBottom = '0';
      }

      target.appendChild(daySection);
    });

    if (unscheduled.length > 0) {
      const unscheduledSection = document.createElement('div');
      unscheduledSection.className = 'card';
      unscheduledSection.style.marginBottom = '16px';
      unscheduledSection.innerHTML = `
        <div class="project-day-heading">
          <span style="font-weight:800; font-size:15px; opacity:.6;">Не визначена дата будівництва</span>
        </div>
        <hr style="margin:10px 0 12px; border:none; border-top:1px solid rgba(255,255,255,.14);">
      `;

      unscheduled
        .sort((a, b) => String(a.client_name || '').localeCompare(String(b.client_name || ''), 'uk'))
        .forEach(project => {
          const projectCard = makeCard(project);
          projectCard.style.marginBottom = '12px';
          unscheduledSection.appendChild(projectCard);
        });

      const lastCard = unscheduledSection.lastElementChild;
      if (lastCard && lastCard.classList.contains('project-card')) {
        lastCard.style.marginBottom = '0';
      }

      target.appendChild(unscheduledSection);
    }

    restoreOpenState();
  }

  const DONE_STATUSES = new Set(['waiting_quality_check', 'salary_pending']);

  const UA_MONTHS = ['Січень','Лютий','Березень','Квітень','Травень','Червень','Липень','Серпень','Вересень','Жовтень','Листопад','Грудень'];

  function fillArchiveBody(bodyEl, items, dateField, cardKeyPrefix) {
    const byYear = {};
    items.forEach(item => {
      const raw = item[dateField] || '';
      const d = raw ? new Date(raw) : null;
      if (!d || isNaN(d.getTime())) {
        if (!byYear['?']) byYear['?'] = { '?': [] };
        byYear['?']['?'].push(item);
        return;
      }
      const y = d.getFullYear();
      const m = d.getMonth();
      if (!byYear[y]) byYear[y] = {};
      if (!byYear[y][m]) byYear[y][m] = [];
      byYear[y][m].push(item);
    });

    const years = Object.keys(byYear).sort((a, b) => b > a ? 1 : -1);
    if (!years.length) {
      bodyEl.innerHTML = `<div class="acc-empty">Немає завершених</div>`;
      return;
    }

    function makeToggle(el, body, stateKey) {
      const isOpen = savedAccordionState.hasOwnProperty(stateKey) ? savedAccordionState[stateKey] : false;
      body.style.display = isOpen ? 'block' : 'none';
      el.querySelector('.acc-chevron').textContent = isOpen ? '▼' : '▶';
      if (isOpen) el.classList.add('acc-header--open');
      el.addEventListener('click', () => {
        const closing = body.style.display !== 'none';
        body.style.display = closing ? 'none' : 'block';
        el.querySelector('.acc-chevron').textContent = closing ? '▶' : '▼';
        el.classList.toggle('acc-header--open', !closing);
        savedAccordionState[stateKey] = !closing;
      });
    }

    years.forEach(year => {
      const months = Object.keys(byYear[year]).sort((a, b) => b > a ? 1 : -1);
      const yearCount = months.reduce((s, m) => s + byYear[year][m].length, 0);
      const yearKey = `${cardKeyPrefix}-y-${year}`;

      const yearSection = document.createElement('div');
      yearSection.className = 'acc-section';

      const yearHeader = document.createElement('button');
      yearHeader.type = 'button';
      yearHeader.className = 'acc-header';
      yearHeader.style.fontSize = '14px';
      yearHeader.innerHTML =
        `<span class="acc-title">${esc(String(year))}</span>` +
        `<span class="acc-count">${yearCount}</span>` +
        `<span class="acc-chevron">▶</span>`;

      const yearBody = document.createElement('div');
      yearBody.className = 'acc-body';
      makeToggle(yearHeader, yearBody, yearKey);

      months.forEach(month => {
        const monthItems = byYear[year][month];
        const monthKey = `${cardKeyPrefix}-m-${year}-${month}`;
        const monthSection = document.createElement('div');
        monthSection.className = 'acc-section';
        monthSection.style.marginBottom = '6px';

        const monthLabel = month === '?' ? 'Без дати' : (UA_MONTHS[+month] || month);
        const monthHeader = document.createElement('button');
        monthHeader.type = 'button';
        monthHeader.className = 'acc-header';
        monthHeader.style.fontSize = '13px';
        monthHeader.innerHTML =
          `<span class="acc-title">${esc(monthLabel)}</span>` +
          `<span class="acc-count">${monthItems.length}</span>` +
          `<span class="acc-chevron">▶</span>`;

        const monthBody = document.createElement('div');
        monthBody.className = 'acc-body';
        makeToggle(monthHeader, monthBody, monthKey);

        monthItems
          .sort((a, b) => String(b[dateField] || '').localeCompare(String(a[dateField] || '')))
          .forEach((item, i) => {
            const card = makeCard(item, `${cardKeyPrefix}-${item.id}-${year}-${month}`);
            card.style.marginBottom = i < monthItems.length - 1 ? '12px' : '0';
            monthBody.appendChild(card);
          });

        monthSection.appendChild(monthHeader);
        monthSection.appendChild(monthBody);
        yearBody.appendChild(monthSection);
      });

      yearSection.appendChild(yearHeader);
      yearSection.appendChild(yearBody);
      bodyEl.appendChild(yearSection);
    });
  }

  function renderAccordionProjects(projects) {
    const schedule          = [];
    const pending           = [];
    const built             = [];
    const defective         = [];
    const completedInstalls = [];
    const completedServices = [];

    const isElecView = MATCH_FIELD === 'electrician';

    // Проекти які вважаються "підтвердженими" (зарплата ще не нарахована, але монтаж завершено)
    // "Придаток" залишається в "Очікують підтвердження" для ручного QA
    const isPridatok = (p) => String(p?.client_name || '').toLowerCase().includes('придаток');

    projects.forEach(project => {
      const cs = String(project?.construction_status || '');
      const isService = String(project?.entry_type || '') === 'service';

      if (isService) {
        if (isElecView && String(project?.status || '') === 'closed') {
          completedServices.push(project);
        } else if (String(project?.status || '') !== 'closed') {
          schedule.push(project);
        }
        return;
      }

      if (isElecView && (cs === 'salary_pending' || cs === 'salary_paid')) {
        completedInstalls.push(project);
      } else if (cs === 'salary_pending' || cs === 'salary_paid') {
        built.push(project);
      } else if (cs === 'has_deficiencies') {
        defective.push(project);
      } else if (project?.installation_completed_at || cs === 'waiting_quality_check' || cs === 'deficiencies_fixed') {
        pending.push(project);
      } else {
        schedule.push(project);
      }
    });

    function byStartDate(a, b) {
      const da = String(a?.panel_work_start_date || '9999-99-99');
      const db = String(b?.panel_work_start_date || '9999-99-99');
      if (da !== db) return da.localeCompare(db);
      return String(a?.client_name || '').localeCompare(String(b?.client_name || ''), 'uk');
    }
    function byCompletedDesc(a, b) {
      return String(b?.installation_completed_at || '0').localeCompare(String(a?.installation_completed_at || '0'));
    }

    schedule.sort(byStartDate);
    pending.sort(byCompletedDesc);
    defective.sort(byCompletedDesc);
    completedInstalls.sort(byCompletedDesc);

    // Render "Графік будівництва" body with date/weekday headers
    function fillScheduleBody(bodyEl, items) {
      if (!items.length) {
        bodyEl.innerHTML = `<div class="acc-empty">Немає проектів</div>`;
        return;
      }
      const days = getWeekRange();
      const grouped = new Map(days.map(day => [day.key, []]));
      const unscheduled = [];

      items.forEach(project => {
        const scheduleEntries = MATCH_FIELD !== 'electrician' && Array.isArray(project?.installer_schedule_entries) && project.installer_schedule_entries.length > 0
          ? project.installer_schedule_entries.filter(e => String(e?.source || '') === 'google_sheet')
          : null;

        const explicitDates = scheduleEntries
          ? scheduleEntries.map(e => e.date)
          : (Array.isArray(project?.[SCHEDULE_DATES_KEY]) ? project[SCHEDULE_DATES_KEY] : []);
        const normalizedKeys = new Set();

        explicitDates.forEach(rawDate => {
          const date = parseProjectDate(rawDate);
          if (!date) return;
          const key = date.toISOString().slice(0, 10);
          if (!grouped.has(key) || normalizedKeys.has(key)) return;
          normalizedKeys.add(key);
          grouped.get(key).push(project);
        });

        if (normalizedKeys.size > 0) return;

        const startDate = parseProjectDate(project?.[SCHEDULE_FIELD]);
        if (!startDate) { unscheduled.push(project); return; }

        const duration = parseProjectDuration(project);
        for (let offset = 0; offset < duration; offset++) {
          const d = new Date(startDate);
          d.setDate(startDate.getDate() + offset);
          const key = d.toISOString().slice(0, 10);
          if (!grouped.has(key) || normalizedKeys.has(key)) continue;
          normalizedKeys.add(key);
          grouped.get(key).push(project);
        }
        if (normalizedKeys.size === 0) {
          const todayStr = days[0].key;
          const allInPast = explicitDates.length > 0
            && explicitDates.every(rawDate => {
              const d = parseProjectDate(rawDate);
              return d && d.toISOString().slice(0, 10) < todayStr;
            });
          if (allInPast && !project.installation_completed_at) {
            if (grouped.has(todayStr)) {
              grouped.get(todayStr).push({ ...project, _isOverdue: true });
            }
          } else {
            unscheduled.push({ ...project, _scheduledDate: project[SCHEDULE_FIELD] });
          }
        }
      });

      days.forEach(day => {
        const dayProjects = (grouped.get(day.key) || []).sort((a, b) =>
          String(a.client_name || '').localeCompare(String(b.client_name || ''), 'uk')
        );
        if (!dayProjects.length) return;
        const dayDiv = document.createElement('div');
        dayDiv.style.marginBottom = '16px';
        dayDiv.innerHTML =
          `<div class="project-day-heading" style="margin-bottom:10px;">` +
          `<span style="font-weight:800;font-size:15px;color:${day.isWeekend ? '#ff6b6b' : 'inherit'};">${esc(day.dateLabel)}</span>` +
          `<span style="font-weight:900;font-size:17px;color:${day.isWeekend ? '#ff6b6b' : 'inherit'};"> ${esc(day.weekdayLabel)}</span>` +
          `</div>`;
        dayProjects.forEach((project, i) => {
          const card = makeCard(project, `acc-sched-${project.id}-${day.key}`);
          card.style.marginBottom = i < dayProjects.length - 1 ? '12px' : '0';
          dayDiv.appendChild(card);
        });
        bodyEl.appendChild(dayDiv);
      });

      if (unscheduled.length > 0) {
        const noDateDiv = document.createElement('div');
        noDateDiv.style.marginTop = '8px';
        const noDateLabel = document.createElement('div');
        noDateLabel.style.cssText = 'opacity:.55;font-size:13px;margin-bottom:8px;';
        noDateLabel.textContent = 'Без дати будівництва';
        noDateDiv.appendChild(noDateLabel);
        unscheduled
          .sort((a, b) => String(a.client_name || '').localeCompare(String(b.client_name || ''), 'uk'))
          .forEach((project, i) => {
            const card = makeCard(project, `acc-unsched-${project.id}`);
            card.style.marginBottom = i < unscheduled.length - 1 ? '12px' : '0';
            noDateDiv.appendChild(card);
          });
        bodyEl.appendChild(noDateDiv);
      }
    }

    // Order depends on view type
    const baseSections = [
      { key: 'pending',   label: '⏳ Очікують підтвердження', projects: pending,   defaultOpen: false, isSchedule: false },
      { key: 'defective', label: '⚠️ Потребують виправлення', projects: defective, defaultOpen: false, isSchedule: false },
      { key: 'schedule',  label: '📅 Графік будівництва',     projects: schedule,  defaultOpen: true,  isSchedule: true  },
      { key: 'built',     label: '🏗 Збудовані',              projects: built,     defaultOpen: false, isArchive: true, archiveField: 'installation_completed_at', archivePrefix: 'bl' },
    ];

    const sections = isElecView
      ? [
          { key: 'completed-installs', label: '📁 Завершені монтажі',  projects: completedInstalls, defaultOpen: false, isArchive: true, archiveField: 'installation_completed_at', archivePrefix: 'ci' },
          { key: 'completed-services', label: '📂 Завершені сервіси',  projects: completedServices, defaultOpen: false, isArchive: true, archiveField: 'closed_at',                 archivePrefix: 'cs' },
          ...baseSections,
        ]
      : baseSections;

    captureOpenState();
    container.innerHTML = '';

    sections.forEach(({ key, label, projects: items, defaultOpen, isSchedule, isArchive, archiveField, archivePrefix }) => {
      // Preserve accordion open/closed state across auto-refreshes
      const open = savedAccordionState.hasOwnProperty(key) ? savedAccordionState[key] : defaultOpen;

      const section = document.createElement('div');
      section.className = 'acc-section';

      const header = document.createElement('button');
      header.type = 'button';
      header.className = 'acc-header' + (open ? ' acc-header--open' : '');
      header.innerHTML =
        `<span class="acc-title">${esc(label)}</span>` +
        `<span class="acc-count">${items.length}</span>` +
        `<span class="acc-chevron">${open ? '▼' : '▶'}</span>`;

      header.addEventListener('click', () => {
        const body = header.nextElementSibling;
        const closing = body.style.display !== 'none';
        body.style.display = closing ? 'none' : 'block';
        header.querySelector('.acc-chevron').textContent = closing ? '▶' : '▼';
        header.classList.toggle('acc-header--open', !closing);
        savedAccordionState[key] = !closing;
      });

      const body = document.createElement('div');
      body.className = 'acc-body';
      body.style.display = open ? 'block' : 'none';

      if (isArchive) {
        fillArchiveBody(body, items, archiveField, archivePrefix);
      } else if (isSchedule) {
        fillScheduleBody(body, items);
      } else if (items.length === 0) {
        body.innerHTML = `<div class="acc-empty">Немає проектів</div>`;
      } else {
        items.forEach((project, i) => {
          const card = makeCard(project, `acc-${key}-${project.id}`);
          card.style.marginBottom = i < items.length - 1 ? '12px' : '0';
          body.appendChild(card);
        });
      }

      section.appendChild(header);
      section.appendChild(body);
      container.appendChild(section);
    });

    restoreOpenState();
  }

  function renderProjects(projects) {
    const base = projects
      .filter(project => !project?.is_retail)
      .filter(project => matchesAssignment(project?.[MATCH_FIELD]))
      .filter(project => String(project.status || '') !== 'completed');

    if (ACCORDION_MODE) {
      renderAccordionProjects(base);
      return;
    }

    // ── Electrician view: prepend archive accordions then render schedule ──
    if (MATCH_FIELD === 'electrician') {
      const doneProjects  = base.filter(p => {
        const cs = String(p?.construction_status || '');
        return String(p?.entry_type || '') !== 'service' && (cs === 'salary_pending' || cs === 'salary_paid');
      });
      const doneServices  = base.filter(p =>
        String(p?.entry_type || '') === 'service' && String(p?.status || '') === 'closed'
      );
      const active = base
        .filter(p => !doneProjects.includes(p) && !doneServices.includes(p))
        .filter(p => !p?.installation_completed_at || p?.construction_status === 'has_deficiencies')
        .filter(p => !DONE_STATUSES.has(p?.construction_status));

      captureOpenState();
      container.innerHTML = '';

      [
        { key: 'arch-installs', label: '📁 Завершені монтажі', items: doneProjects, dateField: 'installation_completed_at', prefix: 'ai' },
        { key: 'arch-services', label: '📂 Завершені сервіси', items: doneServices, dateField: 'closed_at',                 prefix: 'as' },
      ].forEach(({ key, label, items, dateField, prefix }) => {
        const open = savedAccordionState.hasOwnProperty(key) ? savedAccordionState[key] : false;
        const section = document.createElement('div');
        section.className = 'acc-section';

        const header = document.createElement('button');
        header.type = 'button';
        header.className = 'acc-header' + (open ? ' acc-header--open' : '');
        header.innerHTML =
          `<span class="acc-title">${esc(label)}</span>` +
          `<span class="acc-count">${items.length}</span>` +
          `<span class="acc-chevron">${open ? '▼' : '▶'}</span>`;

        const body = document.createElement('div');
        body.className = 'acc-body';
        body.style.display = open ? 'block' : 'none';

        header.addEventListener('click', () => {
          const closing = body.style.display !== 'none';
          body.style.display = closing ? 'none' : 'block';
          header.querySelector('.acc-chevron').textContent = closing ? '▶' : '▼';
          header.classList.toggle('acc-header--open', !closing);
          savedAccordionState[key] = !closing;
        });

        fillArchiveBody(body, items, dateField, prefix);
        section.appendChild(header);
        section.appendChild(body);
        container.appendChild(section);
      });

      const scheduleTarget = document.createElement('div');
      container.appendChild(scheduleTarget);
      renderScheduledProjects(active, scheduleTarget);
      return;
    }

    const filtered = base
      .filter(project => !project?.installation_completed_at || project?.construction_status === 'has_deficiencies')
      .filter(project => !DONE_STATUSES.has(project?.construction_status));

    if (SCHEDULE_FIELD) {
      renderScheduledProjects(filtered);
      return;
    }

    const sorted = filtered.sort((a, b) => String(a.client_name || '').localeCompare(String(b.client_name || ''), 'uk'));

    captureOpenState();
    container.innerHTML = '';

    if (!sorted.length) {
      container.innerHTML = `<div class="card">${esc(EMPTY_TEXT)}</div>`;
      return;
    }

    sorted.forEach(project => container.appendChild(makeCard(project)));
    restoreOpenState();
  }

  async function loadProjects() {
    try {
      // Workers get a slim filtered response; owners/other roles get the full list
      const isWorker = AUTH_USER && AUTH_USER.role === 'worker';
      let projectsUrl = '/api/sales-projects';
      if (isWorker && expectedValue && MATCH_FIELD) {
        projectsUrl += `?worker_mode=1&match_field=${encodeURIComponent(MATCH_FIELD)}&match_value=${encodeURIComponent(expectedValue)}`;
      }

      const fetchList = [fetch(projectsUrl, { headers: { 'Accept': 'application/json' } })];
      const isElectricianView = MATCH_FIELD === 'electrician';
      if (isElectricianView) {
        fetchList.push(fetch('/api/my-service-requests', { headers: { 'Accept': 'application/json' } }));
      }

      const [projectsRes, servicesRes] = await Promise.all(fetchList);
      const projects = await projectsRes.json();
      const services = (isElectricianView && servicesRes && servicesRes.ok) ? await servicesRes.json() : [];

      if (!projectsRes.ok || !Array.isArray(projects)) {
        throw new Error('Не вдалося завантажити проекти');
      }

      const normalizedServices = Array.isArray(services)
        ? services.map((item) => ({
            ...item,
            entry_type: 'service',
            is_retail: false,
            scheduled_date: item.schedule_date || '',
            electric_work_start_date: item.schedule_date || '',
            panel_work_start_date: item.schedule_date || '',
            electric_schedule_dates: item.schedule_date ? [item.schedule_date] : [],
            installer_schedule_dates: item.schedule_date ? [item.schedule_date] : [],
          }))
        : [];

      renderProjects([...projects, ...normalizedServices]);
    } catch (err) {
      container.innerHTML = `<div class="card">${esc(err.message || 'Помилка')}</div>`;
    }
  }

  container.innerHTML = '<div class="card">Завантаження...</div>';
  await loadProjects();
  setInterval(() => {
    loadProjects().catch(() => {});
  }, REFRESH_MS);
});
</script>
