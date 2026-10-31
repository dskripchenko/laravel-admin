<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

use InvalidArgumentException;

/**
 * A Markdown editor that edits like a word processor, the editor of the
 * package @dskripchenko/markdown-editor: a visual mode (the panel's WYSIWYG engine
 * with its toolbar, slash menu and live blocks), the source, and both side
 * by side. The value is a Markdown string; unlike Field\Markdown (a text
 * area with a preview) the user need not know the syntax. The panel's block
 * languages — diagrams, BPMN, API blocks, kanban, calendar — draw inside it.
 *
 *     MarkdownEditor::make('body')
 *         ->modes(['visual', 'source'])
 *         ->placeholder('Write…')
 *         ->minHeight(320)
 */
final class MarkdownEditor extends Field
{
    public const MODES = ['visual', 'source', 'split'];

    public function fieldType(): string
    {
        return 'markdown_editor';
    }

    /**
     * The modes offered, in this order; the first is shown first. All three
     * by default: visual, source, split.
     *
     * @param  array<array-key, string>  $modes
     */
    public function modes(array $modes): static
    {
        if ($modes === []) {
            throw new InvalidArgumentException('MarkdownEditor->modes() needs at least one mode');
        }
        foreach ($modes as $mode) {
            if (! in_array($mode, self::MODES, true)) {
                throw new InvalidArgumentException("Unknown Markdown editor mode `{$mode}`: ".implode(', ', self::MODES));
            }
        }
        $this->attributes['modes'] = array_values(array_unique($modes));

        return $this;
    }

    /** The smallest height of the editing area: 320 (px) or '40vh'; 240px by default. */
    public function minHeight(int|string $height): static
    {
        $this->attributes['minHeight'] = is_int($height) ? $height.'px' : $height;

        return $this;
    }

    /** The largest height of the editing area; past it the area scrolls. */
    public function maxHeight(int|string $height): static
    {
        $this->attributes['maxHeight'] = is_int($height) ? $height.'px' : $height;

        return $this;
    }
}
