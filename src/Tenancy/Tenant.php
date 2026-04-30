<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Tenancy;

/**
 * Контракт сущности «арендатор» (организация / company / project).
 *
 * Реализация — за host-проектом. Минимальные требования:
 *   - стабильный ключ (string|int) для использования в where(...)
 *   - human-readable label для отображения в UI.
 *
 * Можно реализовать на Eloquent-модели либо как value-object.
 */
interface Tenant
{
    /**
     * Стабильный идентификатор для запросов и Eloquent-relations.
     */
    public function getTenantKey(): int|string;

    /**
     * Имя для UI (header, dropdown, badges).
     */
    public function getTenantLabel(): string;
}
