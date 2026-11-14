<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Audit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * The audit entries of a Loggable model — Loggable::auditLogs().
 *
 * The log keeps subject_id as a string, while an integer-keyed model makes
 * Laravel compare it with an integer: `subject_id = 5`, or a raw
 * `subject_id in (5, 6)` when eager loading. PostgreSQL has no varchar =
 * integer operator and refuses both, so the parent's key is passed as text.
 *
 * @template TDeclaringModel of Model
 *
 * @extends MorphMany<AuditLog, TDeclaringModel>
 */
final class SubjectAuditLogs extends MorphMany
{
    public function getParentKey()
    {
        return MorphKey::toString(parent::getParentKey());
    }

    /**
     * @param  array<int, Model>  $models
     * @param  string|null  $key
     * @return array<int, mixed>
     */
    protected function getKeys(array $models, $key = null)
    {
        return array_values(array_filter(
            array_map(MorphKey::toString(...), parent::getKeys($models, $key)),
            static fn (?string $k): bool => $k !== null,
        ));
    }

    protected function whereInMethod(Model $model, $key)
    {
        return 'whereIn';
    }
}
