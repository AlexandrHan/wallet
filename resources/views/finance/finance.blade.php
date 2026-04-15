@php
  $leadManagers = \App\Models\User::query()
    ->whereIn('role', ['ntv', 'manager'])
    ->orderBy('name')
    ->get(['id', 'name', 'email', 'actor'])
    ->map(function ($user) {
      $label = trim((string) ($user->name ?? ''));

      if ($label === '') {
        $label = trim((string) ($user->actor ?? ''));
      }

      if ($label === '') {
        $label = trim((string) ($user->email ?? ''));
      }

      if ($label === '') {
        $label = 'Менеджер #' . $user->id;
      }

      return [
        'id' => (int) $user->id,
        'label' => $label,
      ];
    })
    ->values();
@endphp

@extends('layouts.app')

@push('styles')
  <link rel="stylesheet" href="/css/nav-telegram.css?v={{ filemtime(public_path('css/nav-telegram.css')) }}">

@endpush

@section('content')
<body class="{{ auth()->check() ? 'has-tg-nav' : '' }} finance-openclaw">



<main class="">


  <div class="card">
    <div>

      <button id="createProjectBtn" class="btn" style="align-items:center;width: 100%;background:rgba(84, 192, 134, 0.71); margin-bottom:0;">➕ Новий проект</button>
    </div>
  </div>

  <div class="card" style="margin-top:15px; display:flex; flex-direction:column; gap:8px;">
    <input id="globalSearchInput" class="btn" type="text" placeholder="🔍 Пошук по прізвищу..." style="width:100%; margin-bottom:0;">
    <select id="managerFilterSelect" class="btn" style="width:100%; margin-bottom:0;">
      <option value="">👤 Всі менеджери</option>
    </select>
  </div>

  <div id="projectsContainer" style="margin-top:20px;"></div>
  <div id="projectModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); align-items:center; justify-content:center;">
  <div style="background:#111; padding:20px; border-radius:10px; width:320px;">

    <div style="font-weight:600; margin-bottom:10px;">Новий проект</div>

    <input id="clientName" class="btn" placeholder="ПІБ клієнта" style="width:100%; margin-bottom:10px;">

    <input id="totalAmount" type="number" class="btn" placeholder="Сума проекту" style="width:100%; margin-bottom:10px;">

    <div id="projectTypeSegmented" class="segmented" style="margin-top:0; margin-bottom:10px;">
      <button type="button" class="active" data-project-type="project">Проект</button>
      <button type="button" data-project-type="retail">Роздріб</button>
    </div>

    <select id="projectCurrency" class="btn" style="width:100%; margin-bottom:15px;">
      <option value="USD">USD</option>
      <option value="UAH">UAH</option>
      <option value="EUR">EUR</option>
    </select>

    <button id="saveProjectBtn" class="btn" style="width:100%; margin-bottom:8px;">Створити</button>
    <button id="closeModalBtn" class="btn" style="width:100%; background:#333;">Скасувати</button>

  </div>
</div>

{{-- ── Модалка виправлення валюти ─────────────────────────────── --}}
<div id="correctCurrencyModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); align-items:center; justify-content:center; z-index:1000;">
  <div style="background:#111; padding:20px; border-radius:10px; width:320px;">
    <div style="font-weight:600; margin-bottom:4px;">✏️ Виправити валюту</div>
    <div id="correctCurrencyOldInfo" style="font-size:12px; opacity:.65; margin-bottom:14px;"></div>

    <label style="font-size:12px; opacity:.75; display:block; margin-bottom:4px;">Правильна сума</label>
    <input id="correctAmount" type="number" class="btn" placeholder="Сума" step="0.01" min="0.01"
      style="width:100%; margin-bottom:10px;">

    <label style="font-size:12px; opacity:.75; display:block; margin-bottom:4px;">Правильна валюта</label>
    <select id="correctCurrency" class="btn" style="width:100%; margin-bottom:16px;">
      <option value="USD">USD</option>
      <option value="UAH">UAH</option>
      <option value="EUR">EUR</option>
    </select>

    <button id="saveCorrectBtn" class="btn" style="width:100%; margin-bottom:8px; background:#1a5c2e;">Зберегти виправлення</button>
    <button id="closeCorrectBtn" class="btn" style="width:100%; background:#333;">Скасувати</button>
  </div>
</div>

<div id="advanceModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); align-items:center; justify-content:center;">
  <div style="background:#111; padding:20px; border-radius:10px; width:320px;">

    <div style="font-weight:600; margin-bottom:10px;">Створити аванс</div>

    <input id="advanceAmount" type="number" class="btn" placeholder="Сума авансу" style="width:100%; margin-bottom:10px;">

    <select id="advanceCurrency" class="btn" style="width:100%; margin-bottom:10px;">
      <option value="USD">USD</option>
      <option value="UAH">UAH</option>
      <option value="EUR">EUR</option>
    </select>

    <input id="exchangeRate" type="number" step="0.0001" class="btn" placeholder="Курс до USD" style="width:100%; margin-bottom:15px; display:none;">
    <div id="exchangeRateHint" style="display:none; margin:-4px 0 12px; font-size:12px; opacity:.78;"></div>

    <button id="saveAdvanceBtn" class="btn" style="width:100%; margin-bottom:8px;">Зберегти</button>
    <button id="closeAdvanceBtn" class="btn" style="width:100%; background:#333;">Скасувати</button>

  </div>
</div>


</main>

