@push('styles')
<link rel="stylesheet" href="/css/nav-telegram.css?v={{ filemtime(public_path('css/nav-telegram.css')) }}">
@endpush

@extends('layouts.app')

@section('content')

<main class="wrap has-tg-nav" style="padding:16px; max-width:680px; margin:0 auto; margin-bottom:5rem;">

  <div style="font-weight:700; font-size:18px; margin-bottom:18px; text-align:center;">
    📊 Аналітика
  </div>

  {{-- БАЛАНСИ ПО ВАЛЮТАХ --}}
  <div class="card" style="margin-bottom:14px; padding:16px;">
    <div style="font-weight:600; margin-bottom:12px; opacity:.7; font-size:13px; text-transform:uppercase; letter-spacing:.05em;">Загальний баланс</div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
      @foreach(['UAH','USD','EUR'] as $cur)
        @php $bal = round($balances[$cur]->balance ?? 0) @endphp
        <div style="flex:1; min-width:120px; background:rgba(255,255,255,0.06); border-radius:14px; padding:14px 16px;">
          <div style="font-size:12px; opacity:.5; margin-bottom:4px;">{{ $cur }}</div>
          <div style="font-size:20px; font-weight:700; {{ $bal < 0 ? 'color:#f66;' : '' }}">
            {{ number_format($bal, 0, '.', ' ') }}
          </div>
        </div>
      @endforeach
    </div>
  </div>

  {{-- ПОТОЧНИЙ МІСЯЦЬ --}}
  @php
    $monthNames = ['','Січень','Лютий','Березень','Квітень','Травень','Червень','Липень','Серпень','Вересень','Жовтень','Листопад','Грудень'];
    $incomeNow  = round($thisMonth['income']->total ?? 0);
    $expenseNow = round($thisMonth['expense']->total ?? 0);
    $profitNow  = $incomeNow - $expenseNow;
  @endphp
  <div class="card" style="margin-bottom:14px; padding:16px;">
    <div style="font-weight:600; margin-bottom:12px; opacity:.7; font-size:13px; text-transform:uppercase; letter-spacing:.05em;">
      {{ $monthNames[$month] }} {{ $year }}
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
      <div style="flex:1; min-width:130px; background:rgba(100,220,100,0.08); border-radius:14px; padding:14px 16px;">
        <div style="font-size:12px; opacity:.5; margin-bottom:4px;">Надходження</div>
        <div style="font-size:18px; font-weight:700; color:#6ddf6d;">{{ number_format($incomeNow, 0, '.', ' ') }}</div>
      </div>
      <div style="flex:1; min-width:130px; background:rgba(255,100,100,0.08); border-radius:14px; padding:14px 16px;">
        <div style="font-size:12px; opacity:.5; margin-bottom:4px;">Витрати</div>
        <div style="font-size:18px; font-weight:700; color:#f88;">{{ number_format($expenseNow, 0, '.', ' ') }}</div>
      </div>
      <div style="flex:1; min-width:130px; background:rgba(255,255,255,0.06); border-radius:14px; padding:14px 16px;">
        <div style="font-size:12px; opacity:.5; margin-bottom:4px;">Різниця</div>
        <div style="font-size:18px; font-weight:700; {{ $profitNow >= 0 ? 'color:#6ddf6d;' : 'color:#f66;' }}">
          {{ $profitNow >= 0 ? '+' : '' }}{{ number_format($profitNow, 0, '.', ' ') }}
        </div>
      </div>
    </div>
  </div>

  {{-- ГРАФІК ПО МІСЯЦЯХ --}}
  @if(count($months) > 0)
  @php
    $maxVal = max(array_merge(array_column($months, 'income'), array_column($months, 'expense'), [1]));
  @endphp
  <div class="card" style="margin-bottom:14px; padding:16px;">
    <div style="font-weight:600; margin-bottom:14px; opacity:.7; font-size:13px; text-transform:uppercase; letter-spacing:.05em;">Динаміка (місяці)</div>
    <div style="display:flex; align-items:flex-end; gap:8px; height:120px;">
      @foreach($months as $m => $data)
        @php
          $incPct = round(($data['income'] / $maxVal) * 100);
          $expPct = round(($data['expense'] / $maxVal) * 100);
          $label  = substr($m, 5); // MM
        @endphp
        <div style="flex:1; display:flex; flex-direction:column; align-items:center; gap:3px; height:100%;">
          <div style="flex:1; width:100%; display:flex; align-items:flex-end; gap:2px;">
            <div style="flex:1; height:{{ $incPct }}%; background:#6ddf6d; border-radius:4px 4px 0 0; min-height:2px;" title="Дохід: {{ number_format($data['income'], 0, '.', ' ') }}"></div>
            <div style="flex:1; height:{{ $expPct }}%; background:#f88; border-radius:4px 4px 0 0; min-height:2px;" title="Витрати: {{ number_format($data['expense'], 0, '.', ' ') }}"></div>
          </div>
          <div style="font-size:11px; opacity:.5;">{{ $label }}</div>
        </div>
      @endforeach
    </div>
    <div style="display:flex; gap:14px; margin-top:10px; font-size:12px; opacity:.6;">
      <span><span style="display:inline-block;width:10px;height:10px;background:#6ddf6d;border-radius:2px;margin-right:4px;"></span>Надходження</span>
      <span><span style="display:inline-block;width:10px;height:10px;background:#f88;border-radius:2px;margin-right:4px;"></span>Витрати</span>
    </div>
  </div>
  @endif

  {{-- ПРОЕКТИ ПО ВОРОНЦІ --}}
  <div class="card" style="margin-bottom:14px; padding:16px;">
    <div style="font-weight:600; margin-bottom:12px; opacity:.7; font-size:13px; text-transform:uppercase; letter-spacing:.05em;">Проекти по воронці</div>
    @php $totalProjects = array_sum(array_column($stages, 'count')); @endphp
    @foreach($stages as $stage)
      @if($stage['count'] > 0)
      @php $pct = $totalProjects > 0 ? round(($stage['count'] / $totalProjects) * 100) : 0; @endphp
      <div style="margin-bottom:10px;">
        <div style="display:flex; justify-content:space-between; font-size:14px; margin-bottom:4px;">
          <span>{{ $stage['label'] }}</span>
          <span style="font-weight:600;">{{ $stage['count'] }}</span>
        </div>
        <div style="height:6px; background:rgba(255,255,255,0.08); border-radius:3px;">
          <div style="height:100%; width:{{ $pct }}%; background:rgba(255,255,255,0.35); border-radius:3px;"></div>
        </div>
      </div>
      @endif
    @endforeach
    <div style="font-size:12px; opacity:.4; margin-top:8px;">Всього активних: {{ $totalProjects }}</div>
  </div>

  {{-- ОБЛАДНАННЯ: ПОТРІБНО vs СКЛАД --}}
  <div class="card" style="margin-bottom:14px; padding:16px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
      <div style="font-weight:600; opacity:.7; font-size:13px; text-transform:uppercase; letter-spacing:.05em;">Обладнання</div>
      <a href="/equipment-orders" style="font-size:12px; opacity:.5;">Замовлення →</a>
    </div>

    @php
      $equipRows = [
        ['icon'=>'☀️', 'label'=>'Фотомодулі'],
        ['icon'=>'⚡', 'label'=>'АКБ'],
        ['icon'=>'🔌', 'label'=>'Інвертори'],
      ];
    @endphp

    @foreach($equipBalance as $i => $eq)
    @php
      $diff = $eq['stock'] - $eq['needed'];
      $ok   = $diff >= 0;
    @endphp
    <div style="margin-bottom:14px;">
      <div style="display:flex; justify-content:space-between; align-items:baseline; margin-bottom:6px;">
        <span style="font-size:14px;">{{ $equipRows[$i]['icon'] }} {{ $eq['label'] }}</span>
        <span style="font-size:13px; font-weight:700; {{ $ok ? 'color:#6ddf6d;' : 'color:#f66;' }}">
          {{ $ok ? '+' : '' }}{{ number_format($diff, 0, '.', ' ') }}
        </span>
      </div>
      <div style="display:flex; gap:10px; font-size:12px; opacity:.55;">
        <span>Потрібно: <b style="opacity:1;">{{ number_format($eq['needed'], 0, '.', ' ') }}</b></span>
        <span>На складі: <b style="opacity:1;">{{ number_format($eq['stock'], 0, '.', ' ') }}</b></span>
      </div>
      @if(!$ok)
      <div style="margin-top:5px; font-size:12px; color:#f66;">
        ⚠ Не вистачає {{ number_format(abs($diff), 0, '.', ' ') }} шт — <a href="/equipment-orders" style="color:#f99;">переглянути замовлення</a>
      </div>
      @endif
    </div>
    @if($i < 2)<div style="border-top:1px solid rgba(255,255,255,0.07); margin-bottom:14px;"></div>@endif
    @endforeach
  </div>

  {{-- ПЕРЕКАЗИ --}}
  @if($pendingTransfers > 0)
  <div class="card" style="margin-bottom:14px; padding:16px; border:1px solid rgba(255,200,0,0.3);">
    <a href="/cash-transfers" style="display:flex; align-items:center; gap:10px; text-decoration:none;">
      <span style="font-size:22px;">⏳</span>
      <div>
        <div style="font-weight:600;">Очікують підтвердження</div>
        <div style="font-size:13px; opacity:.6;">Переказів: {{ $pendingTransfers }}</div>
      </div>
      <span style="margin-left:auto; opacity:.4;">→</span>
    </a>
  </div>
  @endif

</main>

@include('partials.nav.bottom')

@endsection
