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
        </div>

        <div>
          <div style="font-weight:700; margin-bottom:6px;">Геолокація</div>
          <input id="serviceGeoLink" class="btn" placeholder="Посилання на геолокацію" style="width:100%; margin-bottom:0;">
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
  const descEl = document.getElementById('serviceDescription');
  const dictationBtn = document.getElementById('serviceDictationBtn');
  const dictationHint = document.getElementById('serviceDictationHint');
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

  let urgentValue = '0';
  let recognition = null;
  let dictationActive = false;

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

  function renderRequests(items) {
    if (!Array.isArray(items) || !items.length) {
      container.innerHTML = `
        <div class="card">
          <div style="opacity:.75;">Поки немає сервісних заявок.</div>
        </div>
      `;
      return;
    }

    container.innerHTML = items.map(item => {
      const urgentBadge = item.is_urgent
        ? `<span class="tag" style="background:rgba(255, 196, 0, .18); border-color:rgba(255, 196, 0, .35); color:#ffd76a;">Терміново</span>`
        : '';

      const phoneHtml = item.phone_number
        ? `<a href="tel:${esc(String(item.phone_number).replace(/[^\d+]/g, ''))}" class="btn mini" style="text-decoration:none; margin-bottom:0;">📞 ${esc(item.phone_number)}</a>`
        : '';

      const telegramHtml = item.telegram_group_link
        ? `<a href="${esc(normalizeUrl(item.telegram_group_link))}" target="_blank" rel="noopener" class="btn mini" style="text-decoration:none; margin-bottom:0;">✈️ Telegram</a>`
        : '';

      const mapsHtml = item.geo_location_link
        ? `<a href="${esc(normalizeUrl(item.geo_location_link))}" target="_blank" rel="noopener" class="btn mini" style="text-decoration:none; margin-bottom:0;">📍 Геолокація</a>`
        : '';

      return `
        <div class="card" style="margin-bottom:12px; ${item.is_urgent ? 'border:2px solid #f2c200;' : ''}">
          <div style="display:flex; justify-content:space-between; gap:10px; align-items:flex-start;">
            <div>
              <div style="font-weight:800; font-size:18px;">${esc(item.client_name)}</div>
              <div style="opacity:.75; margin-top:4px;">${esc(item.settlement)}</div>
            </div>
            <div style="text-align:right;">
              ${urgentBadge}
              <div style="opacity:.65; font-size:12px; margin-top:6px;">${esc(item.created_at || '')}</div>
            </div>
          </div>

          <div style="margin-top:12px; display:grid; gap:8px;">
            <div><strong>Електрик:</strong> ${esc(item.electrician || 'Не вказано')}</div>
            <div><strong>Монтажна бригада:</strong> ${esc(item.installation_team || 'Не вказано')}</div>
            <div><strong>Опис:</strong><br>${esc(item.description || '').replace(/\n/g, '<br>')}</div>
          </div>

          <div style="display:flex; flex-wrap:wrap; gap:8px; margin-top:14px;">
            ${phoneHtml}
            ${telegramHtml}
            ${mapsHtml}
          </div>
        </div>
      `;
    }).join('');
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
    document.getElementById('serviceClientName').value = '';
    document.getElementById('serviceSettlement').value = '';
    document.getElementById('servicePhoneNumber').value = '';
    document.getElementById('serviceTelegramLink').value = '';
    document.getElementById('serviceGeoLink').value = '';
    document.getElementById('serviceElectrician').value = '';
    document.getElementById('serviceInstallationTeam').value = '';
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
    const payload = {
      client_name: document.getElementById('serviceClientName').value.trim(),
      settlement: document.getElementById('serviceSettlement').value.trim(),
      phone_number: document.getElementById('servicePhoneNumber').value.trim(),
      telegram_group_link: document.getElementById('serviceTelegramLink').value.trim(),
      geo_location_link: document.getElementById('serviceGeoLink').value.trim(),
      electrician: document.getElementById('serviceElectrician').value,
      installation_team: document.getElementById('serviceInstallationTeam').value,
      is_urgent: urgentValue === '1',
      description: descEl.value.trim(),
    };

    if (!payload.client_name || !payload.settlement || !payload.description) {
      alert('Заповни імʼя, населений пункт і опис');
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
