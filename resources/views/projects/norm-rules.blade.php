@push('styles')
  <link rel="stylesheet" href="/css/project.css?v={{ filemtime(public_path('css/project.css')) }}">
@endpush

@extends('layouts.app')

@section('content')
<main class="projects-main">

  <div class="projects-title-card">
    <div class="projects-title">🔧 Нормалізація обладнання</div>
    <div style="font-size:12px; opacity:.6; margin-top:2px;">Правила приведення назв до єдиного вигляду</div>
  </div>

  @foreach([['battery','⚡ АКБ'],['panel','☀️ Фотомодулі'],['inverter','🔌 Інвертори']] as [$typeKey, $typeLabel])
  <div class="nr-section" id="section-{{ $typeKey }}">
    <div class="nr-section-header">
      <span>{{ $typeLabel }}</span>
      <button class="bnr-btn-add nr-add-btn" data-type="{{ $typeKey }}">+ Додати</button>
    </div>

    {{-- Форма --}}
    <div class="nr-form card" id="form-{{ $typeKey }}" style="display:none; margin-bottom:12px;">
      <div style="font-weight:700; font-size:13px; margin-bottom:12px;">Нове правило · {{ $typeLabel }}</div>
      <div style="display:flex; flex-direction:column; gap:9px;">
        <div>
          <label class="bnr-label">Якщо назва містить</label>
          <input type="text" class="bnr-input nr-match" placeholder="напр. LV D53">
        </div>
        <div>
          <label class="bnr-label">Привести до</label>
          <input type="text" class="bnr-input nr-output" placeholder="напр. T-BAT LV D53">
        </div>
        <div style="display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
          <div>
            <label class="bnr-label">Пріоритет</label>
            <input type="number" class="bnr-input nr-order" value="0" style="width:80px;">
          </div>
          <label class="bnr-checkbox-label">
            <input type="checkbox" class="nr-is-regex">
            <span>Regex</span>
          </label>
        </div>
        <div style="display:flex; gap:8px; margin-top:2px;">
          <button class="bnr-btn-save nr-save-btn">Зберегти</button>
          <button class="bnr-btn-cancel nr-cancel-btn">Скасувати</button>
        </div>
      </div>
      <div class="nr-err" style="color:#f76; font-size:13px; margin-top:8px; display:none;"></div>
    </div>

    {{-- Список --}}
    <div class="card nr-list" id="list-{{ $typeKey }}">
      <div style="text-align:center; padding:24px; opacity:.4; font-size:13px;">Завантаження...</div>
    </div>
  </div>
  @endforeach

</main>

