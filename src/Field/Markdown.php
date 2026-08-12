<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * A markdown editor with a preview.
 *
 * Which editor that is — CodeMirror, EasyMDE, another — the SPA decides; the
 * backend stores a plain markdown string. Rendering that markdown, for the
 * view mode or an infolist, is not this level's business.
 */
final class Markdown extends Field
{
    public function fieldType(): string
    {
        return 'markdown';
    }

    public function preview(bool $preview = true): static
    {
        $this->attributes['preview'] = $preview;

        return $this;
    }

    public function toolbar(bool $toolbar = true): static
    {
        $this->attributes['toolbar'] = $toolbar;

        return $this;
    }

    public function height(int|string $height): static
    {
        $this->attributes['height'] = $height;

        return $this;
    }

    /**
     * Allows dragging images into the editor. The upload endpoint comes from
     * the uploads stack.
     */
    public function uploadImages(bool $upload = true): static
    {
        $this->attributes['uploadImages'] = $upload;

        return $this;
    }
}