<script>
document.addEventListener('DOMContentLoaded', function () {

  const AUTH_USER = @json(auth()->user());
  const IS_OWNER = AUTH_USER && AUTH_USER.role === 'owner';
  const IS_HLUSHCHENKO = AUTH_USER && AUTH_USER.actor === 'hlushchenko';
  const LEAD_MANAGER_OPTIONS = @json($leadManagers);

  const formatMoney = (value, currency) => {
    const symbols = { UAH: '₴', USD: '$', EUR: '€' };
    const formatted = new Intl.NumberFormat('uk-UA').format(value);
    return `${formatted} ${symbols[currency] ?? currency}`;
  };

  // ✅ FIX: запам'ятовуємо відкриту картку, щоб після reload вона не згорталась
  const OPEN_KEY = 'finance_open_project_id';
  const OPEN_PAID_KEY = 'finance_open_paid_projects';
  const OPEN_ACTIVE_KEY = 'finance_open_active_projects';
  const SEARCH_PAID_KEY = 'finance_search_paid_projects';
  const SEARCH_ACTIVE_KEY = 'finance_search_active_projects';
  const MANAGER_FILTER_KEY = 'finance_manager_filter';
  const GLOBAL_SEARCH_KEY  = 'finance_global_search';
  const rememberOpenProject = (id) => localStorage.setItem(OPEN_KEY, String(id));
  const getOpenProject = () => {
    const v = localStorage.getItem(OPEN_KEY);
    return v ? Number(v) : null;
  };
  const POLL_MS = 20000;
  let isProjectsLoading = false;
  let pendingRefresh = false;
  let cachedProjects = null;
  let projectsPollTimer = null;

  function renderProjects(projects) {
    const container = document.getElementById('projectsContainer');
    if (!container) return;

    // Зберігаємо фокус і курсор перед ренедером
    const focusedEl = document.activeElement;
    const focusedClass = focusedEl?.classList.contains('paid-projects-search') ? 'paid-projects-search'
                       : focusedEl?.classList.contains('active-projects-search') ? 'active-projects-search'
                       : null;
    const focusSel = focusedClass ? [focusedEl.selectionStart, focusedEl.selectionEnd] : null;

    const openId = getOpenProject();
    const isPaidOpen   = localStorage.getItem(OPEN_PAID_KEY) === '1';
    // Default: секція "Проавансовані" відкрита, якщо користувач явно не закрив ('0')
    const isActiveOpen = localStorage.getItem(OPEN_ACTIVE_KEY) !== '0';
    const paidQuery = (localStorage.getItem(SEARCH_PAID_KEY) || '').trim();
    const activeQuery = (localStorage.getItem(SEARCH_ACTIVE_KEY) || '').trim();
    cachedProjects = projects;
    container.innerHTML = '';

    // ── Оновлюємо список менеджерів у фільтрі з реальних даних проектів ──
    const managerFilter  = localStorage.getItem(MANAGER_FILTER_KEY) || '';
    const globalSearch   = localStorage.getItem(GLOBAL_SEARCH_KEY)  || '';
    const sel = document.getElementById('managerFilterSelect');
    if (sel) {
      const names = [...new Set(
        (projects || []).map(p => p.manager_name || '').filter(n => n && n !== '—')
      )].sort((a, b) => a.localeCompare(b, 'uk'));
      const current = sel.value || managerFilter;
      sel.innerHTML = '<option value="">👤 Всі менеджери</option>' +
        names.map(n => `<option value="${n.replace(/"/g,'&quot;')}">${n}</option>`).join('');
      sel.value = current;
    }
    const gsi = document.getElementById('globalSearchInput');
    if (gsi && document.activeElement !== gsi) gsi.value = globalSearch;

    const normalizeG = (v) => String(v ?? '').toLowerCase().trim();
    const filteredByManager = (projects || [])
      .filter(p => !managerFilter || (p.manager_name || '') === managerFilter)
      .filter(p => !globalSearch || [p.client_name, p.manager_name].some(v => normalizeG(v).includes(normalizeG(globalSearch))));

    const byName = (a, b) => String(a.client_name || '').localeCompare(String(b.client_name || ''), 'uk', { sensitivity: 'base' });
    const toNum = (v) => {
      const n = Number(v);
      return Number.isFinite(n) ? n : 0;
    };
    const isPaidProject = (p) => {
      if (p.is_paid !== undefined && p.is_paid !== null) {
        return Boolean(p.is_paid);
      }

      const total = toNum(p.total_amount);
      const paid = toNum(p.paid_amount);
      const remaining = toNum(p.remaining_amount);
      return total > 0 && paid >= total && remaining <= 0;
    };
    const hasPending = (p) => (p.transfers || []).some(t => t.status === 'pending');
    const allActiveProjects = filteredByManager
      .filter(p => !isPaidProject(p))
      .sort((a, b) => {
        const ap = hasPending(a) ? 1 : 0;
        const bp = hasPending(b) ? 1 : 0;
        if (bp !== ap) return bp - ap; // pending — вгорі
        return byName(a, b);           // решта — за алфавітом
      });
    const allPaidProjects = filteredByManager
      .filter(p => isPaidProject(p))
      .sort(byName);

    const normalizeText = (v) => String(v ?? '').toLowerCase().trim();
    const numberForms = (n) => {
      const num = Number(n ?? 0);
      if (!Number.isFinite(num)) return '';
      const fixed = num.toFixed(2);
      const short = String(num);
      const comma = fixed.replace('.', ',');
      return `${short} ${fixed} ${comma}`;
    };
    const projectSearchText = (p) => {
      const transfersText = (p.transfers || []).map(t => [
        t.amount,
        t.currency,
        t.exchange_rate,
        t.project_amount,
        t.usd_amount,
        t.status,
        t.created_at
      ].join(' ')).join(' ');

      return normalizeText([
        p.client_name,
        p.currency,
        p.manager_name,
        p.created_at,
        numberForms(p.total_amount),
        numberForms(p.paid_amount),
        numberForms(p.pending_amount),
        numberForms(p.remaining_amount),
        transfersText
      ].join(' '));
    };
    const matchesQuery = (p, query) => {
      const q = normalizeText(query);
      if (!q) return true;
      return projectSearchText(p).includes(q);
    };
    const activeProjects = allActiveProjects.filter(p => matchesQuery(p, activeQuery));
    const paidProjects = allPaidProjects.filter(p => matchesQuery(p, paidQuery));

    function buildProjectCard(p, { isPaidSection = false } = {}) {
      const card = document.createElement('div');
      card.className = 'card';
      card.style.marginTop = '15px';
      card.style.cursor = 'pointer';

      const debt = p.remaining_amount;

      const transfersHtml = (p.transfers.length === 0)
        ? `<div style="opacity:.6;">Немає авансів</div>`
        : p.transfers.map(t => {
            const convertedInfo =
              (t.currency !== p.currency && t.exchange_rate)
                ? `
                    <div style="font-size:12px; opacity:.7;">
                      ≈ ${formatMoney(t.project_amount ?? t.usd_amount, p.currency)}
                    </div>
                    <div style="font-size:12px; opacity:.6;">
                      Курс: ${t.exchange_rate}
                    </div>
                  `
                : '';

            const IS_ACCOUNTANT = AUTH_USER && AUTH_USER.role === 'accountant';
            const canAccept = (IS_OWNER || IS_ACCOUNTANT) && t.target_owner && (t.target_owner === AUTH_USER.actor);
            const today = new Date();
            const todayFormatted = today.toLocaleDateString('uk-UA');
            const isToday = t.created_at.startsWith(todayFormatted);

            const isCancelled = t.status === 'cancelled';
            // "Виправити валюту" — лише для активних (не cancelled)
            const canCorrect = !isCancelled && (t.status === 'pending' || IS_OWNER);
            // "Скасувати" — лише pending
            const canCancel  = t.status === 'pending';

            const ownerLabels = { accountant: 'Бухгалтер', hlushchenko: 'Глущенко', kolisnyk: 'Колісник' };
            const acceptedByLabel = t.accepted_by ? (ownerLabels[t.accepted_by] ?? t.accepted_by) : '';
            const statusBlock = t.status === 'accepted'
              ? `— ✅ Прийнято${acceptedByLabel ? ' ' + acceptedByLabel : ''}`
              : isCancelled
              ? `— <span style="color:#888;">🔴 Не активно</span>`
              : `
                  — ⏳ В очікуванні
                  ${canAccept ? `
                    <button
                      class="btn accept-advance-btn"
                      data-id="${t.id}"
                      style="margin-top:6px; width:100%;">
                      ✔ Прийняти
                    </button>
                  ` : ''}
                  ${canCancel ? `
                    <button
                      class="btn cancel-advance-btn"
                      data-id="${t.id}"
                      data-amount="${t.amount}"
                      data-currency="${t.currency}"
                      style="margin-top:4px; width:100%; background:#4a1a1a; font-size:12px;">
                      ❌ Скасувати
                    </button>
                  ` : ''}
                `;

            const correctBtn = canCorrect ? `
              <button
                class="btn correct-currency-btn"
                data-id="${t.id}"
                data-amount="${t.amount}"
                data-currency="${t.currency}"
                data-status="${t.status}"
                style="margin-top:6px; width:100%; background:#2a3a55; font-size:12px;">
                ✏️ Виправити валюту
              </button>
            ` : '';

            return `
              <div
                class="advance-card"
                data-transfer-id="${t.id}"
                style="margin-top:5px; padding:8px; background:#111; border-radius:6px; cursor:pointer; ${isCancelled ? 'opacity:.45;' : ''}">
                <div>
                  ${formatMoney(t.amount, t.currency)} ${statusBlock}
                </div>
                <div style="font-size:12px; opacity:.6;">
                  ${t.created_at}
                </div>
                ${isCancelled ? '' : convertedInfo}
                ${correctBtn}
              </div>
            `;
        }).join('');

      const IS_ACCOUNTANT_USER = AUTH_USER && AUTH_USER.role === 'accountant';

      // Бухгалтер: є accepted transfer до нього, і ще немає pending до власника
      const accountantAccepted = p.transfers.find(t =>
        t.status === 'accepted' && t.target_owner === 'accountant'
      );
      const forwardPending = p.transfers.find(t =>
        t.status === 'pending' && (t.target_owner === 'hlushchenko' || t.target_owner === 'kolisnyk')
      );

      let transferButtonsHtml = '';

      if (IS_ACCOUNTANT_USER) {
        if (accountantAccepted && !forwardPending) {
          transferButtonsHtml = `
            <button class="btn forward-owner-btn" data-project="${p.id}" data-owner="hlushchenko" style="margin-right:5px;">
              💸 Глущенко
            </button>
            <button class="btn forward-owner-btn" data-project="${p.id}" data-owner="kolisnyk">
              💸 Колісник
            </button>
          `;
        } else if (forwardPending) {
          transferButtonsHtml = `<div style="font-size:13px; opacity:.6;">⏳ Очікує підтвердження від власника</div>`;
        }
      } else if (!IS_OWNER) {
        if (p.pending_target_owner) {
          transferButtonsHtml = `
            <button class="btn cancel-owner-btn" data-project="${p.id}" style="width:100%; background:#333;">
              ↩️ Відмінити переказ
            </button>
          `;
        } else {
          transferButtonsHtml = `
            <button class="btn send-owner-btn" data-project="${p.id}" data-owner="hlushchenko" style="margin-right:5px;">
              💸 Глущенко
            </button>
            <button class="btn send-owner-btn" data-project="${p.id}" data-owner="kolisnyk" style="margin-right:5px;">
              💸 Колісник
            </button>
            <button class="btn send-owner-btn" data-project="${p.id}" data-owner="accountant">
              💸 Бухгалтер
            </button>
          `;
        }
      }

      const hasNtoMoney = Number(p.pending_amount || 0) > 0;
      if (hasNtoMoney) {
        card.style.border = '2px solid #f2c200';
      }

      card.innerHTML = `
          <div class="project-toggle" style="display:flex; justify-content:space-between;">
            <div style="font-weight:600;">
              ${p.client_name}
            </div>
            <div>
              ${formatMoney(p.total_amount, p.currency)}
            </div>
          </div>

          <div style="margin-top:4px; font-size:12px; opacity:.72;">
            Менеджер: ${p.manager_name || '—'}
          </div>

          <div style="margin-top:5px; font-weight:600; color:${debt > 0 ? '#f20000' : '#3bc97f'};">
            Борг: ${formatMoney(debt, p.currency)}
          </div>

          <div class="project-details" style="display:none; margin-top:15px; border-top:1px solid #ffffff; padding-top:10px;">

            <div style="opacity:.7;">Створено: ${p.created_at}</div>
            <div style="margin-top:10px;">
              <div style="font-size:12px; opacity:.7; margin-bottom:6px;">Ведучий менеджер</div>
              <select
                class="btn lead-manager-select"
                data-project-id="${p.id}"
                style="width:100%; margin-bottom:0;"
              >
                <option value="">Не вказано</option>
                ${LEAD_MANAGER_OPTIONS.map(option => `
                  <option value="${option.id}" ${Number(p.lead_manager_user_id || 0) === Number(option.id) ? 'selected' : ''}>
                    ${option.label}
                  </option>
                `).join('')}
              </select>
            </div>

            <div style="margin-top:8px;">
              Оплачено: ${formatMoney(p.paid_amount, p.currency)}
            </div>

            <div>
              Очікує підтвердження: ${formatMoney(p.pending_amount, p.currency)}
            </div>
            
            ${(AUTH_USER && (AUTH_USER.role === 'ntv' || AUTH_USER.role === 'owner' || AUTH_USER.role === 'accountant' || AUTH_USER.role === 'manager')) ? `
            <div style="margin-top:12px;">
              <button class="btn create-advance-btn" style="width:100%;" data-id="${p.id}" data-currency="${p.currency}">
                ➕ Створити аванс
              </button>
            </div>
          ` : ``}

            <div style="margin-top:10px; font-weight:600;">Аванси:</div>
            ${transfersHtml}

            ${(AUTH_USER && AUTH_USER.role !== 'owner') ? `
              <hr>
              <div style="font-size:16px; font-weight:800; margin-bottom: 14px; text-align:center;margin-top:24px;">Передати кошти</div>
              ${transferButtonsHtml}
            ` : ``}

            ${IS_HLUSHCHENKO ? `
              <hr style="margin-top:16px;">
              <button class="btn delete-project-btn" data-id="${p.id}" data-name="${p.client_name.replace(/"/g,'&quot;')}"
                style="width:100%; background:#8b1a1a; color:#fff; margin-top:8px;">
                🗑 Видалити проект
              </button>
            ` : ``}

          </div>
        `;

      card.addEventListener('click', function(e) {
        if (e.target.closest('button, input, select, textarea, a, label')) return;

        const details = card.querySelector('.project-details');
        const isOpen = details.style.display !== 'none';

        if (isOpen) {
          details.style.display = 'none';
          localStorage.removeItem(OPEN_KEY);
          // Refresh data now that the card is closed (may have been deferred during polling)
          window.refreshSalesProjects?.();
        } else {
          details.style.display = 'block';
          rememberOpenProject(p.id);
        }
      });

      if (!isPaidSection && openId && Number(p.id) === openId) {
        const details = card.querySelector('.project-details');
        if (details) details.style.display = 'block';
      }


      return card;
    }

    if (allPaidProjects.length > 0) {
      const shouldOpenPaid = isPaidOpen || !!paidQuery;

      const paidCard = document.createElement('div');
      paidCard.className = 'card';
      paidCard.style.marginTop = '15px';
      paidCard.innerHTML = `
        <div class="paid-projects-toggle" style="display:flex; justify-content:space-between; align-items:center; cursor:pointer;">
          <div style="font-weight:700;">✅ Оплачені</div>
          <div style="opacity:.75;">${paidProjects.length}${paidQuery ? ` / ${allPaidProjects.length}` : ''}</div>
        </div>
        <div class="paid-projects-details" style="display:${shouldOpenPaid ? 'block' : 'none'}; margin-top:12px; border-top:1px solid #ffffff; padding-top:10px;">
          <input
            class="btn paid-projects-search"
            type="text"
            value="${paidQuery.replace(/"/g, '&quot;')}"
            placeholder="Пошук: імʼя, сума, валюта, менеджер..."
            style="width:100%; margin-bottom:10px;"
          >
          <div class="paid-projects-list"></div>
        </div>
      `;

      const MONTH_NAMES_UK = ['Січень','Лютий','Березень','Квітень','Травень','Червень','Липень','Серпень','Вересень','Жовтень','Листопад','Грудень'];

      function parsePaidDate(p) {
        const raw = p.closed_at || p.created_at || '';
        const m = raw.match(/(\d{2})\.(\d{2})\.(\d{4})/);
        if (!m) return null;
        return { day: +m[1], month: +m[2], year: +m[3] };
      }

      // Group by year → month
      const byYear = {};
      paidProjects.forEach(p => {
        const d = parsePaidDate(p);
        const year  = d ? d.year  : 0;
        const month = d ? d.month : 0;
        if (!byYear[year]) byYear[year] = {};
        if (!byYear[year][month]) byYear[year][month] = [];
        byYear[year][month].push({ p, d });
      });
      const sortedYears = Object.keys(byYear).map(Number).sort((a, b) => b - a);

      const paidList = paidCard.querySelector('.paid-projects-list');

      if (paidProjects.length === 0) {
        paidList.innerHTML = `<div style="opacity:.75; text-align:center;">Нічого не знайдено</div>`;
      } else {
        const nowDate = new Date();
        const nowYear = nowDate.getFullYear();
        const nowMonth = nowDate.getMonth() + 1;

        sortedYears.forEach(year => {
          const yearKey   = `finance_paid_year_${year}`;
          const isYearOpen = localStorage.getItem(yearKey) !== '0';
          const months     = Object.keys(byYear[year]).map(Number).sort((a, b) => b - a);
          const yearTotal  = months.reduce((s, mo) => s + byYear[year][mo].length, 0);

          const yearDiv = document.createElement('div');
          yearDiv.style.cssText = 'margin-bottom:10px;';
          yearDiv.innerHTML = `
            <div class="paid-year-toggle" style="display:flex;justify-content:space-between;align-items:center;cursor:pointer;padding:7px 0;border-bottom:1px solid rgba(255,255,255,0.18);">
              <div style="font-weight:700;font-size:15px;">📅 ${year}</div>
              <div style="opacity:.6;font-size:13px;">${yearTotal}</div>
            </div>
            <div class="paid-year-details" style="display:${isYearOpen ? 'block' : 'none'};padding-left:6px;margin-top:4px;"></div>
          `;
          yearDiv.querySelector('.paid-year-toggle').addEventListener('click', function () {
            const det  = yearDiv.querySelector('.paid-year-details');
            const next = det.style.display === 'none';
            det.style.display = next ? 'block' : 'none';
            localStorage.setItem(yearKey, next ? '1' : '0');
          });

          const yearDetails = yearDiv.querySelector('.paid-year-details');

          months.forEach(month => {
            const monthKey     = `finance_paid_month_${year}_${month}`;
            const storedMonth  = localStorage.getItem(monthKey);
            const isMonthOpen  = storedMonth !== null
              ? storedMonth !== '0'
              : (year === nowYear && month === nowMonth);

            const monthName  = MONTH_NAMES_UK[month - 1] || `Місяць ${month}`;
            const monthItems = byYear[year][month];
            // Sort within month: newest day first
            monthItems.sort((a, b) => {
              if (!a.d && !b.d) return 0;
              if (!a.d) return 1;
              if (!b.d) return -1;
              return b.d.day - a.d.day;
            });

            const monthDiv = document.createElement('div');
            monthDiv.style.cssText = 'margin-bottom:6px;';
            monthDiv.innerHTML = `
              <div class="paid-month-toggle" style="display:flex;justify-content:space-between;align-items:center;cursor:pointer;padding:5px 0;border-bottom:1px solid rgba(255,255,255,0.08);">
                <div style="font-weight:600;font-size:14px;">${monthName}</div>
                <div style="opacity:.6;font-size:12px;">${monthItems.length}</div>
              </div>
              <div class="paid-month-details" style="display:${isMonthOpen ? 'block' : 'none'};"></div>
            `;
            monthDiv.querySelector('.paid-month-toggle').addEventListener('click', function () {
              const det  = monthDiv.querySelector('.paid-month-details');
              const next = det.style.display === 'none';
              det.style.display = next ? 'block' : 'none';
              localStorage.setItem(monthKey, next ? '1' : '0');
            });

            const monthDetails = monthDiv.querySelector('.paid-month-details');
            monthItems.forEach(({ p }) => monthDetails.appendChild(buildProjectCard(p, { isPaidSection: true })));

            yearDetails.appendChild(monthDiv);
          });

          paidList.appendChild(yearDiv);
        });
      }

      paidCard.querySelector('.paid-projects-search')?.addEventListener('input', function () {
        localStorage.setItem(SEARCH_PAID_KEY, this.value || '');
        renderProjects(projects || []);
      });

      paidCard.querySelector('.paid-projects-toggle')?.addEventListener('click', function () {
        const details = paidCard.querySelector('.paid-projects-details');
        const nextOpen = details.style.display === 'none';
        details.style.display = nextOpen ? 'block' : 'none';
        localStorage.setItem(OPEN_PAID_KEY, nextOpen ? '1' : '0');
      });

      container.appendChild(paidCard);
    }

    // ── Підрахунок сум по валютах для заголовка акордеону ─────────────
    const activeTotals = {};
    allActiveProjects.forEach(p => {
      const cur = p.currency || 'USD';
      if (!activeTotals[cur]) activeTotals[cur] = { total: 0, paid: 0 };
      activeTotals[cur].total += toNum(p.total_amount);
      activeTotals[cur].paid  += toNum(p.paid_amount);
    });

    const fmtActiveMoney = (n) => new Intl.NumberFormat('uk-UA', { maximumFractionDigits: 0 }).format(Math.round(n));
    const currencySign = { UAH: '₴', USD: '$', EUR: '€' };

    const activeSummaryHtml = Object.entries(activeTotals)
      .sort(([a], [b]) => a.localeCompare(b))
      .map(([cur, v]) => {
        const remaining = Math.max(0, v.total - v.paid);
        const sign = currencySign[cur] ?? cur;
        return `<div style="font-size:11px; opacity:.72; line-height:1.9;">
          <span style="opacity:.6;">${cur}</span><br>
          &nbsp;Бюджет <b>${fmtActiveMoney(v.total)} ${sign}</b><br>
          &nbsp;Аванс <b style="color:#4ade80;">${fmtActiveMoney(v.paid)} ${sign}</b><br>
          &nbsp;Залишок <b style="color:#fb923c;">${fmtActiveMoney(remaining)} ${sign}</b>
        </div>`;
      }).join('');

    const activeCard = document.createElement('div');
    activeCard.className = 'card';
    activeCard.style.marginTop = '15px';
    activeCard.innerHTML = `
      <div class="active-projects-toggle" style="cursor:pointer;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
          <div style="font-weight:700;">🟡 Проавансовані</div>
          <div style="opacity:.75;">${activeProjects.length}${activeQuery ? ` / ${allActiveProjects.length}` : ''}</div>
        </div>
        ${activeSummaryHtml ? `<div style="margin-top:6px;">${activeSummaryHtml}</div>` : ''}
      </div>
      <div class="active-projects-details" style="display:${isActiveOpen || !!activeQuery ? 'block' : 'none'}; margin-top:12px; border-top:1px solid #ffffff; padding-top:10px;">
        <input
          class="btn active-projects-search"
          type="text"
          value="${activeQuery.replace(/"/g, '&quot;')}"
          placeholder="Пошук: імʼя, сума, валюта, менеджер..."
          style="width:100%; margin-bottom:10px;"
        >
        <div class="active-projects-list"></div>
      </div>
    `;

    const activeDetails = activeCard.querySelector('.active-projects-list');
    if (activeProjects.length > 0) {
      activeProjects.forEach(p => activeDetails.appendChild(buildProjectCard(p)));
    } else {
      activeDetails.innerHTML = `<div style="opacity:.75; text-align:center;">${activeQuery ? 'Нічого не знайдено' : 'Немає активних проектів'}</div>`;
    }

    activeCard.querySelector('.active-projects-search')?.addEventListener('input', function () {
      localStorage.setItem(SEARCH_ACTIVE_KEY, this.value || '');
      renderProjects(projects || []);
    });

    activeCard.querySelector('.active-projects-toggle')?.addEventListener('click', function () {
      const details = activeCard.querySelector('.active-projects-details');
      const nextOpen = details.style.display === 'none';
      details.style.display = nextOpen ? 'block' : 'none';
      localStorage.setItem(OPEN_ACTIVE_KEY, nextOpen ? '1' : '0');
    });

    container.appendChild(activeCard);

    // Відновлюємо фокус після ренедеру (поллінг або пошук)
    if (focusedClass) {
      const el = container.querySelector('.' + focusedClass);
      if (el) {
        el.focus();
        if (focusSel) el.setSelectionRange(focusSel[0], focusSel[1]);
      }
    }
  }

  function loadProjects(opts = {}) {
    const { silent = false } = opts;
    if (isProjectsLoading) {
      pendingRefresh = true;
      return;
    }
    isProjectsLoading = true;
    pendingRefresh = false;

    return fetch('/api/sales-projects?layer=finance')
      .then(r => r.json())
      .then(projects => {
        // Do not rebuild DOM during background polls if the user has a card open —
        // it would visually close the card. Refresh will happen when the card closes.
        if (silent && getOpenProject()) {
          return; // не встановлюємо pendingRefresh — інакше .finally одразу запускає ще один рендер
        }
        renderProjects(projects);
      })
      .catch(err => {
        if (!silent) console.warn('Projects refresh error:', err);
      })
      .finally(() => {
        isProjectsLoading = false;
        if (pendingRefresh) {
          pendingRefresh = false;
          loadProjects({ silent: false });
        }
      });
  }

  window.refreshSalesProjects = () => loadProjects({ silent: false });

  // ── Global search + manager filter ───────────────────────────────────────
  const globalSearchInput = document.getElementById('globalSearchInput');
  if (globalSearchInput) {
    globalSearchInput.value = localStorage.getItem(GLOBAL_SEARCH_KEY) || '';
    globalSearchInput.addEventListener('input', function () {
      localStorage.setItem(GLOBAL_SEARCH_KEY, this.value);
      if (cachedProjects) renderProjects(cachedProjects);
    });
  }
  const managerFilterSelect = document.getElementById('managerFilterSelect');
  if (managerFilterSelect) {
    managerFilterSelect.value = localStorage.getItem(MANAGER_FILTER_KEY) || '';
    managerFilterSelect.addEventListener('change', function () {
      localStorage.setItem(MANAGER_FILTER_KEY, this.value);
      if (cachedProjects) renderProjects(cachedProjects);
    });
  }

  loadProjects();
  projectsPollTimer = window.setInterval(() => loadProjects({ silent: true }), POLL_MS);
  window.addEventListener('beforeunload', () => {
    if (projectsPollTimer) window.clearInterval(projectsPollTimer);
  });

});

