<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Make Vite tag its output (module scripts, modulepreload links and the
        // inline prefetch script) with the same CSP nonce the frontend policy
        // emits, so the strict Content-Security-Policy (Issue #21) allows them
        // without ever falling back to 'unsafe-inline'. spatie's csp-nonce binding
        // only exists when nonce handling is enabled.
        if (config('csp.nonce_enabled') && app()->bound('csp-nonce')) {
            Vite::useCspNonce(app('csp-nonce'));
        }
    }
}
