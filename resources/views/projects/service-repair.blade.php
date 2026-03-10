@push('styles')
  <link rel="stylesheet" href="/css/project.css?v={{ filemtime(public_path('css/project.css')) }}">
@endpush

@extends('layouts.app')

@section('content')
<main class="projects-main">

  <div class="projects-title-card">
    <div class="projects-title">
      🛠 Сервіс та ремонт
    </div>
  </div>

  <div class="card" style="margin-bottom:15px;">
    <button
      type="button"
      id="openServiceRequestModalBtn"
      class="btn"
      style="width:100%; display:flex; align-items:center; justify-content:center;"
    >
      ➕ Створити заявку
    </button>
  </div>

  <div id="serviceRequestsContainer"></div>

  <div id="serviceRequestModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); align-items:center; justify-content:center; z-index:3005;">
    <div class="card" style="width:min(680px, calc(100vw - 24px)); max-height:85vh; overflow:auto;">
      <div style="font-weight:800; font-size:20px; margin-bottom:14px; text-align:center;">
        Нова сервісна заявка
      </div>

      <div style="display:grid; gap:10px;">
        <div>
          <div style="font-weight:700; margin-bottom:6px;">Ім'я</div>
          <input id="serviceClientName" class="btn" placeholder="ПІБ або назва клієнта" style="width:100%; margin-bottom:0;">
        </div>

        <div>
          <div style="font-weight:700; margin-bottom:6px;">Населений пункт</div>
          <input id="serviceSettlement" class="btn" placeholder="Місто / село" style="width:100%; margin-bottom:0;">
        </div>

        <div>
          <div style="font-weight:700; margin-bottom:6px;">Номер телефону</div>
          <input id="servicePhoneNumber" class="btn" placeholder="+380..." style="width:100%; margin-bottom:0;">
        </div>

        <div>
          <div style="font-weight:700; margin-bottom:6px;">Група в Telegram</div>
          <input id="serviceTelegramLink" class="btn" placeholder="Посилання на Telegram" style="width:100%; margin-bottom:0;">
          <button type="button" id="openServiceTelegramBtn" class="btn mini" style="margin-top:8px; margin-bottom:0;">✈️ Відкрити Telegram</button>
        </div>

        <div>
          <div style="font-weight:700; margin-bottom:6px;">Геолокація</div>
          <input id="serviceGeoLink" class="btn" placeholder="Посилання на геолокацію" style="width:100%; margin-bottom:0;">
          <button type="button" id="openServiceGeoBtn" class="btn mini" style="margin-top:8px; margin-bottom:0;">📍 Відкрити Google Maps</button>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
          <div>
            <div style="font-weight:700; margin-bottom:6px;">Електрик</div>
            <select id="serviceElectrician" class="btn" style="width:100%; margin-bottom:0;">
              <option value="">Не вказано</option>
            </select>
          </div>

          <div>
            <div style="font-weight:700; margin-bottom:6px;">Монтажна бригада</div>
            <select id="serviceInstallationTeam" class="btn" style="width:100%; margin-bottom:0;">
              <option value="">Не вказано</option>
            </select>
          </div>
        </div>

        <div>
          <div style="font-weight:700; margin-bottom:6px;">Терміновість</div>
          <div id="serviceUrgentToggle" class="segmented" style="margin-top:0;">
            <button type="button" data-value="0" class="active">Звичайна</button>
            <button type="button" data-value="1">Терміново</button>
          </div>
        </div>

        <div>
          <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:6px;">
            <div style="font-weight:700;">Опис</div>
            <button type="button" id="serviceDictationBtn" class="btn mini" style="margin-bottom:0;">🎙 Диктувати</button>
          </div>
          <textarea
            id="serviceDescription"
            class="btn"
            placeholder="Детальний опис задачі"
            style="width:100%; min-height:140px; resize:vertical; margin-bottom:0;"
          ></textarea>
          <div id="serviceDictationHint" style="font-size:12px; opacity:.7; margin-top:6px;">
            Голосовий ввід працює через браузерний Web Speech API, якщо браузер підтримує цю функцію.
          </div>
        </div>
      </div>

      <div style="display:flex; gap:10px; margin-top:16px;">
        <button type="button" id="saveServiceRequestBtn" class="btn" style="flex:1; margin-bottom:0;">Зберегти</button>
        <button type="button" id="closeServiceRequestModalBtn" class="btn" style="flex:1; margin-bottom:0; background:#333;">Скасувати</button>
      </div>
    </div>
  </div>

