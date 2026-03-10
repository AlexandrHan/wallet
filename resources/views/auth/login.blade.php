<x-guest-layout>

<style>
/* ===== FORCE DARK LOGIN (FIXED) ===== */

/* HTML + BODY */
html{
    background-color: #000;
} 
body {
  margin: 0;
  padding: 0;
  min-height: 120%;
  width: 100%;
  position: fixed;
  background:
    radial-gradient(1400px 700px at 20% -20%, #1b2450 0%, transparent 60%),
    radial-gradient(1200px 600px at 90% 10%, #0f3a2a 0%, transparent 55%),
    linear-gradient(180deg, #0b0d10 0%, #07080c 100%) !important;
}

/* Головний контейнер Breeze */
.min-h-screen,
.bg-gray-100 {
  min-height: 100svh; /* iOS fix */
  background:
    radial-gradient(1400px 700px at 20% -20%, #1b2450 0%, transparent 60%),
    radial-gradient(1200px 600px at 90% 10%, #0f3a2a 0%, transparent 55%),
    linear-gradient(180deg, #0b0d10 0%, #07080c 100%) !important;

  padding-top: env(safe-area-inset-top);
  padding-bottom: env(safe-area-inset-bottom);
}

/* ВНУТРІШНІ ВІДСТУПИ — ТУТ, А НЕ В BODY */
.max-w-md,
.w-full {
  padding-left: 1.25rem;
  padding-right: 1.25rem;
}

/* логотип */
img {
  margin-top: 10rem;
}

/* картка логіну */
.bg-white {
  background: rgba(255,255,255,.08) !important;
  color: #e9eef6 !important;
  border-radius: 22px;
}

/* текст */
h1, h2, h3, label {
  color: #e9eef6 !important;
}

p, span {
  color: #9aa6bc !important;
}

/* inputs */
input {
  background: rgba(255,255,255,.08) !important;
  color: #e9eef6 !important;
  border: 1px solid rgba(255,255,255,.15) !important;
}

/* buttons */
button {
  background: rgb(200, 0, 0) !important;
  color: #fff !important;
  font-weight: 400;
  border: none !important;
}

/* checkbox */
input[type="checkbox"] {
  accent-color: #4c7dff;
}

/* прибрати тіні */
.shadow {
  box-shadow: none !important;
}

/* iOS scroll fix */
body {
  overscroll-behavior: none;
  -webkit-overflow-scrolling: touch;
}
</style>


    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember">
                <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request'))
                <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <x-primary-button class="ms-3">
                {{ __('Log in') }}
            </x-primary-button>
        </div>
    </form>

</x-guest-layout>
