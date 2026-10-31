<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

/**
 * A kanban board written as markup — columns, cards with tags, assignees and
 * due dates — drawn by the kit's UidKanban. Read-only unless editable():
 * then cards move between columns, are added and changed, and each change is
 * sent to a screen method as the new markup. See MarkupLayout.
 *
 *     Layout::kanban('board')                     // $state['board']
 *         ->editable('saveBoard', permission: 'tasks.edit')
 *         ->lockable('lockBoard')
 *         ->locked('board_locked')
 */
final class Kanban extends MarkupLayout
{
    public function type(): string
    {
        return 'kanban';
    }
}
