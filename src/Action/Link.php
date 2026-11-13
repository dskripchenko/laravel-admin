<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Action;

/**
 * An external or internal link.
 *
 * Among a resource's actions a link can lead to the record it is run on:
 * `{id}` in the address becomes the record's key —
 *
 *     Link::make('Edit content')
 *         ->href('/admin/screens/builder?owner=page&id={id}')
 *         ->position(['row', 'header'])
 *
 * opens from a row's menu, the record's page and drawer, and — with the
 * `header` position — from a button beside the record's edit form. A run
 * without exactly one record does not open it.
 */
final class Link extends Action
{
    public function type(): string
    {
        return 'link';
    }

    /**
     * The address; `{id}` — the key of the record the link is run on.
     */
    public function href(string $href): self
    {
        $this->attributes['href'] = $href;

        return $this;
    }

    /**
     * @param  '_self'|'_blank'|'_parent'|'_top'  $target
     */
    public function target(string $target): self
    {
        $this->attributes['target'] = $target;

        return $this;
    }
}
