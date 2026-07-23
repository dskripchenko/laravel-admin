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
     * Человекочитаемые ярлыки типов — едут в каждую сериализацию (list/timeline),
     * чтобы UI не показывал FQCN. Значения через getActorLabelAttribute и т.п.
     *
     * @var list<string>
     */
    protected $appends = ['actor_label', 'subject_label'];

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
     * Резолв FQCN morph-типа в человекочитаемый ярлык.
     *
     * Порядок: config('admin.audit.type_labels')[$fqcn] → reverse morph-map
     * alias (если host вызвал Relation::enforceMorphMap) → class_basename.
     * Пустой/null-тип (например login без subject) → null.
     */
    public static function resolveTypeLabel(?string $type): ?string
    {
        if ($type === null || $type === '') {
            return null;
        }

        $map = (array) config('admin.audit.type_labels', []);
        $label = $map[$type] ?? null;
        if (is_string($label)) {
            return $label;
        }

        $alias = array_search($type, \Illuminate\Database\Eloquent\Relations\Relation::morphMap(), true);
        if (is_string($alias)) {
            return $alias;
        }

        return class_basename($type);
    }

    public function getActorLabelAttribute(): ?string
    {
        return self::resolveTypeLabel($this->actor_type);
    }

    public function getSubjectLabelAttribute(): ?string
    {
        return self::resolveTypeLabel($this->subject_type);
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
