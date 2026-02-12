@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="/css/stock.css?v={{ filemtime(public_path('css/stock.css')) }}">
@endpush

@section('content')

<main class="wrap stock-wrap">
    <div class="breadcrumb" style="margin-bottom:25px;">
        <a href="/stock" class="btn primary" style="width:30%">Склад</a>

        <button class="btn primary" onclick="window.location.href='/deliveries/create'">
            Створити нову партію
        </button>
    </div>

    <div class="card">
        <div class="list-item" style="font-weight:700; text-align:center; margin-bottom:10px;">
            Список поставок
        </div>

        <div id="deliveriesList" class="delivery-list"></div>
    </div>
</main>

<script>
const AUTH_ROLE = @json(auth()->check() ? auth()->user()->role : null);

function canAccept(){
    return AUTH_ROLE === 'owner' || AUTH_ROLE === 'accountant';
}

function formatDate(s){
    if (!s) return '-';
    return String(s).substring(0, 10);
}

async function loadDeliveries() {

    const res = await fetch('/api/deliveries');
    const deliveries = await res.json();

    const list = document.getElementById('deliveriesList');
    list.innerHTML = '';

    deliveries.forEach(d => {

        const status = (d.status || '').toLowerCase();
        const statusText = (d.status || '').toUpperCase();

        const showAccept = (status === 'shipped' && canAccept());

        list.innerHTML += `
            <div class="delivery-row" onclick="openDelivery(${d.id})">

                <div class="delivery-row-top delivery-row-start">
                    Партія #${d.id}
                </div>

                <div class="delivery-row-bottom">
                    <div>
                        <span class="label">Статус</span>
                        <span class="value">${statusText}</span>
                    </div>

                    <div>
                        <span class="label">Дата</span>
                        <span class="value">${formatDate(d.created_at)}</span>
                    </div>
                    
                </div>
                ${showAccept ? `
                    <div style="margin-top:10px;">
                        <button class="btn primary"
                                style="width:100%;"
                                onclick="acceptDelivery(event, ${d.id})">
                            Прийняти
                        </button>
                    </div>
                ` : ''}

            </div>
        `;
    });

}

function openDelivery(id) {
    window.location.href = `/deliveries/${id}`;
}

async function acceptDelivery(e, id) {
    e.stopPropagation();

    const res = await fetch(`/api/deliveries/${id}/accept`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    });

    const data = await res.json();

    if (!res.ok) {
        alert(data.error ?? 'Помилка');
        return;
    }

    loadDeliveries();
}

loadDeliveries();
</script>

@endsection
