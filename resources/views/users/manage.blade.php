@extends('layouts.app')

@section('content')
<main>
  <div class="card" style="margin-bottom:15px;">
    <div style="font-weight:800; font-size:18px;">👤 Користувачі</div>
    <div style="opacity:.7; margin-top:6px;">Створення нових юзерів доступне тільки власнику.</div>
  </div>

  @if(session('status'))
    <div class="card" style="margin-bottom:15px; border:1px solid rgba(102,242,168,.35);">
      {{ session('status') }}
    </div>
  @endif

  @if($errors->any())
    <div class="card" style="margin-bottom:15px; border:1px solid rgba(255,107,107,.35);">
      @foreach($errors->all() as $error)
        <div style="margin-bottom:6px;">{{ $error }}</div>
      @endforeach
    </div>
  @endif

  <div class="card" style="margin-bottom:15px;">
    <div style="font-weight:700; margin-bottom:12px;">Новий користувач</div>

    <form method="POST" action="{{ route('users.store') }}">
      @csrf

      <div style="margin-bottom:10px;">
        <div style="font-size:12px; opacity:.7; margin-bottom:4px;">Ім'я</div>
        <input class="btn" style="width:100%;" name="name" value="{{ old('name') }}" required>
      </div>

      <div style="margin-bottom:10px;">
        <div style="font-size:12px; opacity:.7; margin-bottom:4px;">Email</div>
        <input class="btn" style="width:100%;" type="email" name="email" value="{{ old('email') }}" required>
      </div>

      <div style="margin-bottom:10px;">
        <div style="font-size:12px; opacity:.7; margin-bottom:4px;">Роль</div>
        <select class="btn js-user-role" style="width:100%;" name="role" required>
          @foreach([
            'owner' => 'Owner',
            'accountant' => 'Соловей',
            'ntv' => 'НТВ',
            'sunfix_manager' => 'SunFix Manager',
            'sunfix' => 'SunFix',
            'worker' => 'Працівник',
          ] as $value => $label)
            <option value="{{ $value }}" @selected(old('role') === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </div>

      <div style="margin-bottom:10px;">
        <div style="font-size:12px; opacity:.7; margin-bottom:4px;">Actor</div>
        <input class="btn" style="width:100%;" name="actor" value="{{ old('actor') }}" placeholder="hlushchenko / kolisnyk / ntv ...">
      </div>

      <div class="js-worker-position-box" style="margin-bottom:10px; display:none;">
        <div style="font-size:12px; opacity:.7; margin-bottom:4px;">Позиція (для worker)</div>
        <select class="btn js-user-position" style="width:100%;" name="position">
          <option value="">Без позиції</option>
          <option value="foreman" @selected(old('position') === 'foreman')>Оніпко</option>
          <option value="electrician" @selected(old('position') === 'electrician')>Електрик</option>
          <option value="serviceman_1" @selected(old('position') === 'serviceman_1')>Сервіс 1</option>
          <option value="serviceman_2" @selected(old('position') === 'serviceman_2')>Сервіс 2</option>
        </select>
      </div>

      <div style="margin-bottom:10px;">
        <div style="font-size:12px; opacity:.7; margin-bottom:4px;">Пароль</div>
        <input class="btn" style="width:100%;" type="password" name="password" required>
      </div>

      <div style="margin-bottom:14px;">
        <div style="font-size:12px; opacity:.7; margin-bottom:4px;">Підтвердження пароля</div>
        <input class="btn" style="width:100%;" type="password" name="password_confirmation" required>
      </div>

      <button type="submit" class="btn primary" style="width:100%;">Створити користувача</button>
    </form>
  </div>

  <div class="card">
    <div style="font-weight:700; margin-bottom:12px;">Поточні користувачі</div>

    @forelse($users as $user)
      <div style="padding:10px 0; border-bottom:1px solid rgba(255,255,255,.08);">
        <div style="display:flex; justify-content:space-between; gap:10px; margin-bottom:8px;">
          <div style="font-weight:700;">{{ $user->name }}</div>
          <div style="opacity:.7; font-size:12px;">{{ $user->role }}</div>
        </div>
        <form method="POST" action="{{ route('users.update', $user) }}">
          @csrf
          @method('PATCH')

          <div style="margin-bottom:8px;">
            <div style="font-size:12px; opacity:.7; margin-bottom:4px;">Ім'я</div>
            <input class="btn" style="width:100%;" name="name" value="{{ old('name', $user->name) }}" required>
          </div>

          <div style="margin-bottom:8px;">
            <div style="font-size:12px; opacity:.7; margin-bottom:4px;">Email</div>
            <input class="btn" style="width:100%;" type="email" name="email" value="{{ old('email', $user->email) }}" required>
          </div>

          <div style="margin-bottom:8px;">
            <div style="font-size:12px; opacity:.7; margin-bottom:4px;">Роль</div>
            <select class="btn js-user-role" style="width:100%;" name="role" required>
              @foreach([
                'owner' => 'Owner',
                'accountant' => 'Соловей',
                'ntv' => 'НТВ',
                'sunfix_manager' => 'SunFix Manager',
                'sunfix' => 'SunFix',
                'worker' => 'Працівник',
              ] as $value => $label)
                <option value="{{ $value }}" @selected($user->role === $value)>{{ $label }}</option>
              @endforeach
            </select>
          </div>

          <div style="margin-bottom:8px;">
            <div style="font-size:12px; opacity:.7; margin-bottom:4px;">Actor</div>
            <input class="btn" style="width:100%;" name="actor" value="{{ old('actor', $user->actor) }}">
          </div>

          <div class="js-worker-position-box" style="margin-bottom:8px; display:none;">
            <div style="font-size:12px; opacity:.7; margin-bottom:4px;">Позиція (для worker)</div>
            <select class="btn js-user-position" style="width:100%;" name="position">
              <option value="">Без позиції</option>
              <option value="foreman" @selected($user->position === 'foreman')>Оніпко</option>
              <option value="electrician" @selected($user->position === 'electrician')>Електрик</option>
              <option value="serviceman_1" @selected($user->position === 'serviceman_1')>Сервіс 1</option>
              <option value="serviceman_2" @selected($user->position === 'serviceman_2')>Сервіс 2</option>
            </select>
          </div>

          <div style="margin-bottom:8px;">
            <div style="font-size:12px; opacity:.7; margin-bottom:4px;">Новий пароль (необов'язково)</div>
            <input class="btn" style="width:100%;" type="password" name="password">
          </div>

          <div style="margin-bottom:12px;">
            <div style="font-size:12px; opacity:.7; margin-bottom:4px;">Підтвердження нового пароля</div>
            <input class="btn" style="width:100%;" type="password" name="password_confirmation">
          </div>

          <button type="submit" class="btn" style="width:100%; margin-bottom:8px;">Зберегти зміни</button>
        </form>

        <form method="POST" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm('Видалити користувача {{ addslashes($user->name) }}?');">
          @csrf
          @method('DELETE')
          <button type="submit" class="btn danger" style="width:100%;" @disabled(auth()->id() === $user->id)>
            {{ auth()->id() === $user->id ? 'Свій акаунт не можна видалити' : 'Видалити користувача' }}
          </button>
        </form>
      </div>
    @empty
      <div style="opacity:.7;">Користувачів ще немає.</div>
    @endforelse
  </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
  function syncWorkerPosition(select) {
    const form = select?.closest('form');
    if (!form) return;

    const positionBox = form.querySelector('.js-worker-position-box');
    const positionSelect = form.querySelector('.js-user-position');
    const isWorker = select.value === 'worker';

    if (positionBox) positionBox.style.display = isWorker ? 'block' : 'none';
    if (!isWorker && positionSelect) positionSelect.value = '';
  }

  document.querySelectorAll('.js-user-role').forEach(select => {
    select.addEventListener('change', function () {
      syncWorkerPosition(select);
    });
    syncWorkerPosition(select);
  });
});
</script>

@include('partials.nav.bottom')
@endsection