// ===== Toggle кнопки редагування при кліку на аванс =====
// document.addEventListener('click', function(e){
//
//   const advanceCard = e.target.closest('.advance-card');
//   if(!advanceCard) return;
//
//   const editBtn = advanceCard.querySelector('.edit-advance-btn');
//   if(!editBtn) return;
//
//   const isVisible = editBtn.style.display === 'block';
//
//   document.querySelectorAll('.edit-advance-btn').forEach(b => {
//     b.style.display = 'none';
//   });
//
//   if(!isVisible){
//     editBtn.style.display = 'block';
//   }
//
// });

// ===== Модалка проекту =====
const modal = document.getElementById('projectModal');
let CURRENT_PROJECT_TYPE = 'project';

document.getElementById('createProjectBtn').onclick = () => {
  const projectTypeButtons = document.querySelectorAll('#projectTypeSegmented [data-project-type]');
  CURRENT_PROJECT_TYPE = 'project';
  projectTypeButtons.forEach(btn => {
    btn.classList.toggle('active', btn.dataset.projectType === 'project');
  });
  modal.style.display = 'flex';
};

document.getElementById('closeModalBtn').onclick = () => {
  modal.style.display = 'none';
};

document.querySelectorAll('#projectTypeSegmented [data-project-type]').forEach(btn => {
  btn.addEventListener('click', function () {
    CURRENT_PROJECT_TYPE = this.dataset.projectType === 'retail' ? 'retail' : 'project';
    document.querySelectorAll('#projectTypeSegmented [data-project-type]').forEach(el => {
      el.classList.toggle('active', el === this);
    });
  });
});