<style>
.nr-section { margin-bottom: 20px; }
.nr-section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-weight: 800;
  font-size: 15px;
  margin-bottom: 10px;
}
.bnr-btn-add {
  background: rgba(255,255,255,0.08);
  border: 1px solid rgba(255,255,255,0.15);
  border-radius: 16px;
  padding: 5px 14px;
  font-size: 13px;
  color: inherit;
  cursor: pointer;
  font-weight: 600;
}
.bnr-btn-add:hover { background: rgba(255,255,255,0.14); }
.bnr-label {
  display: block;
  font-size: 11px;
  opacity: .5;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .04em;
  margin-bottom: 4px;
}
.bnr-input {
  width: 100%;
  background: rgba(255,255,255,0.07);
  border: 1px solid rgba(255,255,255,0.15);
  border-radius: 10px;
  padding: 8px 11px;
  color: inherit;
  font-size: 13px;
  box-sizing: border-box;
}
.bnr-input:focus { outline: none; border-color: rgba(255,255,255,0.35); }
.bnr-checkbox-label {
  display: flex;
  align-items: center;
  gap: 7px;
  font-size: 13px;
  cursor: pointer;
  margin-top: 16px;
  opacity: .8;
}
.bnr-checkbox-label input { width: 15px; height: 15px; cursor: pointer; accent-color: #4d9; }
.bnr-btn-save {
  background: rgba(100,200,120,0.18);
  border: 1px solid rgba(100,200,120,0.35);
  border-radius: 10px;
  padding: 7px 16px;
  font-size: 13px;
  color: #4d9;
  cursor: pointer;
  font-weight: 600;
}
.bnr-btn-cancel {
  background: rgba(255,255,255,0.05);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 10px;
  padding: 7px 16px;
  font-size: 13px;
  color: inherit;
  cursor: pointer;
  opacity: .55;
}
.nr-rule-row {
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 10px 0;
  border-bottom: 1px solid rgba(255,255,255,0.05);
  flex-wrap: wrap;
}
.nr-rule-row:last-child { border-bottom: none; }
.nr-match {
  font-size: 12px;
  font-family: monospace;
  background: rgba(255,255,255,0.07);
  border-radius: 5px;
  padding: 2px 7px;
  word-break: break-all;
}
.nr-regex-badge {
  font-size: 10px;
  font-weight: 700;
  background: rgba(180,130,255,0.18);
  color: #c9a0ff;
  border-radius: 4px;
  padding: 1px 5px;
  flex-shrink: 0;
}
.nr-arrow { opacity: .3; flex-shrink: 0; }
.nr-output { font-size: 13px; font-weight: 600; flex: 1; min-width: 80px; }
.nr-priority { font-size: 11px; opacity: .28; white-space: nowrap; }
.nr-btn-del {
  background: rgba(255,80,80,0.1);
  border: 1px solid rgba(255,80,80,0.18);
  border-radius: 7px;
  padding: 3px 9px;
  font-size: 11px;
  color: #f76;
  cursor: pointer;
  flex-shrink: 0;
}
.nr-btn-del:hover { background: rgba(255,80,80,0.2); }
</style>

<script>
const CSRF = () => document.querySelector('meta[name=csrf-token]')?.content ?? '';

function esc(v) {
  return String(v ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

async function loadRules(type) {
  const list = document.getElementById('list-' + type);
  const rules = await fetch('/api/norm-rules?type=' + type).then(r => r.json());
  if (!rules.length) {
    list.innerHTML = '<div style="opacity:.35; padding:14px 0; font-size:13px;">Правил ще немає</div>';
    return;
  }
  list.innerHTML = '';
  rules.forEach(rule => {
    const row = document.createElement('div');
    row.className = 'nr-rule-row';
    const badge = rule.is_regex ? '<span class="nr-regex-badge">REGEX</span>' : '';
    row.innerHTML = `
      <span class="nr-match">${esc(rule.match_text)}</span>
      ${badge}
      <span class="nr-arrow">→</span>
      <span class="nr-output">${esc(rule.output_name)}</span>
      <span class="nr-priority">p:${rule.sort_order}</span>
      <button class="nr-btn-del" data-id="${rule.id}" data-type="${type}">✕</button>
    `;
    list.appendChild(row);
  });
  list.querySelectorAll('.nr-btn-del').forEach(btn => {
    btn.addEventListener('click', async () => {
      if (!confirm('Видалити правило?')) return;
      await fetch('/api/norm-rules/' + btn.dataset.id, {
        method: 'DELETE',
        headers: {'X-CSRF-TOKEN': CSRF()},
      });
      loadRules(btn.dataset.type);
    });
  });
}

document.querySelectorAll('.nr-add-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const type = btn.dataset.type;
    const form = document.getElementById('form-' + type);
    form.style.display = form.style.display === 'none' ? '' : 'none';
    if (form.style.display !== 'none') form.querySelector('.nr-match').focus();
  });
});

document.querySelectorAll('.nr-cancel-btn').forEach(btn => {
  const form = btn.closest('.nr-form');
  btn.addEventListener('click', () => { form.style.display = 'none'; form.querySelector('.nr-err').style.display = 'none'; });
});

document.querySelectorAll('.nr-save-btn').forEach(btn => {
  const form    = btn.closest('.nr-form');
  const section = btn.closest('.nr-section');
  const type    = section.id.replace('section-', '');

  btn.addEventListener('click', async () => {
    const match   = form.querySelector('.nr-match').value.trim();
    const output  = form.querySelector('.nr-output').value.trim();
    const order   = parseInt(form.querySelector('.nr-order').value) || 0;
    const isRegex = form.querySelector('.nr-is-regex').checked;
    const err     = form.querySelector('.nr-err');

    if (!match || !output) {
      err.textContent = 'Заповніть обидва поля';
      err.style.display = '';
      return;
    }
    err.style.display = 'none';

    const res = await fetch('/api/norm-rules', {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF()},
      body: JSON.stringify({ type, match_text: match, output_name: output, sort_order: order, is_regex: isRegex ? 1 : 0 }),
    });

    if (!res.ok) {
      const data = await res.json();
      err.textContent = data.error ?? 'Помилка';
      err.style.display = '';
      return;
    }

    form.querySelector('.nr-match').value  = '';
    form.querySelector('.nr-output').value = '';
    form.querySelector('.nr-order').value  = '0';
    form.querySelector('.nr-is-regex').checked = false;
    form.style.display = 'none';
    loadRules(type);
  });
});

['battery','panel','inverter'].forEach(t => loadRules(t));
</script>

@include('partials.nav.bottom')
@endsection
