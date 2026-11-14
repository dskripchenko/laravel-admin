<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Widget;

use Dskripchenko\LaravelAdmin\Menu\MenuRegistry;
use Dskripchenko\LaravelAdmin\Menu\QuickCreate;
use Dskripchenko\LaravelAdmin\Panel\Panels;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;

/**
 * A row of buttons that lead somewhere: a news item, a blog post, a
 * promotion. The items are those of the top bar's «+ Create» (QuickCreate),
 * shown by the same rules — a resource's create page to the users with its
 * `.create` permission, an arbitrary link to those with every key of its
 * permission(). The first button the user sees is the primary one.
 *
 *     ActionsWidget::make()->title('Создать')->size(12)->actions([
 *         QuickCreate::resource(NewsResource::class),
 *         QuickCreate::resource(PostResource::class, 'Статья блога'),
 *         QuickCreate::make('import', 'Импорт прайса', '/screens/price-import')->permission('admin.prices.import'),
 *     ]);
 *
 *     ActionsWidget::make()->fromQuickCreate();   // the panel's «+ Create» items
 *
 * The resources are resolved only when the widget's data is computed —
 * never while the routes are built. A user who may open none of the items
 * gets an empty widget; hide it with canSee() when that matters.
 */
class ActionsWidget extends Widget
{
    /** @var list<QuickCreate> */
    private array $actions = [];

    private bool $fromQuickCreate = false;

    public function widgetType(): string
    {
        return 'actions';
    }

    /**
     * Adds a button.
     */
    public function action(QuickCreate $action): static
    {
        $this->actions[] = $action;

        return $this;
    }

    /**
     * Adds several buttons; see action().
     *
     * @param  iterable<QuickCreate>  $actions
     */
    public function actions(iterable $actions): static
    {
        foreach ($actions as $action) {
            $this->action($action);
        }

        return $this;
    }

    /**
     * Puts the panel's «+ Create» items (MenuRegistry::quickCreate()) first,
     * before the ones added here.
     */
    public function fromQuickCreate(bool $from = true): static
    {
        $this->fromQuickCreate = $from;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        $panel = Panels::current()->id;
        $actions = $this->actions;
        if ($this->fromQuickCreate) {
            $actions = [...app(MenuRegistry::class)->quickCreateItems($panel), ...$actions];
        }

        $resources = app(ResourceRegistry::class);
        $items = [];
        $seen = [];
        foreach ($actions as $action) {
            try {
                $item = $action->toArray($resources, $panel);
            } catch (\Throwable $e) {
                report($e);

                continue;
            }
            if ($item === null || isset($seen[$item['key']])) {
                continue;
            }
            $seen[$item['key']] = true;
            $items[] = $item;
        }

        return ['items' => $items];
    }
}
