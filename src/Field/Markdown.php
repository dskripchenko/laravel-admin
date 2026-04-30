<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * Markdown editor с предпросмотром.
 *
 * Конкретный editor (CodeMirror/EasyMDE/...) выбирается на стороне SPA;
 * backend хранит plain markdown-string. Конкретный rendering markdown'а
 * (для view-режима / Infolist) не делается на этом уровне.
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
     * Разрешить загрузку картинок drag-n-drop в editor.
     * Endpoint загрузки берётся из uploads-stack (фаза P5+).
     */
    public function uploadImages(bool $upload = true): static
    {
        $this->attributes['uploadImages'] = $upload;

        return $this;
    }
}
