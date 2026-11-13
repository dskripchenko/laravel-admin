<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Support;

use Dskripchenko\LaravelAdmin\I18n\Localize;
use Dskripchenko\LaravelAdmin\Permission\PermissionCheck;

/**
 * A search of the host's own in the ⌘K palette — a policy by its number, a
 * client by phone — beside the panel's search across the resources.
 *
 *   $admin->searchProviders([
 *       SearchProvider::make('policies', 'Договоры')
 *           ->recognizes('/^\d{3}-\d{6,}$/')      // the number's shape: the group goes on top
 *           ->priority(100)
 *           ->permission('admin.contracts.view')
 *           ->using(fn (string $q, ?Model $user): array => Contract::where('number', $q)->limit(5)->get()
 *               ->map(fn (Contract $c) => [
 *                   'title' => $c->number,
 *                   'subtitle' => Mask::name($c->holder_name),   // masking is the provider's business
 *                   'url' => '/screens/contract?number='.$c->number,
 *               ])->all()),
 *       SearchProvider::make('clients', 'Клиенты')->recognizesPhone()->onlyWhenRecognized()->using(...),
 *   ]);
 *
 * The recognizer — a regular expression or a closure over the query — tells
 * that the input looks like what this provider finds. A recognized group goes
 * above the others (by priority, then in registration order); with
 * onlyWhenRecognized() the provider does not run at all otherwise. Without a
 * recognizer the provider runs on every query and keeps its place by priority.
 *
 * What it returns is shown as it is: masking personal data is the provider's
 * business — the palette never sees anything else.
 */
final class SearchProvider
{
    /** A Russian or international phone: +7 999 123-45-67, 8 (999) 123 45 67, 9991234567. */
    public const PHONE = '/^\+?[\d\s()\-]{10,20}$/u';

    /** Anything with an @ in it. */
    public const EMAIL = '/@/';

    /** @var string|\Closure(string): bool|null */
    private string|\Closure|null $recognizer = null;

    private bool $onlyWhenRecognized = false;

    private int $priority = 0;

    private ?string $icon = null;

    /** @var list<string> */
    private array $permissions = [];

    /** @var (\Closure(string, ?object): array<int, mixed>)|null */
    private ?\Closure $resolver = null;

    private int $limit = 5;

    private function __construct(private readonly string $key, private readonly string $label) {}

    public static function make(string $key, string $label): self
    {
        return new self($key, $label);
    }

    /**
     * What the input looks like when it is for this provider: a regular
     * expression, matched against the trimmed query, or a closure returning
     * a boolean.
     *
     * @param  string|\Closure(string): bool  $recognizer
     */
    public function recognizes(string|\Closure $recognizer): self
    {
        $this->recognizer = $recognizer;

        return $this;
    }

    public function recognizesPhone(): self
    {
        return $this->recognizes(static fn (string $q): bool => preg_match(self::PHONE, $q) === 1
            && strlen((string) preg_replace('/\D/', '', $q)) >= 10);
    }

    public function recognizesEmail(): self
    {
        return $this->recognizes(self::EMAIL);
    }

    /** Runs only when the recognizer recognizes the query. */
    public function onlyWhenRecognized(bool $only = true): self
    {
        $this->onlyWhenRecognized = $only;

        return $this;
    }

    /** The higher, the higher the group stands among the recognized ones (and among the rest). */
    public function priority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    public function icon(?string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Every key is required; a user without them does not get this group.
     *
     * @param  list<string>|string|null  $permissions
     */
    public function permission(array|string|null $permissions): self
    {
        $this->permissions = PermissionCheck::normalize($permissions);

        return $this;
    }

    /** At most this many items are shown; one more tells there are more. */
    public function limit(int $limit): self
    {
        $this->limit = max(1, $limit);

        return $this;
    }

    /**
     * The search itself: `fn (string $query, ?object $user): array` returning
     * items `['title' => …, 'subtitle' => …, 'url' => '/r/…']`; a url outside
     * the panel (`https://…`) opens as a link.
     *
     * @param  \Closure(string, ?object): array<int, mixed>  $resolver
     */
    public function using(\Closure $resolver): self
    {
        $this->resolver = $resolver;

        return $this;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function recognizesQuery(string $query): bool
    {
        if ($this->recognizer === null) {
            return false;
        }
        $query = trim($query);
        if ($this->recognizer instanceof \Closure) {
            return (bool) ($this->recognizer)($query);
        }

        return @preg_match($this->recognizer, $query) === 1;
    }

    /**
     * The provider's group for a query, or null when it has nothing — or must
     * not run for this user or this query.
     *
     * @return array{slug: string, label: string, icon: string|null, items: list<array{id: mixed, title: string, subtitle: string|null, url: string}>, hasMore: bool, moreUrl: string|null, recognized: bool, priority: int}|null
     */
    public function search(string $query, ?object $user): ?array
    {
        if ($this->resolver === null || ! PermissionCheck::allows($this->permissions, $user)) {
            return null;
        }
        $recognized = $this->recognizesQuery($query);
        if ($this->onlyWhenRecognized && ! $recognized) {
            return null;
        }

        $rows = ($this->resolver)(trim($query), $user);
        $items = [];
        foreach (array_values($rows) as $i => $row) {
            if (! is_array($row) || ! is_string($row['url'] ?? null) || $row['url'] === '') {
                continue;
            }
            $items[] = [
                'id' => $row['id'] ?? $i,
                'title' => (string) ($row['title'] ?? $row['url']),
                'subtitle' => isset($row['subtitle']) ? (string) $row['subtitle'] : null,
                'url' => $row['url'],
            ];
        }
        if ($items === []) {
            return null;
        }

        return [
            'slug' => 'provider.'.$this->key,
            'label' => (string) Localize::string($this->label),
            'icon' => $this->icon,
            'items' => array_slice($items, 0, $this->limit),
            'hasMore' => count($items) > $this->limit,
            'moreUrl' => null,
            'recognized' => $recognized,
            'priority' => $this->priority,
        ];
    }
}
