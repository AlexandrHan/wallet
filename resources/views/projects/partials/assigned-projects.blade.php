<main class="projects-main">

  <style>
    @media (max-width: 768px) {
      .project-day-heading {
        text-align: center;
      }
    }
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
  const REFRESH_MS = 8000;
  const WEEKDAY_LABELS = ['Неділя', 'Понеділок', 'Вівторок', 'Середа', 'Четвер', "П'ятниця", 'Субота'];

  let savedOpen = {}; // cardKey → { bodyVisible, openSectionIndexes[] }

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
    return a !== '' && (a === b || (c && a === c));
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
          </div>
        </div>
      </div>
    `;

    card.querySelector('.project-header')?.addEventListener('click', function () {
      const body = card.querySelector('.project-body');
      const isHidden = window.getComputedStyle(body).display === 'none';
      body.style.display = isHidden ? 'block' : 'none';
    });

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
    const isService = String(project.status || '') === 'service';
    if (!isWorker || isService) return '';

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

            <div class="project-field-label">Доп. роботи</div>
            <div class="btn project-input-full">${esc(project.extra_works || '—')}</div>
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
            this.textContent = '🟡 Очікує перевірки';
            this.classList.remove('complete-construction-btn');
            this.style.background = '';
            this.style.color = '';
            this.disabled = true;
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

    return card;
  }

  function renderScheduledProjects(projects) {
    const days = getWeekRange();
    const grouped = new Map(days.map(day => [day.key, []]));
    const unscheduled = [];

    // Build a map of installer_schedule_entries by date for quick lookup
    function getInstallerDescriptionForDate(project, dateKey) {
      const entries = Array.isArray(project?.installer_schedule_entries) ? project.installer_schedule_entries : [];
      const entry = entries.find(e => String(e.date || '').slice(0, 10) === dateKey);
      return entry?.description || null;
    }

    projects.forEach(project => {
      // Prefer installer_schedule_entries (has per-day descriptions) over plain dates array
      const scheduleEntries = Array.isArray(project?.installer_schedule_entries) && project.installer_schedule_entries.length > 0
        ? project.installer_schedule_entries
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

      // Date is set but falls entirely outside the visible window (past or far future) —
      // show in unscheduled so the project is never invisible.
      if (normalizedKeys.size === 0) {
        unscheduled.push({ ...project, _scheduledDate: project[SCHEDULE_FIELD] });
      }
    });

    captureOpenState();
    container.innerHTML = '';

    const hasAnyProjects = Array.from(grouped.values()).some(items => items.length > 0) || unscheduled.length > 0;
    if (!hasAnyProjects) {
      container.innerHTML = `<div class="card">${esc(EMPTY_TEXT)}</div>`;
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

      container.appendChild(daySection);
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

      container.appendChild(unscheduledSection);
    }

    restoreOpenState();
  }

  function renderProjects(projects) {
    const filtered = projects
      .filter(project => !project?.is_retail)
      .filter(project => matchesAssignment(project?.[MATCH_FIELD]))
      .filter(project => String(project.status || '') !== 'completed');

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

      const [projectsRes, servicesRes] = await Promise.all([
        fetch(projectsUrl, { headers: { 'Accept': 'application/json' } }),
        fetch('/api/my-service-requests', { headers: { 'Accept': 'application/json' } }),
      ]);
      const projects = await projectsRes.json();
      const services = servicesRes.ok ? await servicesRes.json() : [];

      if (!projectsRes.ok || !Array.isArray(projects)) {
        throw new Error('Не вдалося завантажити проекти');
      }

      const normalizedServices = Array.isArray(services)
        ? services.map((item) => ({
            ...item,
            client_name: item.client_name,
            status: 'service',
            is_retail: false,
            created_at: item.created_at,
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
