<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * A kanban board written as markup — columns, cards with tags, assignees and
 * due dates — edited in the form with the kit's UidKanban: cards are moved
 * between columns, added and changed, and each change goes into the form's
 * value. The value is the markup, a string in a text column. See MarkupField.
 *
 *     Kanban::make('board')
 *         ->title('Board')
 *         ->height(480)
 */
final class Kanban extends MarkupField
{
    public function fieldType(): string
    {
        return 'kanban';
    }
}