document.getElementById('saveProjectBtn').onclick = () => {

  const client_name = document.getElementById('clientName').value;
  const total_amount = document.getElementById('totalAmount').value;
  const currency = document.getElementById('projectCurrency').value;
  const is_retail = CURRENT_PROJECT_TYPE === 'retail';

  fetch('/api/sales-projects', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    },
    body: JSON.stringify({ client_name, total_amount, currency, is_retail })
  })
  .then(r => r.json())
  .then(res => {
    if(res.ok){
      modal.style.display = 'none';
      window.refreshSalesProjects?.();
    } else {
      alert(res.error || 'Помилка');
    }
  });

};



// ===== Модалка авансу =====
const advanceModal = document.getElementById('advanceModal');
const exchangeInput = document.getElementById('exchangeRate');

let currentProjectId = null;

document.addEventListener('click', function (e) {
  const btn = e.target.closest('.create-advance-btn'); // ✅ ВАЖЛИВО: closest()
  if (!btn) return;

  currentProjectId = btn.dataset.id;

  const pCur = String(btn.dataset.currency || 'USD').toUpperCase();

  // reset полів
  document.getElementById('advanceAmount').value = '';
  document.getElementById('exchangeRate').value = '';

  // ✅ валюта авансу по дефолту = валюті проекту
  const advCurEl = document.getElementById('advanceCurrency');
  advCurEl.value = pCur;

  // ✅ встановлюємо валюту проекту ТИХО (без алертів)
  window.setAdvanceProjectCurrency?.(pCur, { silent: true });

  advanceModal.style.display = 'flex';
});

