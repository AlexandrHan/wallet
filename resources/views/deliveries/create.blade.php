@extends('layouts.app')

@section('content')

<main class="wrap">

    <div class="card">
        <div style="font-size:18px; font-weight:700;">
            Нова партія поставки
        </div>

        <div style="margin-top:14px;">
            <button class="btn" onclick="createDelivery()">
                Створити DRAFT партію
            </button>
        </div>
    </div>

</main>

<script>
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

    window.location.href = '/deliveries/' + data.id;
}
</script>

@endsection
