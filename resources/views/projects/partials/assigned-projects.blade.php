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
  const REFRESH_MS = 15000;
  const WEEKDAY_LABELS = ['Неділя', 'Понеділок', 'Вівторок', 'Середа', 'Четвер', "П'ятниця", 'Субота'];

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

  function getWeekRange() {
    const start = new Date();
    start.setHours(0, 0, 0, 0);

    return Array.from({ length: 7 }, (_, index) => {
      const date = new Date(start);
      date.setDate(start.getDate() + index);
      const key = date.toISOString().slice(0, 10);
      const dateLabel = `${String(date.getDate()).padStart(2, '0')}.${String(date.getMonth() + 1).padStart(2, '0')}.${String(date.getFullYear()).slice(-2)}`;
      const weekdayLabel = WEEKDAY_LABELS[date.getDay()];
      const isWeekend = date.getDay() === 0 || date.getDay() === 6;
      return { date, key, dateLabel, weekdayLabel, isWeekend };
    });
  }

  function makeCard(project) {
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
    if (hasGreenTariff) card.classList.add('project-card--green');
    if (hasDefects) card.classList.add('project-card--defects');

    card.innerHTML = `
      <div class="project-header">
        <div class="project-header-row">
          <div class="project-header-name">${esc(project.client_name)}</div>
          <div class="project-header-meta">
            ${esc(project.created_at || '')} ${isClosed ? '• ✅ Закритий' : ''}
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

            ${project.installation_team_task_note ? `
              <div class="project-field-label">Монтажна бригада: завдання з таблиці</div>
              <div class="btn project-textarea" style="text-align:left; cursor:default;">${esc(project.installation_team_task_note)}</div>
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

    return card;
  }

  function renderScheduledProjects(projects) {
    const days = getWeekRange();
    const grouped = new Map(days.map(day => [day.key, []]));

    projects.forEach(project => {
      const explicitDates = Array.isArray(project?.[SCHEDULE_DATES_KEY]) ? project[SCHEDULE_DATES_KEY] : [];
      const normalizedKeys = new Set();

      explicitDates.forEach(rawDate => {
        const date = parseProjectDate(rawDate);
        if (!date) return;
        const key = date.toISOString().slice(0, 10);
        if (!grouped.has(key) || normalizedKeys.has(key)) return;
        normalizedKeys.add(key);
        grouped.get(key).push(project);
      });

      if (normalizedKeys.size > 0) {
        return;
      }

      const startDate = parseProjectDate(project?.[SCHEDULE_FIELD]);
      if (!startDate) return;

      const duration = parseProjectDuration(project);
      for (let offset = 0; offset < duration; offset++) {
        const date = new Date(startDate);
        date.setDate(startDate.getDate() + offset);
        const key = date.toISOString().slice(0, 10);
        if (!grouped.has(key) || normalizedKeys.has(key)) continue;
        normalizedKeys.add(key);
        grouped.get(key).push(project);
      }
    });

    container.innerHTML = '';

    const hasAnyProjects = Array.from(grouped.values()).some(items => items.length > 0);
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
        const projectCard = makeCard(project);
        projectCard.style.marginBottom = '12px';
        daySection.appendChild(projectCard);
      });

      const lastCard = daySection.lastElementChild;
      if (lastCard && lastCard.classList.contains('project-card')) {
        lastCard.style.marginBottom = '0';
      }

      container.appendChild(daySection);
    });
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

    container.innerHTML = '';

    if (!sorted.length) {
      container.innerHTML = `<div class="card">${esc(EMPTY_TEXT)}</div>`;
      return;
    }

    sorted.forEach(project => container.appendChild(makeCard(project)));
  }

  async function loadProjects() {
    try {
      const res = await fetch('/api/sales-projects', { headers: { 'Accept': 'application/json' } });
      const projects = await res.json();

      if (!res.ok || !Array.isArray(projects)) {
        throw new Error('Не вдалося завантажити проекти');
      }

      renderProjects(projects);
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
