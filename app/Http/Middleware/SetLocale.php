<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = array_keys(config('examprep.locales'));
        $learner = $request->attributes->get('learner');

        $locale = $request->session()->get('locale')
            ?? $learner?->locale
            ?? $request->getPreferredLanguage($supported)
            ?? config('examprep.fallback_locale');

        App::setLocale(in_array($locale, $supported, true) ? $locale : config('examprep.fallback_locale'));

        return $next($request);
    }
}
