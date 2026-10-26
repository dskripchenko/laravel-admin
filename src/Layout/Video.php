<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

use Dskripchenko\LaravelAdmin\Layout\Concerns\PlaysMedia;
use Dskripchenko\LaravelAdmin\Support\MediaUrl;
use InvalidArgumentException;

/**
 * A video player — UidVideo of the UI kit: scrub bar with chapters and
 * thumbnail preview, volume, speed, quality and subtitle menus, full screen,
 * picture-in-picture, keyboard shortcuts. HLS (`.m3u8`) plays through the
 * kit's adapter, loaded only when such a source appears.
 *
 *     Layout::video('https://cdn.example/intro.mp4')
 *     Layout::video('videos/intro.mp4')->disk('public')
 *         ->poster('videos/intro.jpg')
 *         ->subtitles('videos/intro.en.vtt', 'en', 'English', default: true)
 *         ->chapters('videos/intro.chapters.vtt')
 *         ->thumbnails('videos/intro.thumbs.vtt')
 *     Layout::video([
 *         ['src' => '/media/intro-1080.mp4', 'label' => '1080p'],
 *         ['src' => '/media/intro-720.mp4', 'label' => '720p'],
 *     ])
 *     Layout::video('/streams/intro/master.m3u8')   // HLS, by the extension
 *     Layout::video('recording')                    // $state['recording']
 *
 * A string that is neither a URL nor a path with a media extension is a key
 * of the screen's state; fromState() says so explicitly.
 */
final class Video extends Layout
{
    use PlaysMedia;

    /** @var list<array{src: string, type?: string, label?: string}> */
    private array $sources = [];

    /** @var list<array{src: string, kind: string, srclang?: string, label?: string, default?: bool}> */
    private array $tracks = [];

    private ?string $poster = null;

    private ?string $thumbnails = null;

    /**
     * @param  string|array<int|string, mixed>|null  $source  A URL, a storage path, a state key, a source
     *                                                        `['src' => …, 'type' => …, 'label' => …]`
     *                                                        or a list of sources.
     */
    public static function make(string|array|null $source = null): self
    {
        $instance = new self;
        if (is_string($source)) {
            if (MediaUrl::looksLikeMedia($source)) {
                $instance->source($source);
            } else {
                $instance->fromState($source);
            }
        } elseif (is_array($source)) {
            $list = array_is_list($source) ? $source : [$source];
            foreach ($list as $item) {
                if (is_string($item)) {
                    $instance->source($item);
                } elseif (is_array($item) && is_string($item['src'] ?? null)) {
                    $instance->source(
                        $item['src'],
                        isset($item['label']) ? (string) $item['label'] : null,
                        isset($item['type']) ? (string) $item['type'] : null,
                    );
                } else {
                    throw new InvalidArgumentException('Layout::video() takes sources as strings or arrays with `src`');
                }
            }
        }

        return $instance;
    }

    public function type(): string
    {
        return 'video';
    }

    /**
     * Adds a source. Sources go in order of preference: the browser gets the
     * first one it can play, the next one when it fails. Two or more with a
     * label form the quality menu (`1080p`, `720p`).
     */
    public function source(string $src, ?string $label = null, ?string $type = null): self
    {
        $source = ['src' => $src];
        if ($type !== null && $type !== '') {
            $source['type'] = $type;
        }
        if ($label !== null && $label !== '') {
            $source['label'] = $label;
        }
        $this->sources[] = $source;

        return $this;
    }

    /** A source of one quality, for the quality menu. */
    public function quality(string $label, string $src, ?string $type = null): self
    {
        return $this->source($src, $label, $type);
    }

    /**
     * An HLS stream. A `.m3u8` address is recognised by itself; this names
     * the type for one that is not (`/streams/42/playlist`).
     */
    public function hls(string $src, ?string $label = null): self
    {
        return $this->source($src, $label, 'application/x-mpegURL');
    }

    /** The MIME type of the sources given without one: `video/webm`, `application/x-mpegURL`. */
    public function mime(string $type): self
    {
        foreach ($this->sources as $i => $source) {
            $this->sources[$i]['type'] ??= $type;
        }

        return $this;
    }

    /** The image shown before playback. */
    public function poster(string $poster): self
    {
        $this->poster = $poster;

        return $this;
    }

    /** The caption above the player, and its accessible name. */
    public function title(string $title): self
    {
        $this->props['title'] = $title;

        return $this;
    }

    /** WebVTT subtitles in one language; the first `default` one is on at start. */
    public function subtitles(string $src, string $lang, ?string $label = null, bool $default = false): self
    {
        return $this->track('subtitles', $src, $lang, $label, $default);
    }

