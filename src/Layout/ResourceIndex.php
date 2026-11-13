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
 * record, as on the list page — or, with `->rowOpen('drawer')`, a panel over
 * the list.
 *
 * Every list on a screen keeps a state of its own: search, filters, page,
 * sort, selection. Several of them — side by side or in Layout::tabs() — each
 * get `->queryKey('orders')`, which namespaces their keys in the address
 * (`?orders.q=…&orders.filter[status]=new`), so a link opens each list as it
 * was. Without a key the list uses the plain keys (`?q=…`), as the only list
 * on a screen always did. A list in a tab that is not open loads nothing
 * until the tab is opened.
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

    /**
     * The namespace of this list's keys in the address — for a screen with
     * several lists. Letters, digits, `_` and `-`.
     */
    public function queryKey(string $key): self
    {
        if (preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $key) !== 1) {
            throw new InvalidArgumentException("ResourceIndex::queryKey() expects letters, digits, _ or -, starting with a letter, got '{$key}'");
        }
        $this->props['queryKey'] = $key;

        return $this;
    }

    /**
     * How a row opens: `page` — the record's page, as on the list page — or
     * `drawer` — a panel over the list with the record's view and form, its
     * actions, and ‹ › (Alt+↑ / Alt+↓) to the previous and next record. The
     * list stays where it was and reloads after a save or an action. Not set,
     * the resource decides (Resource::rowOpen()).
     *
     * `$size` is the drawer's width: sm, md, lg (the default), xl or a CSS length.
     */
    public function rowOpen(string $mode, ?string $size = null): self
    {
        if (! in_array($mode, ['page', 'drawer'], true)) {
            throw new InvalidArgumentException("ResourceIndex::rowOpen() expects page or drawer, got '{$mode}'");
        }
        $this->props['rowOpen'] = $mode;
        if ($size !== null) {
            $this->props['drawerSize'] = $size;
        }

        return $this;
    }

    /**
     * After a save or an action in the drawer, move on to the next record at
     * once — a queue (reviews, requests) is worked through record by record.
     * Implies `rowOpen('drawer')` when no mode is set.
     */
    public function nextAfterAction(bool $next = true): self
    {
        $this->props['nextAfterAction'] = $next;
        if ($next && ! isset($this->props['rowOpen'])) {
            $this->props['rowOpen'] = 'drawer';
        }

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
