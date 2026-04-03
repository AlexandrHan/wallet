@push('styles')
  <link rel="stylesheet" href="/css/nav-telegram.css?v={{ filemtime(public_path('css/nav-telegram.css')) }}">
  <link rel="stylesheet" href="/css/project.css?v={{ filemtime(public_path('css/project.css')) }}">
@endpush

@extends('layouts.app')

@section('content')
<main style="padding:0 0 100px;">

  <div class="projects-title-card">
    <div class="projects-title">
      <div style="font-size:13px; opacity:.55; margin-bottom:2px;">🔐 Тільки для Глущенко</div>
      <div>🚨 Підозрілі дії</div>
    </div>
  </div>

  @php
    $totalCount = collect($grouped)->flatten(1)->flatten(1)->count();
    $actorCounts = [];
    foreach ($grouped as $actor => $categories) {
      $cnt = 0;
      foreach ($categories as $items) $cnt += count($items);
      $actorCounts[$actor] = $cnt;
    }
    arsort($actorCounts); // highest count first
  @endphp

  <div style="padding:0 16px 8px;">
    <div style="font-size:13px; opacity:.55; text-align:center;">
      Всього подій: <strong style="opacity:1;">{{ $totalCount }}</strong> • Співробітників: <strong style="opacity:1;">{{ count($grouped) }}</strong>
    </div>
  </div>

  @if(empty($grouped))
    <div class="card" style="text-align:center; opacity:.6; font-size:14px;">
      Підозрілих дій не знайдено 🎉
    </div>
  @else

    @foreach($actorCounts as $actor => $actorTotal)
      @php $categories = $grouped[$actor]; @endphp

      <div class="card" style="margin-bottom:12px; padding:0; overflow:hidden;">

        {{-- Actor header (accordion toggle) --}}
        <details class="suspicious-actor-details">
          <summary class="suspicious-actor-summary">
            <div style="display:flex; justify-content:space-between; align-items:center; width:100%;">
              <div>
                <span style="font-weight:800; font-size:15px;">{{ $actor }}</span>
                <span style="font-size:12px; opacity:.5; margin-left:8px;">{{ $actorTotal }} {{ $actorTotal === 1 ? 'подія' : ($actorTotal < 5 ? 'події' : 'подій') }}</span>
              </div>
              <span class="suspicious-chevron">▼</span>
            </div>
          </summary>

          <div style="padding:0 16px 12px;">

            @foreach($categories as $category => $events)
              {{-- Category sub-accordion --}}
              <details class="suspicious-cat-details" open>
                <summary class="suspicious-cat-summary">
                  <div style="display:flex; justify-content:space-between; align-items:center; width:100%;">
                    <span style="font-size:13px; font-weight:700;">
                      @php
                        $catIcon = match($category) {
                          'Редагування суми'        => '✏️',
                          'Видалення операції'      => '🗑',
                          'Зміна валюти авансу'     => '🔄',
                          'Скасування авансу'       => '❌',
                          'Виправлення валюти авансу' => '🔄',
                          'Видалення проекту'       => '🗑',
                          default                   => '⚠️',
                        };
                      @endphp
                      {{ $catIcon }} {{ $category }}
                    </span>
                    <span style="font-size:11px; background:rgba(229,62,62,.2); color:#f88;
                      padding:2px 8px; border-radius:99px; font-weight:700; white-space:nowrap;">
                      {{ count($events) }}
                    </span>
                  </div>
                </summary>

                <div style="margin-top:6px;">
                  @foreach(array_reverse($events) as $ev)
                    <div style="padding:10px 0 10px; border-bottom:1px solid rgba(255,255,255,.05);">
                      <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:8px;">
                        <div style="flex:1; min-width:0;">
                          {{-- Parse message lines --}}
                          @php
                            $lines = explode("\n", trim($ev['message']));
                            $displayLines = [];
                            foreach ($lines as $line) {
                              $line = trim($line);
                              if ($line === '') continue;
                              // Skip the "Хто:" line since actor is the accordion header
                              if (str_starts_with($line, '👤 Хто:')) continue;
                              $displayLines[] = $line;
                            }
                          @endphp
                          @foreach($displayLines as $line)
                            <div style="font-size:13px; line-height:1.6;">{{ $line }}</div>
                          @endforeach
                        </div>
                        <div style="font-size:11px; opacity:.4; white-space:nowrap; padding-top:2px; text-align:right;">
                          {{ \Carbon\Carbon::parse($ev['created_at'])->format('d.m.y H:i') }}
                        </div>
                      </div>
                    </div>
                  @endforeach
                </div>

              </details>
            @endforeach

          </div>
        </details>

      </div>
    @endforeach

  @endif

</main>

<style>
.suspicious-actor-details {
  width: 100%;
}
.suspicious-actor-summary {
  list-style: none;
  display: flex;
  align-items: center;
  padding: 14px 16px;
  cursor: pointer;
  user-select: none;
  border-bottom: 1px solid rgba(255,255,255,.06);
}
.suspicious-actor-summary::-webkit-details-marker { display: none; }

.suspicious-actor-details[open] .suspicious-chevron { transform: rotate(180deg); }
.suspicious-chevron {
  font-size: 11px;
  opacity: .45;
  transition: transform .2s;
  display: inline-block;
  margin-left: 8px;
  flex-shrink: 0;
}

.suspicious-cat-details {
  margin-top: 10px;
  border-radius: 8px;
  background: rgba(229,62,62,.04);
  border: 1px solid rgba(229,62,62,.12);
  overflow: hidden;
}
.suspicious-cat-summary {
  list-style: none;
  display: flex;
  align-items: center;
  padding: 10px 12px;
  cursor: pointer;
  user-select: none;
}
.suspicious-cat-summary::-webkit-details-marker { display: none; }
.suspicious-cat-details > div {
  padding: 0 12px;
}
</style>

<script>
// Prevent accordions from toggling when clicking inside content (only summary toggles)
document.querySelectorAll('.suspicious-actor-details, .suspicious-cat-details').forEach(details => {
  details.addEventListener('click', function(e) {
    // Only allow toggling via the summary element
    const summary = this.querySelector(':scope > summary');
    if (summary && !summary.contains(e.target)) {
      e.stopPropagation();
    }
  });
});
</script>

@include('partials.nav.bottom')
@endsection
