<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Resource\Screens;

use Dskripchenko\LaravelAdmin\Action\Link;
use Dskripchenko\LaravelAdmin\Field\Field;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Screen\Screen;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The base class of the screens generated over a resource: list, create, edit
 * and view.
 *
 * ScreenRegistry instantiates each Generated*Screen bound to a particular
 * resource, and its compile() then produces the page's JSON description for
 * the SPA. A subclass overrides only kind(), name(), layout() and
 * commandBar(); the shared plumbing — the permissions, the slug, the type —
 * lives here.
 */
abstract class GeneratedScreen extends Screen
{
    public function __construct(protected readonly Resource $resource) {}

    /**
     * Which kind of screen this is: list, create, edit or view.
     */
    abstract public function kind(): string;

    /**
     * The slug in the admin API: `{resource-slug}.{kind}`.
     */
    public static function slug(): string
    {
        // A GeneratedScreen is never used without a resource: the slug comes
        // from the instance's kind(). This static method exists only for
        // compatibility with Screen::slug(), and returns the class basename
        // without the 'Screen' suffix.
        return parent::slug();
    }

    /**
     * The instance's slug, bound to its resource.
     */
    public function instanceSlug(): string
    {
        return $this->resource::slug().'.'.$this->kind();
    }

    /**
     * The permission required, taken from Resource::permission().{kind}.
     *
     * @return list<string>|string|null
     */
    public function permission(): array|string|null
    {
        $base = $this->resource::permission();
        $action = match ($this->kind()) {
            'list', 'view' => 'view',
            'create' => 'create',
            'edit' => 'update',
            default => 'view',
        };

        return $base.'.'.$action;
    }

    public function description(): ?string
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function compile(mixed ...$params): array
    {
        $base = parent::compile(...$params);

        return [
            ...$base,
            'type' => 'generated.'.$this->kind(),
            'resource_slug' => $this->resource::slug(),
        ];
    }

    /**
     * Loads a record by id, or throws a 404. The edit and view screens' query()
     * uses it, and it returns the payload in query()'s shape: the record and
     * the id.
     *
     * @return array<string, mixed>
     */
    protected function queryRecord(mixed $id): array
    {
        if ($id === null) {
            return ['record' => []];
        }

        $record = $this->resource->modelQuery()->find($id);
        if ($record === null) {
            throw new NotFoundHttpException("Record {$id} not found");
        }

        return ['record' => $record->toArray(), 'id' => $record->getKey()];
    }

    /**
     * The "Back" link to the resource's index page.
     */
    protected function buildBackLink(string $label = 'Назад'): Link
    {
        return Link::make($label)->href('/admin/r/'.$this->resource::slug());
    }

    /**
     * Filters the resource's fields by context: create or update.
     *
     * @return list<Field>
     */
    protected function filterFieldsBy(string $context): array
    {
        return array_values(array_filter(
            $this->resource->fields(),
            static fn (Field $f): bool => $f->appliesTo($context),
        ));
    }
}
