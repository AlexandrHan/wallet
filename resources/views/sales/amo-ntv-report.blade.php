@php
  $money = function ($amount, $currency = 'USD') {
      $amount = (float) $amount;
      if ($amount <= 0) return '—';
      $symbol = strtoupper((string) $currency) === 'USD' ? '$' : strtoupper((string) $currency) . ' ';
      return $symbol . number_format($amount, 0, '.', ' ');
  };

  $selectedManager = (int) ($selected_manager ?? 0);

  $stageColors = [
    38556547 => ['bg' => 'rgba(245,158,11,.18)',  'color' => '#fbbf24', 'dot' => '#f59e0b'],  // Частично оплатил
    69586234 => ['bg' => 'rgba(76,125,255,.18)',   'color' => '#7ca5ff', 'dot' => '#4c7dff'],  // Комплектація
    38556550 => ['bg' => 'rgba(76,125,255,.18)',   'color' => '#7ca5ff', 'dot' => '#4c7dff'],  // Очікування доставки
    69593822 => ['bg' => 'rgba(20,184,166,.18)',   'color' => '#2dd4bf', 'dot' => '#14b8a6'],  // заплановане будівництво
    69593826 => ['bg' => 'rgba(102,242,168,.15)',  'color' => '#66f2a8', 'dot' => '#4ade80'],  // Монтаж СП
    69593830 => ['bg' => 'rgba(34,211,238,.15)',   'color' => '#22d3ee', 'dot' => '#06b6d4'],  // Електрична частина
    69593834 => ['bg' => 'rgba(168,85,247,.18)',   'color' => '#c084fc', 'dot' => '#a855f7'],  // Здача проекту
    41906428 => ['bg' => 'rgba(251,146,60,.18)',   'color' => '#fb923c', 'dot' => '#f97316'],  // Збільшення потужності
    41906431 => ['bg' => 'rgba(99,102,241,.18)',   'color' => '#a5b4fc', 'dot' => '#6366f1'],  // Оформлення ЗТ
    49782427 => ['bg' => 'rgba(102,242,168,.22)',  'color' => '#4ade80', 'dot' => '#22c55e'],  // Остаточна оплата
  ];

  // Group rows by stage
  $grouped = [];
  foreach ($rows as $row) {
      $sid = (int) $row['status_id'];
      $grouped[$sid][] = $row;
  }
@endphp

@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="/css/nav-telegram.css?v={{ filemtime(public_path('css/nav-telegram.css')) }}">
<style>
.ntv-wrap {
  width: 100%;
  max-width: 660px;
  margin: 0 auto;
  padding-bottom: 100px;
  box-sizing: border-box;
}

main.wrap.reclamations-main {
  width: 100%;
  max-width: 980px;
  margin-left: auto;
  margin-right: auto;
}

/* ── Filter bar ── */
.ntv-filter {
  display: flex;
  gap: 8px;
  align-items: stretch;
  margin-bottom: 14px;
}
.ntv-filter select {
  flex: 1;
  min-width: 0;
  height: 44px;
  background: rgba(255,255,255,.08);
  border: 1px solid rgba(255,255,255,.1);
  border-radius: 14px;
  color: var(--text);
  font-size: 15px;
  padding: 0 14px;
  appearance: none;
  -webkit-appearance: none;
  cursor: pointer;
}
.ntv-filter select option { background: #0b0d10; color: #e9eef6; }
.ntv-filter-btn {
  height: 44px;
  padding: 0 18px;
  background: rgba(84,192,134,.75);
  color: #000;
  font-weight: 700;
  font-size: 14px;
  border: none;
  border-radius: 14px;
  cursor: pointer;
  white-space: nowrap;
  flex-shrink: 0;
}
.ntv-toggle-all {
  height: 44px;
  padding: 0 14px;
  background: rgba(255,255,255,.08);
  color: var(--text);
  font-weight: 700;
  font-size: 14px;
  border: 1px solid rgba(255,255,255,.1);
  border-radius: 14px;
  cursor: pointer;
  white-space: nowrap;
  flex-shrink: 0;
}

/* ── Stats row ── */
.ntv-stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
  margin-bottom: 16px;
}
.ntv-stat {
  background: linear-gradient(180deg, rgba(255,255,255,.1), rgba(255,255,255,.04));
  border: 1px solid rgba(255,255,255,.1);
  border-radius: 16px;
  padding: 12px 10px;
  text-align: center;
}
.ntv-stat__label {
  font-size: 10px;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: .04em;
  font-weight: 600;
  margin-bottom: 4px;
}
.ntv-stat__val {
  font-size: 18px;
  font-weight: 800;
  color: var(--text);
  line-height: 1.1;
}

