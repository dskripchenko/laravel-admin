<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

use Closure;
use Dskripchenko\LaravelAdmin\I18n\Localize;
use InvalidArgumentException;

/**
 * A feed of events of different kinds — letters, processes, calls to an
 * outside system, audit records — on one time line, newest first, with chips
 * that filter it by kind (each with its count) and «Только ошибки».
 *
 *     Layout::timeline('events')                    // a state key the screen fills
 *         ->types([
 *             'mail' => ['label' => 'Письма', 'icon' => 'mail', 'tone' => 'info'],
 *             'process' => ['label' => 'Процессы', 'icon' => 'workflow'],
 *             'kias' => ['label' => 'КИАС', 'icon' => 'server'],
 *             'audit' => ['label' => 'Аудит', 'icon' => 'shield'],
 *         ])
 *         ->title('События');
 *
 * An event is an array:
 *
 *     ['type' => 'kias', 'title' => 'Выпуск полиса', 'time' => '2026-11-13T14:12:00+03:00',
 *      'details' => 'Таймаут 30 с', 'tone' => 'danger', 'url' => '/admin/r/processes/41',
 *      'linkLabel' => 'Процесс #41', 'linkTarget' => 'drawer']
 *
 * `tone` (neutral, info, success, warning, danger) and `icon` fall back to
 * the type's; `error: true` (or the danger tone) puts it under «Только
 * ошибки»; `details` folds under «Подробнее» — a string, or a list of
 * `['label' => …, 'value' => …]` lines. `time` is an ISO date, shown in the
 * panel's locale, or any text. The data is the screen's business: the source
 * is a state key, a list, or a closure resolved when the screen is
 * serialized.
 */
final class Timeline extends Layout
{
    /** @var string|array<int, array<string, mixed>>|Closure(): array<int, array<string, mixed>> */
    private string|array|Closure $source;

    /**
     * @param  string|array<int, array<string, mixed>>|callable(): array<int, array<string, mixed>>  $events
     */
    public static function make(string|array|callable $events): self
    {
        $instance = new self;
        $instance->source = is_string($events) || is_array($events) ? $events : Closure::fromCallable($events);

        return $instance;
    }

    public function type(): string
    {
        return 'timeline';
    }

    /**
     * The kinds of events: key => label, or key => ['label', 'icon', 'tone'].
     * The chips go in this order; a kind without events shows no chip.
     *
     * @param  array<string, string|array{label?: string, icon?: string, tone?: string}>  $types
     */
    public function types(array $types): self
    {
        $out = [];
        foreach ($types as $key => $type) {
            $type = is_string($type) ? ['label' => $type] : $type;
            $out[] = [
                'key' => (string) $key,
                'label' => (string) Localize::string($type['label'] ?? (string) $key),
                'icon' => $type['icon'] ?? null,
                'tone' => $type['tone'] ?? null,
            ];
        }
        $this->props['types'] = $out;

        return $this;
    }

    public function title(string $title): self
    {
        $this->props['title'] = $title;

        return $this;
    }

    /**
     * What the feed says without events.
     */
    public function emptyText(string $text): self
    {
        $this->props['emptyText'] = Localize::string($text);

        return $this;
    }

    /**
     * Shows the newest `$limit` and «Показать ещё» for the rest.
     */
    public function limit(int $limit): self
    {
        if ($limit < 1) {
            throw new InvalidArgumentException('Timeline::limit() expects a positive number');
        }
        $this->props['limit'] = $limit;

        return $this;
    }

    /**
     * Without the chips — a short feed of one kind.
     */
    public function withoutFilters(): self
    {
        $this->props['filters'] = false;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        if (is_string($this->source)) {
            $this->props['source'] = $this->source;
            unset($this->props['events']);
        } else {
            $events = $this->source instanceof Closure ? ($this->source)() : $this->source;
            $this->props['events'] = array_values(array_map(static fn ($e): array => (array) $e, $events));
            unset($this->props['source']);
        }

        return parent::toArray();
    }
}
