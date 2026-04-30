<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Audit;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Запись audit-лога.
 *
 * Структура `changes` зависит от события:
 *   - created → `{after: {...}}`
 *   - updated → `{before: {...}, after: {...}}` (только изменённые поля)
 *   - deleted/forceDeleted → `{before: {...}}`
 *   - login/logout → `{guard: 'admin'}` (auth payload)
 *
 * @property int $id
 * @property string|null $actor_type
 * @property int|null $actor_id
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string $event
 * @property array<string, mixed>|null $changes
 * @property string|null $ip
 * @property string|null $user_agent
 * @property string|null $url
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
final class AuditLog extends Model
{
    protected $table = 'admin_audit_logs';

    protected $fillable = [
        'actor_type',
        'actor_id',
        'subject_type',
        'subject_id',
        'event',
        'changes',
        'ip',
        'user_agent',
        'url',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope: для конкретного subject (model + id).
     *
     * @param  Builder<AuditLog>  $query
     * @return Builder<AuditLog>
     */
    public function scopeForSubject(Builder $query, Model $subject): Builder
    {
        return $query
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey());
    }

    /**
     * @param  Builder<AuditLog>  $query
     * @return Builder<AuditLog>
     */
    public function scopeForActor(Builder $query, Model $actor): Builder
    {
        return $query
            ->where('actor_type', $actor->getMorphClass())
            ->where('actor_id', $actor->getKey());
    }
}
