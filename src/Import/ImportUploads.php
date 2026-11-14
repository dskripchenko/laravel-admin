<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Import;

use Illuminate\Database\Eloquent\Model;

/**
 * Where the import's files live and whose they are.
 *
 * The disk is always `admin.imports.disk`, never one a request names. Every
 * user uploads into a folder of their own under `admin.imports.directory`,
 * named by a keyed hash of their identity, and preview and start accept a path
 * only from the signed-in user's own folder: a file another user uploaded, or
 * any other file on the disk, cannot be read or imported by naming its path.
 */
final class ImportUploads
{
    public static function disk(): string
    {
        return (string) config('admin.imports.disk', 'local');
    }

    public static function directoryFor(Model $user): string
    {
        $root = trim((string) config('admin.imports.directory', 'imports'), '/');
        $identity = $user->getMorphClass().'|'.(string) $user->getKey();
        $token = substr(hash_hmac('sha256', $identity, (string) config('app.key', 'laravel-admin')), 0, 32);

        return ($root === '' ? '' : $root.'/').$token;
    }

    /**
     * Whether the path names a file directly inside the user's own upload
     * folder. Anything that could step out of it — `..`, `.`, a backslash, an
     * absolute path, a NUL byte, a nested folder — is refused.
     */
    public static function ownedBy(string $path, Model $user): bool
    {
        if (! self::isSafeRelativePath($path)) {
            return false;
        }

        $directory = self::directoryFor($user).'/';
        if (! str_starts_with($path, $directory)) {
            return false;
        }

        $name = substr($path, strlen($directory));

        return $name !== '' && ! str_contains($name, '/');
    }

    /**
     * A relative path made of plain segments only.
     */
    public static function isSafeRelativePath(string $path): bool
    {
        if ($path === '' || str_contains($path, "\0") || str_contains($path, '\\')) {
            return false;
        }
        if (str_starts_with($path, '/') || preg_match('/^[a-z][a-z0-9+.-]*:/i', $path) === 1) {
            return false;
        }
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }
}
