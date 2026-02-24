@extends('layouts.app')

@section('content')

<style>
/* ===== FORCE DARK PROFILE ===== */
html{ background-color:#000; }
body{
  padding-top: env(safe-area-inset-top);
  background: radial-gradient(1400px 700px at 20% -20%, #1b2450 0%, transparent 60%),
    radial-gradient(1200px 600px at 90% 10%, #0f3a2a 0%, transparent 55%),
    linear-gradient(180deg, #0b0d10 0%, #07080c 100%) !important;
  min-height: 100vh;
  width: 100%;
  overscroll-behavior: none;
}

/* ===== FIX PROFILE HEADER LAYOUT ===== */
/* ===== HARD FIX PROFILE HEADER POSITION ===== */

.card section {
    position: relative !important;
}

.card section > header {
    position: static !important;
    inset: auto !important;
    transform: none !important;
    background: transparent;
    display: flex !important;
    flex-direction: column !important;
    align-items: flex-start !important;

    margin: 0 0 16px 0 !important;
    padding: 0 !important;
}


.card section > header h2 {
    margin: 0;
    font-size: 18px;
    font-weight: 700;
}

.card section > header p {
    margin: 0;
    font-size: 14px;
    opacity: 0.8;
}

/* cards */
.bg-white {
  background: rgba(255, 255, 255, 0) !important;
  color: #e9eef6 !important;
  border-radius: 18px;
}

/* text */
h1,h2,h3,label { 
  color:#e9eef6 !important;
   }
p, span { color:#9aa6bc !important; }

/* inputs */
input{
  background: rgba(255,255,255,.08) !important;
  color:#e9eef6 !important;
  border:1px solid rgba(255,255,255,.15) !important;
}

/* shadow off */
.shadow { box-shadow:none !important; }


</style>

<main class="wrap has-tg-nav" style="padding-top:90px; padding-bottom:100px;">

  <div class="card" style="margin-bottom:20px;">
      <div style="font-weight:800; text-align:center; font-size:20px;">
          Профіль
      </div>
  </div>

  <div class="card" style="margin-bottom:20px;">
      @include('profile.partials.update-profile-information-form')
  </div>

  <div class="card" style="margin-bottom:20px;">
      @include('profile.partials.update-password-form')
  </div>

  <div class="card" style="margin-bottom:20px;">
      @include('profile.partials.delete-user-form')
  </div>

</main>

@endsection