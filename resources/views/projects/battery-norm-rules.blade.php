@push('styles')
  <link rel="stylesheet" href="/css/project.css?v={{ filemtime(public_path('css/project.css')) }}">
@endpush

@extends('layouts.app')

@section('content')
<main class="projects-main">

  <div class="projects-title-card">
    <div class="projects-title">🔧 Нормалізація назв АКБ</div>
    <div style="font-size:12px; opacity:.6; margin-top:2px;">Правила приведення назв батарей до єдиного вигляду</div>
  </div>

  <div style="margin-bottom:16px;">
    <button class="bnr-btn-add" id="btnAddRule">+ Додати правило</button>
  </div>

  {{-- Форма додавання --}}
  <div id="addRuleForm" class="card" style="display:none; margin-bottom:16px;">
    <div style="font-weight:700; font-size:14px; margin-bottom:14px;">Нове правило</div>
    <div style="display:flex; flex-direction:column; gap:10px;">
      <div>
        <label class="bnr-label">Якщо назва містить</label>
        <input id="newMatch" type="text" class="bnr-input" placeholder="напр. LV D53 або regex: lv[\s\-]*d[\s\-]*53">
      </div>
      <div>
        <label class="bnr-label">Привести до</label>
        <input id="newOutput" type="text" class="bnr-input" placeholder="напр. T-BAT LV D53">
      </div>
      <div style="display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
        <div>
          <label class="bnr-label">Пріоритет</label>
          <input id="newOrder" type="number" class="bnr-input" value="0" style="width:90px;">
        </div>
        <label class="bnr-checkbox-label">
          <input type="checkbox" id="newIsRegex">
          <span>Regex-патерн</span>
        </label>
      </div>
      <div style="display:flex; gap:10px; margin-top:4px;">
        <button class="bnr-btn-save" id="btnSaveRule">Зберегти</button>
        <button class="bnr-btn-cancel" id="btnCancelAdd">Скасувати</button>
      </div>
    </div>
    <div id="addError" style="color:#f76; font-size:13px; margin-top:8px; display:none;"></div>
  </div>

  <div id="rulesList">
    <div style="text-align:center; padding:40px; opacity:.5;">Завантаження...</div>
  </div>

</main>

<style>
.bnr-btn-add {
  background: rgba(255,255,255,0.1);
  border: 1px solid rgba(255,255,255,0.18);
  border-radius: 20px;
  padding: 8px 18px;
  font-size: 14px;
  color: inherit;
  cursor: pointer;
  font-weight: 600;
  transition: background .15s;
}
.bnr-btn-add:hover { background: rgba(255,255,255,0.16); }
.bnr-label {
  display: block;
  font-size: 12px;
  opacity: .5;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .04em;
  margin-bottom: 5px;
}
.bnr-input {
  width: 100%;
  background: rgba(255,255,255,0.07);
  border: 1px solid rgba(255,255,255,0.15);
  border-radius: 10px;
  padding: 9px 12px;
  color: inherit;
  font-size: 14px;
  box-sizing: border-box;
}
.bnr-input:focus { outline: none; border-color: rgba(255,255,255,0.35); }
.bnr-checkbox-label {
  display: flex;
  align-items: center;
  gap: 7px;
  font-size: 13px;
  cursor: pointer;
  margin-top: 18px;
  opacity: .8;
}
.bnr-checkbox-label input { width: 16px; height: 16px; cursor: pointer; accent-color: #4d9; }
.bnr-btn-save {
  background: rgba(100,200,120,0.2);
  border: 1px solid rgba(100,200,120,0.4);
  border-radius: 10px;
  padding: 8px 18px;
  font-size: 14px;
  color: #4d9;
  cursor: pointer;
  font-weight: 600;
}
.bnr-btn-cancel {
  background: rgba(255,255,255,0.06);
  border: 1px solid rgba(255,255,255,0.12);
  border-radius: 10px;
  padding: 8px 18px;
  font-size: 14px;
  color: inherit;
  cursor: pointer;
  opacity: .6;
}
.bnr-rule-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 11px 0;
  border-bottom: 1px solid rgba(255,255,255,0.06);
  flex-wrap: wrap;
}
.bnr-rule-row:last-child { border-bottom: none; }
.bnr-arrow { opacity: .35; font-size: 16px; flex-shrink: 0; }
.bnr-match {
  font-size: 13px;
  font-family: monospace;
  background: rgba(255,255,255,0.07);
  border-radius: 6px;
  padding: 3px 8px;
  word-break: break-all;
}
.bnr-regex-badge {
  font-size: 10px;
  font-weight: 700;
  background: rgba(180,130,255,0.18);
  color: #c9a0ff;
  border-radius: 4px;
  padding: 2px 6px;
  flex-shrink: 0;
}
.bnr-output { font-size: 13px; font-weight: 600; flex: 1; min-width: 100px; }
.bnr-priority { font-size: 11px; opacity: .3; white-space: nowrap; }
.bnr-btn-del {
  background: rgba(255,80,80,0.12);
  border: 1px solid rgba(255,80,80,0.2);
  border-radius: 8px;
  padding: 4px 10px;
  font-size: 12px;
  color: #f76;
  cursor: pointer;
  flex-shrink: 0;
}
.bnr-btn-del:hover { background: rgba(255,80,80,0.22); }
</style>

