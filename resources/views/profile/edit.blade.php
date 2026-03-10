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

  <a href="/" class="btn" style="display:block; width:100%; text-align:center; margin-bottom:20px;">
      ← Повернутись на головну
  </a>

  <div class="card" style="margin-bottom:20px;">
      <div style="font-weight:800; text-align:center; font-size:20px;">
          Профіль
      </div>
  </div>

  <div class="card" style="margin-bottom:20px;">
      <section>
          <header style="margin-bottom:16px;">
              <h2>Фото профілю</h2>
              <p>Оновіть свій аватар. Це фото буде показуватись у шапці застосунку.</p>
          </header>

          <div style="display:flex; align-items:center; gap:14px; margin-bottom:16px;">
              @php
                  $avatarFallbacks = [
                      1 => '/img/avatars/hlushchenko.jpg',
                      2 => '/img/avatars/kolisnyk.jpg',
                      3 => '/img/avatars/accountant.jpg',
                      4 => '/img/avatars/foreman.jpg',
                      5 => '/img/avatars/sunfix.jpg',
                      6 => '/img/avatars/savencov.jpg',
                      7 => '/img/avatars/malinin.jpg',
                      8 => '/img/avatars/sunfix-manager.jpg',
                      9 => '/img/avatars/ntv.jpg',
                  ];

                  $currentAvatar = $user->avatar_path
                      ? \Illuminate\Support\Facades\Storage::disk('public')->url($user->avatar_path)
                      : ($avatarFallbacks[$user->id] ?? '/img/avatars/default.jpg');
              @endphp
              <img src="{{ $currentAvatar }}" alt="Avatar" class="avatar-image" style="width:72px; height:72px;">
              <div style="opacity:.75; font-size:14px;">PNG/JPG, до 5MB</div>
          </div>

          <form method="POST" action="{{ route('profile.avatar') }}" enctype="multipart/form-data">
              @csrf
              <input type="file" name="avatar" accept="image/*" class="btn" style="width:100%; margin-bottom:12px;" required>
              <button type="submit" class="btn primary" style="width:100%;">Оновити фото</button>
          </form>
      </section>
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

  <div class="card" style="margin-bottom:20px;">
      <form method="POST" action="{{ route('logout') }}">
          @csrf
          <button type="submit" class="btn danger" style="width:100%;">🚪 Вийти з облікового запису</button>
      </form>
  </div>

</main>

@endsection
