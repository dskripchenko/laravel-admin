<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

use Dskripchenko\LaravelAdmin\Contracts\Renderable;

/**
 * Абстрактный layout-слой.
 *
 * Композитный паттерн: layout содержит дочерние layouts/fields/actions
 * (любые `Renderable`). Концретные подклассы (Rows, Columns, Tabs, Block,
 * View, ...) определяют свой `type()` и опциональные props.
 *
 * Статические фабрики (`Layout::rows`, `Layout::columns`, ...) — точка входа
 * в DSL; возвращают экземпляры конкретных подклассов.
 */
abstract class Layout implements Renderable
{
    /** Стабильный id слоя — для частичных обновлений и cache-ключей. */
    protected ?string $id = null;

    /** @var array<string, mixed> Type-specific props (название, иконка, ratios, ...). */
    protected array $props = [];

    /** @var list<Renderable> */
    protected array $children = [];

    /** @var bool|callable(): bool */
    protected $visibility = true;

    abstract public function type(): string;

    /* -----------------------------------------------------------------
     * Статические фабрики — DSL вход
     * ----------------------------------------------------------------- */

    /**
     * @param  list<Renderable>  $fields
     */
    public static function rows(array $fields = []): Rows
    {
        return Rows::make($fields);
    }

    /**
     * @param  list<Renderable>  $children
     */
    public static function columns(array $children = []): Columns
    {
        return Columns::make($children);
    }

    /**
     * @param  array<string, Renderable|list<Renderable>>  $tabs  Map label => layout/fields.
     */
    public static function tabs(array $tabs = []): Tabs
    {
        return Tabs::make($tabs);
    }

    /**
     * @param  list<Renderable>  $children
     */
    public static function block(string $title, array $children = []): Block
    {
        return Block::make($title, $children);
    }

    /**
     * Произвольный Vue-компонент с props.
     *
     * @param  array<string, mixed>  $props
     */
    public static function view(string $component, array $props = []): View
    {
        return View::make($component, $props);
    }

    /**
     * @param  array<string, Renderable|list<Renderable>>  $sections
     */
    public static function accordion(array $sections = []): Accordion
    {
        return Accordion::make($sections);
    }

    /**
     * @param  list<Renderable>  $children
     */
    public static function modal(string $title = '', array $children = []): Modal
    {
        return Modal::make($title, $children);
    }

    /**
     * @param  list<Renderable>  $children
     */
    public static function drawer(string $title = '', array $children = []): Drawer
    {
        return Drawer::make($title, $children);
    }

    /**
     * @param  list<Renderable>  $children
     */
    public static function wrapper(array $children = []): Wrapper
    {
        return Wrapper::make($children);
    }

    /**
     * @param  list<Step>  $steps
     */
    public static function wizard(array $steps = []): Wizard
    {
        return Wizard::make($steps);
    }

    /**
     * @param  list<Renderable>  $children
     */
    public static function step(string $title, array $children = []): Step
    {
        return Step::make($title, $children);
    }

    /**
     * @param  list<\Dskripchenko\LaravelAdmin\Infolist\Entry>  $entries
     */
    public static function infolist(array $entries = []): Infolist
    {
        return Infolist::make($entries);
    }

    /**
     * @param  list<\Dskripchenko\LaravelAdmin\Widget\Widget>  $widgets
     */
    public static function dashboard(array $widgets = []): Dashboard
    {
        return Dashboard::make($widgets);
    }

    /* -----------------------------------------------------------------
     * Fluent API
     * ----------------------------------------------------------------- */

    public function withId(string $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function id(): string
    {
        if ($this->id === null) {
            $this->id = 'l-'.bin2hex(random_bytes(4));
        }

        return $this->id;
    }

    /**
     * @param  bool|callable(): bool  $cond
     */
    public function canSee(bool|callable $cond): static
    {
        $this->visibility = $cond;

        return $this;
    }

    public function isVisible(): bool
    {
        return is_callable($this->visibility)
            ? (bool) ($this->visibility)()
            : (bool) $this->visibility;
    }

    /* -----------------------------------------------------------------
     * Сериализация
     * ----------------------------------------------------------------- */

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $children = [];
        foreach ($this->children as $child) {
            if (! $child->isVisible()) {
                continue;
            }
            $children[] = $child->toArray();
        }

        return [
            'id' => $this->id(),
            'type' => $this->type(),
            'props' => $this->props,
            'children' => $children,
        ];
    }

    public function toJson(): string
    {
        return (string) json_encode($this->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
