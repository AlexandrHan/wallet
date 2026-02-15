@extends('layouts.app')

@push('styles')
  <link rel="stylesheet" href="/css/nav-telegram.css?v={{ filemtime(public_path('css/nav-telegram.css')) }}">
@endpush

@section('content')
<body class="{{ auth()->check() ? 'has-tg-nav' : '' }}">

  <main class="wrap">
    <div class="card">
      <div style="font-weight:700; text-align:center;">Продажі</div>
      <div style="opacity:.7; text-align:center; margin-top:6px;">На даний момент сторінка в роззробці</div>
    </div>
  </main>

  @auth
    @include('partials.nav.bottom-wallet')
  @endauth

</body>
@endsection
