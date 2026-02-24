<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            Видалення акаунта
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            Після видалення акаунта всі дані буде безповоротно стерто.
            Перед видаленням переконайтесь, що ви зберегли необхідну інформацію.
        </p>
    </header>

    <x-danger-button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >
        Видалити акаунт
    </x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
            @csrf
            @method('delete')

            <h2 class="text-lg font-medium text-gray-900">
                Ви впевнені, що хочете видалити акаунт?
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                Цю дію неможливо скасувати. Введіть пароль для підтвердження.
            </p>

            <div class="mt-6">
                <x-input-label for="password" value="Пароль" class="sr-only" />

                <x-text-input
                    id="delete_password"
                    name="password"
                    type="password"
                    class="mt-1 block w-3/4"
                    placeholder="Пароль"
                    autocomplete="current-password"
                />

                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end">
                <x-secondary-button x-on:click="$dispatch('close')">
                    Скасувати
                </x-secondary-button>

                <x-danger-button class="ms-3">
                    Видалити
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>