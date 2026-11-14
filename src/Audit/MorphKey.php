<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Audit;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * The cast of the audit log's morph ids — actor_id, subject_id,
 * impersonator_id.
 *
 * The columns are strings (the 2026_11_15 migration), so that a record keyed
 * by a uuid, a ulid or any other string can be audited as well as one keyed
 * by an integer. A key is written as its text; on reading, a canonical
 * integer — digits with no leading zero, within PHP's range — comes back as
 * an int, as it did while the columns were bigint, and anything else as the
 * string it was.
 *
 * @implements CastsAttributes<int|string|null, mixed>
 */
final class MorphKey implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): int|string|null
    {
        if ($value === null || is_int($value)) {
            return $value;
        }
        $text = self::toString($value);
        if ($text === null) {
            return null;
        }

        return self::isCanonicalInt($text) ? (int) $text : $text;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return self::toString($value);
    }

    /**
     * A model's key the way the log stores and looks it up: a string, or
     * null. A key that is neither an integer nor a string — an array, a plain
     * object — is not one the log can name, and gives null.
     */
    public static function toString(mixed $key): ?string
    {
        if (is_int($key) || is_string($key) || $key instanceof \Stringable) {
            return (string) $key;
        }

        return null;
    }

    private static function isCanonicalInt(string $text): bool
    {
        return preg_match('/^(0|-?[1-9]\d{0,18})$/', $text) === 1
            && (string) (int) $text === $text;
    }
}
