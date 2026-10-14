<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Auth;

use Illuminate\Http\Request;

/**
 * The login page's external ways in, per panel ({@see ExternalLogin}).
 *
 * Registered as resolvers, not as a fixed list: what a page offers can depend
 * on the request — a client's own identity provider behind a link of its own,
 * a provider switched off a minute ago. A resolver runs while the guest's
 * bootstrap is built; in the `xhr` bootstrap strategy that request is the
 * bootstrap call, not the page, so state a resolver needs from the page is
 * better kept in the session than in the query string.
 *
 * A resolver that throws is reported and skipped: a broken way in must not
 * take the password form down with it.
 */
final class ExternalLoginRegistry
{
    /** @var array<string, list<callable(Request, string): iterable<ExternalLogin|array<string, mixed>>>> */
    private array $resolvers = [];

    /**
     * @param  callable(Request, string): iterable<ExternalLogin|array<string, mixed>>  $resolver
     *                                                                                             Gets the request and the panel's id.
     */
    public function add(string $panel, callable $resolver): void
    {
        $this->resolvers[$panel][] = $resolver;
    }

    /**
     * @return list<array{label: string, url: string, icon: string|null, description: string|null}>
     */
    public function for(Request $request, string $panel): array
    {
        $out = [];
        $seen = [];
        foreach ($this->resolvers[$panel] ?? [] as $resolver) {
            try {
                foreach ($resolver($request, $panel) as $item) {
                    $login = $item instanceof ExternalLogin ? $item : self::fromArray($item);
                    if ($login === null || isset($seen[$login->url])) {
                        continue;
                    }
                    $seen[$login->url] = true;
                    $out[] = $login->toArray();
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $out;
    }

    /** @param array<string, mixed> $item */
    private static function fromArray(array $item): ?ExternalLogin
    {
        $label = $item['label'] ?? null;
        $url = $item['url'] ?? null;
        if (! is_string($label) || $label === '' || ! is_string($url) || $url === '') {
            return null;
        }

        return new ExternalLogin(
            $label,
            $url,
            is_string($item['icon'] ?? null) ? $item['icon'] : null,
            is_string($item['description'] ?? null) ? $item['description'] : null,
        );
    }
}
