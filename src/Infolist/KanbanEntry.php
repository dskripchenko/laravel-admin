<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Infolist;

/**
 * A stored kanban board — the markup of the kit's UidKanban — drawn
 * read-only.
 *
 *     KanbanEntry::make('board')->height(420)
 *
 * A Field\Kanban on a resource's form is shown this way on its view page by
 * itself.
 */
final class KanbanEntry extends DocumentEntry
{
    public function entryType(): string
    {
        return 'kanban';
    }
}
