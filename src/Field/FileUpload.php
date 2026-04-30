<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * Загрузка файлов.
 *
 * SPA загружает файл через `/api/admin/uploads/upload` (фаза P5/upload-stack)
 * и помещает в state {id, url, name, mime, size}. Backend получает либо id
 * uploaded record'а, либо raw file (multipart) — выбор за реализатором.
 */
final class FileUpload extends Field
{
    public function fieldType(): string
    {
        return 'file';
    }

    public function multiple(bool $multiple = true): static
    {
        $this->attributes['multiple'] = $multiple;

        return $this;
    }

    /**
     * MIME-типы или расширения. Браузер фильтрует выбор файлов.
     *
     * @param  list<string>|string  $accept
     */
    public function accept(array|string $accept): static
    {
        $this->attributes['accept'] = is_array($accept)
            ? implode(',', $accept)
            : $accept;

        return $this;
    }

    /**
     * Максимальный размер одного файла в килобайтах.
     */
    public function maxSize(int $kilobytes): static
    {
        $this->attributes['maxSize'] = $kilobytes;

        return $this;
    }

    /**
     * Максимальное количество файлов (при multiple=true).
     */
    public function maxFiles(int $max): static
    {
        $this->attributes['maxFiles'] = $max;

        return $this;
    }

    /**
     * Включить режим image-only с превью.
     */
    public function image(bool $image = true): static
    {
        $this->attributes['image'] = $image;
        if ($image && ! isset($this->attributes['accept'])) {
            $this->attributes['accept'] = 'image/*';
        }

        return $this;
    }

    /**
     * Disk из config/filesystems.php (default uploads idёт в `local`).
     */
    public function disk(string $disk): static
    {
        $this->attributes['disk'] = $disk;

        return $this;
    }
}
