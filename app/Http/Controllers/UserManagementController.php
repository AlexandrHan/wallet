<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    private function validateUserPayload(Request $request, ?User $user = null): array
    {
        $emailRule = 'required|string|email|max:255|unique:users,email';
        if ($user) {
            $emailRule .= ',' . $user->id;
        }

        $rules = [
            'name' => 'required|string|max:255',
            'email' => $emailRule,
            'role' => 'required|in:owner,accountant,ntv,manager,sunfix_manager,sunfix,worker',
            'actor' => 'nullable|string|max:255',
            'position' => 'nullable|in:foreman,electrician,serviceman_1,serviceman_2',
        ];

        if ($user) {
            $rules['password'] = 'nullable|string|min:8|confirmed';
        } else {
            $rules['password'] = 'required|string|min:8|confirmed';
        }

        $data = $request->validate($rules);

        if ($data['role'] !== 'worker') {
            $data['position'] = null;
        }

        $data['actor'] = !empty($data['actor'])
            ? trim((string)$data['actor'])
            : null;

        return $data;
    }

    public function index(): View
    {
        return view('users.manage', [
            'users' => User::query()
                ->orderBy('role')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateUserPayload($request);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'actor' => $data['actor'],
            'position' => $data['position'] ?? null,
        ]);

        return redirect()
            ->route('users.manage')
            ->with('status', 'Користувача створено');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validateUserPayload($request, $user);

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'actor' => $data['actor'],
            'position' => $data['position'] ?? null,
        ];

        if (!empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        $user->update($payload);

        return redirect()
            ->route('users.manage')
            ->with('status', 'Користувача оновлено');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ((int)$request->user()->id === (int)$user->id) {
            return redirect()
                ->route('users.manage')
                ->withErrors(['Неможна видалити свій власний акаунт.']);
        }

        $user->delete();

        return redirect()
            ->route('users.manage')
            ->with('status', 'Користувача видалено');
    }
}
