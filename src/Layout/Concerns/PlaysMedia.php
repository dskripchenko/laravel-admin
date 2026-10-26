<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout\Concerns;

use Dskripchenko\LaravelAdmin\Support\MediaUrl;
use InvalidArgumentException;

/**
 * What the video and the audio layouts share: where the media comes from (a
 * URL, a storage path on a disk, or a key of the screen's state) and the
 * player's common options.
 *
 * @phpstan-require-extends \Dskripchenko\LaravelAdmin\Layout\Layout
 */
trait PlaysMedia
{
    /** The disk storage paths are resolved through; null — the values are URLs. */
    protected ?string $disk = null;

    /** The state key holding the media, read in the browser. */
    protected ?string $stateKey = null;

    /** @var array<string, mixed> Passed to the kit's HLS adapter. */
    protected array $hlsOptions = [];

    /**
     * Storage paths are resolved through this disk: `Storage::url()` for a
     * disk with a public URL, the panel's streaming endpoint for a local one
     * without (list it in `admin.uploads.servable_disks`). Full URLs are left
     * alone. Applies to the posters and the WebVTT tracks too, and to the
     * values read from the state.
     */
    public function disk(string $disk): static
    {
        $this->disk = $disk;

        return $this;
    }

    /**
     * Reads the media from a key of the screen's state (or of the record):
     * a URL or a path, an uploaded file (`{url, mime, …}`), or a list of them.
     */
    public function fromState(string $key): static
    {
        if ($key === '') {
            throw new InvalidArgumentException('fromState() needs a state key');
        }
        $this->stateKey = $key;

        return $this;
    }

    /**
     * Remembers the playback position in the browser (localStorage) and
     * resumes from it. Without a key the media's address is the key.
     */
    public function resume(?string $key = null): static
    {
        $this->props['resumeKey'] = $key ?? true;

        return $this;
    }

    public function loop(bool $loop = true): static
    {
        $this->props['loop'] = $loop;

        return $this;
    }

    /** 'none', 'metadata' (the default) or 'auto'. */
    public function preload(string $preload): static
    {
        if (! in_array($preload, ['none', 'metadata', 'auto'], true)) {
            throw new InvalidArgumentException("Unknown preload `{$preload}`: none, metadata or auto");
        }
        $this->props['preload'] = $preload;

        return $this;
    }

    /**
     * Starting this player pauses the others on the page; on by default.
     * `false` lets it play alongside them.
     */
    public function exclusive(bool $exclusive = true): static
    {
        $this->props['exclusive'] = $exclusive;

        return $this;
    }

    /** Keyboard shortcuts while the player has focus; on by default. */
    public function hotkeys(bool $on = true): static
    {
        $this->props['hotkeys'] = $on;

        return $this;
    }

    /**
     * The speed menu.
     *
     * @param  array<array-key, int|float>  $rates
     */
    public function rates(array $rates): static
    {
        $this->props['rates'] = array_values(array_map(static fn (int|float $r): float => (float) $r, $rates));

        return $this;
    }

    /** '', 'anonymous' or 'use-credentials' — for media and tracks from another origin. */
    public function crossorigin(string $mode = 'anonymous'): static
    {
        if (! in_array($mode, ['', 'anonymous', 'use-credentials'], true)) {
            throw new InvalidArgumentException("Unknown crossorigin `{$mode}`");
        }
        $this->props['crossorigin'] = $mode;

        return $this;
    }

    /** The widest the player gets: 640 (px) or '40rem'; the full width by default. */
    public function maxWidth(int|string $width): static
    {
        $this->props['maxWidth'] = is_int($width) ? $width.'px' : $width;

        return $this;
    }

    /**
     * Options of the kit's HLS adapter: preferNative, startLevel,
     * audioLanguage, maxBufferAhead, maxBufferBehind, retries,
     * initialBandwidth, capToPlayerSize.
     *
     * @param  array<string, mixed>  $options
     */
    public function hlsOptions(array $options): static
    {
        $this->hlsOptions = $options;

        return $this;
    }

    /** A URL as it is, a storage path through the disk. */
    protected function url(?string $value): ?string
    {
        return $value === null ? null : MediaUrl::resolve($value, $this->disk);
    }

    /**
     * The part of the serialized layout every media layout has: the state
     * key, the disk's address for the values read there, the HLS options.
     *
     * @return array<string, mixed>
     */
    protected function mediaArray(): array
    {
        return [
            'source' => $this->stateKey,
            'disk' => $this->disk !== null && $this->stateKey !== null ? MediaUrl::base($this->disk) : null,
            'hlsOptions' => $this->hlsOptions === [] ? null : $this->hlsOptions,
        ];
    }
}
