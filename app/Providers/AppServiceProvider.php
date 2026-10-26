<?php

namespace App\Providers;

use App\Models\PracticeSession;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        Route::model('session', PracticeSession::class);
        Route::pattern('session', '[0-9a-f-]{36}');

        RateLimiter::for('practice', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
    }
}
