<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Widget;

use Dskripchenko\LaravelAdmin\Layout\Board;
use InvalidArgumentException;

/**
 * A collaborative board on a dashboard: Layout::board() in the widget's
 * frame. Like the layout, it only draws: a package that stores boards
 * (dskripchenko/laravel-admin-board) builds it with the board's endpoint.
 *
 *     BoardWidget::make()->title('Sprint board')->size(12)->rowSpan(4)
 *         ->endpoint('/boards/42')->user([...])->realtime([...])
 *
 * Not offered in the dashboard's «Add widget» dialog: it needs a board the
 * package creates.
 */
class BoardWidget extends Widget
{
    protected ?int $rowSpan = 4;

    private ?string $endpoint = null;

    private bool $readonly = false;

    /** @var array{id: ?string, name: ?string, color: ?string}|null */
    private ?array $user = null;

    /** @var array{ticketUrl: string, channel: string}|null */
    private ?array $realtime = null;

    private int $pollInterval = 1500;

    public function widgetType(): string
    {
        return 'board';
    }

    /** The board's API path, relative to the panel's client: '/boards/42'. */
    public function endpoint(string $endpoint): static
    {
        $this->endpoint = Board::checkEndpoint($endpoint, 'BoardWidget');

        return $this;
    }

    public function readonly(bool $readonly = true): static
    {
        $this->readonly = $readonly;

        return $this;
    }

    /** @param  array{id?: string|int, name?: string, color?: string}  $user */
    public function user(array $user): static
    {
        $this->user = Board::normalizeUser($user);

        return $this;
    }

    /** @param  array{ticketUrl: string, channel: string}|null  $realtime */
    public function realtime(?array $realtime): static
    {
        $this->realtime = Board::normalizeRealtime($realtime, 'BoardWidget');

        return $this;
    }

    public function pollInterval(int $ms): static
    {
        $this->pollInterval = Board::checkPollInterval($ms, 'BoardWidget');

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        if ($this->endpoint === null) {
            throw new InvalidArgumentException('BoardWidget needs ->endpoint()');
        }

        return [
            'endpoint' => $this->endpoint,
            'readonly' => $this->readonly,
            'user' => $this->user,
            'realtime' => $this->realtime,
            'pollInterval' => $this->pollInterval,
        ];
    }
}
