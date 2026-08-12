<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * A file upload.
 *
 * The SPA uploads through `/api/admin/uploads/upload` and puts
 * {id, url, name, mime, size} into the state. The backend receives either the
 * uploaded record's id or the raw multipart file — whichever the implementer
 * prefers.
 */
class FileUpload extends Field
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
     * The MIME types or the extensions; the browser filters the file picker by them.
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
     * The largest a single file may be, in kilobytes.
     */
    public function maxSize(int $kilobytes): static
    {
        $this->attributes['maxSize'] = $kilobytes;

        return $this;
    }

    /**
     * The largest number of files, when multiple is true.
     */
    public function maxFiles(int $max): static
    {
        $this->attributes['maxFiles'] = $max;

        return $this;
    }

    /**
     * Turns on the image-only mode, with a preview.
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
     * The disk from config/filesystems.php; uploads go to `local` by default.
     */
    public function disk(string $disk): static
    {
        $this->attributes['disk'] = $disk;

        return $this;
    }
}
