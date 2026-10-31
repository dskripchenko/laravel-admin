<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Widget\Concerns;

use Dskripchenko\LaravelAdmin\Permission\PermissionCheck;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Widget\UserWidgets;
use InvalidArgumentException;

/**
 * The two sources of the kanban and calendar widgets.
 *
 * - **A resource**: its own kanban or calendar view (Resource::kanban(),
 *   Resource::calendar()) inside the widget, loaded through the resource's
 *   board or calendar endpoint — with the same permission checks, scope and
 *   filters as its index page, plus the widget's preset filters and search.
 *   Read-only unless editable(), and then only for a user who may update the
 *   resource. The widget is hidden from a user without the resource's view
 *   permission, and when the resource is not registered in the current panel
 *   (UserWidgets::resourceAvailable()).
 * - **Markup**: the board or the calendar written as text, in the format of
 *   the kit's UidKanban / UidEventCalendar, shown read-only.
 *
 * The class using it names the view through resourceView() — 'kanban' or
 * 'calendar'.
 */
trait ShowsResourceView
{
    /** @var class-string<resource>|null */
    private ?string $resourceClass = null;

    /** @var string|(callable(): string)|null */
    private $markup = null;

    /** @var array<string, mixed> */
    private array $presetFilters = [];

    private ?string $presetSearch = null;

    private bool $editableView = false;

    /** The resource view this widget shows: 'kanban' or 'calendar'. */
    abstract protected function resourceView(): string;

    /**
     * Shows the kanban or calendar view of a resource. The resource must
     * declare it — Resource::kanban() or Resource::calendar().
     *
     * @param  class-string<resource>  $resourceClass
     */
    public function resource(string $resourceClass): static
    {
        if (! is_subclass_of($resourceClass, Resource::class)) {
            throw new InvalidArgumentException(static::class.'::resource() expects a subclass of '.Resource::class.", got {$resourceClass}");
        }
        $this->resourceClass = $resourceClass;
        $this->markup = null;

        return $this;
    }

    /**
     * Shows a board or a calendar written as markup, read-only — a string
     * or a callable returning one.
     *
     * @param  string|callable(): string  $markup
     */
    public function markup(string|callable $markup): static
    {
        $this->markup = $markup;
        $this->resourceClass = null;

        return $this;
    }

    /**
     * Preset filters of the resource's list, by filter name — the same
     * `filters` map the index page sends: `['status' => 'open']`.
     *
     * @param  array<string, mixed>  $filters
     */
    public function filters(array $filters): static
    {
        $this->presetFilters = $filters;

        return $this;
    }

    /** A preset search over the resource's searchable columns. */
    public function search(string $search): static
    {
        $this->presetSearch = trim($search) === '' ? null : $search;

        return $this;
    }

    /**
     * Lets the cards (events) be dragged — a status change or a new date,
     * saved through the resource's own move endpoint. Only for a user who
     * may update the resource, and never on a view declared readonly().
     */
    public function editable(bool $editable = true): static
    {
        $this->editableView = $editable;

        return $this;
    }

    /** The resource class, or null in the markup mode. */
    public function resourceClass(): ?string
    {
        return $this->resourceClass;
    }

    /**
     * Hidden, in the resource mode, from a user without the resource's view
     * permission, and when the resource is not one of the current panel's.
     */
    public function isVisible(): bool
    {
        if (! parent::isVisible()) {
            return false;
        }
        if ($this->resourceClass === null) {
            return true;
        }

        return UserWidgets::resourceAvailable($this->resourceClass::slug(), $this->resourceView());
    }

    /**
     * The source part of data(): the mode and what it needs.
     *
     * @return array<string, mixed>
     */
    protected function sourceData(): array
    {
        if ($this->resourceClass !== null) {
            $permission = $this->resourceClass::permission();

            return [
                'mode' => 'resource',
                'resource' => $this->resourceClass::slug(),
                'filters' => (object) $this->presetFilters,
                'search' => $this->presetSearch,
                'editable' => $this->editableView && PermissionCheck::allows($permission.'.update'),
                'content' => null,
            ];
        }

        $markup = is_callable($this->markup) ? ($this->markup)() : $this->markup;

        return [
            'mode' => 'markup',
            'resource' => null,
            'content' => (string) ($markup ?? ''),
        ];
    }
}
