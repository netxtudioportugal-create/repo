<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class AboutController extends Controller
{
    public function show()
    {
        return view('about');
    }

    /** Deletes the learner, every session and every logged response, and the cookie (Checklist 9). */
    public function clear(Request $request)
    {
        $this->learner($request)->delete();
        $request->session()->forget('locale');

        return redirect()->route('onboarding')
            ->with('status', __('app.cleared'))
            ->withCookie(Cookie::forget(config('examprep.learner_cookie')));
    }
}
