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
     * `drawer` (1.74) opens a panel address in the side panel over the page:
     * a resource's list filtered by the link's query, or a screen. See inDrawer().
     *
     * @param  '_self'|'_blank'|'_parent'|'_top'|'drawer'  $target
     */
    public function target(string $target): self
    {
        $this->attributes['target'] = $target;

        return $this;
    }

    /**
     * Opens the address in the side panel over the current page instead of
     * going there — «Связанное» of a record card:
     *
     *     Link::make('Обращения · 2')
     *         ->href('/admin/r/claims?filter[phone]=79001234567&scope=open')
     *         ->inDrawer();
     *
     * A resource's list opens with the query's search (`q`), filters
     * (`filter[...]`), sort and scope (`scope`, one of Resource::scopes()); a
     * screen (`/admin/screens/{slug}?…`) with its query; anything else as a
     * page. The address of the page under it stays. `$size`: sm, md, lg, xl
     * or a CSS length (wide by default).
     */
    public function inDrawer(?string $size = null): self
    {
        $this->attributes['target'] = 'drawer';
        if ($size !== null) {
            $this->attributes['drawerSize'] = $size;
        }

        return $this;
    }
}
