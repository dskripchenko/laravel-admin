<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

use Dskripchenko\LaravelAdmin\Permission\PermissionCheck;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use InvalidArgumentException;

/**
 * The live index of a resource — the very table of its list page, with its
 * search, filters, sorting, row and bulk actions, inline edits, reordering,
 * trash, or its tree when the resource is hierarchical, and its kanban and
 * calendar views when it has them — embedded into a screen:
 *
 *   public function layout(): array
 *   {
 *       return [
 *           Layout::markdown('What the table shows…'),
 *           Layout::resourceIndex(OrderResource::class),
 *       ];
 *   }
 *
 * `->initialView('kanban')` opens it on the resource's kanban (or calendar) view.
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

    /**
     * The view to open with when the URL names none: `list`, `kanban` or
     * `calendar` — the latter two when the resource declares them
     * (Resource::kanban(), Resource::calendar()). The page's view switcher
     * still offers the others.
     *
     * Named initialView() because Layout::view() is already the factory of a
     * Blade view layout.
     */
    public function initialView(string $view): self
    {
        if (! in_array($view, ['list', 'kanban', 'calendar'], true)) {
            throw new InvalidArgumentException("ResourceIndex::initialView() expects list, kanban or calendar, got '{$view}'");
        }
        $this->props['view'] = $view;

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
