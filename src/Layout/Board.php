<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

use InvalidArgumentException;

/**
 * A collaborative board — BoardEditor of @dskripchenko/board — edited by
 * several people at once: changes, cursors and selections travel live.
 *
 * This is the drawing half only. The board's storage, the order of changes
 * and the endpoints come from a package (dskripchenko/laravel-admin-board),
 * which builds this layout with its props:
 *
 *     Layout::board()
 *         ->endpoint('/boards/42')                 // the board's API, relative to the panel's client
 *         ->user(['id' => '7', 'name' => 'Anna', 'color' => '#ec4899'])
 *         ->realtime(['ticketUrl' => '/realtime/ticket', 'channel' => 'admin-board.42'])
 *         ->readonly(! $canEdit)
 *         ->height('70vh')
 *
 * The client loads the board from `{endpoint}/snapshot`, sends changes to
 * `{endpoint}/batches`, follows the others' changes on the realtime channel
 * (when the host has laravel-realtime) or by polling `{endpoint}/poll`, and
 * shares cursors through `{endpoint}/presence`.
 */
final class Board extends Layout
{
    /** The smallest poll interval accepted, ms. */
    public const MIN_POLL_INTERVAL = 250;

    private ?string $endpoint = null;

    private bool $readonly = false;

    /** @var array{id: ?string, name: ?string, color: ?string}|null */
    private ?array $user = null;

    /** @var array{ticketUrl: string, channel: string}|null */
    private ?array $realtime = null;

    private int $pollInterval = 1500;

    public static function make(): self
    {
        return new self;
    }

    public function type(): string
    {
        return 'board';
    }

    /** The board's API path, relative to the panel's client: '/boards/42'. */
    public function endpoint(string $endpoint): self
    {
        $this->endpoint = self::checkEndpoint($endpoint, 'Layout::board()');

        return $this;
    }

    /** View only: no changes are sent (the server checks the right all the same). */
    public function readonly(bool $readonly = true): self
    {
        $this->readonly = $readonly;

        return $this;
    }

    /**
     * Who is editing, as the others see them next to the cursor.
     *
     * @param  array{id?: string|int, name?: string, color?: string}  $user
     */
    public function user(array $user): self
    {
        $this->user = self::normalizeUser($user);

        return $this;
    }

    /**
     * The laravel-realtime channel the server publishes the board's changes
     * on, and where the browser gets its ticket; null polls instead.
     *
     * @param  array{ticketUrl: string, channel: string}|null  $realtime
     */
    public function realtime(?array $realtime): self
    {
        $this->realtime = self::normalizeRealtime($realtime, 'Layout::board()');

        return $this;
    }

    /** How often the others' changes are polled without realtime, ms. */
    public function pollInterval(int $ms): self
    {
        $this->pollInterval = self::checkPollInterval($ms, 'Layout::board()');

        return $this;
    }

    /** A caption above the board. */
    public function title(string $title): self
    {
        $this->props['title'] = $title;

        return $this;
    }

    /** The height of the box: 560 (px) or '70vh'; 600px by default. */
    public function height(int|string $height): self
    {
        $this->props['height'] = self::checkHeight($height, 'Layout::board()');

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        if ($this->endpoint === null) {
            throw new InvalidArgumentException('Layout::board() needs ->endpoint()');
        }
        $array = parent::toArray();
        // Addresses, a channel and a person's name: added after the props are
        // localized, as Flow adds its model, so nothing here is translated.
        $array['endpoint'] = $this->endpoint;
        $array['readonly'] = $this->readonly;
        $array['user'] = $this->user;
        $array['realtime'] = $this->realtime;
        $array['pollInterval'] = $this->pollInterval;

        return $array;
    }

    /** @internal Shared with Widget\BoardWidget. */
    public static function checkEndpoint(string $endpoint, string $where): string
    {
        $endpoint = rtrim(trim($endpoint), '/');
        if (! str_starts_with($endpoint, '/') || str_starts_with($endpoint, '//') || preg_match('/\s/', $endpoint) === 1) {
            throw new InvalidArgumentException("{$where}->endpoint() takes a path relative to the panel's API, like '/boards/42', `{$endpoint}` given");
        }

        return $endpoint;
    }

    /** @internal */
    public static function checkPollInterval(int $ms, string $where): int
    {
        if ($ms < self::MIN_POLL_INTERVAL) {
            throw new InvalidArgumentException("{$where}->pollInterval() is at least ".self::MIN_POLL_INTERVAL." ms, {$ms} given");
        }

        return $ms;
    }

    /** @internal */
    public static function checkHeight(int|string $height, string $where): string
    {
        if (is_int($height)) {
            if ($height <= 0) {
                throw new InvalidArgumentException("{$where}->height() must be positive, {$height} given");
            }

            return $height.'px';
        }
        $height = trim($height);
        if (preg_match('/^\d+(\.\d+)?(px|%|vh|vw|dvh|svh|lvh|em|rem)$/', $height) !== 1) {
            throw new InvalidArgumentException("{$where}->height() takes pixels or a CSS length like '70vh', `{$height}` given");
        }

        return $height;
    }

    /**
     * @internal
     *
     * @param  array<string, mixed>  $user
     * @return array{id: ?string, name: ?string, color: ?string}
     */
    public static function normalizeUser(array $user): array
    {
        $text = static fn (mixed $v): ?string => is_scalar($v) && (string) $v !== '' ? (string) $v : null;

        return ['id' => $text($user['id'] ?? null), 'name' => $text($user['name'] ?? null), 'color' => $text($user['color'] ?? null)];
    }

    /**
     * @internal
     *
     * @param  array<string, mixed>|null  $realtime
     * @return array{ticketUrl: string, channel: string}|null
     */
    public static function normalizeRealtime(?array $realtime, string $where): ?array
    {
        if ($realtime === null) {
            return null;
        }
        $ticketUrl = $realtime['ticketUrl'] ?? null;
        $channel = $realtime['channel'] ?? null;
        if (! is_string($ticketUrl) || $ticketUrl === '' || ! is_string($channel) || $channel === '') {
            throw new InvalidArgumentException("{$where}->realtime() takes ['ticketUrl' => …, 'channel' => …] or null");
        }

        return ['ticketUrl' => $ticketUrl, 'channel' => $channel];
    }
}
