<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Infolist;

/**
 * Stored Markdown, rendered: the kit's UidMarkdown escapes the source first
 * (raw HTML shows as text), and the panel's block languages — diagrams,
 * BPMN, API blocks, kanban, calendar — draw as themselves.
 *
 *     MarkdownEntry::make('body')
 *
 * A Field\Markdown or Field\MarkdownEditor on a resource's form is shown
 * this way on its view page by itself.
 */
final class MarkdownEntry extends Entry
{
    public function entryType(): string
    {
        return 'markdown';
    }
}
