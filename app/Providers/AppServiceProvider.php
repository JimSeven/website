<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\LogSecurityAuthEvent;
use Illuminate\Support\Facades\Event;
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
        // Security-Event-Logging für CP-Auth (Issue #23, OWASP A09): explizit
        // registrierter Subscriber auf die relevanten Auth-Events.
        Event::subscribe(LogSecurityAuthEvent::class);

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
