@push('styles')
<link rel="stylesheet" href="/css/nav-telegram.css?v={{ filemtime(public_path('css/nav-telegram.css')) }}">
<style>
  .amo-advance-report {
    background:
      radial-gradient(circle at top right, rgba(255, 200, 87, 0.16), transparent 34%),
      radial-gradient(circle at bottom left, rgba(76, 201, 240, 0.14), transparent 38%),
      linear-gradient(145deg, rgba(255,255,255,0.06), rgba(255,255,255,0.02));
    border: 1px solid rgba(255,255,255,0.08);
    overflow: hidden;
  }
  .amo-advance-summary {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 14px;
  }
  .amo-advance-pill {
    padding: 8px 12px;
    border-radius: 999px;
    background: rgba(255,255,255,0.08);
    font-size: 12px;
    opacity: .82;
  }
  .amo-advance-currency {
    margin-top: 16px;
    padding: 14px;
    border-radius: 18px;
    background: rgba(10,16,24,0.28);
    border: 1px solid rgba(255,255,255,0.06);
    backdrop-filter: blur(10px);
  }
  .amo-advance-kpis {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
    margin: 14px 0;
  }
  .amo-advance-kpi {
    padding: 14px;
    border-radius: 16px;
    background: rgba(255,255,255,0.06);
  }
  .amo-advance-progress {
    height: 14px;
    border-radius: 999px;
    background: rgba(255,255,255,0.08);
    overflow: hidden;
    display: flex;
    margin-bottom: 8px;
  }
  .amo-advance-progress__advance {
    background: linear-gradient(90deg, #2dd4bf, #22c55e);
  }
  .amo-advance-progress__remaining {
    background: linear-gradient(90deg, #fb923c, #f97316);
  }
  .amo-advance-stage-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    margin-top: 14px;
  }
  .amo-advance-stage {
    padding: 12px;
    border-radius: 16px;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.06);
  }
  .amo-advance-stage__bar {
    height: 8px;
    border-radius: 999px;
    background: rgba(255,255,255,0.08);
    overflow: hidden;
    margin-top: 10px;
  }
  .amo-advance-stage__bar > span {
    display: block;
    height: 100%;
    background: linear-gradient(90deg, #38bdf8, #22c55e);
    border-radius: 999px;
  }
  @media (max-width: 640px) {
    .amo-advance-kpis,
    .amo-advance-stage-grid {
      grid-template-columns: 1fr;
    }
    .analytics-profit-grid {
      grid-template-columns: 1fr !important;
    }
  }
</style>
@endpush

@extends('layouts.app')

@section('content')

<main class="wrap has-tg-nav" style="padding:16px; max-width:680px; margin:0 auto; margin-bottom:5rem;">

  <div style="font-weight:700; font-size:18px; margin-bottom:18px; text-align:center;">
    📊 Аналітика
  </div>

  {{-- ── ФІНАНСОВА АНАЛІТИКА ЗА МІСЯЦЬ ─────────────────────────────── --}}
  <div class="card" id="monthAnalyticsCard" style="margin-bottom:14px; padding:16px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; gap:10px; flex-wrap:wrap;">
      <div style="font-weight:600; opacity:.7; font-size:13px; text-transform:uppercase; letter-spacing:.05em;">💰 Доходи / Витрати / Прибуток</div>
      <input
        type="month"
        id="analyticsMonthPicker"
        class="btn"
        style="padding:6px 12px; font-size:13px; width:auto;"
        value="{{ now()->format('Y-m') }}"
      >
    </div>

    <div id="analyticsBody" style="min-height:60px;">
      <div style="opacity:.5; font-size:13px;">Завантаження…</div>
    </div>
  </div>

  <script>
  (function () {
    const picker  = document.getElementById('analyticsMonthPicker');
    const body    = document.getElementById('analyticsBody');
    const fmtNum  = (n) => new Intl.NumberFormat('uk-UA', { maximumFractionDigits: 2 }).format(n);
    const SIGNS   = { UAH: '₴', USD: '$', EUR: '€' };

    function sign(cur) { return SIGNS[cur] ?? cur; }

    function renderAnalytics(data) {
      const curs = data.currencies || [];
      if (!curs.length) {
        body.innerHTML = '<div style="opacity:.5; font-size:13px; text-align:center;">Немає проводок за цей місяць</div>';
        return;
      }

      const html = curs.map(c => {
        const profitColor = c.profit >= 0 ? '#4ade80' : '#f87171';
        const profitSign  = c.profit >= 0 ? '+' : '';
        return `
          <div style="margin-bottom:12px;">
            <div style="font-size:12px; font-weight:700; opacity:.55; margin-bottom:8px; letter-spacing:.04em;">${c.currency}</div>
            <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:8px;" class="analytics-profit-grid">
              <div style="background:rgba(74,222,128,0.08); border-radius:14px; padding:12px;">
                <div style="font-size:11px; opacity:.5; margin-bottom:4px;">Доходи</div>
                <div style="font-size:16px; font-weight:700; color:#4ade80;">${fmtNum(c.income)} ${sign(c.currency)}</div>
              </div>
              <div style="background:rgba(248,113,113,0.08); border-radius:14px; padding:12px;">
                <div style="font-size:11px; opacity:.5; margin-bottom:4px;">Витрати</div>
                <div style="font-size:16px; font-weight:700; color:#f87171;">${fmtNum(c.expense)} ${sign(c.currency)}</div>
              </div>
              <div style="background:rgba(255,255,255,0.05); border-radius:14px; padding:12px;">
                <div style="font-size:11px; opacity:.5; margin-bottom:4px;">Прибуток</div>
                <div style="font-size:16px; font-weight:700; color:${profitColor};">${profitSign}${fmtNum(c.profit)} ${sign(c.currency)}</div>
              </div>
            </div>
          </div>`;
      }).join('');

      body.innerHTML = html;
    }

    function loadAnalytics(month) {
      body.innerHTML = '<div style="opacity:.5; font-size:13px;">Завантаження…</div>';
      fetch('/api/finance/analytics?month=' + encodeURIComponent(month), { credentials: 'same-origin' })
        .then(r => r.json())
        .then(renderAnalytics)
        .catch(() => {
          body.innerHTML = '<div style="color:#f87171; font-size:13px;">Помилка завантаження</div>';
        });
    }

    picker.addEventListener('change', function () {
      loadAnalytics(this.value);
    });

    loadAnalytics(picker.value);
  })();
  </script>

  {{-- ФІНАНСОВИЙ ЗВІТ АВАНСІВ З AMO CRM --}}
  <div class="card amo-advance-report" style="margin-bottom:14px; padding:16px;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px; margin-bottom:12px;">
      <div>
        <div style="font-weight:600; opacity:.7; font-size:13px; text-transform:uppercase; letter-spacing:.05em;">Фінансовий звіт авансів з AMO CRM</div>
        <div style="font-size:14px; opacity:.72; margin-top:6px;">Сума проектів від етапу "Частично оплатил" до фінальних етапів, аванси з amoCRM і залишок до оплати.</div>
      </div>
    </div>

    <div class="amo-advance-summary">
      <div class="amo-advance-pill">Проектів у звіті: <b>{{ number_format($amoAdvanceReport['total_projects'] ?? 0, 0, '.', ' ') }}</b></div>
      <div class="amo-advance-pill">Етапів враховано: <b>{{ number_format($amoAdvanceReport['stage_count'] ?? 0, 0, '.', ' ') }}</b></div>
      <div class="amo-advance-pill">Валют у звіті: <b>{{ number_format(count($amoAdvanceReport['currencies'] ?? []), 0, '.', ' ') }}</b></div>
    </div>

    @php
      $moneySigns = ['USD' => '$', 'UAH' => '₴', 'EUR' => '€'];
      $formatAmoMoney = function ($amount, $currency) use ($moneySigns) {
          $sign = $moneySigns[$currency] ?? $currency;
          return number_format((float) $amount, 0, '.', ' ') . ' ' . $sign;
      };
    @endphp

    @forelse(($amoAdvanceReport['currencies'] ?? []) as $currencyReport)
      @php
        $currency = $currencyReport['currency'];
        $advancePercent = max(0, min(100, (int) ($currencyReport['completion_percent'] ?? 0)));
        $remainingPercent = max(0, 100 - $advancePercent);
        $currencyColor = match($currency) {
          'USD' => '#fbbf24',
          'EUR' => '#60a5fa',
          default => '#34d399',
        };
      @endphp
      <div class="amo-advance-currency">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
          <div style="display:flex; align-items:center; gap:10px;">
            <div style="width:12px; height:12px; border-radius:999px; background:{{ $currencyColor }};"></div>
            <div style="font-size:18px; font-weight:700;">{{ $currency }}</div>
          </div>
          <div style="font-size:12px; opacity:.68;">Проектів: {{ number_format($currencyReport['projects'], 0, '.', ' ') }}</div>
        </div>

        <div class="amo-advance-kpis">
          <div class="amo-advance-kpi">
            <div style="font-size:12px; opacity:.58; margin-bottom:6px;">Загальна сума проектів</div>
            <div style="font-size:22px; font-weight:700;">{{ $formatAmoMoney($currencyReport['total_amount'], $currency) }}</div>
          </div>
          <div class="amo-advance-kpi">
            <div style="font-size:12px; opacity:.58; margin-bottom:6px;">Аванси з AMO CRM</div>
            <div style="font-size:22px; font-weight:700; color:#4ade80;">{{ $formatAmoMoney($currencyReport['advance_amount'], $currency) }}</div>
          </div>
          <div class="amo-advance-kpi">
            <div style="font-size:12px; opacity:.58; margin-bottom:6px;">Залишок до оплати</div>
            <div style="font-size:22px; font-weight:700; color:#fb923c;">{{ $formatAmoMoney($currencyReport['remaining_amount'], $currency) }}</div>
          </div>
        </div>

        <div class="amo-advance-progress" aria-hidden="true">
          <div class="amo-advance-progress__advance" style="width:{{ $advancePercent }}%;"></div>
          <div class="amo-advance-progress__remaining" style="width:{{ $remainingPercent }}%;"></div>
        </div>
        <div style="display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap; font-size:12px; opacity:.68;">
          <span>Проавансовано: {{ $advancePercent }}%</span>
          <span>Залишилось: {{ $remainingPercent }}%</span>
        </div>

        <div class="amo-advance-stage-grid">
          @foreach($currencyReport['stages'] as $stageReport)
            @continue(($stageReport['projects'] ?? 0) === 0)
            <div class="amo-advance-stage">
              <div style="display:flex; justify-content:space-between; gap:10px; align-items:flex-start;">
                <div style="font-size:14px; font-weight:600;">{{ $stageReport['label'] }}</div>
                <div style="font-size:12px; opacity:.62;">{{ number_format($stageReport['projects'], 0, '.', ' ') }} пр.</div>
              </div>
              <div style="margin-top:8px; font-size:12px; opacity:.6;">Сума: {{ $formatAmoMoney($stageReport['total_amount'], $currency) }}</div>
              <div style="margin-top:3px; font-size:12px; color:#4ade80;">Аванси: {{ $formatAmoMoney($stageReport['advance_amount'], $currency) }}</div>
              <div style="margin-top:3px; font-size:12px; color:#fb923c;">Залишок: {{ $formatAmoMoney($stageReport['remaining_amount'], $currency) }}</div>
              <div class="amo-advance-stage__bar"><span style="width:{{ max(0, min(100, (int) ($stageReport['completion_percent'] ?? 0))) }}%;"></span></div>
            </div>
          @endforeach
        </div>
      </div>
    @empty
      <div style="padding:14px; border-radius:16px; background:rgba(255,255,255,0.05); font-size:14px; opacity:.72;">
        Немає amoCRM-проектів у вибраних етапах для побудови звіту.
      </div>
    @endforelse
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
