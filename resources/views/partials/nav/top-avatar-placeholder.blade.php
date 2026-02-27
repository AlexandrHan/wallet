<!-- Аватарка в шапке -->
<div class="avatar-placeholder">
       @php
  $u = auth()->user();

  $avatars = [
    1 => '/img/avatars/hlushchenko.jpg',      // Hlushchenko
    2 => '/img/avatars/kolisnyk.jpg',         // Kolisnyk
    3 => '/img/avatars/accountant.jpg',       // Accountant
    4 => '/img/avatars/foreman.jpg',          // Foreman
    5 => '/img/avatars/sunfix.jpg',           // SunFix
    6 => '/img/avatars/savencov.jpg',         // ServiceMan Savenkov
    7 => '/img/avatars/malinin.jpg',          // ServiceMan Malinin
    8 => '/img/avatars/sunfix-manager.jpg',   // SunFix Manager
    9 => '/img/avatars/ntv.jpg',              // NTV
  ];

  $avatarSrc = $avatars[$u->id] ?? '/img/avatars/default.jpg';
@endphp

<div class="avatar-placeholder">
  <img src="{{ $avatarSrc }}" alt="Avatar" class="avatar-image">
</div>
</div>



  