document.getElementById('closeAdvanceBtn').onclick = () => {
  advanceModal.style.display = 'none';
};

document.addEventListener('change', function (e) {
  const target = e.target instanceof Element ? e.target : null;
  const select = target ? target.closest('.lead-manager-select') : null;
  if (!select) return;

  const projectId = select.dataset.projectId;
  const leadManagerUserId = select.value ? Number(select.value) : null;

  fetch(`/api/sales-projects/${projectId}/lead-manager`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    },
    body: JSON.stringify({
      lead_manager_user_id: leadManagerUserId
    })
  })
  .then(async (r) => {
    const payload = await r.json().catch(() => ({}));
    if (!r.ok) {
      throw new Error(payload.error || 'Не вдалося зберегти ведучого менеджера');
    }
    return payload;
  })
  .then(() => {
    localStorage.setItem('finance_open_project_id', String(projectId));
    window.refreshSalesProjects?.();
  })
  .catch((err) => {
    alert(err.message || 'Помилка');
    window.refreshSalesProjects?.();
  });
});


// ===== Прямий аванс в гаманець власника аванс =====
document.getElementById('saveAdvanceBtn').onclick = function(){
  if (!window.validateAdvanceFx()) return;


  const amount = document.getElementById('advanceAmount').value;
  const currency = document.getElementById('advanceCurrency').value;
  const exchange_rate = window.getAdvanceFxValue?.();

  fetch(`/api/sales-projects/${currentProjectId}/advance`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    },
    body: JSON.stringify({ amount, currency, exchange_rate })
  })
  .then(r => r.json())
  .then(res => {
    if(res.ok){
      advanceModal.style.display = 'none';
      localStorage.setItem('finance_open_project_id', String(currentProjectId)); // ✅ ключове
      window.refreshSalesProjects?.();
    } else {
      alert(res.error || 'Помилка');
    }
  });

};

// ===== Прийняти аванс =====
document.addEventListener('click', function(e){

  if(e.target.classList.contains('accept-advance-btn')){

    const transferId = e.target.dataset.id;

    fetch(`/api/cash-transfers/${transferId}/accept`, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
      }
    })
    .then(r => r.json())
    .then(res => {
      if(res.success){
        window.refreshSalesProjects?.();
      } else {
        alert(res.error || 'Помилка');
      }
    });

  }

});
// ===== Редагувати аванс =====
document.addEventListener('click', function(e){

  if(!e.target.classList.contains('edit-advance-btn')) return;

  const transferId = e.target.dataset.id;
  const currentAmount = e.target.dataset.amount;

  const newAmount = prompt('Нова сума авансу:', currentAmount);

  if(!newAmount) return;

  fetch(`/api/cash-transfers/${transferId}`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    },
    body: JSON.stringify({
      amount: newAmount
    })
  })
  .then(r => r.json())
  .then(res => {
    if(res.success){
      window.refreshSalesProjects?.();
    } else {
      alert(res.error || 'Помилка');
    }
  });

});

// ===== Бухгалтер: forward до власника =====
document.addEventListener('click', function(e){
  if(!e.target.classList.contains('forward-owner-btn')) return;

  const projectId = e.target.dataset.project;
  const owner = e.target.dataset.owner;

  fetch(`/api/sales-projects/${projectId}/forward-to-owner`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    },
    body: JSON.stringify({ target_owner: owner })
  })
  .then(r => r.json())
  .then(res => {
    if(res.ok){
      localStorage.setItem('finance_open_project_id', String(projectId));
      window.refreshSalesProjects?.();
    } else {
      alert(res.error || 'Помилка');
    }
  });
});

// ===== НТО: вибір власника =====
document.addEventListener('click', function(e){
  if(!e.target.classList.contains('send-owner-btn')) return;

  const projectId = e.target.dataset.project;
  const owner = e.target.dataset.owner;

  fetch(`/api/sales-projects/${projectId}/target-owner`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    },
    body: JSON.stringify({ target_owner: owner })
  })
  .then(r => r.json())
  .then(res => {
    if(res.ok){
      localStorage.setItem('finance_open_project_id', String(projectId));
      window.refreshSalesProjects?.();
    } else {
      alert(res.error || 'Помилка');
    }
  });
});

// ===== НТО: відмінити переказ =====
document.addEventListener('click', function(e){
  if(!e.target.classList.contains('cancel-owner-btn')) return;

  const projectId = e.target.dataset.project;

  fetch(`/api/sales-projects/${projectId}/target-owner-cancel`, {
    method: 'POST',
    headers: {
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    }
  })
  .then(r => r.json())
  .then(res => {
    if(res.ok){
      localStorage.setItem('finance_open_project_id', String(projectId));
      window.refreshSalesProjects?.();
    } else {
      alert(res.error || 'Помилка');
    }
  });
});


