<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Audit;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Преобразует коллекцию AuditLog'ов в нормализованные timeline-карточки
 * для SPA.
 *
 * Каждая карточка:
 *   ```
 *   {
 *     id, event, created_at,
 *     actor: { id, name, type } | null,
 *     summary: 'Создал User #42 «Иван»',
 *     diff: [{ field, before, after }] | null
 *   }
 *   ```
 *
 * `diff` строится из `changes.before` × `changes.after` с union'ом ключей.
 */
final class AuditTimelineProjector
{
    /**
     * @param  Collection<int, AuditLog>  $logs
     * @return list<array<string, mixed>>
     */
    public static function project(Collection $logs): array
    {
        return $logs->map(static fn (AuditLog $log): array => [
            'id' => $log->id,
            'event' => $log->event,
            'created_at' => $log->created_at->toIso8601String(),
            'actor' => self::actorPayload($log),
            'subject' => [
                'type' => $log->subject_type,
                'id' => $log->subject_id,
            ],
            'summary' => self::summary($log),
            'diff' => self::diff($log),
            'context' => [
                'ip' => $log->ip,
                'user_agent' => $log->user_agent,
                'url' => $log->url,
            ],
        ])->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function actorPayload(AuditLog $log): ?array
    {
        if ($log->actor_id === null || $log->actor_type === null) {
            return null;
        }

        $actor = $log->actor;
        if (! $actor instanceof Model) {
            return [
                'id' => $log->actor_id,
                'type' => $log->actor_type,
                'name' => null,
            ];
        }

        return [
            'id' => $actor->getKey(),
            'type' => $log->actor_type,
            'name' => (string) ($actor->getAttribute('name') ?? $actor->getAttribute('email') ?? ''),
        ];
    }

    private static function summary(AuditLog $log): string
    {
        $event = $log->event;

        return match (true) {
            $event === 'created' => 'Создано',
            $event === 'updated' => 'Изменено',
            $event === 'deleted' => 'Удалено',
            $event === 'forceDeleted' => 'Окончательно удалено',
            $event === 'restored' => 'Восстановлено',
            $event === 'login' => 'Вход в систему',
            $event === 'logout' => 'Выход из системы',
            $event === 'login.failed' => 'Неудачная попытка входа',
            $event === 'login.lockout' => 'Блокировка по rate-limit',
            $event === 'password.reset' => 'Сброс пароля',
            str_starts_with($event, 'two-factor.') => '2FA: '.$event,
            str_starts_with($event, 'impersonation.') => 'Impersonation: '.$event,
            default => $event,
        };
    }

    /**
     * @return list<array{field: string, before: mixed, after: mixed}>|null
     */
    private static function diff(AuditLog $log): ?array
    {
        $changes = $log->changes ?? [];
        $before = is_array($changes['before'] ?? null) ? $changes['before'] : [];
        $after = is_array($changes['after'] ?? null) ? $changes['after'] : [];

        if ($before === [] && $after === []) {
            return null;
        }

        $keys = array_unique(array_merge(array_keys($before), array_keys($after)));
        $diff = [];
        foreach ($keys as $field) {
            $diff[] = [
                'field' => (string) $field,
                'before' => $before[$field] ?? null,
                'after' => $after[$field] ?? null,
            ];
        }

        return $diff;
    }
}
