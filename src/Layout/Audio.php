<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

use Dskripchenko\LaravelAdmin\Layout\Concerns\PlaysMedia;
use Dskripchenko\LaravelAdmin\Support\MediaUrl;
use InvalidArgumentException;

/**
 * An audio player — UidAudio of the UI kit: a row with play, the scrub bar
 * (or the waveform), time, volume and speed; a playlist with previous/next
 * and a track list; a one-line `mini` variant. An `.m3u8` source (audio-only
 * HLS) plays through the kit's adapter.
 *
 *     Layout::audio('/media/episode-12.mp3')->title('Episode 12')->artist('The Podcast')->waveform()
 *     Layout::audio('calls/2026-10-26.ogg')->disk('local')->mini()
 *     Layout::audio([
 *         ['src' => '/media/a.mp3', 'title' => 'Intro', 'artist' => 'Band', 'duration' => 64],
 *         ['src' => '/media/b.mp3', 'title' => 'Outro'],
 *     ])->waveform()
 *     Layout::audio('recordings')    // $state['recordings']: a URL, a file or a list
 */
final class Audio extends Layout
{
    use PlaysMedia;

    /** @var list<array{src: string, type?: string, title?: string, artist?: string, duration?: int|float, peaks?: list<float>}> */
    private array $tracks = [];

    /** @var list<float>|null The peaks of a single track. */
    private ?array $peaks = null;

    /**
     * @param  string|array<int|string, mixed>|null  $source  A URL, a storage path, a state key, a track
     *                                                        `['src' => …, 'title' => …]` or a playlist.
     */
    public static function make(string|array|null $source = null): self
    {
        $instance = new self;
        if (is_string($source)) {
            if (MediaUrl::looksLikeMedia($source)) {
                $instance->track($source);
            } else {
                $instance->fromState($source);
            }
        } elseif (is_array($source)) {
            $list = array_is_list($source) ? $source : [$source];
            foreach ($list as $item) {
                if (is_string($item)) {
                    $instance->track($item);
                } elseif (is_array($item) && is_string($item['src'] ?? null)) {
                    $instance->track($item['src'], $item);
                } else {
                    throw new InvalidArgumentException('Layout::audio() takes tracks as strings or arrays with `src`');
                }
            }
        }

        return $instance;
    }

    public function type(): string
    {
        return 'audio';
    }

    /**
     * Adds a track; two or more make a playlist.
     *
     * @param  array<string, mixed>  $meta  title, artist, duration (seconds), peaks (0..1), type
     */
    public function track(string $src, array $meta = []): self
    {
        $track = ['src' => $src];
        foreach (['type', 'title', 'artist'] as $key) {
            if (isset($meta[$key]) && is_string($meta[$key]) && $meta[$key] !== '') {
                $track[$key] = $meta[$key];
            }
        }
        if (isset($meta['duration']) && is_numeric($meta['duration'])) {
            $track['duration'] = $meta['duration'] + 0;
        }
        if (isset($meta['peaks']) && is_array($meta['peaks'])) {
            $track['peaks'] = self::peakList($meta['peaks']);
        }
        $this->tracks[] = $track;

        return $this;
    }

    /** The title of a single track, shown above the scrub bar. */
    public function title(string $title): self
    {
        $this->props['title'] = $title;

        return $this;
    }

    /** The artist of a single track, shown next to the title. */
    public function artist(string $artist): self
    {
        $this->props['artist'] = $artist;

        return $this;
    }

    /** The MIME type of the tracks given without one: `audio/ogg`, `application/x-mpegURL`. */
    public function mime(string $type): self
    {
        foreach ($this->tracks as $i => $track) {
            $this->tracks[$i]['type'] ??= $type;
        }

        return $this;
    }

    /**
     * Draws the waveform as the scrub bar. It is decoded in the browser
     * (the file has to be same-origin or CORS-readable); not for streams.
     */
    public function waveform(bool $on = true): self
    {
        $this->props['waveform'] = $on;

        return $this;
    }

    /**
     * Precomputed waveform peaks, 0..1, of a single track: the waveform is
     * drawn without decoding. For a playlist give `peaks` per track.
     *
     * @param  list<int|float>  $peaks
     */
    public function peaks(array $peaks): self
    {
        $this->peaks = self::peakList($peaks);

        return $this;
    }

    /** One line — play, a short bar, time — for lists and narrow places. */
    public function mini(bool $mini = true): self
    {
        $this->props['variant'] = $mini ? 'mini' : 'default';

        return $this;
    }

    /** The full row without the volume and speed controls. */
    public function compact(bool $compact = true): self
    {
        $this->props['showVolume'] = ! $compact;
        $this->props['showRate'] = ! $compact;

        return $this;
    }

    /** The track list under a playlist; on by default. */
    public function playlist(bool $show = true): self
    {
        $this->props['showPlaylist'] = $show;

        return $this;
    }

    /** Plays the next playlist track when one ends; on by default. */
    public function autoAdvance(bool $on = true): self
    {
        $this->props['autoAdvance'] = $on;

        return $this;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function tracks(): array
    {
        return $this->tracks;
    }

    /**
     * @param  array<array-key, mixed>  $peaks
     * @return list<float>
     */
    private static function peakList(array $peaks): array
    {
        return array_values(array_map(
            static fn (mixed $p): float => max(0.0, min(1.0, is_numeric($p) ? (float) $p : 0.0)),
            $peaks,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        $array['tracks'] = array_map(
            fn (array $t): array => ['src' => (string) $this->url($t['src'])] + $t,
            $this->tracks,
        );
        // The peaks travel once, at the top level: a long list would double the payload in `props`.
        $array['peaks'] = $this->peaks;

        return [...$array, ...$this->mediaArray()];
    }
}
