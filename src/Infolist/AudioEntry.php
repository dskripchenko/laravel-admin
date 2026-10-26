<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Infolist;

use Dskripchenko\LaravelAdmin\Support\MediaUrl;

/**
 * An audio recording of the record, played by the kit's UidAudio — the
 * one-line mini player by default.
 *
 *     AudioEntry::make('call_recording')->disk('local')
 *     AudioEntry::make('episode_url')->full()->waveform()
 *
 * The value is a URL, a storage path (with disk()), an uploaded file
 * (`{url, mime, …}`) or a list of them — then a playlist.
 */
final class AudioEntry extends Entry
{
    public function entryType(): string
    {
        return 'audio';
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

    /** The full player — title, volume, speed — instead of the mini one. */
    public function full(bool $full = true): static
    {
        $this->attributes['variant'] = $full ? 'default' : 'mini';

        return $this;
    }

    /** The waveform as the scrub bar, decoded in the browser. */
    public function waveform(bool $on = true): static
    {
        $this->attributes['waveform'] = $on;

        return $this;
    }

    /** Precomputed peaks (0..1) from another attribute of the record. */
    public function peaksFrom(string $attribute): static
    {
        $this->attributes['peaksFrom'] = $attribute;

        return $this;
    }

    /** Remembers the playback position per record. */
    public function resume(bool $on = true): static
    {
        $this->attributes['resume'] = $on;

        return $this;
    }
}