/* ── Stage group ── */
.ntv-group {
  width: 100%;
  margin-bottom: 10px;
}
.ntv-group-head {
  display: flex;
  width: 100%;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  background: rgba(255,255,255,.05);
  border: 1px solid rgba(255,255,255,.09);
  border-radius: 14px;
  cursor: pointer;
  user-select: none;
  -webkit-user-select: none;
  box-sizing: border-box;
  text-align: left;
  font-family: inherit;
}
.ntv-group-head.is-open {
  border-bottom-left-radius: 0;
  border-bottom-right-radius: 0;
}
.ntv-group-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}
.ntv-group-label {
  flex: 1;
  font-size: 14px;
  font-weight: 700;
  color: var(--text);
}
.ntv-group-count {
  font-size: 12px;
  font-weight: 700;
  color: var(--muted);
  background: rgba(255,255,255,.1);
  padding: 2px 9px;
  border-radius: 99px;
}
.ntv-group-chevron {
  font-size: 11px;
  color: var(--muted);
  transition: transform .2s;
}
.ntv-group-head.is-open .ntv-group-chevron {
  transform: rotate(180deg);
}
.ntv-group-body {
  display: none;
  width: 100%;
  border: 1px solid rgba(255,255,255,.09);
  border-top: none;
  border-radius: 0 0 14px 14px;
  padding: 6px;
  background: rgba(255,255,255,.02);
  box-sizing: border-box;
}
.ntv-group-body.is-open { display: block; }

/* ── Project card ── */
.ntv-card {
  width: 100%;
  background: linear-gradient(160deg, rgba(255,255,255,.09), rgba(255,255,255,.03));
  border: 1px solid rgba(255,255,255,.09);
  border-radius: 12px;
  padding: 14px;
  margin-bottom: 6px;
  box-sizing: border-box;
}
.ntv-card:last-child { margin-bottom: 0; }

.ntv-card__top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 8px;
}
.ntv-card__name {
  font-size: 15px;
  font-weight: 700;
  color: var(--text);
  line-height: 1.3;
}
.ntv-card__deal {
  font-size: 11px;
  color: var(--muted);
  margin-top: 2px;
}
.ntv-card__amt {
  text-align: right;
  flex-shrink: 0;
}
.ntv-card__amt-val {
  font-size: 16px;
  font-weight: 800;
  color: var(--green);
  white-space: nowrap;
}
.ntv-card__amt-adv {
  font-size: 11px;
  color: var(--muted);
  margin-top: 1px;
  white-space: nowrap;
}

.ntv-card__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  align-items: center;
  margin-bottom: 10px;
}
.ntv-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  font-weight: 600;
  padding: 3px 9px;
  border-radius: 99px;
  background: rgba(255,255,255,.08);
  color: var(--muted);
  border: 1px solid rgba(255,255,255,.09);
  white-space: nowrap;
}
.ntv-chip--stage {
  font-weight: 700;
}

/* ── Equipment toggle ── */
.ntv-equip-toggle {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--muted);
  background: none;
  border: none;
  padding: 0;
  cursor: pointer;
  font-family: inherit;
  margin-top: 2px;
}
.ntv-equip-toggle svg {
  transition: transform .2s;
}
.ntv-equip-toggle.is-open svg {
  transform: rotate(180deg);
}
.ntv-equip-body {
  display: none;
  margin-top: 8px;
  padding: 10px;
  background: rgba(255,255,255,.05);
  border-radius: 10px;
  border: 1px solid rgba(255,255,255,.07);
}
.ntv-equip-body.is-open { display: block; }
.ntv-equip-row {
  display: flex;
  align-items: baseline;
  gap: 6px;
  font-size: 13px;
  padding: 4px 0;
  border-bottom: 1px solid rgba(255,255,255,.05);
}
.ntv-equip-row:last-child { border-bottom: none; padding-bottom: 0; }
.ntv-equip-label {
  color: var(--muted);
  font-size: 11px;
  font-weight: 600;
  flex-shrink: 0;
  width: 100px;
}
.ntv-equip-val {
  color: var(--text);
  font-weight: 500;
  line-height: 1.3;
}

