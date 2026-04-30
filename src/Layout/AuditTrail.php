<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

/**
 * Layout для отображения audit-timeline на view-screen'е.
 *
 * Конфиг говорит SPA какой subject грузить (subject_type + ?subject_id из state)
 * и какой endpoint вызывать. Сама загрузка идёт через `audit.timeline` action.
 *
 * Использование:
 *
 *     AuditTrail::for(\App\Models\User::class)->limit(50)
 */
final class AuditTrail extends Layout
{
    public static function for(string $subjectType): self
    {
        $instance = new self;
        $instance->props['subjectType'] = $subjectType;

        return $instance;
    }

    public function type(): string
    {
        return 'audit_trail';
    }

    /**
     * Source key из state, откуда брать `id` записи. Default: 'id'.
     */
    public function fromState(string $key): self
    {
        $this->props['idStateKey'] = $key;

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->props['limit'] = $limit;

        return $this;
    }

    /**
     * Permission для отображения: если у пользователя его нет — компонент
     * скрыт целиком. Default: null = доступно всем authenticated.
     */
    public function withPermission(string $permission): self
    {
        $this->props['permission'] = $permission;

        return $this;
    }
}
