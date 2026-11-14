<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Widget;

use Dskripchenko\LaravelAdmin\Widget\Concerns\HasListItems;

/**
 * A list without a table header: each row is a caption, a secondary text, a
 * badge with its tone and a link — queues with their counts, the latest
 * failures, drafts.
 *
 *     ListWidget::make()->title('Очереди')->size(4)
 *         ->item('Обращения и претензии', 'старейшее 26 ч', 12, 'danger', '/screens/queue?tab=claims')
 *         ->item('Заявки', '3 ч', 31, 'neutral', '/screens/queue?tab=requests')
 *         ->emptyText('Очереди пусты');
 *
 * The rows may be computed in data() of a subclass: fill them with item() or
 * items() and return parent::data().
 */
class ListWidget extends Widget
{
    use HasListItems;

    public function widgetType(): string
    {
        return 'list';
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return [
            'items' => $this->listItemsPayload(),
            'emptyText' => $this->listEmptyTextPayload(),
        ];
    }
}
