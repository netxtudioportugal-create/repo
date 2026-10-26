<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Services\Turnstile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function show()
    {
        return view('staff.login');
    }

    public function login(Request $request, Turnstile $turnstile)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! $turnstile->verify($request->input('cf-turnstile-response'), $request->ip())) {
            throw ValidationException::withMessages(['email' => 'The security check did not pass. Try again.']);
        }

        $key = Str::lower($credentials['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.',
            ]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'Those credentials do not match a staff account.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->intended(route('staff.items.index'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
