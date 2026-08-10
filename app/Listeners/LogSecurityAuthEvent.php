<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Statamic\Events\TwoFactorAuthenticationFailed;

/**
 * Structured security logging for Control-Panel authentication events (Issue #23,
 * OWASP A09). Writes every relevant auth event to the dedicated `security` log
 * channel and — only on a `Lockout` — additionally alerts via the Slack channel
 * when a webhook is configured.
 *
 * The attempted username/email is logged for detection, but the password (and
 * the whole `credentials` array of the `Failed` event) is never written.
 */
final class LogSecurityAuthEvent
{
    /**
     * Log a failed login attempt.
     */
    public function handleFailed(Failed $event): void
    {
        $this->logSecurity('warning', 'CP authentication failed', $this->context([
            'event' => 'auth.failed',
            'guard' => $event->guard,
            'username' => $this->usernameFromCredentials($event->credentials),
        ]));
    }

    /**
     * Log a throttle lockout and alert on it (the only alerting trigger).
     */
    public function handleLockout(Lockout $event): void
    {
        $context = $this->context([
            'event' => 'auth.lockout',
            'username' => $this->usernameFromRequest($event->request),
        ], $event->request);

        $this->logSecurity('warning', 'CP authentication lockout', $context);

        $this->alertLockout($context);
    }

    /**
     * Log a failed two-factor challenge.
     */
    public function handleTwoFactorFailed(TwoFactorAuthenticationFailed $event): void
    {
        $this->logSecurity('warning', 'CP two-factor authentication failed', $this->context([
            'event' => 'auth.two_factor_failed',
            'user_id' => $event->user instanceof Authenticatable ? $event->user->getAuthIdentifier() : null,
        ]));
    }

    /**
     * Log a successful login (includes the authenticated user's ID).
     */
    public function handleLogin(Login $event): void
    {
        $this->logSecurity('info', 'CP authentication succeeded', $this->context([
            'event' => 'auth.login',
            'guard' => $event->guard,
            'user_id' => $event->user->getAuthIdentifier(),
        ]));
    }

    /**
     * Register the event-to-handler mapping for the subscriber.
     *
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Failed::class => 'handleFailed',
            Lockout::class => 'handleLockout',
            TwoFactorAuthenticationFailed::class => 'handleTwoFactorFailed',
            Login::class => 'handleLogin',
        ];
    }

    /**
     * Write a record to the dedicated security log channel.
     *
     * @param  array<string, mixed>  $context
     */
    private function logSecurity(string $level, string $message, array $context): void
    {
        Log::channel('security')->log($level, $message, $context);
    }

    /**
     * Send a Slack alert for a lockout when a webhook is configured; otherwise
     * stay silent (log-only) without raising an error.
     *
     * @param  array<string, mixed>  $context
     */
    private function alertLockout(array $context): void
    {
        if (empty(config('logging.channels.slack.url'))) {
            return;
        }

        Log::channel('slack')->critical('CP login lockout — possible brute-force attempt', $context);
    }

    /**
     * Merge the given event fields with the request-derived base context.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function context(array $fields, ?Request $request = null): array
    {
        $request ??= request();

        return array_merge($fields, [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Extract the attempted identifier from a `Failed` event's credentials,
     * deliberately dropping the password.
     *
     * @param  array<string, mixed>  $credentials
     */
    private function usernameFromCredentials(array $credentials): mixed
    {
        return Arr::first(Arr::except($credentials, ['password']));
    }

    /**
     * Extract the attempted identifier from a throttled request.
     */
    private function usernameFromRequest(Request $request): mixed
    {
        return $request->input('email', $request->input('username'));
    }
}
