<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Contracts;

/**
 * Сериализуемый в JSON-схему UI-элемент.
 *
 * Реализуется Layout и Field. SPA получает дерево {type, props, children} и
 * рендерит соответствующий компонент через LayoutRenderer / FieldRenderer.
 */
interface Renderable
{
    /**
     * Сериализация в JSON-friendly массив для SPA.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;

    /**
     * Видимость элемента (canSee / onCreate / onUpdate флаги).
     */
    public function isVisible(): bool;
}
