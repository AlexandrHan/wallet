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

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        <link rel="stylesheet" href="/css/wallet.css?v={{ filemtime(public_path('css/wallet.css')) }}">
        <link rel="stylesheet" href="/css/reclamations.css?v={{ filemtime(public_path('css/reclamations.css')) }}">

        <script src="/js/header.js?v={{ filemtime(public_path('js/header.js')) }}" defer></script>
        <script src="/js/reclamations.js?v={{ filemtime(public_path('js/reclamations.js')) }}" defer></script>
        <link rel="manifest" href="/manifest.webmanifest?v={{ filemtime(public_path('manifest.webmanifest')) }}">
        @stack('styles')
        <meta name="theme-color" content="#0b0d10">

        <style>
        :root{ color-scheme: dark; }
        html{ background:#0b0d10; }
        body{ margin:0; }
        #appSplash{ position:fixed; inset:0; background:#0b0d10; z-index:99999; }
        </style>


        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
    <div class="app-bg"></div>

    <div id="appSplash">
        <div class="splash-logo">
        <img src="/img/holding.png" alt="SolarGlass">
        </div>
    </div>

    {{-- твій хедер (той що з бургером) --}}
    @include('partials.sg_header')

    <main class="wrap reclamations-main">
        @yield('content')
    </main>
    </body>

</html>
