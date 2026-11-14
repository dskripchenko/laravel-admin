<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\DelayedProcess;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Who started a delayed process through the panel.
 *
 * The delayed-process table has no owner column, so the panel keeps the owner
 * in the cache, keyed by the process's uuid, for `admin.delayed.owner_ttl`
 * seconds (a day by default). `delayed/status` answers the owner; a host that
 * starts a process in code of its own and lets the SPA poll it calls
 * remember() with the uuid.
 */
final class ProcessOwnership
{
    private const PREFIX = 'admin.delayed.owner.';

    public static function remember(string $uuid, Model $owner): void
    {
        Cache::put(
            self::PREFIX.$uuid,
            self::identity($owner),
            max(60, (int) config('admin.delayed.owner_ttl', 86400)),
        );
    }

    public static function owns(string $uuid, ?Model $user): bool
    {
        if ($user === null) {
            return false;
        }

        $stored = Cache::get(self::PREFIX.$uuid);

        return is_string($stored) && hash_equals($stored, self::identity($user));
    }

    private static function identity(Model $user): string
    {
        return $user->getMorphClass().'|'.(string) $user->getKey();
    }
}