<script>
function esc(v) {
  return String(v ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

async function loadRules() {
  const list = document.getElementById('rulesList');
  const rules = await fetch('/api/battery-norm-rules').then(r => r.json());
  if (!rules.length) {
    list.innerHTML = '<div style="opacity:.4; padding:20px 0; font-size:13px;">Правил ще немає</div>';
    return;
  }
  const card = document.createElement('div');
  card.className = 'card';
  rules.forEach(rule => {
    const row = document.createElement('div');
    row.className = 'bnr-rule-row';
    const regexBadge = rule.is_regex ? '<span class="bnr-regex-badge">REGEX</span>' : '';
    row.innerHTML = `
      <span class="bnr-match">${esc(rule.match_text)}</span>
      ${regexBadge}
      <span class="bnr-arrow">→</span>
      <span class="bnr-output">${esc(rule.output_name)}</span>
      <span class="bnr-priority">p:${rule.sort_order}</span>
      <button class="bnr-btn-del" data-id="${rule.id}">Видалити</button>
    `;
    card.appendChild(row);
  });
  list.innerHTML = '';
  list.appendChild(card);

  list.querySelectorAll('.bnr-btn-del').forEach(btn => {
    btn.addEventListener('click', async () => {
      if (!confirm('Видалити правило?')) return;
      await fetch('/api/battery-norm-rules/' + btn.dataset.id, {
        method: 'DELETE',
        headers: {'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? ''},
      });
      loadRules();
    });
  });
}

document.getElementById('btnAddRule').addEventListener('click', () => {
  document.getElementById('addRuleForm').style.display = '';
  document.getElementById('newMatch').focus();
});
document.getElementById('btnCancelAdd').addEventListener('click', () => {
  document.getElementById('addRuleForm').style.display = 'none';
  document.getElementById('addError').style.display = 'none';
});

document.getElementById('btnSaveRule').addEventListener('click', async () => {
  const match   = document.getElementById('newMatch').value.trim();
  const output  = document.getElementById('newOutput').value.trim();
  const order   = parseInt(document.getElementById('newOrder').value) || 0;
  const isRegex = document.getElementById('newIsRegex').checked;
  const err     = document.getElementById('addError');

  if (!match || !output) {
    err.textContent = 'Заповніть обидва поля';
    err.style.display = '';
    return;
  }
  err.style.display = 'none';

  const res = await fetch('/api/battery-norm-rules', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
    },
    body: JSON.stringify({ match_text: match, output_name: output, sort_order: order, is_regex: isRegex ? 1 : 0 }),
  });

  if (!res.ok) {
    const data = await res.json();
    err.textContent = data.error ?? 'Помилка збереження';
    err.style.display = '';
    return;
  }

  document.getElementById('newMatch').value  = '';
  document.getElementById('newOutput').value = '';
  document.getElementById('newOrder').value  = '0';
  document.getElementById('newIsRegex').checked = false;
  document.getElementById('addRuleForm').style.display = 'none';
  loadRules();
});

loadRules();
</script>

@include('partials.nav.bottom')
@endsection
