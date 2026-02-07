@extends('layouts.app')
@section('content')
@php
  $steps = [
    'reported' => 'Дані клієнта',
    'dismantled' => 'Демонтували',
    'where_left' => 'Де залишили',
    'shipped_to_service' => 'Відправили НП на ремонт',
    'service_received' => 'Сервіс отримав',
    'repaired_shipped_back' => 'Відремонтували та відправили',
    'installed' => 'Встановили',
    'loaner_return' => 'Повернення підмінного',
    'closed' => 'Завершили',
  ];
@endphp

<main class="wrap page reclamations-show" data-reclamation-id="{{ $reclamation->id }}">

  <div class="row content">
    
    <a href="{{ route('reclamations.index') }}" class="btn right back">← Назад</a>
    <div style="font-weight:900;">{{ $reclamation->code }}</div>
  </div>

  <div class="card" style="margin-top:14px;">
    <div class="reclam-row">
      <div class="muted">Клієнт</div>
      <div class="right"><b>{{ $reclamation->last_name }}</b> • {{ $reclamation->city }} • {{ $reclamation->phone }}</div>
    </div>
    <div class="reclam-row">
      <div class="muted">Серійник</div>
      <div class="right"><span class="mono">{{ $reclamation->serial_number }}</span></div>
    </div>
    @if($reclamation->problem)
      <div class="reclam-row">
        <div class="muted">Проблема</div>
        <div class="right">{{ $reclamation->problem }}</div>
      </div>
    @endif

    <div class="reclam-row">
      <div class="muted">Підмінний</div>
      <div class="right">
        <b>{{ $reclamation->has_loaner ? 'Є' : 'Нема' }}</b>
        @if(!$reclamation->has_loaner)
          <span class="muted">• {{ $reclamation->loaner_ordered ? 'замовили' : 'не замовляли' }}</span>
        @endif
      </div>
    </div>
  </div>

  <div class="card" style="margin-top:14px;">
    <div style="font-weight:900; margin-bottom:10px;">Етапи</div>

<div class="pipeline">  
@foreach($steps as $k => $label)
  @php
    $s = $reclamation->step($k);
    $isDone = $s && ($s->done_date || ($s->note && trim($s->note) !== '') || ($s->ttn && trim($s->ttn) !== '') || (is_array($s->files) && count($s->files)));
    $need = ($k === 'installed' && (!$s || !$s->note || trim($s->note) === '')); // "встановили" без комента = критично
    $cls = $need ? 'step-need' : ($isDone ? 'step-done' : 'step-empty');

    $badge = $need ? '⚠️ треба' : ($isDone ? '✅ готово' : '⏳ очікує');
    $sub = $s?->done_date
  ? $s->done_date->format('d.m.Y')
  : ($isDone ? 'заповнено' : 'натисни щоб заповнити');

  @endphp

  <div class="step {{ $cls }}" data-step="{{ $k }}">
  <div class="step-line"><div class="step-dot"></div></div>
  <div class="step-card">

    <div class="step-head">
      <div class="step-left">
        <div class="step-title">{{ $label }}</div>
        <div class="step-sub">{{ $sub }}</div>
      </div>

      <div class="step-right">
        <div class="step-badge">{{ $badge }}</div>

      </div>
    </div>

    <div class="step-body">
      @if($s?->ttn)
        <div class="step-row">
          <div class="muted">ТТН</div>
          <div class="right"><span class="mono">{{ $s->ttn }}</span></div>
        </div>
      @endif

      @if($s?->note)
        <div class="muted">{{ $s->note }}</div>
      @endif

      @if($s?->files && count($s->files))
        <div class="step-row">
          <div class="muted">Файли</div>
          <div class="right"><b>{{ count($s->files) }}</b></div>
        </div>
      @endif
    </div>
    </div>
  </div>
@endforeach
</div>

  </div>
</main>

{{-- модалка редагування етапу --}}
<div id="stepModal" class="modal hidden">
  <div class="modal-backdrop"></div>
  <div class="modal-panel">
    <div class="modal-handle"></div>
    <div class="modal-header">
      <div class="modal-title" id="stepTitle">Етап</div>
    </div>

    <div class="modal-body">
      <div id="dateWrap">
        <input id="stepDate" class="btn" type="date" />
      </div>

      <div id="ttnWrap" style="margin-top:10px;">
        <input id="stepTTN" class="btn" placeholder="ТТН (якщо потрібно)" />
      </div>

      <textarea id="stepNote" class="btn" placeholder="Коментар / опис" style="margin-top:10px; min-height:90px;"></textarea>

      <div id="stepExtra" style="margin-top:10px;"></div>

      <div class="row" style="margin-top:12px;">
       
        <button type="button" class="btn primary right" id="stepSave">Зберегти</button>
        <button type="button" class="btn" id="stepClose">Закрити</button> 
      </div>
    </div>
  </div>
</div>

<script>
  window.RECL = {
    id: {{ $reclamation->id }},
    saveUrl: @json(route('reclamations.steps.save', ['reclamation'=>$reclamation->id, 'stepKey'=>'__STEP__'])),
    uploadUrl: @json(route('reclamations.upload', ['reclamation'=>$reclamation->id])),
  };
</script>
@endsection
