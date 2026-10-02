<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

use Dskripchenko\LaravelAdmin\Permission\PermissionCheck;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use InvalidArgumentException;

/**
 * The live index of a resource — the very table of its list page, with its
 * search, filters, sorting, row and bulk actions, inline edits, reordering,
 * trash, or its tree when the resource is hierarchical — embedded into a
 * screen:
 *
 *   public function layout(): array
 *   {
 *       return [
 *           Layout::markdown('What the table shows…'),
 *           Layout::resourceIndex(OrderResource::class),
 *       ];
 *   }
 *
 * Unlike ResourceTable, which shows the children of the record being edited,
 * it shows the whole resource and needs no parent. A row click opens the
 * record, as on the list page. One per screen: the list's state is shared.
 *
 * It is left out for a user without the resource's view permission.
 */
final class ResourceIndex extends Layout
{
    /** @var class-string<resource> */
    private string $resourceClass;

    /**
     * @param  class-string<resource>  $resourceClass
     */
    public static function for(string $resourceClass): self
    {
        if (! is_subclass_of($resourceClass, Resource::class)) {
            throw new InvalidArgumentException(
                'ResourceIndex::for() expects subclass of '.Resource::class.", got {$resourceClass}",
            );
        }
        $instance = new self;
        $instance->resourceClass = $resourceClass;

        return $instance;
    }

    /**
     * Replaces the resource's label above the table.
     */
    public function title(string $title): self
    {
        $this->props['title'] = $title;

        return $this;
    }

    public function type(): string
    {
        return 'admin.resource-index';
    }

    public function isVisible(): bool
    {
        return parent::isVisible()
            && PermissionCheck::allows($this->resourceClass::permission().'.view');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $this->props['resource'] = $this->resourceClass::slug();

        return parent::toArray();
    }
}
