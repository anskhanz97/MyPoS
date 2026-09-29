<?php

namespace App\Providers;

use App\Domain\Fiscal\FbrDiV112Transport;
use App\Domain\Fiscal\FiscalTransport;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(FiscalTransport::class, FbrDiV112Transport::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute(5)->by(
            Str::lower($request->string('email')->toString()).'|'.$request->ip(),
        ));
        RateLimiter::for('password-reset', fn (Request $request): Limit => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('pin-switch', fn (Request $request): Limit => Limit::perMinute(5)->by(
            (string) $request->route('device').'|'.$request->ip(),
        ));
        RateLimiter::for('approval', fn (Request $request): Limit => Limit::perMinute(10)->by(
            (string) $request->route('device').'|'.$request->ip(),
        ));
        RateLimiter::for('device-pairing', fn (Request $request): Limit => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('device-bootstrap', fn (Request $request): Limit => Limit::perMinute(30)->by((string) $request->route('device').'|'.$request->ip()));
        RateLimiter::for('offline-sync', fn (Request $request): Limit => Limit::perMinute(60)->by(
            $request->string('device_id')->toString().'|'.$request->ip(),
        ));
        RateLimiter::for('print-helper', fn (Request $request): Limit => Limit::perMinute(120)->by(
            $request->string('device_id')->toString().'|'.$request->ip(),
        ));
        RateLimiter::for('display-pairing', fn (Request $request): Limit => Limit::perMinute(20)->by($request->ip()));
        RateLimiter::for('display-projection', fn (Request $request): Limit => Limit::perMinute(240)->by((string) ($request->header('X-Display-Token') ?: $request->ip())));
        RateLimiter::for('public-loyalty-info', fn (Request $request): Limit => Limit::perMinute(60)->by((string) $request->route('programSlug').'|'.$request->ip()));
        RateLimiter::for('public-loyalty-join', fn (Request $request): Limit => Limit::perMinute(5)->by((string) $request->route('programSlug').'|'.$request->ip()));
        RateLimiter::for('public-loyalty-lookup', fn (Request $request): Limit => Limit::perMinute(10)->by((string) $request->route('programSlug').'|'.$request->ip()));
    }
}
