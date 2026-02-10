@extends('layouts.app')

@section('content')

<main class="wrap">

<main class="wrap">

    <div style="margin-bottom:12px;">
        <a href="/stock" class="btn">← Назад до складу</a>
    </div>

    <div class="card">
        <div style="font-size:18px; font-weight:700;">
            Партії поставки
        </div>

        <div style="margin-top:14px;">
            <a href="/deliveries/create" class="btn">
                + Нова партія
            </a>
        </div>
    </div>

    <div class="card" style="margin-top:16px;">
        <div style="font-weight:700; margin-bottom:10px;">
            Список партій
        </div>

        <table style="width:100%;">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Status</th>
                    <th>Дата</th>
                </tr>
            </thead>
            <tbody id="deliveriesTable"></tbody>
        </table>
    </div>

</main>

<script>
async function loadDeliveries() {

    const res = await fetch('/api/deliveries');
    const data = await res.json();

    const table = document.getElementById('deliveriesTable');
    table.innerHTML = '';

    data.forEach(d => {
        table.innerHTML += `
            <tr onclick="window.location='/deliveries/${d.id}'" style="cursor:pointer">
                <td>#${d.id}</td>
                <td>${d.status}</td>
                <td>${d.created_at}</td>
            </tr>
        `;
    });
}

loadDeliveries();
</script>


</main>

@endsection