    /** WebVTT captions — subtitles that also describe sounds. */
    public function captions(string $src, string $lang, ?string $label = null, bool $default = false): self
    {
        return $this->track('captions', $src, $lang, $label, $default);
    }

    /** WebVTT chapters: gaps in the scrub bar and the chapter's title over it. */
    public function chapters(string $src, ?string $lang = null): self
    {
        return $this->track('chapters', $src, $lang, null, false);
    }

    /**
     * WebVTT tracks as arrays: `['src' => …, 'kind' => 'subtitles'|'captions'|'chapters',
     * 'srclang' => …, 'label' => …, 'default' => bool]`.
     *
     * @param  list<array<string, mixed>>  $tracks
     */
    public function tracks(array $tracks): self
    {
        foreach ($tracks as $track) {
            if (! is_string($track['src'] ?? null)) {
                throw new InvalidArgumentException('A video track needs `src`');
            }
            $this->track(
                (string) ($track['kind'] ?? 'subtitles'),
                $track['src'],
                isset($track['srclang']) ? (string) $track['srclang'] : null,
                isset($track['label']) ? (string) $track['label'] : null,
                (bool) ($track['default'] ?? false),
            );
        }

        return $this;
    }

    /**
     * A WebVTT file whose cues point to sprite tiles
     * (`sprite.jpg#xywh=0,0,160,90`): the preview over the scrub bar.
     */
    public function thumbnails(string $vtt): self
    {
        $this->thumbnails = $vtt;

        return $this;
    }

    /** Starts playing at once — muted, since browsers block unmuted autoplay. */
    public function autoplay(bool $autoplay = true): self
    {
        $this->props['autoplay'] = $autoplay;
        if ($autoplay) {
            $this->props['muted'] = true;
        }

        return $this;
    }

    public function muted(bool $muted = true): self
    {
        $this->props['muted'] = $muted;

        return $this;
    }

    /** `false` leaves the bare element with the browser's own controls. */
    public function controls(bool $controls = true): self
    {
        $this->props['controls'] = $controls;

        return $this;
    }

    /**
     * The box ratio before the video's size is known: '16 / 9' (the
     * default), '4:3', 1.85.
     */
    public function aspectRatio(string|int|float $ratio): self
    {
        if (is_string($ratio) && preg_match('~^\s*(\d+(?:\.\d+)?)\s*[:/]\s*(\d+(?:\.\d+)?)\s*$~', $ratio, $m) === 1) {
            $ratio = $m[1].' / '.$m[2];
        } elseif (is_string($ratio) && ! is_numeric(trim($ratio))) {
            throw new InvalidArgumentException("Unknown aspect ratio `{$ratio}`: '16 / 9', '4:3' or a number");
        }
        $this->props['aspectRatio'] = is_string($ratio) ? trim($ratio) : (string) $ratio;

        return $this;
    }

    /** Milliseconds without activity before the controls hide while playing; 2500 by default. */
    public function autoHide(int $milliseconds): self
    {
        $this->props['autoHide'] = max(0, $milliseconds);

        return $this;
    }

    /**
     * @return list<array{src: string, type?: string, label?: string}>
     */
    public function sources(): array
    {
        return $this->sources;
    }

    private function track(string $kind, string $src, ?string $lang, ?string $label, bool $default): self
    {
        if (! in_array($kind, ['subtitles', 'captions', 'chapters'], true)) {
            throw new InvalidArgumentException("Unknown track kind `{$kind}`: subtitles, captions or chapters");
        }
        $track = ['src' => $src, 'kind' => $kind];
        if ($lang !== null && $lang !== '') {
            $track['srclang'] = $lang;
        }
        if ($label !== null && $label !== '') {
            $track['label'] = $label;
        }
        if ($default) {
            $track['default'] = true;
        }
        $this->tracks[] = $track;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        // The sources and tracks travel once, at the top level.
        $array['sources'] = array_map(
            fn (array $s): array => ['src' => (string) $this->url($s['src'])] + $s,
            $this->sources,
        );
        // Track labels are content — a language's own name, "English" — and
        // are not run through the panel's translations.
        $array['tracks'] = array_map(
            fn (array $t): array => ['src' => (string) $this->url($t['src'])] + $t,
            $this->tracks,
        );
        $array['poster'] = $this->url($this->poster);
        $array['thumbnails'] = $this->url($this->thumbnails);

        return [...$array, ...$this->mediaArray()];
    }
}
