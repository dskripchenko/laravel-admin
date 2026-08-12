<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Infolist;

/**
 * Displays a related record, through a relation on the parent model.
 *
 * The SPA renders it as a link to the related resource's view page, when
 * linkTo is set, and as plain text otherwise.
 */
final class RelationEntry extends Entry
{
    public function entryType(): string
    {
        return 'relation';
    }

    /**
     * The relation's name on the parent model.
     */
    public function relation(string $relation): static
    {
        $this->attributes['relation'] = $relation;

        return $this;
    }

    /**
     * The column to display; 'name' by default.
     */
    public function display(string $column): static
    {
        $this->attributes['displayColumn'] = $column;

        return $this;
    }

    /**
     * The resource slug a click leads to.
     */
    public function linkTo(string $resourceSlug): static
    {
        $this->attributes['linkTo'] = $resourceSlug;

        return $this;
    }
}
