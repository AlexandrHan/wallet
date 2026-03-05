@push('styles')
<link rel="stylesheet" href="/css/stock.css?v={{ filemtime(public_path('css/stock.css')) }}">
@endpush

@extends('layouts.app')

@section('content')
<main class="wrap stock-wrap {{ auth()->check() ? 'has-tg-nav' : '' }}">

  <div class="card" style="margin-top:14px;">
    <div style="font-weight:700; text-align:center;">Історія тижневих звітів</div>
    <div style="margin-top:8px; opacity:.78; text-align:center;">Дата звіту, товари, кількість, ціна та підсумкова сума</div>
  </div>

  @if(($reports ?? collect())->isEmpty())
    <div class="card" style="margin-top:14px; text-align:center; opacity:.75;">
      Звітів поки немає
    </div>
  @else
    @php
      $reportsByMonth = collect($reports)->groupBy(function ($report) {
        return \Carbon\Carbon::parse($report['report_date'])->format('Y-m');
      });
    @endphp

    @foreach($reportsByMonth as $monthKey => $monthReports)
      @php
        $monthDate = \Carbon\Carbon::createFromFormat('Y-m', $monthKey);
        $monthMap = [
          1 => 'Січень', 2 => 'Лютий', 3 => 'Березень', 4 => 'Квітень',
          5 => 'Травень', 6 => 'Червень', 7 => 'Липень', 8 => 'Серпень',
          9 => 'Вересень', 10 => 'Жовтень', 11 => 'Листопад', 12 => 'Грудень',
        ];
        $monthTitle = ($monthMap[(int)$monthDate->format('n')] ?? $monthDate->format('m')) . ' ' . $monthDate->format('Y');
        $monthTotal = collect($monthReports)->sum(fn($r) => (float)($r['report_total'] ?? 0));
      @endphp

      <div class="card" style="margin-top:14px;">
        <details class="stock-cat">
          <summary class="stock-cat-summary" style="list-style:none; cursor:pointer;">
            <div class="stock-cat-left" style="display:block;">
              <div class="stock-cat-title" style="font-weight:800;">🗓 {{ $monthTitle }}</div>
              <div style="margin-top:4px; opacity:.7; font-size:12px;">Звітів: {{ count($monthReports) }}</div>
            </div>
            <div class="stock-cat-right" style="font-weight:800;">{{ number_format((float)$monthTotal, 2, '.', ' ') }} $</div>
          </summary>

          <div class="stock-cat-body" style="margin-top:10px;">
            @foreach($monthReports as $report)
              @php
                $dateKey = $report['report_date'];
                $rows = $itemsByDate[$dateKey] ?? [];
              @endphp

              <div class="card" style="margin-bottom:10px;">
                <details class="stock-cat">
                  <summary class="stock-cat-summary" style="list-style:none; cursor:pointer;">
                    <div class="stock-cat-left" style="display:block;">
                      <div class="stock-cat-title" style="font-weight:800;">📅 Звіт за {{ \Carbon\Carbon::parse($dateKey)->format('d.m.Y') }}</div>
                      <div style="margin-top:4px; opacity:.7; font-size:12px;">Створено: {{ $report['report_created_at'] ?? '—' }}</div>
                    </div>
                    <div class="stock-cat-right" style="font-weight:800;">{{ number_format((float)($report['report_total'] ?? 0), 2, '.', ' ') }} $</div>
                  </summary>

                  <div class="stock-cat-body" style="margin-top:10px;">
                    @foreach($rows as $row)
                      <div class="delivery-row" style="margin-bottom:10px;">
                        <div class="delivery-row-top">{{ $row['product_name'] }}</div>
                        <div class="delivery-row-bottom">
                          <div class="kv"><span class="label">К-сть</span><span class="value">{{ $row['qty'] }}</span></div>
                          <div class="kv"><span class="label">Ціна</span><span class="value">{{ number_format((float)$row['unit_price'], 2, '.', ' ') }} $</span></div>
                          <div class="kv"><span class="label">Сума</span><span class="value">{{ number_format((float)$row['total'], 2, '.', ' ') }} $</span></div>
                        </div>
                      </div>
                    @endforeach
                  </div>
                </details>
              </div>
            @endforeach
          </div>
        </details>
      </div>
    @endforeach
  @endif

</main>

@include('partials.nav.bottom')
@endsection
