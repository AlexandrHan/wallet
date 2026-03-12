@push('styles')
<link rel="stylesheet" href="/css/stock.css?v={{ filemtime(public_path('css/stock.css')) }}">
<link rel="stylesheet" href="/css/nav-telegram.css?v={{ filemtime(public_path('css/nav-telegram.css')) }}">
@endpush

@extends('layouts.app')

@section('content')

<main class="wrap stock-wrap has-tg-nav">
  <div class="breadcrumb-inner">
    <div class="breadcrumb" style="margin-bottom:20px; max-width:58%">
      <!--  Склад (кнопка видалена за запитом) -->
    </div>
  </div>

  <div class="card" style="margin-top:14px;">
    <div class="list-item" style="font-weight:700; margin-bottom:10px; text-align:center;">
      Склад Solar Glass
    </div>
    <p style="text-align:center; margin-bottom: 16px; opacity:.8;">Тут поки що — базова сторінка. Додайте логіку за потреби.</p>
  </div>

</main>

@include('partials.nav.bottom')

@endsection
