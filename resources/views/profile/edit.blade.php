<x-app-layout>

<style>
/* ===== FORCE DARK PROFILE ===== */
html{
  background-color: #000;
} 

body {
  padding-top: env(safe-area-inset-top);
  background: radial-gradient(1400px 700px at 20% -20%, #1b2450 0%, transparent 60%),
    radial-gradient(1200px 600px at 90% 10%, #0f3a2a 0%, transparent 55%),
    linear-gradient(180deg, #0b0d10 0%, #07080c 100%) !important;
  height: 120%;
  width: 100%;
  overscroll-behavior: none;
}

/* layout breeze */
.min-h-screen,
.bg-gray-100 {
  background: radial-gradient(1400px 700px at 20% -20%, #1b2450 0%, transparent 60%),
    radial-gradient(1200px 600px at 90% 10%, #0f3a2a 0%, transparent 55%),
    linear-gradient(180deg, #0b0d10 0%, #07080c 100%) !important;
}

/* cards */
.bg-white {
  background: rgba(255, 255, 255, 0) !important;
  color: #e9eef6 !important;
  border-radius: 18px;
}

/* text */
h1,h2,h3,label {
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
/* button {
  background: rgba(249, 39, 39, 1) !important;
  color: #e9eef6 !important;
} */

/* прибрати тінь */
.shadow {
  box-shadow: none !important;
}
</style>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
