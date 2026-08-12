<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Audit\Concerns;

use Dskripchenko\LaravelAdmin\Audit\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;

/**
 * Apply this trait to a model and its CRUD events are written into
 * admin_audit_logs by themselves.
 *
 * The events:
 *   - created — writes the full `after` snapshot
 *   - updated — writes `before`/`after` for the changed attributes alone
 *   - deleted — writes the `before` snapshot
 *   - restored — writes the `after` snapshot, with SoftDeletes
 *   - forceDeleted — writes the `before` snapshot, with SoftDeletes
 *
 * The attributes listed in `config('admin.audit.excluded_attributes')` —
 * passwords, tokens and the like — are stripped automatically, and a model may
 * override that with `getAuditExcluded(): array<string>`.
 */
trait Loggable
{
    public static function bootLoggable(): void
    {
        static::created(function (Model $model): void {
            self::recordAudit($model, 'created', null, $model->getAttributes());
        });

        static::updated(function (Model $model): void {
            $changes = $model->getChanges();
            if ($changes === []) {
                return;
            }
            $original = [];
            foreach (array_keys($changes) as $key) {
                $original[$key] = $model->getOriginal($key);
            }
            self::recordAudit($model, 'updated', $original, $changes);
        });

        static::deleted(function (Model $model): void {
            $event = method_exists($model, 'isForceDeleting') && $model->isForceDeleting()
                ? 'forceDeleted'
                : 'deleted';
            self::recordAudit($model, $event, $model->getAttributes(), null);
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function (Model $model): void {
                self::recordAudit($model, 'restored', null, $model->getAttributes());
            });
        }
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'subject');
    }

    /**
     * The attributes that must never reach the changes snapshot.
     *
     * @return list<string>
     */
    public function getAuditExcluded(): array
    {
        $configured = config('admin.audit.excluded_attributes', [
            'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
        ]);

        /** @var list<string> $list */
        $list = is_array($configured) ? array_values(array_filter($configured, 'is_string')) : [];

        return $list;
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    private static function recordAudit(Model $subject, string $event, ?array $before, ?array $after): void
    {
        if (! (bool) config('admin.audit.enabled', true)) {
            return;
        }

        $excluded = method_exists($subject, 'getAuditExcluded')
            ? $subject->getAuditExcluded()
            : [];

        $changes = [];
        if ($before !== null) {
            $changes['before'] = self::filterExcluded($before, $excluded);
        }
        if ($after !== null) {
            $changes['after'] = self::filterExcluded($after, $excluded);
        }

        // For update / restore events, skip the AuditLog row entirely when
        // every changed attribute has been filtered out — otherwise the
        // timeline shows an empty "Changed" entry that hides the actual
        // change history (e.g. only updated_at / last_login_at touched).
        if (
            (bool) config('admin.audit.skip_empty_updates', true)
            && in_array($event, ['updated', 'restored'], true)
            && empty($changes['before'] ?? [])
            && empty($changes['after'] ?? [])
        ) {
            return;
        }

        AuditLog::create([
            'actor_type' => self::actorMorph(),
            'actor_id' => self::actorKey(),
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'event' => $event,
            'changes' => $changes !== [] ? $changes : null,
            'ip' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, (int) config('admin.audit.user_agent_max_length', 1024)),
            'url' => substr((string) request()->fullUrl(), 0, (int) config('admin.audit.url_max_length', 2048)),
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<string>  $excluded
     * @return array<string, mixed>
     */
    private static function filterExcluded(array $values, array $excluded): array
    {
        return array_diff_key($values, array_flip($excluded));
    }

    private static function actorMorph(): ?string
    {
        $guard = \Dskripchenko\LaravelAdmin\Panel\Panels::currentGuard();
        $user = Auth::guard($guard)->user();

        return $user instanceof Model ? $user->getMorphClass() : null;
    }

    private static function actorKey(): null|int|string
    {
        $guard = \Dskripchenko\LaravelAdmin\Panel\Panels::currentGuard();
        $user = Auth::guard($guard)->user();
        if (! $user instanceof Model) {
            return null;
        }
        $key = $user->getKey();

        return is_int($key) || is_string($key) ? $key : null;
    }
}
