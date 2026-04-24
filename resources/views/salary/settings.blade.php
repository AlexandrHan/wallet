@extends('layouts.app')

@push('styles')
  <link rel="stylesheet" href="/css/nav-telegram.css?v={{ filemtime(public_path('css/nav-telegram.css')) }}">
  <link rel="stylesheet" href="/css/project.css?v={{ filemtime(public_path('css/project.css')) }}">
@endpush

@section('content')
<main class="">
  <div class="card" style="margin-bottom:15px;">
    <a href="/salary" style="display:block; font-weight:800; font-size:18px; text-align:center; text-decoration:none; color:inherit;">
      ⚙️ Налаштування зарплатні
    </a>
  </div>

  <div id="salaryRulesSettings"></div>
</main>

<script>
document.addEventListener('DOMContentLoaded', async function () {
  const root = document.getElementById('salaryRulesSettings');
  if (!root) return;

  const esc = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

  const GROUP_LABELS = {
    electrician: 'Електрики',
    installation_team: 'Монтажні бригади',
    manager: 'Менеджери',
    accountant: 'Соловей',
    foreman: 'Оніпко',
  };

  function normalizeNumber(value) {
    const text = String(value ?? '').trim().replace(',', '.');
    return text === '' ? '' : text;
  }

  function getRuleMap(rules) {
    const map = new Map();
    (Array.isArray(rules) ? rules : []).forEach(rule => {
      const key = `${rule.staff_group}::${String(rule.staff_name || '').trim().toLowerCase()}`;
      map.set(key, rule);
    });
    return map;
  }

  function renderCard(group, name, rule) {
    const mode = rule?.mode || 'fixed';
    const currency = rule?.currency || 'UAH';
    const fixedAmount = rule?.fixed_amount ?? '';
    const commissionPercent = rule?.commission_percent ?? '';
    const pieceworkUnitRate = rule?.piecework_unit_rate ?? '';
    const foremanBonus = rule?.foreman_bonus ?? '';
    const gridLe50 = rule?.piecework_grid_le_50 ?? '';
    const gridGt50 = rule?.piecework_grid_gt_50 ?? '';
    const hybridLe50 = rule?.piecework_hybrid_le_50 ?? '';
    const hybridGt50 = rule?.piecework_hybrid_gt_50 ?? '';

    const isManager = group === 'manager';

    return `
      <div class="card" style="margin-bottom:12px;" data-salary-rule-card="${esc(group)}" data-staff-group="${esc(group)}" data-staff-name="${esc(name)}">
        <div style="display:flex; justify-content:space-between; gap:10px; align-items:center; margin-bottom:10px;">
          <div>
            <div style="font-weight:800; font-size:16px;">${esc(name)}</div>
            <div style="font-size:12px; opacity:.7; margin-top:4px;">${esc(GROUP_LABELS[group] || group)}</div>
          </div>
          <button type="button" class="btn" data-save-salary-rule>Зберегти</button>
        </div>

        ${isManager ? `
          <div class="project-field-label">Відсоток комісії (%)</div>
          <input class="btn" type="number" step="0.01" min="0" max="100"
            data-field="commission_percent" value="${esc(commissionPercent)}"
            style="width:100%; margin-bottom:10px;"
            placeholder="наприклад 1.5">
        ` : `
          <div class="project-field-label">Режим нарахування</div>
          <select class="btn" style="width:100%; margin-bottom:10px;" data-field="mode">
            <option value="fixed" ${mode === 'fixed' ? 'selected' : ''}>Ставка</option>
            <option value="piecework" ${mode === 'piecework' ? 'selected' : ''}>Виробіток</option>
          </select>

          <div class="project-field-label">Валюта</div>
          <select class="btn" style="width:100%; margin-bottom:10px;" data-field="currency">
            <option value="UAH" ${currency === 'UAH' ? 'selected' : ''}>UAH</option>
            <option value="USD" ${currency === 'USD' ? 'selected' : ''}>USD</option>
            <option value="EUR" ${currency === 'EUR' ? 'selected' : ''}>EUR</option>
          </select>

          <div data-fixed-block style="display:${mode === 'fixed' ? 'block' : 'none'};">
            <div class="project-field-label">Помісячна зарплата</div>
            <input class="btn" type="number" step="0.01" data-field="fixed_amount" value="${esc(fixedAmount)}" style="width:100%; margin-bottom:10px;">
          </div>

          <div data-piecework-electrician style="display:${mode === 'piecework' && group === 'electrician' ? 'block' : 'none'};">
            <div class="project-field-label">Мережева до 50 кВт</div>
            <input class="btn" type="number" step="0.01" data-field="piecework_grid_le_50" value="${esc(gridLe50)}" style="width:100%; margin-bottom:8px;">

            <div class="project-field-label">Мережева понад 50 кВт</div>
            <input class="btn" type="number" step="0.01" data-field="piecework_grid_gt_50" value="${esc(gridGt50)}" style="width:100%; margin-bottom:8px;">

            <div class="project-field-label">Гібрид до 50 кВт</div>
            <input class="btn" type="number" step="0.01" data-field="piecework_hybrid_le_50" value="${esc(hybridLe50)}" style="width:100%; margin-bottom:8px;">

            <div class="project-field-label">Гібрид понад 50 кВт</div>
            <input class="btn" type="number" step="0.01" data-field="piecework_hybrid_gt_50" value="${esc(hybridGt50)}" style="width:100%; margin-bottom:0;">
          </div>

          <div data-piecework-installation style="display:${mode === 'piecework' && group === 'installation_team' ? 'block' : 'none'};">
            <div class="project-field-label">Ставка за 1 кВт панелей</div>
            <input class="btn" type="number" step="0.01" data-field="piecework_unit_rate" value="${esc(pieceworkUnitRate)}" style="width:100%; margin-bottom:8px;">

            <div class="project-field-label">Бригадирські</div>
            <input class="btn" type="number" step="0.01" data-field="foreman_bonus" value="${esc(foremanBonus)}" style="width:100%; margin-bottom:0;">
          </div>
        `}
      </div>
    `;
  }

  function bindCardInteractions() {
    root.querySelectorAll('[data-salary-rule-card]').forEach(card => {
      const modeSelect = card.querySelector('[data-field="mode"]');
      if (!(modeSelect instanceof HTMLSelectElement)) return;

      const syncMode = () => {
        const fixedBlock = card.querySelector('[data-fixed-block]');
        const electricianBlock = card.querySelector('[data-piecework-electrician]');
        const installationBlock = card.querySelector('[data-piecework-installation]');
        const group = String(card.dataset.staffGroup || '');
        const isFixed = modeSelect.value === 'fixed';
        if (fixedBlock) fixedBlock.style.display = isFixed ? 'block' : 'none';
        if (electricianBlock) {
          electricianBlock.style.display = !isFixed && group === 'electrician' ? 'block' : 'none';
        }
        if (installationBlock) {
          installationBlock.style.display = !isFixed && group === 'installation_team' ? 'block' : 'none';
        }
      };

      modeSelect.addEventListener('change', syncMode);
      syncMode();
    });
  }

  function render(subjects, rules) {
    const ruleMap = getRuleMap(rules);
    const sections = Object.entries(subjects || {}).map(([group, names]) => {
      const items = (Array.isArray(names) ? names : [])
        .map(name => String(name || '').trim())
        .filter(Boolean);

      if (!items.length) {
        return `
          <div class="card" style="margin-bottom:12px;">
            <div style="font-weight:800; font-size:16px; margin-bottom:6px;">${esc(GROUP_LABELS[group] || group)}</div>
            <div style="font-size:14px; opacity:.75;">Поки немає працівників.</div>
          </div>
        `;
      }

      return `
        <div class="card" style="margin-bottom:12px;">
          <div style="font-weight:800; font-size:16px; margin-bottom:12px;">${esc(GROUP_LABELS[group] || group)}</div>
          ${items.map(name => {
            const key = `${group}::${name.toLowerCase()}`;
            return renderCard(group, name, ruleMap.get(key));
          }).join('')}
        </div>
      `;
    });

    root.innerHTML = sections.join('');
    bindCardInteractions();
  }

  async function loadData() {
    root.innerHTML = `
      <div class="card">
        <div style="font-size:14px; opacity:.8; text-align:center;">Завантаження правил...</div>
      </div>
    `;

    const res = await fetch('/api/salary-rules/settings-data');
    const payload = await res.json();
    if (!res.ok) {
      throw new Error(payload.error || 'Не вдалося завантажити правила');
    }

    render(payload.subjects || {}, payload.rules || []);
  }

  document.addEventListener('click', async function (e) {
    const target = e.target instanceof Element ? e.target : null;
    const saveBtn = target ? target.closest('[data-save-salary-rule]') : null;
    if (!(saveBtn instanceof HTMLButtonElement)) return;

    const card = saveBtn.closest('[data-salary-rule-card]');
    if (!(card instanceof HTMLElement)) return;

    const staffGroup = String(card.dataset.salaryRuleCard || '');
    const staffName = String(card.dataset.staffName || '');

    const mode = card.querySelector('[data-field="mode"]')?.value || 'fixed';
    const currency = card.querySelector('[data-field="currency"]')?.value || 'UAH';

    const payload = {
      staff_group: staffGroup,
      staff_name: staffName,
      mode,
      currency,
      commission_percent: normalizeNumber(card.querySelector('[data-field="commission_percent"]')?.value),
      fixed_amount: normalizeNumber(card.querySelector('[data-field="fixed_amount"]')?.value),
      piecework_unit_rate: normalizeNumber(card.querySelector('[data-field="piecework_unit_rate"]')?.value),
      foreman_bonus: normalizeNumber(card.querySelector('[data-field="foreman_bonus"]')?.value),
      piecework_grid_le_50: normalizeNumber(card.querySelector('[data-field="piecework_grid_le_50"]')?.value),
      piecework_grid_gt_50: normalizeNumber(card.querySelector('[data-field="piecework_grid_gt_50"]')?.value),
      piecework_hybrid_le_50: normalizeNumber(card.querySelector('[data-field="piecework_hybrid_le_50"]')?.value),
      piecework_hybrid_gt_50: normalizeNumber(card.querySelector('[data-field="piecework_hybrid_gt_50"]')?.value),
    };

    saveBtn.disabled = true;

    try {
      const res = await fetch('/api/salary-rules', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify(payload),
      });

      const result = await res.json();
      if (!res.ok || !result.ok) {
        throw new Error(result.error || 'Не вдалося зберегти правило');
      }

      await loadData();
    } catch (err) {
      alert(err.message || 'Не вдалося зберегти правило');
    } finally {
      saveBtn.disabled = false;
    }
  });

  loadData().catch((err) => {
    root.innerHTML = `
      <div class="card">
        <div style="font-size:14px; opacity:.8; text-align:center;">${esc(err.message || 'Помилка завантаження')}</div>
      </div>
    `;
  });
});
</script>

@include('partials.nav.bottom')
@endsection
