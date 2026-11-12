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

        $locale = $request->session()->get('locale') ?? $learner?->locale ?? $this->fromBrowser($request, $supported);
        $locale = config('examprep.legacy_locales')[$locale] ?? $locale;

        App::setLocale(in_array($locale, $supported, true) ? $locale : config('examprep.fallback_locale'));

        return $next($request);
    }

    /** First browser language whose language part matches a supported locale. ru-UA needs an exact match. */
    private function fromBrowser(Request $request, array $supported): ?string
    {
        foreach ($request->getLanguages() as $language) {
            $language = str_replace('_', '-', $language);
            foreach ($supported as $locale) {
                if ($language === $locale || ($locale !== 'ru-UA' && strtok($language, '-') === strtok($locale, '-'))) {
                    return $locale;
                }
            }
        }

        return null;
    }
}
