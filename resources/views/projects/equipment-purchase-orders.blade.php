@extends('layouts.app')

@section('title', 'Замовлення обладнання')

@push('styles')
<style>
.epo-wrap {
  width: 100%;
  max-width: 100%;
  margin: 0 auto;
  padding: 16px 14px 76px;
  box-sizing: border-box;
}

.epo-page-title {
  font-size: 20px;
  font-weight: 700;
  color: var(--text, #e9eef6);
  margin: 0 0 4px;
}

.epo-page-sub {
  font-size: 13px;
  color: var(--muted, #9aa6bc);
  margin: 0 0 22px;
}

.epo-section {
  background: rgba(255,255,255,.04);
  border: 1px solid rgba(255,255,255,.08);
  border-radius: 16px;
  margin-bottom: 16px;
  overflow: hidden;
}

.epo-section-head {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px 16px 12px;
  border-bottom: 1px solid rgba(255,255,255,.06);
}

.epo-section-icon {
  font-size: 20px;
  line-height: 1;
}

.epo-section-title {
  font-size: 15px;
  font-weight: 700;
  color: var(--text, #e9eef6);
  flex: 1;
}

.epo-badge {
  min-width: 22px;
  height: 22px;
  padding: 0 7px;
  background: #e53e3e;
  color: #fff;
  border-radius: 99px;
  font-size: 11px;
  font-weight: 800;
  line-height: 22px;
  text-align: center;
}

.epo-badge--ok {
  background: rgba(102,242,168,.15);
  color: #66f2a8;
}

.epo-table {
  width: 100%;
  border-collapse: collapse;
  margin: 0;
}

.epo-table thead th {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: var(--muted, #9aa6bc);
  padding: 10px 16px 8px;
  text-align: left;
  border-bottom: 1px solid rgba(255,255,255,.05);
}

.epo-table thead th:last-child { text-align: right; }
.epo-table thead th:nth-child(2) { text-align: center; }

.epo-table tbody tr {
  border-bottom: 1px solid rgba(255,255,255,.04);
}

.epo-table tbody tr:last-child { border-bottom: none; }

.epo-table tbody td {
  padding: 10px 16px;
  font-size: 13px;
  color: var(--text, #e9eef6);
  vertical-align: middle;
}

.epo-table tbody td:nth-child(2) {
  text-align: center;
  font-weight: 700;
  color: #ff6b6b;
  font-size: 15px;
}

.epo-table tbody td:last-child {
  text-align: right;
  white-space: nowrap;
}

.epo-date {
  display: inline-block;
  background: rgba(251,191,36,.12);
  color: #fbbf24;
  border-radius: 8px;
  padding: 3px 8px;
  font-size: 12px;
  font-weight: 600;
}

.epo-date--none {
  color: var(--muted, #9aa6bc);
  font-size: 12px;
}

.epo-empty {
  padding: 14px 16px;
  font-size: 13px;
  color: var(--muted, #9aa6bc);
  text-align: center;
}

.epo-additional-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 16px;
  border-bottom: 1px solid rgba(255,255,255,.04);
  font-size: 13px;
  color: var(--text, #e9eef6);
}

.epo-additional-row:last-child { border-bottom: none; }

.epo-stock-pill {
  background: rgba(102,242,168,.12);
  color: #66f2a8;
  border-radius: 8px;
  padding: 3px 10px;
  font-size: 12px;
  font-weight: 700;
}

.epo-stock-pill--zero {
  background: rgba(255,107,107,.12);
  color: #ff6b6b;
}

.epo-updated {
  font-size: 11px;
  color: var(--muted, #9aa6bc);
  text-align: center;
  margin-top: 8px;
  padding-bottom: 4px;
}

@media (min-width: 769px) {
  .epo-wrap {
    padding-left: 0;
    padding-right: 0;
  }

  .epo-section {
    margin-bottom: 14px;
  }

  .epo-table tbody tr:last-child td {
    padding-bottom: 10px;
  }
}
</style>
@endpush

@section('content')
<div class="epo-wrap">
  <div class="epo-page-title">🛒 Замовлення обладнання</div>
  <div class="epo-page-sub">Нестача по активних проектах · відсортовано за першою датою</div>

  {{-- ⚡ ІНВЕРТОРИ --}}
  <div class="epo-section">
    <div class="epo-section-head">
      <div class="epo-section-icon">🔌</div>
      <div class="epo-section-title">Інвертори</div>
      @if(count($inverterRows) > 0)
        <div class="epo-badge">{{ count($inverterRows) }}</div>
      @else
        <div class="epo-badge epo-badge--ok">✓</div>
      @endif
    </div>

    @if(count($inverterRows) > 0)
      <table class="epo-table">
        <thead>
          <tr>
            <th>Модель</th>
            <th>Не вистачає</th>
            <th>Перша дата нестачі</th>
          </tr>
        </thead>
        <tbody>
          @foreach($inverterRows as $row)
            <tr>
              <td>{{ $row['name'] }}</td>
              <td>{{ $row['shortage'] }} шт</td>
              <td>
                @if($row['first_date'])
                  <span class="epo-date">{{ \Carbon\Carbon::parse($row['first_date'])->format('d.m.Y') }}</span>
                @else
                  <span class="epo-date--none">—</span>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @else
      <div class="epo-empty">Нестачі немає — всі інвертори є на складі</div>
    @endif
  </div>

  {{-- 🔋 АКБ --}}
  <div class="epo-section">
    <div class="epo-section-head">
      <div class="epo-section-icon">🔋</div>
      <div class="epo-section-title">Акумуляторні батареї (АКБ)</div>
      @if(count($batteryRows) > 0)
        <div class="epo-badge">{{ count($batteryRows) }}</div>
      @else
        <div class="epo-badge epo-badge--ok">✓</div>
      @endif
    </div>

    @if(count($batteryRows) > 0)
      <table class="epo-table">
        <thead>
          <tr>
            <th>Модель</th>
            <th>Не вистачає</th>
            <th>Перша дата нестачі</th>
          </tr>
        </thead>
        <tbody>
          @foreach($batteryRows as $row)
            <tr>
              <td>{{ $row['name'] }}</td>
              <td>{{ $row['shortage'] }} шт</td>
              <td>
                @if($row['first_date'])
                  <span class="epo-date">{{ \Carbon\Carbon::parse($row['first_date'])->format('d.m.Y') }}</span>
                @else
                  <span class="epo-date--none">—</span>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @else
      <div class="epo-empty">Нестачі немає — всі АКБ є на складі</div>
    @endif
  </div>

  {{-- 🔧 ДОДАТКОВЕ ОБЛАДНАННЯ --}}
  <div class="epo-section">
    <div class="epo-section-head">
      <div class="epo-section-icon">🔧</div>
      <div class="epo-section-title">Додаткове обладнання</div>
    </div>
    @foreach($additionalRows as $item)
      <div class="epo-additional-row">
        <span>{{ $item['name'] }}</span>
        <span class="epo-stock-pill {{ $item['stock'] == 0 ? 'epo-stock-pill--zero' : '' }}">
          {{ $item['stock'] }} {{ $item['unit'] }}
        </span>
      </div>
    @endforeach
  </div>

  <div class="epo-updated">Оновлено: {{ now()->format('d.m.Y H:i') }}</div>
</div>

@include('partials.nav.bottom')
@endsection
