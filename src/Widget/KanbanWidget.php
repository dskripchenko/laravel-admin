<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Widget;

use Dskripchenko\LaravelAdmin\Widget\Concerns\ShowsResourceView;

/**
 * A kanban board on a dashboard: a resource's kanban view or a board written
 * as markup.
 *
 *     KanbanWidget::make()->title('Open tasks')
 *         ->resource(TaskResource::class)         // Resource::kanban()
 *         ->filters(['assignee' => 'me'])
 *         ->editable();                            // drag cards, when the user may update
 *
 *     KanbanWidget::make()->title('Roadmap')
 *         ->markup("## Now\n- Billing #api\n\n## Next\n- Reports");
 *
 * In the resource mode the cards come from the resource's own board endpoint
 * — its scope, filters, permissions — and the widget is hidden from a user
 * who may not view the resource. See ShowsResourceView.
 */
class KanbanWidget extends Widget
{
    use ShowsResourceView;

    protected int $size = 12;

    protected ?int $rowSpan = 3;

    public function widgetType(): string
    {
        return 'kanban';
    }

    protected function resourceView(): string
    {
        return 'kanban';
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->sourceData();
    }
}