/* ── Empty state ── */
.ntv-empty {
  text-align: center;
  padding: 40px 20px;
  color: var(--muted);
  font-size: 15px;
}

/* ── Title ── */
.ntv-title-row {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 14px;
}
.ntv-title {
  font-size: 20px;
  font-weight: 800;
  color: var(--text);
  flex: 1;
}
.ntv-subtitle {
  font-size: 11px;
  color: var(--muted);
  margin-top: 2px;
}

@media (min-width: 1024px) {
  main.wrap.reclamations-main {
    max-width: 980px;
    padding-left: 18px;
    padding-right: 18px;
  }

  .ntv-wrap {
    max-width: 100%;
  }

  .ntv-stats {
    gap: 12px;
  }

  .ntv-group-body {
    padding: 10px;
  }

  .ntv-card {
    padding: 16px 18px;
  }
}
</style>
@endpush

@section('content')
<div class="ntv-wrap">

  {{-- Title --}}
  <div class="ntv-title-row">
    <div>
      <div class="ntv-title">АМО звіт</div>
      <div class="ntv-subtitle">Pipeline 4071382 · {{ count($rows) > 0 ? 'Від "Частично оплатил" до "Остаточна оплата"' : 'Немає даних' }}</div>
    </div>
  </div>

  {{-- Filter --}}
  <form class="ntv-filter" method="GET" action="{{ route('sales.amo-ntv-report') }}" id="ntvFilterForm">
    <select name="manager" id="ntvManagerSelect">
      <option value="0" @selected($selectedManager === 0)>👤 Всі менеджери</option>
      @foreach($managers as $manager)
        <option value="{{ $manager['id'] }}" @selected($selectedManager === (int) $manager['id'])>
          {{ $manager['name'] }}
        </option>
      @endforeach
    </select>
    <button type="submit" class="ntv-filter-btn">Показати</button>
    @if(!empty($rows))
      <button type="button" class="ntv-toggle-all" id="ntvToggleAll">Розкрити всі</button>
    @endif
  </form>

  {{-- Stats --}}
  <div class="ntv-stats">
    <div class="ntv-stat">
      <div class="ntv-stat__label">Проєктів</div>
      <div class="ntv-stat__val">{{ number_format((int)($totals['projects'] ?? 0), 0, '.', ' ') }}</div>
    </div>
    <div class="ntv-stat">
      <div class="ntv-stat__label">Сума AMO</div>
      <div class="ntv-stat__val" style="font-size:14px;">{{ $money($totals['amount'] ?? 0, 'USD') }}</div>
    </div>
    <div class="ntv-stat">
      <div class="ntv-stat__label">Аванс</div>
      <div class="ntv-stat__val" style="font-size:14px;">{{ $money($totals['advance'] ?? 0, 'USD') }}</div>
    </div>
  </div>

  {{-- Content --}}
  @if(empty($rows))
    <div class="ntv-empty">Немає AMO-проєктів за вибраним фільтром.</div>
  @else
    @foreach($stage_labels as $stageId => $stageLabel)
      @if(!empty($grouped[$stageId]))
        @php
          $sc = $stageColors[$stageId] ?? ['bg' => 'rgba(255,255,255,.1)', 'color' => '#9aa6bc', 'dot' => '#9aa6bc'];
          $stageRows = $grouped[$stageId];
        @endphp
        <div class="ntv-group">
          <button type="button" class="ntv-group-head" onclick="this.classList.toggle('is-open'); this.nextElementSibling.classList.toggle('is-open');">
            <span class="ntv-group-dot" style="background:{{ $sc['dot'] }};"></span>
            <span class="ntv-group-label" style="color:{{ $sc['color'] }};">{{ $stageLabel }}</span>
            <span class="ntv-group-count">{{ count($stageRows) }}</span>
            <span class="ntv-group-chevron">▾</span>
          </button>
          <div class="ntv-group-body">
            @foreach($stageRows as $row)
              @php
                $hasEquip = !empty($row['equipment']);
                $cardId = 'ntv-card-' . $row['id'];
              @endphp
              <div class="ntv-card">
                <div class="ntv-card__top">
                  <div>
                    <div class="ntv-card__name">{{ $row['client_name'] }}</div>
                    <div class="ntv-card__deal">{{ $row['stage'] }}</div>
                    @if($row['deal_name'] && $row['deal_name'] !== $row['client_name'])
                      <div class="ntv-card__deal">{{ $row['deal_name'] }}</div>
                    @endif
                    <div class="ntv-card__deal">AMO #{{ $row['amo_deal_id'] }}</div>
                  </div>
                  <div class="ntv-card__amt">
                    <div class="ntv-card__amt-val">{{ $money($row['total_amount'], $row['currency']) }}</div>
                    @if($row['advance_amount'] > 0)
                      <div class="ntv-card__amt-adv">Аванс {{ $money($row['advance_amount'], 'USD') }}</div>
                    @endif
                  </div>
                </div>

                <div class="ntv-card__meta">
                  @if($selectedManager === 0)
                    <span class="ntv-chip">👤 {{ $row['manager_name'] }}</span>
                  @endif
                  @if($hasEquip)
                    <button type="button" class="ntv-equip-toggle" onclick="toggleEquip(this)" data-target="{{ $cardId }}-equip">
                      📦 Обладнання ({{ count($row['equipment']) }})
                      <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                        <path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                      </svg>
                    </button>
                  @else
                    <span class="ntv-chip" style="opacity:.5;">📦 Обладнання не вказане</span>
                  @endif
                </div>

                @if($hasEquip)
                  <div class="ntv-equip-body" id="{{ $cardId }}-equip">
                    @foreach($row['equipment'] as $item)
                      <div class="ntv-equip-row">
                        <span class="ntv-equip-label">{{ $item['label'] }}</span>
                        <span class="ntv-equip-val">{{ $item['value'] }}</span>
                      </div>
                    @endforeach
                  </div>
                @endif
              </div>
            @endforeach
          </div>
        </div>
      @endif
    @endforeach
  @endif

