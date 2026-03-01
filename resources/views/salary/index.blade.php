@extends('layouts.app')

@push('styles')
  <link rel="stylesheet" href="/css/nav-telegram.css?v={{ filemtime(public_path('css/nav-telegram.css')) }}">
  <link rel="stylesheet" href="/css/project.css?v={{ filemtime(public_path('css/project.css')) }}">
@endpush

@section('content')
<main class="">
  <div class="card" style="margin-bottom:15px;">
    <div style="font-weight:800; font-size:18px; text-align:center;">
      💰 Зарплатня
    </div>
  </div>

  <a href="/salary/electricians" class="card" style="margin-bottom:12px; display:block; text-decoration:none; color:inherit;">
    <div style="font-weight:800; font-size:16px; margin-bottom:6px;">
      ⚡ Зарплата електрикам
    </div>
    <div style="font-size:14px; opacity:.75;">
      Перейти до карток електриків.
    </div>
  </a>

  <a href="/salary/installers" class="card" style="margin-bottom:12px; display:block; text-decoration:none; color:inherit;">
    <div style="font-weight:800; font-size:16px; margin-bottom:6px;">
      🛠 Зарплата Монтажникам
    </div>
    <div style="font-size:14px; opacity:.75;">
      Перейти до карток монтажних бригад.
    </div>
  </a>

  <div class="card" style="margin-bottom:12px;">
    <div style="font-weight:800; font-size:16px; margin-bottom:6px;">
      📈 Зарплата Менеджерам
    </div>
    <div style="font-size:14px; opacity:.75;">
      Блок для нарахувань менеджерам.
    </div>
  </div>

  <div class="card" style="margin-bottom:12px;">
    <div style="font-weight:800; font-size:16px; margin-bottom:6px;">
      🧾 Зарплата Бухгалтеру
    </div>
    <div style="font-size:14px; opacity:.75;">
      Блок для нарахувань бухгалтеру.
    </div>
  </div>

  <div class="card">
    <div style="font-weight:800; font-size:16px; margin-bottom:6px;">
      🏗 Зарплата прорабу
    </div>
    <div style="font-size:14px; opacity:.75;">
      Блок для нарахувань прорабу.
    </div>
  </div>
</main>

@include('partials.nav.bottom')
@endsection