// ===== Виправлення валюти авансу =====
(function () {
  const modal    = document.getElementById('correctCurrencyModal');
  const oldInfo  = document.getElementById('correctCurrencyOldInfo');
  const amountEl = document.getElementById('correctAmount');
  const curEl    = document.getElementById('correctCurrency');
  const saveBtn  = document.getElementById('saveCorrectBtn');
  const closeBtn = document.getElementById('closeCorrectBtn');
  let currentTransferId = null;

  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.correct-currency-btn');
    if (!btn) return;

    currentTransferId = btn.dataset.id;
    const oldAmount   = btn.dataset.amount;
    const oldCurrency = btn.dataset.currency;
    const status      = btn.dataset.status;

    oldInfo.textContent = `Поточно: ${Number(oldAmount).toLocaleString('uk-UA')} ${oldCurrency} (${status === 'accepted' ? 'Прийнято' : 'В очікуванні'})`;
    amountEl.value = oldAmount;

    // Pre-select the other common currency
    const options = ['USD', 'UAH', 'EUR'];
    curEl.value = options.find(c => c !== oldCurrency) || oldCurrency;

    modal.style.display = 'flex';
  });

  closeBtn.addEventListener('click', () => { modal.style.display = 'none'; currentTransferId = null; });
  modal.addEventListener('click', e => { if (e.target === modal) { modal.style.display = 'none'; currentTransferId = null; } });

  saveBtn.addEventListener('click', function () {
    if (!currentTransferId) return;

    const newAmount   = parseFloat(amountEl.value);
    const newCurrency = curEl.value;

    if (!newAmount || newAmount <= 0) {
      alert('Введіть правильну суму');
      return;
    }

    saveBtn.disabled = true;
    saveBtn.textContent = 'Збереження…';

    fetch(`/api/cash-transfers/${currentTransferId}/correct-currency`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
      },
      body: JSON.stringify({ new_currency: newCurrency, new_amount: newAmount })
    })
    .then(r => r.json())
    .then(res => {
      if (res.ok) {
        modal.style.display = 'none';
        currentTransferId = null;
        window.refreshSalesProjects?.();
      } else {
        alert(res.error || 'Помилка виправлення');
      }
    })
    .catch(() => alert('Помилка мережі'))
    .finally(() => {
      saveBtn.disabled = false;
      saveBtn.textContent = 'Зберегти виправлення';
    });
  });
})();

// ===== Скасувати помилковий аванс =====
document.addEventListener('click', function(e){
  const btn = e.target.closest('.cancel-advance-btn');
  if(!btn) return;

  const id       = btn.dataset.id;
  const amount   = btn.dataset.amount;
  const currency = btn.dataset.currency;

  if(!confirm(`Скасувати аванс ${Number(amount).toLocaleString('uk-UA')} ${currency}?\nБаланс буде автоматично скоригований.`)) return;

  btn.disabled = true;

  fetch(`/api/cash-transfers/${id}/cancel-advance`, {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
  })
  .then(r => r.json())
  .then(res => {
    if(res.ok){
      window.refreshSalesProjects?.();
    } else {
      alert(res.error || 'Помилка скасування');
      btn.disabled = false;
    }
  })
  .catch(() => { alert('Помилка мережі'); btn.disabled = false; });
});

document.addEventListener('click', function(e){
  if(!e.target.classList.contains('delete-project-btn')) return;

  const id   = e.target.dataset.id;
  const name = e.target.dataset.name;

  if(!confirm(`Видалити проект "${name}"?\nЦю дію неможливо скасувати.`)) return;

  fetch(`/api/sales-projects/${id}`, {
    method: 'DELETE',
    headers: {
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    }
  })
  .then(r => r.json())
  .then(res => {
    if(res.ok){
      window.refreshSalesProjects?.();
    } else {
      alert(res.error || 'Помилка видалення');
    }
  })
  .catch(() => alert('Помилка мережі'));
});

(() => {
  ///////////////////////////////////////////////////////////////
  // helpers
  ///////////////////////////////////////////////////////////////

  const PALETTE = ['#66f2a8', '#4c7dff', '#ffb86c', '#ff6b6b', '#9aa6bc'];

  function fmt0(n) {
    const num = Number(String(n ?? 0).replace(',', '.')) || 0;
    return new Intl.NumberFormat('uk-UA', { maximumFractionDigits: 0 })
      .format(Math.round(num))
      .replace(/\u00A0/g, ' ');
  }

  function ensureChartJs() {
    return new Promise((resolve, reject) => {
      if (window.Chart) return resolve();

      const s = document.createElement('script');
      s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
      s.onload = () => resolve();
      s.onerror = () => reject(new Error('Не вдалося завантажити Chart.js'));
      document.head.appendChild(s);
    });
  }

  function getCanvasCtx(id) {
    const c = document.getElementById(id);
    return c ? c.getContext('2d') : null;
  }

  ///////////////////////////////////////////////////////////////
  // UI
  ///////////////////////////////////////////////////////////////

  function makeSalesCard() {
    if (document.getElementById('salesChartsCard')) return;

    const main = document.querySelector('main');
    if (!main) return;

    const card = document.createElement('div');
    card.className = 'card';
    card.id = 'salesChartsCard';
    card.style.marginBottom = '15px';
    card.style.listStyle = 'none';
    card.style.cursor = 'pointer';
    card.style.padding = '18px 18px 16px';
    card.style.borderRadius = '22px';
    card.style.background = 'radial-gradient(120% 180% at 50% 0%, rgba(102, 242, 168, .22) 0%, rgba(255, 255, 255, .08) 35%, rgba(255, 255, 255, .05) 70%, rgba(255, 255, 255, .04) 100%)';
    card.style.border = '1px solid rgba(255, 255, 255, .10)';
    card.style.boxShadow = '0 18px 48px rgba(0, 0, 0, .42), inset 0 1px 0 rgba(255, 255, 255, .12)';
    card.style.backdropFilter = 'blur(18px)';
    // ✅ прибрати маркер summary (трикутник)
    const st = document.createElement('style');
    st.textContent = `
      #salesChartsDetails > summary { list-style: none; }
      #salesChartsDetails > summary::-webkit-details-marker { display: none; }
      #salesChartsDetails > summary::marker { content: ""; }
    `;
    document.head.appendChild(st);

    card.innerHTML = `
      <details id="salesChartsDetails">
        <summary style="cursor:pointer;">
          <div style="display:flex; align-items:center; justify-content:space-between; gap:10px;">
            <div style="font-weight:800;font-size: 22px;
              font-weight: 800;
              letter-spacing: .2px;
              opacity: .95;">SG Holding</div>
            <div style="display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 6px 12px;
                border-radius: 999px;
                font-weight: 800;
                font-size: 13px;
                color: #0b0d10;
                background: rgba(102, 242, 168, .95);
                box-shadow: 0 6px 16px rgba(0, 0, 0, .25);">
              USD
            </div>
          </div>

          <div style="margin-top:10px; font-size:28px; font-weight:900; letter-spacing:.3px;text-align:center;">
            <span id="salesTotalVal">0</span> $
          </div>

          <div style="margin-top:10px; display:flex; gap:10px;">
            <div class="btn" style="flex:1; text-align:center; padding:10px 8px; background:rgba(255, 255, 255, 0.1); border:1px solid rgba(255, 255, 255, 0.25);">
              <div style="opacity:.85; font-weight:700; font-size:12px;">✅ Авансовано</div>
              <div style="font-weight:900; margin-top:4px;"><span id="salesAdvVal">0</span> $</div>
            </div>

            <div class="btn" style="flex:1; text-align:center; padding:10px 8px; background:rgba(255, 255, 255, 0.1); border:1px solid rgba(255, 255, 255, 0.25);">
              <div style="opacity:.85; font-weight:700; font-size:12px;">🟡 Залишок</div>
              <div style="font-weight:900; margin-top:4px;"><span id="salesRemVal">0</span> $</div>
            </div>
          </div>

        </summary>

        <div style="margin-top:12px;">
          <div class="card" style="margin-top:10px;">
            <div style="font-weight:800; text-align:center; margin-bottom:10px;">Загальний бюджет / авансовано / залишок</div>

            <div style="height:220px;">
              <canvas id="pieSales"></canvas>
            </div>

            <div id="barsSales" style="margin-top:10px;"></div>
          </div>
        </div>
      </details>
    `;

      // ✅ статистика завжди найперша в main
      main.insertAdjacentElement('afterbegin', card);
  }

  ///////////////////////////////////////////////////////////////
  // Bars (wallet-like)
  ///////////////////////////////////////////////////////////////

  function renderBars(el, labels, values) {
    if (!el) return;

    const rows = labels.map((label, i) => ({
      label,
      value: Number(values[i] || 0),
    }));

    const total = rows.reduce((s, r) => s + r.value, 0) || 1;

    el.innerHTML = '';

    rows.forEach((r, idx) => {
      const pct = Math.round((r.value / total) * 100);
      const color = PALETTE[idx % PALETTE.length];

      el.insertAdjacentHTML('beforeend', `
        <div style="display:flex; align-items:center; gap:10px; padding:10px 4px; border-bottom:1px solid rgba(255,255,255,.06);">
          <div style="flex:1; min-width:0;">
            <div style="font-weight:800; font-size:14px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
              ${r.label}
            </div>
            <div style="margin-top:6px; height:10px; border-radius:999px; background:rgba(255,255,255,.08); overflow:hidden;">
              <div style="height:100%; width:${pct}%; background:${color};"></div>
            </div>
          </div>

          <div style="text-align:right; min-width:88px;">
            <div style="font-weight:900;">${pct}%</div>
            <div style="opacity:.7; font-size:12px;">${fmt0(r.value)} $</div>
          </div>
        </div>
      `);
    });

    const last = el.lastElementChild;
    if (last) last.style.borderBottom = 'none';
  }

  ///////////////////////////////////////////////////////////////
  // Chart
  ///////////////////////////////////////////////////////////////

  let chartSales = null;

  function upsertPie(labels, data) {
    const ctx = getCanvasCtx('pieSales');
    if (!ctx) return;

    const colors = labels.map((_, i) => PALETTE[i % PALETTE.length]);

    if (chartSales) {
      chartSales.data.labels = labels;
      chartSales.data.datasets[0].data = data;
      chartSales.data.datasets[0].backgroundColor = colors;
      chartSales.update();
      return;
    }

    chartSales = new Chart(ctx, {
      type: 'pie',
      data: {
        labels,
        datasets: [{
          data,
          backgroundColor: colors,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: true,
            position: 'bottom',
            labels: {
              color: '#e9eef6',
              boxWidth: 10,
              boxHeight: 10,
              padding: 12,
              font: { size: 12, weight: '600' }
            }
          },
          tooltip: {
            callbacks: {
              label: (item) => `${item.label}: ${fmt0(Number(item.raw || 0))} $`
            }
          }
        }
      }
    });
  }

  ///////////////////////////////////////////////////////////////
  // Data
  ///////////////////////////////////////////////////////////////

  async function loadAndRenderSales() {
    const res = await fetch('/api/sales-projects?layer=finance', { credentials: 'same-origin' });
    if (!res.ok) throw new Error(`GET /api/sales-projects failed (${res.status})`);
    const projects = await res.json();

    // ✅ беремо тільки USD (як у твоєму борговому блоці USD-пілл)
    const usd = Array.isArray(projects) ? projects.filter(p => p.currency === 'USD') : [];

    const totalBudget = usd.reduce((s, p) => s + Number(p.total_amount || 0), 0);

    // "Авансовано" = прийнято + очікує (бо це вже внесені суми по проекту, просто частина ще в НТО)
    const advanced = usd.reduce((s, p) => s + Number(p.paid_amount || 0) + Number(p.pending_amount || 0), 0);

    const remaining = Math.max(0, totalBudget - advanced);

    const totalEl = document.getElementById('salesTotalVal');
    const advEl   = document.getElementById('salesAdvVal');
    const remEl   = document.getElementById('salesRemVal');

    if (totalEl) totalEl.innerText = fmt0(totalBudget);
    if (advEl)   advEl.innerText   = fmt0(advanced);
    if (remEl)   remEl.innerText   = fmt0(remaining);

    const labels = ['✅ Авансовано', '🟡 Залишок'];
    const data   = [advanced, remaining];

    upsertPie(labels, data);
    renderBars(document.getElementById('barsSales'), labels, data);
  }

  async function boot() {
    makeSalesCard();
    await ensureChartJs();
    await loadAndRenderSales();
    setInterval(() => loadAndRenderSales().catch(() => {}), 15000);
  }

  document.addEventListener('DOMContentLoaded', () => {
    boot().catch((e) => console.warn('Sales charts:', e.message));
  });
})();