</div>

@include('partials.nav.bottom')

<script>
function toggleEquip(btn) {
  const body = document.getElementById(btn.dataset.target);
  const willOpen = body ? !body.classList.contains('is-open') : !btn.classList.contains('is-open');
  setEquipOpen(btn, willOpen);
}

function setEquipOpen(btn, isOpen) {
  btn.classList.toggle('is-open', isOpen);
  const body = document.getElementById(btn.dataset.target);
  if (body) body.classList.toggle('is-open', isOpen);
}

function setGroupOpen(head, isOpen) {
  head.classList.toggle('is-open', isOpen);
  if (head.nextElementSibling) {
    head.nextElementSibling.classList.toggle('is-open', isOpen);
  }
}

function syncToggleAllLabel(btn, groups) {
  const allOpen = Array.from(groups).every(function (group) {
    return group.classList.contains('is-open');
  });
  btn.textContent = allOpen ? 'Закрити всі' : 'Розкрити всі';
}

// Auto-open stage groups if only one manager selected (less clutter)
document.addEventListener('DOMContentLoaded', function () {
  const groups = document.querySelectorAll('.ntv-group-head');
  const selectedManager = {{ $selectedManager }};
  const toggleAll = document.getElementById('ntvToggleAll');

  if (selectedManager > 0 || groups.length <= 3) {
    groups.forEach(function (g) {
      setGroupOpen(g, true);
    });
  }

  if (selectedManager > 0) {
    document.querySelectorAll('.ntv-equip-toggle').forEach(function (btn) {
      setEquipOpen(btn, true);
    });
  }

  if (toggleAll) {
    syncToggleAllLabel(toggleAll, groups);
    toggleAll.addEventListener('click', function () {
      const shouldOpen = Array.from(groups).some(function (group) {
        return !group.classList.contains('is-open');
      });
      groups.forEach(function (group) {
        setGroupOpen(group, shouldOpen);
      });
      syncToggleAllLabel(toggleAll, groups);
    });

    groups.forEach(function (group) {
      group.addEventListener('click', function () {
        window.setTimeout(function () {
          syncToggleAllLabel(toggleAll, groups);
        }, 0);
      });
    });
  }
});
</script>
@endsection
