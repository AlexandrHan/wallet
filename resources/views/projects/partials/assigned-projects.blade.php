<main class="projects-main">

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

            <div class="project-field-label">Монтажна бригада</div>
            <div class="btn project-input-full">${esc(project.installation_team || '—')}</div>

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

  container.innerHTML = '<div class="card">Завантаження...</div>';

  try {
    const res = await fetch('/api/sales-projects', { headers: { 'Accept': 'application/json' } });
    const projects = await res.json();

    if (!res.ok || !Array.isArray(projects)) {
      throw new Error('Не вдалося завантажити проекти');
    }

    const filtered = projects
      .filter(project => matchesAssignment(project?.[MATCH_FIELD]))
      .sort((a, b) => {
        if (String(a.status) === 'completed' && String(b.status) !== 'completed') return 1;
        if (String(a.status) !== 'completed' && String(b.status) === 'completed') return -1;
        return String(a.client_name || '').localeCompare(String(b.client_name || ''), 'uk');
      });

    container.innerHTML = '';

    if (!filtered.length) {
      container.innerHTML = `<div class="card">${esc(EMPTY_TEXT)}</div>`;
      return;
    }

    filtered.forEach(project => container.appendChild(makeCard(project)));
  } catch (err) {
    container.innerHTML = `<div class="card">${esc(err.message || 'Помилка')}</div>`;
  }
});
</script>
