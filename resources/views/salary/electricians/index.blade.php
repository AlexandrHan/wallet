@extends('layouts.app')

@push('styles')
  <link rel="stylesheet" href="/css/nav-telegram.css?v={{ filemtime(public_path('css/nav-telegram.css')) }}">
  <link rel="stylesheet" href="/css/project.css?v={{ filemtime(public_path('css/project.css')) }}">
@endpush

@section('content')
<main class="">
  <div class="card" style="margin-bottom:15px;">
    <a href="/salary" style="display:block; font-weight:800; font-size:18px; text-align:center; text-decoration:none; color:inherit;">
      ⚡ З/П електрикам
    </a>
  </div>

  <div id="salaryElectriciansList"></div>
</main>

{{-- Payment modal --}}
<div id="payModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.75); z-index:9000;
  align-items:center; justify-content:center; padding:16px;">
  <div class="card" style="width:100%; max-width:400px; padding:20px;">
    <div style="font-weight:800; font-size:16px; margin-bottom:6px;" id="payModalTitle">Виплатити зарплату</div>
    <div style="font-size:14px; opacity:.8; margin-bottom:16px;" id="payModalDesc"></div>

    <div class="project-field-label">Гаманець для списання</div>
    <select id="payWalletSelect" class="btn" style="width:100%; margin-bottom:16px;">
      <option value="">Завантаження...</option>
    </select>

    <div style="display:flex; gap:8px;">
      <button id="payConfirmBtn" type="button" class="btn save" style="flex:1;">Виплатити</button>
      <button type="button" class="btn" style="flex:1;" onclick="closePayModal()">Скасувати</button>
    </div>
  </div>
</div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
let walletsCache = [];
let currentPayUserId = null;

function fmoney(amount, currency) {
  const sym = { UAH: '₴', USD: '$', EUR: '€' };
  return new Intl.NumberFormat('uk-UA', { maximumFractionDigits: 0 })
    .format(Number(amount) || 0) + '\u00a0' + (sym[currency] || currency || '');
}

async function openPayModal(userId, userName, total, currency) {
  currentPayUserId = userId;
  document.getElementById('payModalTitle').textContent = `Виплатити — ${userName}`;
  document.getElementById('payModalDesc').textContent  = `Сума: ${fmoney(total, currency)}`;

  const sel = document.getElementById('payWalletSelect');
  sel.innerHTML = '<option value="">Завантаження...</option>';

  try {
    if (!walletsCache.length) {
      const r = await fetch('/api/quality-checks/wallets');
      walletsCache = r.ok ? await r.json() : [];
    }
    const matching = walletsCache.filter(w => w.currency === currency);
    sel.innerHTML = matching.length
      ? matching.map(w => `<option value="${w.id}">${String(w.name ?? '').replace(/"/g,'&quot;')} (${w.currency})</option>`).join('')
      : `<option value="">Немає гаманців у ${currency}</option>`;
  } catch (_) {
    sel.innerHTML = '<option value="">Помилка завантаження</option>';
  }

  document.getElementById('payModal').style.display = 'flex';
}

function closePayModal() {
  document.getElementById('payModal').style.display = 'none';
  currentPayUserId = null;
}

