<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Sessions ended by a sign-out stay ended.
 *
 * A sign-out invalidates the session, but a request that was already running
 * with it — the shell's manifest, a status poll — saves the session back when
 * it finishes, under the old id, and sends the old cookie with it. The
 * session comes back to life and the person is signed in again on the next
 * page load. The sign-out remembers the ended id for the session's lifetime;
 * a request carrying it is signed out and gets a fresh session.
 *
 * The cache being down must not take the panel with it: a failed lookup is
 * reported and the request goes on as before.
 */
final class EndedSessions
{
    private const PREFIX = 'admin:ended-session:';

    public static function end(string $sessionId): void
    {
        if ($sessionId === '') {
            return;
        }
        try {
            Cache::put(self::key($sessionId), true, now()->addMinutes(max(1, (int) config('session.lifetime', 120))));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Signs the request out of `$guard` when its session was ended.
     *
     * @return bool whether it was
     */
    public static function clearIfEnded(Request $request, string $guard): bool
    {
        if (! $request->hasSession()) {
            return false;
        }
        try {
            if (! Cache::has(self::key($request->session()->getId()))) {
                return false;
            }
        } catch (\Throwable $e) {
            report($e);

            return false;
        }

        Auth::guard($guard)->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return true;
    }

    private static function key(string $sessionId): string
    {
        return self::PREFIX.hash('sha256', $sessionId);
    }
}
