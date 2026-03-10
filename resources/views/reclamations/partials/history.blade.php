<div class="history">
  @if($logs->isEmpty())
    <div class="muted">Історія поки порожня.</div>
  @else
    @foreach($logs as $log)
      @php
        $dt = $log->created_at?->format('d.m.Y H:i') ?? '';
        $who = $log->user?->name ?? '—';

        $step = $log->step_key ? $log->step_key : '—';

        $title = match($log->action){
          'step_update' => 'Оновлено етап',
          'file_upload' => 'Додано файл',
          default => $log->action,
        };
      @endphp

      <div class="history-item">
        <div class="history-left">
          <div class="history-title"><b>{{ $title }}</b> <span class="muted">• {{ $step }}</span></div>
          <div class="history-sub muted">{{ $dt }} • {{ $who }}</div>
        </div>

        <div class="history-right">
          @if(is_array($log->payload))
            @if(($log->action === 'file_upload') && !empty($log->payload['original_name']))
              <div class="mono">{{ $log->payload['original_name'] }}</div>
            @elseif(!empty($log->payload['ttn']))
              <div class="mono">TTN: {{ $log->payload['ttn'] }}</div>
            @elseif(!empty($log->payload['note']))
              <div>{{ $log->payload['note'] }}</div>
            @endif
          @endif
        </div>
      </div>
    @endforeach
  @endif
</div>
