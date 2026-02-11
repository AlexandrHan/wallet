@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="/css/stock.css?v={{ filemtime(public_path('css/stock.css')) }}">
@endpush

@section('content')

<main class="wrap stock-wrap">
        <div class="breadcrumb" style="margin-bottom:25px;">
        <a href="/stock" class="btn primary" style="width:30%">Склад</a>
        <button class="btn primary"
                onclick="window.location.href='/deliveries/create'">
            Створити нову партію
        </button>


        
    </div>


    <div class="card">
        <div style="font-weight:700; text-align:center; margin-bottom:20px;">
            
            Партії поставок
        </div>
            <hr>
        <div id="deliveriesList" class="delivery-list"></div>
    </div>

</main>

<script>

async function loadDeliveries() {

    const res = await fetch('/api/deliveries');
    const deliveries = await res.json();

    const list = document.getElementById('deliveriesList');
    list.innerHTML = '';

    deliveries.forEach(d => {

        list.innerHTML += `
            <div class="delivery-row"
                onclick="openDelivery(${d.id})">

                <div class="delivery-row-top">
                    Партія #${d.id}
                </div>

                <div class="delivery-row-bottom">
                    <div>
                        <span class="label">Статус</span>
                        <span class="value">${d.status.toUpperCase()}</span>
                    </div>

                    <div>
                        <span class="label">Дата</span>
                        <span class="value">${d.created_at.substring(0,10)}</span>
                    </div>
                </div>

            </div>
        `;

    });

}

loadDeliveries();

function openDelivery(id) {
    window.location.href = `/deliveries/${id}`;
}

async function createDelivery() {

    const res = await fetch('/api/deliveries', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            supplier_id: 1
        })
    });

    const data = await res.json();

    window.location.href = `/deliveries/${data.id}`;
}

</script>

@endsection
