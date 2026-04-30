<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Action;

/**
 * Действие, применяемое к нескольким выделенным записям сразу.
 *
 * SPA рендерит как кнопку в bulk-toolbar таблицы (показывается когда
 * есть selection). Backend получает {ids: [...], confirm: ...} в payload.
 *
 * Бэкенд-имплементация — `runMethod` action на ResourceController, который
 * вызывает Resource-метод по name'у с переданным набором ids.
 */
final class BulkAction extends Action
{
    public function __construct()
    {
        $this->position = ['bulk'];
    }

    public function type(): string
    {
        return 'bulk';
    }

    /**
     * Имя метода на Resource'е, который выполнит действие. Принимает
     * `array<int, mixed> $ids` плюс optional payload.
     */
    public function method(string $method): self
    {
        $this->attributes['method'] = $method;

        return $this;
    }

    /**
     * Минимальное количество выделенных rows, при котором action активен.
     */
    public function requiresAtLeast(int $count): self
    {
        $this->attributes['requiresAtLeast'] = max(1, $count);

        return $this;
    }

    /**
     * Максимальное количество (предотвращает бессмысленные «delete 10000»).
     */
    public function requiresAtMost(int $count): self
    {
        $this->attributes['requiresAtMost'] = $count;

        return $this;
    }
}
