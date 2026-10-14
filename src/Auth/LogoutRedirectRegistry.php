<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

/**
 * Where the browser goes after a sign-out, per panel: an identity provider's
 * own sign-out ("RP-initiated logout"), so that leaving the panel ends the
 * session the person signed in with, not just the panel's.
 *
 * A resolver runs before the panel's session is cleared — what it needs (an
 * ID token kept at sign-in) is still there — and returns an absolute http(s)
 * address or a path, or null to stay out of it. The first address wins. The
 * SPA follows it instead of opening its own login page.
 *
 * A resolver that throws is reported and skipped: the sign-out itself has
 * already happened and must not fail because a provider is down.
 */
final class LogoutRedirectRegistry
{
    /** @var array<string, list<callable(Request, string, Authenticatable|null): (string|null)>> */
    private array $resolvers = [];

    /**
     * @param  callable(Request, string, Authenticatable|null): (string|null)  $resolver
     *                                                                                    Gets the request, the panel's id and the user signing out.
     */
    public function add(string $panel, callable $resolver): void
    {
        $this->resolvers[$panel][] = $resolver;
    }

    public function for(Request $request, string $panel, ?Authenticatable $user): ?string
    {
        foreach ($this->resolvers[$panel] ?? [] as $resolver) {
            try {
                $url = $resolver($request, $panel, $user);
            } catch (\Throwable $e) {
                report($e);

                continue;
            }
            if (is_string($url) && self::followable($url)) {
                return $url;
            }
        }

        return null;
    }

    /** An absolute http(s) address or a path — never a script or a data URL. */
    private static function followable(string $url): bool
    {
        if (str_starts_with($url, '/')) {
            return ! str_starts_with($url, '//');
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) && is_string(parse_url($url, PHP_URL_HOST));
    }
}
