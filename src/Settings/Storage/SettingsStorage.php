<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Settings\Storage;

/**
 * The contract of a settings store.
 *
 * The implementations are KeyValueSettingsStorage, over the admin_settings
 * table, and EloquentSettingsStorage, over a dedicated Eloquent model with
 * typed columns.
 *
 * Every method works within a `group` — a namespace, usually the
 * SettingsResource's slug.
 */
interface SettingsStorage
{
    /**
     * Returns every setting of a group.
     *
     * @return array<string, mixed>
     */
    public function all(string $group): array;

    /**
     * Returns a single value.
     */
    public function get(string $group, string $key, mixed $default = null): mixed;

    /**
     * Saves a group in bulk. The existing keys that $values does not mention
     * are left alone — the semantics are a merge.
     *
     * @param  array<string, mixed>  $values
     */
    public function save(string $group, array $values): void;

    /**
     * Resets a group entirely: the old keys are removed and the new ones written.
     *
     * @param  array<string, mixed>  $values
     */
    public function replace(string $group, array $values): void;

    /**
     * Removes a single value.
     */
    public function forget(string $group, string $key): void;
}
