<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Action;

use Dskripchenko\LaravelAdmin\Field\Field;

/**
 * Action, которое открывает modal с формой полей перед submit'ом.
 *
 * SPA показывает Modal с переданными $fields, после submit'а вызывает
 * server-side method с собранным payload'ом. Полезно для actions с
 * параметрами: «Отправить уведомление», «Изменить статус с reason'ом».
 */
final class ModalAction extends Action
{
    public function type(): string
    {
        return 'modal';
    }

    public function method(string $method): self
    {
        $this->attributes['method'] = $method;

        return $this;
    }

    /**
     * @param  list<Field>  $fields
     */
    public function fields(array $fields): self
    {
        $this->attributes['fields'] = array_map(
            static fn (Field $f): array => $f->toArray(),
            $fields,
        );

        return $this;
    }

    public function modalSize(string $size): self
    {
        $this->attributes['modalSize'] = $size;

        return $this;
    }

    public function modalTitle(string $title): self
    {
        $this->attributes['modalTitle'] = $title;

        return $this;
    }

    public function submitLabel(string $label): self
    {
        $this->attributes['submitLabel'] = $label;

        return $this;
    }
}
