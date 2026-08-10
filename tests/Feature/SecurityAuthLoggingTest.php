<?php

declare(strict_types=1);

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Statamic\Events\TwoFactorAuthenticationFailed;

/**
 * Security-Event-Logging für CP-Auth (Issue #23, OWASP A09).
 *
 * Deckt ab: Auth-Events landen strukturiert im dedizierten `security`-Channel,
 * ein Lockout triggert den Slack-Sink (nur wenn Webhook gesetzt), und das
 * Passwort taucht niemals im Log auf.
 */

/**
 * Hänge einen Monolog TestHandler an einen gecachten Log-Channel und gib ihn
 * zurück. Der Listener löst denselben (gecachten) Channel auf, daher fängt der
 * Handler dessen Records ab.
 */
function captureChannel(string $channel): TestHandler
{
    $handler = new TestHandler();
    Log::channel($channel)->getLogger()->setHandlers([$handler]);

    return $handler;
}

function fakeUser(int|string $id): Authenticatable
{
    return new class($id) implements Authenticatable
    {
        public function __construct(private int|string $id) {}

        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): mixed
        {
            return $this->id;
        }

        public function getAuthPasswordName(): string
        {
            return 'password';
        }

        public function getAuthPassword(): string
        {
            return '';
        }

        public function getRememberToken(): string
        {
            return '';
        }

        public function setRememberToken($value): void {}

        public function getRememberTokenName(): string
        {
            return 'remember_token';
        }
    };
}

it('logs a failed CP login to the security channel without the password', function () {
    $handler = captureChannel('security');

    event(new Failed('web', null, [
        'email' => 'attacker@example.com',
        'password' => 'super-secret-guess',
    ]));

    expect($handler->hasWarningThatContains('CP authentication failed'))->toBeTrue();

    $record = collect($handler->getRecords())->firstWhere('message', 'CP authentication failed');

    expect($record->context['event'])->toBe('auth.failed')
        ->and($record->context['username'])->toBe('attacker@example.com')
        ->and($record->context)->not->toHaveKey('password');

    // Belt-and-braces: das Passwort darf in keinem serialisierten Record stehen.
    expect(json_encode($handler->getRecords()))->not->toContain('super-secret-guess');
});

it('logs a successful login with the user id', function () {
    $handler = captureChannel('security');

    event(new Login('web', fakeUser(42), false));

    $record = collect($handler->getRecords())->firstWhere('message', 'CP authentication succeeded');

    expect($record)->not->toBeNull()
        ->and($record->level)->toBe(Level::Info)
        ->and($record->context['event'])->toBe('auth.login')
        ->and($record->context['user_id'])->toBe(42);
});

it('logs a failed two-factor challenge', function () {
    $handler = captureChannel('security');

    event(new TwoFactorAuthenticationFailed(fakeUser(7)));

    $record = collect($handler->getRecords())->firstWhere('message', 'CP two-factor authentication failed');

    expect($record)->not->toBeNull()
        ->and($record->context['event'])->toBe('auth.two_factor_failed')
        ->and($record->context['user_id'])->toBe(7);
});

it('alerts the slack sink on a lockout when a webhook is configured', function () {
    config(['logging.channels.slack.url' => 'https://hooks.slack.test/services/xxx']);

    $security = captureChannel('security');
    $slack = captureChannel('slack');

    event(new Lockout(Request::create('/cp/auth/login', 'POST', ['email' => 'attacker@example.com'])));

    expect($security->hasWarningThatContains('CP authentication lockout'))->toBeTrue()
        ->and($slack->hasCriticalThatContains('CP login lockout'))->toBeTrue();

    $alert = collect($slack->getRecords())->first();
    expect($alert->context['username'])->toBe('attacker@example.com');
});

it('does not alert slack on a lockout when no webhook is configured', function () {
    // Slack-Channel mit Dummy-URL instanziieren (Handler anhängen), dann Webhook
    // entfernen: der Listener-Guard muss den Sink jetzt überspringen.
    config(['logging.channels.slack.url' => 'https://hooks.slack.test/services/xxx']);
    $slack = captureChannel('slack');
    $security = captureChannel('security');
    config(['logging.channels.slack.url' => null]);

    event(new Lockout(Request::create('/cp/auth/login', 'POST', ['email' => 'attacker@example.com'])));

    expect($security->hasWarningThatContains('CP authentication lockout'))->toBeTrue()
        ->and($slack->getRecords())->toBeEmpty();
});