// ===============================
// ADVANCE FX UI GUARD
// - валюта проекту береться з window.ADV_PROJECT_CURRENCY
// - алерти ТІЛЬКИ коли юзер руками змінив валюту авансу
// ===============================
(function () {
  const advanceCurrencyEl = document.getElementById('advanceCurrency');
  const exchangeRateEl    = document.getElementById('exchangeRate');
  const exchangeRateHintEl = document.getElementById('exchangeRateHint');
  const saveAdvanceBtnEl = document.getElementById('saveAdvanceBtn');

  if (!advanceCurrencyEl || !exchangeRateEl) return;

  // глобальний стан
  window.ADV_PROJECT_CURRENCY = String(window.ADV_PROJECT_CURRENCY || 'USD').toUpperCase();
  window.ADV_FX_MAP = window.ADV_FX_MAP || null;
  window.ADV_FX_LAST_OK_MAP = window.ADV_FX_LAST_OK_MAP || null;
  window.ADV_AUTO_RATE = null;
  window.ADV_AUTO_RATE_LABEL = '';
  window.ADV_FX_SYNCING = false;
  window.ADV_FX_SYNC_SEQ = 0;

  let userChanged = false;  // true тільки після ручної зміни select
  let alertKey = null;      // антиспам

  function showRateHint(text) {
    if (!exchangeRateHintEl) return;
    exchangeRateHintEl.style.display = text ? 'block' : 'none';
    exchangeRateHintEl.textContent = text || '';
  }

  function setAdvanceSaveDisabled(isDisabled) {
    if (!saveAdvanceBtnEl) return;
    saveAdvanceBtnEl.disabled = !!isDisabled;
    saveAdvanceBtnEl.textContent = isDisabled ? 'Оновлення курсу...' : 'Зберегти';
  }

  function showRateField(placeholderText) {
    exchangeRateEl.style.display = '';
    exchangeRateEl.required = true;
    exchangeRateEl.placeholder = placeholderText || 'Курс';
    window.ADV_AUTO_RATE = null;
    window.ADV_AUTO_RATE_LABEL = '';
  }

  function hideRateField() {
    exchangeRateEl.style.display = 'none';
    exchangeRateEl.required = false;
    exchangeRateEl.value = '';
    exchangeRateEl.placeholder = '';
  }

  function alertIfUser(text, key) {
    if (!userChanged) return;          // ✅ тільки після ручної зміни
    if (key && alertKey === key) return;
    alertKey = key || null;
    alert(text);
  }

  async function loadFinanceFxRates(force = false) {
    if (!force && window.ADV_FX_MAP) return window.ADV_FX_MAP;

    const attempts = force ? 2 : 2;

    for (let attempt = 0; attempt < attempts; attempt += 1) {
      try {
        const res = await fetch('/api/fx/rates', {
          headers: { 'Accept': 'application/json' },
          cache: 'no-store',
        });
        const data = await res.json();
        if (!res.ok || data.error) throw new Error(data.error || 'FX unavailable');

        const map = {};
        (data.rates || []).forEach(rate => {
          map[String(rate.currency || '').toUpperCase()] = {
            buy: Number(rate.purchase || 0),
            sell: Number(rate.sale || 0),
          };
        });

        window.ADV_FX_MAP = map;
        window.ADV_FX_LAST_OK_MAP = map;
        return map;
      } catch (_) {
        window.ADV_FX_MAP = null;
        if (attempt < attempts - 1) {
          await new Promise(resolve => window.setTimeout(resolve, 150));
          continue;
        }
      }
    }

    if (window.ADV_FX_LAST_OK_MAP) {
      window.ADV_FX_MAP = window.ADV_FX_LAST_OK_MAP;
      return window.ADV_FX_LAST_OK_MAP;
    }

    return null;
  }

  function resolveAutoAdvanceRate(projectCurrency, advanceCurrency, fxMap) {
    if (advanceCurrency === projectCurrency) {
      return { value: 1, label: 'Курс не потрібен: однакова валюта' };
    }

    const USD = fxMap?.USD;
    const EUR = fxMap?.EUR;
    const safe = (n) => (Number.isFinite(n) && n > 0 ? Number(n) : null);

    if (projectCurrency === 'UAH' && advanceCurrency === 'USD') {
      const rate = safe(USD?.buy);
      return rate ? { value: rate, label: `Автокурс USD→UAH: ${rate.toFixed(4)}` } : null;
    }

    if (projectCurrency === 'UAH' && advanceCurrency === 'EUR') {
      const rate = safe(EUR?.buy);
      return rate ? { value: rate, label: `Автокурс EUR→UAH: ${rate.toFixed(4)}` } : null;
    }

    if (projectCurrency === 'USD' && advanceCurrency === 'UAH') {
      const rate = safe(USD?.sell);
      return rate ? { value: rate, label: `Автокурс USD→UAH: ${rate.toFixed(4)}` } : null;
    }

    if (projectCurrency === 'EUR' && advanceCurrency === 'UAH') {
      const rate = safe(EUR?.sell);
      return rate ? { value: rate, label: `Автокурс EUR→UAH: ${rate.toFixed(4)}` } : null;
    }

    if (projectCurrency === 'USD' && advanceCurrency === 'EUR') {
      const eurBuy = safe(EUR?.buy);
      const usdSell = safe(USD?.sell);
      const cross = eurBuy && usdSell ? eurBuy / usdSell : null;
      return cross ? { value: cross, label: `Автокурс EUR→USD: ${cross.toFixed(4)}` } : null;
    }

    if (projectCurrency === 'EUR' && advanceCurrency === 'USD') {
      const usdBuy = safe(USD?.buy);
      const eurSell = safe(EUR?.sell);
      const cross = usdBuy && eurSell ? usdBuy / eurSell : null;
      return cross ? { value: cross, label: `Автокурс USD→EUR: ${cross.toFixed(4)}` } : null;
    }

    return null;
  }

  async function syncAdvanceFxUI() {
    const syncSeq = ++window.ADV_FX_SYNC_SEQ;
    const pCur = String(window.ADV_PROJECT_CURRENCY || 'USD').toUpperCase();
    const aCur = String(advanceCurrencyEl.value || 'USD').toUpperCase();
    window.ADV_FX_SYNCING = true;
    window.ADV_AUTO_RATE = null;
    window.ADV_AUTO_RATE_LABEL = '';
    setAdvanceSaveDisabled(true);

    const isStale = () => syncSeq !== window.ADV_FX_SYNC_SEQ;

    try {
      // якщо валюта авансу = валюта проєкту -> курс не потрібен
      if (aCur === pCur) {
        if (isStale()) return;
        hideRateField();
        window.ADV_AUTO_RATE = 1;
        window.ADV_AUTO_RATE_LABEL = 'Курс не потрібен: однакова валюта';
        showRateHint(window.ADV_AUTO_RATE_LABEL);
        return;
      }

      const fxMap = await loadFinanceFxRates();
      if (isStale()) return;
      const autoRate = resolveAutoAdvanceRate(pCur, aCur, fxMap);
      if (autoRate) {
        hideRateField();
        window.ADV_AUTO_RATE = autoRate.value;
        window.ADV_AUTO_RATE_LABEL = autoRate.label;
        showRateHint(autoRate.label);
        return;
      }

      showRateHint('Автокурс недоступний. Введіть курс вручну.');

      // різні валюти -> показуємо поле з підказкою по напрямку
      if (aCur === 'EUR' && pCur === 'USD') {
        alertIfUser(
          "⚠️ АВАНС У EUR, ПРОЄКТ У USD.\n" +
          "Потрібен крос-курс EUR→USD.\n" +
          "Приклад: 1 EUR → 1.12 USD.",
          'eur_usd'
        );
        showRateField('Крос курс EUR→USD (1 -> 1.12)');
        return;
      }

      if (aCur === 'UAH' && pCur === 'USD') {
        showRateField('Курс USD→UAH (1 -> 43.50)');
        return;
      }

      if (aCur === 'USD' && pCur === 'UAH') {
        showRateField('Курс USD→UAH (1 -> 43.50)');
        return;
      }

      if (aCur === 'EUR' && pCur === 'UAH') {
        showRateField('Курс EUR→UAH (1 -> 45.00)');
        return;
      }

      if (aCur === 'USD' && pCur === 'EUR') {
        showRateField('Крос курс USD→EUR (1 -> 0.89)');
        return;
      }

      if (aCur === 'UAH' && pCur === 'EUR') {
        showRateField('Курс EUR→UAH (1 -> 45.00)');
        return;
      }

      showRateField(`Курс ${aCur}→${pCur}`);
    } finally {
      if (!isStale()) {
        window.ADV_FX_SYNCING = false;
        setAdvanceSaveDisabled(false);
      }
    }
  }

  // доступ зовні
  window.syncAdvanceFxUI = syncAdvanceFxUI;

  // ✅ ця функція має бути "тиха" при відкритті модалки
  window.setAdvanceProjectCurrency = function (cur, opts = {}) {
    window.ADV_PROJECT_CURRENCY = String(cur || 'USD').toUpperCase();

    if (opts.silent) {
      userChanged = false;
      alertKey = null;
    }

    syncAdvanceFxUI().catch(() => {
      showRateHint('Автокурс недоступний. Введіть курс вручну.');
      showRateField('Введіть курс вручну');
    });
  };

  // init тихо
  userChanged = false;
  alertKey = null;
  syncAdvanceFxUI().catch(() => {
    showRateHint('Автокурс недоступний. Введіть курс вручну.');
    showRateField('Введіть курс вручну');
  });

  // ✅ алерти тільки при ручній зміні валюти авансу
  advanceCurrencyEl.addEventListener('change', () => {
    userChanged = true;
    alertKey = null;
    syncAdvanceFxUI().catch(() => {
      showRateHint('Автокурс недоступний. Введіть курс вручну.');
      showRateField('Введіть курс вручну');
    });
  });

  window.getAdvanceFxValue = function () {
    const pCur = String(window.ADV_PROJECT_CURRENCY || 'USD').toUpperCase();
    const aCur = String(advanceCurrencyEl.value || 'USD').toUpperCase();

    if (aCur === pCur) return 1;
    if (window.ADV_AUTO_RATE && Number(window.ADV_AUTO_RATE) > 0) {
      return Number(window.ADV_AUTO_RATE);
    }

    return Number(String(exchangeRateEl.value || '').replace(',', '.')) || '';
  };

  // ✅ валідація перед збереженням (без алертів тут, тільки блок)
  window.validateAdvanceFx = function () {
    const pCur = String(window.ADV_PROJECT_CURRENCY || 'USD').toUpperCase();
    const aCur = String(advanceCurrencyEl.value || 'USD').toUpperCase();

    if (window.ADV_FX_SYNCING) {
      alert('Зачекай, курс ще оновлюється');
      return false;
    }

    if (aCur === pCur) return true;
    if (window.ADV_AUTO_RATE && Number(window.ADV_AUTO_RATE) > 0) return true;

    const rate = Number(String(exchangeRateEl.value || '').replace(',', '.'));
    if (!rate || rate <= 0) {
      alert('Введи курс вручну');
      exchangeRateEl.focus();
      return false;
    }

    // для проєкту в UAH очікуємо звичайний курс (USD→UAH / EUR→UAH), тобто > 1
    if (pCur === 'UAH' && (aCur === 'USD' || aCur === 'EUR') && rate < 1) {
      alert('❌ Для проєкту в UAH введи звичайний курс, наприклад 43.50 або 45.00.');
      exchangeRateEl.focus();
      return false;
    }

    // швидкий захист від "53" там де має бути ~1.xx
    if (pCur === 'USD' && aCur === 'EUR' && rate >= 10) {
      alert('❌ Для EUR→USD має бути ~1.xx (типу 1.12), не 40/53.');
      exchangeRateEl.focus();
      return false;
    }

    return true;
  };
})();
</script>




@include('partials.nav.bottom')

</body>
@endsection
