<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Infolist;

use Dskripchenko\LaravelAdmin\Support\MediaUrl;

/**
 * A video of the record, played by the kit's UidVideo.
 *
 *     VideoEntry::make('recording_path')->disk('public')->posterFrom('cover_path')
 *
 * The value is a URL, a storage path (with disk()), an uploaded file
 * (`{url, mime, …}` — what a FileUpload stores) or a list of sources.
 */
final class VideoEntry extends Entry
{
    public function entryType(): string
    {
        return 'video';
    }

    /**
     * Storage paths are resolved through this disk: its public URL, or the
     * panel's streaming endpoint for a local disk without one (list it in
     * `admin.uploads.servable_disks`).
     */
    public function disk(string $disk): static
    {
        $this->attributes['disk'] = MediaUrl::base($disk);

        return $this;
    }

    /** A poster image, by URL (or by path, with disk()). */
    public function poster(string $poster): static
    {
        $this->attributes['poster'] = $poster;

        return $this;
    }

    /** The poster from another attribute of the record. */
    public function posterFrom(string $attribute): static
    {
        $this->attributes['posterFrom'] = $attribute;

        return $this;
    }

    /** '16 / 9' (the default), '4:3'… */
    public function aspectRatio(string $ratio): static
    {
        $this->attributes['aspectRatio'] = str_replace(':', ' / ', $ratio);

        return $this;
    }

    /** The widest the player gets: 640 (px) or '40rem'; 640px by default. */
    public function maxWidth(int|string $width): static
    {
        $this->attributes['maxWidth'] = is_int($width) ? $width.'px' : $width;

        return $this;
    }

    /** Remembers the playback position per record. */
    public function resume(bool $on = true): static
    {
        $this->attributes['resume'] = $on;

        return $this;
    }
}