</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const modal = document.getElementById('serviceRequestModal');
  const container = document.getElementById('serviceRequestsContainer');
  const urgentToggle = document.getElementById('serviceUrgentToggle');
  const clientNameEl = document.getElementById('serviceClientName');
  const settlementEl = document.getElementById('serviceSettlement');
  const phoneNumberEl = document.getElementById('servicePhoneNumber');
  const descEl = document.getElementById('serviceDescription');
  const dictationBtn = document.getElementById('serviceDictationBtn');
  const dictationHint = document.getElementById('serviceDictationHint');
  const telegramInputEl = document.getElementById('serviceTelegramLink');
  const geoInputEl = document.getElementById('serviceGeoLink');
  const electricianEl = document.getElementById('serviceElectrician');
  const installationTeamEl = document.getElementById('serviceInstallationTeam');
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

  let urgentValue = '0';
  let recognition = null;
  let dictationActive = false;
  const OPEN_SERVICE_KEY = 'service_repair_open_id';
  const OPEN_SERVICE_SECTIONS_KEY = 'service_repair_open_sections';

  const esc = (v) => String(v ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

  function normalizeUrl(raw) {
    const v = String(raw || '').trim();
    if (!v) return '';
    if (/^https?:\/\//i.test(v)) return v;
    return `https://${v}`;
  }

  function getRememberedOpenSections() {
    try {
      const parsed = JSON.parse(localStorage.getItem(OPEN_SERVICE_SECTIONS_KEY) || '{}');
      return parsed && typeof parsed === 'object' ? parsed : {};
    } catch (_) {
      return {};
    }
  }

  function setRememberedOpenSections(serviceId, sectionKeys) {
    const current = getRememberedOpenSections();
    current[String(serviceId)] = Array.isArray(sectionKeys) ? sectionKeys : [];
    localStorage.setItem(OPEN_SERVICE_SECTIONS_KEY, JSON.stringify(current));
  }

  function clearRememberedOpenSections(serviceId) {
    const current = getRememberedOpenSections();
    delete current[String(serviceId)];
    localStorage.setItem(OPEN_SERVICE_SECTIONS_KEY, JSON.stringify(current));
  }

  function renderRequests(items) {
    if (!Array.isArray(items) || !items.length) {
      container.innerHTML = `
        <div class="card">
          <div style="opacity:.75;">Поки немає сервісних заявок.</div>
        </div>
      `;
      return;
    }

    const rememberedId = Number(localStorage.getItem(OPEN_SERVICE_KEY) || 0);
    const rememberedSectionsMap = getRememberedOpenSections();
    const rememberedSections = Array.isArray(rememberedSectionsMap[String(rememberedId)])
      ? rememberedSectionsMap[String(rememberedId)]
      : [];

    container.innerHTML = items.map(item => {
      const urgentBadge = item.is_urgent
        ? `<span class="tag" style="background:rgba(255, 0, 26, .14); border-color:rgba(255, 0, 26, .35); color:#ff8a96;">Терміново</span>`
        : '';

      const telegramHtml = item.telegram_group_link
        ? `<a href="${esc(normalizeUrl(item.telegram_group_link))}" target="_blank" rel="noopener" class="btn mini" style="text-decoration:none; margin-bottom:0;">✈️ Telegram</a>`
        : '';

      const mapsHtml = item.geo_location_link
        ? `<a href="${esc(normalizeUrl(item.geo_location_link))}" target="_blank" rel="noopener" class="btn mini" style="text-decoration:none; margin-bottom:0;">📍 Геолокація</a>`
        : '';

      const isOpen = rememberedId === Number(item.id);

      return `
        <div class="card service-card" data-id="${item.id}" style="margin-bottom:30px; cursor:pointer; ${item.is_urgent ? 'border:2px solid #ff001aad;' : ''}">
          <div class="project-header" style="display:flex; justify-content:space-between; gap:10px; align-items:flex-start;">
            <div>
              <div style="font-weight:800; font-size:18px;">${esc(item.client_name)}</div>
              <div style="opacity:.75; margin-top:4px;">${esc(item.settlement)}</div>
            </div>

          </div>

          <div class="project-body" style="display:${isOpen ? 'block' : 'none'}; margin-top:12px; border-top:1px solid #ffffff20; padding-top:12px;">
            <div style="text-align:center;">
              
              ${urgentBadge}
              <div style="opacity:.65; font-size:12px; font-weight:600; margin-top:14px;">${esc(item.created_at || '')}</div>
              <div style="margin-top:8px;">
                <button type="button" class="btn mini service-expand-btn" style="margin-bottom:14px;">Розкрити сервіс</button>
              </div>
            </div>
            <div class="project-section ${isOpen && rememberedSections.includes('client') ? 'is-open' : ''}" data-section data-section-key="client">
              <button type="button" class="project-section-toggle">
                <span>Дані клієнта</span>
                <span class="project-section-caret">▸</span>
              </button>
              <div class="project-section-body">
                <div class="project-kv">
                  <div class="project-field-label" style="margin-top:14px;">Ім'я</div>
                  <div style="margin-bottom:14px;">${esc(item.client_name)}</div>
                </div>
                <hr style="margin:14px 0; border-color:#ffffff20;">
                <div class="project-kv">
                  <div class="project-field-label">Населений пункт</div>
                  <div>${esc(item.settlement || 'Не вказано')}</div>
                </div>
                <hr style="margin:14px 0; border-color:#ffffff20;">
                <div class="project-kv">
                  <div class="project-field-label">Номер телефону</div>
                  <div>${item.phone_number ? `<a href="tel:${esc(String(item.phone_number).replace(/[^\d+]/g, ''))}" style="color:inherit; text-decoration:none;">${esc(item.phone_number)}</a>` : 'Не вказано'}</div>
                </div>
                <div style="display:flex; flex-wrap:wrap; gap:8px; margin-top:10px;">
                  ${telegramHtml}
                  ${mapsHtml}
                </div>
              </div>
            </div>

            <div class="project-section ${isOpen && rememberedSections.includes('staff') ? 'is-open' : ''}" data-section data-section-key="staff">
              <button type="button" class="project-section-toggle">
                <span>Персонал</span>
                <span class="project-section-caret">▸</span>
              </button>
              <div class="project-section-body">
                <div class="project-kv">
                  <div class="project-field-label" style="margin-top:14px;">Електрик</div>
                  <div>${esc(item.electrician || 'Не вказано')}</div>
                </div>
                <hr style="margin:14px 0; border-color:#ffffff20;">
                <div class="project-kv">
                  <div class="project-field-label">Монтажна бригада</div>
                  <div>${esc(item.installation_team || 'Не вказано')}</div>
                </div>
              </div>
            </div>

            <div class="project-section ${isOpen && rememberedSections.includes('description') ? 'is-open' : ''}" data-section data-section-key="description">
              <button type="button" class="project-section-toggle">
                <span>Опис</span>
                <span class="project-section-caret">▸</span>
              </button>
              <div class="project-section-body">
                <div style="white-space:pre-wrap; word-break:break-word;">${esc(item.description || '')}</div>
              </div>
            </div>

            <div style="margin-top:12px;">
              <button
                type="button"
                class="btn danger delete-service-btn"
                data-id="${item.id}"
                style="width:100%; margin-bottom:0;"
              >
                🗑 Видалити сервіс
              </button>
            </div>
          </div>
        </div>
      `;
    }).join('');

    const closeAllServiceCards = () => {
      container.querySelectorAll('.service-card').forEach((otherCard) => {
        const otherId = otherCard.dataset.id || '';
        const otherBody = otherCard.querySelector('.project-body');
        if (otherBody) {
          otherBody.style.display = 'none';
        }
        otherCard.querySelectorAll('.project-section').forEach((section) => {
          section.classList.remove('is-open');
        });
        if (otherId) {
          clearRememberedOpenSections(otherId);
        }
        const otherBtn = otherCard.querySelector('.service-expand-btn');
        if (otherBtn) {
          otherBtn.textContent = 'Розкрити сервіс';
        }
      });
      localStorage.removeItem(OPEN_SERVICE_KEY);
    };

    const openServiceCard = (card, openSections = false) => {
      if (!card) return;

      closeAllServiceCards();

      const body = card.querySelector('.project-body');
      if (!body) return;

      body.style.display = 'block';
      localStorage.setItem(OPEN_SERVICE_KEY, String(card.dataset.id || ''));

      const sections = Array.from(card.querySelectorAll('.project-section'));
      sections.forEach((section) => {
        if (openSections) {
          section.classList.add('is-open');
        } else {
          section.classList.remove('is-open');
        }
      });
      setRememberedOpenSections(
        card.dataset.id || '',
        openSections ? sections.map((section) => section.dataset.sectionKey).filter(Boolean) : []
      );

      const btn = card.querySelector('.service-expand-btn');
      if (btn) {
        btn.textContent = openSections ? 'Згорнути сервіс' : 'Розкрити сервіс';
      }
    };

    const collapseServiceCard = (card) => {
      if (!card) return;
      const body = card.querySelector('.project-body');
      if (body) {
        body.style.display = 'none';
      }
      card.querySelectorAll('.project-section').forEach((section) => {
        section.classList.remove('is-open');
      });
      clearRememberedOpenSections(card.dataset.id || '');
      const btn = card.querySelector('.service-expand-btn');
      if (btn) {
        btn.textContent = 'Розкрити сервіс';
      }
      if (localStorage.getItem(OPEN_SERVICE_KEY) === String(card.dataset.id || '')) {
        localStorage.removeItem(OPEN_SERVICE_KEY);
      }
    };

    container.querySelectorAll('.service-card .project-header').forEach((header) => {
      header.addEventListener('click', () => {
        const card = header.closest('.service-card');
        if (!card) return;
        const body = card.querySelector('.project-body');
        if (!body) return;

        const isOpen = window.getComputedStyle(body).display !== 'none';
        if (isOpen) {
          collapseServiceCard(card);
        } else {
          openServiceCard(card, false);
        }
      });
    });

    container.querySelectorAll('.service-expand-btn').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const card = btn.closest('.service-card');
        if (!card) return;
        const body = card.querySelector('.project-body');
        if (!body) return;

        const sections = Array.from(card.querySelectorAll('.project-section'));
        const bodyIsOpen = window.getComputedStyle(body).display !== 'none';
        const allSectionsOpen = sections.length > 0 && sections.every((section) => section.classList.contains('is-open'));

        if (!bodyIsOpen) {
          openServiceCard(card, true);
          return;
        }

        if (!allSectionsOpen) {
          sections.forEach((section) => section.classList.add('is-open'));
          setRememberedOpenSections(
            card.dataset.id || '',
            sections.map((section) => section.dataset.sectionKey).filter(Boolean)
          );
          btn.textContent = 'Згорнути сервіс';
          return;
        }

        collapseServiceCard(card);
      });
    });

    container.querySelectorAll('.project-section-toggle').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();

        const section = btn.closest('.project-section');
        if (!section) return;

        section.classList.toggle('is-open');

        const card = btn.closest('.service-card');
        if (!card) return;

        const openSectionKeys = Array.from(card.querySelectorAll('.project-section.is-open'))
          .map((item) => item.dataset.sectionKey)
          .filter(Boolean);
        setRememberedOpenSections(card.dataset.id || '', openSectionKeys);

        const expandBtn = card.querySelector('.service-expand-btn');
        if (!expandBtn) return;

        const sections = Array.from(card.querySelectorAll('.project-section'));
        const allSectionsOpen = sections.length > 0 && sections.every((item) => item.classList.contains('is-open'));
        expandBtn.textContent = allSectionsOpen ? 'Згорнути сервіс' : 'Розкрити сервіс';
      });
    });
  }

  async function loadServiceRequests() {
    const res = await fetch('/api/service-requests', { headers: { Accept: 'application/json' } });
    if (!res.ok) throw new Error('Не вдалося завантажити сервісні заявки');
    const items = await res.json();
    renderRequests(items);
  }

  async function loadStaffOptions() {
    const res = await fetch('/api/construction-staff-options', { headers: { Accept: 'application/json' } });
    if (!res.ok) return;
    const data = await res.json();
    const staff = data.staff || data;

    const electricianOptions = Array.isArray(staff.electrician) ? staff.electrician : [];
    const installerOptions = Array.isArray(staff.installation_team) ? staff.installation_team : [];

    const electricianEl = document.getElementById('serviceElectrician');
    const installerEl = document.getElementById('serviceInstallationTeam');

    electricianOptions.forEach(opt => {
      const option = document.createElement('option');
      option.value = String(opt.name || opt);
      option.textContent = String(opt.name || opt);
      electricianEl.appendChild(option);
    });

    installerOptions.forEach(opt => {
      const option = document.createElement('option');
      option.value = String(opt.name || opt);
      option.textContent = String(opt.name || opt);
      installerEl.appendChild(option);
    });
  }

  function resetForm() {
    clientNameEl.value = '';
    settlementEl.value = '';
    phoneNumberEl.value = '';
    telegramInputEl.value = '';
    geoInputEl.value = '';
    electricianEl.value = '';
    installationTeamEl.value = '';
    descEl.value = '';
    urgentValue = '0';
    urgentToggle.querySelectorAll('button').forEach((btn, index) => {
      btn.classList.toggle('active', index === 0);
    });
  }

  function stopDictation() {
    if (recognition && dictationActive) {
      recognition.stop();
    }
    dictationActive = false;
    dictationBtn.textContent = '🎙 Диктувати';
  }

  document.getElementById('openServiceRequestModalBtn').addEventListener('click', () => {
    resetForm();
    modal.style.display = 'flex';
  });

  function openDraftLink(rawValue, emptyMessage) {
    const url = normalizeUrl(rawValue);
    if (!url) {
      alert(emptyMessage);
      return;
    }
    window.open(url, '_blank', 'noopener');
  }

  document.getElementById('openServiceTelegramBtn').addEventListener('click', () => {
    openDraftLink(telegramInputEl.value, 'Введи посилання на Telegram');
  });

  document.getElementById('openServiceGeoBtn').addEventListener('click', () => {
    openDraftLink(geoInputEl.value, 'Введи посилання на геолокацію');
  });

  document.getElementById('closeServiceRequestModalBtn').addEventListener('click', () => {
    stopDictation();
    modal.style.display = 'none';
  });

  modal.addEventListener('click', (e) => {
    if (e.target === modal) {
      stopDictation();
      modal.style.display = 'none';
    }
  });

  urgentToggle.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-value]');
    if (!btn) return;
    urgentValue = btn.dataset.value || '0';
    urgentToggle.querySelectorAll('button').forEach(el => {
      el.classList.toggle('active', el === btn);
    });
  });

  const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (SpeechRecognition) {
    recognition = new SpeechRecognition();
    recognition.lang = 'uk-UA';
    recognition.interimResults = true;
    recognition.continuous = true;

    let committedText = '';

    recognition.addEventListener('start', () => {
      dictationActive = true;
      committedText = descEl.value.trim();
      dictationBtn.textContent = '⏹ Зупинити';
      dictationHint.textContent = 'Диктування увімкнене. Говоріть, текст буде додаватися в опис.';
    });

    recognition.addEventListener('result', (event) => {
      let interim = '';
      for (let i = event.resultIndex; i < event.results.length; i++) {
        const text = event.results[i][0]?.transcript || '';
        if (event.results[i].isFinal) {
          committedText = `${committedText} ${text}`.trim();
        } else {
          interim += text;
        }
      }
      descEl.value = [committedText, interim.trim()].filter(Boolean).join(' ').trim();
    });

    recognition.addEventListener('end', () => {
      dictationActive = false;
      dictationBtn.textContent = '🎙 Диктувати';
      dictationHint.textContent = 'Голосовий ввід працює через браузерний Web Speech API, якщо браузер підтримує цю функцію.';
    });

    recognition.addEventListener('error', () => {
      dictationActive = false;
      dictationBtn.textContent = '🎙 Диктувати';
      dictationHint.textContent = 'Браузер не дав доступ до мікрофона або голосовий ввід недоступний.';
    });

    dictationBtn.addEventListener('click', () => {
      if (dictationActive) {
        stopDictation();
        return;
      }
      recognition.start();
    });
  } else {
    dictationBtn.disabled = true;
    dictationHint.textContent = 'У цьому браузері голосовий ввід недоступний. Найчастіше це працює в Chrome на Android.';
  }

  document.getElementById('saveServiceRequestBtn').addEventListener('click', async () => {
    const clientName = clientNameEl.value.trim();
    const settlement = settlementEl.value.trim();
    const description = descEl.value.trim();

    const payload = {
      client_name: clientName,
      settlement,
      phone_number: phoneNumberEl.value.trim(),
      telegram_group_link: telegramInputEl.value.trim(),
      geo_location_link: geoInputEl.value.trim(),
      electrician: electricianEl.value,
      installation_team: installationTeamEl.value,
      is_urgent: urgentValue === '1',
      description,
    };

    const missing = [];
    if (!clientName) missing.push('імʼя');
    if (!settlement) missing.push('населений пункт');
    if (!description) missing.push('опис');

    if (missing.length) {
      alert(`Заповни: ${missing.join(', ')}`);
      return;
    }

    try {
      const res = await fetch('/api/service-requests', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrf,
        },
        body: JSON.stringify(payload),
      });

      const json = await res.json();
      if (!res.ok || !json.ok) {
        throw new Error(json.error || 'Не вдалося створити сервісну заявку');
      }

      stopDictation();
      modal.style.display = 'none';
      await loadServiceRequests();
    } catch (error) {
      alert(error.message || 'Помилка');
    }
  });

  container.addEventListener('click', async (e) => {
    const btn = e.target instanceof Element ? e.target.closest('.delete-service-btn') : null;
    if (!btn) return;

    e.preventDefault();
    e.stopPropagation();

    const serviceId = btn.dataset.id;
    if (!serviceId) return;

    if (!confirm('Видалити сервісну картку?')) {
      return;
    }

    try {
      const res = await fetch(`/api/service-requests/${serviceId}`, {
        method: 'DELETE',
        headers: {
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrf,
        },
      });

      const json = await res.json();
      if (!res.ok || !json.ok) {
        throw new Error(json.error || 'Не вдалося видалити сервіс');
      }

      if (localStorage.getItem(OPEN_SERVICE_KEY) === String(serviceId)) {
        localStorage.removeItem(OPEN_SERVICE_KEY);
      }

      await loadServiceRequests();
    } catch (error) {
      alert(error.message || 'Помилка');
    }
  });

  Promise.allSettled([loadStaffOptions(), loadServiceRequests()])
    .then(() => {
      setInterval(() => {
        loadServiceRequests().catch(() => {});
      }, 15000);
    });
});
</script>

@include('partials.nav.bottom')
@endsection
