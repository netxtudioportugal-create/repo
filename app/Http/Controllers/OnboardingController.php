<?php

namespace App\Http\Controllers;

use App\Models\Learner;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function show(Request $request)
    {
        if ($request->attributes->get('learner')?->acknowledged_at) {
            return redirect()->route('home');
        }

        return view('onboarding');
    }

    public function store(Request $request)
    {
        $request->validate(['ack' => ['accepted']]);

        $learner = $request->attributes->get('learner')
            ?? Learner::create(['locale' => app()->getLocale()]);

        $learner->update(['acknowledged_at' => now(), 'locale' => app()->getLocale()]);

        return redirect()->route('home')->withCookie(cookie(
            config('examprep.learner_cookie'),
            $learner->id,
            config('examprep.learner_cookie_days') * 24 * 60,
            secure: $request->isSecure(),
            httpOnly: true,
            sameSite: 'lax',
        ));
    }
}
