<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Action;

use Dskripchenko\LaravelAdmin\Field\Field;

/**
 * An action that opens a modal with a form of fields before it submits.
 *
 * The SPA shows a modal built from the given $fields and, once it is
 * submitted, calls the server-side method with the collected payload. It suits
 * the actions that take parameters: "Send a notification", "Change the status
 * with a reason".
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
