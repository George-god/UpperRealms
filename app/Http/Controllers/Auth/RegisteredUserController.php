<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'username' => ['required', 'string', 'max:30', 'unique:'.User::class.',username', 'regex:/^[a-zA-Z0-9_]+$/'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'username' => $request->username,
            'email' => $request->email,
            'password_hash' => Hash::make($request->password),
            'is_admin' => false,
            'realm_id' => 1,
            'level' => 1,
            'chi' => 100,
            'max_chi' => 100,
            'attack' => 10,
            'defense' => 10,
            'wins' => 0,
            'losses' => 0,
            'rating' => 1000.00,
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect()->route('onboarding.welcome');
    }
}