async function paySalary() {
  const walletId = document.getElementById('payWalletSelect').value;
  if (!walletId) { alert('Оберіть гаманець'); return; }

  const btn = document.getElementById('payConfirmBtn');
  btn.disabled = true;
  btn.textContent = 'Виплата...';

  try {
    const r = await fetch(`/api/salary/pay/${currentPayUserId}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
      body: JSON.stringify({ wallet_id: walletId }),
    });
    const data = await r.json();
    if (r.ok && data.ok) {
      closePayModal();
      location.reload();
    } else {
      alert(data.error || 'Помилка виплати');
    }
  } catch (e) {
    alert('Помилка з\'єднання');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Виплатити';
  }
}

document.getElementById('payConfirmBtn').addEventListener('click', paySalary);

document.addEventListener('click', function (e) {
  const btn = e.target.closest('.pay-btn');
  if (btn) {
    e.preventDefault();
    openPayModal(
      parseInt(btn.dataset.userId),
      btn.dataset.userName,
      parseFloat(btn.dataset.total),
      btn.dataset.currency,
    );
    return;
  }
  if (e.target.id === 'payModal') closePayModal();
});

document.addEventListener('DOMContentLoaded', async function () {
  const list = document.getElementById('salaryElectriciansList');
  if (!list) return;

  const esc = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

  const formatMoney = (value, currency) => {
    const symbols = { UAH: '₴', USD: '$' };
    return `${new Intl.NumberFormat('uk-UA', { maximumFractionDigits: 0 }).format(Number(value || 0))} ${symbols[currency] || currency}`;
  };

  function parseInverterPower(inverterName) {
    const text = String(inverterName || '').replace(',', '.');
    const matches = text.match(/(\d+(?:\.\d+)?)\s*(?:k|kw|квт|кв)/i);
    if (matches) return Number(matches[1]);
    const anyNumber = text.match(/(\d+(?:\.\d+)?)/);
    return anyNumber ? Number(anyNumber[1]) : null;
  }

  function isLikelyHybrid(inverterName) {
    const text = String(inverterName || '').toLowerCase().replace(/\s+/g, ' ').trim();
    if (!text) return false;
    return ['hybrid','hybr','hibr','hibryd','гібрид','гибрид','гiбрид','гібр','гибр','гвбрид','гиб']
      .some(token => text.includes(token));
  }

  function calculatePieceworkSalary(project, rule) {
    const inverter = String(project?.inverter || '').trim();
    const power = parseInverterPower(inverter);
    const hybrid = isLikelyHybrid(inverter);
    const underOrEqual50 = power === null ? true : power <= 50;
    const toNumber = (value) => Number(value || 0);
    if (hybrid) {
      return {
        amount: underOrEqual50 ? toNumber(rule?.piecework_hybrid_le_50) : toNumber(rule?.piecework_hybrid_gt_50),
        currency: rule?.currency || 'USD',
      };
    }
    return {
      amount: underOrEqual50 ? toNumber(rule?.piecework_grid_le_50) : toNumber(rule?.piecework_grid_gt_50),
      currency: rule?.currency || 'USD',
    };
  }

  function renderPendingFolder(accruals, userId, userName, currency) {
    if (!accruals || !accruals.length) return '';

    const total = accruals.reduce((s, a) => s + Number(a.amount || 0), 0);
    const rows = accruals.map(a => `
      <div style="display:flex; justify-content:space-between; align-items:center;
        padding:6px 0; border-bottom:1px solid rgba(255,255,255,.07); font-size:13px;">
        <div style="opacity:.85;">${esc(a.client_name)}</div>
        <div style="font-weight:700; white-space:nowrap; padding-left:10px;">${formatMoney(a.amount, a.currency)}</div>
      </div>
    `).join('');

    return `
      <details style="margin-top:12px;" open>
        <summary style="cursor:pointer; font-size:13px; font-weight:700; color:#f5c842; padding:4px 0; list-style:none;">
          📁 Очікує виплати (${accruals.length} ${accruals.length === 1 ? 'проєкт' : 'проєкти'}) — ${formatMoney(total, currency)}
        </summary>
        <div style="margin-top:6px;">
          ${rows}
          <button type="button" class="btn save pay-btn"
            data-user-id="${userId}"
            data-user-name="${esc(userName)}"
            data-total="${total}"
            data-currency="${esc(currency)}"
            style="width:100%; margin-top:10px;">
            💸 Виплатити ${formatMoney(total, currency)}
          </button>
        </div>
      </details>
    `;
  }

  list.innerHTML = `<div class="card"><div style="font-size:14px; opacity:.8; text-align:center;">Завантаження...</div></div>`;

  try {
    const [staffRes, projectsRes, rulesRes, accrualsRes] = await Promise.all([
      fetch('/api/construction-staff-options'),
      fetch('/api/salary/projects'),
      fetch('/api/salary-rules?staff_group=electrician'),
      fetch('/api/salary/accruals'),
    ]);

    const staff = await staffRes.json();
    const projects = await projectsRes.json();
    const rulesPayload = await rulesRes.json();
    const accrualsGroups = accrualsRes.ok ? await accrualsRes.json() : [];

    if (!staffRes.ok) throw new Error(staff.error || 'Не вдалося завантажити електриків');
    if (!projectsRes.ok) throw new Error(projects.error || 'Не вдалося завантажити проєкти');
    if (!rulesRes.ok) throw new Error(rulesPayload.error || 'Не вдалося завантажити правила зарплатні');

    // Map accruals by staff_name (lowercase), only electrician group
    const accrualsByName = {};
    for (const group of accrualsGroups) {
      const electricianAccruals = (group.accruals ?? []).filter(a => a.staff_group === 'electrician');
      if (!electricianAccruals.length) continue;
      const key = String(electricianAccruals[0].staff_name || '').trim().toLowerCase();
      if (key) accrualsByName[key] = { ...group, accruals: electricianAccruals };
    }

    const electricians = Array.isArray(staff.electrician) ? staff.electrician : [];
    const rules = Array.isArray(rulesPayload.rules) ? rulesPayload.rules : [];
    const rulesMap = new Map(rules.map(rule => [String(rule?.staff_name || '').trim().toLowerCase(), rule]));

    if (!electricians.length) {
      list.innerHTML = `<div class="card"><div style="font-size:14px; opacity:.8; text-align:center;">Електриків поки немає</div></div>`;
      return;
    }

    const projectList = Array.isArray(projects) ? projects : [];
    const html = [];

    electricians
      .map(person => String(person.name || '').trim())
      .filter(Boolean)
      .forEach((name) => {
        const key = name.toLowerCase();
        const rule = rulesMap.get(key);
        const accrualGroup = accrualsByName[key];
        const pendingAccruals = accrualGroup?.accruals ?? [];
        const accrualCurrency = accrualGroup?.currency || 'USD';
        const userId = accrualGroup?.user_id;

        if (!rule) {
          html.push(`
            <div class="card" style="margin-bottom:12px;">
              <div style="font-weight:800; font-size:16px; margin-bottom:6px;">⚡ ${esc(name)}</div>
              <div style="font-size:14px; opacity:.75;">Правило нарахування ще не задано.</div>
              ${userId ? renderPendingFolder(pendingAccruals, userId, name, accrualCurrency) : ''}
            </div>
          `);
          return;
        }

        if (String(rule.mode) === 'fixed') {
          const pending = userId ? renderPendingFolder(pendingAccruals, userId, name, accrualCurrency) : '';
          if (!pending) {
            html.push(`
              <a href="/salary/fixed/show?staff_group=electrician&staff_name=${encodeURIComponent(name)}" class="card" style="margin-bottom:12px; display:block; text-decoration:none; color:inherit;">
                <div style="display:flex; justify-content:space-between; gap:10px; align-items:center;">
                  <div>
                    <div style="font-weight:800; font-size:16px;">⚡ ${esc(name)}</div>
                    <div style="font-size:13px; opacity:.72; margin-top:4px;">Помісячна зарплата</div>
                  </div>
                  <div style="font-weight:900; font-size:18px; white-space:nowrap;">${formatMoney(rule.fixed_amount || 0, rule.currency || 'UAH')}</div>
                </div>
              </a>
            `);
          } else {
            html.push(`
              <div class="card" style="margin-bottom:12px;">
                <div style="display:flex; justify-content:space-between; gap:10px; align-items:center;">
                  <div>
                    <div style="font-weight:800; font-size:16px;">⚡ ${esc(name)}</div>
                    <div style="font-size:13px; opacity:.72; margin-top:4px;">Помісячна зарплата</div>
                  </div>
                  <a href="/salary/fixed/show?staff_group=electrician&staff_name=${encodeURIComponent(name)}"
                    style="font-weight:900; font-size:18px; white-space:nowrap; text-decoration:none; color:inherit;">
                    ${formatMoney(rule.fixed_amount || 0, rule.currency || 'UAH')}
                  </a>
                </div>
                ${pending}
              </div>
            `);
          }
          return;
        }

        const total = projectList
          .filter(project => String(project?.electrician || '').trim().toLowerCase() === key)
          .map(project => calculatePieceworkSalary(project, rule))
          .reduce((sum, calc) => sum + Number(calc.amount || 0), 0);

        const pending = userId ? renderPendingFolder(pendingAccruals, userId, name, accrualCurrency) : '';

        if (!pending) {
          html.push(`
            <a href="/salary/electricians/show?name=${encodeURIComponent(name)}" class="card" style="margin-bottom:12px; display:block; text-decoration:none; color:inherit;">
              <div style="display:flex; justify-content:space-between; gap:10px; align-items:center;">
                <div>
                  <div style="font-weight:800; font-size:16px;">⚡ ${esc(name)}</div>
                  <div style="font-size:13px; opacity:.72; margin-top:4px;">Відкрити проєкти ${esc(name)}</div>
                </div>
                <div style="font-weight:900; font-size:18px; white-space:nowrap;">${formatMoney(total, rule.currency || 'USD')}</div>
              </div>
            </a>
          `);
        } else {
          html.push(`
            <div class="card" style="margin-bottom:12px;">
              <div style="display:flex; justify-content:space-between; gap:10px; align-items:center;">
                <div>
                  <div style="font-weight:800; font-size:16px;">⚡ ${esc(name)}</div>
                  <div style="font-size:13px; opacity:.72; margin-top:4px;">
                    <a href="/salary/electricians/show?name=${encodeURIComponent(name)}" style="color:inherit;">Відкрити проєкти →</a>
                  </div>
                </div>
                <div style="font-weight:900; font-size:18px; white-space:nowrap;">${formatMoney(total, rule.currency || 'USD')}</div>
              </div>
              ${pending}
            </div>
          `);
        }
      });

    list.innerHTML = html.join('');
  } catch (err) {
    list.innerHTML = `<div class="card"><div style="font-size:14px; opacity:.8; text-align:center;">${String(err.message || 'Помилка завантаження').replace(/</g,'&lt;')}</div></div>`;
  }
});
</script>

@include('partials.nav.bottom')
@endsection
