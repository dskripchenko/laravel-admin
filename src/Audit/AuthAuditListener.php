<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Audit;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;

/**
 * Слушатели Laravel Auth events с записью в audit-log.
 *
 * Реагирует только если `admin.audit.log_auth_events` = true и event
 * относится к admin-guard (config admin.auth.guard).
 */
final class AuthAuditListener
{
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(Login::class, [$this, 'onLogin']);
        $events->listen(Logout::class, [$this, 'onLogout']);
        $events->listen(Failed::class, [$this, 'onFailed']);
        $events->listen(Lockout::class, [$this, 'onLockout']);
        $events->listen(PasswordReset::class, [$this, 'onPasswordReset']);
    }

    public function onLogin(Login $event): void
    {
        if (! $this->shouldLog($event->guard)) {
            return;
        }
        $this->record('login', $event->user instanceof Model ? $event->user : null, [
            'guard' => $event->guard,
            'remember' => $event->remember,
        ]);
    }

    public function onLogout(Logout $event): void
    {
        if (! $this->shouldLog($event->guard)) {
            return;
        }
        $this->record('logout', $event->user instanceof Model ? $event->user : null, [
            'guard' => $event->guard,
        ]);
    }

    public function onFailed(Failed $event): void
    {
        if (! $this->shouldLog($event->guard)) {
            return;
        }
        $this->record('login.failed', $event->user instanceof Model ? $event->user : null, [
            'guard' => $event->guard,
            'email' => $event->credentials['email'] ?? null,
        ]);
    }

    public function onLockout(Lockout $event): void
    {
        $this->record('login.lockout', null, [
            'route' => $event->request->path(),
        ]);
    }

    public function onPasswordReset(PasswordReset $event): void
    {
        $this->record('password.reset', $event->user instanceof Model ? $event->user : null, [
            'guard' => (string) config('admin.auth.guard', 'admin'),
        ]);
    }

    private function shouldLog(?string $guard): bool
    {
        if (! (bool) config('admin.audit.enabled', true)) {
            return false;
        }
        if (! (bool) config('admin.audit.log_auth_events', true)) {
            return false;
        }
        $expected = (string) config('admin.auth.guard', 'admin');

        // Failed/Login/Logout: $guard != null. Если guard'а нет — не наш event.
        return $guard === null || $guard === $expected;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function record(string $event, ?Model $subject, array $payload): void
    {
        AuditLog::create([
            'actor_type' => $subject?->getMorphClass(),
            'actor_id' => $subject?->getKey(),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'event' => $event,
            'changes' => $payload === [] ? null : ['payload' => $payload],
            'ip' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 1024),
            'url' => substr((string) request()->fullUrl(), 0, 2048),
        ]);
    }
}
