<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

            <!-- iOS / PWA -->
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="csrf-token" content="{{ csrf_token() }}">


        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        <link rel="stylesheet" href="/css/wallet.css?v={{ filemtime(public_path('css/wallet.css')) }}">
        <link rel="stylesheet" href="/css/reclamations.css?v={{ filemtime(public_path('css/reclamations.css')) }}">
        <link rel="stylesheet" href="/css/nav-telegram.css?v={{ filemtime(public_path('css/nav-telegram.css')) }}">


        <script src="/js/header.js?v={{ filemtime(public_path('js/header.js')) }}" defer></script>
        <script src="/js/reclamations.js?v={{ filemtime(public_path('js/reclamations.js')) }}" defer></script>
        <link rel="manifest" href="/manifest.webmanifest?v={{ filemtime(public_path('manifest.webmanifest')) }}">
        @stack('styles')
        <meta name="theme-color" content="#0b0d10">

        <style>
        :root{ color-scheme: dark; }
        html{ background:#0b0d10; }
        body{ margin:0; }

        </style>


        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @auth
        <script>
        window.sgPushConfig = {
            apiKey:            "{{ config('services.firebase.api_key') }}",
            authDomain:        "{{ config('services.firebase.auth_domain') }}",
            projectId:         "{{ config('services.firebase.project_id') }}",
            messagingSenderId: "{{ config('services.firebase.messaging_sender_id') }}",
            appId:             "{{ config('services.firebase.app_id') }}",
            vapidKey:          "{{ config('services.firebase.vapid_key') }}"
        };
        </script>

        {{-- ── Shared sound system ──────────────────────────────────────────────
             Pre-loads + unlocks audio on first user gesture.
             window._sgPlaySound(src) — plays with 2s dedup to prevent
             double-play when both WebSocket and FCM push arrive for the same event.
        --}}
        <audio id="_sg_snd_chat"   src="/sounds/chat.mp3"   preload="auto" style="display:none"></audio>
        <audio id="_sg_snd_moneta" src="/sounds/moneta.mp3" preload="auto" style="display:none"></audio>
        <script>(function () {
          let _unlocked = false;
          let _lastAt   = 0;
          const _pool   = {};

          function _elem(src) {
            if (!_pool[src]) {
              const id = src.includes('chat') ? '_sg_snd_chat'
                       : src.includes('moneta') ? '_sg_snd_moneta'
                       : null;
              _pool[src] = (id && document.getElementById(id)) || new Audio(src);
            }
            return _pool[src];
          }

          function _unlock() {
            if (_unlocked) return;
            _unlocked = true;
            ['_sg_snd_chat', '_sg_snd_moneta'].forEach(id => {
              const a = document.getElementById(id);
              if (!a) return;
              try {
                a.muted = true;
                a.play().then(() => { a.pause(); a.currentTime = 0; a.muted = false; })
                         .catch(() => { a.muted = false; });
              } catch {}
            });
          }

          ['click', 'keydown'].forEach(ev => document.addEventListener(ev, _unlock, { once: true }));
          document.addEventListener('touchstart', _unlock, { once: true, passive: true });

          window._sgLastSoundAt = 0;
          window._sgLastNotifId = 0;

          // External code (wallet.js) can call this to inform dedup it played a sound
          window._sgBumpSound = function () {
            _lastAt = Date.now();
            window._sgLastSoundAt = _lastAt;
          };

          window._sgPlaySound = function (src, vol) {
            const now = Date.now();
            if (now - _lastAt < 2000) return; // dedup: WS + FCM same event
            _lastAt = now;
            window._sgLastSoundAt = now;
            const a = _elem(src);
            if (!a) return;
            try {
              a.volume = vol ?? 0.85;
              a.currentTime = 0;
              a.play().catch(e => { _lastAt = 0; }); // allow retry if blocked
            } catch { _lastAt = 0; }
          };
        })();
        </script>

        <script src="https://www.gstatic.com/firebasejs/10.12.0/firebase-app-compat.js"></script>
        <script src="https://www.gstatic.com/firebasejs/10.12.0/firebase-messaging-compat.js"></script>
        <script src="/js/push-notifications.js?v={{ filemtime(public_path('js/push-notifications.js')) }}"></script>
        @endauth
    </head>
    <body>
    <div class="app-bg"></div>



    {{-- твій хедер (той що з бургером) --}}
    @include('partials.sg_header')

    <main class="wrap reclamations-main">
        @yield('content')
    </main>
    @include('partials.global.quick-modals')
    </body>

</html>
