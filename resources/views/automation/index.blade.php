@extends('layouts.app')

@section('content')
<div class="wrap">
    <div class="card">
        <h2>⚙️ Automation Center</h2>

        <button id="runFxBtn" class="btn">
            🔄 Запустити FX оновлення
        </button>

        <div id="statusBox" class="card" style="display:none; margin-top:15px; padding:12px;"></div>

        <hr style="margin:20px 0;">

        <h3>📜 Останні запускі</h3>

        <table style="width:100%; font-size:14px;">
            <thead>
                <tr>
                    <th align="left">Час</th>
                    <th align="left">Користувач</th>
                    <th align="left">Дія</th>
                    <th align="left">Статус</th>
                    <th align="left">Повідомлення</th>
                </tr>
            </thead>
            <tbody id="automationLogsBody">
                @foreach($logs as $log)
                    <tr>
                        <td>{{ optional($log->created_at)->format('d.m.Y H:i:s') }}</td>
                        <td>{{ optional($log->user)->name ?: optional($log->user)->email ?: ('User #' . $log->user_id) }}</td>
                        <td>{{ $log->action }}</td>
                        <td>
                            @if($log->status === 'success')
                                <span style="color:green;">✔ success</span>
                            @else
                                <span style="color:red;">✖ error</span>
                            @endif
                        </td>
                        <td>{{ $log->message }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<script>
function renderAutomationLogs(logs) {
    const tbody = document.getElementById('automationLogsBody');
    if (!tbody) return;

    tbody.innerHTML = '';

    (logs || []).forEach(log => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${log.created_at || ''}</td>
            <td>${log.user || ''}</td>
            <td>${log.action || ''}</td>
            <td>${log.status === 'success'
                ? '<span style="color:green;">✔ success</span>'
                : '<span style="color:red;">✖ error</span>'}</td>
            <td>${log.message || ''}</td>
        `;
        tbody.appendChild(tr);
    });
}

function renderAutomationStatus(result) {
    const box = document.getElementById('statusBox');
    if (!box) return;

    const ok = !!result?.ok;
    box.style.display = 'block';
    box.style.border = ok ? '2px solid #3bc97f' : '2px solid #ff6b6b';
    box.style.color = ok ? '#3bc97f' : '#ff6b6b';
    box.innerHTML = `
        <div style="font-weight:800; font-size:15px;">
            ${ok ? '✔ Успішно' : '✖ Помилка'}
        </div>
        <div style="margin-top:6px; font-size:13px; color:#e9eef6;">
            ${ok ? 'FX оновлення виконано' : (result?.body || result?.message || 'Не вдалося виконати FX оновлення')}
        </div>
        ${result?.status ? `<div style="margin-top:4px; font-size:12px; opacity:.8; color:#e9eef6;">HTTP: ${result.status}</div>` : ''}
    `;
}

document.getElementById('runFxBtn').addEventListener('click', function() {

    fetch("{{ route('automation.fx') }}", {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(r => r.json())
    .then(data => {
        renderAutomationStatus(data.result ?? data);

        if (Array.isArray(data.logs)) {
            renderAutomationLogs(data.logs);
        }
    });

});
</script>

@include('partials.nav.bottom')
@endsection
