<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Audit\Concerns;

use Dskripchenko\LaravelAdmin\Audit\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;

/**
 * Подключите этот trait к модели чтобы события CRUD автоматически писались
 * в admin_audit_logs.
 *
 * События:
 *   - created — пишет full `after` снимок
 *   - updated — пишет `before/after` только для изменённых атрибутов
 *   - deleted — пишет `before` снимок
 *   - restored — пишет `after` снимок (если SoftDeletes)
 *   - forceDeleted — пишет `before` снимок (если SoftDeletes)
 *
 * Атрибуты из `config('admin.audit.excluded_attributes')` (password, tokens
 * и т.п.) автоматически вычищаются. Можно переопределить per-модель через
 * `getAuditExcluded(): array<string>`.
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
     * Атрибуты, которые не должны попадать в changes-снимок.
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

        AuditLog::create([
            'actor_type' => self::actorMorph(),
            'actor_id' => self::actorKey(),
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'event' => $event,
            'changes' => $changes !== [] ? $changes : null,
            'ip' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 1024),
            'url' => substr((string) request()->fullUrl(), 0, 2048),
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
        $guard = (string) config('admin.auth.guard', 'admin');
        $user = Auth::guard($guard)->user();

        return $user instanceof Model ? $user->getMorphClass() : null;
    }

    private static function actorKey(): null|int|string
    {
        $guard = (string) config('admin.auth.guard', 'admin');
        $user = Auth::guard($guard)->user();
        if (! $user instanceof Model) {
            return null;
        }
        $key = $user->getKey();

        return is_int($key) || is_string($key) ? $key : null;
    }
}
