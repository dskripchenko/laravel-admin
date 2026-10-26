<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Support;

use Dskripchenko\LaravelAdmin\Uploads\UploadController;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Addresses of media files for the players: a URL is left alone, a storage
 * path is turned into a URL through a disk.
 *
 *   - A disk with a public URL — `url` in its config, or a cloud driver
 *     (s3 and the like) — gives its own: `Storage::disk($disk)->url($path)`.
 *   - A local disk without one (the default `local`) is streamed by the
 *     panel's `uploads/serve` endpoint, behind the panel's session. The disk
 *     has to be listed in `admin.uploads.servable_disks`.
 *
 * Values that are only known in the browser — a screen's state, a record's
 * attribute — are resolved there: base() describes the disk's address as a
 * prefix and a suffix around the path, and how the path is encoded.
 */
final class MediaUrl
{
    /** A path the disk is asked for, to find where it puts the path in its URLs. */
    private const MARK = 'admin-media-path-mark';

    /** Extensions of the formats the players take, playlists included. */
    public const EXTENSIONS = [
        'mp4', 'm4v', 'mov', 'webm', 'ogv', 'mkv', 'm3u8',
        'mp3', 'm4a', 'm4b', 'aac', 'oga', 'ogg', 'opus', 'wav', 'flac', 'weba',
        'vtt', 'jpg', 'jpeg', 'png', 'webp', 'avif', 'gif',
    ];

    /**
     * Whether the string is already an address the browser can load: a
     * scheme (`https:`, `data:`, `blob:`), a protocol-relative or a
     * root-relative one.
     */
    public static function isAbsolute(string $url): bool
    {
        return str_starts_with($url, '/')
            || preg_match('~^[a-z][a-z0-9+.\-]*:~i', $url) === 1;
    }

    /**
     * Whether the string names a media file (a URL or a storage path) rather
     * than a key of the screen's state: it has a scheme or a slash, or ends in
     * one of the known extensions. A dotted state key (`media.url`) is not
     * one.
     */
    public static function looksLikeMedia(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }
        if (str_contains($value, '/') || self::isAbsolute($value)) {
            return true;
        }
        $path = (string) preg_replace('~[?#].*$~', '', $value);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, self::EXTENSIONS, true);
    }

    /**
     * The URL of a value: as it is when $disk is null or the value is already
     * an address, otherwise through the disk.
     */
    public static function resolve(string $value, ?string $disk): string
    {
        if ($disk === null || $value === '' || self::isAbsolute($value)) {
            return $value;
        }
        $path = ltrim($value, '/');

        return self::servedByPanel($disk)
            ? UploadController::serveUrl($disk, $path)
            : Storage::disk($disk)->url($path);
    }

    /**
     * The disk's address around a path, for the browser:
     * `{prefix, suffix, encode}`, where `encode` is `component` (the whole
     * path is one query value) or `path` (the slashes stay).
     *
     * @return array{prefix: string, suffix: string, encode: 'component'|'path'}
     */
    public static function base(string $disk): array
    {
        if (self::servedByPanel($disk)) {
            [$prefix, $suffix] = self::split(UploadController::serveUrl($disk, self::MARK));

            return ['prefix' => $prefix, 'suffix' => $suffix, 'encode' => 'component'];
        }
        [$prefix, $suffix] = self::split(Storage::disk($disk)->url(self::MARK));

        return ['prefix' => $prefix, 'suffix' => $suffix, 'encode' => 'path'];
    }

    /**
     * A local disk without a public URL: its files are reachable only through
     * the panel.
     */
    public static function servedByPanel(string $disk): bool
    {
        $config = config("filesystems.disks.{$disk}");
        if (! is_array($config)) {
            throw new InvalidArgumentException("Disk `{$disk}` is not configured in filesystems.disks");
        }

        return ($config['driver'] ?? 'local') === 'local'
            && (! isset($config['url']) || $config['url'] === '');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function split(string $url): array
    {
        $at = strpos($url, self::MARK);
        if ($at === false) {
            // A driver that rewrites the path beyond recognition: the base is
            // what precedes the file name, the best guess there is.
            return [rtrim(dirname($url), '/').'/', ''];
        }

        return [substr($url, 0, $at), substr($url, $at + strlen(self::MARK))];
    }
}
