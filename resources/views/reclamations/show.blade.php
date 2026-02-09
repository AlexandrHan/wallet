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

  </div>

  <div class="card client-card" id="clientCard" style="margin-top:14px; cursor:pointer;">

    <div class="reclam-row">
      <div class="muted">Клієнт</div>
      <div class="right client-meta">
        <div><b>{{ $reclamation->last_name }}</b></div>
      </div>
    </div>

    <div class="reclam-row">
      <div class="muted">Населений пункт</div>
      <div class="right client-meta">
        <div class="muted">{{ $reclamation->city }}</div>
      </div>
    </div>
    <div class="reclam-row">
      <div class="muted">Телефон</div>
      <div class="right client-meta">
        <div class="mono">{{ $reclamation->phone }}</div>
      </div>
    </div>

    <div class="reclam-row">
      <div class="muted">Серійник</div>
      <div class="right"><span class="mono">{{ $reclamation->serial_number }}</span></div>
    </div>

    @if($reclamation->problem)
      <div class="reclam-row">
        <div class="muted">Проблема</div>
        <div class="right wraptext">{{ $reclamation->problem }}</div>
      </div>
    @endif

    <div class="reclam-row">
      <div class="muted">Підмінний</div>
      <div class="right">
        <b>{{ $reclamation->has_loaner ? 'В наявності' : 'Відсутній' }}</b>
        @if(!$reclamation->has_loaner)
          <span class="red">• {{ $reclamation->loaner_ordered ? 'Замовили' : 'Не замовляли' }}</span>
        @endif
      </div>
    </div>
    @if(auth()->user()->role === 'owner')
    <form method="POST"
          action="{{ route('reclamations.destroy', $reclamation->id) }}"
          onsubmit="return confirm('Видалити рекламацію?')">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn del-rec" style="margin-top:15px;border:none;background:transparent;margin-left:42%;" data-delete-reclamation="{{ $reclamation->id }}">🗑</button>
    </form>
    @endif

  </div>
  <div id="clientHistory" class="card history-card hidden" style="margin-top:10px;"></div>


  <div class="card" style="margin-top:14px;">
    <div style="font-weight:900; margin-bottom:10px;">Етапи</div>

<div class="pipeline">  
@foreach($steps as $k => $label)
  @php
    $s = $reclamation->step($k);
    $isDone = $s && ($s->done_date || ($s->note && trim($s->note) !== '') || ($s->ttn && trim($s->ttn) !== '') || (is_array($s->files) && count($s->files)));
    $need = ($k === 'installed' && (!$s || !$s->note || trim($s->note) === '')); // "встановили" без комента = критично
    $cls = $need ? 'step-need' : ($isDone ? 'step-done' : 'step-empty');

    $badge = $need ? '⚠️ відсутній фідбек' : ($isDone ? '✅ виконано' : '⏳ очікує');
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


        <div class="step-photos" style="margin-top:10px; display:flex; gap:10px; flex-wrap:wrap;">
          @foreach($s->files as $path)
            @php $url = Storage::disk('public')->url($path); @endphp

            <a href="{{ $url }}" data-img-viewer style="display:block;">
              <img
                src="{{ $url }}"
                class="step-photo-preview"
                alt="Фото"
                style="width:84px;height:84px;object-fit:cover;border-radius:14px;border:1px solid rgba(255,255,255,.10);"
              >
            </a>
          @endforeach
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
        <input id="stepDate" class="btn btn-modal" type="date" />
      </div>

      <div id="ttnWrap" style="margin-top:10px;">
        <input id="stepTTN" class="btn btn-modal" placeholder="ТТН (якщо потрібно)" />
      </div>

      <textarea id="stepNote" class="btn btn-modal" placeholder="Коментар / опис" style="margin-top:10px; min-height:90px;"></textarea>

      <div id="stepExtra" style="margin-top:10px;"></div>

      <div class="row" style="margin-top:12px;">
       
        <button type="button" class="btn primary right" id="stepSave">Зберегти</button>
        <button type="button" class="btn" id="stepClose">Закрити</button> 
      </div>
    </div>
  </div>
</div>

@php
  $recPayload = [
    'reported_at' => optional($reclamation->reported_at)->format('Y-m-d'),
    'last_name' => $reclamation->last_name,
    'city' => $reclamation->city,
    'phone' => $reclamation->phone,
    'serial_number' => $reclamation->serial_number,
    'problem' => $reclamation->problem,
    'has_loaner' => (bool) $reclamation->has_loaner,
    'loaner_ordered' => (bool) $reclamation->loaner_ordered,
    'status' => $reclamation->status,
  ];

  $stepsPayload = $reclamation->steps
    ->keyBy('step_key')
    ->map(function ($s) {
      return [
        'done_date' => optional($s->done_date)->format('Y-m-d'),
        'ttn' => $s->ttn,
        'note' => $s->note,
        'files' => $s->files ?? [],
      ];
    })
    ->toArray();
@endphp


<script>
  window.RECL = {
    id: {{ $reclamation->id }},
    saveUrl: @json(route('reclamations.steps.save', ['reclamation'=>$reclamation->id, 'stepKey'=>'__STEP__'])),
    uploadUrl: @json(route('reclamations.upload', ['reclamation'=>$reclamation->id])),

    rec: @json($recPayload),
    steps: @json($stepsPayload),
  };
</script>


@endsection



<div id="imgViewer" class="img-viewer hidden" aria-hidden="true">
  <div class="img-viewer-backdrop"></div>

  <button type="button" class="img-viewer-close" aria-label="Закрити">✕</button>

  <img id="imgViewerImg" class="img-viewer-img" alt="Фото рекламації" />

  <div class="img-viewer-hint muted">Клік або Esc щоб закрити</div>
</div>
