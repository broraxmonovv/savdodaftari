<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return Auth::check() && Auth::user()->is_admin
            ? redirect()->route('admin.dashboard')
            : view('admin.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string'],
        ]);

        $phone = '+'.ltrim(preg_replace('/[^\d+]/', '', $data['phone']), '+');
        $user = User::where('phone', $phone)->first();

        $valid = $user !== null
            && $user->is_admin
            && ! $user->isBlocked()
            && filled($user->admin_password)
            && Hash::check($data['password'], $user->admin_password);

        if (! $valid) {
            throw ValidationException::withMessages(['phone' => 'Telefon yoki parol noto\'g\'ri.']);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
